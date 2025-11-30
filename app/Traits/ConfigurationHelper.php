<?php

namespace App\Traits;

use App\Models\Configuration;
use App\Models\Invoice;

trait ConfigurationHelper
{
    /**
     * Get a configuration value by code
     *
     * @param string $code
     * @param mixed $default
     * @return mixed
     */
    public static function getConfigValue(string $code, $default = null)
    {
        return Configuration::where('key', $code)->first()?->value ?? $default;
    }

    /**
     * Get invoice due date in days
     *
     * @return int
     */
    public static function getInvoiceDueDate(): int
    {
        return (int) self::getConfigValue('DUE_DATE', 30); // Default to 30 days if not configured
    }

    /**
     * Generate a new invoice number
     * Format: {prefix}-{last_id+1}-{date}
     *
     * @return string
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = self::getConfigValue('INVOICE_PREFIX', 'INV');
        $lastInvoice = Invoice::orderBy('id', 'desc')->first();
        $nextId = $lastInvoice ? $lastInvoice->id + 1 : 1;
        $date = now()->format('Ymd');

        return "{$prefix}{$nextId}";
    }
}