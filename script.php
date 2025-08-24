<?php
  use PHPMailer\PHPMailer\PHPMailer;
  use PHPMailer\PHPMailer\SMTP;
  use PHPMailer\PHPMailer\Exception;

  require 'vendor/autoload.php';
  function send_verification_email($first_name, $last_name, $email, $verify_token)
  {
    $mail = new PHPMailer(true);
    $mail->isSMTP();                                          //Send using SMTP
    $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                 //Enable SMTP authentication
    $mail->Username   = 'jeffreyli69420@gmail.com';           //SMTP username
    $mail->Password   = 'puux avdy cqyn lvum';                //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;       //Enable implicit TLS encryption
    $mail->Port       = 587;

    $mail->setFrom('jeffreyli69420@gmail.com', 'WCLS');
    $mail->addAddress($email, $first_name . " " . $last_name);
    $mail->isHTML(true);                                      //Set email format to HTML
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

          // start session to indicate whether user is registered or not
          session_start();
          $_SESSION["registration_status"] = "Registration successful! Please verify your email address";
          header("Location: login.php");
          die();
        } else {
          $_SESSION["registration_status"] = "Registration unsuccessful";
          header("Location: registration.php");
        }
      }
    }
  }

  if (isset($_SESSION['registration_status'])) {
    echo "<h4>".$_SESSION['registration_status']."</h4>";
    unset($_SESSION['registration_status']);
  }

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
        $_SESSION["user"] = $user["id"];
        $_SESSION["account_type"] = $user["account_type"];
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

<?php 
  if (isset($_POST['CreateCourse'])) {
    $program = $_POST['program'];
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $course_price = $_POST['course_price'];
    $course_description = $_POST['course_description'];
    $teacher_id = $_POST['teacher_id'];
    $term = $_POST['term'];
    $year = $_POST['year'];
    $capacity = $_POST['capacity'];
    $room = $_POST['room'];

    require_once 'connection.php';

    $query = "INSERT INTO courses (
        program, 
        course_code, 
        course_name, 
        course_price,
        course_description, 
        default_capacity,
        year,
        term,
        room_number,
        teacher_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_stmt_init($conn);
    $prepStmt = mysqli_stmt_prepare($stmt, $query);
    if ($prepStmt) {
      mysqli_stmt_bind_param(
        $stmt,
        'sssdsiisii',
        $program, 
        $course_code,
        $course_name,
        $course_price,
        $course_description,
        $capacity,
        $year,
        $term,
        $room,
        $teacher_id
      );

      if (mysqli_stmt_execute($stmt)) {
            echo "Course created successfully!";
        } else {
            echo "Execute failed: " . mysqli_stmt_error($stmt);
        }
    } else {
        echo "Prepare failed: " . mysqli_error($conn);
    }
  }
?>