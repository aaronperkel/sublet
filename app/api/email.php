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

if (empty($recipients)) {
    http_response_code(400);
    echo json_encode(['error' => 'No recipients found']);
    exit;
}

// Build HTML email
$htmlBody = '';
$paragraphs = explode("\n\n", $body);
foreach ($paragraphs as $p) {
    $htmlBody .= '<p>' . nl2br(htmlspecialchars(trim($p))) . '</p>';
}

$emailHtml = <<<HTML
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

$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: UVM Sublets <aperkel@uvm.edu>',
];

$sent = 0;
$failed = 0;

foreach ($recipients as $username) {
    $to = $username . '@uvm.edu';
    if (mail($to, $subject, $emailHtml, implode("\r\n", $headers))) {
        $sent++;
    } else {
        $failed++;
    }
}

// Send admin a copy
$recipientList = implode(', ', $recipients);
$adminSummary = '<div style="background: #fffbe6; border: 1px solid #ffe082; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; font-size: 0.9rem;">'
    . '<strong>Admin Copy</strong> — This is what was sent to users.<br>'
    . 'Sent to ' . $sent . ' recipient(s): ' . htmlspecialchars($recipientList) . '<br>'
    . 'Failed: ' . $failed
    . '</div>';
$adminEmailHtml = str_replace('<div style="padding: 1.5rem; background: #ffffff; border: 1px solid #e0e4e5;">', '<div style="padding: 1.5rem; background: #ffffff; border: 1px solid #e0e4e5;">' . $adminSummary, $emailHtml);
mail('aperkel@uvm.edu', "[Copy] $subject", $adminEmailHtml, implode("\r\n", $headers));

echo json_encode([
    'success' => true,
    'sent' => $sent,
    'failed' => $failed,
    'total' => count($recipients)
]);
