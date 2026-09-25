<?php

namespace App\Cms\Forms;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Which fields a contact form asks for, and which of them it insists on.
 *
 * A contact form is drawn in one place - a builder widget or a theme's own
 * page - and posted to a single shared endpoint. The endpoint therefore has
 * to be told what the form asked for, and it cannot simply believe the
 * browser: a required field would be trivial to drop from the request, and an
 * extra field could otherwise be invented to fill the inbox with anything.
 *
 * So the schema travels with the form as an encrypted token. The browser
 * carries it and cannot read or rewrite it; the server decrypts it with the
 * application key and validates against that, never against the field names
 * that happen to be in the request.
 *
 * Email is not configurable. It is the address every reply goes to, the one
 * the inbox is searched on, and a message that cannot be answered is not
 * worth keeping.
 */
class ContactFormSchema
{
    /** Built-in fields, in the order a form lays them out. */
    public const BUILT_IN = ['name', 'email', 'phone', 'subject', 'message'];

    /** The input types an extra field may use. */
    public const TYPES = [
        'text' => 'Single line text',
        'textarea' => 'Paragraph',
        'email' => 'Email address',
        'tel' => 'Phone number',
        'url' => 'Web address',
        'number' => 'Number',
        'date' => 'Date',
        'select' => 'Dropdown',
        'checkbox' => 'Checkbox',
    ];

    /** Extra field values arrive under this key, away from the built-ins. */
    public const EXTRA_INPUT = 'custom';

    /** The hidden input carrying the encrypted schema. */
    public const TOKEN_INPUT = '_schema';

    private const MAX_EXTRA_FIELDS = 30;

    private const DEFAULT_SUCCESS = 'Thanks for getting in touch. We will reply shortly.';

    /**
     * What every contact form did before any of this was configurable, and
     * what a theme's own form - which sends no token - still gets.
     */
    public static function legacy(): array
    {
        return self::normalise([
            'fields' => [
                ['key' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'width' => 'half'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false, 'width' => 'half'],
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => false, 'width' => 'half'],
                ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
            ],
            'success' => self::DEFAULT_SUCCESS,
        ]);
    }

    /** Build the schema a Contact form widget's settings describe. */
    public static function fromSettings(array $settings): array
    {
        $fields = [
            [
                'key' => 'name',
                'label' => 'Your name',
                'type' => 'text',
                'required' => (bool) ($settings['require_name'] ?? true),
                'width' => 'half',
            ],
            [
                'key' => 'email',
                'label' => 'Email address',
                'type' => 'email',
                'required' => true,
                'width' => 'half',
            ],
        ];

        if (! empty($settings['show_phone'])) {
            $fields[] = [
                'key' => 'phone',
                'label' => 'Phone',
                'type' => 'tel',
                'required' => (bool) ($settings['require_phone'] ?? false),
                'width' => 'half',
            ];
        }

        if (! empty($settings['show_subject'])) {
            $fields[] = [
                'key' => 'subject',
                'label' => 'Subject',
                'type' => 'text',
                'required' => (bool) ($settings['require_subject'] ?? false),
                'width' => 'half',
            ];
        }

        foreach (self::extraFields($settings) as $field) {
            $fields[] = $field;
        }

        // The message stays last: it is the tall one, and a form that ends on
        // a paragraph box reads better than one that buries it mid-way.
        $fields[] = [
            'key' => 'message',
            'label' => 'Message',
            'type' => 'textarea',
            'required' => (bool) ($settings['require_message'] ?? true),
            'width' => 'full',
        ];

        return self::normalise([
            'fields' => $fields,
            'success' => (string) ($settings['success_text'] ?? self::DEFAULT_SUCCESS),
        ]);
    }

    /**
     * Turn the widget's repeater rows into fields, giving each one a stable
     * input name derived from its label so the editor never has to invent one.
     */
    private static function extraFields(array $settings): array
    {
        $rows = $settings['extra_fields'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        $fields = [];
        $taken = self::BUILT_IN;

        foreach (array_slice($rows, 0, self::MAX_EXTRA_FIELDS) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $key = Str::limit(Str::slug($label, '_'), 40, '');
            $key = $key !== '' ? $key : 'field_'.($index + 1);

            // Two fields called the same thing would overwrite each other.
            $unique = $key;
            $suffix = 2;

            while (in_array($unique, $taken, true)) {
                $unique = $key.'_'.$suffix++;
            }

            $taken[] = $unique;

            $fields[] = [
                'key' => $unique,
                'label' => $label,
                'type' => (string) ($row['type'] ?? 'text'),
                'required' => (bool) ($row['required'] ?? false),
                'width' => (string) ($row['width'] ?? 'full'),
                'placeholder' => (string) ($row['placeholder'] ?? ''),
                'options' => self::choices($row['options'] ?? ''),
                'extra' => true,
            ];
        }

        return $fields;
    }

    /** Dropdown choices, written one per line in the editor. */
    private static function choices(mixed $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw) ?: [];

        $choices = array_filter(
            array_map(fn ($line) => trim(Str::limit($line, 190, '')), $lines),
            fn ($line) => $line !== ''
        );

        return array_slice(array_values(array_unique($choices)), 0, 50);
    }

    /**
     * Sanitise a schema, whether it was just built or has come back from a
     * token. Anything unrecognised is dropped rather than trusted.
     */
    public static function normalise(array $raw): array
    {
        $fields = [];
        $seen = [];

        foreach ($raw['fields'] ?? [] as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $builtIn = in_array($key, self::BUILT_IN, true);

            if (! $builtIn && ! preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
                continue;
            }

            if (in_array($key, $seen, true)) {
                continue;
            }

            $seen[] = $key;

            $type = (string) ($field['type'] ?? 'text');

            if (! array_key_exists($type, self::TYPES)) {
                $type = 'text';
            }

            $options = array_values(array_filter(
                array_map(fn ($option) => (string) $option, (array) ($field['options'] ?? [])),
                fn ($option) => $option !== ''
            ));

            $fields[] = [
                'key' => $key,
                'label' => Str::limit(trim((string) ($field['label'] ?? $key)), 120, ''),
                'type' => $type,
                // Email is the reply address, so it is never optional.
                'required' => $key === 'email' ? true : (bool) ($field['required'] ?? false),
                'width' => ($field['width'] ?? 'full') === 'half' ? 'half' : 'full',
                'placeholder' => Str::limit((string) ($field['placeholder'] ?? ''), 120, ''),
                'options' => array_slice($options, 0, 50),
                'extra' => ! $builtIn,
            ];
        }

        $success = trim((string) ($raw['success'] ?? ''));

        return [
            'fields' => $fields,
            'success' => $success !== '' ? Str::limit($success, 300, '') : self::DEFAULT_SUCCESS,
        ];
    }

    /** The encrypted schema a form carries in a hidden input. */
    public static function token(array $schema): string
    {
        return Crypt::encryptString(json_encode($schema, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Read a token back, or null when there is none and when one has been
     * tampered with - which are handled the same way, by falling back to the
     * built-in schema rather than by trusting the request.
     */
    public static function fromToken(mixed $token): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return null;
        }

        if (! is_array($decoded) || empty($decoded['fields'])) {
            return null;
        }

        return self::normalise($decoded);
    }

    /** Validation rules for everything the form declared, and nothing else. */
    public static function rules(array $schema): array
    {
        // Bots fill the honeypot; people never see it.
        $rules = ['website' => ['nullable', 'size:0']];

        foreach ($schema['fields'] as $field) {
            $rules[self::inputName($field)] = self::fieldRules($field);
        }

        return $rules;
    }

    /** Where a field's answer sits in the request. */
    public static function inputName(array $field): string
    {
        return $field['extra'] ? self::EXTRA_INPUT.'.'.$field['key'] : $field['key'];
    }

    /** The same, written the way an HTML name attribute wants it. */
    public static function htmlName(array $field): string
    {
        return $field['extra'] ? self::EXTRA_INPUT.'['.$field['key'].']' : $field['key'];
    }

    private static function fieldRules(array $field): array
    {
        $required = $field['required'];
        $presence = $required ? 'required' : 'nullable';

        $rules = match ($field['type']) {
            'checkbox' => [$required ? 'accepted' : 'nullable'],
            'email' => [$presence, 'email', 'max:190'],
            'url' => [$presence, 'url', 'max:190'],
            'number' => [$presence, 'numeric'],
            'date' => [$presence, 'date'],
            'tel' => [$presence, 'string', 'max:30'],
            'textarea' => [$presence, 'string', 'max:5000'],
            'select' => array_merge([$presence, 'string'], self::choiceRule($field)),
            default => [$presence, 'string', 'max:190'],
        };

        // The message has always had a floor, and it is what keeps "hi" and a
        // stray keystroke out of the inbox.
        if ($field['key'] === 'message' && $required) {
            $rules[] = 'min:10';
        }

        return $rules;
    }

    /**
     * A dropdown answer has to be one of the choices offered. Rule::in takes
     * the list as an array, which is what keeps a choice containing a comma
     * from being read as two.
     */
    private static function choiceRule(array $field): array
    {
        if ($field['options'] === []) {
            return ['max:190'];
        }

        return [\Illuminate\Validation\Rule::in($field['options'])];
    }

    /** Labels for the error messages, so they read as the form reads. */
    public static function attributes(array $schema): array
    {
        $attributes = [];

        foreach ($schema['fields'] as $field) {
            $attributes[self::inputName($field)] = Str::lower($field['label']);
        }

        return $attributes;
    }

    /**
     * The extra fields' answers, kept as label and value pairs so a message
     * still reads correctly after the form that collected it has changed.
     */
    public static function extraValues(array $schema, array $validated): array
    {
        $answers = [];
        $submitted = (array) ($validated[self::EXTRA_INPUT] ?? []);

        foreach ($schema['fields'] as $field) {
            if (! $field['extra']) {
                continue;
            }

            $value = $submitted[$field['key']] ?? null;

            if ($field['type'] === 'checkbox') {
                $value = filled($value) ? 'Yes' : 'No';
            }

            if (blank($value)) {
                continue;
            }

            $answers[] = ['label' => $field['label'], 'value' => (string) $value];
        }

        return $answers;
    }
}
