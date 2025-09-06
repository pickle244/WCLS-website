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

    // error if password only contains lowercase
    if (ctype_lower($password)) {
      array_push($errors, "Password must contain an upper case character");
    }
    
    // error if password only contains uppercase
    if (ctype_upper($password)) {
      array_push($errors, "Password must contain a lower case character");
    }

    // error if password does not contain a number
    if (preg_match('/[0-9]/', $password) == 0) {
      array_push($errors, "Password must contain a number");
    }

    // error if password does not contain a special character
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
        $prepStmt = mysqli_stmt_prepare($stmt, $sql); // create statement to execute
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

  // display the session variable
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
        $_SESSION['user_id'] = $user['id']; // new canonical
        $_SESSION["account_type"] = $user["account_type"];
        if ($_SESSION['account_type'] == 'Admin') {
          header("Location: index.php");
        } elseif ($_SESSION['account_type'] == 'Parent') {
          header("Location: parent_dashboard.php");
        } elseif($_SESSION['account_type'] == 'Teacher') {
          header("Location: teacher_dashboard.php");
        }
        
        die();
      } else {
        echo "<div class='alert danger'>Password does not match</div>";
      }
    } else {
      echo "<div class='alert danger'>Email does not exist</div>";

    }
  }

  // if create course button is clicked
  if (isset($_POST['CreateCourse'])) {
    // store course info
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

    // verify course does not exist
    $query = "SELECT * FROM courses WHERE course_code = '$course_code'";
    $result = mysqli_query($conn, $query);
    $course = mysqli_fetch_array($result, MYSQLI_ASSOC);
    if (!$course) {
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
        teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
              // error inserting into table
              echo "Execute failed: " . mysqli_stmt_error($stmt);
          }
      } else {
          // error preparing statement
          echo "Prepare failed: " . mysqli_error($conn);
      }
    } else {
      echo "A course with this course code already exists";
    }
  }
  
  if (isset($_POST['CreateTeacher'])) {
    // store teacher info
    $user_id = $_POST['user_id'];
    $image = $_POST['image'];
    $bio = $_POST['bio'];
    $title = $_POST['title'];

    require_once 'connection.php';

    $query = "SELECT * FROM users WHERE id = '$user_id'";
    $user = $conn->query($query)->fetch_assoc();

    if ($user['account_type'] == 'Teacher') {

      // verify teacher does not exist
      $query = "SELECT * FROM teachers WHERE user_id = '$user_id'";
      $result = mysqli_query($conn, $query);
      $teacher = mysqli_fetch_array($result, MYSQLI_ASSOC);
      if (!$teacher) {
        $query = "INSERT INTO teachers (
          user_id, 
          image, 
          bio, 
          title) VALUES (?, ?, ?, ?)";

        $stmt = mysqli_stmt_init($conn);
        $prepStmt = mysqli_stmt_prepare($stmt, $query);
        if ($prepStmt) {
          mysqli_stmt_bind_param(
            $stmt,
            'isss',
            $user_id, 
            $image,
            $bio,
            $title);

          if (mysqli_stmt_execute($stmt)) {
                echo "Teacher created successfully!";
          } else {
              // error inserting into table
              echo "Execute failed: " . mysqli_stmt_error($stmt);
          }
        } else {
            // error preparing statement
            echo "Prepare failed: " . mysqli_error($conn);
        }
      } else {
        echo "A teeacher with this user_id already exists";
      }
    } else {
      echo "User with specified user_id is not a teacher";
    }
  }

  if (isset($_POST['CreateFamily'])) {
    $user_id = $_SESSION['user'];
    $relationship = $_POST['relationship'];
    $mobile_number = $_POST['mobile_number'];
    $home_address = $_POST['home_address'];
    $home_city = $_POST['home_city'];
    $home_state = $_POST['home_state'];
    $home_zip = $_POST['home_zip'];
    $emergency_contact_name = $_POST['emergency_contact_name'];
    $emergency_contact_number = $_POST['emergency_contact_number'];

    require_once 'connection.php';

    $query = "INSERT INTO families (
      user_id,
      relationship,
      mobile_number,
      home_address,
      home_city,
      home_state,
      home_zip,
      emergency_contact_name,
      emergency_contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_stmt_init($conn);
    $prepStmt = mysqli_stmt_prepare($stmt, $query);
    if ($prepStmt) {
      mysqli_stmt_bind_param($stmt,
        'sssssssss',
        $user_id,
        $relationship,
        $mobile_number,
        $home_address,
        $home_city,
        $home_state,
        $home_zip,
        $emergency_contact_name,
        $emergency_contact_number);

      if (mysqli_stmt_execute($stmt)) {
        echo "Family created";
      } else {
        echo "Execute failed: " . mysqli_stmt_error($stmt);
      }
    } else {
      echo "Prepare failed: " . mysqli_error($conn);
    }
  }

  if (isset($_POST['CreateStudent'])) {
    $family_id = $_POST['family_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $DOB = $_POST['DOB'];

    require_once 'connection.php';

    $query = "INSERT INTO students (
      family_id,
      first_name,
      last_name,
      DOB) VALUES (?, ?, ?, ?)";

    $stmt = mysqli_stmt_init($conn);
    $prepStmt = mysqli_stmt_prepare($stmt, $query);
    if ($prepStmt) {
      mysqli_stmt_bind_param($stmt,
        'isss',
        $family_id,
        $first_name,
        $last_name,
        $DOB);

      if (mysqli_stmt_execute($stmt)) {
        echo "Student created";
      } else {
        echo "Execute failed: " . mysqli_stmt_error($stmt);
      }
    } else {
      echo "Prepare failed: " . mysqli_error($conn);
    }
  }

  if (isset($_POST['CopyCourses'])) {
    $curr_year = date("Y");

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
        teacher_id)
      SELECT
        program, 
        course_code, 
        course_name, 
        course_price,
        course_description, 
        default_capacity,
        year + 1,
        term,
        room_number,
        teacher_id
      FROM courses WHERE year = '$curr_year'";
    
    $stmt = mysqli_stmt_init($conn);
    $prepStmt = mysqli_stmt_prepare($stmt, $query);

    if ($prepStmt) {
      
      if (mysqli_stmt_execute($stmt)) {
        echo "Copied successfuly";
      } else {
        echo "Execute failed: " . mysqli_stmt_error($stmt);
      }
    } else {
      echo "Prepare failed: " . mysqli_error($conn);
    }
  }
?>