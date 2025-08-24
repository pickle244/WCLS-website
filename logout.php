<?php
  session_start();
  unset($_SESSION['account_type']);
  session_destroy();
  header("Location: login.php");
?>