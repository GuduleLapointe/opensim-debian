<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui;

/**
 * Answers the questions of the setup from a table, so the same flows run without anyone typing: the quick
 * setup (a form for what matters, the defaults for the rest) and the setup from a file.
 *
 * A question is answered, in this order, by the table (the key is the beginning of the question, as written
 * in English, or any part of it), else by the interface behind when its label is one of those to ask (the
 * plan to accept, the credentials of the database administrator...), else by its default. An answer the
 * question refuses (its validation) is asked again by the interface behind, with this answer as the default.
 */
final class PresetUi implements InstallerUi
{
    use AsksForms;

    /** @var list<array{0:string,1:string}> what was answered, the label and the answer, secrets masked */
    public array $answered = [];

    /**
     * @param array<string,string|bool|list<string>> $answers question => answer
     * @param list<string> $ask the questions the interface behind asks, whatever the table says
     */
    public function __construct(
        private InstallerUi $inner,
        private array $answers,
        private array $ask = [],
    ) {}

    public function intro(string $title): void
    {
        $this->inner->intro($title);
    }

    public function note(string $message): void
    {
        $this->inner->note($message);
    }

    public function entity(string $name): string
    {
        return $this->inner->entity($name);
    }

    public function warn(string $message): void
    {
        $this->inner->warn($message);
    }

    public function error(string $message): void
    {
        $this->inner->error($message);
    }

    public function outro(string $message): void
    {
        $this->inner->outro($message);
    }

    public function spin(\Closure $callback, string $message): mixed
    {
        return $this->inner->spin($callback, $message);
    }

    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        $preset = $this->preset($label);
        // A list is the choices to try in turn (an account that exists, else the one to make)
        foreach (is_array($preset) ? $preset : [$preset] as $candidate) {
            if (is_string($candidate) && isset($options[$candidate])) {
                return $this->took($label, $candidate);
            }
        }
        if ($this->asked($label) || $preset !== null) {
            return $this->inner->choose($label, $options, $default, $hint);
        }

        return $default ?? (string) array_key_first($options);
    }

    public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array
    {
        $preset = $this->preset($label);
        if ($preset !== null) {
            $keys = is_array($preset) ? array_map('strval', $preset) : array_values(array_filter(explode(',', (string) $preset)));

            return array_values(array_filter($keys, static fn(string $k): bool => isset($options[$k])));
        }

        return $this->asked($label) ? $this->inner->checklist($label, $options, $defaults, $hint) : $defaults;
    }

    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string
    {
        $preset = $this->preset($label);
        if ($preset !== null && !is_array($preset)) {
            $value = (string) (is_bool($preset) ? ($preset ? 'true' : 'false') : $preset);
            if ($validate === null || $validate($value) === null) {
                return $this->took($label, $value);
            }

            return $this->inner->text($label, $value, $validate, $hint);
        }
        if ($this->asked($label)) {
            return $this->inner->text($label, $default, $validate, $hint);
        }
        // The default of a question the user is not asked, which the question would refuse, is asked after all
        if ($validate !== null && $validate($default) !== null) {
            return $this->inner->text($label, $default, $validate, $hint);
        }

        return $default;
    }

    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string
    {
        $preset = $this->preset($label);
        if (is_string($preset) && ($validate === null || $validate($preset) === null)) {
            return $this->took($label, $preset, true);
        }

        // A secret has no default: it is asked, unless it can be nothing
        return $validate === null || $validate('') === null
            ? ''
            : $this->inner->secret($label, $validate, $hint);
    }

    public function confirm(string $label, bool $default = true): bool
    {
        $preset = $this->preset($label);
        if ($preset !== null) {
            $value = is_bool($preset) ? $preset : in_array(strtolower((string) $preset), ['1', 'true', 'yes', 'y'], true);
            $this->took($label, $value ? 'yes' : 'no');

            return $value;
        }

        return $this->asked($label) ? $this->inner->confirm($label, $default) : $default;
    }

    /** @return string|bool|list<string>|null */
    private function preset(string $label): string|bool|array|null
    {
        foreach ($this->answers as $match => $answer) {
            if (self::matches($label, $match)) {
                return $answer;
            }
        }

        return null;
    }

    private function asked(string $label): bool
    {
        foreach ($this->ask as $match) {
            if (self::matches($label, $match)) {
                return true;
            }
        }

        return false;
    }

    /** The question holds the words, as they are written in the source or translated. */
    private static function matches(string $label, string $words): bool
    {
        $words = explode('%', $words)[0];

        return $words !== '' && (str_contains($label, $words) || str_contains($label, _($words)));
    }

    private function took(string $label, string $answer, bool $secret = false): string
    {
        $this->answered[] = [$label, $secret ? '' : $answer];

        return $answer;
    }
}
