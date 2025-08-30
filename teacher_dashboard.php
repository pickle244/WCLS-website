<?php
// teacher dashbard.php

session_start();
require 'connection.php';

// --- only Teacher can view ---
if (($_SESSION['account_type'] ?? '') !== 'Teacher') {
  header('Location: login.php');
  exit;
}

// --- Current user id  ---
$user_id = (int)($_SESSION['user_id'] ?? ($_SESSION['user'] ?? 0));

// router: view=home|courses|class|print; tab for class=roster|grades
$view    = $_GET['view'] ?? 'home';     
$tab     = $_GET['tab']  ?? 'roster';   // for view=class: roster | grades

// Helper functions
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
// Build internal link to this page with query parameters
function view_url(string $v, array $extra = []): string {
  $base = strtok($_SERVER['REQUEST_URI'], '?');
  $q    = array_merge(['view'=>$v], $extra);
  return h($base . '?' . http_build_query($q));
}

// --- CSRF token (used by Grades save)
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// ---- Resolve teacher_id by user_id  ----
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

// Added final_grade, final_comment, final_updated, final_updated_by in enrollments table in database
// POST: Save grades/comments 
// Flow: CSRF -> course ownership -> enrollment gate -> batch update -> PRG
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
  $course_id   = (int)($_GET['course_id'] ?? 0); // action URL carries ?course_id=...
  $posted_csrf = $_POST['csrf'] ?? '';

  // 1) CSRF & basic guards
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $posted_csrf)) {
    $error = 'Security token mismatch. Please retry.';
  } elseif ($course_id <= 0) {
    $error = 'Missing or invalid course_id.';
  } elseif ($teacher_id === null) {
    $error = 'Your account is not linked to a teacher profile.';
  } else {
    // 2) Course ownership: the course must belong to current teacher
    $own = $conn->prepare("SELECT id FROM courses WHERE id=? AND teacher_id=? LIMIT 1");
    $own->bind_param('ii', $course_id, $teacher_id);
    $own_ok = $own->execute() && $own->get_result()->fetch_row();
    $own->close();
    if (!$own_ok) $error = 'You are not authorized to modify this course.';
  }

  // 3) Normalize input arrays
  $ids    = $_POST['enrollment_id'] ?? [];
  $grades = $_POST['final_grade']   ?? [];
  $notes  = $_POST['final_comment'] ?? [];

  $N = min(count($ids), count($grades), count($notes));
  $rows = []; // enrollment_id => [grade, comment]
  for ($i=0; $i<$N; $i++) {
    $eid = (int)$ids[$i];
    if ($eid <= 0) continue;
    $g = trim((string)$grades[$i]);
    $c = trim((string)$notes[$i]);
    if (mb_strlen($g) > 16)   $g = mb_substr($g, 0, 16);
    if (mb_strlen($c) > 2000) $c = mb_substr($c, 0, 2000);
    $rows[$eid] = [$g, $c]; // de-duplicate by id
  }
  if (empty($error) && empty($rows)) $error = 'Nothing to save.';

  // 4) Enrollment-level gate: only allow enrollments of this course
  if (empty($error)) {
    $allowed = [];
    $q = $conn->prepare("SELECT e.id FROM enrollments e WHERE e.course_id = ?");
    $q->bind_param('i', $course_id);
    if ($q->execute()) {
      $rs = $q->get_result();
      while ($r = $rs->fetch_row()) $allowed[(int)$r[0]] = true;
    }
    $q->close();
    foreach (array_keys($rows) as $eid) {
      if (empty($allowed[$eid])) unset($rows[$eid]);
    }
    if (!$rows) $error = 'No valid enrollments to update.';
  }

  // 5) Batch update
  $ok=0; $tot=count($rows);
  if (empty($error)) {
    $upd = $conn->prepare(
      "UPDATE enrollments
         SET final_grade = ?,
             final_comment = ?,
             final_updated = NOW(),
             final_updated_by = ?
       WHERE id = ?"
    );
    foreach ($rows as $eid => [$g, $c]) {
      $eid_i = (int)$eid;
      $tid_i = (int)$teacher_id; // who updated
      $upd->bind_param('ssii', $g, $c, $tid_i, $eid_i);
      if ($upd->execute()) $ok++;
    }
    $upd->close();

    // Flash + PRG to avoid resubmission on refresh
    $_SESSION['registration_status'] = ($ok === $tot)
      ? "Saved $ok/$tot records."
      : "Partially saved: $ok/$tot records.";
    header('Location: '. view_url('class', ['course_id'=>$course_id, 'tab'=>'grades']));
    exit;
  }

}

// ---- Data holders for views ----
$flash=''; $error='';
$courses=[]; $course=null; $roster=[];

// ---- Preload data by view (READ-ONLY SQL) ----
if ($view === 'courses' && $teacher_id !== null) {
  // List all classes taught by this teacher.
  $sql = "SELECT c.id, c.course_name AS name, c.term, c.year, c.room_number AS room
          FROM courses c
          WHERE c.teacher_id = ?
          ORDER BY c.year DESC, c.term ASC, c.course_name ASC";
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
    $chk = $conn->prepare("SELECT id, course_name AS name, term, year, room_number AS room, teacher_id FROM courses WHERE id = ? LIMIT 1");
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

  <!-- Header / Nav -->
  <header class="teacher-header">
    <h1 class="teacher-title">Teacher Dashboard</h1>
    <nav class="teacher-nav">
      <a class="teacher-link <?= $view==='home'    ?'active':'' ?>" href="<?= view_url('home') ?>">Home</a>
      <a class="teacher-link <?= $view==='courses' ?'active':'' ?>" href="<?= view_url('courses') ?>">My classes</a>
      <a class="teacher-link logout" href="logout.php">Logout</a>
    </nav>
  </header>

  <!-- Global banners -->
  <?php if ($error): ?>
    <div class="alert danger"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['registration_status'])): ?>
    <div class="alert success"><?= h($_SESSION['registration_status']) ?></div>
    <?php unset($_SESSION['registration_status']); ?>
  <?php endif; ?>

  <!-- HOME -->
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

  <!-- COURSES -->
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

  <!-- CLASS (roster/grades) -->
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
              <th>#</th><th>Student</th><th>DOB</th>
              <th>Parent Email</th><th>Emergency Contact</th><th>Phone</th>
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

      <?php else: /* Grades tab */ ?>
        <form method="post" class="card" action="<?= view_url('class',['course_id'=>(int)$course['id'],'tab'=>'grades']) ?>">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="save_grades" value="1">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th>#</th><th>Student</th><th>Final Grade</th><th>Comment</th><th class="muted">Updated</th></tr>
              </thead>
              <tbody>
                <?php $i=1; foreach ($roster as $r): ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= h($r['last_name'] . ', ' . $r['first_name']) ?></td>
                    <td>
                      <input type="hidden" name="enrollment_id[]" value="<?= (int)$r['enrollment_id'] ?>">
                      <input class="grade" type="text" name="final_grade[]" maxlength="16" placeholder="A / 95 / Pass"
                             value="<?= h($r['final_grade'] ?? '') ?>">
                    </td>
                    <td>
                      <textarea class="comment" name="final_comment[]" maxlength="2000" placeholder="Optional end-term comment"><?= h($r['final_comment'] ?? '') ?></textarea>
                    </td>
                    <td class="muted"><?= !empty($r['final_updated']) ? h($r['final_updated']) : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$roster): ?>
                  <tr><td colspan="5" class="muted">No students to grade.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div style="margin-top:.75rem;">
            <button class="btn primary" type="submit">Save All</button>
          </div>
        </form>
      <?php endif; ?>
    </main>

  <!-- PRINT -->
  <?php elseif ($view === 'print' && $course && !$error): ?>
    <main class="content">
      <h2 style="margin:.2rem 0;"><?= h($course['name']) ?> — Roster</h2>
      <div class="muted" style="margin-bottom:.6rem;">Term: <?= h($course['term']) ?> · Year: <?= h($course['year']) ?> · Room: <?= h($course['room']) ?></div>
      <div class="table-wrap card">
        <table class="table">
          <thead><tr>
            <th>#</th><th>Student</th><th>DOB</th>
            <th>Parent Email</th><th>Emergency Contact</th><th>Phone</th>
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

  <!-- Unknown view -->
  <?php else: ?>
    <main class="content">
      <div class="card"><p>Unknown view.</p></div>
    </main>
  <?php endif; ?>

</body>
</html>