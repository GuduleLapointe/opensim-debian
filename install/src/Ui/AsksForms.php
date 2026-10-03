<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui;

/**
 * A form made of the questions of the interface: each field is asked in turn, then the answers are
 * shown to be accepted or corrected (a field at a time, the others are kept), so a typo is not a
 * reason to start again. The form of InstallerUi for every frontend that asks one question at a time.
 */
trait AsksForms
{
    /**
     * @param list<array{key:string,label:string,type?:string,default?:string,required?:bool,validate?:?\Closure,hint?:string}> $fields
     * @return array<string,string>
     */
    public function form(array $fields, string $title = ''): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field['key']] = $this->askField($field, $field['default'] ?? '');
        }

        while (true) {
            $lines = [];
            foreach ($fields as $field) {
                $value = $values[$field['key']];
                $lines[] = $field['label'] . ': ' . (($field['type'] ?? 'text') === 'secret' && $value !== '' ? '••••••' : $value);
            }
            $this->note(implode("\n", $lines));
            if ($this->choose(_('Is this right?'), ['ok' => _('Continue'), 'edit' => _('Change something')], 'ok') === 'ok') {
                return $values;
            }

            $options = [];
            foreach ($fields as $field) {
                $options[$field['key']] = $field['label'];
            }
            $key = $this->choose(_('Which one?'), $options, array_key_first($options));
            foreach ($fields as $field) {
                if ($field['key'] === $key) {
                    $values[$key] = $this->askField($field, $values[$key], true);
                }
            }
        }
    }

    /** @param array{key:string,label:string,type?:string,default?:string,required?:bool,validate?:?\Closure,hint?:string} $field */
    private function askField(array $field, string $current, bool $keep = false): string
    {
        $required = $field['required'] ?? true;
        $custom = $field['validate'] ?? null;
        $validate = static fn(string $v): ?string => $required && trim($v) === ''
            ? _('This field is required.')
            : ($custom !== null && trim($v) !== '' ? $custom($v) : null);

        if (($field['type'] ?? 'text') === 'secret') {
            // A secret is never shown: nothing typed keeps the one given before
            $value = $this->secret(
                $field['label'],
                $keep && $current !== '' ? static fn(string $v): ?string => $v === '' ? null : ($custom !== null ? $custom($v) : null) : $validate,
                $field['hint'] ?? null,
            );

            return $value === '' && $keep ? $current : $value;
        }

        return trim($this->text($field['label'], $current, $validate, $field['hint'] ?? null));
    }
}
