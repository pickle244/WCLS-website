<?php 
// reset_password.php
// validate token (from URL) -> show form -> update users.password -> delete token.

session_start();
require 'connection.php';

/* Variable declarations (state for rendering + processing)
 * $invalid        : if true, we do NOT show the reset form (invalid/missing/expired token)
 * $emailForReset  : email associated with the valid token (fetched from password_reset)
 * $errorMsgs      : validation errors for the submitted passwords
 * $statusMsg      : placeholder for any general status
 */
$invalid = false;        
$emailForReset = '';     
$errorMsgs = [];         
$statusMsg='';           

/* ------------------------------
 * 1) Validate token from URL (existence + expiry)
 *    - If token missing or not found => mark as invalid
 *    - If token expired => delete that token row and mark as invalid
 * ------------------------------ */
$token = $_GET['token'] ?? '';
if ($token === '') {
    // No token in URL => cannot proceed
    $invalid = true;
} else {
    //  look token in password_reset table
    $stmt = $conn->prepare("SELECT email, expires FROM password_reset WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($emailForReset, $expiresDT);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        // Token not found at all
        $invalid = true;
    } else {
        // Compare DATETIME (string) with current time by converting to timestamp
        if (strtotime($expiresDT) <= time()) {
            // Token is expired -> clean up this specific token row
            $del = $conn->prepare("DELETE FROM password_reset WHERE token = ?");
            $del->bind_param("s", $token);
            $del->execute();
            $del->close();

            // Mark as invalid so the form won't show
            $invalid = true;
        }
    }
}

/*Handle POST submit (only when token is valid)
 *    - Validate password strength rules and match
 *    - If valid:
 *        a) Hash password
 *        b) Update users.password by email
 *        c) Delete ALL tokens for that email (cleanup)
 *        d) Set session banner + redirect to login
 * ------------------------------ */
if (!$invalid && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $newPass = $_POST['password'] ?? '';
    $confirm = $_POST['passwordConfirm'] ?? '';

    // Password policy checks
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

    // If no errors, persist the new password and clean up tokens
    if (empty($errorMsgs)) {
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

        // Success banner then redirect to Login
        $_SESSION['registration_status'] = 'Password reset successful! Please login.';
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
  <div class="container">
    <h1 class="form-title">Reset Password</h1>

    <?php if ($invalid): ?>
      <!-- Case A: token invalid or expired -> only show a red alert, no form -->
      <div class="alert" style="color: red;">Reset link is invalid or has expired.</div>
    <?php else: ?>
      <!-- Case B: token valid -> optionally show validation errors + the form -->
      <?php if (!empty($errorMsgs)): ?>
        <div class="alert danger">
          <ul style="margin-left:1rem;">
            <?php foreach ($errorMsgs as $msg): ?>
              <li><?php echo htmlspecialchars($msg, ENT_QUOTES, 'utf-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Show new password form only if the token is valid -->
      <form method="post" action="">
        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" id="rp_password" placeholder="New Password" required>
          <span class="toggle-password"><i class="fas fa-eye"></i></span>
          <label for="rp_password">New Password</label>
        </div>

        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input type="password" name="passwordConfirm" id="rp_passwordConfirm" placeholder="Confirm New Password" required>
          <span class="toggle-password"><i class="fas fa-eye"></i></span>
          <label for="rp_passwordConfirm">Confirm New Password</label>
        </div>

        <input type="submit" class="btn" value="Set New Password" name="reset_password">
      </form>
    <?php endif; ?>
  </div>

  <script src="script.js"></script>
</body>
</html>
