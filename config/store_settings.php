<?php
/**
 * Store-wide settings the admin changes from the CMS (settings.php).
 * Values live in the existing `settings` key/value table
 * (setting_key / setting_value), so no migration is needed.
 *
 * Tax rate
 *   - Percent of the order subtotal, e.g. 8 = 8 %, 0 = no tax.
 *   - create_order.php charges it; api/get_store_settings.php serves it to the
 *     website and the app so the totals they show match what the server charges.
 *   - Until the admin saves a value, the default (8) is used — the rate the shop
 *     has always charged — so deploying this code changes nothing by itself.
 *   - Past orders keep the tax amount they were saved with.
 */

if (!defined('STORE_DEFAULT_TAX_RATE_PERCENT')) {
    define('STORE_DEFAULT_TAX_RATE_PERCENT', 8.0);
    define('STORE_MAX_TAX_RATE_PERCENT', 28.0);   // highest GST slab in India
    define('STORE_SETTING_TAX_RATE', 'tax_rate_percent');
}

if (!function_exists('normalizeTaxRatePercent')) {
    /**
     * A valid percent (0 … 28, max 2 decimals) as float, or null if invalid.
     */
    function normalizeTaxRatePercent($value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $v = round((float) $value, 2);
        if (!is_finite($v) || $v < 0 || $v > STORE_MAX_TAX_RATE_PERCENT) {
            return null;
        }
        return $v;
    }

    /** "8", "2.5", "0" — a percent without trailing zeros. */
    function formatTaxRatePercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    }

    /**
     * Current tax rate in percent. Falls back to the default when the setting
     * has never been saved, holds an invalid value, or cannot be read.
     */
    function getTaxRatePercent(PDO $conn): float
    {
        try {
            $stmt = $conn->prepare(
                "SELECT setting_value FROM settings WHERE setting_key = ? ORDER BY updated_at DESC LIMIT 1"
            );
            $stmt->execute([STORE_SETTING_TAX_RATE]);
            $raw = $stmt->fetchColumn();
            if ($raw === false) {
                return STORE_DEFAULT_TAX_RATE_PERCENT; // never saved
            }
            $v = normalizeTaxRatePercent($raw);
            if ($v === null) {
                error_log('[store_settings] invalid tax_rate_percent "' . $raw . '" — using default');
                return STORE_DEFAULT_TAX_RATE_PERCENT;
            }
            return $v;
        } catch (Throwable $e) {
            error_log('[store_settings] could not read tax rate: ' . $e->getMessage());
            return STORE_DEFAULT_TAX_RATE_PERCENT;
        }
    }

    /**
     * Save the tax rate. Caller must pass a value from normalizeTaxRatePercent().
     * The settings table has no unique key or auto-increment id, so update the
     * existing row(s) or insert one with the next id.
     */
    function saveTaxRatePercent(PDO $conn, float $percent): void
    {
        $value = formatTaxRatePercent($percent);

        $conn->beginTransaction();
        try {
            $exists = $conn->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $exists->execute([STORE_SETTING_TAX_RATE]);

            if ((int) $exists->fetchColumn() > 0) {
                $conn->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?")
                     ->execute([$value, STORE_SETTING_TAX_RATE]);
            } else {
                $conn->prepare(
                    "INSERT INTO settings (id, setting_key, setting_value, created_at, updated_at)
                     SELECT COALESCE(MAX(id), 0) + 1, ?, ?, NOW(), NOW() FROM settings"
                )->execute([STORE_SETTING_TAX_RATE, $value]);
            }
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }
}
