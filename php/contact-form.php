<?php
/**
 * Jaibaba Interlinings — Bulk Enquiry Form Handler
 * Validates & sanitizes the enquiry form, sends notification email, returns JSON.
 *
 * SMTP (for future use): to switch from PHP mail() to SMTP, install PHPMailer via
 * Composer and replace the sendNotificationEmail() body with a PHPMailer SMTP call.
 * Keep RECIPIENT_EMAIL / SMTP credentials in environment variables, not in this file.
 */

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

const RECIPIENT_EMAIL = 'Jaibabananakclothtrading@gmail.com';
const SITE_NAME       = 'Jaibaba Interlinings';

// SMTP settings placeholder — populate and wire up when moving off PHP mail().
const SMTP_HOST       = '';
const SMTP_PORT       = 587;
const SMTP_USERNAME   = '';
const SMTP_PASSWORD   = '';
const SMTP_ENCRYPTION = 'tls';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function respond(bool $success, string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function cleanText(string $value): string
{
    $value = trim($value);
    $value = strip_tags($value);
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Blocks header-injection attempts (newlines used to smuggle extra mail headers).
 */
function isSafeSingleLine(string $value): bool
{
    return strpbrk($value, "\r\n") === false;
}

// ---------------------------------------------------------------------------
// Request guard
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', 405);
}

// ---------------------------------------------------------------------------
// Collect & validate fields
// ---------------------------------------------------------------------------

$requiredFields = [
    'full_name' => 'Full Name',
    'company'   => 'Company Name',
    'email'     => 'Email Address',
    'phone'     => 'Phone Number',
    'city'      => 'City',
    'country'   => 'Country',
    'product'   => 'Product Required',
    'quantity'  => 'Quantity Required',
];

$errors = [];
$data   = [];

foreach ($requiredFields as $key => $label) {
    $raw = isset($_POST[$key]) ? (string) $_POST[$key] : '';

    if ($raw === '') {
        $errors[] = "{$label} is required.";
        continue;
    }

    if (!isSafeSingleLine($raw)) {
        $errors[] = "{$label} contains invalid characters.";
        continue;
    }

    $data[$key] = cleanText($raw);
}

// Message is optional
$rawMessage    = isset($_POST['message']) ? (string) $_POST['message'] : '';
$data['message'] = trim(strip_tags($rawMessage));
$data['message'] = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');

// Email format validation
if (isset($data['email'])) {
    $filteredEmail = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if ($filteredEmail === false) {
        $errors[] = 'Please provide a valid email address.';
    } else {
        $data['email'] = $filteredEmail;
    }
}

// Phone sanity check — digits, spaces, +, -, () only, 7-20 characters
if (isset($data['phone'])) {
    $phoneDigitsOnly = preg_replace('/[^0-9]/', '', $_POST['phone']);
    if (strlen($phoneDigitsOnly) < 7 || strlen($phoneDigitsOnly) > 15) {
        $errors[] = 'Please provide a valid phone number.';
    }
}

if (!empty($errors)) {
    respond(false, implode(' ', $errors), 422);
}

// ---------------------------------------------------------------------------
// Compose & send email
// ---------------------------------------------------------------------------

function sendNotificationEmail(array $data): bool
{
    $to      = RECIPIENT_EMAIL;
    $subject = 'New Bulk Enquiry from Website';

    $body  = "A new bulk enquiry has been submitted on the " . SITE_NAME . " website.\r\n\r\n";
    $body .= "Name: {$data['full_name']}\r\n";
    $body .= "Company: {$data['company']}\r\n";
    $body .= "Email: {$data['email']}\r\n";
    $body .= "Phone: {$data['phone']}\r\n";
    $body .= "City: {$data['city']}\r\n";
    $body .= "Country: {$data['country']}\r\n";
    $body .= "Product Required: {$data['product']}\r\n";
    $body .= "Quantity: {$data['quantity']}\r\n";
    $body .= "Message:\r\n{$data['message']}\r\n\r\n";
    $body .= "Submitted: " . date('Y-m-d H:i:s') . "\r\n";

    $safeReplyToName = str_replace(['"', "\r", "\n"], '', $data['full_name']);

    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . SITE_NAME . ' Website <no-reply@jaibabainterlinings.com>';
    $headers[] = "Reply-To: \"{$safeReplyToName}\" <{$data['email']}>";
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

$mailSent = sendNotificationEmail($data);

if (!$mailSent) {
    respond(false, 'We could not send your enquiry right now. Please call +91 9910555033 or email us directly.', 500);
}

respond(true, 'Thank you, ' . $data['full_name'] . '. Your enquiry has been received — our team will contact you within one business day.');
