<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register & Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" id="signup">
      <h1 class="form-title">Register</h1>
      <?php
    if(isset($_POST["SignUp"])) {
      $first_name = $_POST["fName"];
      $last_name = $_POST["lName"];
      $email = $_POST["email"];
      $password = $_POST["password"];
      $password_confirm = $_POST["passwordConfirm"];
      $password_hash = password_hash($password, PASSWORD_DEFAULT);
      $errors = array();
      if (empty($first_name) OR 
          empty($last_name) OR 
          empty($email) OR 
          empty($password) OR
          empty($password_confirm))
      {
        array_push($errors, "All fields are required");
      }
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        array_push($errors, "Invalid email");
      }
      if (strlen($password) < 8) {
        array_push($errors, "Password must be at least 8 characters long");
      }
      if ($password !== $password_confirm) {
        array_push($errors, "Passwords do not match");
      }
      if (count($errors) > 0) {
        foreach ($errors as $error) {
          echo "<div class='alert registration-error'>$error</div>";
        }
      }
    }
    ?>
      <form method="post" action="index.php">
      <div class="input-group">
        <i class="fas fa-users"></i>
        <!-- <label for="role">Select Role</label> -->
        <select name="role" i="role" required>
          <option value="">--Select Role--</option>
          <option value="Parent">Parent</option>
          <option value="Teacher">Teacher</option>
          <option value="Admin">Admin</option>
        </select>
      </div>
        <div class="input-group">
           <i class="fas fa-user"></i>
           <input type="text" name="fName" id="fName" placeholder="First Name" required>
           <label for="fname">First Name</label>
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
            <label for="password">Password</label>
        </div>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="passwordConfirm" id="passwordConfirm" placeholder="Confirm Password" required>
            <label for="passwordConfirm">Password</label>
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
        <button id="signInButton">Sign In</button>
</div>
</div>


<div class="container" id="signIn">
      <h1 class="form-title">Sign In</h1>
      <form method="_POST" action="">

      <div class="input-group">
        <label for="role">Select Role</label>
        <select name="role" i="role" required>
          <option value="">--Select Role--</option>
          <option value="Parent">Parent</option>
          <option value="Teacher">Teacher</option>
          <option value="Admin">Admin</option>
        </select>
      </div>

        <!-- <div class="input-group">
           <i class="fas fa-user"></i>
           <input type="text" name="fName" id="fName" placeholder="First Name" required>
           <label for="fname">First Name</label>
        </div>
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="lName" id="lName" placeholder="Last Name" required>
            <label for="lName">Last Name</label>
        </div> -->

        <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" id="email" placeholder="Email" required>
            <label for="email">Email</label>
        </div>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="password" placeholder="Password" required>
            <label for="password">Password</label>
        </div>
         <p class="recover">
          <a href="#">Recover Password</a>
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
        <button id="signUpButton">Sign Up</button>
</div>
</div>
  
</body>
</html>
