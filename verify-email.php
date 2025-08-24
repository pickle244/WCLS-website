<?php
  session_start();

  // check whether a token was provided
  if (isset($_GET['token'])) {
    // verify token is valid
    $token = $_GET['token'];
    $verify_query = "SELECT verify_token, verify_status FROM users WHERE verify_token='$token' LIMIT 1";
    require_once "connection.php";
    $verify_query_run = mysqli_query($conn, $verify_query);

    if (mysqli_num_rows($verify_query_run) > 0) {
      $row = mysqli_fetch_array($verify_query_run);
      echo $row['verify_token'];
      // if token is not yet verified
      if ($row['verify_status'] == "0") {
        // find user with token and change their verified status
        $clicked_token = $row['verify_token'];
        $update_query = "UPDATE users SET verify_status='1' WHERE verify_token='$clicked_token' LIMIT 1";
        $update_query_run = mysqli_query($conn, $update_query);
        if ($update_query_run) {
          $_SESSION['registration_status'] = "Verification successful. You can now login";
          header("Location: login.php");
          exit(0);
        } else {
          $_SESSION['registration_status'] = "Verification failed";
          header("Location: login.php");
          exit(0);
        }
      } else {
        $_SESSION['registration_status'] = "Email already verified. You can now login";
        header("Location: login.php");
        exit(0);
      }
    } else {
      $_SESSION['registration_status'] = "Invalid token";
      header("Location: login.php");
    }
  } else {
    $_SESSION['registration_status'] = "Not allowed";
    header("Location: login.php");
  }
?>