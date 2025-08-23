<?php
session_start();
// if user is not logged in, redirect them to sign in
if (!isset($_SESSION["user"])) {
  header("Location: login.php");
} else {
  echo $_SESSION['user'];
  echo $_SESSION['account_type'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php require 'script.php';?>
</body>
</html>
