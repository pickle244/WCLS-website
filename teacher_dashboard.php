<?php
// teacher dashbard.php

session_start();
require 'connection.php';

// only teacher can view this page: if not login or not Teacher -> redirect to login page
if (!isset($_SESSION['account_type']) || ($_SESSION['account_type']?? '')!== 'Teacher'){
    header('Location:login.php');
    exit;
}

// if not logged in, $user_id = 0. If logged in, it will be a safe integer, prevents unexpected strings or malicious input.
$user_id = (int)($_SESSION['user_id'] ?? 0);

// -------mini router-------------------
// read view from query string: ?view=home 
$view    = $_GET['view'] ?? 'home';     
$tab     = $_GET['tab']  ?? 'roster';   // for view=class: roster | grades

// safe output and clean URLs
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
// safe internal link to this page with query parameters
function view_url(string $v, array $extra = []): string {
  $base = strtok($_SERVER['REQUEST_URI'], '?');
  $q    = array_merge(['view'=>$v], $extra);
  return h($base . '?' . http_build_query($q));
}

// ---- Feature flag: saving grades/comments is OFF for now ----
$enable_grading_write = false;

// ---- CSRF token (even if saving is off, keep the pattern ready) ----
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// ---- Resolve teacher_id by user_id (adjust column names if needed) ----
$teacher_id = null;
if ($user_id > 0) {
  $stm = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
  $stm->bind_param('i', $user_id);
  if ($stm->execute()) {
    if ($row = $stm->get_result()->fetch_assoc()) {
      $teacher_id = (int)$row['id'];
    }
  }
  $stm->close();
}

// ---- Data holders for views ----
$flash=''; $error='';
$courses=[]; $course=null; $roster=[];

// ---- Preload data by view (READ-ONLY SQL) ----
if ($view === 'courses' && $teacher_id !== null) {
  // List all classes taught by this teacher.
  $sql = "SELECT c.id, c.name, c.term, c.year, c.room
          FROM courses c
          WHERE c.teacher_id = ?
          ORDER BY c.year DESC, c.term ASC, c.name ASC";
  $stm = $conn->prepare($sql);
  $stm->bind_param('i', $teacher_id);
  if ($stm->execute()) {
    $rs = $stm->get_result();
    while ($r = $rs->fetch_assoc()) $courses[] = $r;
  }
  $stm->close();
}
elseif (($view === 'class' || $view === 'print')) {
  // Single class context: ownership check + roster
  $course_id = (int)($_GET['course_id'] ?? 0);
  if ($course_id <= 0) {
    $error = 'Missing course_id.';
  } elseif ($teacher_id === null) {
    $error = 'Your account is not linked to a teacher profile.';
  } else {
    // Ownership check
    $chk = $conn->prepare("SELECT id, name, term, year, room, teacher_id FROM courses WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $course_id);
    if ($chk->execute()) $course = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$course) {
      $error = 'Course not found.';
    } elseif ((int)$course['teacher_id'] !== $teacher_id) {
      $error = 'You are not authorized to view this course.';
      $course = null;
    }

    // READ roster if allowed
    if (!$error && $course) {
      // Join through families->users to get parent email + emergency contact fields
      $sql = "SELECT
                s.id AS student_id,
                s.first_name, s.last_name, s.DOB,
                f.id AS family_id,
                u.email AS parent_email,
                f.emergency_contact_name,
                f.emergency_contact_number,
                e.id AS enrollment_id
              FROM enrollments e
              JOIN students   s ON s.id = e.student_id
              LEFT JOIN families f ON f.id = s.family_id
              LEFT JOIN users    u ON u.id = f.user_id
              WHERE e.course_id = ?
              ORDER BY s.last_name ASC, s.first_name ASC";
      $stm = $conn->prepare($sql);
      $stm->bind_param('i', $course_id);
      if ($stm->execute()) {
        $rs = $stm->get_result();
        while ($r = $rs->fetch_assoc()) $roster[] = $r;
      }
      $stm->close();
    }
  }
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
        <a class="teacher-link logout" href="logout.php">Logout</a>
      </nav>
    </header>

    <?php if ($error): ?><div class="alert danger"><?= h($error) ?></div><?php endif; ?>

    <!-- HOME view -->
    <?php if ($view === 'home'): ?>
      <main class="content">
        <div class="home-grid">
          <a class="home-card" href="<?= view_url('courses') ?>">
            <div class="home-icon">📚</div>
            <div class="home-title">My Classes</div>
            <div class="home-sub">View rosters, print contacts, and add grades</div>
          </a>
        </div>
      </main>
    
    <!-- teacher COURSES view -->
    <!--According $teacher_id to get the classes list for the teacher-->
      <?php elseif ($view === 'courses'): ?>
  <main class="content">
    <div class="row-between">
      <h2 style="margin:0;">My Classes</h2>
      <span class="muted">Total: <?= count($courses) ?></span>
    </div>

      <?php if ($teacher_id === null): ?>
      <div class="alert danger">Your account is not linked to a teacher profile.</div>
      <?php elseif (!$courses): ?>
      <div class="card"><p>No classes found.</p></div>
      <?php else: ?>
      <div class="table-wrap card">
        <table class="table">
          <thead>
            <tr><th>Course</th><th>Term</th><th>Year</th><th>Room</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($courses as $c): ?>
              <tr>
                <td><?= h($c['name']) ?></td>
                <td><?= h($c['term']) ?></td>
                <td><?= h($c['year']) ?></td>
                <td><?= h($c['room']) ?></td>
                <td class="actions">
                  <a class="btn small ghost"   href="<?= view_url('class',['course_id'=>(int)$c['id'],'tab'=>'roster']) ?>">Roster</a>
                  <a class="btn small ghost"   href="<?= view_url('class',['course_id'=>(int)$c['id'],'tab'=>'grades']) ?>">Grades</a>
                  <a class="btn small primary" href="<?= view_url('print',['course_id'=>(int)$c['id']]) ?>" target="_blank">Print</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
  </main>

  <?php elseif ($view === 'class' && $course && !$error): ?>
  <main class="content">
    <div class="card">
      <div class="row-between">
        <div>
          <strong><?= h($course['name']) ?></strong>
          <div class="muted">Term: <?= h($course['term']) ?> · Year: <?= h($course['year']) ?> · Room: <?= h($course['room']) ?></div>
        </div>
        <div class="actions">
          <a class="btn ghost" href="<?= view_url('courses') ?>">Back</a>
          <a class="btn <?= $tab==='roster'?'primary':'ghost' ?>" href="<?= view_url('class',['course_id'=>(int)$course['id'],'tab'=>'roster']) ?>">Roster</a>
          <a class="btn <?= $tab==='grades'?'primary':'ghost' ?>" href="<?= view_url('class',['course_id'=>(int)$course['id'],'tab'=>'grades']) ?>">Grades</a>
          <a class="btn primary" href="<?= view_url('print',['course_id'=>(int)$course['id']]) ?>" target="_blank">Print</a>
        </div>
      </div>
    </div>

    <?php elseif ($view === 'class' && $course && !$error): ?>
  <main class="content">
    <div class="card">
      <div class="row-between">
        <div>
          <strong><?= h($course['name']) ?></strong>
          <div class="muted">Term: <?= h($course['term']) ?> · Year: <?= h($course['year']) ?> · Room: <?= h($course['room']) ?></div>
        </div>
        <div class="actions">
          <a class="btn ghost" href="<?= view_url('courses') ?>">Back</a>
          <a class="btn <?= $tab==='roster'?'primary':'ghost' ?>" href="<?= view_url('class',['course_id'=>(int)$course['id'],'tab'=>'roster']) ?>">Roster</a>
          <a class="btn <?= $tab==='grades'?'primary':'ghost' ?>" href="<?= view_url('class',['course_id'=>(int)$course['id'],'tab'=>'grades']) ?>">Grades</a>
          <a class="btn primary" href="<?= view_url('print',['course_id'=>(int)$course['id']]) ?>" target="_blank">Print</a>
        </div>
      </div>
    </div>

    <?php if ($tab === 'roster'): ?>
      <div class="table-wrap card">
        <table class="table">
          <thead><tr>
            <th>#</th><th>Student</th><th>DOB</th><th>Parent Email</th><th>Emergency Contact</th><th>Phone</th>
          </tr></thead>
          <tbody>
            <?php $i=1; foreach ($roster as $r): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= h($r['last_name'] . ', ' . $r['first_name']) ?></td>
                <td><?= h($r['DOB']) ?></td>
                <td><?= h($r['parent_email']) ?></td>
                <td><?= h($r['emergency_contact_name']) ?></td>
                <td><?= h($r['emergency_contact_number']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$roster): ?>
              <tr><td colspan="6" class="muted">No students enrolled.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php else: /* Grades tab — UI only for now */ ?>
      <?php if (!$enable_grading_write): ?>
        <div class="alert danger">Grades & comments editing UI is ready. Saving is temporarily disabled until DB fields are finalized.</div>
      <?php endif; ?>

      <form method="post" class="card" action="<?= view_url('class',['course_id'=>(int)$course['id'],'tab'=>'grades']) ?>">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="save_grades" value="1">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>#</th><th>Student</th><th>Final Grade</th><th>Comment</th><th class="muted">Updated</th></tr></thead>
            <tbody>
              <?php $i=1; foreach ($roster as $r): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= h($r['last_name'] . ', ' . $r['first_name']) ?></td>
                  <td>
                    <input type="hidden" name="enrollment_id[]" value="<?= (int)$r['enrollment_id'] ?>">
                    <input class="grade" type="text" name="final_grade[]" placeholder="A / 95 / Pass" value="">
                  </td>
                  <td><textarea class="comment" name="final_comment[]" placeholder="Optional end-term comment"></textarea></td>
                  <td class="muted">—</td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$roster): ?>
                <tr><td colspan="5" class="muted">No students to grade.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:.75rem;">
          <button class="btn primary" type="submit" <?= $enable_grading_write ? '' : 'disabled' ?>>Save All</button>
          <span class="muted" style="margin-left:.5rem;">Saving will be enabled after DB fields are ready.</span>
        </div>
      </form>
    <?php endif; ?>
  </main>


          </div>
      </form>
    <?php endif; ?>
  </main>

<?php elseif ($view === 'print' && $course && !$error): ?>
  <main class="content">
    <h2 style="margin:.2rem 0;"><?= h($course['name']) ?> — Roster</h2>
    <div class="muted" style="margin-bottom:.6rem;">Term: <?= h($course['term']) ?> · Year: <?= h($course['year']) ?> · Room: <?= h($course['room']) ?></div>
    <div class="table-wrap card">
      <table class="table">
        <thead><tr>
          <th>#</th><th>Student</th><th>DOB</th><th>Parent Email</th><th>Emergency Contact</th><th>Phone</th>
        </tr></thead>
        <tbody>
          <?php $n=1; foreach ($roster as $r): ?>
            <tr>
              <td><?= $n++ ?></td>
              <td><?= h($r['last_name'] . ', ' . $r['first_name']) ?></td>
              <td><?= h($r['DOB']) ?></td>
              <td><?= h($r['parent_email']) ?></td>
              <td><?= h($r['emergency_contact_name']) ?></td>
              <td><?= h($r['emergency_contact_number']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$roster): ?>
            <tr><td colspan="6" class="muted">No students enrolled.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="muted">Tip: Use your browser’s print (Ctrl/Cmd + P).</p>
  </main>

  <?php else: ?>
  <main class="content">
    <div class="card"><p>Unknown view.</p></div>
  </main>
<?php endif; ?>

</body>
</html>







