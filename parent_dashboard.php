<?php
session_start();
require 'connection.php';
require 'script.php';

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
      <a class="parent-link <?php echo ($view==='family')?'active':''; ?>" href="<?php echo view_url('family'); ?>">Family</a>
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
      <a class="home-card" href="<?php echo view_url('family'); ?>">
        <div class="home-icon">📚</div>
        <div class="home-title">View Family</div>
        <div class="home-sub">View your family</div>
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
  <?php elseif ($view == 'family'): ?>
    <?php
      $user_id = $_SESSION['user'];
      $query = "SELECT * FROM families WHERE user_id = '$user_id'";
      $families = $conn->query($query)->fetch_assoc();
      if (!$families):
    ?>
      <div class="container" id='create_family'>
        <h1 class="form-title">Create Family</h1>
        <form method="post" action="parent_dashboard.php">
          <div class="input-group">
              <i class="fas fa-user"></i>
              <input type="text" name="relationship" id="relationship" required>
              <label for="relationship">Relationship</label>
          </div>
          <div class="input-group">
              <i class="fas fa-user"></i>
              <input type="text" name="mobile_number" id="mobile_number" required>
              <label for="mobile_number">Mobile Number</label>
          </div>
          <div class="input-group">
              <i class="fas fa-envelope"></i>
              <input type="text" name="home_address" id="home_address" required>
              <label for="home_address">Home Address</label>
          </div>
          <div class="input-group">
              <i class="fas fa-lock"></i>
              <input type="text" name="home_city" id="home_city" required>
              <label for="home_city">Home City</label>
          </div>
          <div class="input-group">
              <i class="fas fa-lock"></i>
              <input type="text" name="home_state" id="home_state" required>
              <label for="home_state">Home State</label>
          </div>
          <div class="input-group">
              <i class="fas fa-lock"></i>
              <input type="text" name="home_zip" id="home_zip" required>
              <label for="home_zip">Zipcode</label>
          </div>
          <div class="input-group">
              <i class="fas fa-lock"></i>
              <input type="text" name="emergency_contact_name" id="emergency_contact_name" required>
              <label for="emergency_contact_name">Emergency Contact Name</label>
          </div>
          <div class="input-group">
              <i class="fas fa-lock"></i>
              <input type="text" name="emergency_contact_number" id="emergency_contact_number" required>
              <label for="emergency_contact_number">Emergency Contact Phone</label>
          </div>
          <input type="submit" class="btn" value="Create Family" name="CreateFamily">
        </form>
      </div>
    <?php else: ?>
      <div class='container' id='students_list'>
        <h1>Students</h1>
        <table>
          <tr>
            <th>Name</th>
            <th>DOB</th>
          </tr>
          <?php
            $family_id = $families['id'];
            $query = "SELECT * FROM students WHERE family_id = '$family_id'";
            $students = $conn->query($query);

            if ($students) {
              while ($row = $students->fetch_assoc()) {
                echo
                "<tr>
                  <td>" . $row['first_name'] . " " . $row['last_name'] . "</td>
                  <td>" . $row['DOB'] . "</td>
                </tr>";
              }
            }
          ?>
        </table>
      </div>
      <div class='container' id='create_student'>
        <h1 class="form-title">Create Student</h1>
        <form method="post" action="parent_dashboard.php">
          <div class="input-group">
              <i class="fas fa-user"></i>
              <input type="number" name="family_id" id="family_id" required>
              <label for="family_id">Family ID</label>
          </div>
          <div class="input-group">
              <i class="fas fa-user"></i>
              <input type="text" name="first_name" id="first_name" required>
              <label for="first_name">First Name</label>
          </div>
          <div class="input-group">
              <i class="fas fa-envelope"></i>
              <input type="text" name="last_name" id="last_name" required>
              <label for="last_name">Last Name</label>
          </div>
          <div class="input-group">
              <i class="fas fa-lock"></i>
              <input type="text" name="DOB" id="DOB" required>
              <label for="DOB">DOB</label>
          </div>
          <input type="submit" class="btn" value="Create Student" name="CreateStudent">
        </form>
      </div>
    <?php endif;?>
  <?php endif;?>
</body>
</html>