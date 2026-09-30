<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Text/CLI frontend built on Laravel Prompts (arrow-key selection, inline
 * validation, spinners).
 */
final class PromptsUi implements InstallerUi
{
    public function intro(string $title): void
    {
        intro($title);
    }

    public function note(string $message): void
    {
        note($message);
    }

    public function warn(string $message): void
    {
        warning($message);
    }

    public function error(string $message): void
    {
        error($message);
    }

    public function outro(string $message): void
    {
        outro($message);
    }

    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        return (string) select(
            label: $label,
            options: $options,
            default: $default ?? array_key_first($options),
            hint: $hint ?? '',
        );
    }

    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string
    {
        return text(
            label: $label,
            default: $default,
            validate: $validate,
            hint: $hint ?? '',
        );
    }

    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string
    {
        return password(
            label: $label,
            validate: $validate,
            hint: $hint ?? '',
        );
    }

    public function confirm(string $label, bool $default = true): bool
    {
        return confirm(label: $label, default: $default);
    }

    public function spin(\Closure $callback, string $message): mixed
    {
        return spin($callback, $message);
    }
}
