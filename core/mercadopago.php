<?php
require_once __DIR__ . '/../config/services.php';

function mp_access_token(): string {
    return trim((string)config_get('mp', 'access_token', ''));
}

function mp_ssl_verify(): bool {
    return config_bool('mp', 'ssl_verify', true);
}

function mp_local_test_mode(): bool {
    return config_bool('mp', 'local_test_mode', false);
}

function mp_request(string $method, string $url, ?array $payload = null): array {
    $token = mp_access_token();
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'missing_token'];
    }

    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, mp_ssl_verify());
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $data = is_string($response) && $response !== '' ? json_decode($response, true) : null;

    return [
        'ok' => $error === '' && $status >= 200 && $status < 300 && is_array($data),
        'status' => $status,
        'data' => $data,
        'error' => $error,
    ];
}

function mp_create_preference(array $preference_data): array {
    return mp_request('POST', 'https://api.mercadopago.com/checkout/preferences', $preference_data);
}

function mp_get_payment(string $payment_id): array {
    return mp_request('GET', 'https://api.mercadopago.com/v1/payments/' . rawurlencode($payment_id));
}

function mp_extract_payment_id(): ?string {
    $payment_id = $_GET['payment_id'] ?? $_GET['collection_id'] ?? null;
    if (!is_string($payment_id) || trim($payment_id) === '' || strtolower($payment_id) === 'null') {
        return null;
    }

    return trim($payment_id);
}

function mp_validate_approved_payment(?string $payment_id, string $expected_reference, float $expected_amount): bool {
    if (!$payment_id) {
        return false;
    }

    $result = mp_get_payment($payment_id);
    if (!$result['ok']) {
        error_log('Mercado Pago validation failed: status=' . $result['status'] . ' error=' . $result['error']);
        return false;
    }

    $payment = $result['data'];
    $status = (string)($payment['status'] ?? '');
    $reference = (string)($payment['external_reference'] ?? '');
    $amount = (float)($payment['transaction_amount'] ?? 0);

    return $status === 'approved'
        && hash_equals($expected_reference, $reference)
        && abs($amount - $expected_amount) < 0.01;
}

function mp_checkout_url(array $preference): string {
    return (string)($preference['init_point'] ?? $preference['sandbox_init_point'] ?? '');
}

