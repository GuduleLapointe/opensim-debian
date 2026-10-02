<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui;

/**
 * No interface, for the commands that use what the setup uses without asking
 * anything: warnings and errors go to the error output, the rest is dropped and
 * every question gets its default.
 */
final class QuietUi implements InstallerUi
{
    public function intro(string $title): void {}

    public function note(string $message): void {}

    public function warn(string $message): void
    {
        fwrite(STDERR, "$message\n");
    }

    public function error(string $message): void
    {
        fwrite(STDERR, "$message\n");
    }

    public function outro(string $message): void {}

    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        return $default ?? (string) array_key_first($options);
    }

    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string
    {
        return $default;
    }

    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string
    {
        return '';
    }

    public function confirm(string $label, bool $default = true): bool
    {
        return $default;
    }

    public function spin(\Closure $callback, string $message): mixed
    {
        return $callback();
    }
}
