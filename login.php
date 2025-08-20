<?php
session_start();
// if user is already logged in, redirect them to the dashboard
if (isset($_SESSION["user"])) {
  header("Location: index.php");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container" id="signIn">
    <div class="alert">
      <?php require 'script.php'?>
    </div>
      <h1 class="form-title">Sign In</h1>
      <form method="post" action="login.php">
        <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" id="email" placeholder="Email" required>
            <label for="email">Email</label>
        </div>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            
              <input type="password" name="password" id="password" placeholder="Password" required>
              <span class="toggle-password" toggle="#password">
                <i class="fas fa-eye"></i>
          </span>
            <label for="password">Password</label>
        </div>
         <p class="recover">
          <!-- change # to forgot_password.php -->
          <a href="forgot_password.php">Recover Password</a>
        </p>
        <input type="submit" class="btn" value="Sign In" name="SignIn">
      </form>
      <!-- link section -->
      <p class="or">
        ------------or-----------
      </p>
      <div class="icons">
        <i class="fab fa-google"></i>
        <i class="fab fa-facebook"></i>
      </div>
      <div class="links">
        <p>Don't have account yet?</p>
        <button id="signUpButton"><a href="registration.php">Sign Up</a></button>
    </div>
  </div>
  <script src="script.js"></script>
</body>
</html>