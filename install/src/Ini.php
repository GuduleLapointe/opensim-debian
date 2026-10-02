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

    /** Set key = value in section (uncommenting an existing commented key). */
    public function set(string $section, string $key, string $value): void
    {
        $newLine = "$key = $value";
        $keyRe = '/^\s*;?\s*' . preg_quote($key, '/') . '\s*=/';

        $sectionStart = -1;
        $inTarget = false;

        foreach ($this->lines as $i => $text) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $text, $m)) {
                if ($inTarget) {
                    // Reached the next section without finding the key: insert it
                    // after the section's last content line, before trailing blanks.
                    $at = $i;
                    while ($at > 0 && trim($this->lines[$at - 1]) === '') {
                        $at--;
                    }
                    array_splice($this->lines, $at, 0, [$newLine]);

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
        $this->lines[] = $newLine;
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
        @mkdir(dirname($path), 0o755, true);
        file_put_contents($path, $this->toString());
    }
}
