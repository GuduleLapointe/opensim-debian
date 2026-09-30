<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui;

/**
 * Presentation abstraction for the installer.
 *
 * The installer flow depends only on this interface, so the same flow can be
 * driven by a text frontend (PromptsUi, using Laravel Prompts) today and a web
 * frontend tomorrow.
 */
interface InstallerUi
{
    public function intro(string $title): void;

    public function note(string $message): void;

    public function warn(string $message): void;

    public function error(string $message): void;

    public function outro(string $message): void;

    /**
     * Single choice among options.
     *
     * @param array<string,string> $options  key => label shown to the user
     * @return string                         the chosen key
     */
    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string;

    /**
     * Free text input.
     *
     * @param ?\Closure(string):?string $validate  returns an error message, or null if valid
     */
    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string;

    /**
     * Secret input (a password): not shown while typed, never proposed.
     *
     * @param ?\Closure(string):?string $validate  returns an error message, or null if valid
     */
    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string;

    public function confirm(string $label, bool $default = true): bool;

    /**
     * Run $callback while showing a progress indicator; return its result.
     *
     * @template T
     * @param \Closure():T $callback
     * @return T
     */
    public function spin(\Closure $callback, string $message): mixed;
}
