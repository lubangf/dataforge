<?php

declare(strict_types=1);

require dirname(__DIR__) . '/backend/services/leads.php';

function dataforge_env(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }

    return is_string($value) ? trim($value) : $default;
}

function dataforge_notify_new_lead(array $lead): void
{
    $adminEmail = dataforge_env('DATAFORGE_LEAD_NOTIFY_EMAIL');
    if ($adminEmail === '') {
        return;
    }

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('DATAFORGE_LEAD_NOTIFY_EMAIL is not a valid email address.');
        return;
    }

    $subject = sprintf('New Data Forge inquiry: %s', (string)($lead['name'] ?? 'Unknown contact'));
    $body = implode("\r\n", [
        'New inquiry captured from Data Forge contact form.',
        '',
        'Lead ID: ' . (string)($lead['leadId'] ?? ''),
        'Created (UTC): ' . (string)($lead['createdAtUtc'] ?? ''),
        'Name: ' . (string)($lead['name'] ?? ''),
        'Email: ' . (string)($lead['email'] ?? ''),
        'Primary Need: ' . (string)($lead['primaryNeed'] ?? ''),
        'Context: ' . (string)($lead['context'] ?? ''),
        'IP: ' . (string)($lead['ip'] ?? ''),
        'User-Agent: ' . (string)($lead['userAgent'] ?? ''),
    ]);

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $mailFrom = dataforge_env('DATAFORGE_MAIL_FROM');
    if ($mailFrom !== '' && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'From: ' . $mailFrom;
    }

    $leadEmail = (string)($lead['email'] ?? '');
    if ($leadEmail !== '' && filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $leadEmail;
    }

    $sent = @mail($adminEmail, $subject, $body, implode("\r\n", $headers));
    if ($sent !== true) {
        error_log('Data Forge lead saved but email notification failed to send.');
    }
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$isJson = strpos($contentType, 'application/json') !== false;

$payload = [];
if ($isJson) {
    $rawBody = file_get_contents('php://input') ?: '';
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
} else {
    $payload = $_POST;
}

$name = trim((string)($payload['name'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$primaryNeed = trim((string)($payload['primaryNeed'] ?? ''));
$context = trim((string)($payload['context'] ?? ''));

if ($name === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please provide your name.']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

if ($primaryNeed === '') {
    $primaryNeed = 'General product consultation';
}

$lead = [
    'leadId' => 'df_lead_' . bin2hex(random_bytes(6)),
    'createdAtUtc' => gmdate('c'),
    'name' => $name,
    'email' => $email,
    'primaryNeed' => $primaryNeed,
    'context' => $context,
    'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    'userAgent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400),
];

try {
    dataforge_leads_append($lead);
    dataforge_notify_new_lead($lead);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to capture your inquiry right now.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Thanks. Your inquiry has been captured.',
    'leadId' => $lead['leadId'],
]);
