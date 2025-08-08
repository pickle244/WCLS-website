<?php
$hostName = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "WCLS_users";
$conn = mysqli_connect($hostname, $dbUser, $dbPassword, $dbName);
if (!$conn) {
  die("Error connecting to database");
}
?>