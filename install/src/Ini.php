<?php


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
     * The configuration of a file in layers: the distribution (what the software ships, with every setting it has), over it
     * what the operator already set in the file of the grid (active settings only), so that a new setting of the distribution
     * is always there and what was chosen is kept. The setup lays its own choices over the result.
     *
     * @param ?string $distribution the file of the distribution, none starts from nothing
     * @param ?string $existing     the file of the grid, none when it does not exist yet
     */
    public static function layered(?string $distribution, ?string $existing): self
    {
        $ini = self::load($distribution ?? '');
        if ($existing !== null && is_file($existing)) {
            $ini->overlay(TextFile::read($existing));
        }

        return $ini;
    }

    /**
     * Lay the settings of a text over this one, in place: each active `key = value` is set in its section (replacing the key
     * the section has, uncommenting its example, or added, the section being made when it is not there); the comments, the
     * other keys and the order of this text stay. A key given twice in a section counts once, the first: OpenSimulator reads it
     * that way.
     */
    public function overlay(string $text): void
    {
        $section = '';
        $seen = [];
        foreach (explode("\n", TextFile::unix($text)) as $line) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $line, $m)) {
                $section = trim($m[1]);

                continue;
            }
            if ($section === '' || !preg_match('/^\s*([^\s;#=][^=]*?)\s*=\s*(.*?)\s*$/', $line, $m)) {
                continue;
            }
            $id = strtolower($section) . '|' . strtolower($m[1]);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $this->set($section, $m[1], $m[2]);
        }
    }

    /**
     * The active settings of this text that the other one does not have, or has with another value, as a text that overlay()
     * takes: what a file adds to its base. A key given twice in a section counts once, the first.
     */
    public function differences(self $base): string
    {
        $section = '';
        $seen = [];
        $blocks = [];
        foreach ($this->lines as $line) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $line, $m)) {
                $section = trim($m[1]);

                continue;
            }
            if ($section === '' || !preg_match('/^\s*([^\s;#=][^=]*?)\s*=\s*(.*?)\s*$/', $line, $m)) {
                continue;
            }
            $id = strtolower($section) . '|' . strtolower($m[1]);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $mine = trim($m[2], " \t\"");
            if ($base->get($section, $m[1]) !== $mine) {
                $blocks[$section][] = "{$m[1]} = {$m[2]}";
            }
        }
        $text = '';
        foreach ($blocks as $name => $lines) {
            $text .= "[$name]\n" . implode("\n", $lines) . "\n\n";
        }

        return $text;
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
        // A key the section has (active) is the one to set, whatever commented example comes before it
        $keyRe = '/^\s*' . preg_quote($key, '/') . '\s*=/';
        if (!$this->has($section, $keyRe)) {
            $keyRe = '/^\s*;?\s*' . preg_quote($key, '/') . '\s*=/';
        }

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
                // The value it already has is left as it is, with the way it is written; a new one takes the place of the line
                // (or of its commented example) with the same indentation
                if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*?)\s*$/', $text, $current) && trim($current[1]) === trim($value)) {
                    return;
                }
                $this->lines[$i] = (preg_match('/^\s*/', $text, $indent) ? $indent[0] : '') . $newLine;

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

        // Target section is the last one and the key was absent: append, before the blank lines that end the file.
        if ($example !== null) {
            array_splice($this->lines, $example + 1, 0, [$newLine]);

            return;
        }
        $at = count($this->lines);
        while ($at > 0 && trim($this->lines[$at - 1]) === '') {
            $at--;
        }
        array_splice($this->lines, $at, 0, [$newLine]);
    }

    /** Whether a section has a line that matches. */
    private function has(string $section, string $lineRe): bool
    {
        $inTarget = false;
        foreach ($this->lines as $text) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $text, $m)) {
                $inTarget = strcasecmp(trim($m[1]), $section) === 0;
            } elseif ($inTarget && preg_match($lineRe, $text)) {
                return true;
            }
        }

        return false;
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

    /**
     * Remove the active lines of a key from a section: the comments, and the other keys, stay.
     *
     * @return bool whether something was removed
     */
    public function remove(string $section, string $key): bool
    {
        $kept = [];
        $inTarget = false;
        $removed = false;
        $re = '/^\s*' . preg_quote($key, '/') . '\s*=/i';
        foreach ($this->lines as $text) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $text, $m)) {
                $inTarget = strcasecmp(trim($m[1]), $section) === 0;
            } elseif ($inTarget && preg_match($re, $text)) {
                $removed = true;

                continue;
            }
            $kept[] = $text;
        }
        $this->lines = $kept;

        return $removed;
    }

    /** Whether a section has the key, active or commented out. */
    public function mentions(string $section, string $key): bool
    {
        return $this->has($section, '/^\s*;?\s*' . preg_quote($key, '/') . '\s*=/i');
    }

    /**
     * Comment out the active lines of a key in a section, with the value they had: it is kept to be seen, and what sets the key
     * again uncomments it.
     *
     * @return bool whether something was commented
     */
    public function comment(string $section, string $key): bool
    {
        $inTarget = false;
        $commented = false;
        $re = '/^(\s*)(' . preg_quote($key, '/') . '\s*=.*)$/i';
        foreach ($this->lines as $i => $text) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $text, $m)) {
                $inTarget = strcasecmp(trim($m[1]), $section) === 0;
            } elseif ($inTarget && preg_match($re, $text, $m)) {
                $this->lines[$i] = $m[1] . ';' . $m[2];
                $commented = true;
            }
        }

        return $commented;
    }

    /**
     * The active keys of a section whose name matches.
     *
     * @param  string $pattern a regular expression
     * @return list<string>
     */
    public function keys(string $section, string $pattern): array
    {
        $keys = [];
        $inTarget = false;
        foreach ($this->lines as $text) {
            if (preg_match('/^\s*\[(.+?)\]\s*$/', $text, $m)) {
                $inTarget = strcasecmp(trim($m[1]), $section) === 0;
            } elseif ($inTarget && preg_match('/^\s*([^\s;#=][^=]*?)\s*=/', $text, $m) && preg_match($pattern, $m[1])) {
                $keys[] = $m[1];
            }
        }

        return $keys;
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
