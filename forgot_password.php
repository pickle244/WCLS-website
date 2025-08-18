<?php
// forgot_password.php
// Goal: user enters email -> we create a token + expiry (DATETIME) -> send reset link via email.

session_start();

// 1) DB connection
require 'connection.php';

// 2) PHPMailer (installed via Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$info = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_request'])) {
    // --- Get and validate email from form ---
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Basic server-side validation
        $error = 'Please enter a valid email address.';
    } else {
        // (Optional) Check if email exists in `users` table.
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        // For security, we always show the same message,
        // but only generate a token if the email exists.
        if ($exists) {
            // --- Create a raw token and an expiry (DATETIME string) valid for 1 hour ---
            $token     = bin2hex(random_bytes(32));          // raw token to send to the user
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // store as DATETIME to match your schema

            // Remove old tokens for this email (cleanup is good practice)
            $del = $conn->prepare("DELETE FROM password_reset WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();
            $del->close();

            // Insert the new token row (table name is singular: password_reset)
            $ins = $conn->prepare("INSERT INTO password_reset (email, token, expires) VALUES (?,?,?)");
            $ins->bind_param("sss", $email, $token, $expiresAt);
            $ins->execute();
            $ins->close();

            // Build an absolute reset link to reset_password.php with the token
            $resetLink = sprintf('%s://%s%s/reset_password.php?token=%s',
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
                $_SERVER['HTTP_HOST'],
                rtrim(dirname($_SERVER['PHP_SELF']), '/\\'),
                $token
            );

            // --- Send email (use the SAME SMTP settings as your teammate) ---
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();                                     // Use SMTP
                $mail->Host       = 'smtp.gmail.com';                // Gmail SMTP server
                $mail->SMTPAuth   = true;                            // Enable SMTP auth
                $mail->Username   = 'jeffreyli69420@gmail.com';      // <-- SAME as registration.php
                $mail->Password   = 'puux avdy cqyn lvum';           // <-- SAME Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // TLS
                $mail->Port       = 587;

                // setFrom should match your authenticated account for best deliverability
                $mail->setFrom('jeffreyli69420@gmail.com', 'WCLS');
                $mail->addAddress($email);                           // Recipient

                $mail->isHTML(true);
                $mail->Subject = 'Reset your password';
                $mail->Body    = '
                    <p>You requested a password reset.</p>
                    <p>Click this link (valid for 1 hour):</p>
                    <p><a href="'.$resetLink.'">'.$resetLink.'</a></p>
                    <p>If you did not request this, you can ignore this email.</p>
                ';
                $mail->AltBody = "Reset link (valid 1 hour): $resetLink";

                $mail->send();
            } catch (Exception $e) {
                // For local debugging you may temporarily echo $mail->ErrorInfo
                // echo 'Mailer Error: ' . $mail->ErrorInfo;
            }
        }

        // Always show the same status to avoid leaking which emails exist
        $_SESSION['status'] = 'If that email exists, a reset link has been sent.';
        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h1 class="form-title">Forgot Password</h1>

    <?php if ($info): ?>
      <div class="alert" style="color: green;"><?php echo htmlspecialchars($info); ?></div>
    <?php elseif ($error): ?>
      <div class="alert" style="color: red;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Simple form: user enters their email to receive the reset link -->
    <form method="post" action="forgot_password.php">
      <div class="input-group">
        <i class="fas fa-envelope"></i>
        <input type="email" name="email" id="fp_email" placeholder="Your Email" required>
        <label for="fp_email">Email</label>
      </div>
      <input type="submit" class="btn" value="Send Reset Link" name="reset_request">
    </form>
  </div>
</body>
</html>
