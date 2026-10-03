<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui\Prompts;

use Closure;
use Laravel\Prompts\Concerns\TypedValue;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

/**
 * Several fields on one screen: Tab, Enter or Down go to the next field, Shift-Tab or Up to the one before,
 * Enter in the last one sends the form. The fields are edited with the keys of Shortcuts (readline).
 * Whatever is wrong is shown under the form, on the field to correct.
 */
final class FormPrompt extends Prompt
{
    use Shortcuts;
    use TypedValue;

    /** The field being edited */
    public int $focus = 0;

    /** @var array<string,string> the value of each field, by key */
    public array $values = [];

    public bool|string $required = false;

    public mixed $validate = null;

    public ?Closure $transform = null;

    /**
     * @param list<array{key:string,label:string,type?:string,default?:string,required?:bool,validate?:?Closure,hint?:string}> $fields
     */
    public function __construct(public string $title, public array $fields)
    {
        foreach ($fields as $field) {
            $this->values[$field['key']] = $field['default'] ?? '';
        }
        $this->trackTypedValue($this->values[$fields[0]['key']], submit: false);
        $this->validate = fn(array $values): ?string => $this->firstProblem();
        $this->on('key', fn(string $key) => $this->navigate($key));
    }

    /** @return array<string,string> */
    public function value(): array
    {
        return $this->values;
    }

    /** What the field being edited says of its hint, to show under the form */
    public function hint(): string
    {
        return (string) ($this->fields[$this->focus]['hint'] ?? '');
    }

    /** The value of a field to show: a secret is masked, the field being edited has the cursor */
    public function shown(int $i, int $maxWidth): string
    {
        $field = $this->fields[$i];
        $secret = ($field['type'] ?? 'text') === 'secret';
        $value = $i === $this->focus ? $this->typedValue : $this->values[$field['key']];
        $text = $secret ? str_repeat('•', mb_strlen($value)) : $value;

        return $i === $this->focus && $this->state !== 'submit'
            ? $this->addCursor($text, $this->cursorPosition, $maxWidth)
            : $text;
    }

    private function navigate(string $key): void
    {
        $this->values[$this->fields[$this->focus]['key']] = $this->typedValue;

        $last = count($this->fields) - 1;
        if ($key === Key::ENTER && $this->focus === $last) {
            $this->submit();

            return;
        }
        if (in_array($key, [Key::ENTER, Key::TAB, Key::DOWN, Key::DOWN_ARROW], true)) {
            $this->focusOn(min($last, $this->focus + 1));
        } elseif (in_array($key, [Key::SHIFT_TAB, Key::UP, Key::UP_ARROW], true)) {
            $this->focusOn(max(0, $this->focus - 1));
        }
    }

    private function focusOn(int $i): void
    {
        $this->focus = $i;
        $this->typedValue = $this->values[$this->fields[$i]['key']];
        $this->cursorPosition = mb_strlen($this->typedValue);
    }

    /** The first field that is not right: it takes the focus, and its problem is the error shown. */
    private function firstProblem(): ?string
    {
        foreach ($this->fields as $i => $field) {
            $value = trim($this->values[$field['key']]);
            $problem = null;
            if (($field['required'] ?? true) && $value === '') {
                $problem = sprintf(_('%s is required.'), $field['label']);
            } elseif ($value !== '' && ($check = $field['validate'] ?? null) !== null) {
                $problem = $check($this->values[$field['key']]);
            }
            if ($problem !== null) {
                $this->focusOn($i);

                return $problem;
            }
        }

        return null;
    }

    protected function getRenderer(): callable
    {
        return new FormPromptRenderer($this);
    }
}
