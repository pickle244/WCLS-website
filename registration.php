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
    <?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    require 'vendor/autoload.php';
    function send_verification_email($first_name, $last_name, $email, $verify_token)
    {
      $mail = new PHPMailer(true);
      $mail->isSMTP();                                            //Send using SMTP
      $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
      $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
      $mail->Username   = 'jeffreyli69420@gmail.com';                     //SMTP username
      $mail->Password   = 'puux avdy cqyn lvum';                               //SMTP password
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            //Enable implicit TLS encryption
      $mail->Port       = 587;

      $mail->setFrom('jeffreyli69420@gmail.com', 'WCLS');
      $mail->addAddress($email, $first_name . " " . $last_name);
      $mail->isHTML(true);                                  //Set email format to HTML
      $mail->Subject = 'WCLS Email Verification';

      $email_template = "
        <h2>You have registered with Wellesley Chinese Language School</h2>
        <h5>Verify your email address with the link below:</h5>
        <br>
        <a href='http://localhost/WCLS-website/verify-email.php?token=$verify_token'>Verify</a>
      ";

      $mail->Body = $email_template;
      $mail->send();
      echo 'Verification email has been sent';
    }

    if(isset($_POST["SignUp"])) {
      // store user inputs
      $role = $_POST["role"];
      $first_name = $_POST["fName"];
      $last_name = $_POST["lName"];
      $email = $_POST["email"];
      $password = $_POST["password"];
      $password_confirm = $_POST["passwordConfirm"];
      $verify_token = md5(rand());

      // hash password for security
      $password_hash = password_hash($password, PASSWORD_DEFAULT);

      $errors = array();

      // if any field is missing, push respective errors
      if (empty($first_name) OR 
          empty($last_name) OR 
          empty($email) OR 
          empty($password) OR
          empty($password_confirm))
      {
        array_push($errors, "All fields are required");
      }

      // error if email is not proper format
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        array_push($errors, "Invalid email");
      }

      // error if password is less than 8 chars
      if (strlen($password) < 8) {
        array_push($errors, "Password must be at least 8 characters long");
      }

      if (ctype_lower($password)) {
        array_push($errors, "Password must contain an upper case character");
      }
      
      if (ctype_upper($password)) {
        array_push($errors, "Password must contain a lower case character");
      }

      if (preg_match('/[0-9]/', $password) == 0) {
        array_push($errors, "Password must contain a number");
      }

      if (preg_match('/[^a-zA-Z0-9]/', $password) == 0) {
        array_push($errors, "Password must contain a special character");
      }

      // error if passwords to not match
      if ($password !== $password_confirm) {
        array_push($errors, "Passwords do not match");
      }

      /*
      if any errors exist, prevent registration
      otherwise, add info to database 
      */
      if (count($errors) > 0) {
        foreach ($errors as $error) {
          echo "<div class='alert danger'>$error</div>";
        }
      } else {
        require_once "connection.php"; // establish connection to database

        // verify that the email is not already being used
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);
        $user = mysqli_fetch_array($result, MYSQLI_ASSOC);
        if ($user) {
          echo "<div class='alert danger'>Email already exists</div>";
        } else {
          $sql = "INSERT INTO users (
            first_name, 
            last_name, 
            email, 
            password, 
            account_type,
            verify_token) VALUES (?, ?, ?, ?, ?, ?)";
          $stmt = mysqli_stmt_init($conn);
          $prepStmt = mysqli_stmt_prepare($stmt, $sql); // create mysql statement
          if ($prepStmt) {
            // insert respective inputs into database columns
            mysqli_stmt_bind_param(
              $stmt, 
              "ssssss", 
              $first_name, 
              $last_name,
              $email,
              $password_hash,
              $role,
              $verify_token);
            mysqli_stmt_execute($stmt);
            send_verification_email(
              "$first_name", 
              "$last_name", 
              "$email", 
              "$verify_token");

            // start session to indicate user is registered and logged in
            session_start();
            $_SESSION["status"] = "Registration successful! Please verify your email address";
            header("Location: login.php");
            die();
          } else {
            $_SESSION["status"] = "Registration unsuccessful";
            header("Location: registration.php");
          }
        }
      }
    }
    ?>
    <form method="post" action="registration.php">
    <div class="input-group">
      <i class="fas fa-users"></i>
      <!-- <label for="role">Select Role</label> -->
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