<?php

session_start();


<<<<<<< HEAD
// ── HARDCODED ADMIN ACCOUNT ──────────────────────────────────
$hardcoded_email    = "admin@pinandthrow.com";
$hardcoded_password = "admin123";

// If logging in via hardcoded account
if (isset($_POST['login_email']) && isset($_POST['login_password'])) {
    if ($_POST['login_email'] === $hardcoded_email && $_POST['login_password'] === $hardcoded_password) {
        $_SESSION['user_id']  = 0;
        $_SESSION['role']     = 'admin';
        $_SESSION['username'] = 'Admin Officer';
    }
}

// Block access if not logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '
    <form method="POST" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;gap:12px;font-family:sans-serif;">
        <h2>Pin & Throw — Admin Login</h2>
        <input type="email"    name="login_email"    placeholder="Email"    required style="padding:10px;width:280px;border:1px solid #ccc;border-radius:8px;">
        <input type="password" name="login_password" placeholder="Password" required style="padding:10px;width:280px;border:1px solid #ccc;border-radius:8px;">
        <button type="submit" style="padding:10px;width:280px;background:#1a7a3e;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer;">Login</button>
    </form>';
    exit();
}

$host   = 'localhost';
$dbname = 'pinandthrow_db';
=======
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['officer', 'admin'])) {
    header('Location: login.php');
    exit();
}


$host   = 'localhost';
$dbname = 'pin_and_throw';
>>>>>>> 9628f4d9f05b8e2864d28b7ca13ebd1f33438fb0
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $report_id = intval($_POST['report_id']);
    $new_status = $_POST['status'];
    $officer_id = $_SESSION['user_id'];

    $allowed = ['pending', 'verified', 'inprogress', 'resolved', 'rejected'];
    if (!in_array($new_status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }

    // Update report status and assign officer
    $stmt = $pdo->prepare("UPDATE Reports SET status = ?, officer_ID = ? WHERE report_ID = ?");
    $stmt->execute([$new_status, $officer_id, $report_id]);

    // Insert a notification for the resident
    $msgMap = [
        'verified'   => 'Your report has been verified by the barangay.',
        'inprogress' => 'A cleanup crew has been dispatched to your reported location.',
        'resolved'   => 'Your report has been resolved. Thank you for keeping the barangay clean!',
        'rejected'   => 'Your report was reviewed but could not be actioned at this time.',
        'pending'    => 'Your report status has been reset to pending.',
    ];
    $message = $msgMap[$new_status] ?? 'Your report status has been updated.';

    // Get the resident_ID from the report
    $res = $pdo->prepare("SELECT resident_ID FROM Reports WHERE report_ID = ?");
    $res->execute([$report_id]);
    $report = $res->fetch(PDO::FETCH_ASSOC);

    if ($report) {
        $notif = $pdo->prepare("INSERT INTO Notifications (report_ID, user_ID, message, isRead) VALUES (?, ?, ?, 0)");
        $notif->execute([$report_id, $report['resident_ID'], $message]);
    }

    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    exit();
}


$counts = $pdo->query("
    SELECT
        SUM(status = 'pending')    AS pending,
        SUM(status = 'inprogress') AS inprogress,
        SUM(status = 'resolved')   AS resolved,
        COUNT(*)                   AS total
    FROM Reports
")->fetch(PDO::FETCH_ASSOC);


$active_tab = $_GET['tab'] ?? 'pending';
$allowed_tabs = ['pending', 'verified', 'inprogress', 'resolved', 'rejected'];
if (!in_array($active_tab, $allowed_tabs)) $active_tab = 'pending';

$stmt = $pdo->prepare("
    SELECT r.report_ID, r.description, r.imageUrl, r.status, r.timestamp,
           u.firstName, u.lastName,
           l.locationName, l.latitude, l.longitude
    FROM Reports r
    JOIN Users u ON u.user_ID = r.resident_ID
    LEFT JOIN Locations l ON l.report_ID = r.report_ID
    WHERE r.status = ?
    ORDER BY r.timestamp DESC
    LIMIT 20
");
$stmt->execute([$active_tab]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);


$tab_counts = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM Reports GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);


$alerts = $pdo->query("
    SELECT r.report_ID, r.description, r.timestamp,
           l.locationName,
           DATEDIFF(NOW(), r.timestamp) AS days_old
    FROM Reports r
    LEFT JOIN Locations l ON l.report_ID = r.report_ID
    WHERE r.status IN ('pending','verified')
      AND (DATEDIFF(NOW(), r.timestamp) >= 3 OR r.description LIKE '%hazard%')
    ORDER BY days_old DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$activity = $pdo->query("
    SELECT n.message, n.report_ID,
           u.firstName, u.lastName,
           n.isRead,
           r.status,
           r.timestamp
    FROM Notifications n
    JOIN Users u ON u.user_ID = n.user_ID
    JOIN Reports r ON r.report_ID = n.report_ID
    ORDER BY n.notification_ID DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$by_location = $pdo->query("
    SELECT l.locationName, COUNT(*) AS cnt
    FROM Reports r
    JOIN Locations l ON l.report_ID = r.report_ID
    GROUP BY l.locationName
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);


$max_loc = !empty($by_location) ? max(array_column($by_location, 'cnt')) : 1;


$officer = $pdo->prepare("SELECT firstName, lastName FROM Users WHERE user_ID = ?");
$officer->execute([$_SESSION['user_id']]);
$officer = $officer->fetch(PDO::FETCH_ASSOC);
$officer_initials = strtoupper(substr($officer['firstName'],0,1) . substr($officer['lastName'],0,1));
<<<<<<< HEAD
$officer_initials = 'AO';
$officer_name     = 'Admin Officer';
=======
$officer_name = htmlspecialchars($officer['firstName'] . ' ' . $officer['lastName']);
>>>>>>> 9628f4d9f05b8e2864d28b7ca13ebd1f33438fb0


function statusClass($s) {
    return match($s) {
        'verified'   => 'verified',
        'resolved'   => 'resolved',
        'rejected'   => 'rejected',
        'inprogress' => 'verified',
        default      => 'pending',
    };
}

// Helper: status display label
function statusLabel($s) {
    return match($s) {
        'inprogress' => 'In-Progress',
        default      => ucfirst($s),
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pin and Throw — Officer Command Center</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #f0f7f2;
    --surface: #ffffff;
    --card: #ffffff;
    --border: #d4e8da;
    --accent: #1a7a3e;
    --accent-dim: rgba(26,122,62,0.10);
    --warn: #d94f1e;
    --warn-dim: rgba(217,79,30,0.10);
    --text: #1a2d22;
    --muted: #6b8f76;
    --sidebar-w: 230px;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; min-height: 100vh; display: flex; overflow-x: hidden; }

  /* ── SIDEBAR ── */
  .sidebar { width: var(--sidebar-w); background: #1a7a3e; border-right: 1px solid #155e30; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; }
  .sidebar-logo { padding: 28px 24px 24px; border-bottom: 1px solid rgba(255,255,255,0.15); }
  .sidebar-logo .brand { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px; }
  .logo-pin { width: 28px; height: 28px; background: #fff; border-radius: 50% 50% 50% 4px; transform: rotate(-45deg); flex-shrink: 0; position: relative; }
  .logo-pin::after { content:''; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:10px; height:10px; background:#1a7a3e; border-radius:50%; }
  .sidebar-logo .sub { font-size: 10px; color: rgba(255,255,255,0.6); font-family: 'DM Mono', monospace; margin-top: 4px; letter-spacing: 1px; text-transform: uppercase; }
  .nav-section { padding: 20px 14px 0; flex: 1; }
  .nav-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; color: rgba(255,255,255,0.45); padding: 0 10px; margin-bottom: 6px; font-family: 'DM Mono', monospace; }
  .nav-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; cursor: pointer; font-size: 13.5px; color: rgba(255,255,255,0.7); transition: all .15s; margin-bottom: 2px; font-weight: 500; text-decoration: none; }
  .nav-item:hover { background: rgba(255,255,255,0.12); color: #fff; }
  .nav-item.active { background: rgba(255,255,255,0.18); color: #fff; }
  .nav-icon { font-size: 15px; width: 18px; text-align: center; }
  .nav-badge { margin-left: auto; background: rgba(255,255,255,0.25); color: #fff; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 20px; font-family: 'DM Mono', monospace; }
  .nav-group-gap { height: 20px; }
  .sidebar-footer { padding: 16px 14px; border-top: 1px solid rgba(255,255,255,0.15); }
  .officer-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: rgba(255,255,255,0.12); border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); }
  .officer-avatar { width: 32px; height: 32px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 12px; color: #1a7a3e; flex-shrink: 0; }
  .officer-info .name { font-size: 12.5px; font-weight: 500; color: #fff; }
  .officer-info .role { font-size: 10px; color: rgba(255,255,255,0.55); font-family: 'DM Mono', monospace; }
  .logout-link { display: block; text-align: center; margin-top: 8px; font-size: 11px; color: rgba(255,255,255,0.5); font-family: 'DM Mono', monospace; text-decoration: none; }
  .logout-link:hover { color: #fff; }

  /* ── MAIN ── */
  .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
  .topbar { display: flex; align-items: center; justify-content: space-between; padding: 16px 32px; border-bottom: 1px solid var(--border); background: #fff; position: sticky; top: 0; z-index: 50; box-shadow: 0 1px 4px rgba(26,122,62,0.07); }
  .topbar-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; }
  .topbar-subtitle { font-size: 12px; color: var(--muted); font-family: 'DM Mono', monospace; }
  .topbar-right { display: flex; align-items: center; gap: 12px; }
  .btn-icon { width: 36px; height: 36px; border-radius: 8px; background: #f0f7f2; border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all .15s; position: relative; text-decoration: none; }
  .btn-icon:hover { border-color: var(--accent); background: var(--accent-dim); }
  .notif-dot { position: absolute; top: 6px; right: 6px; width: 7px; height: 7px; background: var(--warn); border-radius: 50%; border: 1.5px solid #fff; }
  .live-badge { display: flex; align-items: center; gap: 6px; background: var(--accent-dim); border: 1px solid rgba(26,122,62,0.25); border-radius: 20px; padding: 5px 12px; font-size: 11px; color: var(--accent); font-family: 'DM Mono', monospace; font-weight: 500; }
  .live-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); animation: pulse 1.4s infinite; }
  @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.8)} }

  /* ── CONTENT ── */
  .content { padding: 28px 32px; flex: 1; }

  /* ── STATS ── */
  .stat-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
  .stat-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px 22px; position: relative; overflow: hidden; animation: fadeUp .4s ease both; }
  .stat-card::before { content:''; position:absolute; top:0;left:0;right:0; height:2px; }
  .stat-card.green::before { background:#1a7a3e; }
  .stat-card.orange::before { background:var(--warn); }
  .stat-card.blue::before { background:#1a7a3e; }
  .stat-card.yellow::before { background:#b07d00; }
  .stat-label { font-size:10px; text-transform:uppercase; letter-spacing:1.2px; color:var(--muted); font-family:'DM Mono',monospace; margin-bottom:10px; }
  .stat-val { font-family:'Syne',sans-serif; font-size:36px; font-weight:800; line-height:1; margin-bottom:8px; }
  .stat-card.green .stat-val  { color:#1a7a3e; }
  .stat-card.orange .stat-val { color:var(--warn); }
  .stat-card.blue .stat-val   { color:#1a7a3e; }
  .stat-card.yellow .stat-val { color:#b07d00; }
  .stat-icon { position:absolute; top:18px; right:18px; font-size:28px; opacity:.13; }
  @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

  /* ── LAYOUT ── */
  .grid-2 { display:grid; grid-template-columns:1fr 340px; gap:20px; margin-bottom:24px; }
  .grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }

  /* ── PANEL ── */
  .panel { background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; }
  .panel-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
  .panel-title { font-family:'Syne',sans-serif; font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; }
  .panel-title .dot { width:8px; height:8px; border-radius:50%; }
  .panel-action { font-size:11px; color:var(--accent); cursor:pointer; font-family:'DM Mono',monospace; font-weight:500; }

  /* ── TABS ── */
  .tab-bar { display:flex; border-bottom:1px solid var(--border); padding:0 20px; }
  .tab { font-size:12px; font-weight:500; padding:11px 16px; cursor:pointer; color:var(--muted); border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .15s; text-decoration:none; display:flex; align-items:center; gap:6px; }
  .tab.active { color:#1a7a3e; border-bottom-color:#1a7a3e; }
  .tab:hover:not(.active) { color:var(--text); }
  .tab .count { background:#e8f5ee; border-radius:20px; font-family:'DM Mono',monospace; font-size:10px; padding:0 6px; line-height:16px; }
  .tab.active .count { background:rgba(26,122,62,.15); color:#1a7a3e; }

  /* ── REPORT LIST ── */
  .report-list { overflow-y:auto; max-height:430px; }
  .report-row { display:flex; align-items:center; padding:13px 20px; border-bottom:1px solid var(--border); cursor:pointer; transition:background .12s; gap:14px; }
  .report-row:last-child { border-bottom:none; }
  .report-row:hover { background:rgba(26,122,62,.03); }
  .report-row.selected { background:var(--accent-dim); border-left:2px solid var(--accent); }
  .report-thumb { width:42px; height:42px; border-radius:6px; background:#e8f5ee; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:18px; overflow:hidden; }
  .report-thumb img { width:100%; height:100%; object-fit:cover; border-radius:6px; }
  .report-meta { flex:1; min-width:0; }
  .report-id { font-family:'DM Mono',monospace; font-size:10px; color:var(--muted); margin-bottom:3px; }
  .report-desc { font-size:13px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:4px; }
  .report-loc { font-size:11px; color:var(--muted); }
  .status-pill { font-size:10px; font-family:'DM Mono',monospace; font-weight:500; padding:3px 9px; border-radius:20px; white-space:nowrap; flex-shrink:0; }
  .status-pill.pending  { background:rgba(176,125,0,.10); color:#9a6c00; border:1px solid rgba(176,125,0,.25); }
  .status-pill.verified { background:rgba(26,122,62,.10); color:#1a7a3e; border:1px solid rgba(26,122,62,.25); }
  .status-pill.resolved { background:rgba(21,94,48,.10); color:#155e30; border:1px solid rgba(21,94,48,.25); }
  .status-pill.rejected { background:rgba(192,57,43,.10); color:#c0392b; border:1px solid rgba(192,57,43,.25); }

  /* ── DETAIL PANEL ── */
  .detail-body { padding:20px; overflow-y:auto; max-height:430px; }
  .detail-img { width:100%; height:140px; background:#e8f5ee; border-radius:8px; margin-bottom:16px; display:flex; align-items:center; justify-content:center; font-size:32px; overflow:hidden; position:relative; }
  .detail-img img { width:100%; height:100%; object-fit:cover; border-radius:8px; }
  .detail-img-label { position:absolute; bottom:8px; left:8px; background:rgba(0,0,0,.5); border-radius:4px; font-size:10px; padding:3px 7px; font-family:'DM Mono',monospace; color:#fff; }
  .detail-map { width:100%; height:110px; background:linear-gradient(135deg,#e8f5ee,#d4ede0); border-radius:8px; margin-bottom:16px; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; }
  .detail-map-grid { position:absolute; inset:0; background-image:linear-gradient(rgba(26,122,62,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(26,122,62,.08) 1px,transparent 1px); background-size:20px 20px; }
  .detail-map-dot { width:14px; height:14px; background:var(--warn); border-radius:50%; border:2px solid rgba(255,255,255,.7); box-shadow:0 0 0 6px rgba(217,79,30,.2); position:relative; z-index:2; }
  .detail-map-coords { position:absolute; bottom:8px; right:8px; font-size:9px; font-family:'DM Mono',monospace; color:#1a7a3e; opacity:.8; }
  .detail-row { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; font-size:12.5px; }
  .detail-key { color:var(--muted); flex-shrink:0; width:90px; }
  .detail-val { text-align:right; font-weight:500; font-size:12px; }
  .detail-val.mono { font-family:'DM Mono',monospace; font-size:11px; color:var(--muted); }
  .detail-divider { height:1px; background:var(--border); margin:14px 0; }
  .desc-text { font-size:12.5px; line-height:1.6; color:var(--text); margin-bottom:16px; padding:12px; background:#f0f7f2; border-radius:8px; border:1px solid var(--border); }
  .field-label { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); font-family:'DM Mono',monospace; margin-bottom:6px; }
  .status-select { width:100%; background:#f0f7f2; border:1px solid var(--border); border-radius:8px; color:var(--text); font-family:'DM Sans',sans-serif; font-size:13px; padding:9px 12px; cursor:pointer; outline:none; margin-bottom:10px; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='none'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%236b8f76' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; }
  .status-select:focus { border-color:var(--accent); }
  .btn-primary { width:100%; background:#1a7a3e; color:#fff; border:none; border-radius:8px; padding:10px; font-family:'Syne',sans-serif; font-weight:700; font-size:13px; cursor:pointer; transition:opacity .15s,transform .1s; }
  .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
  .btn-danger { width:100%; background:transparent; color:#c0392b; border:1px solid rgba(192,57,43,.25); border-radius:8px; padding:9px; font-family:'DM Mono',monospace; font-size:12px; cursor:pointer; margin-top:6px; transition:background .15s; }
  .btn-danger:hover { background:rgba(192,57,43,.06); }

  /* ── ALERTS ── */
  .alert-item { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; gap:12px; align-items:flex-start; }
  .alert-item:last-child { border-bottom:none; }
  .alert-icon-wrap { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
  .alert-icon-wrap.high { background:rgba(217,79,30,.10); }
  .alert-icon-wrap.med  { background:rgba(176,125,0,.10); }
  .alert-text { flex:1; }
  .alert-title { font-size:12.5px; font-weight:500; margin-bottom:3px; }
  .alert-sub { font-size:11px; color:var(--muted); }
  .alert-time { font-family:'DM Mono',monospace; font-size:10px; color:var(--muted); white-space:nowrap; }

  /* ── HEATMAP ── */
  .heatmap-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; margin-bottom:10px; }
  .heatmap-cell { aspect-ratio:1; border-radius:4px; cursor:pointer; transition:transform .1s; }
  .heatmap-cell:hover { transform:scale(1.15); }
  .heatmap-cell.l0 { background:#e8f5ee; border:1px solid #d4e8da; }
  .heatmap-cell.l1 { background:rgba(26,122,62,.2); }
  .heatmap-cell.l2 { background:rgba(26,122,62,.4); }
  .heatmap-cell.l3 { background:rgba(26,122,62,.65); }
  .heatmap-cell.l4 { background:#1a7a3e; }
  .heatmap-cell.lh { background:#d94f1e; }
  .heatmap-legend { display:flex; align-items:center; gap:6px; font-size:10px; color:var(--muted); font-family:'DM Mono',monospace; }
  .heatmap-legend .cells { display:flex; gap:3px; }
  .heatmap-legend .cells span { width:10px; height:10px; border-radius:2px; display:block; }

  /* ── BAR CHART ── */
  .bar-chart { display:flex; flex-direction:column; gap:10px; }
  .bar-row { display:flex; flex-direction:column; gap:5px; }
  .bar-label { display:flex; justify-content:space-between; font-size:11px; color:var(--muted); font-family:'DM Mono',monospace; }
  .bar-track { height:6px; background:var(--border); border-radius:10px; overflow:hidden; }
  .bar-fill { height:100%; border-radius:10px; background:#1a7a3e; animation:growBar .8s .3s ease both; }
  @keyframes growBar { from{width:0!important} }

  /* ── DONUT ── */
  .donut-wrap { display:flex; align-items:center; gap:20px; padding:16px 20px; }
  .donut-legend { flex:1; }
  .legend-item { display:flex; align-items:center; gap:8px; font-size:11.5px; margin-bottom:8px; }
  .legend-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
  .legend-pct { font-family:'DM Mono',monospace; font-size:11px; color:var(--muted); margin-left:auto; }

  /* ── TIMELINE ── */
  .timeline { padding:12px 20px; }
  .tl-item { display:flex; gap:12px; margin-bottom:14px; position:relative; }
  .tl-dot { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; }
  .tl-dot.green  { background:rgba(26,122,62,.12); }
  .tl-dot.orange { background:rgba(217,79,30,.10); }
  .tl-dot.blue   { background:rgba(26,122,62,.08); }
  .tl-body { flex:1; padding-top:4px; }
  .tl-action { font-size:12.5px; font-weight:500; margin-bottom:2px; }
  .tl-meta { font-size:11px; color:var(--muted); font-family:'DM Mono',monospace; }

  /* ── MISC ── */
  ::-webkit-scrollbar { width:4px; }
  ::-webkit-scrollbar-thumb { background:#c8dece; border-radius:4px; }
  .toast { position:fixed; bottom:24px; right:24px; background:#fff; border:1px solid #1a7a3e; border-radius:10px; padding:12px 18px; font-size:13px; color:#1a7a3e; font-family:'DM Mono',monospace; z-index:999; opacity:0; transform:translateY(10px); transition:all .3s; pointer-events:none; box-shadow:0 4px 20px rgba(26,122,62,.15); }
  .toast.show { opacity:1; transform:translateY(0); }
  .empty-state { padding:32px 20px; text-align:center; color:var(--muted); font-size:13px; }
</style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="brand"><div class="logo-pin"></div> Pin &amp; Throw</div>
    <div class="sub">Officer Command Center</div>
  </div>
  <nav class="nav-section">
    <div class="nav-label">Main</div>
    <a class="nav-item active" href="admin_dashboard.php"><span class="nav-icon">🗺️</span> Dashboard</a>
    <a class="nav-item" href="admin_dashboard.php?tab=pending"><span class="nav-icon">📋</span> All Reports
      <span class="nav-badge"><?= intval($tab_counts['pending'] ?? 0) ?></span>
    </a>
    <a class="nav-item" href="admin_dashboard.php?tab=pending"><span class="nav-icon">⚠️</span> High Priority
      <span class="nav-badge"><?= count($alerts) ?></span>
    </a>
    <div class="nav-group-gap"></div>
    <div class="nav-label">Management</div>
    <a class="nav-item" href="admin_dashboard.php?tab=verified"><span class="nav-icon">✅</span> Verified</a>
    <a class="nav-item" href="admin_dashboard.php?tab=inprogress"><span class="nav-icon">🔄</span> In-Progress</a>
    <a class="nav-item" href="admin_dashboard.php?tab=resolved"><span class="nav-icon">📁</span> Resolved Archive</a>
    <div class="nav-group-gap"></div>
    <div class="nav-label">Admin</div>
    <a class="nav-item" href="analytics.php"><span class="nav-icon">📊</span> Analytics</a>
    <a class="nav-item" href="user_management.php"><span class="nav-icon">👥</span> User Management</a>
    <a class="nav-item" href="settings.php"><span class="nav-icon">⚙️</span> Settings</a>
  </nav>
  <div class="sidebar-footer">
    <div class="officer-card">
      <div class="officer-avatar"><?= $officer_initials ?></div>
      <div class="officer-info">
        <div class="name"><?= $officer_name ?></div>
        <div class="role"><?= strtoupper(htmlspecialchars($_SESSION['role'])) ?></div>
      </div>
    </div>
    <a class="logout-link" href="logout.php">← Log out</a>
  </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title">Officer Dashboard</div>
      <div class="topbar-subtitle">Brgy. Pio Del Pilar, Makati City</div>
    </div>
    <div class="topbar-right">
      <div class="live-badge"><div class="live-dot"></div> LIVE MONITORING</div>
      <a class="btn-icon" href="notifications.php" title="Notifications">🔔
        <?php if (!empty($alerts)): ?><div class="notif-dot"></div><?php endif; ?>
      </a>
      <a class="btn-icon" href="export.php" title="Export">📤</a>
    </div>
  </div>

  <div class="content">

    <!-- STATS -->
    <div class="stat-grid">
      <div class="stat-card orange">
        <div class="stat-icon">🗑️</div>
        <div class="stat-label">Pending Reports</div>
        <div class="stat-val"><?= intval($counts['pending'] ?? 0) ?></div>
        <div class="stat-delta">Awaiting action</div>
      </div>
      <div class="stat-card blue">
        <div class="stat-icon">🔄</div>
        <div class="stat-label">In-Progress</div>
        <div class="stat-val"><?= intval($counts['inprogress'] ?? 0) ?></div>
        <div class="stat-delta">Crews active</div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">Resolved</div>
        <div class="stat-val"><?= intval($counts['resolved'] ?? 0) ?></div>
        <div class="stat-delta up">Total resolved</div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-icon">📋</div>
        <div class="stat-label">Total Reports</div>
        <div class="stat-val"><?= intval($counts['total'] ?? 0) ?></div>
        <div class="stat-delta">All time</div>
      </div>
    </div>

    <!-- REPORT LIST + DETAIL -->
    <div class="grid-2" style="margin-bottom:24px;">
      <div class="panel" style="display:flex;flex-direction:column;">
        <div class="tab-bar">
          <?php
          $tabs = [
            'pending'    => 'Unverified',
            'inprogress' => 'Active',
            'resolved'   => 'Resolved',
          ];
          foreach ($tabs as $key => $label):
            $cnt = intval($tab_counts[$key] ?? 0);
            $cls = $active_tab === $key ? 'active' : '';
          ?>
          <a class="tab <?= $cls ?>" href="?tab=<?= $key ?>">
            <?= $label ?> <span class="count"><?= $cnt ?></span>
          </a>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;flex:1;min-height:420px;">
          <!-- REPORT LIST -->
          <div style="width:55%;border-right:1px solid var(--border);">
            <div class="report-list" id="reportList">
              <?php if (empty($reports)): ?>
                <div class="empty-state">No reports found.</div>
              <?php else: ?>
                <?php foreach ($reports as $i => $r):
                  $name = htmlspecialchars($r['firstName'] . ' ' . $r['lastName']);
                  $loc  = htmlspecialchars($r['locationName'] ?? 'Unknown location');
                  $desc = htmlspecialchars($r['description']);
                  $date = date('M d, Y · h:i A', strtotime($r['timestamp']));
                  $cls  = statusClass($r['status']);
                  $lbl  = statusLabel($r['status']);
                  $img  = htmlspecialchars($r['imageUrl'] ?? '');
                  $lat  = htmlspecialchars($r['latitude'] ?? '');
                  $lng  = htmlspecialchars($r['longitude'] ?? '');
                ?>
                <div class="report-row <?= $i === 0 ? 'selected' : '' ?>"
                     onclick="selectReport(this)"
                     data-id="<?= intval($r['report_ID']) ?>"
                     data-reporter="<?= $name ?>"
                     data-loc="<?= $loc ?>"
                     data-date="<?= $date ?>"
                     data-desc="<?= $desc ?>"
                     data-status="<?= htmlspecialchars($r['status']) ?>"
                     data-img="<?= $img ?>"
                     data-lat="<?= $lat ?>"
                     data-lng="<?= $lng ?>">
                  <div class="report-thumb">
                    <?php if ($img): ?>
                      <img src="<?= $img ?>" alt="Proof image">
                    <?php else: ?>🗑️<?php endif; ?>
                  </div>
                  <div class="report-meta">
                    <div class="report-id">#RPT-<?= str_pad($r['report_ID'], 4, '0', STR_PAD_LEFT) ?></div>
                    <div class="report-desc"><?= $desc ?></div>
                    <div class="report-loc">📍 <?= $loc ?></div>
                  </div>
                  <div class="status-pill <?= $cls ?>"><?= $lbl ?></div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- DETAIL PANEL -->
          <div style="flex:1;display:flex;flex-direction:column;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
              <div id="detailId" style="font-size:12px;font-family:'DM Mono',monospace;color:var(--muted);">
                <?php if (!empty($reports)): ?>#RPT-<?= str_pad($reports[0]['report_ID'], 4, '0', STR_PAD_LEFT) ?><?php endif; ?>
              </div>
              <div class="status-pill <?= !empty($reports) ? statusClass($reports[0]['status']) : 'pending' ?>" id="detailBadge">
                <?= !empty($reports) ? statusLabel($reports[0]['status']) : 'Pending' ?>
              </div>
            </div>
            <div class="detail-body">
              <!-- Image proof -->
              <div class="detail-img" id="detailImgWrap">
                <?php if (!empty($reports[0]['imageUrl'])): ?>
                  <img id="detailImg" src="<?= htmlspecialchars($reports[0]['imageUrl']) ?>" alt="Proof">
                <?php else: ?>
                  <span id="detailImgPlaceholder">🗑️</span>
                <?php endif; ?>
                <div class="detail-img-label">IMAGE PROOF</div>
              </div>

              <!-- Map -->
              <div class="detail-map">
                <div class="detail-map-grid"></div>
                <div class="detail-map-dot"></div>
                <div class="detail-map-coords" id="detailCoords">
                  <?= !empty($reports[0]['latitude']) ? $reports[0]['latitude'].'° N, '.$reports[0]['longitude'].'° E' : 'No coordinates' ?>
                </div>
              </div>

              <div class="detail-row">
                <span class="detail-key">Reporter</span>
                <span class="detail-val" id="detailReporter">
                  <?= !empty($reports) ? htmlspecialchars($reports[0]['firstName'].' '.$reports[0]['lastName']) : '—' ?>
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-key">Location</span>
                <span class="detail-val" id="detailLoc">
                  <?= !empty($reports) ? htmlspecialchars($reports[0]['locationName'] ?? '—') : '—' ?>
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-key">Submitted</span>
                <span class="detail-val mono" id="detailDate">
                  <?= !empty($reports) ? date('M d, Y · h:i A', strtotime($reports[0]['timestamp'])) : '—' ?>
                </span>
              </div>
              <div class="detail-divider"></div>
              <div class="desc-text" id="detailDesc">
                <?= !empty($reports) ? htmlspecialchars($reports[0]['description']) : 'Select a report to view details.' ?>
              </div>

              <input type="hidden" id="detailReportId" value="<?= !empty($reports) ? intval($reports[0]['report_ID']) : '' ?>">

              <div class="field-label">Update Status</div>
              <select class="status-select" id="statusSelect">
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="inprogress">In-Progress</option>
                <option value="resolved">Resolved</option>
                <option value="rejected">Rejected</option>
              </select>

              <button class="btn-primary" onclick="saveAction()">💾 Save &amp; Notify Resident</button>
              <button class="btn-danger"  onclick="rejectReport()">✕ Reject Report</button>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- ALERT FEED -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title"><div class="dot" style="background:var(--warn)"></div>Priority Alerts</div>
            <div class="panel-action">View all →</div>
          </div>
          <?php if (empty($alerts)): ?>
            <div class="empty-state">No priority alerts.</div>
          <?php else: ?>
            <?php foreach ($alerts as $a):
              $days = intval($a['days_old']);
              $severity = $days >= 3 ? 'high' : 'med';
              $icon = $days >= 3 ? '⚠️' : '🟡';
              $timeLabel = $days > 0 ? $days.'d ago' : 'Today';
            ?>
            <div class="alert-item">
              <div class="alert-icon-wrap <?= $severity ?>"><?= $icon ?></div>
              <div class="alert-text">
                <div class="alert-title"><?= htmlspecialchars(substr($a['description'], 0, 50)).'...' ?></div>
                <div class="alert-sub"><?= htmlspecialchars($a['locationName'] ?? 'Unknown') ?></div>
              </div>
              <div class="alert-time"><?= $timeLabel ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- HEATMAP (static visual — replace with real coords if needed) -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title"><div class="dot" style="background:#1a7a3e"></div>Waste Density Map</div>
          </div>
          <div style="padding:16px 20px;">
            <div class="heatmap-grid" id="heatmapGrid"></div>
            <div class="heatmap-legend">
              <span>Less</span>
              <div class="cells">
                <span style="background:#e8f5ee;border:1px solid #d4e8da"></span>
                <span style="background:rgba(26,122,62,.2)"></span>
                <span style="background:rgba(26,122,62,.4)"></span>
                <span style="background:rgba(26,122,62,.65)"></span>
                <span style="background:#1a7a3e"></span>
                <span style="background:#d94f1e"></span>
              </div>
              <span>Critical</span>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ANALYTICS ROW -->
    <div class="grid-3">

      <!-- BAR CHART (from DB) -->
      <div class="panel">
        <div class="panel-header"><div class="panel-title">Reports by Location</div></div>
        <div style="padding:16px 20px;">
          <div class="bar-chart">
            <?php if (empty($by_location)): ?>
              <div class="empty-state">No data yet.</div>
            <?php else: ?>
              <?php foreach ($by_location as $row):
                $pct = $max_loc > 0 ? round(($row['cnt'] / $max_loc) * 100) : 0;
              ?>
              <div class="bar-row">
                <div class="bar-label">
                  <span><?= htmlspecialchars(substr($row['locationName'], 0, 22)) ?></span>
                  <span><?= intval($row['cnt']) ?></span>
                </div>
                <div class="bar-track">
                  <div class="bar-fill" style="width:<?= $pct ?>%;background:#1a7a3e"></div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- DONUT (from DB counts) -->
      <div class="panel">
        <div class="panel-header"><div class="panel-title">Report Status Split</div></div>
        <?php
          $total = max(1, intval($counts['total']));
          $pPending    = round(($tab_counts['pending']    ?? 0) / $total * 100);
          $pResolved   = round(($tab_counts['resolved']   ?? 0) / $total * 100);
          $pInprogress = round(($tab_counts['inprogress'] ?? 0) / $total * 100);
          $pRejected   = round(($tab_counts['rejected']   ?? 0) / $total * 100);
          $circ = 226; // 2πr where r=36
          $dPending    = round($pPending    / 100 * $circ);
          $dResolved   = round($pResolved   / 100 * $circ);
          $dInprogress = round($pInprogress / 100 * $circ);
          $dRejected   = round($pRejected   / 100 * $circ);
          $off1 = 0;
          $off2 = -$dPending;
          $off3 = -($dPending + $dResolved);
          $off4 = -($dPending + $dResolved + $dInprogress);
        ?>
        <div class="donut-wrap">
          <svg width="90" height="90" viewBox="0 0 90 90">
            <circle cx="45" cy="45" r="36" fill="none" stroke="#e8f5ee" stroke-width="14"/>
            <circle cx="45" cy="45" r="36" fill="none" stroke="#b07d00" stroke-width="14"
              stroke-dasharray="<?= $dPending ?> <?= $circ - $dPending ?>"
              stroke-dashoffset="<?= $off1 ?>" transform="rotate(-90 45 45)"/>
            <circle cx="45" cy="45" r="36" fill="none" stroke="#1a7a3e" stroke-width="14"
              stroke-dasharray="<?= $dResolved ?> <?= $circ - $dResolved ?>"
              stroke-dashoffset="<?= $off2 ?>" transform="rotate(-90 45 45)"/>
            <circle cx="45" cy="45" r="36" fill="none" stroke="#5aaa76" stroke-width="14"
              stroke-dasharray="<?= $dInprogress ?> <?= $circ - $dInprogress ?>"
              stroke-dashoffset="<?= $off3 ?>" transform="rotate(-90 45 45)"/>
            <circle cx="45" cy="45" r="36" fill="none" stroke="#c0392b" stroke-width="14"
              stroke-dasharray="<?= $dRejected ?> <?= $circ - $dRejected ?>"
              stroke-dashoffset="<?= $off4 ?>" transform="rotate(-90 45 45)"/>
            <text x="45" y="49" text-anchor="middle" fill="#1a2d22" font-size="13" font-family="Syne,sans-serif" font-weight="700"><?= $total ?></text>
            <text x="45" y="58" text-anchor="middle" fill="#6b8f76" font-size="7" font-family="DM Mono,monospace">TOTAL</text>
          </svg>
          <div class="donut-legend">
            <div class="legend-item"><div class="legend-dot" style="background:#1a7a3e"></div>Resolved<span class="legend-pct"><?= $pResolved ?>%</span></div>
            <div class="legend-item"><div class="legend-dot" style="background:#b07d00"></div>Pending<span class="legend-pct"><?= $pPending ?>%</span></div>
            <div class="legend-item"><div class="legend-dot" style="background:#5aaa76"></div>In-Progress<span class="legend-pct"><?= $pInprogress ?>%</span></div>
            <div class="legend-item"><div class="legend-dot" style="background:#c0392b"></div>Rejected<span class="legend-pct"><?= $pRejected ?>%</span></div>
          </div>
        </div>
      </div>

      <!-- RECENT ACTIVITY (from Notifications) -->
      <div class="panel">
        <div class="panel-header"><div class="panel-title">Recent Activity</div></div>
        <div class="timeline">
          <?php if (empty($activity)): ?>
            <div class="empty-state">No recent activity.</div>
          <?php else: ?>
            <?php foreach ($activity as $a):
              $dotClass = match($a['status']) { 'resolved' => 'green', 'inprogress' => 'orange', default => 'blue' };
              $icon = match($a['status']) { 'resolved' => '✅', 'inprogress' => '🔄', 'rejected' => '✕', default => '📋' };
              $when = date('M d · h:i A', strtotime($a['timestamp']));
            ?>
            <div class="tl-item">
              <div class="tl-dot <?= $dotClass ?>"><?= $icon ?></div>
              <div class="tl-body">
                <div class="tl-action"><?= htmlspecialchars(substr($a['message'], 0, 50)) ?></div>
                <div class="tl-meta"><?= htmlspecialchars($a['firstName'].' '.$a['lastName']) ?> · <?= $when ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ── SELECT REPORT ─────────────────────────────────────────────
function selectReport(el) {
  document.querySelectorAll('.report-row').forEach(r => r.classList.remove('selected'));
  el.classList.add('selected');

  const d = el.dataset;
  document.getElementById('detailId').textContent       = '#RPT-' + String(d.id).padStart(4,'0');
  document.getElementById('detailReporter').textContent = d.reporter;
  document.getElementById('detailLoc').textContent      = d.loc;
  document.getElementById('detailDate').textContent     = d.date;
  document.getElementById('detailDesc').textContent     = d.desc;
  document.getElementById('statusSelect').value         = d.status;
  document.getElementById('detailReportId').value       = d.id;
  document.getElementById('detailCoords').textContent   = d.lat ? d.lat + '° N, ' + d.lng + '° E' : 'No coordinates';

  const badge = document.getElementById('detailBadge');
  badge.textContent = statusLabel(d.status);
  badge.className   = 'status-pill ' + statusClass(d.status);

  const imgEl = document.getElementById('detailImg');
  const placeholder = document.getElementById('detailImgPlaceholder');
  if (d.img) {
    if (!imgEl) {
      const wrap = document.getElementById('detailImgWrap');
      const img = document.createElement('img');
      img.id = 'detailImg'; img.alt = 'Proof';
      img.src = d.img;
      wrap.prepend(img);
      if (placeholder) placeholder.style.display = 'none';
    } else { imgEl.src = d.img; imgEl.style.display = ''; if (placeholder) placeholder.style.display = 'none'; }
  } else {
    if (imgEl) imgEl.style.display = 'none';
    if (placeholder) placeholder.style.display = '';
  }
}

// ── SAVE VIA AJAX ─────────────────────────────────────────────
function saveAction() {
  const reportId = document.getElementById('detailReportId').value;
  const status   = document.getElementById('statusSelect').value;
  if (!reportId) return;

  const form = new FormData();
  form.append('action',    'update_status');
  form.append('report_id', reportId);
  form.append('status',    status);

  fetch('admin_dashboard.php', { method: 'POST', body: form })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('✅ Status updated — resident notified');
        // Update badge + row pill
        const badge = document.getElementById('detailBadge');
        badge.textContent = statusLabel(status);
        badge.className   = 'status-pill ' + statusClass(status);
        const pill = document.querySelector('.report-row.selected .status-pill');
        if (pill) { pill.textContent = statusLabel(status); pill.className = 'status-pill ' + statusClass(status); }
        // Update data attribute
        const row = document.querySelector('.report-row.selected');
        if (row) row.dataset.status = status;
      } else {
        showToast('❌ Error: ' + data.message);
      }
    })
    .catch(() => showToast('❌ Network error. Please try again.'));
}

function rejectReport() {
  document.getElementById('statusSelect').value = 'rejected';
  saveAction();
}

// ── HELPERS ───────────────────────────────────────────────────
function statusClass(s) {
  const map = { verified:'verified', resolved:'resolved', rejected:'rejected', inprogress:'verified' };
  return map[s] || 'pending';
}
function statusLabel(s) {
  const map = { inprogress:'In-Progress', pending:'Pending', verified:'Verified', resolved:'Resolved', rejected:'Rejected' };
  return map[s] || s.charAt(0).toUpperCase() + s.slice(1);
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

// ── HEATMAP (static visual) ───────────────────────────────────
(function() {
  const grid = document.getElementById('heatmapGrid');
  const cells = ['l0','l1','l0','l2','l1','l0','l1','l2','l1','l3','l2','l0','l1','l2','l4','l3','l2','l4','l3','l1','l0','lh','l4','l3','l2','l4','l2','l1','l2','l3','l4','l1','l3','l3','l0','l1','l0','l2','l0','l1','l2','l1','l0','l1','l1','l0','l0','l1','l0'];
  cells.forEach(cls => {
    const c = document.createElement('div');
    c.className = 'heatmap-cell ' + cls;
    c.title = 'Zone block';
    grid.appendChild(c);
  });
})();

// ── PRE-SELECT FIRST ROW ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const first = document.querySelector('.report-row');
  if (first) {
    // Status dropdown pre-select
    document.getElementById('statusSelect').value = first.dataset.status || 'pending';
  }
});
</script>
</body>
</html>
