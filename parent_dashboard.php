<?php
// parent_dashboard.php — Parent portal (Home + Family Info[view/edit in one card] + Register + Payment)

session_start();
require 'connection.php';

// Only parents can view this page
if (!isset($_SESSION['user_id']) || ( $_SESSION['account_type'] ?? '' ) !== 'Parent') {
  header("Location: login.php");
  exit();
}


$user_id   = (int)$_SESSION['user_id'];
$flash     = "";                         // one-time message
$activeTab = $_GET['tab']  ?? 'home';    // home | fill | register | payment
$infoMode  = $_GET['mode'] ?? null;      // Family Info sub-mode: view | edit

// ---------- helpers ----------
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Load family row by user_id; null if not exists */
function load_family(mysqli $conn, int $user_id): ?array {
  $sql = "SELECT * FROM families WHERE user_id=?";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $user_id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  return $row ?: null;
}

/** Get or create a cart (used by Register flow) */
function get_or_create_cart(mysqli $conn, int $user_id): int {
  $sql = "SELECT cart_id FROM carts WHERE user_id=? ORDER BY created_at DESC LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $user_id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  if ($row && isset($row['cart_id'])) return (int)$row['cart_id'];
  $ins = $conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
  $ins->bind_param("i", $user_id);
  $ins->execute();
  return (int)$conn->insert_id;
}

/** Load all students under a family_id */
function load_students(mysqli $conn, int $family_id): array {
  $st = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE family_id=? ORDER BY id DESC");
  $st->bind_param("i", $family_id);
  $st->execute();
  return $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

// ---------- load base data ----------
$family     = load_family($conn, $user_id);
$family_id  = $family['id'] ?? null;
$students   = $family_id ? load_students($conn, $family_id) : [];

// Family Info default sub-mode
if ($activeTab === 'fill') {
  if (!$family_id) $infoMode = 'edit';
  elseif ($infoMode !== 'edit' && $infoMode !== 'view') $infoMode = 'view';
}

// ---------- Family Info: create/update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_family'])) {
  $relationship = trim($_POST['relationship'] ?? '');
  $mobile       = trim($_POST['mobile_number'] ?? '');
  $addr         = trim($_POST['home_address'] ?? '');
  $city         = trim($_POST['home_city'] ?? '');
  $state        = trim($_POST['home_state'] ?? '');
  $zip          = trim($_POST['home_zip'] ?? '');
  $em_name      = trim($_POST['emergency_contact_name'] ?? '');
  $em_phone     = trim($_POST['emergency_contact_number'] ?? '');

  $errors = [];
  if ($relationship === '') $errors[] = "Relationship is required.";
  if ($mobile === '')       $errors[] = "Mobile number is required.";
  if ($addr === '')         $errors[] = "Home address is required.";
  if ($city === '')         $errors[] = "City is required.";
  if ($state === '')        $errors[] = "State is required.";
  if ($zip === '')          $errors[] = "ZIP is required.";
  if ($em_name === '')      $errors[] = "Emergency contact name is required.";
  if ($em_phone === '')     $errors[] = "Emergency contact number is required.";

  if ($errors) {
    $flash = "<div class='alert danger'>".implode("<br>", array_map('h',$errors))."</div>";
    $activeTab = 'fill'; $infoMode = 'edit';
  } else {
    if ($family_id) {
      $sql = "UPDATE families
              SET relationship=?, mobile_number=?, home_address=?, home_city=?, home_state=?, home_zip=?,
                  emergency_contact_name=?, emergency_contact_number=?
              WHERE id=?";
      $st = $conn->prepare($sql);
      $st->bind_param("ssssssssi",
        $relationship, $mobile, $addr, $city, $state, $zip, $em_name, $em_phone, $family_id
      );
      $ok = $st->execute();
    } else {
      $sql = "INSERT INTO families
              (user_id, relationship, mobile_number, home_address, home_city, home_state, home_zip,
               emergency_contact_name, emergency_contact_number)
              VALUES (?,?,?,?,?,?,?,?,?)";
      $st = $conn->prepare($sql);
      $st->bind_param("issssssss",
        $user_id, $relationship, $mobile, $addr, $city, $state, $zip, $em_name, $em_phone
      );
      $ok = $st->execute();
      if ($ok) $family_id = (int)$conn->insert_id;
    }

    if ($ok) {
      $flash    = "<div class='alert success'>Family information saved.</div>";
      $family   = load_family($conn, $user_id); // refresh values
      $activeTab= 'fill';
      $infoMode = 'view';                       // show table after saving
    } else {
      $flash = "<div class='alert danger'>Failed to save family information.</div>";
      $activeTab='fill'; $infoMode='edit';
    }
  }
}

// ---------- Add to Cart (kept) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
  $course_id  = (int)($_POST['course_id'] ?? 0);
  $student_id = (int)($_POST['student_id'] ?? 0);

  if ($course_id <= 0 || $student_id <= 0) {
    $flash = "<div class='alert danger'>Please choose a student and a course.</div>";
    $activeTab = 'register';
  } elseif (!$family_id) {
    $flash = "<div class='alert danger'>Please complete “Family Info” first.</div>";
    $activeTab = 'fill'; $infoMode='edit';
  } else {
    $st = $conn->prepare("SELECT id FROM students WHERE id=? AND family_id=?");
    $st->bind_param("ii", $student_id, $family_id);
    $st->execute();

    if (!$st->get_result()->fetch_assoc()) {
      $flash = "<div class='alert danger'>This student does not belong to your family.</div>";
      $activeTab = 'register';
    } else {
      $cart_id = get_or_create_cart($conn, $user_id);
      $dup = $conn->prepare("SELECT id FROM cart_items WHERE cart_id=? AND student_id=? AND course_id=?");
      $dup->bind_param("iii", $cart_id, $student_id, $course_id);
      $dup->execute();

      if ($dup->get_result()->fetch_assoc()) {
        $flash = "<div class='alert info'>Already in your cart for that student.</div>";
      } else {
        $ins = $conn->prepare("INSERT INTO cart_items (cart_id, student_id, course_id) VALUES (?,?,?)");
        $ins->bind_param("iii", $cart_id, $student_id, $course_id);
        $flash = $ins->execute()
          ? "<div class='alert success'>Added to cart.</div>"
          : "<div class='alert danger'>Failed to add to cart.</div>";
      }
      $activeTab = 'register';
    }
  }
}

// ---------- load course catalog ----------
$courses = [];
$catalogSql = "
  SELECT 
    c.id, c.course_code, c.course_name, c.course_description, c.course_price,
    c.program, c.term, c.year, c.room_number, c.teacher_id,
    u.first_name AS teacher_first, u.last_name AS teacher_last,
    t.title AS teacher_title, t.bio AS teacher_bio, t.image AS teacher_image
  FROM courses c
  LEFT JOIN users u    ON u.id = c.teacher_id
  LEFT JOIN teachers t ON t.user_id = c.teacher_id
  ORDER BY c.year DESC, c.term DESC, c.program ASC, c.course_code ASC
";
$res = $conn->query($catalogSql);
if ($res) $courses = $res->fetch_all(MYSQLI_ASSOC);

// ---------- flags ----------
$hasFamily     = (bool)$family_id;
$studentCount  = count($students);
$courseCount   = count($courses);
$canRegister   = $hasFamily && $studentCount > 0;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Parent Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
</head>
<body>

<div class="admin-header">
  <h1>Parent Dashboard</h1>
  <nav class="admin-nav">
    <a class="admin-link <?= $activeTab==='home' ? 'active' : '' ?>" href="?tab=home">Home</a>
    <a class="admin-link <?= $activeTab==='fill' ? 'active' : '' ?>" href="?tab=fill">Family Info</a>
    <a class="admin-link <?= $activeTab==='register' ? 'active' : '' ?>" href="?tab=register">Register</a>
    <a class="admin-link <?= $activeTab==='payment' ? 'active' : '' ?>" href="?tab=payment">Payment</a>
    <a class="admin-link" href="logout.php">Logout</a>
  </nav>
</div>

<?= $flash; ?>

<?php if ($activeTab === 'home'): ?>
  <!-- ========== HOME (unchanged) ========== -->
  <?php if (!$hasFamily): ?>
    <div class="container"><div class="alert info">
      You have not completed your Family Info yet. Please finish it before registering courses.
    </div></div>
  <?php endif; ?>

  <div class="admin-home">
    <div class="home-card">
      <div class="home-title">Family Info</div>
      <div class="home-sub">
        <?= $hasFamily ? "Completed · You can review or update your information." : "Required before registration."; ?>
      </div>
      <?php if ($hasFamily): ?>
        <p style="margin-top:10px;color:#333;">
          <strong>Relationship:</strong> <?= h($family['relationship'] ?? ''); ?><br>
          <strong>Mobile:</strong> <?= h($family['mobile_number'] ?? ''); ?><br>
          <strong>Address:</strong> <?= h(($family['home_address'] ?? '').', '.($family['home_city'] ?? '').', '.($family['home_state'] ?? '').' '.($family['home_zip'] ?? '')); ?><br>
          <strong>Emergency:</strong> <?= h(($family['emergency_contact_name'] ?? '').' / '.($family['emergency_contact_number'] ?? '')); ?>
        </p>
      <?php endif; ?>
      <a class="btn" href="<?= $hasFamily ? '?tab=fill&mode=view' : '?tab=fill&mode=edit' ?>" style="display:inline-block;margin-top:10px;">
        <?= $hasFamily ? 'View / Edit Family Info' : 'Complete Family Info' ?>
      </a>
    </div>

    <div class="home-card">
      <div class="home-title">Register</div>
      <div class="home-sub">
        <?= $courseCount ?> course<?= $courseCount===1 ? '' : 's' ?> available
        · <?= $canRegister ? 'Ready to register.' : 'Complete Family Info and add student(s) first.'; ?>
      </div>
      <a class="btn <?= $canRegister ? '' : 'disabled' ?>" href="<?= $canRegister ? '?tab=register' : '?tab=fill&mode=edit' ?>" style="display:inline-block;margin-top:10px;">
        <?= $canRegister ? 'Start Registering' : 'Complete Prerequisites' ?>
      </a>
    </div>

    <div class="home-card">
      <div class="home-title">Payment</div>
      <div class="home-sub">Checkout and pay for selected courses.</div>
      <a class="btn disabled" href="?tab=payment" style="display:inline-block;margin-top:10px;">Go to Payment</a>
    </div>
  </div>
<?php endif; ?>

<?php if ($activeTab === 'fill'): ?>
  <!-- ========== FAMILY INFO (single card: title + table OR form) ========== -->
  <div class="container">
    <h2 class="form-title">Family Information</h2>

    <?php if ($infoMode === 'view' && $hasFamily): ?>
  <!-- View mode: two-row horizontal table -->
  <div class="table-scroll">
    <table class="kv-table kv-two-row" aria-label="Saved Family Details">
      <thead>
        <tr>
          <th>Relationship</th>
          <th>Mobile Number</th>
          <th>Home Address</th>
          <th>City</th>
          <th>State</th>
          <th>ZIP</th>
          <th>Emergency Contact Name</th>
          <th>Emergency Contact Number</th>
          <th>Registration Due</th>
          <th>Registration Payment</th>
          <th>Created At</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= h($family['relationship']); ?></td>
          <td><?= h($family['mobile_number']); ?></td>
          <td><?= h($family['home_address']); ?></td>
          <td><?= h($family['home_city']); ?></td>
          <td><?= h($family['home_state']); ?></td>
          <td><?= h($family['home_zip']); ?></td>
          <td><?= h($family['emergency_contact_name']); ?></td>
          <td><?= h($family['emergency_contact_number']); ?></td>
          <td><?= h($family['registration_due'] ?? '—'); ?></td>
          <td><?= isset($family['registration_payment']) ? ('$'.number_format((float)$family['registration_payment'],2)) : '—'; ?></td>
          <td><?= h($family['created_at'] ?? ''); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Right-bottom action -->
  <div class="kv-actions kv-actions-right">
    <a class="btn" href="?tab=fill&mode=edit">Edit</a>
  </div>

    <?php else: ?>
      <!-- Edit: same card, show the form -->
      <form method="post" novalidate class="parent-fill">
        <input type="hidden" name="save_family" value="1">

        <div class="input-group">
          <i class="fas fa-user-group icon"></i>
          <select id="relationship" name="relationship" class="with-icon" required>
            <option value="">-- Select --</option>
            <?php
              $opts = ['Mother','Father','Guardian','Other'];
              $cur  = $family['relationship'] ?? '';
              foreach($opts as $opt){
                $sel = ($cur === $opt) ? 'selected' : '';
                echo "<option $sel value=\"".h($opt)."\">".h($opt)."</option>";
              }
            ?>
          </select>
          <label for="relationship">Relationship</label>
        </div>

        <div class="input-group">
          <i class="fas fa-phone icon"></i>
          <input type="text" id="mobile_number" name="mobile_number" class="with-icon"
                 placeholder="Mobile Number" value="<?= h($family['mobile_number'] ?? '') ?>" required>
          <label for="mobile_number">Mobile Number</label>
        </div>

        <div class="input-group">
          <i class="fas fa-house icon"></i>
          <input type="text" id="home_address" name="home_address" class="with-icon"
                 placeholder="Home Address" value="<?= h($family['home_address'] ?? '') ?>" required>
          <label for="home_address">Home Address</label>
        </div>

        <div class="input-group">
          <i class="fas fa-city icon"></i>
          <input type="text" id="home_city" name="home_city" class="with-icon"
                 placeholder="City" value="<?= h($family['home_city'] ?? '') ?>" required>
          <label for="home_city">City</label>
        </div>

        <div class="input-group">
          <i class="fas fa-map-marker-alt icon"></i>
          <input type="text" id="home_state" name="home_state" class="with-icon"
                 placeholder="State" value="<?= h($family['home_state'] ?? '') ?>" required>
          <label for="home_state">State</label>
        </div>

        <div class="input-group">
          <i class="fas fa-mail-bulk icon"></i>
          <input type="text" id="home_zip" name="home_zip" class="with-icon"
                 placeholder="ZIP" value="<?= h($family['home_zip'] ?? '') ?>" required>
          <label for="home_zip">ZIP</label>
        </div>

        <div class="input-group">
          <i class="fas fa-user-shield icon"></i>
          <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="with-icon"
                 placeholder="Emergency Contact Name" value="<?= h($family['emergency_contact_name'] ?? '') ?>" required>
          <label for="emergency_contact_name">Emergency Contact Name</label>
        </div>

        <div class="input-group">
          <i class="fas fa-phone-square icon"></i>
          <input type="text" id="emergency_contact_number" name="emergency_contact_number" class="with-icon"
                 placeholder="Emergency Contact Number" value="<?= h($family['emergency_contact_number'] ?? '') ?>" required>
          <label for="emergency_contact_number">Emergency Contact Number</label>
        </div>

        <div class="kv-actions">
          <button type="submit" class="btn">Save</button>
          <?php if ($hasFamily): ?>
            <a class="admin-link" href="?tab=fill&mode=view">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($activeTab === 'register'): ?>
  <!-- ========== REGISTER (unchanged except one-child shortcut) ========== -->
  <?php
    if (!$hasFamily): ?>
      <div class="container"><div class="alert info">Please complete “Family Info” first.</div></div>
  <?php elseif ($studentCount === 0): ?>
      <div class="container"><div class="alert info">You have no students yet. Please add your child information in “Family Info”.</div></div>
  <?php endif; ?>

  <div class="admin-home" style="margin-top:16px;">
    <?php foreach ($courses as $c): ?>
      <div class="home-card">
        <div class="home-title"><?= h($c['course_name']); ?></div>
        <div class="home-sub">
          <?= h($c['program']); ?> · <?= h($c['term'])." ".h($c['year']); ?> ·
          $<?= number_format((float)$c['course_price'], 2); ?>
        </div>
        <p style="margin-top:10px;color:#333;"><?= h($c['course_description']); ?></p>
        <p style="margin-top:8px;"><strong>Teacher:</strong>
          <?php $tname = trim(($c['teacher_first'] ?? '').' '.($c['teacher_last'] ?? '')); echo $tname ?: 'TBD'; ?>
        </p>
        <?php if (!empty($c['room_number'])): ?>
          <p style="margin-top:4px;color:#555;">Room: <?= h($c['room_number']); ?></p>
        <?php endif; ?>

        <form method="post" style="margin-top:12px;">
          <input type="hidden" name="course_id" value="<?= (int)$c['id']; ?>">
          <input type="hidden" name="add_to_cart" value="1">

          <?php if ($hasFamily && $studentCount === 1): ?>
            <input type="hidden" name="student_id" value="<?= (int)$students[0]['id']; ?>">
            <button type="submit" class="btn" style="margin-top:10px;">Add to Cart</button>
          <?php else: ?>
            <div class="input-group" style="margin-top:6px;">
              <label for="student_<?= (int)$c['id']; ?>">Choose Student</label>
              <select id="student_<?= (int)$c['id']; ?>" name="student_id" required <?= $canRegister ? '' : 'disabled' ?>>
                <option value="">-- Choose Student --</option>
                <?php foreach ($students as $s): ?>
                  <option value="<?= (int)$s['id']; ?>"><?= h($s['first_name'].' '.$s['last_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn" style="margin-top:10px;" <?= $canRegister ? '' : 'disabled' ?>>
              Add to Cart
            </button>
          <?php endif; ?>
        </form>
      </div>
    <?php endforeach; ?>

    <?php if (empty($courses)): ?>
      <div class="home-card">
        <div class="home-title">No courses available</div>
        <p style="margin-top:6px;color:#555;">Please check back later.</p>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($activeTab === 'payment'): ?>
  <div class="container">
    <div class="alert info">Payment page will be available after cart/checkout is finalized.</div>
  </div>
<?php endif; ?>

</body>
</html>

