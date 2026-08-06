<?php
/**
 * Email sending API (admin only).
 * POST: type (all|semester|individual), semester, recipients[], subject, body
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_same_origin();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$type = $_POST['type'] ?? 'all';
// Strip CR/LF from the subject: mail() writes it straight into the header
// block, so a newline would let the sender inject arbitrary headers (extra
// Bcc: recipients, a forged From:, etc).
$subject = trim(str_replace(["\r", "\n"], '', $_POST['subject'] ?? ''));

// A header may only contain ASCII. mail() writes the subject in verbatim, so a
// single curly quote, accented letter or em dash used to leave non-ASCII bytes
// in the header block — Postfix then demanded SMTPUTF8, the UVM relay does not
// offer it, and every message bounced 5.6.7 *after* mail() had already returned
// true. RFC 2047 encoding sidesteps that; pure ASCII passes through untouched.
$subjectHeader = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
$body = trim($_POST['body'] ?? '');

if (empty($subject) || empty($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Subject and message are required']);
    exit;
}

// Determine recipients
$recipients = [];

if ($type === 'all') {
    // Only users with a listing that is actually on the site. Someone whose
    // semester was deactivated is not visible to anyone, so a broadcast should
    // skip them. Mirrors $emailableUsers in admin.php — keep the two in step.
    $stmt = $pdo->query("SELECT DISTINCT s.username FROM sublets s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE);
    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($type === 'semester') {
    // No visibility filter here: picking a specific semester is an explicit
    // choice, including a deactivated one.
    $semester = $_POST['semester'] ?? '';
    if (empty($semester)) {
        http_response_code(400);
        echo json_encode(['error' => 'Semester required']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT DISTINCT username FROM sublets WHERE semester = ?");
    $stmt->execute([$semester]);
    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($type === 'individual') {
    $selected = $_POST['recipients'] ?? [];
    if (is_string($selected)) {
        $selected = json_decode($selected, true) ?: [];
    }
    $recipients = array_filter($selected);
}

// Each recipient becomes "{name}@uvm.edu" and is passed straight to mail(),
// which writes it into the header block. type=individual takes its list from
// the request body, so a value containing CR/LF could inject extra headers the
// same way an unsanitised subject could. Only real netid shapes get through.
$recipients = array_values(array_filter($recipients, static function ($u) {
    return is_string($u) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $u) === 1;
}));

if (empty($recipients)) {
    http_response_code(400);
    echo json_encode(['error' => 'No recipients found']);
    exit;
}

// What to call each recipient. Anyone who has not set a display name keeps
// their NetID, which is what every one of these emails used before.
$nameMap = [];
$emailColumns = table_columns($pdo, 'sublets');
if (isset($emailColumns['display_name'])) {
    $placeholders = implode(',', array_fill(0, count($recipients), '?'));
    $stmtNames = $pdo->prepare("SELECT username, display_name FROM sublets WHERE username IN ($placeholders)");
    $stmtNames->execute($recipients);
    foreach ($stmtNames->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nameMap[$row['username']] = poster_name($row);
    }
}

/**
 * Render the message for one recipient.
 *
 * "{name}" anywhere in the typed body is replaced with theirs; if the admin
 * did not use it, a "Hi <name>," line is added so the mail is addressed either
 * way. Substitution happens before escaping, so a name is escaped like any
 * other text rather than being trusted as markup.
 */
function render_email_html(string $body, string $name): string {
    $usesPlaceholder = str_contains($body, '{name}');
    $body = str_replace('{name}', $name, $body);

    $htmlBody = '';
    if (!$usesPlaceholder) {
        $htmlBody .= '<p>Hi ' . htmlspecialchars($name) . ',</p>';
    }
    foreach (explode("\n\n", $body) as $p) {
        $p = trim($p);
        if ($p !== '') {
            $htmlBody .= '<p>' . nl2br(htmlspecialchars($p)) . '</p>';
        }
    }

    return <<<HTML
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #00313C; max-width: 600px; margin: 0 auto;">
    <div style="background: #154734; padding: 1.5rem; text-align: center; border-radius: 8px 8px 0 0;">
        <h1 style="color: #FFD100; margin: 0; font-size: 1.5rem;">UVM Sublets</h1>
    </div>
    <div style="padding: 1.5rem; background: #ffffff; border: 1px solid #e0e4e5;">
        {$htmlBody}
    </div>
    <div style="padding: 1rem 1.5rem; background: #F7F7F7; border-radius: 0 0 8px 8px; font-size: 0.85rem; color: #7a8e93; text-align: center;">
        <p>This email was sent from <a href="https://sublet.aperkel.w3.uvm.edu" style="color: #154734;">UVM Sublets</a></p>
    </div>
</body>
</html>
HTML;
}

$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: UVM Sublets <aperkel@uvm.edu>',
];

$sent = 0;
$failed = 0;

foreach ($recipients as $username) {
    $to = $username . '@uvm.edu';
    $html = render_email_html($body, $nameMap[$username] ?? $username);
    if (mail($to, $subjectHeader, $html, implode("\r\n", $headers))) {
        $sent++;
    } else {
        $failed++;
    }
}

// Send admin a copy, rendered the way the first recipient would have seen it.
$emailHtml = render_email_html($body, $nameMap[$recipients[0]] ?? $recipients[0]);
$recipientList = implode(', ', $recipients);
$adminSummary = '<div style="background: #fffbe6; border: 1px solid #ffe082; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; font-size: 0.9rem;">'
    . '<strong>Admin Copy</strong> — This is what was sent to users.<br>'
    . 'Sent to ' . $sent . ' recipient(s): ' . htmlspecialchars($recipientList) . '<br>'
    . 'Failed: ' . $failed
    . '</div>';
$adminEmailHtml = str_replace('<div style="padding: 1.5rem; background: #ffffff; border: 1px solid #e0e4e5;">', '<div style="padding: 1.5rem; background: #ffffff; border: 1px solid #e0e4e5;">' . $adminSummary, $emailHtml);
mail('aperkel@uvm.edu', mb_encode_mimeheader("[Copy] $subject", 'UTF-8', 'B', "\r\n"), $adminEmailHtml, implode("\r\n", $headers));

echo json_encode([
    'success' => true,
    'sent' => $sent,
    'failed' => $failed,
    'total' => count($recipients)
]);
