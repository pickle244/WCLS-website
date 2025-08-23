<?php
// reset_password.php
// Goal: validate token (from URL) -> show form -> update users.password -> delete token.

session_start();
require 'connection.php';

$invalid = false;        // Controls whether we show the form
$emailForReset = '';     // Email fetched from table for this token
$errorMsgs = [];             // Form error 
$statusMsg='';  

// 1) Read token from URL and validate existence/expiry (expires is DATETIME)
$token = $_GET['token'] ?? '';
if ($token === '') {
    $invalid = true; // No token provided at all
} else {
    $stmt = $conn->prepare("SELECT email, expires FROM password_reset WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($emailForReset, $expiresDT);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        // Token not found
        $invalid = true;
    }else{
      // Compare DATETIME (string) with current time by converting to timestamp
      if (strtotime($expiresDT) <= time()) {
          // clean up this expired token row
          $del = $conn->prepare("DELETE FROM password_reset WHERE token = ?");
          $del->bind_param("s", $token);
          $del->execute();
          $del->close();
          $invalid = true; // Token expired
    }
}
}

// 2) Handle POST: user submits new password
if (!$invalid && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $newPass = $_POST['password'] ?? '';
    $confirm = $_POST['passwordConfirm'] ?? '';


    if (strlen($newPass) < 8) {
      $errorMsgs[] = 'Password must be at least 8 characters long';
      }
    if (!preg_match('/[A-Z]/', $newPass)) {
        $errorMsgs[] = 'Password must contain an upper-case letter';
    }
    if (!preg_match('/[a-z]/', $newPass)) {
        $errorMsgs[] = 'Password must contain a lower-case letter';
    }
    if (!preg_match('/\d/', $newPass)) {              // same as /[0-9]/
        $errorMsgs[] = 'Password must contain a number';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $newPass)) {
        $errorMsgs[] = 'Password must contain a special character';
    }
    if ($newPass === '' || $newPass !== $confirm) {
        $errorMsgs[] = 'Passwords do not match';
    }

    if(empty($errorMsgs)){
        // Hash the new password (users.password stores hash)
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);

        // Update users.password for this email
        $u = $conn->prepare("UPDATE users SET password = ? WHERE email = ? LIMIT 1");
        $u->bind_param("ss", $newHash, $emailForReset);
        $u->execute();
        $u->close();

        // Delete all tokens for this email (cleanup)
        $d = $conn->prepare("DELETE FROM password_reset WHERE email = ?");
        $d->bind_param("s", $emailForReset);
        $d->execute();
        $d->close();

        $_SESSION['status'] = 'Password reset successful! Please login.';
        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h1 class="form-title">Reset Password</h1>

    <?php if ($invalid): ?>
      <!-- Token invalid or expired -->
      <div class="alert" style="color: red;">Reset link is invalid or has expired.</div>
    <?php else: ?>
      <?php if (!empty($error)): ?>
        <div class="alert" style="color: red;"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!-- Show new password form only if the token is valid -->
      <form method="post" action="">
        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" id="rp_password" placeholder="New Password" required>
          <label for="rp_password">New Password</label>
        </div>
        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input type="password" name="passwordConfirm" id="rp_passwordConfirm" placeholder="Confirm New Password" required>
          <label for="rp_passwordConfirm">Confirm New Password</label>
        </div>
        <input type="submit" class="btn" value="Set New Password" name="reset_password">
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
