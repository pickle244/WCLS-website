<?php
session_start();
require 'connection.php';

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
  <!-- Top header bar with navigation -->
  <header class="parent-header">
    <h1 class="parent-title">Parent Dashboard</h1><!-- page title-->
    <nav class="parent-nav">
    <!-- add "active" class if current $view matches -->
      <a class="parent-link <?php echo ($view==='home')?'active':''; ?>" href="<?php echo view_url('home'); ?>">Home</a>
      <a class="parent-link <?php echo ($view==='courses')?'active':''; ?>" href="<?php echo view_url('courses'); ?>">Courses</a>
      <a class="parent-link logout" href="logout.php">Logout</a>
    </nav>
  </header>
  <?php if ($view == 'home'): ?>
    <main class="admin-home">
      <a class="home-card" href="<?php echo view_url('courses'); ?>">
        <div class="home-icon">📚</div>
        <div class="home-title">View Courses</div>
        <div class="home-sub">View the course catalog</div>
      </a>
    </main>
  <?php elseif ($view == 'courses'): ?>
    <div class='container' id='course_list'>
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
            "<details>
              <summary>" . $row['course_code'] .  " - " . $row['course_name'] . "</summary>
              <p>" . $row['course_description'] . "</p>
              <p>Price: $" . $row['course_price'] . "</p>
              <p>Teacher: " . $teacher['first_name'] . " " . $teacher['last_name'] . "</p>
            </details>";
          }
        }
      ?>
    </div>
  <?php endif;?>
</body>
</html>