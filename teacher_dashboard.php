<?php

// teacher dashbard.php

session_start();
require 'connection.php';

// only teacher can view this page: if not login or not Teacher -> redirect to login page
if (!isset($_SESSION['account_type']) || ($_SESSION['account_type']?? '')!== 'Teacher'){
    header('Location:login.php');
    exit;
}

// -------mini router-------------------
$view = isset($_GET['view']) ? $_GET['view'] : 'home';
// build clean link to the same page with different view
function view_url($v) {
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
  <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'Teacher'): ?>
    <?php require 'connection.php'?>

    <!-- Top header bar with navigation -->
     <header class="teacher-header">
     <h1 class="teacher-title">Teacher Dashboard</h1><!-- page title-->
     <nav class="teacher-nav">
      <!-- add "active" class if current $view matches -->
        <a class="teacher-link <?php echo ($view==='home')?'active':''; ?>" href="<?php echo view_url('home'); ?>">Home</a>
        <a class="teacher-link <?php echo ($view==='courses')?'active':''; ?>" href="<?php echo view_url('courses'); ?>">My classes</a>
        <a class="teacher-link <?php echo ($view==='students')?'active':''; ?>" href="<?php echo view_url('students'); ?>">Students</a>
        <a class="teacher-link logout" href="logout.php">Logout</a>
      </nav>
    </header>

    <!-- HOME view -->
    <?php if ($view === 'home'): ?>
      <main class="teacher-home">
        <a class="home-card" href="<?php echo view_url('courses'); ?>">
          <div class="home-icon">📚</div>
          <div class="home-title">My classes</div>
          <div class="home-sub">View all classes you teach</div>
        </a>
        <a class="home-card" href="<?php echo view_url('students'); ?>">
          <div class="home-icon">👩</div>
          <div class="home-title">Students</div>
          <div class="home-sub">See rosters and parent contacts</div>
        </a>
      </main>
    
    <!-- teacher COURSES view -->
    <!--According $teacher_id to get the classes list for the teacher-->
      <?php elseif ($view == 'courses'): ?>
        <main class="content">
            <h2>My Classes</h2>

            <?php if ($teacher_id === null): ?>
                <div class="alert danger">Your account is not linked to a teacher profile.</div>
            <?php if (empty($courses)): ?>
                <div class="card"><p>No classes found.</p></div>
            <?php else: ?>
                <div class="table-wrap card">
                    <table class="table">
                        <thead>
                            <tr>
                               <th>Course</th>
                                <th>Term</th>
                                <th>Year</th>
                                <th>Room</th>
                                <th></th> 
            </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                    <tr>
                               <td><?php echo h($c['name'])?>Course</th>
                                <th>Term</th>
                                <th>Year</th>
                                <th>Room</th>
                                <th></th> 
                    </tr>

                    <p>No classes found.</p></div>
            <div class="card">
                <p>Coming next: list classes for teacher_id=<?php echo $teacher_id !==null ? (int)$teacher_id : 0; ?>.</p>
      </div>
      </main>

    <!-- teacher STUDENTS view -->
    <!--According ?course_id=xx to get the roster and parent contact for the teacher-->
      <?php elseif ($view == 'students'): ?>
        <main class="content">
            <h2>Students</h2>
            <div class="card">
                <p>Coming next: show roster for a selected class, with parent contact info.</p>
                <p>Tip: navigate from <em>My Classes</em> to here with <code>?view=students&course_id=123</code>.</p>
      </div>
      </main>




