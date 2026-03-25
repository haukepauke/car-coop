<?php

namespace App\Service;

class CurrencyService
{
    public const CURRENCIES = ['EUR', 'USD', 'GBP', 'PLN'];

    private const SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'PLN' => 'zł',
    ];

    private const DEFAULT_PRICE_PER_UNIT = [
        'PLN' => 1.00,
    ];

    private const FALLBACK_PRICE_PER_UNIT = 0.30;

    public static function getSymbol(string $currency): string
    {
        return self::SYMBOLS[$currency] ?? '€';
    }

    public function getDefaultPricePerUnit(string $currency): float
    {
        return self::DEFAULT_PRICE_PER_UNIT[$currency] ?? self::FALLBACK_PRICE_PER_UNIT;
    }

    public static function getFormChoices(): array
    {
        return [
            'Euro (€)'           => 'EUR',
            'US Dollar ($)'      => 'USD',
            'British Pound (£)'  => 'GBP',
            'Polish Złoty (zł)'  => 'PLN',
        ];
    }
}
