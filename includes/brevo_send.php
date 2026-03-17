<?php
/**
 * Shared Brevo email helper - matches working request_email_change.php behavior.
 * Use getenv + $_SERVER fallback for Render/Docker compatibility.
 */
function brevo_send_email($toEmail, $toName, $subject, $htmlContent) {
    $apiKey = getenv('BREVO_API_KEY') ?: ($_SERVER['BREVO_API_KEY'] ?? '');
    if (!$apiKey) {
        return ['success' => false, 'message' => 'Email service not configured. Please set BREVO_API_KEY.'];
    }

    $emailData = [
        "sender" => ["name" => "Arts Gym Portal", "email" => "lancegarcia841@gmail.com"],
        "to" => [["email" => $toEmail, "name" => $toName]],
        "subject" => $subject,
        "htmlContent" => $htmlContent
    ];

    $ch = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "api-key: " . $apiKey,
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($emailData),
        CURLOPT_RETURNTRANSFER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true];
    }
    $apiRes = json_decode($response, true);
    $reason = $apiRes['message'] ?? 'Unknown error';
    return ['success' => false, 'message' => $reason];
}
