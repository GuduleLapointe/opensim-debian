<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * Line-oriented INI editor that preserves comments and structure.
 *
 * Surgical edits like crudini: setting a key replaces it in place (uncommenting
 * a commented occurrence), adds it to the section if absent, or appends the
 * section if needed. Everything else in the file is left untouched.
 */
final class Ini
{
    /** @var list<string> */
    private array $lines;

    public function __construct(string $content)
    {
        $this->lines = explode("\n", $content);
    }

    public static function load(string $path): self
    {
        return new self(is_file($path) ? TextFile::read($path) : '');
    }

    /**
     * Set key = value in section (uncommenting an existing commented key).
     *
     * A key the section does not have goes after the last commented example of
     * the same family when $near is given (a prefix such as "Region_"), else at the
     * end of the section's own lines: the comments before the next section
     * (separated from the lines above by a blank one) document that section, the
     * key does not go between them and it.
     */
    public function set(string $section, string $key, string $value, ?string $near = null): void
    {
        $newLine = "$key = $value";
        $keyRe = '/^\s*;?\s*' . preg_quote($key, '/') . '\s*=/';

        $sectionStart = -1;
        $inTarget = false;
        $example = null;
        $nearRe = $near === null ? null : '/^\s*;+\s*' . preg_quote($near, '/') . '/';

        foreach ($this->lines as $i => $text) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $text, $m)) {
                if ($inTarget) {
                    // Reached the next section without finding the key: insert it
                    // after the example of its family, else after the section's last
                    // content line, before trailing blanks.
                    array_splice($this->lines, $example !== null ? $example + 1 : $this->endOfContent($i), 0, [
                        $newLine,
                    ]);

                    return;
                }
                if (strcasecmp(trim($m[1]), $section) === 0) {
                    $inTarget = true;
                    $sectionStart = $i;
                }

                continue;
            }

            if ($inTarget && preg_match($keyRe, $text)) {
                $this->lines[$i] = $newLine;

                return;
            }
            if ($inTarget && $nearRe !== null && preg_match($nearRe, $text)) {
                $example = $i;
            }
        }

        if ($sectionStart === -1) {
            if ($this->lines !== [] && end($this->lines) !== '') {
                $this->lines[] = '';
            }
            $this->lines[] = "[$section]";
            $this->lines[] = $newLine;

            return;
        }

        // Target section is the last one and the key was absent: append.
        if ($example !== null) {
            array_splice($this->lines, $example + 1, 0, [$newLine]);

            return;
        }
        $this->lines[] = $newLine;
    }

    /**
     * Where the lines of a section end, given the header of the next one: before
     * the blank lines, and before the comments that document the next section
     * (a comment block after a blank line).
     */
    private function endOfContent(int $header): int
    {
        $at = $header;
        while ($at > 0 && trim($this->lines[$at - 1]) === '') {
            $at--;
        }
        $comments = $at;
        while ($comments > 0 && preg_match('/^\s*[;#]/', $this->lines[$comments - 1])) {
            $comments--;
        }
        if ($comments < $at && $comments > 0 && trim($this->lines[$comments - 1]) === '') {
            $at = $comments;
            while ($at > 0 && trim($this->lines[$at - 1]) === '') {
                $at--;
            }
        }

        return $at;
    }

    /**
     * Apply a section => [key => value] map.
     *
     * @param array<string,array<string,string>> $data
     */
    public function merge(array $data): void
    {
        foreach ($data as $section => $pairs) {
            foreach ($pairs as $key => $value) {
                $this->set($section, $key, (string) $value);
            }
        }
    }

    /** Uncomment every "; key = …" occurrence in the file (crudini-free). */
    public function uncomment(string $key): void
    {
        $re = '/^\s*;\s*(' . preg_quote($key, '/') . '\s*=.*)$/';
        foreach ($this->lines as $i => $text) {
            if (preg_match($re, $text, $m)) {
                $this->lines[$i] = $m[1];
            }
        }
    }

    public function removeSection(string $section): void
    {
        $kept = [];
        $inTarget = false;
        foreach ($this->lines as $text) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $text, $m)) {
                $inTarget = strcasecmp(trim($m[1]), $section) === 0;
                if ($inTarget) {
                    continue;
                }
            }
            if (!$inTarget) {
                $kept[] = $text;
            }
        }
        $this->lines = $kept;
    }

    public function get(string $section, string $key): ?string
    {
        $inTarget = false;
        foreach ($this->lines as $text) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $text, $m)) {
                $inTarget = strcasecmp(trim($m[1]), $section) === 0;

                continue;
            }
            if ($inTarget && preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*?)\s*$/', $text, $m)) {
                return trim($m[1], " \t\"");
            }
        }

        return null;
    }

    public function toString(): string
    {
        return implode("\n", $this->lines);
    }

    public function save(string $path): void
    {
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0o755, true);
        }
        file_put_contents($path, $this->toString());
    }
}
