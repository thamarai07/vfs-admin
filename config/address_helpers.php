<?php
/**
 * Shared helpers for the address APIs.
 * Keeps the JSON shape and the "compose full address" logic in one place
 * so get / save / update all return identical structures.
 */

if (!function_exists('formatAddressRow')) {
    /**
     * Convert a customer_addresses DB row into the JSON shape the frontend expects.
     */
    function formatAddressRow(array $row): array
    {
        return [
            'id'             => (int) $row['id'],
            'customer_id'    => (int) $row['customer_id'],
            'name'           => $row['name'],
            'phone'          => $row['phone'],
            'email'          => $row['email'],
            'flat_no'        => $row['flat_no'],
            'street_address' => $row['street_address'] ?? null,
            'area'           => $row['area'] ?? null,
            'landmark'       => $row['landmark'],
            'city'           => $row['city'] ?? null,
            'state'          => $row['state'] ?? null,
            'pincode'        => $row['pincode'] ?? null,
            'country'        => $row['country'] ?? 'India',
            'full_address'   => $row['full_address'],
            'label'          => $row['label'],
            'latitude'       => isset($row['latitude']) ? (float) $row['latitude'] : null,
            'longitude'      => isset($row['longitude']) ? (float) $row['longitude'] : null,
            'is_default'     => (int) $row['is_default'],
            'phone_verified' => (int) ($row['phone_verified'] ?? 0),
            'created_at'     => $row['created_at'],
            'updated_at'     => $row['updated_at'],
        ];
    }
}

if (!function_exists('composeFullAddress')) {
    /**
     * Build a human-readable single-line address from the structured parts.
     * Used for backward compatibility (orders, older components read full_address).
     *
     * @param array $p keys: flat_no, street_address, area, landmark, city, state, pincode, country
     */
    function composeFullAddress(array $p): string
    {
        $landmark = trim((string) ($p['landmark'] ?? ''));

        $parts = [
            $p['flat_no']        ?? '',
            $p['street_address'] ?? '',
            $p['area']           ?? '',
            $landmark !== '' ? ('Near ' . $landmark) : '',
            $p['city']           ?? '',
            $p['state']          ?? '',
            $p['pincode']        ?? '',
            $p['country']        ?? '',
        ];

        $parts = array_values(array_filter(
            array_map(fn($v) => trim((string) $v), $parts),
            fn($v) => $v !== ''
        ));

        return implode(', ', $parts);
    }
}

if (!function_exists('readJsonBody')) {
    /**
     * Read and decode the JSON request body. Throws on invalid JSON.
     */
    function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            throw new Exception('No input data received');
        }
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('normalizeLabel')) {
    /**
     * Normalize the address type to one of Home / Work / Other.
     * Accepts case-insensitive input and treats "Office" as "Work".
     */
    function normalizeLabel($label): string
    {
        $label = ucfirst(strtolower(trim((string) $label)));
        if ($label === 'Office') {
            $label = 'Work';
        }
        return in_array($label, ['Home', 'Work', 'Other'], true) ? $label : 'Home';
    }
}
