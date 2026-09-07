<?php
// includes/paystack.php — thin wrapper around Paystack's REST API.
//
// IMPORTANT — how this actually gets tested: this sandbox cannot reach
// api.paystack.co (network is restricted to a fixed allowlist), so the live
// HTTP round-trip has NOT been executed here. This is written strictly against
// Paystack's documented request/response shape. You MUST verify the real
// round-trip yourself once your test keys are in config.php — initialize a
// transaction, pay with a Paystack test card, and confirm verify_payment()
// returns success before ever switching to live keys.

require_once __DIR__ . '/db.php';

/**
 * Start a Paystack transaction. Returns the authorization_url to redirect the
 * customer to, or throws on failure.
 */
function paystack_initialize(string $email, float $amountNaira, string $reference, string $callbackUrl): string {
    $url = 'https://api.paystack.co/transaction/initialize';
    $fields = [
        'email'        => $email,
        'amount'       => (int) round($amountNaira * 100), // Paystack expects kobo, not naira
        'reference'    => $reference,
        'callback_url' => $callbackUrl,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($fields),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Paystack initialize cURL error: ' . $curlError);
        throw new Exception('Could not reach the payment provider. Please try again.');
    }

    $data = json_decode($response, true);
    if (empty($data['status']) || empty($data['data']['authorization_url'])) {
        error_log('Paystack initialize failed: ' . $response);
        throw new Exception('Could not start payment. Please try again.');
    }

    return $data['data']['authorization_url'];
}

/**
 * Verify a transaction server-side — NEVER trust the callback URL alone to mean
 * payment succeeded; always re-check with Paystack directly using the secret key.
 * Returns true only if Paystack confirms status === 'success'.
 */
function paystack_verify(string $reference): bool {
    $url = 'https://api.paystack.co/transaction/verify/' . urlencode($reference);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Paystack verify cURL error: ' . $curlError);
        return false;
    }

    $data = json_decode($response, true);
    return !empty($data['status']) && ($data['data']['status'] ?? '') === 'success';
}
