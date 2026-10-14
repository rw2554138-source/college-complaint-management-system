<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (file_exists(__DIR__. '/config/database.php')) {
    require_once __DIR__. '/config/database.php';
} elseif (file_exists(__DIR__. '/../config/database.php')) {
    require_once __DIR__. '/../config/database.php';
} else {
    die("database.php kidaikala");
}

$is_mysqli = isset($conn) && $conn instanceof mysqli;
$is_pdo = isset($pdo) && $pdo instanceof PDO;
if (!$is_mysqli &&!$is_pdo) die("DB connect aagala");

function getCount($pdo, $conn, $is_mysqli, $sql, $params = []) {
    if ($is_mysqli) {
        $stmt = $conn->prepare($sql);
        if ($params) $stmt->bind_param(str_repeat('i', count($params)),...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_array();
        return (int)$row[0];
    } else {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return (int)$s->fetchColumn();
    }
}

$user_id = (int)($_SESSION['user_id']?? 0);
$user_name = (string)($_SESSION['user_name']?? $_SESSION['name']?? 'User');
$user_role = strtolower(trim((string)($_SESSION['role']?? 'student')));

// --- RESOLVE LOGIC START ---
if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $complaint_id = (int)($_POST['complaint_id']?? 0);
    $new_status = $_POST['status']?? '';
    $allowed = ['Pending', 'In Progress', 'Resolved'];
    if ($complaint_id > 0 && in_array($new_status, $allowed)) {
        if ($is_mysqli) {
            $stmt = $conn->prepare("UPDATE ccms_complaints SET status=? WHERE id=?");
            $stmt->bind_param("si", $new_status, $complaint_id);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("UPDATE ccms_complaints SET status=? WHERE id=?");
            $stmt->execute([$new_status, $complaint_id]);
        }
    }
    header("Location: dashboard.php");
    exit;
}
// --- RESOLVE LOGIC END ---

$total = $pending = $inprogress = $resolved = 0;
$all = [];

try {
    if ($user_role === 'admin') {
        $total = getCount($pdo?? null, $conn?? null, $is_mysqli, "SELECT COUNT(*) FROM ccms_complaints");
        $pending = getCount($pdo?? null, $conn?? null, $is_mysqli, "SELECT COUNT(*) FROM ccms_complaints WHERE status='Pending'");
        $inprogress = getCount($pdo?? null, $conn?? null, $is_mysqli, "SELECT COUNT(*) FROM ccms_complaints WHERE status='In Progress'");
        $resolved = getCount($pdo?? null, $conn?? null, $is_mysqli, "SELECT COUNT(*) FROM ccms_complaints WHERE status='Resolved'");
        if ($is_mysqli) {
            $all = $conn->query("SELECT c.*, u.name as student_name, u.email FROM ccms_complaints c LEFT JOIN ccms_users u ON c.user_id = u.id ORDER BY c.id DESC")->fetch_all(MYSQLI_ASSOC);
        } else {
            $all = $pdo->query("SELECT c.*, u.name as student_name, u.email FROM ccms_complaints c LEFT JOIN ccms_users u ON c.user_id = u.id ORDER BY c.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $total = getCount($pdo?? null, $conn?? null, $is_mysqli, "SELECT COUNT(*) FROM ccms_complaints WHERE user_id=?", [$user_id]);
        $pending = getCount($pdo?? null, $conn?? null, $is_mysqli, "SELECT COUNT(*) FROM ccms_complaints WHERE user_id=? AND status='Pending'", [$user_id]);
        $inprogress = getCount($pdo?? null, $conn?? null, $is_mysqli, "SELECT COUNT(*) FROM ccms_complaints WHERE user_id=? AND status='In Progress'", [$user_id]);
        $resolved = getCount($pdo?? null, $conn?? null, $is_mysqli, "SELECT COUNT(*) FROM ccms_complaints WHERE user_id=? AND status='Resolved'", [$user_id]);
    }
} catch (Throwable $e) {
    die("DB Error: ". $e->getMessage());
}

function getBadge($status){
    if($status === 'Resolved') return 'bg-success';
    if($status === 'In Progress') return 'bg-primary';
    return 'bg-warning text-dark';
}
?>
<html><head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.desc-row{display:none; background:#f8f9fa;}.desc-box{padding:15px;}</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">CCMS - <?= strtoupper(htmlspecialchars($user_role))?> - <?= htmlspecialchars($user_name)?></span><a href="logout.php" class="btn btn-sm btn-light">Logout</a></div></nav>
<div class="container mt-4">
<div class="row g-2"><div class="col-3"><div class="card p-2 text-center"><h4><?= $total?></h4>Total</div></div><div class="col-3"><div class="card p-2 text-center"><h4><?= $pending?></h4>Pending</div></div><div class="col-3"><div class="card p-2 text-center"><h4><?= $inprogress?></h4>In Progress</div></div><div class="col-3"><div class="card p-2 text-center"><h4><?= $resolved?></h4>Resolved</div></div></div>

<?php if ($user_role === 'admin') {?>
<h4 class="mt-4">All Complaints - Admin Only</h4>
<table class="table table-bordered mt-3"><tr><th>ID</th><th>Student</th><th>Subject</th><th>Status</th><th>View</th></tr>
<?php foreach ($all as $r) {?>
<tr>
<td><?= (int)$r['id']?></td>
<td><?= htmlspecialchars((string)($r['student_name']?? 'Unknown'))?></td>
<td><?= htmlspecialchars((string)($r['subject']?? ''))?></td>
<td><span class="badge <?= getBadge($r['status']?? 'Pending')?>"><?= htmlspecialchars((string)($r['status']?? 'Pending'))?></span></td>
<td><button onclick="toggleDesc(<?= (int)$r['id']?>)" class="btn btn-sm btn-primary">View</button></td>
</tr>
<tr id="d-<?= (int)$r['id']?>" class="desc-row"><td colspan="5"><div class="desc-box">
<b>ID #<?= (int)$r['id']?> - <?= htmlspecialchars((string)($r['subject']?? ''))?></b><br>
<b>Student:</b> <?= htmlspecialchars((string)($r['student_name']?? 'Unknown'))?> (<?= htmlspecialchars((string)($r['email']?? ''))?>)<br>
<b>Category:</b> <?= htmlspecialchars((string)($r['category']?? 'N/A'))?> | <b>Priority:</b> <?= htmlspecialchars((string)($r['priority']?? 'N/A'))?><br><br>
<b>Full Description:</b><br><?= nl2br(htmlspecialchars((string)($r['description']?? '')))?><br><br>
<form method="POST" class="d-flex gap-2 align-items-center">
  <input type="hidden" name="complaint_id" value="<?= (int)$r['id']?>">
  <select name="status" class="form-control" style="width:180px;">
    <option value="Pending" <?= ($r['status']=='Pending')?'selected':''?>>Pending</option>
    <option value="In Progress" <?= ($r['status']=='In Progress')?'selected':''?>>In Progress</option>
    <option value="Resolved" <?= ($r['status']=='Resolved')?'selected':''?>>Resolved</option>
  </select>
  <button type="submit" name="update_status" class="btn btn-success btn-sm">Update Status</button>
  <button type="button" onclick="toggleDesc(<?= (int)$r['id']?>)" class="btn btn-sm btn-secondary">Close</button>
</form>
</div></td></tr>
<?php }?></table>
<?php if (count($all) === 0) echo "<p>Complaints innum illa.</p>";?>
<?php } else {?>
<h4 class="mt-4">Submit Complaint - Student Only</h4>
<form method="POST" action="submit_complaint.php" class="card p-3">
  <div class="row">
    <div class="col-6 mb-2"><label>Category</label><select name="category" class="form-control" required><option value="">Select</option><option>Hostel</option><option>Food</option><option>Transport</option><option>Academics</option><option>Fees</option><option>Others</option></select></div>
    <div class="col-6 mb-2"><label>Priority</label><select name="priority" class="form-control"><option>Medium</option><option>High</option><option>Low</option></select></div>
  </div>
  <label>Subject</label><input name="subject" class="form-control mb-2" required>
  <label>Description</label><textarea name="description" class="form-control mb-2" rows="4" required></textarea>
  <button class="btn btn-primary w-100">Submit</button>
</form>
<h5 class="mt-4">My Complaints</h5>
<table class="table table-bordered"><tr><th>ID</th><th>Subject</th><th>Status</th></tr>
<?php
if ($is_mysqli) { $stmt = $conn->prepare("SELECT * FROM ccms_complaints WHERE user_id=? ORDER BY id DESC"); $stmt->bind_param("i", $user_id); $stmt->execute(); $myRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }
else { $my = $pdo->prepare("SELECT * FROM ccms_complaints WHERE user_id=? ORDER BY id DESC"); $my->execute([$user_id]); $myRows = $my->fetchAll(PDO::FETCH_ASSOC); }
foreach ($myRows as $m) {?>
<tr><td><?= (int)$m['id']?></td><td><?= htmlspecialchars((string)($m['subject']?? ''))?></td><td><span class="badge <?= getBadge($m['status']?? 'Pending')?>"><?= htmlspecialchars((string)($m['status']?? 'Pending'))?></span></td></tr><?php }?></table>
<?php }?>
</div>
<script>
function toggleDesc(id){
  var el = document.getElementById('d-'+id);
  el.style.display = el.style.display === 'table-row'? 'none' : 'table-row';
}
</script>
</body></html>