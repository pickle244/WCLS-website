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
  
    <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'Admin'): ?>
      <h2>Admin dashboard</h2>
      <a href="logout.php">Logout</a>
      <div class="container" class='create_course'>
        <h1 class="form-title">Create Course</h1>
        <?php require 'script.php';?>
        <form method="post" action="index.php">
        <div class="input-group">
          <i class="fas fa-users"></i>
          <select name="program" id="program" required>
            <option value="">--Select Program--</option>
            <option value="Sunday">Sunday</option>
            <option value="Afterschool">Afterschool</option>
          </select>
        </div>
          <div class="input-group">
              <i class="fas fa-user"></i>
              <input type="text" name="course_code" id="course_code" placeholder="#####" required>
              <label for="course_code">Course Code</label>
          </div>
          <div class="input-group">
              <i class="fas fa-user"></i>
              <input type="text" name="course_name" id="course_name" placeholder="Name" required>
              <label for="course_name">Course Name</label>
          </div>
          <div class="input-group">
              <i class="fas fa-envelope"></i>
              <input type="number" name="course_price" id="course_price" placeholder="$0.00" min='0' step='0.01' required>
              <label for="course_price">Course Price</label>
          </div>
          <div class="input-group">
              <i class="fas fa-lock"></i>
              <input type="text" name="course_description" id="course_description" placeholder="Type..." required>
              <label for="course_description">Course Description</label>
          </div>
          <div class="input-group">
              <i class="fas fa-lock"></i>
              <input type="text" name="teacher_name" id="teacher_name" placeholder="Name" required>
              <label for="teacher_name">Teacher Name</label>
          </div>
          <input type="submit" class="btn" value="Create Course" name="CreateCourse">
        </form>
      </div>
    <?php else:?>
      <h2>Parent dashboard</h2>
      <a href="logout.php">Logout</a>
    <?php endif;?>
</body>
</html>
