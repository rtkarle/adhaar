<?php
/**
 * Adhaar AI Python Bridge
 * Calls the Flask API from PHP using cURL.
 * Include: require_once __DIR__ . '/ai_python.php';
 */

define('AI_FLASK_URL', getenv('AI_FLASK_URL') ?: 'http://localhost:5000');
define('AI_TIMEOUT_SEC', 10);

/**
 * Call any Flask AI endpoint.
 * @param string $endpoint  e.g. 'donation_match', 'volunteer_recommend'
 * @param array  $payload   Data to POST as JSON
 * @return array|null       Decoded JSON response or null on failure
 */
function ai_call(string $endpoint, array $payload): ?array {
    $url = AI_FLASK_URL . '/api/v1/' . ltrim($endpoint, '/');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => AI_TIMEOUT_SEC,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $http_code === 0) {
        error_log("AI Python Bridge error: $err (HTTP $http_code)");
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * MODULE 1 — Smart Donation Matching
 * Returns match quality + recommendation for a donation → NGO pairing.
 */
function ai_donation_match(
    string $donation_category,
    int    $quantity,
    string $urgency,
    string $donor_zone,
    string $beneficiary_zone,
    string $ngo_category,
    int    $ngo_capacity,
    int    $donor_past_donations = 0
): ?array {
    return ai_call('donation_match', [
        'donation_category'      => $donation_category,
        'quantity'               => $quantity,
        'urgency'                => $urgency,
        'donor_zone'             => $donor_zone,
        'beneficiary_zone'       => $beneficiary_zone,
        'ngo_required_category'  => $ngo_category,
        'ngo_capacity'           => $ngo_capacity,
        'donor_past_donations'   => $donor_past_donations,
    ]);
}

/**
 * MODULE 2 — Volunteer Recommendation
 * Scores a list of volunteers for a donation pickup.
 * @param string $donation_zone  Zone of pickup address
 * @param array  $volunteers     Array of volunteer data arrays
 */
function ai_volunteer_recommend(string $donation_zone, array $volunteers): ?array {
    return ai_call('volunteer_recommend', [
        'donation_zone' => $donation_zone,
        'volunteers'    => $volunteers,
    ]);
}

/**
 * MODULE 3 — Product Recommendation
 * Scores product list based on user browsing/purchase/search history.
 */
function ai_product_recommend(
    string $searched_category,
    string $bought_category,
    string $viewed_category,
    array  $products
): ?array {
    return ai_call('product_recommend', [
        'user_searched_category' => $searched_category,
        'user_bought_category'   => $bought_category,
        'user_viewed_category'   => $viewed_category,
        'products'               => $products,
    ]);
}

/**
 * MODULE 4 — Analytics Prediction
 * Predicts next week donations + marketplace sales.
 */
function ai_analytics_predict(array $weekly_stats): ?array {
    return ai_call('analytics_predict', $weekly_stats);
}

/**
 * Gemini LLM Chat
 * @param string $message  User's question
 * @param string $context  'donor' | 'volunteer' | 'admin' | 'seller'
 * @param string $language 'en' | 'hi' | 'mr'
 */
function ai_chat(string $message, string $context = 'donor', string $language = 'en'): ?array {
    return ai_call('ai_chat', [
        'message'  => $message,
        'context'  => $context,
        'language' => $language,
    ]);
}

/**
 * Health check — returns true if Flask API is running.
 */
function ai_health_check(): bool {
    $ch = curl_init(AI_FLASK_URL . '/api/v1/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}
