<?php
session_start()
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container" id="signup">
    <h1 class="form-title">Register</h1>
    <?php require 'script.php';?>
    <form method="post" action="registration.php">
    <div class="input-group">
      <i class="fas fa-users"></i>
      <select name="role" id="role" required>
        <option value="">--Select Role--</option>
        <option value="Parent">Parent</option>
        <option value="Teacher">Teacher</option>
        <option value="Admin">Admin</option>
      </select>
    </div>
      <div class="input-group">
          <i class="fas fa-user"></i>
          <input type="text" name="fName" id="fName" placeholder="First Name" required>
          <label for="fName">First Name</label>
      </div>
      <div class="input-group">
          <i class="fas fa-user"></i>
          <input type="text" name="lName" id="lName" placeholder="Last Name" required>
          <label for="lName">Last Name</label>
      </div>
      <div class="input-group">
          <i class="fas fa-envelope"></i>
          <input type="email" name="email" id="email" placeholder="Email" required>
          <label for="email">Email</label>
      </div>
      <div class="input-group">
          <i class="fas fa-lock"></i>
          
            <input type="password" name="password" id="password" placeholder="Password" required>
            <span class="toggle-password" toggle="#passwordConfirm">
              <i class="fas fa-eye"></i>
        </span>
          <label for="password">Password</label>
      </div>
      <div class="input-group">
          <i class="fas fa-lock"></i>
          
            <input type="password" name="passwordConfirm" id="passwordConfirm" placeholder="Confirm Password" required>
            <span class="toggle-password" toggle="#password">
              <i class="fas fa-eye"></i>
        </span>
          <label for="passwordConfirm">Confirm Password</label>
      </div>
      <input type="submit" class="btn" value="Sign Up" name="SignUp">
    </form>
    <p class="or">
      ------------or-----------

    </p>
    <div class="icons">
      <i class="fab fa-google"></i>
      <i class="fab fa-facebook"></i>
    </div>
    <div class="links">
      <p>Already Have Account ?</p>
      <button id="signInButton"><a href="login.php">Sign In</a></button>
    </div>
  </div>
  <script src="script.js"></script>
</body>
</html>