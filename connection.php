<?php
$hostName = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "wcls_register";
$conn = mysqli_connect($hostName, $dbUser, $dbPassword, $dbName);
if (!$conn) {
  die("Error connecting to database");
}
?>