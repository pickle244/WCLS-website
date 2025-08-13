<?php
$hostName = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "WCLS";
$conn = mysqli_connect($hostName, $dbUser, $dbPassword, $dbName);
if (!$conn) {
  die("Error connecting to database");
}
?>