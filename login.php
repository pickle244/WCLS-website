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
      <?php
        if (isset($_SESSION['status'])) {
          echo "<h4>".$_SESSION['status']."</h4>";
          unset($_SESSION['status']);
        }
      ?>
    </div>
      <h1 class="form-title">Sign In</h1>
      <?php
      if (isset($_POST["SignIn"])) {
        $role = $_POST["role"];
        $email = $_POST["email"];
        $password = $_POST["password"];

        require_once "connection.php"; // establish connection to database

        // verify that email exists
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);
        $user = mysqli_fetch_array($result, MYSQLI_ASSOC);
        if ($user) {
          // verify that password matches
          if (password_verify($password, $user["password"])) {
            // start session to indicate user is logged in
            session_start();
            $_SESSION["user"] = "yes";
            header("Location: index.php");
            die();
          } else {
            echo "<div class='alert danger'>Password does not match</div>";
          }
        } else {
          echo "<div class='alert danger'>Email does not match</div>";

        }
      }
      ?>
      <form method="post" action="login.php">

      <!-- <div class="input-group">
        <i class="fas fa-users"></i>
        
        <select name="role" id="role" required>
          <option value="" disabled selected hidden>--Select Role--</option>
          <option value="Parent">Parent</option>
          <option value="Teacher">Teacher</option>
          <option value="Admin">Admin</option>
      </select>
      </div> -->

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
        <button id="signUpButton"><a href="registration.php">Sign Up</a></button>
    </div>
  </div>
  <script src="script.js"></script>
</body>
</html>