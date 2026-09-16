<?php

namespace App\Cms\Shop;

/**
 * The currencies the shop can be priced in.
 *
 * The installer, the settings screen and the symbol money() prints all read
 * this list, so a currency added here shows up everywhere at once and the
 * symbol can never drift away from the code it belongs to.
 */
class Currencies
{
    /** @var array<string, array{label: string, symbol: string}> */
    private const LIST = [
        'USD' => ['label' => 'US Dollar (USD)', 'symbol' => '$'],
        'EUR' => ['label' => 'Euro (EUR)', 'symbol' => '€'],
        'GBP' => ['label' => 'British Pound (GBP)', 'symbol' => '£'],
        'INR' => ['label' => 'Indian Rupee (INR)', 'symbol' => '₹'],
        'AUD' => ['label' => 'Australian Dollar (AUD)', 'symbol' => 'A$'],
        'CAD' => ['label' => 'Canadian Dollar (CAD)', 'symbol' => 'C$'],
        'SGD' => ['label' => 'Singapore Dollar (SGD)', 'symbol' => 'S$'],
        'AED' => ['label' => 'UAE Dirham (AED)', 'symbol' => 'د.إ'],
        'JPY' => ['label' => 'Japanese Yen (JPY)', 'symbol' => '¥'],
        'ZAR' => ['label' => 'South African Rand (ZAR)', 'symbol' => 'R'],
        'BRL' => ['label' => 'Brazilian Real (BRL)', 'symbol' => 'R$'],
        'MYR' => ['label' => 'Malaysian Ringgit (MYR)', 'symbol' => 'RM'],
        'NGN' => ['label' => 'Nigerian Naira (NGN)', 'symbol' => '₦'],
        'PKR' => ['label' => 'Pakistani Rupee (PKR)', 'symbol' => '₨'],
        'BDT' => ['label' => 'Bangladeshi Taka (BDT)', 'symbol' => '৳'],
        'PHP' => ['label' => 'Philippine Peso (PHP)', 'symbol' => '₱'],
        'IDR' => ['label' => 'Indonesian Rupiah (IDR)', 'symbol' => 'Rp'],
        'CHF' => ['label' => 'Swiss Franc (CHF)', 'symbol' => 'CHF'],
        'SEK' => ['label' => 'Swedish Krona (SEK)', 'symbol' => 'kr'],
        'NZD' => ['label' => 'New Zealand Dollar (NZD)', 'symbol' => 'NZ$'],
        'SAR' => ['label' => 'Saudi Riyal (SAR)', 'symbol' => 'ر.س'],
        'TRY' => ['label' => 'Turkish Lira (TRY)', 'symbol' => '₺'],
        'MXN' => ['label' => 'Mexican Peso (MXN)', 'symbol' => 'MX$'],
        'KES' => ['label' => 'Kenyan Shilling (KES)', 'symbol' => 'KSh'],
    ];

    /** Codes mapped to their label, ready for a select field. */
    public static function options(): array
    {
        return array_map(fn (array $currency) => $currency['label'], self::LIST);
    }

    /** The symbol a currency is normally written with, or null if unknown. */
    public static function symbolFor(?string $code): ?string
    {
        return self::LIST[strtoupper((string) $code)]['symbol'] ?? null;
    }
}
