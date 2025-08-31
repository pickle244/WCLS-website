<?php
session_start();
require 'connection.php';
// if user is not logged in, redirect them to sign in page
if (!isset($_SESSION["user"])) {
  header("Location: login.php");
} else {
  // comment out these code because it caused unexpected word "3 parent" after login.
  // echo $_SESSION['user'];
  // echo $_SESSION['account_type'];
}

// mini router using query string like ?view=home / ?view=courses ?view=teachers
$view = isset($_GET['view']) ? $_GET['view']: 'home';
function view_url($v) {
  // build clean link to the same page with different view
  $base = strtok($_SERVER['REQUEST_URI'], '?');
  return htmlspecialchars($base. '?view='.$v, ENT_QUOTES, 'UTF-8');
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

    <!-- Top header bar with navigation -->
     <header class="admin-header">
     <h1 class="admin-title">Admin Dashboard</h1><!-- page title-->
     <nav class="admin-nav">
      <!-- add "active" class if current $view matches -->
        <a class="admin-link <?php echo ($view==='home')?'active':''; ?>" href="<?php echo view_url('home'); ?>">Home</a>
        <a class="admin-link <?php echo ($view==='courses')?'active':''; ?>" href="<?php echo view_url('courses'); ?>">Edit Courses</a>
        <a class="admin-link <?php echo ($view==='teachers')?'active':''; ?>" href="<?php echo view_url('teachers'); ?>">Edit Teachers</a>
        <a class="admin-link logout" href="logout.php">Logout</a>
      </nav>
    </header>

  <?php if ($view === 'home'): ?><!-- HOME view (landing page with two buttons courses and teachers) -->
      <main class="admin-home">
        <a class="home-card" href="<?php echo view_url('courses'); ?>">
          <div class="home-icon">📚</div>
          <div class="home-title">Edit Courses</div>
          <div class="home-sub">Add new course or update existing ones</div>
        </a>
        <a class="home-card" href="<?php echo view_url('teachers'); ?>">
          <div class="home-icon">👩‍🏫</div>
          <div class="home-title">Edit Teachers</div>
          <div class="home-sub">Add new teacher or update profiles</div>
        </a>
      </main>

    <?php elseif ($view === 'courses'): ?>
    <!-- <a href="logout.php">Logout</a> -->
    <div class='container' id='course_list'>
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
    </div>
    <div class="container" id='create_course'>
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

  <?php elseif ($view === 'teachers'): ?><!-- TEACHERS view -->
    <div class='container' id='teacher_list'>
      <h1>Teachers</h1>
      <table>
        <tr>
          <th>name</th>
          <th>user id</th>
          <th>title</th>
          <th>bio</th>
          <th>image</th>
        </tr>
        <?php
          $query = "SELECT * FROM teachers";
          $teachers = $conn->query($query);

          if ($teachers) {
            while ($row = $teachers->fetch_assoc()) {
              $id = $row['id'];
              $query = "SELECT
                  u.first_name,
                  u.last_name
                FROM
                  teachers as t
                JOIN
                  users AS u ON t.user_id = u.id
                WHERE
                  t.id = '$id'";
              $user = $conn->query($query)->fetch_assoc();
              echo
              "<tr>
                <td>" . $user['first_name'] . " " . $user['last_name']. "</td>
                <td>" . $row['user_id'] . "</td>
                <td>" . $row['title'] . "</td>
                <td>" . $row['bio'] . "</td>
                <td>" . $row['image'] . "</td>
              </tr>";
            }
          }
        ?>
      </table>
    </div>
    <div class="container" id='create_teacher'>
      <h1 class="form-title">Create Teacher</h1>
      <form method="post" action="index.php">
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="number" name="user_id" id="user_id" placeholder="#" required>
            <label for="user_id">User ID</label>
        </div>
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="image" id="image" placeholder="image.png" required>
            <label for="image">Image</label>
        </div>
        <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="text" name="bio" id="bio" placeholder="Type here..." required>
            <label for="bio">Bio</label>
        </div>
        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="text" name="title" id="title" placeholder="Type..." required>
            <label for="title">Title</label>
        </div>
        <input type="submit" class="btn" value="Create Teacher" name="CreateTeacher">
      </form>
    </div>

    <?php endif; ?><!-- end view switching -->
  <?php else:;?>
  <h1>You do not have access to view this page</h1>
  <a href='logout.php'>Exit</a>
  <?php endif;?>
</body>
</html>
