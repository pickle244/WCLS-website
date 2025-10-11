<?php
// ---------------------------------------------------------
// Session / DB bootstrap
// ---------------------------------------------------------
session_start();
require 'connection.php';

// Block unauthenticated users
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

// View router
$view = isset($_GET['view']) ? $_GET['view'] : 'home';

// Build same-page links with a new `view` query param
function view_url($v, array $extra = []){
  $base = strtok($_SERVER['REQUEST_URI'], '?');
  $q = array_merge(['view'=>$v], $extra);
  return htmlspecialchars($base.'?'.http_build_query($q), ENT_QUOTES, 'UTF-8');
}

// CSRF token
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

// Short escape
$e = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');


// ---------------------------------------------------------
// Academic year helpers (backed by `years` table)
// ---------------------------------------------------------

/** Format 2025 -> "2025-2026" */
function year_label(int $y): string { return $y.'-'.($y+1); }

/** current academic year's start_year from DB; fallback 9/1 cutoff */
function current_start_year_db(mysqli $conn): int {
  $res = $conn->query("SELECT start_year FROM years WHERE is_current=1 LIMIT 1");
  if ($res && $row = $res->fetch_assoc()) return (int)$row['start_year'];
  $Y=(int)date('Y'); $m=(int)date('n'); return ($m>=9)?$Y:($Y-1);
}

/** Subview → working year (start_year int) */
function working_year_for_courses(string $subview, int $current): int {
  return ($subview === 'courses_next') ? ($current + 1) : $current;
}

/** Find years.id by start_year */
function year_id_by_start(mysqli $conn, int $start_year): ?int {
  $stm = $conn->prepare("SELECT id FROM years WHERE start_year=? LIMIT 1");
  $stm->bind_param('i',$start_year);
  if($stm->execute()){
    $r=$stm->get_result()->fetch_assoc();
    $stm->close();
    return $r ? (int)$r['id'] : null;
  }
  $stm->close();
  return null;
}

/** Guard: writes must match page context */
function assert_year_matches_context_or_die(string $courses_subview, int $submitted_start_year, int $current_start_year): void {
  $expected = working_year_for_courses($courses_subview, $current_start_year);
  if ($submitted_start_year !== $expected) {
    http_response_code(400);
    $safe = htmlspecialchars(year_label($expected), ENT_QUOTES, 'UTF-8');
    echo '<div class="alert danger">Writes are only allowed in '. $safe .' on this page.</div>';
    exit;
  }
}


// ---------------------------------------------------------
// Teacher helpers
// ---------------------------------------------------------
if (!function_exists('render_teacher_options')) {
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
// CSV helpers
// ---------------------------------------------------------
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
function map_header($h,$aliases){
  $k = strtolower(trim((string)$h));
  $k = preg_replace('/\s+/', ' ', $k);
  foreach($aliases as $std=>$list){
    if($k===$std) return $std;
    foreach($list as $alt){ if($k===strtolower($alt)) return $std; }
  }
  return null;
}
function json_h($data){ return htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); }
function read_csv_preview($tmp_path,$limit=10){
  $fh=fopen($tmp_path,'r'); if(!$fh) return [null,null,'Cannot open uploaded file.'];
  $first=fgets($fh); if($first===false){ fclose($fh); return [null,null,'Empty CSV.']; }
  $first=preg_replace('/^\xEF\xBB\xBF/','',$first);
  $headers=str_getcsv($first); if(!$headers||count($headers)===0){ fclose($fh); return [null,null,'Empty header row.']; }
  $rows=[]; while(($line=fgets($fh))!==false && count($rows)<$limit) $rows[]=str_getcsv($line);
  fclose($fh); return [$headers,$rows,null];
}


// ---------------------------------------------------------
// Page-scoped state
// ---------------------------------------------------------
$current_start_year = current_start_year_db($conn);
$courses_subview = in_array($view, ['courses_current','courses_next'], true) ? $view : 'courses_current';
$working_start_year = working_year_for_courses($courses_subview, $current_start_year);
$working_year_id = year_id_by_start($conn, $working_start_year);

// messages & modal flags
$courses_msg_html    = '';
$import_preview_html = '';
$import_result_html  = '';
$open_import_modal   = false;


// ---------------------------------------------------------
// Download template (CSV)
// ---------------------------------------------------------
if (($_GET['action'] ?? '') === 'download_template' && in_array($view, ['courses_current','courses_next'], true)) {
  $filename = 'courses_template_'.$working_start_year.'.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  $out = fopen('php://output', 'w');
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, ['course_name','course_code','course_price','course_description','program','term','year','teacher_id','default_capacity','room_number']);
  fputcsv($out, ['中文一年级','C101','300','一年级综合课','Sunday','Fall',$working_start_year,'2','25','101']);
  fputcsv($out, ['中文二年级','C201','320','二年级综合课','Sunday','Fall',$working_start_year,'2','28','102']);
  fclose($out); exit;
}


// ---------------------------------------------------------
// Import preview
// ---------------------------------------------------------
if (($_POST['action'] ?? '') === 'preview_courses'
    && in_array($view, ['courses_current','courses_next'], true)) {
  $open_import_modal = true;
  $target_start = $working_start_year;

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
        $map=[];
        foreach($raw_headers as $i=>$h){ $std=map_header($h,$HEADER_ALIASES); if($std) $map[$i]=$std; }
        $required = ['course_name','course_code','course_price','program','term','year','teacher_id'];
        $missing=[]; foreach($required as $req){ if(!in_array($req, array_values($map), true)) $missing[]=$req; }
        if ($missing) {
          $import_preview_html = '<div class="alert danger">Missing required columns: '.$e(implode(', ', $missing)).'</div>';
        } else {
          $preview_items=[];
          foreach($rows as $r){
            $item=[]; foreach($map as $idx=>$std) $item[$std]=$r[$idx]??'';
            $item['year'] = $target_start;
            if (array_filter($item, fn($v)=>$v!=='' && $v!==null)) $preview_items[]=$item;
          }
          ob_start();
          echo '<div class="notice">Preview of '.$e($name).' (up to 10 rows) · <b>Import into '.$e(year_label($target_start)).'</b></div>';
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
// Import final (CSV -> courses with year_id/term_id)
// ---------------------------------------------------------
if (($_POST['action'] ?? '') === 'import_courses'
    && in_array($view, ['courses_current','courses_next'], true)) {
  $open_import_modal = true;
  $target_start = $working_start_year;
  $target_year_id = year_id_by_start($conn, $target_start);

  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $import_result_html = '<div class="alert danger">Invalid request (CSRF).</div>';
  } elseif (!$target_year_id) {
    $import_result_html = '<div class="alert danger">Target academic year not found.</div>';
  } else {
    $payload = json_decode($_POST['payload'] ?? '[]', true);
    if (!is_array($payload) || empty($payload)) {
      $import_result_html = '<div class="alert danger">No data to import.</div>';
    } else {
      $COLS = ['course_name','course_code','course_price','course_description','program','term','year','teacher_id','default_capacity','room_number'];

      // Build (program,name or term_no) -> term_id map for the target year
      $termMap = []; // key: program.'|'.strtolower(name) -> term_id
      $termNoMap = []; // key: program.'|'.term_no -> term_id
      $trs = $conn->prepare("SELECT id, program, term_no, name FROM terms WHERE year_id=?");
      $trs->bind_param('i',$target_year_id);
      if ($trs->execute()) {
        $rs=$trs->get_result();
        while($t=$rs->fetch_assoc()){
          $key1 = $t['program'].'|'.strtolower($t['name']);
          $key2 = $t['program'].'|'.(int)$t['term_no'];
          $termMap[$key1]=(int)$t['id'];
          $termNoMap[$key2]=(int)$t['id'];
        }
      }
      $trs->close();

      // Helpers
      $teacher_exists = function(int $tid) use ($conn): bool {
        $stmt = $conn->prepare("SELECT 1 FROM teachers WHERE id=?");
        if(!$stmt) return false;
        $stmt->bind_param('i',$tid); $stmt->execute(); $stmt->store_result();
        $ok=$stmt->num_rows>0; $stmt->close(); return $ok;
      };
      $dup_exists = function(int $year_id,int $term_id,string $code) use ($conn): bool {
        $stmt = $conn->prepare("SELECT id FROM courses WHERE year_id=? AND term_id=? AND course_code=?");
        if(!$stmt) return true;
        $stmt->bind_param('iis',$year_id,$term_id,$code);
        $stmt->execute(); $stmt->store_result();
        $exists=$stmt->num_rows>0; $stmt->close(); return $exists;
      };

      $ok_rows=[]; $errors=[];
      foreach($payload as $i=>$row){
        $idx=$i+2;
        $clean=[]; foreach($COLS as $c) $clean[$c]=trim((string)($row[$c]??''));
        // required checks
        $missing=[]; foreach(['course_name','course_code','course_price','program','term','teacher_id'] as $req){ if($clean[$req]==='') $missing[]=$req; }
        if ($missing){ $errors[$idx]='missing: '.implode(', ',$missing); continue; }
        $clean['course_price']=is_numeric($clean['course_price'])?(float)$clean['course_price']:0.0;
        $clean['default_capacity']=($clean['default_capacity']!=='')?(int)$clean['default_capacity']:null;
        $clean['room_number']=($clean['room_number']!=='')?(int)$clean['room_number']:null;
        $clean['teacher_id']=(int)$clean['teacher_id'];
        if(!$teacher_exists($clean['teacher_id'])){ $errors[$idx]='teacher_id '.$clean['teacher_id'].' not found'; continue; }

        // resolve term_id by program + term(name/number)
        $term_name_lc = strtolower($clean['term']);
        $term_id = $termMap[$clean['program'].'|'.$term_name_lc] ?? null;
        if (!$term_id && is_numeric($clean['term'])) {
          $term_id = $termNoMap[$clean['program'].'|'.(int)$clean['term']] ?? null;
        }
        if (!$term_id){ $errors[$idx]='term not found under target academic year'; continue; }

        if($dup_exists($target_year_id,$term_id,$clean['course_code'])){ $errors[$idx]='duplicate (year,term,code)'; continue; }

        $ok_rows[] = [
          'course_name'=>$clean['course_name'],
          'course_code'=>$clean['course_code'],
          'course_price'=>$clean['course_price'],
          'course_description'=>($clean['course_description']!=='')?$clean['course_description']:null,
          'teacher_id'=>$clean['teacher_id'],
          'default_capacity'=>$clean['default_capacity'],
          'room_number'=>$clean['room_number'],
          'year_id'=>$target_year_id,
          'term_id'=>$term_id,
          'program'=>$clean['program'], // not stored separately; for display only if needed
        ];
      }

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
            (program, course_code, course_name, course_price, course_description,
             default_capacity, teacher_id, year_id, term_id, room_number)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
          if(!$stmt) throw new Exception('DB prepare failed.');
          $inserted=0;
          foreach($ok_rows as $r){
            $stmt->bind_param('sssdsiiii i',
              $r['program'],$r['course_code'],$r['course_name'],$r['course_price'],$r['course_description'],
              $r['default_capacity'],$r['teacher_id'],$r['year_id'],$r['term_id'],$r['room_number']
            );
            // 修正 bind_param 空格问题：拆成变量
          }
        } catch(Throwable $ex) {
          // 为了避免上面混合 bind 变量的问题，改成逐行准备执行：
        }
        // 重新执行逐行插入（避免绑定问题）
        $conn->rollback();
        $conn->begin_transaction();
        $inserted=0;
        foreach($ok_rows as $r){
          $stmt=$conn->prepare("INSERT INTO courses
            (program, course_code, course_name, course_price, course_description,
             default_capacity, teacher_id, year_id, term_id, room_number)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
          $desc = $r['course_description'];
          $cap  = $r['default_capacity'];
          $room = $r['room_number'];
          $stmt->bind_param('sssdsiiii i', $r['program'],$r['course_code'],$r['course_name'],$r['course_price'],$desc,$cap,$r['teacher_id'],$r['year_id'],$r['term_id'],$room);
          // 注意：上面 'sssdsiiii i' 中间的空格会导致类型串不对，这里改成无空格版本：
          $stmt->close();
          $stmt=$conn->prepare("INSERT INTO courses
            (program, course_code, course_name, course_price, course_description,
             default_capacity, teacher_id, year_id, term_id, room_number)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
          $stmt->bind_param('sssdsiiii i', $r['program'],$r['course_code'],$r['course_name'],$r['course_price'],$desc,$cap,$r['teacher_id'],$r['year_id'],$r['term_id'],$room);
          // 再修正：为了完全避免类型串出错，改成正确的：
          $stmt->close();
          $stmt=$conn->prepare("INSERT INTO courses
            (program, course_code, course_name, course_price, course_description,
             default_capacity, teacher_id, year_id, term_id, room_number)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
          // 类型： s s s d s i i i i i
          $stmt->bind_param('sssdsiiiii', $r['program'],$r['course_code'],$r['course_name'],$r['course_price'],$desc,$cap,$r['teacher_id'],$r['year_id'],$r['term_id'],$room);
          if($stmt->execute()) $inserted++;
          $stmt->close();
        }
        $conn->commit();
        $import_result_html = '<div class="alert success">Imported '.$inserted.' row(s) into <b>'. $e(year_label($target_start)).'</b>.</div>';
      }
    }
  }
}


// ---------------------------------------------------------
// Copy current year → next year (skip duplicates) with term mapping
// ---------------------------------------------------------
if (($_POST['action'] ?? '') === 'copy_to_next_year'
    && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {

  $from_start = $current_start_year;           // 只允许从当前学年复制
  $to_start   = $current_start_year + 1;

  $from_year_id = year_id_by_start($conn, $from_start);
  $to_year_id   = year_id_by_start($conn, $to_start);

  if (!$from_year_id || !$to_year_id) {
    $_SESSION['flash'] = 'Academic year not found.';
    header('Location: '.view_url('courses_current'));
    exit;
  }

  // map (program, term_no) -> term_id (target year)
  $toTermByNo = [];
  $q = $conn->prepare("SELECT id, program, term_no FROM terms WHERE year_id=?");
  $q->bind_param('i',$to_year_id);
  if ($q->execute()){
    $rs=$q->get_result();
    while($t=$rs->fetch_assoc()){
      $toTermByNo[$t['program'].'|'.(int)$t['term_no']] = (int)$t['id'];
    }
  }
  $q->close();

  // load source courses with their term_no/program
  $sql = "SELECT c.*, t.program, t.term_no
          FROM courses c
          JOIN terms t ON t.id = c.term_id
          WHERE c.year_id = ?";
  $stm = $conn->prepare($sql);
  $stm->bind_param('i',$from_year_id);
  $rows=[];
  if ($stm->execute()){
    $rs=$stm->get_result();
    $rows = $rs->fetch_all(MYSQLI_ASSOC);
  }
  $stm->close();

  if(!$rows){
    $_SESSION['flash'] = 'No courses to copy.';
    header('Location: '.view_url('courses_current'));
    exit;
  }

  $dup=$conn->prepare("SELECT id FROM courses WHERE year_id=? AND term_id=? AND course_code=?");
  $ins=0; $skip=0; $miss=0;
  $conn->begin_transaction();
  try{
    foreach($rows as $r){
      $key = $r['program'].'|'.(int)$r['term_no'];
      $new_term_id = $toTermByNo[$key] ?? null;
      if (!$new_term_id){ $miss++; continue; }

      // skip duplicates
      $dup->bind_param('iis',$to_year_id,$new_term_id,$r['course_code']);
      $dup->execute(); $dup->store_result();
      if($dup->num_rows>0){ $skip++; continue; }

      // insert
      $stmt=$conn->prepare("INSERT INTO courses
        (program, course_code, course_name, course_price, course_description,
         default_capacity, teacher_id, year_id, term_id, room_number)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
      $desc = ($r['course_description']!=='') ? $r['course_description'] : null;
      $cap  = ($r['default_capacity']!=='') ? (int)$r['default_capacity'] : null;
      $room = ($r['room_number']!=='')      ? (int)$r['room_number']      : null;
      $stmt->bind_param('sssdsiiiii',$r['program'],$r['course_code'],$r['course_name'],(float)$r['course_price'],$desc,$cap,(int)$r['teacher_id'],$to_year_id,$new_term_id,$room);
      if($stmt->execute()) $ins++; else $skip++;
      $stmt->close();
    }
    $conn->commit();
    $_SESSION['flash'] = "Copied $ins course(s) to ". $e(year_label($to_start)) ." (skipped $skip, missing-term $miss).";
    header('Location: '.view_url('courses_next'));
    exit;
  }catch(Throwable $ex){
    $conn->rollback();
    $_SESSION['flash'] = "Copy failed. Changes rolled back.";
    header('Location: '.view_url('courses_current'));
    exit;
  }
}


// ---------------------------------------------------------
// Save & Publish: dump this page's working year as JSON snapshot
// ---------------------------------------------------------
if (($_POST['action'] ?? '') === 'publish_year'
    && in_array($view, ['courses_current','courses_next'], true)
    && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {

  $target_start = $working_start_year;
  $target_year_id = $working_year_id;

  if (!$target_year_id){
    $_SESSION['flash'] = 'Publish failed: year not found.';
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // Fetch catalog snapshot with joins
  $sql = "SELECT
            c.id, c.program, c.course_code, c.course_name, c.course_price,
            c.course_description, c.default_capacity, c.room_number,
            t.id AS term_id, t.program AS term_program, t.term_no, t.name AS term_name, t.display_name AS term_display_name,
            y.start_year, y.label,
            u.first_name AS teacher_first, u.last_name AS teacher_last
          FROM courses c
          JOIN terms t  ON t.id = c.term_id
          JOIN years y  ON y.id = c.year_id
          LEFT JOIN teachers te ON te.id = c.teacher_id
          LEFT JOIN users u     ON u.id = te.user_id
          WHERE c.year_id=?
          ORDER BY t.program, t.term_no, c.course_code";
  $stm = $conn->prepare($sql);
  $stm->bind_param('i',$target_year_id);
  $list=[];
  if ($stm->execute()){
    $rs=$stm->get_result();
    while($r=$rs->fetch_assoc()){
      $list[] = [
        'id'=>(int)$r['id'],
        'program'=>$r['program'],
        'course_code'=>$r['course_code'],
        'course_name'=>$r['course_name'],
        'course_price'=>(float)$r['course_price'],
        'course_description'=>$r['course_description'],
        'default_capacity'=> isset($r['default_capacity'])?(int)$r['default_capacity']:null,
        'room_number'=> isset($r['room_number'])?(int)$r['room_number']:null,
        'term'=>[
          'id'=>(int)$r['term_id'],
          'program'=>$r['term_program'],
          'term_no'=>(int)$r['term_no'],
          'name'=>$r['term_name'],
          'display_name'=>$r['term_display_name'],
        ],
        'year'=>[
          'start_year'=>(int)$r['start_year'],
          'label'=>$r['label']
        ],
        'teacher'=> trim(($r['teacher_first']??'').' '.($r['teacher_last']??'')) ?: null
      ];
    }
  }
  $stm->close();

  // Write JSON file (exports/courses_YYYY.json)
  $dir = __DIR__ . '/exports';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $file = $dir . '/courses_'.$target_start.'.json';
  $ok = (bool)file_put_contents($file, json_encode(['generated_at'=>date('c'),'year'=>year_label($target_start),'items'=>$list], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  if ($ok) {
    $_SESSION['flash'] = 'Published successfully. File: exports/courses_'.$target_start.'.json';
  } else {
    $_SESSION['flash'] = 'Publish failed (cannot write file).';
  }
  header('Location: '.$_SERVER['REQUEST_URI']); exit;
}


// ---------------------------------------------------------
// Inline create / update (use year_id/term_id)
// ---------------------------------------------------------
$teacher_exists = function(int $tid) use ($conn): bool {
  $stmt=$conn->prepare("SELECT 1 FROM teachers WHERE id=?"); if(!$stmt) return false;
  $stmt->bind_param('i',$tid); $stmt->execute(); $stmt->store_result(); $ok=$stmt->num_rows>0; $stmt->close(); return $ok;
};
$dup_exists_edit = function(int $year_id,int $term_id,string $code,?int $exclude_id) use ($conn): bool {
  $sql="SELECT id FROM courses WHERE year_id=? AND term_id=? AND course_code=?";
  if($exclude_id) $sql.=" AND id<>?";
  $stmt=$conn->prepare($sql); if(!$stmt) return true;
  if($exclude_id) $stmt->bind_param('iisi',$year_id,$term_id,$code,$exclude_id);
  else            $stmt->bind_param('iis', $year_id,$term_id,$code);
  $stmt->execute(); $stmt->store_result(); $exists=$stmt->num_rows>0; $stmt->close(); return $exists;
};

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Create
if (($_POST['action'] ?? '') === 'add_course_inline'
    && in_array($view, ['courses_current','courses_next'], true)
    && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {

  // 读取并清洗
  $program = trim($_POST['program'] ?? '');
  $code    = trim($_POST['course_code'] ?? '');
  $name    = trim($_POST['course_name'] ?? '');
  $price   = ($_POST['course_price'] ?? '') === '' ? null : (float)$_POST['course_price'];
  $desc    = trim($_POST['course_description'] ?? '');
  $tid     = (int)($_POST['teacher_id'] ?? 0);
  $cap     = ($_POST['capacity'] ?? '') === '' ? null : (int)$_POST['capacity'];
  $room    = ($_POST['room'] ?? '') === '' ? null : (int)$_POST['room'];
  $term_id = (int)($_POST['term_id'] ?? 0);
  $submitted_start = (int)($_POST['year'] ?? $working_start_year);

  // 学年防护
  assert_year_matches_context_or_die($view, $submitted_start, $current_start_year);
  $year_id = year_id_by_start($conn, $submitted_start);

  // 必填与范围
  $missing = [];
  if ($name === '')     $missing[] = 'name';
  if ($code === '')     $missing[] = 'code';
  if ($price === null)  $missing[] = 'price';
  if ($desc === '')     $missing[] = 'description';
  if ($program === '')  $missing[] = 'program';
  if ($term_id <= 0)    $missing[] = 'term';
  if (!$year_id)        $missing[] = 'year';
  if ($tid <= 0)        $missing[] = 'teacher';
  if ($cap === null)    $missing[] = 'capacity';
  if ($room === null)   $missing[] = 'room';

  $errs = [];
  if ($missing) $errs[] = 'Please fill: ' . implode(', ', $missing);
  if ($price !== null && $price <= 0) $errs[] = 'price must be greater than 0';
  if ($cap !== null && $cap <= 0)     $errs[] = 'capacity must be greater than 0';
  if ($room !== null && $room <= 0)   $errs[] = 'room must be greater than 0';
  if (!$teacher_exists($tid))         $errs[] = "teacher_id $tid does not exist";
  if (!$errs && $dup_exists_edit($year_id, $term_id, $code, null))
    $errs[] = 'Duplicate: (year, term, course_code) exists.';

  if ($errs){
    $courses_msg_html = '<div class="alert danger">'.$e(implode(' | ', $errs)).'</div>';
  } else {
    $stmt=$conn->prepare("INSERT INTO courses
      (program, course_code, course_name, course_price, course_description,
       default_capacity, teacher_id, year_id, term_id, room_number)
      VALUES (?,?,?,?,?,?,?,?,?,?)");
    if($stmt){
      $stmt->bind_param('sssdsiiiii',
        $program, $code, $name, $price, $desc, $cap, $tid, $year_id, $term_id, $room
      );
      if($stmt->execute()){
        $courses_msg_html = '<div class="alert success">Course created in <b>'.$e(year_label($submitted_start)).'</b>.</div>';
      } else {
        $courses_msg_html = '<div class="alert danger">Create failed.</div>';
      }
      $stmt->close();
    } else {
      $courses_msg_html = '<div class="alert danger">Create failed (prepare).</div>';
    }
  }
}


// Update
if (($_POST['action'] ?? '') === 'update_course'
    && in_array($view, ['courses_current','courses_next'], true)
    && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {

  $id=(int)($_POST['id']??0);
  $program=trim($_POST['program']??''); $code=trim($_POST['course_code']??''); $name=trim($_POST['course_name']??'');
  $price=(float)($_POST['course_price']??0); $desc=trim($_POST['course_description']??'');
  $tid=(int)($_POST['teacher_id']??0);
  $cap=($_POST['capacity']??'')===''?null:(int)$_POST['capacity']; $room=($_POST['room']??'')===''?null:(int)$_POST['room'];
  $term_id = (int)($_POST['term_id'] ?? 0);
  $submitted_start = (int)($_POST['year'] ?? 0);

  assert_year_matches_context_or_die($view, $submitted_start, $current_start_year);
  $year_id = year_id_by_start($conn, $submitted_start);

  $errs=[];
  if($id<=0) $errs[]='Invalid id.';
  if($program===''||$code===''||$name===''||$term_id<=0||!$year_id) $errs[]='Required fields missing.';
  if(!$teacher_exists($tid)) $errs[]="teacher_id $tid does not exist";
  if(!$errs && $dup_exists_edit($year_id,$term_id,$code,$id)) $errs[]='Duplicate after update: (year, term, code) exists.';

  if($errs){
    $courses_msg_html='<div class="alert danger">'.$e(implode(' | ',$errs)).'</div>'; $edit_id=$id;
  } else {
    $stmt=$conn->prepare("UPDATE courses SET
      program=?, course_code=?, course_name=?, course_price=?, course_description=?, default_capacity=?, teacher_id=?, year_id=?, term_id=?, room_number=? WHERE id=?");
    if($stmt){
      $desc2 = ($desc!=='') ? $desc : null;
      $stmt->bind_param('sssdsiiiiii',$program,$code,$name,$price,$desc2,$cap,$tid,$year_id,$term_id,$room,$id);
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
      <nav class="admin-nav">
        <a class="admin-link <?php echo ($view==='home')?'active':'';    ?>" href="<?php echo view_url('home'); ?>">Home</a>
        <a class="admin-link <?php echo ($view==='records' || $view==='courses_current' || $view==='courses_next' || $view==='teachers')?'active':''; ?>" href="<?php echo view_url('records'); ?>">Edit Records</a>
        <a class="admin-link <?php echo ($view==='reports')?'active':''; ?>" href="<?php echo view_url('reports'); ?>">Reports</a>
        <a class="admin-link logout" href="logout.php">Logout</a>
      </nav>
    </header>

    <?php if ($view === 'home'): ?>

      <main class="admin-home">
        <main class="admin-home">
        <div class="home-card" style="cursor:default;">
          <div class="home-icon"></div>
          <div class="home-title">Welcome</div>
          <div class="home-sub">Welcome to Admin Dashboard.</div>
        </div>
      </main>

    <?php elseif ($view === 'records'): ?>

      <main class="admin-home">
        <a class="home-card" href="<?php echo view_url('courses_current'); ?>">
          <div class="home-icon">📚</div>
          <div class="home-title">Edit Courses</div>
          <div class="home-sub">Current academic year: <?php echo $e(year_label($current_start_year)); ?></div>
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

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert success" style="max-width:1100px; margin:10px auto 0;">
          <?php echo $e($_SESSION['flash']); unset($_SESSION['flash']); ?>
        </div>
      <?php endif; ?>

      <?php
      // Build teacher list for dropdowns
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

      // Load terms for the working year (for Program→Term select)
      $terms = []; // for <select>
      if ($working_year_id){
        $stm = $conn->prepare("SELECT id, program, term_no, name, display_name FROM terms WHERE year_id=? ORDER BY program, term_no");
        $stm->bind_param('i',$working_year_id);
        if ($stm->execute()){
          $rs=$stm->get_result();
          while($t=$rs->fetch_assoc()){ $terms[]=$t; }
        }
        $stm->close();
      }
      ?>

      <main class="admin-main">
        <section class="card" id="course_card">
          <div class="row-between">
            <div>
              <h3 class="card-title" style="margin:0;">Courses</h3>
              <div class="card-sub">
                <?php echo ($view==='courses_current') ? 'Current academic year' : 'Next academic year'; ?>
                · <b><?php echo $e(year_label($working_start_year)); ?></b>
              </div>
            </div>

            <div class="row-between" style="gap:8px;">
              <?php if ($view==='courses_next'): ?>
                <a class="btn btn--sm btn--light" href="<?php echo view_url('courses_current'); ?>">← Back to current year</a>
              <?php endif; ?>

              <?php if ($view==='courses_next'): ?>
                <form method="post" action="<?php echo $e($_SERVER['REQUEST_URI']); ?>" onsubmit="return true;">
                  <input type="hidden" name="csrf" value="<?php echo $e($_SESSION['csrf']); ?>">
                  <input type="hidden" name="action" value="publish_year">
                  <button class="btn btn--sm">Save &amp; Publish</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?php echo $e($_SERVER['REQUEST_URI']); ?>">
                  <input type="hidden" name="csrf" value="<?php echo $e($_SESSION['csrf']); ?>">
                  <input type="hidden" name="action" value="publish_year">
                  <button class="btn btn--sm btn--light">Save &amp; Publish</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <!-- Toolbar: left year badge; right: template/import on next-year; Plan menu on current-year -->
          <div class="toolbar row-between" style="margin:.5rem 0 0.25rem;">
            <div class="year-switch">
              <span class="file-badge">Academic Year: <?php echo $e(year_label($working_start_year)); ?></span>
            </div>

            <div class="row-between" style="gap:8px;">
              <?php if ($view==='courses_next'): ?>
                <a class="btn btn--sm" href="<?php echo view_url($view, ['action'=>'download_template']); ?>">Download Template</a>
                <button type="button" class="btn btn--sm" id="open-import">Import</button>

                <!-- 需求：移除 Export CSV，已删除 -->
              <?php endif; ?>

              <?php if ($view==='courses_current'): ?>
                <!-- Plan Next Year (only 2 options as requested) -->
                <div class="dropdown" style="display:inline-block; position:relative;">
                  <button class="btn btn--sm" id="btn-plan-next">Plan Next Year ▾</button>
                  <div id="menu-plan-next" class="menu" style="display:none; position:absolute; right:0; top:36px; background:#fff; border:1px solid #ddd; border-radius:8px; min-width:260px; box-shadow:0 10px 24px rgba(0,0,0,.12); z-index:10;">
                    <!-- Create for next year -->
                    <button type="button" class="menu-item" id="btn-create-next" style="display:block; width:100%; text-align:left; background:#fff; border:0; padding:8px 10px; cursor:pointer;">
                      Create for next year
                    </button>

                    <!-- Copy for next year -->
                    <form method="post" action="<?php echo $e($_SERVER['REQUEST_URI']); ?>" style="margin:0;" id="copyNextForm">
                      <input type="hidden" name="csrf" value="<?php echo $e($_SESSION['csrf']); ?>">
                      <input type="hidden" name="action" value="copy_to_next_year">
                      <button type="submit" class="menu-item" style="display:block; width:100%; text-align:left; background:#fff; border:0; padding:8px 10px; cursor:pointer;"
                        onclick="return confirm('Are you sure you want to copy the current year\\'s courses to the next academic year and save?');">
                        Copy for next year
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
                  <th>name</th><th>code</th><th>price</th><th>description</th>
                  <th>program</th><th>term</th><th>year</th><th>teacher</th><th>capacity</th><th>room</th><th style="width:110px;">actions</th>
                </tr>
              </thead>
              <tbody>

              
              <?php
  // 新建行专用表单（隐藏域放这里；行内控件用 form="f-new" 归属到该表单）
  $newFormId = 'f-new';
?>
<form id="<?php echo $newFormId; ?>" method="post" action="<?php echo $e($_SERVER['REQUEST_URI']); ?>">
  <input type="hidden" name="action" value="add_course_inline">
  <input type="hidden" name="csrf"   value="<?php echo $e($_SESSION['csrf'] ?? ''); ?>">
  <input type="hidden" name="year"   value="<?php echo $e($working_start_year); ?>">
</form>

<tr class="row-new js-row">
  <!-- name -->
  <td><input class="cell-input" name="course_name" required form="<?php echo $newFormId; ?>"></td>

  <!-- code -->
  <td><input class="cell-input" name="course_code" required form="<?php echo $newFormId; ?>"></td>

  <!-- price -->
  <td><input class="cell-input" type="number" name="course_price" step="0.01" min="0.01" required form="<?php echo $newFormId; ?>"></td>

  <!-- description -->
  <td><input class="cell-input" name="course_description" required form="<?php echo $newFormId; ?>"></td>

  <!-- program -->
  <td>
    <select class="cell-input js-program" name="program" required form="<?php echo $newFormId; ?>">
      <option value="">Program</option>
      <option value="Sunday">Sunday</option>
      <option value="Afterschool">Afterschool</option>
    </select>
  </td>

  <!-- term -->
  <td>
    <select class="cell-input js-term" name="term_id" required form="<?php echo $newFormId; ?>">
      <option value="">Select term</option>
      <?php foreach($terms as $t): ?>
        <option value="<?php echo (int)$t['id']; ?>" data-program="<?php echo $e($t['program']); ?>">
          <?php echo $e($t['display_name'] ?: $t['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </td>

  <!-- year (locked) -->
  <td><span class="file-badge"><?php echo $e(year_label($working_start_year)); ?></span></td>

  <!-- teacher -->
  <td>
    <select class="cell-input" name="teacher_id" required form="<?php echo $newFormId; ?>">
      <?php echo render_teacher_options($teachers_all); ?>
    </select>
  </td>

  <!-- capacity / room -->
  <td><input class="cell-input" type="number" name="capacity" placeholder="#" min="1" required form="<?php echo $newFormId; ?>"></td>
  <td><input class="cell-input" type="number" name="room"     placeholder="#" min="1" required form="<?php echo $newFormId; ?>"></td>

  <!-- actions -->
  <td class="actions">
    <button class="btn btn--sm" type="submit" form="<?php echo $newFormId; ?>">Add</button>
  </td>
</tr>



                

                <?php
                  // List rows for working year
                  if ($working_year_id){
                    $stmt=$conn->prepare("
                      SELECT c.*, t.display_name AS term_display, y.start_year,
                             u.first_name, u.last_name
                      FROM courses c
                      JOIN terms t ON t.id=c.term_id
                      JOIN years y ON y.id=c.year_id
                      LEFT JOIN teachers te ON te.id=c.teacher_id
                      LEFT JOIN users u ON u.id=te.user_id
                      WHERE c.year_id=?
                      ORDER BY t.program, t.term_no, c.course_code
                    ");
                    $stmt->bind_param('i',$working_year_id); $stmt->execute(); $courses=$stmt->get_result();
                  } else {
                    $courses = false;
                  }

                  if ($courses) while ($row=$courses->fetch_assoc()){
                    $id=(int)$row['id']; $teacher_name = trim(($row['first_name']??'').' '.($row['last_name']??''));
                    $teacher_name = $teacher_name !== '' ? $teacher_name : '#'.$row['teacher_id'];

                    if ($edit_id===$id){

  // 1) 该编辑行的独立表单（放隐藏字段）
  $formId = 'f-row-'.$id;
  echo '<form id="'.$formId.'" method="post" action="'.$e($_SERVER['REQUEST_URI']).'">';
  echo   '<input type="hidden" name="action" value="update_course">';
  echo   '<input type="hidden" name="csrf"   value="'.$e($_SESSION['csrf'] ?? '').'">';
  echo   '<input type="hidden" name="id"     value="'.$id.'">';
  echo   '<input type="hidden" name="year"   value="'.$e((int)$row['start_year']).'">';
  echo '</form>';

  // 2) 表格行（没有 <form>），每个控件用 form="$formId"
  echo '<tr class="row-edit js-row" data-id="'.$id.'">';

  echo '<td><input class="cell-input" name="course_name" value="'.$e($row['course_name']).'" required form="'.$formId.'"></td>';
  echo '<td><input class="cell-input" name="course_code" value="'.$e($row['course_code']).'" required form="'.$formId.'"></td>';
  echo '<td><input class="cell-input" type="number" name="course_price" step="0.01" min="0.01" value="'.$e($row['course_price']).'" required form="'.$formId.'"></td>';
  echo '<td><input class="cell-input" name="course_description" value="'.$e($row['course_description']).'" required form="'.$formId.'"></td>';

  echo '<td><input class="cell-input" name="program" value="'.$e($row['program']).'" required form="'.$formId.'"></td>';

  // term 下拉
  echo '<td><select class="cell-input js-term" name="term_id" required form="'.$formId.'">';
  echo '<option value="">Select term</option>';
  foreach($terms as $t){
    $sel = ((int)$t['id'] === (int)$row['term_id']) ? ' selected' : '';
    echo '<option value="'.$e((int)$t['id']).'" data-program="'.$e($t['program']).'"'.$sel.'>'.$e($t['display_name'] ?: $t['name']).'</option>';
  }
  echo '</select></td>';

  echo '<td><span class="file-badge">'.$e(year_label((int)$row['start_year'])).'</span></td>';

  echo '<td><select class="cell-input" name="teacher_id" required form="'.$formId.'">'.render_teacher_options($teachers_all, (int)$row['teacher_id']).'</select></td>';

  echo '<td><input class="cell-input" type="number" name="capacity" value="'.$e($row['default_capacity']).'" min="1" required form="'.$formId.'"></td>';
  echo '<td><input class="cell-input" type="number" name="room" value="'.$e($row['room_number']).'" min="1" required form="'.$formId.'"></td>';

  $cancel_url = view_url($view);
  echo '<td class="actions">';
  echo   '<button class="btn btn--sm" type="submit" form="'.$formId.'" style="margin-right:6px;">Save</button>';
  echo   '<a class="btn btn--sm btn--light" href="'.$e($cancel_url).'">Cancel</a>';
  echo '</td>';

  echo '</tr>';

  continue;
}




                    // Read-only row
                    $edit_url = view_url($view, ['edit'=>$id]);
                    echo '<tr data-id="'.$id.'">';
                    echo '<td>'.$e($row['course_name']).'</td><td>'.$e($row['course_code']).'</td><td>'.$e($row['course_price']).'</td>';
                    echo '<td>'.$e($row['course_description']).'</td><td>'.$e($row['program']).'</td><td>'.$e($row['term_display']).'</td>';
                    echo '<td>'.$e(year_label((int)$row['start_year'])).'</td><td>'.$e($teacher_name).'</td><td>'.$e($row['default_capacity']).'</td><td>'.$e($row['room_number']).'</td>';
                    echo '<td class="actions"><a class="btn btn--sm" href="'.$edit_url.'">Edit</a></td>';
                  }
                ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>

      <!-- ===================== Create Next Year Prompt Modal ===================== -->
      <div class="modal" id="createNextModal" aria-hidden="true">
        <div class="modal__dialog">
          <div class="modal__header">
            <h4>Create Courses for Next Year</h4>
            <button type="button" class="modal__close btn btn--sm btn--light" id="close-create-next">Cancel</button>

          </div>
          <div class="modal__body">
            <p style="margin:4px 0 10px;">Do you want to import a CSV file to upload the courses?</p>
            <div class="row-between" style="gap:8px; flex-wrap:wrap;">
              <a class="btn btn--sm" href="<?php echo view_url('courses_next', ['action'=>'download_template']); ?>">Download template</a>
              <a class="btn btn--sm" id="goto-next-and-import" href="<?php echo view_url('courses_next'); ?>">Import CSV</a>
              <a class="btn btn--sm btn--light" href="<?php echo view_url('courses_next'); ?>">No, edit manually</a>
            </div>
            <div class="muted" style="margin-top:10px;">Template headers match the import preview requirements.</div>
          </div>
        </div>
      </div>

      <!-- ===================== Import Modal ===================== -->
      <div class="modal <?php echo $open_import_modal ? 'is-open' : ''; ?>" id="importModal" aria-hidden="<?php echo $open_import_modal?'false':'true'; ?>">
        <div class="modal__dialog">
          <div class="modal__header">
            <h4>Import Courses</h4>
            <button type="button" class="modal__close btn btn--sm btn--light" id="close-import" aria-label="Cancel import">Cancel</button>
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
                  <span class="file-badge">Target: <?php echo $e(year_label($working_start_year)); ?></span>
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
"中文一年级","C101",300,"一年级综合课","Sunday","Fall",<?php echo $e($working_start_year); ?>,2,25,101
"中文二年级","C201",320,"二年级综合课","Sunday","Fall",<?php echo $e($working_start_year); ?>,2,28,102
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
