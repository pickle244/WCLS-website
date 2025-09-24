<?php
// ---------------------------------------------------------
// Session / DB bootstrap
// ---------------------------------------------------------
session_start();
require 'connection.php';

// Block unauthenticated users
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

// View router (coarse views only; sub-modes handled by query params or sub-views)
$view = isset($_GET['view']) ? $_GET['view'] : 'home';

// Build same-page links with a new `view` query param
function view_url($v, array $extra = []){
  $base = strtok($_SERVER['REQUEST_URI'], '?');
  $q = array_merge(['view'=>$v], $extra);
  return htmlspecialchars($base.'?'.http_build_query($q), ENT_QUOTES, 'UTF-8');
}

// CSRF token for all POST actions on this page
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

// Short HTML-escape helper
$e = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');


// ---------------------------------------------------------
// Academic year helpers (backed by `years` table)
// ---------------------------------------------------------

/**
 * Format 2025 -> "2025-2026" for display purposes.
 */
function year_label(int $y): string { return $y.'-'.($y+1); }

/**
 * Return the start year (e.g. 2025 for 2025-2026) of the current academic year.
 * Driven by the `years` table (is_current=1). If the table isn't ready,
 * we fall back to a simple 9/1 cutoff rule to avoid a hard failure.
 */
function current_start_year_db(mysqli $conn): int {
  $res = $conn->query("SELECT start_year FROM years WHERE is_current=1 LIMIT 1");
  if ($res && $row = $res->fetch_assoc()) return (int)$row['start_year'];
  // Fallback: 9/1 cutoff; safe guard if `years` hasn't been seeded.
  $Y=(int)date('Y'); $m=(int)date('n'); return ($m>=9)?$Y:($Y-1);
}

/**
 * Compute the "working year" for course pages:
 * - courses_current: current academic start year
 * - courses_next:    current academic start year + 1
 */
function working_year_for_courses(string $subview, int $current): int {
  return ($subview === 'courses_next') ? ($current + 1) : $current;
}

/**
 * Hard guard to reject any write attempts on a year that does not match the page context.
 * This prevents crafted forms from writing into other years.
 */
function assert_year_matches_context_or_die(mysqli $conn, string $courses_subview, int $submitted_year, int $current_year): void {
  $expected = working_year_for_courses($courses_subview, $current_year);
  if ($submitted_year !== $expected) {
    http_response_code(400);
    $safe = htmlspecialchars(year_label($expected), ENT_QUOTES, 'UTF-8');
    echo '<div class="alert danger">Writes are only allowed in '. $safe .' on this page.</div>';
    exit;
  }
}


// ---------------------------------------------------------
// Teacher helpers (render <option> for teacher drop-downs)
// ---------------------------------------------------------
if (!function_exists('render_teacher_options')) {
  /**
   * Render <option> list for teacher select from [id => "First Last"].
   * $selected_id  preselects an option.
   */
  function render_teacher_options(array $teachers, $selected_id = null): string {
    $h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    if (empty($teachers)) return '<option value="" disabled selected>No teachers found</option>';
    $html = '<option value="">Select teacher</option>';
    foreach ($teachers as $id => $name) {
      $sel = ($selected_id !== null && (int)$selected_id === (int)$id) ? ' selected' : '';
      $html .= '<option value="'.$h($id).'"'.$sel.'>'.$h($name).'</option>';
    }
    return $html;
  }
}


// ---------------------------------------------------------
// CSV helpers shared by Import/Export
// ---------------------------------------------------------

// Header aliases for import mapping
$HEADER_ALIASES = [
  'course_name'        => ['course name','name','课程名','课程名称'],
  'course_code'        => ['course code','code','课程代码','课程编号'],
  'course_price'       => ['price','course price','学费','价格'],
  'course_description' => ['description','desc','课程描述'],
  'program'            => ['program','项目','周日班/课后班'],
  'term'               => ['term','学期'],
  'year'               => ['year','学年','年份'],
  'teacher_id'         => ['teacher id','teacher','教师id','老师id'],
  'default_capacity'   => ['capacity','default capacity','人数上限','容量'],
  'room_number'        => ['room','room number','教室','教室号'],
];

// Map header text to canonical key (case/space tolerant)
function map_header($h,$aliases){
  $k = strtolower(trim((string)$h));
  $k = preg_replace('/\s+/', ' ', $k);
  foreach($aliases as $std=>$list){
    if($k===$std) return $std;
    foreach($list as $alt){ if($k===strtolower($alt)) return $std; }
  }
  return null;
}

// Safe JSON string for hidden form fields
function json_h($data){
  return htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

// Read header and up to N rows for preview
function read_csv_preview($tmp_path,$limit=10){
  $fh=fopen($tmp_path,'r'); if(!$fh) return [null,null,'Cannot open uploaded file.'];
  $first=fgets($fh); if($first===false){ fclose($fh); return [null,null,'Empty CSV.']; }
  $first=preg_replace('/^\xEF\xBB\xBF/','',$first); // strip UTF-8 BOM
  $headers=str_getcsv($first); if(!$headers||count($headers)===0){ fclose($fh); return [null,null,'Empty header row.']; }
  $rows=[]; while(($line=fgets($fh))!==false && count($rows)<$limit) $rows[]=str_getcsv($line);
  fclose($fh); return [$headers,$rows,null];
}


// ---------------------------------------------------------
// Page-scoped state
// ---------------------------------------------------------
$current_year = current_start_year_db($conn);

// Secondary router inside "Edit Courses": subviews are 'courses_current' and 'courses_next'
$courses_subview = in_array($view, ['courses_current','courses_next'], true) ? $view : 'courses_current';
$working_year    = working_year_for_courses($courses_subview, $current_year);

// Server messages & modal flags
$courses_msg_html    = '';   // table-level messages (add/update/copy)
$import_preview_html = '';   // import preview block inside modal
$import_result_html  = '';   // import final result block inside modal
$open_import_modal   = false;


// ---------------------------------------------------------
// Handle downloadable actions early (template / export)
// These are GET actions and do not alter data.
// ---------------------------------------------------------
if (($_GET['action'] ?? '') === 'download_template' && in_array($view, ['courses_current','courses_next'], true)) {
  // Single CSV template for the current courses subview's year
  $filename = 'courses_template_'.$working_year.'.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  $out = fopen('php://output', 'w');
  // UTF-8 BOM for Excel friendliness
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, ['course_name','course_code','course_price','course_description','program','term','year','teacher_id','default_capacity','room_number']);
  fputcsv($out, ['中文一年级','C101','300','一年级综合课','Sunday','Fall',$working_year,'12','25','101']);
  fputcsv($out, ['中文二年级','C201','320','二年级综合课','Sunday','Fall',$working_year,'15','28','102']);
  fclose($out);
  exit;
}

if (($_GET['action'] ?? '') === 'export_year' && in_array($view, ['courses_current','courses_next'], true)) {
  // Export all courses for the working year
  $filename = 'courses_'.$working_year.'.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  $out = fopen('php://output', 'w');
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  $cols = ['id','course_name','course_code','course_price','course_description','program','term','year','teacher_id','default_capacity','room_number','created_at'];
  fputcsv($out, $cols);
  $stmt=$conn->prepare("SELECT ".implode(',', $cols)." FROM courses WHERE year=? ORDER BY program, term, course_code");
  $stmt->bind_param('i', $working_year);
  $stmt->execute();
  $rs=$stmt->get_result();
  while($row=$rs->fetch_assoc()){
    $line=[]; foreach($cols as $c) $line[] = $row[$c] ?? '';
    fputcsv($out, $line);
  }
  fclose($out); exit;
}


// ---------------------------------------------------------
// Import: Step 2 preview (modal posts back here)
// ---------------------------------------------------------
if (($_POST['action'] ?? '') === 'preview_courses'
    && in_array($view, ['courses_current','courses_next'], true)) {
  $open_import_modal = true;
  $target_year = $working_year; // lock to page context

  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $import_preview_html = '<div class="alert danger">Invalid request (CSRF).</div>';
  } elseif (!isset($_FILES['courses_file']) || $_FILES['courses_file']['error'] !== UPLOAD_ERR_OK) {
    $import_preview_html = '<div class="alert danger">Upload failed.</div>';
  } else {
    $name = $_FILES['courses_file']['name'];
    $tmp  = $_FILES['courses_file']['tmp_name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($ext !== 'csv') {
      $import_preview_html = '<div class="alert danger">Preview supports CSV only.</div>';
    } else {
      [$raw_headers,$rows,$err] = read_csv_preview($tmp, 10);
      if ($err) {
        $import_preview_html = '<div class="alert danger">'.$e($err).'</div>';
      } else {
        // Build header map (index -> canonical)
        $map=[];
        foreach($raw_headers as $i=>$h){ $std=map_header($h,$HEADER_ALIASES); if($std) $map[$i]=$std; }

        // Check required columns
        $required = ['course_name','course_code','course_price','program','term','year','teacher_id'];
        $missing=[]; foreach($required as $req){ if(!in_array($req, array_values($map), true)) $missing[]=$req; }
        if ($missing) {
          $import_preview_html = '<div class="alert danger">Missing required columns: '.$e(implode(', ', $missing)).'</div>';
        } else {
          // Normalize rows and force import year to the chosen target
          $preview_items=[];
          foreach($rows as $r){
            $item=[]; foreach($map as $idx=>$std) $item[$std]=$r[$idx]??'';
            $item['year'] = $target_year;
            if (array_filter($item, fn($v)=>$v!=='' && $v!==null)) $preview_items[]=$item;
          }

          // Render preview + import button
          ob_start();
          echo '<div class="notice">Preview of '.$e($name).' (up to 10 rows) · <b>Import into '.$e(year_label($target_year)).'</b></div>';
          echo '<div class="table-wrap"><table class="table"><thead><tr>';
          $std_headers = array_values(array_unique($map));
          if(!in_array('year',$std_headers,true)) $std_headers[]='year';
          foreach($std_headers as $h) echo '<th>'.$e($h).'</th>';
          echo '</tr></thead><tbody>';
          foreach($preview_items as $r){ echo '<tr>'; foreach($std_headers as $h) echo '<td>'.$e($r[$h]??'').'</td>'; echo '</tr>'; }
          echo '</tbody></table></div>';

          echo '<form method="post" action="'.$e($_SERVER['REQUEST_URI']).'">';
          echo '<input type="hidden" name="action" value="import_courses">';
          echo '<input type="hidden" name="csrf" value="'.$e($_SESSION['csrf']).'">';
          echo '<input type="hidden" name="payload" value="'.json_h($preview_items).'">';
          echo '<div class="input-group" style="margin:8px 0;"><input type="submit" class="btn btn--sm" value="Import"></div>';
          echo '</form>';

          $import_preview_html = ob_get_clean();
        }
      }
    }
  }
}


// ---------------------------------------------------------
// Import: Final insertion (modal stays open to show result)
// ---------------------------------------------------------
if (($_POST['action'] ?? '') === 'import_courses'
    && in_array($view, ['courses_current','courses_next'], true)) {
  $open_import_modal = true;
  $target_year = $working_year; // lock to page context

  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $import_result_html = '<div class="alert danger">Invalid request (CSRF).</div>';
  } else {
    $payload = json_decode($_POST['payload'] ?? '[]', true);
    if (!is_array($payload) || empty($payload)) {
      $import_result_html = '<div class="alert danger">No data to import.</div>';
    } else {
      $COLS = ['course_name','course_code','course_price','course_description','program','term','year','teacher_id','default_capacity','room_number'];

      // Existence checks
      $teacher_exists = function(int $tid) use ($conn): bool {
        $stmt = $conn->prepare("SELECT 1 FROM teachers WHERE id=?"); if(!$stmt) return false;
        $stmt->bind_param('i',$tid); $stmt->execute(); $stmt->store_result(); $ok=$stmt->num_rows>0; $stmt->close(); return $ok;
      };
      $dup_exists = function(string $program,string $term,int $year,string $code) use ($conn): bool {
        $stmt = $conn->prepare("SELECT id FROM courses WHERE program=? AND term=? AND year=? AND course_code=?");
        if(!$stmt) return true;
        $stmt->bind_param('ssis',$program,$term,$year,$code); $stmt->execute(); $stmt->store_result();
        $exists=$stmt->num_rows>0; $stmt->close(); return $exists;
      };

      // Validate and normalize rows
      $ok_rows=[]; $errors=[];
      foreach($payload as $i=>$row){
        $idx=$i+2; // CSV line number (1 header + data)
        $clean=[]; foreach($COLS as $c) $clean[$c]=trim((string)($row[$c]??''));
        $clean['year']=(int)$target_year;

        $missing=[]; foreach(['course_name','course_code','course_price','program','term','year','teacher_id'] as $req){ if($clean[$req]===''||$clean[$req]===null) $missing[]=$req; }
        if ($missing){ $errors[$idx]='missing column(s): '.implode(', ',$missing); continue; }

        $clean['course_price']=is_numeric($clean['course_price'])?(float)$clean['course_price']:0.0;
        $clean['default_capacity']=($clean['default_capacity']!=='')?(int)$clean['default_capacity']:null;
        $clean['room_number']=($clean['room_number']!=='')?(int)$clean['room_number']:null;
        $clean['teacher_id']=(int)$clean['teacher_id'];

        if(!$teacher_exists($clean['teacher_id'])){ $errors[$idx]='teacher_id '.$clean['teacher_id'].' does not exist (create under Teachers first)'; continue; }
        if($dup_exists($clean['program'],$clean['term'],$clean['year'],$clean['course_code'])){ $errors[$idx]='already exists by (program, term, year, course_code)'; continue; }

        $ok_rows[]=$clean;
      }

      // Report or insert
      if (empty($ok_rows)) {
        ob_start();
        echo '<div class="alert danger">All rows invalid or duplicates. Nothing imported.</div>';
        if(!empty($errors)){
          echo '<details open style="margin-top:8px;"><summary>See details</summary>';
          echo '<div class="table-wrap"><table class="table"><thead><tr><th>CSV Line</th><th>course_code</th><th>course_name</th><th>Reason</th></tr></thead><tbody>';
          foreach($errors as $line=>$reason){
            $code = $e($payload[$line-2]['course_code'] ?? ''); $name = $e($payload[$line-2]['course_name'] ?? '');
            echo '<tr><td>'.$line.'</td><td>'.$code.'</td><td>'.$name.'</td><td>'.$e($reason).'</td></tr>';
          }
          echo '</tbody></table></div></details>';
        }
        $import_result_html = ob_get_clean();
      } else {
        $conn->begin_transaction();
        try{
          $stmt=$conn->prepare("INSERT INTO courses
            (course_name, course_code, course_price, course_description,
             program, term, year, teacher_id, default_capacity, room_number)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
          if(!$stmt) throw new Exception('DB prepare failed.');
          $inserted=0;
          foreach($ok_rows as $r){
            $desc = ($r['course_description']!=='') ? $r['course_description'] : null;
            $cap  = $r['default_capacity'];
            $room = $r['room_number'];
            $stmt->bind_param('ssdsssiiii',$r['course_name'],$r['course_code'],$r['course_price'],$desc,$r['program'],$r['term'],$r['year'],$r['teacher_id'],$cap,$room);
            if(!$stmt->execute()) throw new Exception('DB execute failed.');
            $inserted += $stmt->affected_rows;
          }
          $conn->commit();
          $import_result_html = '<div class="alert success">Imported '.$inserted.' row(s) into <b>'. $e(year_label($target_year)).'</b>.</div>';
        }catch(Throwable $ex){
          $conn->rollback();
          $import_result_html = '<div class="alert danger">Import failed. Changes rolled back.</div>';
        }
      }
    }
  }
}


// ---------------------------------------------------------
// Copy current year → next year (skip duplicates) then redirect
// Triggered from "Plan Next Year" menu (copy option).
// ---------------------------------------------------------
if (($_POST['action'] ?? '') === 'copy_to_next_year'
    && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  $from = $current_year;
  $to   = $current_year + 1;

  $res=$conn->prepare("SELECT * FROM courses WHERE year=?");
  $res->bind_param('i',$from); $res->execute(); $rs=$res->get_result();

  if($rs && $rs->num_rows>0){
    $rows=$rs->fetch_all(MYSQLI_ASSOC);
    $conn->begin_transaction();
    try{
      $skip=0; $ins=0;
      $dup=$conn->prepare("SELECT id FROM courses WHERE program=? AND term=? AND year=? AND course_code=?");
      $insstmt=$conn->prepare("INSERT INTO courses
        (course_name, course_code, course_price, course_description,
         program, term, year, teacher_id, default_capacity, room_number)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
      foreach($rows as $r){
        $dup->bind_param('ssis',$r['program'],$r['term'],$to,$r['course_code']); $dup->execute(); $dup->store_result();
        if($dup->num_rows>0){ $skip++; continue; }
        $desc = ($r['course_description']!=='') ? $r['course_description'] : null;
        $cap  = ($r['default_capacity']!=='') ? (int)$r['default_capacity'] : null;
        $room = ($r['room_number']!=='')      ? (int)$r['room_number']      : null;
        $insstmt->bind_param('ssdsssiiii',$r['course_name'],$r['course_code'],$r['course_price'],$desc,$r['program'],$r['term'],$to,$r['teacher_id'],$cap,$room);
        if($insstmt->execute()) $ins++; else $skip++;
      }
      $conn->commit();
      // After copy, go to "next year" page to continue editing
      header('Location: '.view_url('courses_next'));
      exit;
    }catch(Throwable $ex){
      $conn->rollback();
      $courses_msg_html='<div class="alert danger">Copy failed. Changes rolled back.</div>';
    }
  } else {
    // No rows to copy → still go to next year page (empty state)
    header('Location: '.view_url('courses_next'));
    exit;
  }
}


// ---------------------------------------------------------
// Inline create / update for courses (same validations as import)
// ---------------------------------------------------------
$teacher_exists = function(int $tid) use ($conn): bool {
  $stmt=$conn->prepare("SELECT 1 FROM teachers WHERE id=?"); if(!$stmt) return false;
  $stmt->bind_param('i',$tid); $stmt->execute(); $stmt->store_result(); $ok=$stmt->num_rows>0; $stmt->close(); return $ok;
};
$dup_exists_edit = function(string $program,string $term,int $year,string $code,?int $exclude_id) use ($conn): bool {
  $sql="SELECT id FROM courses WHERE program=? AND term=? AND year=? AND course_code=?";
  if($exclude_id) $sql.=" AND id<>?";
  $stmt=$conn->prepare($sql); if(!$stmt) return true;
  if($exclude_id) $stmt->bind_param('ssisi',$program,$term,$year,$code,$exclude_id);
  else            $stmt->bind_param('ssis', $program,$term,$year,$code);
  $stmt->execute(); $stmt->store_result(); $exists=$stmt->num_rows>0; $stmt->close(); return $exists;
};

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Create
if (($_POST['action'] ?? '') === 'add_course_inline'
    && in_array($view, ['courses_current','courses_next'], true)
    && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  $program=trim($_POST['program']??''); $code=trim($_POST['course_code']??''); $name=trim($_POST['course_name']??'');
  $price=(float)($_POST['course_price']??0); $desc=trim($_POST['course_description']??''); $term=trim($_POST['term']??'');
  $year=(int)($_POST['year'] ?? $working_year);
  // Guard: submitted year must match page context
  assert_year_matches_context_or_die($conn, $view, $year, $current_year);
  $tid=(int)($_POST['teacher_id']??0);
  $cap=($_POST['capacity']??'')===''?null:(int)$_POST['capacity']; $room=($_POST['room']??'')===''?null:(int)$_POST['room'];

  $errs=[];
  if($program===''||$code===''||$name===''||$term===''||$year<=0) $errs[]='Required fields missing.';
  if(!$teacher_exists($tid)) $errs[]="teacher_id $tid does not exist (create under Teachers first)";
  if(!$errs && $dup_exists_edit($program,$term,$year,$code,null)) $errs[]='Duplicate: (program, term, year, course_code) already exists.';

  if($errs){
    $courses_msg_html='<div class="alert danger">'.$e(implode(' | ',$errs)).'</div>';
  } else {
    $stmt=$conn->prepare("INSERT INTO courses
      (course_name, course_code, course_price, course_description, program, term, year, teacher_id, default_capacity, room_number)
      VALUES (?,?,?,?,?,?,?,?,?,?)");
    if($stmt){
      $desc2 = ($desc!=='') ? $desc : null;
      $stmt->bind_param('ssdsssiiii',$name,$code,$price,$desc2,$program,$term,$year,$tid,$cap,$room);
      if($stmt->execute()) $courses_msg_html='<div class="alert success">Course created in '. $e(year_label($year)).'.</div>';
      else                 $courses_msg_html='<div class="alert danger">Create failed.</div>';
      $stmt->close();
    } else {
      $courses_msg_html='<div class="alert danger">Create failed (prepare).</div>';
    }
  }
}

// Update
if (($_POST['action'] ?? '') === 'update_course'
    && in_array($view, ['courses_current','courses_next'], true)
    && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
  $id=(int)($_POST['id']??0);
  $program=trim($_POST['program']??''); $code=trim($_POST['course_code']??''); $name=trim($_POST['course_name']??'');
  $price=(float)($_POST['course_price']??0); $desc=trim($_POST['course_description']??''); $term=trim($_POST['term']??'');
  $year=(int)($_POST['year'] ?? 0);
  // Guard: submitted year must match page context
  assert_year_matches_context_or_die($conn, $view, $year, $current_year);
  $tid=(int)($_POST['teacher_id']??0);
  $cap=($_POST['capacity']??'')===''?null:(int)$_POST['capacity']; $room=($_POST['room']??'')===''?null:(int)$_POST['room'];

  $errs=[];
  if($id<=0) $errs[]='Invalid id.';
  if($program===''||$code===''||$name===''||$term===''||$year<=0) $errs[]='Required fields missing.';
  if(!$teacher_exists($tid)) $errs[]="teacher_id $tid does not exist (create under Teachers first)";
  if(!$errs && $dup_exists_edit($program,$term,$year,$code,$id)) $errs[]='Duplicate after update: (program, term, year, course_code) exists.';

  if($errs){
    $courses_msg_html='<div class="alert danger">'.$e(implode(' | ',$errs)).'</div>'; $edit_id=$id;
  } else {
    $stmt=$conn->prepare("UPDATE courses SET
      course_name=?, course_code=?, course_price=?, course_description=?, program=?, term=?, year=?, teacher_id=?, default_capacity=?, room_number=? WHERE id=?");
    if($stmt){
      $desc2 = ($desc!=='') ? $desc : null;
      $stmt->bind_param('ssdsssiiiii',$name,$code,$price,$desc2,$program,$term,$year,$tid,$cap,$room,$id);
      if($stmt->execute()) { $courses_msg_html='<div class="alert success">Course updated.</div>'; $edit_id=0; }
      else                 { $courses_msg_html='<div class="alert danger">Update failed.</div>'; $edit_id=$id; }
      $stmt->close();
    } else {
      $courses_msg_html='<div class="alert danger">Update failed (prepare).</div>'; $edit_id=$id;
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
  <script src="script.js?v=<?php echo filemtime('script.js'); ?>" defer></script>
</head>
<body>

  <?php require 'script.php';  ?>

  <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'Admin'): ?>

    <header class="admin-header">
      <h1 class="admin-title">Admin Dashboard</h1>
      <!-- Top-level nav kept minimal by design; course/teacher editing live under "Edit Records" -->
      <nav class="admin-nav">
        <a class="admin-link <?php echo ($view==='home')?'active':'';    ?>" href="<?php echo view_url('home'); ?>">Home</a>
        <a class="admin-link <?php echo ($view==='records' || $view==='courses_current' || $view==='courses_next' || $view==='teachers')?'active':''; ?>" href="<?php echo view_url('records'); ?>">Edit Records</a>
        <a class="admin-link <?php echo ($view==='reports')?'active':''; ?>" href="<?php echo view_url('reports'); ?>">Reports</a>
        <a class="admin-link logout" href="logout.php">Logout</a>
      </nav>
    </header>

    <?php if ($view === 'home'): ?>

      <main class="admin-home">
        <a class="home-card" href="<?php echo view_url('records'); ?>">
          <div class="home-icon">🛠️</div>
          <div class="home-title">Edit Records</div>
          <div class="home-sub">Courses and Teachers</div>
        </a>
        <a class="home-card" href="<?php echo view_url('reports'); ?>">
          <div class="home-icon">📊</div>
          <div class="home-title">Reports</div>
          <div class="home-sub">Exports and summaries</div>
        </a>
      </main>

    <?php elseif ($view === 'records'): ?>

      <!-- Records landing: split to Courses / Teachers (keeps top-level nav clean) -->
      <main class="admin-home">
        <a class="home-card" href="<?php echo view_url('courses_current'); ?>">
          <div class="home-icon">📚</div>
          <div class="home-title">Edit Courses</div>
          <div class="home-sub">Current academic year: <?php echo $e(year_label($current_year)); ?></div>
        </a>
        <a class="home-card" href="<?php echo view_url('teachers'); ?>">
          <div class="home-icon">👩‍🏫</div>
          <div class="home-title">Edit Teachers</div>
          <div class="home-sub">Add/Update teacher profiles</div>
        </a>
      </main>

    <?php elseif ($view === 'teachers'): ?>

      <div class="container" id="teacher_list">
        <h1>Teachers</h1>
        <table class="table">
          <tr><th>name</th><th>user id</th><th>title</th><th>bio</th><th>image</th></tr>
          <?php
            $teachers = $conn->query("SELECT * FROM teachers");
            if ($teachers) while ($row=$teachers->fetch_assoc()){
              $id=$row['id'];
              $user = $conn->query("SELECT u.first_name,u.last_name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.id='$id'")->fetch_assoc();
              echo "<tr><td>".$e($user['first_name'].' '.$user['last_name'])."</td><td>".$e($row['user_id'])."</td><td>".$e($row['title'])."</td><td>".$e($row['bio'])."</td><td>".$e($row['image'])."</td></tr>";
            }
          ?>
        </table>
      </div>

    <?php elseif ($view === 'reports'): ?>

      <main class="admin-home">
        <div class="home-card" style="cursor:default;">
          <div class="home-icon">ℹ️</div>
          <div class="home-title">Reports</div>
          <div class="home-sub">Add report entries here later.</div>
        </div>
      </main>

    <?php elseif ($view === 'courses_current' || $view === 'courses_next'): ?>

      <?php
      // Build teacher list once (id => "First Last") for all dropdowns below
      $teachers_all = [];
      $res = $conn->query("
        SELECT t.id, u.first_name, u.last_name
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        ORDER BY u.first_name, u.last_name
      ");
      if ($res) while ($r = $res->fetch_assoc()) {
        $teachers_all[(int)$r['id']] = trim($r['first_name'].' '.$r['last_name']);
      }
      ?>

      <main class="admin-main">
        <section class="card" id="course_card">
          <h3 class="card-title">Courses</h3>
          <div class="card-sub">
            <?php
              echo ($view==='courses_current')
                ? 'Current academic year'
                : 'Next academic year';
            ?>
            · <b><?php echo $e(year_label($working_year)); ?></b>
          </div>

          <!-- Toolbar: left (year label), right (import/export/template + plan next year menu only on current page) -->
          <div class="toolbar row-between" style="margin:.5rem 0 0.25rem;">
            <div class="year-switch">
              <span class="file-badge">Academic Year: <?php echo $e(year_label($working_year)); ?></span>
            </div>

            <div class="row-between" style="gap:8px;">
              <a class="btn btn--sm" href="<?php echo view_url($view, ['action'=>'download_template']); ?>">Download Template</a>
              <button class="btn btn--sm" id="open-import">Import</button>
              <a class="btn btn--sm" href="<?php echo view_url($view, ['action'=>'export_year']); ?>">Export CSV</a>

              <?php if ($view==='courses_current'): ?>
                <!-- Plan next year: dropdown button with two actions (create/edit next year, copy then go next) -->
                <div class="dropdown" style="display:inline-block; position:relative;">
                  <button class="btn btn--sm" id="btn-plan-next">Plan Next Year ▾</button>
                  <div id="menu-plan-next" style="display:none; position:absolute; right:0; top:36px; background:#fff; border:1px solid #ddd; border-radius:8px; min-width:240px; box-shadow:0 10px 24px rgba(0,0,0,.12); z-index:10;">
                    <a href="<?php echo view_url('courses_next'); ?>" style="display:block; padding:8px 10px; text-decoration:none; color:#111;">Create / edit courses for next year</a>
                    <form method="post" action="<?php echo $e($_SERVER['REQUEST_URI']); ?>" style="margin:0;">
                      <input type="hidden" name="csrf" value="<?php echo $e($_SESSION['csrf']); ?>">
                      <input type="hidden" name="action" value="copy_to_next_year">
                      <button type="submit" style="display:block; width:100%; text-align:left; background:#fff; border:0; padding:8px 10px; cursor:pointer;"
                        onclick="return confirm('Copy all courses from <?php echo $e(year_label($current_year)); ?> to <?php echo $e(year_label($current_year+1)); ?>?');">
                        Copy from this year
                      </button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($courses_msg_html)) echo '<div style="margin-top:8px;">'.$courses_msg_html.'</div>'; ?>

          <!-- Editable table -->
          <div class="table-wrap is-scroll" style="margin-top:10px;">
            <table id="editableTable" class="table">
              <thead>
                <tr>
                  <th>name</th><th>code</th><th>price</th><th>description</th><th>program</th>
                  <th>term</th><th>year</th><th>teacher</th><th>capacity</th><th>room</th><th style="width:90px;">actions</th>
                </tr>
              </thead>
              <tbody>
                <!-- First row: inline create (year is locked to page context; shown as badge + hidden input) -->
                <tr class="row-new">
                  <form method="post" action="<?php echo $e($_SERVER['REQUEST_URI']); ?>">
                    <input type="hidden" name="action" value="add_course_inline">
                    <input type="hidden" name="csrf"   value="<?php echo $e($_SESSION['csrf'] ?? ''); ?>">
                    <td><input class="cell-input" name="course_name"></td>
                    <td><input class="cell-input" name="course_code"></td>
                    <td><input class="cell-input" type="number" name="course_price" step="0.01" min="0"></td>
                    <td><input class="cell-input" name="course_description"></td>
                    <td>
                      <select class="cell-input" name="program">
                        <option value="">Program</option>
                        <option value="Sunday">Sunday</option>
                        <option value="Afterschool">Afterschool</option>
                      </select>
                    </td>
                    <td><input class="cell-input" name="term" placeholder="e.g. Fall"></td>
                    <td>
                      <span class="file-badge"><?php echo $e(year_label($working_year)); ?></span>
                      <input type="hidden" name="year" value="<?php echo $e($working_year); ?>">
                    </td>
                    <td>
                      <select class="cell-input" name="teacher_id">
                        <?php echo render_teacher_options($teachers_all); ?>
                      </select>
                    </td>
                    <td><input class="cell-input" type="number" name="capacity" placeholder="#"></td>
                    <td><input class="cell-input" type="number" name="room"     placeholder="#"></td>
                    <td class="actions"><button class="btn btn--sm" type="submit">Add</button></td>
                  </form>
                </tr>

                <?php
                  // List rows for working year
                  $stmt=$conn->prepare("SELECT * FROM courses WHERE year=? ORDER BY program, term, course_code");
                  $stmt->bind_param('i',$working_year); $stmt->execute(); $courses=$stmt->get_result();

                  if ($courses) while ($row=$courses->fetch_assoc()){
                    $id=(int)$row['id']; $teacher_id=(int)$row['teacher_id'];
                    $teacher_name = $teachers_all[$teacher_id] ?? ('#'.$teacher_id);

                    // Inline edit row
                    if ($edit_id===$id){
                      echo '<tr class="row-edit" data-id="'.$id.'"><form method="post" action="'.$e($_SERVER['REQUEST_URI']).'">';
                      echo '<input type="hidden" name="action" value="update_course"><input type="hidden" name="csrf" value="'.$e($_SESSION['csrf'] ?? '').'"><input type="hidden" name="id" value="'.$id.'">';
                      echo '<td><input class="cell-input" name="course_name" value="'.$e($row['course_name']).'"></td>';
                      echo '<td><input class="cell-input" name="course_code" value="'.$e($row['course_code']).'"></td>';
                      echo '<td><input class="cell-input" type="number" name="course_price" step="0.01" min="0" value="'.$e($row['course_price']).'"></td>';
                      echo '<td><input class="cell-input" name="course_description" value="'.$e($row['course_description']).'"></td>';
                      echo '<td><input class="cell-input" name="program" value="'.$e($row['program']).'"></td>';
                      echo '<td><input class="cell-input" name="term" value="'.$e($row['term']).'"></td>';
                      // Show year as read-only badge; submit actual value via hidden input
                      echo '<td><span class="file-badge">'.$e(year_label((int)$row['year'])).'</span><input type="hidden" name="year" value="'.$e((int)$row['year']).'"></td>';
                      echo '<td><select class="cell-input" name="teacher_id">'.render_teacher_options($teachers_all, $teacher_id).'</select></td>';
                      echo '<td><input class="cell-input" type="number" name="capacity" value="'.$e($row['default_capacity']).'"></td>';
                      echo '<td><input class="cell-input" type="number" name="room" value="'.$e($row['room_number']).'"></td>';
                      $cancel_url = view_url($view);
                      echo '<td class="actions"><button class="btn btn--sm" type="submit" style="margin-right:6px;">Save</button><a class="btn btn--sm btn--light" href="'.$e($cancel_url).'">Cancel</a></td>';
                      echo '</form></tr>';
                      continue;
                    }

                    // Read-only row
                    $edit_url = view_url($view, ['edit'=>$id]);
                    echo '<tr data-id="'.$id.'">';
                    echo '<td>'.$e($row['course_name']).'</td><td>'.$e($row['course_code']).'</td><td>'.$e($row['course_price']).'</td>';
                    echo '<td>'.$e($row['course_description']).'</td><td>'.$e($row['program']).'</td><td>'.$e($row['term']).'</td>';
                    echo '<td>'.$e(year_label((int)$row['year'])).'</td><td>'.$e($teacher_name).'</td><td>'.$e($row['default_capacity']).'</td><td>'.$e($row['room_number']).'</td>';
                    echo '<td class="actions"><a class="btn btn--sm" href="'.$e($edit_url).'">Edit</a></td></tr>';
                  }
                ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>

      <!-- ===================== Import Modal ===================== -->
      <div class="modal <?php echo $open_import_modal ? 'is-open' : ''; ?>" id="importModal" aria-hidden="<?php echo $open_import_modal?'false':'true'; ?>">
        <div class="modal__dialog">
          <div class="modal__header">
            <h4>Import Courses</h4>
            <!-- Right-top cancel -->
            <button class="modal__close btn btn--sm btn--light" id="close-import" aria-label="Cancel import">Cancel</button>
          </div>

          <div class="modal__body">
            <ol class="stepper">
              <li class="active">Upload &amp; preview</li>
            </ol>

            <div class="import-step" id="import-step2">
              <form action="<?php echo $e($_SERVER['REQUEST_URI']); ?>" method="post" enctype="multipart/form-data" id="import-form">
                <input type="hidden" name="action" value="preview_courses">
                <input type="hidden" name="csrf" value="<?php echo $e($_SESSION['csrf'] ?? ''); ?>">
                <input type="file" id="import-file" name="courses_file" accept=".csv" hidden>

                <div class="form-row">
                  <span class="file-badge">Target: <?php echo $e(year_label($working_year)); ?></span>
                  <button type="button" class="btn btn--sm" id="btn-import-choose">Choose CSV</button>
                  <span id="import-chosen-file" class="file-badge">No file chosen</span>
                  <input type="submit" class="btn btn--sm" id="btn-preview" value="Preview" disabled hidden>
                </div>
              </form>
            </div>

            <?php
              if (!empty($import_preview_html)) echo '<div class="import-result">'.$import_preview_html.'</div>';
              if (!empty($import_result_html))  echo '<div class="import-result">'.$import_result_html.'</div>';
            ?>

            <details style="margin-top:14px;">
              <summary>CSV template &amp; header tips</summary>
<pre style="white-space:pre-wrap; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:13px; padding:8px; border:1px solid #eee; border-radius:8px; background:#fafafa;">
course_name,course_code,course_price,course_description,program,term,year,teacher_id,default_capacity,room_number
"中文一年级","C101",300,"一年级综合课","Sunday","Fall",<?php echo $e($working_year); ?>,12,25,101
"中文二年级","C201",320,"二年级综合课","Sunday","Fall",<?php echo $e($working_year); ?>,15,28,102
</pre>
              <div class="muted">Required columns: course_name, course_code, course_price, program, term, year, teacher_id</div>
            </details>

          </div>
        </div>
      </div>

    <?php endif; ?>
  <?php else: ?>
    <h1>You do not have access to view this page</h1><a href='logout.php'>Exit</a>
  <?php endif; ?>



</body>
</html>
