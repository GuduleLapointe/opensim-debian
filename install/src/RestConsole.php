<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * The remote (REST) console of a Robust or an OpenSimulator instance, which
 * gives every command of its console through one port: the same way to reach an
 * instance on this machine, in a container, or on another machine.
 *
 * The protocol, as OpenSimulator serves it (Framework/Console/RemoteConsole.cs):
 *  - StartSession: a form with USER and PASS, answered with a SessionID (401
 *    when they are wrong);
 *  - SessionCommand: a form with ID and COMMAND, a line typed in the console;
 *  - ReadResponses/<ID>/: the lines the console wrote since the last reading,
 *    waiting (up to 25 seconds) when there are none. A line has Input when it
 *    is the echo of what was typed, and Prompt when the console waits for input
 *    (Command as well when it is for a new command, else it is a question);
 *  - CloseSession: a form with ID.
 *
 * The console is one for everybody: the lines typed in a session go to the
 * console of the instance, and every session sees all the output.
 */
final class RestConsole
{
    private string $session = '';
    /** @var string the last prompt the console showed */
    public string $prompt = '';
    /** @var string why it could not connect or authenticate */
    public string $error = '';

    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
        private string $scheme = 'http',
    ) {
    }

    /**
     * The console of an instance on this machine, from its config: the port,
     * the user and the password of [Network] (ConsolePort or console_port, then
     * ConsoleUser and ConsolePass), reached through the local address.
     */
    public static function fromIni(string $ini): ?self
    {
        $text = (string) @file_get_contents($ini);
        $get = static function (string $key) use ($text): ?string {
            return preg_match('/^\s*' . $key . '\s*=\s*"?([^"\r\n]*?)"?\s*$/im', $text, $m) ? $m[1] : null;
        };
        $port = $get('ConsolePort') ?? $get('console_port');
        $user = $get('ConsoleUser');
        $password = $get('ConsolePass');
        if ($port === null || !ctype_digit($port) || (int) $port === 0 || $user === null || $user === '' || $password === null || $password === '') {
            return null;
        }

        return new self('127.0.0.1', (int) $port, $user, $password);
    }

    /** Open the session. */
    public function connect(): bool
    {
        [$status, $body] = $this->post('/StartSession/', ['USER' => $this->user, 'PASS' => $this->password], 5);
        if ($status === 401) {
            $this->error = 'the user or the password of the console is wrong';

            return false;
        }
        if ($status === 0) {
            $this->error = "cannot reach the console at {$this->host}:{$this->port} (the instance is not running, or not with its remote console)";

            return false;
        }
        if (!preg_match('#<SessionID>([^<]+)</SessionID>#', $body, $m)) {
            $this->error = "no session from the console at {$this->host}:{$this->port} (HTTP $status)";

            return false;
        }
        $this->session = $m[1];
        if (preg_match('#<Prompt>([^<]*)</Prompt>#', $body, $p)) {
            $this->prompt = html_entity_decode($p[1], ENT_QUOTES | ENT_XML1);
        }
        // What the console showed before: not an answer to anything
        $this->read(1.0);

        return true;
    }

    /**
     * Type a line in the console, and give what it answers: the lines written
     * until the console waits for input again (a command prompt: done, or a
     * question: the console wants an answer, typed with the next call), or until
     * $wait seconds pass. An instance that stops, like after shutdown, ends the
     * connection: not an error.
     *
     * @return array{lines:list<string>,prompt:string,question:bool,closed:bool}
     */
    public function command(string $line, float $wait = 5.0): array
    {
        $result = ['lines' => [], 'prompt' => $this->prompt, 'question' => false, 'closed' => false];
        [$status] = $this->post('/SessionCommand/', ['ID' => $this->session, 'COMMAND' => $line], 5);
        if ($status !== 200) {
            $result['closed'] = true;

            return $result;
        }

        $echoed = false;
        $end = microtime(true) + $wait;
        while (microtime(true) < $end) {
            $started = microtime(true);
            $entries = $this->read(min(2.0, max(0.2, $end - microtime(true))));
            if ($entries === [] && microtime(true) - $started < 0.05) {
                usleep(100000);
            }
            if ($entries === null) {
                $result['closed'] = true;
                break;
            }
            foreach ($entries as $entry) {
                if ($entry['input']) {
                    $echoed = true;

                    continue;
                }
                if ($entry['prompt']) {
                    $this->prompt = $entry['text'];
                    $result['prompt'] = $entry['text'];
                    $result['question'] = !$entry['command'];

                    return $result;
                }
                if ($echoed) {
                    $result['lines'][] = $entry['text'];
                }
            }
        }

        return $result;
    }

    public function close(): void
    {
        if ($this->session !== '') {
            $this->post('/CloseSession/', ['ID' => $this->session], 2);
            $this->session = '';
        }
    }

    /**
     * The lines written since the last reading.
     *
     * @return list<array{text:string,input:bool,prompt:bool,command:bool}>|null null when the console is gone
     */
    private function read(float $timeout): ?array
    {
        [$status, $body] = $this->post("/ReadResponses/{$this->session}/", [], $timeout);
        if ($status === 0 && $timeout >= 1.0 && $body === '') {
            // A reading that waits for output and finds none ends by timeout
            return [];
        }
        if ($status !== 200) {
            return $status === 0 ? [] : null;
        }

        $entries = [];
        if (preg_match_all('#<Line\b([^>]*?)(?:/>|>(.*?)</Line>)#s', $body, $lines, PREG_SET_ORDER)) {
            foreach ($lines as $line) {
                preg_match_all('/(\w+)="([^"]*)"/', $line[1], $attributes);
                $attribute = array_combine($attributes[1], $attributes[2]) ?: [];
                $entries[] = [
                    'text' => html_entity_decode($line[2] ?? '', ENT_QUOTES | ENT_XML1),
                    'input' => ($attribute['Input'] ?? '') === 'true',
                    'prompt' => ($attribute['Prompt'] ?? '') === 'true',
                    'command' => ($attribute['Command'] ?? '') === 'true',
                ];
            }
        }

        return $entries;
    }

    /**
     * A POST of a form.
     *
     * @param array<string,string> $fields
     * @return array{0:int,1:string} [HTTP status, 0 when nothing answered; body]
     */
    private function post(string $path, array $fields, float $timeout): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
            'content' => http_build_query($fields),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents("{$this->scheme}://{$this->host}:{$this->port}$path", false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
                $status = (int) $m[1];
            }
        }

        return [$status, $body === false ? '' : $body];
    }
}
