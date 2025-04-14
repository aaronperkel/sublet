<?php
include 'top.php';

// Only allow admin access
if ($_SERVER['REMOTE_USER'] !== 'aperkel') {
    die("Access denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $body_text = trim($_POST['message'] ?? '');

    if (empty($subject) || empty($body_text)) {
        echo "<p>Please provide both a subject and a message.</p>";
    } else {
        // Process body text: split paragraphs on double newlines, replace newlines with <br>, wrap each in <p>
        $paragraphs = preg_split("/\n\s*\n/", $body_text);
        $html_paragraphs = [];
        foreach ($paragraphs as $p) {
            $p = nl2br(htmlspecialchars($p));
            $html_paragraphs[] = "<p style=\"font: 14pt serif;\">{$p}</p>";
        }
        $html_body = implode("", $html_paragraphs);
        // Append signature block
        $html_body .= '
<p style="font: 14pt serif;">
  <span style="color: green;"><a href="https://sublet.aperkel.w3.uvm.edu">UVM Sublets</a></span><br>
  P: (478)262-8935 | E: aperkel@uvm.edu
</p>';

        // Get all distinct usernames from posts
        $stmt = $pdo->query("SELECT DISTINCT username FROM sublets");
        $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: aperkel@uvm.edu\r\n";

        $sentEmails = [];
        foreach ($usernames as $username) {
            $to = $username . "@uvm.edu";
            if (mail($to, $subject, $html_body, $headers)) {
                $sentEmails[] = $to;
            }
        }
        $mail_sent_count = count($sentEmails);

        mail('aperkel@uvm.edu', $subject, $html_body, $headers);

        // Send confirmation email to admin with list of recipients
        $adminTo = "aperkel@uvm.edu";
        $confirmationSubject = "Confirmation: Mail sent to users";
        $confirmationMessage = "<p>The following emails were sent:</p><ul>";
        foreach ($sentEmails as $email) {
            $confirmationMessage .= "<li>" . htmlspecialchars($email) . "</li>";
        }
        $confirmationMessage .= "</ul>";
        mail($adminTo, $confirmationSubject, $confirmationMessage, $headers);

        echo "<p>Mail sent to {$mail_sent_count} users. Confirmation email sent to admin.</p>";
    }
}
?>

<h2>Send Mail to All Users</h2>
<form action="send_mail.php" method="post">
    <label for="subject">Subject:</label><br>
    <input type="text" name="subject" id="subject" required><br><br>
    <label for="message">Message (use double newline for new paragraphs):</label><br>
    <textarea name="message" id="message" rows="10" cols="50" required></textarea><br><br>
    <input type="submit" value="Send Mail">
</form>

<?php include 'footer.php'; ?>
