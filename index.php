<?php
session_start();
// if user is not logged in, redirect them to sign in page
if (!isset($_SESSION["user"])) {
  header("Location: login.php");
} else {
  // comment out these code because it caused unexpected word "3 parent" after login.
  // echo $_SESSION['user'];
  // echo $_SESSION['account_type'];
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
  <?php require 'script.php'?>
  <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'Admin'): ?>
    <?php require 'connection.php'?>
    <h2>Admin dashboard</h2>
    <h3>Courses</h3>
    <table>
      <tr>
        <th>name</th>
        <th>code</th>
        <th>price</th>
        <th>description</th>
        <th>program</th>
        <th>term</th>
        <th>year</th>
        <th>teacher</th>
        <th>capacity</th>
        <th>room</th>
      </tr>
      <?php
        $query = "SELECT * FROM courses";
        $courses = $conn->query($query);

        if ($courses) {
          while ($row = $courses->fetch_assoc()) {
            $teacher_id = $row['teacher_id'];
            $query = "SELECT
                u.first_name,
                u.last_name
              FROM
                teachers as t
              JOIN
                users AS u ON t.user_id = u.id
              WHERE
                t.id = '$teacher_id'";
            $teacher = $conn->query($query)->fetch_assoc();
            echo
            "<tr>
              <td>" . $row['course_name'] . "</td>
              <td>" . $row['course_code'] . "</td>
              <td>" . $row['course_price'] . "</td>
              <td>" . $row['course_description'] . "</td>
              <td>" . $row['program'] . "</td>
              <td>" . $row['term'] . "</td>
              <td>" . $row['year'] . "</td>
              <td>" . $teacher['first_name'] . $teacher['last_name']. "</td>
              <td>" . $row['default_capacity'] . "</td>
              <td>" . $row['room_number'] . "</td>
            </tr>";
          }
        }
      ?>
    </table>
    <a href="logout.php">Logout</a>
    <div class="container" class='create_course'>
      <h1 class="form-title">Create Course</h1>
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
            <input type="text" name="teacher_id" id="teacher_id" placeholder="#" required>
            <label for="teacher_id">Teacher ID</label>
        </div>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="text" name="term" id="term" placeholder="Term" required>
            <label for="term">Term</label>
        </div>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="number" name="year" id="year" placeholder="YYYY" required>
            <label for="year">Year</label>
        </div>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="number" name="capacity" id="capacity" placeholder="#" required>
            <label for="capacity">Capacity</label>
        </div>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="number" name="room" id="room" placeholder="#" required>
            <label for="room">Room</label>
        </div>
        <input type="submit" class="btn" value="Create Course" name="CreateCourse">
      </form>
    </div>
  <?php elseif (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'Parent'):?>
    <h2>Parent dashboard</h2>
    <a href="logout.php">Logout</a>
  <?php endif;?>
</body>
</html>
