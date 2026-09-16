<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Mail\MailConfigurator;
use App\Cms\Seo\SeoManager;
use App\Cms\Settings\SettingsRepository;
use App\Cms\Shop\Currencies;
use App\Cms\Themes\ThemeManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Renders and saves every settings screen.
 *
 * The forms are generated from config/settings.php, so adding an option to the
 * CMS is a one-line change there - this controller never needs touching.
 */
class SettingsController extends Controller
{
    public function __construct(
        private SettingsRepository $settings,
        private ThemeManager $themes,
        private MailConfigurator $mail,
    ) {}

    public function edit(Request $request, ?string $group = null): View
    {
        $groups = $this->visibleGroups();
        $group ??= array_key_first($groups);

        abort_unless(isset($groups[$group]), 404);

        return view('admin.settings.edit', [
            'groups' => $groups,
            'activeGroup' => $group,
            'definition' => $groups[$group],
            'values' => $this->currentValues($groups[$group]),
            'optionSets' => $this->optionSets(),
            'mailStatus' => $group === 'mail' ? $this->mailStatus() : null,
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $groups = $this->visibleGroups();

        abort_unless(isset($groups[$group]), 404);

        $fields = $groups[$group]['fields'] ?? [];
        $rules = [];

        foreach ($fields as $key => $field) {
            if (($field['type'] ?? 'text') === 'notice') {
                continue;
            }

            $rules[$key] = $this->rulesFor($field);
        }

        $validated = Validator::make($request->all(), $rules)->validate();

        $values = [];

        foreach ($fields as $key => $field) {
            $type = $field['type'] ?? 'text';

            if ($type === 'notice') {
                continue;
            }

            // An unchecked checkbox is simply absent from the request, so a
            // boolean has to be read from presence rather than from the array.
            if ($type === 'boolean') {
                $values[$key] = $request->boolean($key);

                continue;
            }

            // A blank secret means "leave the stored value alone", which is
            // what lets the form render a masked placeholder safely.
            if ($type === 'secret' && blank($validated[$key] ?? null)) {
                continue;
            }

            $values[$key] = $validated[$key] ?? null;
        }

        if ($group === 'shop') {
            $values = $this->syncCurrencySymbol($values);
        }

        $this->settings->setMany($values, $group);

        // Switching theme has to republish assets and drop the view cache, or
        // the site keeps rendering the previous template.
        if ($group === 'appearance' && isset($values['active_theme'])) {
            $this->themes->activate($values['active_theme']);
        }

        activity('settings.updated', "Updated the {$group} settings.", properties: ['group' => $group, 'keys' => array_keys($values)]);

        return back()->with('status', ucfirst($groups[$group]['label'] ?? $group).' settings saved.');
    }

    /** Settings groups whose module is enabled. */
    private function visibleGroups(): array
    {
        return collect(config('settings', []))
            ->filter(fn ($group) => blank($group['module'] ?? null) || modules()->enabled($group['module']))
            ->all();
    }

    private function currentValues(array $group): array
    {
        $values = [];

        foreach ($group['fields'] ?? [] as $key => $field) {
            // Secrets are never sent back to the browser; the form shows an
            // empty field with a "leave blank to keep" hint instead.
            $values[$key] = ($field['type'] ?? 'text') === 'secret'
                ? null
                : $this->settings->get($key);
        }

        return $values;
    }

    /** Rules from the field definition, with sane defaults per type. */
    private function rulesFor(array $field): array
    {
        if (isset($field['rules'])) {
            return array_merge(['nullable'], explode('|', $field['rules']));
        }

        return match ($field['type'] ?? 'text') {
            'email' => ['nullable', 'email', 'max:190'],
            'url' => ['nullable', 'url', 'max:255'],
            'number' => ['nullable', 'numeric'],
            'boolean' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:32'],
            'select' => ['nullable', 'string', 'max:190'],
            'textarea', 'code' => ['nullable', 'string', 'max:65535'],
            default => ['nullable', 'string', 'max:1000'],
        };
    }

    /**
     * Option lists a field can reference by name, so config/settings.php does
     * not have to inline hundreds of timezones or currencies.
     */
    private function optionSets(): array
    {
        return [
            'timezones' => collect(\DateTimeZone::listIdentifiers())
                ->mapWithKeys(fn ($zone) => [$zone => $zone])
                ->all(),

            'themes' => \App\Models\Theme::orderBy('name')
                ->pluck('name', 'slug')
                ->all(),

            'schema_types' => SeoManager::SCHEMA_TYPES,

            'currencies' => Currencies::options(),
        ];
    }

    /**
     * Move the symbol along when the currency changes.
     *
     * Someone who picks "Indian Rupee" expects prices to read in rupees, not
     * to also have to know that the symbol is a separate field. A symbol that
     * has been customised is left alone - only one still matching the currency
     * being switched away from is treated as untouched.
     */
    private function syncCurrencySymbol(array $values): array
    {
        if (! array_key_exists('shop_currency', $values) || ! array_key_exists('shop_currency_symbol', $values)) {
            return $values;
        }

        $previous = (string) $this->settings->get('shop_currency', 'USD');

        if (strtoupper((string) $values['shop_currency']) === strtoupper($previous)) {
            return $values;
        }

        $submitted = trim((string) $values['shop_currency_symbol']);

        if ($submitted !== '' && $submitted !== Currencies::symbolFor($previous)) {
            return $values;
        }

        $values['shop_currency_symbol'] = Currencies::symbolFor($values['shop_currency']) ?? $submitted;

        return $values;
    }

    /** Whether the chosen mail provider is actually usable on this host. */
    private function mailStatus(): array
    {
        $driver = (string) $this->settings->get('mail_driver', 'smtp');

        return [
            'driver' => $driver,
            'available' => $this->mail->driverIsAvailable($driver),
            'install_hint' => $this->mail->installHintFor($driver),
            'env' => $this->mail->envStatusFor($driver),
        ];
    }
}
