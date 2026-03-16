<?php

session_start();

// Only residents can access this page (same pattern as admin_dashboard.php)
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && !in_array(strtolower($_SESSION['role']), ['resident']))) {
    header('Location: login.php');
    exit();
}

$host   = 'localhost';
$dbname = 'pin_and_throw';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$resident_id = (int) $_SESSION['user_id'];

// ── Mark notification as read (AJAX) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    header('Content-Type: application/json');
    $notification_id = (int) ($_POST['notification_id'] ?? 0);
    if ($notification_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification']);
        exit();
    }
    $stmt = $pdo->prepare("UPDATE Notifications SET isRead = 1 WHERE notification_ID = ? AND user_ID = ?");
    $stmt->execute([$notification_id, $resident_id]);
    echo json_encode(['success' => true]);
    exit();
}

// ── Counts for THIS resident's reports only ──────────────────────────────
$counts = $pdo->prepare("
    SELECT
        SUM(status = 'pending')    AS pending,
        SUM(status = 'inprogress') AS inprogress,
        SUM(status = 'resolved')   AS resolved,
        COUNT(*)                   AS total
    FROM Reports
    WHERE resident_ID = ?
");
$counts->execute([$resident_id]);
$counts = $counts->fetch(PDO::FETCH_ASSOC);

// ── Tab filter for My Reports ───────────────────────────────────────────
$active_tab = $_GET['tab'] ?? 'all';
$allowed_tabs = ['all', 'pending', 'verified', 'inprogress', 'resolved', 'rejected'];
if (!in_array($active_tab, $allowed_tabs)) $active_tab = 'all';

// ── My reports list (this resident only) ──────────────────────────────────
if ($active_tab === 'all') {
    $stmt = $pdo->prepare("
        SELECT r.report_ID, r.description, r.imageUrl, r.status, r.timestamp,
               l.locationName, l.latitude, l.longitude
        FROM Reports r
        LEFT JOIN Locations l ON l.report_ID = r.report_ID
        WHERE r.resident_ID = ?
        ORDER BY r.timestamp DESC
        LIMIT 50
    ");
    $stmt->execute([$resident_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT r.report_ID, r.description, r.imageUrl, r.status, r.timestamp,
               l.locationName, l.latitude, l.longitude
        FROM Reports r
        LEFT JOIN Locations l ON l.report_ID = r.report_ID
        WHERE r.resident_ID = ? AND r.status = ?
        ORDER BY r.timestamp DESC
        LIMIT 50
    ");
    $stmt->execute([$resident_id, $active_tab]);
}
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Tab counts for this resident's reports ───────────────────────────────
$tab_counts_stmt = $pdo->prepare("
    SELECT status, COUNT(*) AS cnt FROM Reports WHERE resident_ID = ? GROUP BY status
");
$tab_counts_stmt->execute([$resident_id]);
$tab_counts = $tab_counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Notifications for this user only ─────────────────────────────────────
$notif_stmt = $pdo->prepare("
    SELECT n.notification_ID, n.report_ID, n.message, n.isRead,
           r.status, r.description AS report_desc, r.timestamp AS report_ts
    FROM Notifications n
    JOIN Reports r ON r.report_ID = n.report_ID
    WHERE n.user_ID = ?
    ORDER BY n.notification_ID DESC
    LIMIT 20
");
$notif_stmt->execute([$resident_id]);
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_count = 0;
foreach ($notifications as $n) {
    if (empty($n['isRead'])) $unread_count++;
}

// ── Current user info for sidebar ────────────────────────────────────────
$user_stmt = $pdo->prepare("SELECT firstName, lastName FROM Users WHERE user_ID = ?");
$user_stmt->execute([$resident_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_initials = $user ? strtoupper(substr($user['firstName'], 0, 1) . substr($user['lastName'], 0, 1)) : 'U';
$user_name = $user ? htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) : 'Resident';

// Helpers (same as admin_dashboard.php)
function statusClass($s) {
    return match($s) {
        'verified'   => 'verified',
        'resolved'   => 'resolved',
        'rejected'   => 'rejected',
        'inprogress' => 'verified',
        default      => 'pending',
    };
}
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
<title>Pin and Throw — My Dashboard</title>
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
  .user-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: rgba(255,255,255,0.12); border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); }
  .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 12px; color: #1a7a3e; flex-shrink: 0; }
  .user-info .name { font-size: 12.5px; font-weight: 500; color: #fff; }
  .user-info .role { font-size: 10px; color: rgba(255,255,255,0.55); font-family: 'DM Mono', monospace; }
  .logout-link { display: block; text-align: center; margin-top: 8px; font-size: 11px; color: rgba(255,255,255,0.5); font-family: 'DM Mono', monospace; text-decoration: none; }
  .logout-link:hover { color: #fff; }

  .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
  .topbar { display: flex; align-items: center; justify-content: space-between; padding: 16px 32px; border-bottom: 1px solid var(--border); background: #fff; position: sticky; top: 0; z-index: 50; box-shadow: 0 1px 4px rgba(26,122,62,0.07); }
  .topbar-title { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; }
  .topbar-subtitle { font-size: 12px; color: var(--muted); font-family: 'DM Mono', monospace; }
  .topbar-right { display: flex; align-items: center; gap: 12px; }
  .btn-icon { width: 36px; height: 36px; border-radius: 8px; background: #f0f7f2; border: 1px solid var(--border); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all .15s; position: relative; text-decoration: none; color: inherit; }
  .btn-icon:hover { border-color: var(--accent); background: var(--accent-dim); }
  .notif-dot { position: absolute; top: 6px; right: 6px; width: 7px; height: 7px; background: var(--warn); border-radius: 50%; border: 1.5px solid #fff; }
  .btn-report { display: inline-flex; align-items: center; gap: 8px; background: #1a7a3e; color: #fff; border: none; border-radius: 8px; padding: 10px 18px; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 13px; cursor: pointer; text-decoration: none; transition: opacity .15s, transform .1s; }
  .btn-report:hover { opacity: .9; transform: translateY(-1px); color: #fff; }

  .content { padding: 28px 32px; flex: 1; }

  .stat-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
  .stat-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px 22px; position: relative; overflow: hidden; }
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

  .grid-2 { display:grid; grid-template-columns:1fr 340px; gap:20px; margin-bottom:24px; }

  .panel { background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; }
  .panel-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
  .panel-title { font-family:'Syne',sans-serif; font-size:14px; font-weight:700; display:flex; align-items:center; gap:8px; }
  .panel-title .dot { width:8px; height:8px; border-radius:50%; }
  .panel-action { font-size:11px; color:var(--accent); font-family:'DM Mono',monospace; font-weight:500; text-decoration: none; }
  .panel-action:hover { text-decoration: underline; }

  .tab-bar { display:flex; border-bottom:1px solid var(--border); padding:0 20px; flex-wrap: wrap; }
  .tab { font-size:12px; font-weight:500; padding:11px 16px; cursor:pointer; color:var(--muted); border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .15s; text-decoration:none; display:flex; align-items:center; gap:6px; }
  .tab.active { color:#1a7a3e; border-bottom-color:#1a7a3e; }
  .tab:hover:not(.active) { color:var(--text); }
  .tab .count { background:#e8f5ee; border-radius:20px; font-family:'DM Mono',monospace; font-size:10px; padding:0 6px; line-height:16px; }
  .tab.active .count { background:rgba(26,122,62,.15); color:#1a7a3e; }

  .report-list { overflow-y:auto; max-height:420px; }
  .report-row { display:flex; align-items:center; padding:13px 20px; border-bottom:1px solid var(--border); gap:14px; cursor:pointer; transition: background .12s; }
  .report-row:last-child { border-bottom:none; }
  .report-row:hover { background:rgba(26,122,62,.03); }
  .report-row.selected { background:var(--accent-dim); border-left:3px solid var(--accent); }
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

  .detail-body { padding:20px; overflow-y:auto; max-height:420px; }
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

  .notif-list { max-height:400px; overflow-y:auto; }
  .notif-item { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; gap:12px; align-items:flex-start; }
  .notif-item:last-child { border-bottom:none; }
  .notif-item.unread { background:rgba(26,122,62,.04); }
  .notif-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; background:rgba(26,122,62,.12); }
  .notif-body { flex:1; min-width:0; }
  .notif-msg { font-size:12.5px; margin-bottom:4px; }
  .notif-meta { font-size:11px; color:var(--muted); font-family:'DM Mono',monospace; }
  .notif-item.unread .notif-msg { font-weight: 500; }

  ::-webkit-scrollbar { width:4px; }
  ::-webkit-scrollbar-thumb { background:#c8dece; border-radius:4px; }
  .empty-state { padding:32px 20px; text-align:center; color:var(--muted); font-size:13px; }
  .toast { position:fixed; bottom:24px; right:24px; background:#fff; border:1px solid #1a7a3e; border-radius:10px; padding:12px 18px; font-size:13px; color:#1a7a3e; font-family:'DM Mono',monospace; z-index:999; opacity:0; transform:translateY(10px); transition:all .3s; pointer-events:none; box-shadow:0 4px 20px rgba(26,122,62,.15); }
  .toast.show { opacity:1; transform:translateY(0); }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="brand"><div class="logo-pin"></div> Pin &amp; Throw</div>
    <div class="sub">Resident Dashboard</div>
  </div>
  <nav class="nav-section">
    <div class="nav-label">Main</div>
    <a class="nav-item active" href="user_dashboard.php"><span class="nav-icon">🏠</span> My Dashboard</a>
    <a class="nav-item" href="report.php"><span class="nav-icon">📍</span> Report Waste Now</a>
    <a class="nav-item" href="user_dashboard.php?tab=all"><span class="nav-icon">📋</span> My Reports</a>
    <a class="nav-item" href="#notifications"><span class="nav-icon">🔔</span> Notifications
      <?php if ($unread_count > 0): ?><span class="nav-badge"><?= $unread_count ?></span><?php endif; ?>
    </a>
    <div class="nav-group-gap"></div>
    <div class="nav-label">Account</div>
    <a class="nav-item" href="logout.php"><span class="nav-icon">🚪</span> Log out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= $user_initials ?></div>
      <div class="user-info">
        <div class="name"><?= $user_name ?></div>
        <div class="role">RESIDENT</div>
      </div>
    </div>
    <a class="logout-link" href="logout.php">← Log out</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title">My Dashboard</div>
      <div class="topbar-subtitle">Track your reports · Brgy. Pio Del Pilar, Makati City</div>
    </div>
    <div class="topbar-right">
      <a class="btn-report" href="report.php">📍 Report Waste Now</a>
      <a class="btn-icon" href="#notifications" title="Notifications">🔔
        <?php if ($unread_count > 0): ?><div class="notif-dot"></div><?php endif; ?>
      </a>
    </div>
  </div>

  <div class="content">

    <div class="stat-grid">
      <div class="stat-card orange">
        <div class="stat-icon">📋</div>
        <div class="stat-label">Pending</div>
        <div class="stat-val"><?= intval($counts['pending'] ?? 0) ?></div>
        <div class="stat-delta">Awaiting review</div>
      </div>
      <div class="stat-card blue">
        <div class="stat-icon">🔄</div>
        <div class="stat-label">In-Progress</div>
        <div class="stat-val"><?= intval($counts['inprogress'] ?? 0) ?></div>
        <div class="stat-delta">Being handled</div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">Resolved</div>
        <div class="stat-val"><?= intval($counts['resolved'] ?? 0) ?></div>
        <div class="stat-delta">Cleaned up</div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-icon">📊</div>
        <div class="stat-label">Total Reports</div>
        <div class="stat-val"><?= intval($counts['total'] ?? 0) ?></div>
        <div class="stat-delta">All time</div>
      </div>
    </div>

    <div class="grid-2">
      <!-- MY REPORTS LIST + DETAIL -->
      <div class="panel" style="display:flex;flex-direction:column;">
        <div class="tab-bar">
          <a class="tab <?= $active_tab === 'all' ? 'active' : '' ?>" href="?tab=all">All <span class="count"><?= intval($counts['total'] ?? 0) ?></span></a>
          <a class="tab <?= $active_tab === 'pending' ? 'active' : '' ?>" href="?tab=pending">Pending <span class="count"><?= intval($tab_counts['pending'] ?? 0) ?></span></a>
          <a class="tab <?= $active_tab === 'inprogress' ? 'active' : '' ?>" href="?tab=inprogress">In-Progress <span class="count"><?= intval($tab_counts['inprogress'] ?? 0) ?></span></a>
          <a class="tab <?= $active_tab === 'resolved' ? 'active' : '' ?>" href="?tab=resolved">Resolved <span class="count"><?= intval($tab_counts['resolved'] ?? 0) ?></span></a>
          <a class="tab <?= $active_tab === 'rejected' ? 'active' : '' ?>" href="?tab=rejected">Rejected <span class="count"><?= intval($tab_counts['rejected'] ?? 0) ?></span></a>
        </div>

        <div style="display:flex;flex:1;min-height:400px;">
          <div style="width:55%;border-right:1px solid var(--border);">
            <div class="report-list" id="reportList">
              <?php if (empty($reports)): ?>
                <div class="empty-state">No reports yet. <a href="report.php" style="color:var(--accent);">Report waste now</a>.</div>
              <?php else: ?>
                <?php foreach ($reports as $i => $r):
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
                     data-loc="<?= $loc ?>"
                     data-date="<?= $date ?>"
                     data-desc="<?= $desc ?>"
                     data-status="<?= htmlspecialchars($r['status']) ?>"
                     data-img="<?= $img ?>"
                     data-lat="<?= $lat ?>"
                     data-lng="<?= $lng ?>">
                  <div class="report-thumb">
                    <?php if ($img): ?><img src="<?= $img ?>" alt="Proof"><?php else: ?>🗑️<?php endif; ?>
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

          <div style="flex:1;display:flex;flex-direction:column;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
              <div id="detailId" style="font-size:12px;font-family:'DM Mono',monospace;color:var(--muted);">
                <?php if (!empty($reports)): ?>#RPT-<?= str_pad($reports[0]['report_ID'], 4, '0', STR_PAD_LEFT) ?><?php endif; ?>
              </div>
              <div class="status-pill <?= !empty($reports) ? statusClass($reports[0]['status']) : 'pending' ?>" id="detailBadge">
                <?= !empty($reports) ? statusLabel($reports[0]['status']) : '—' ?>
              </div>
            </div>
            <div class="detail-body">
              <div class="detail-img" id="detailImgWrap">
                <?php if (!empty($reports[0]['imageUrl'])): ?>
                  <img id="detailImg" src="<?= htmlspecialchars($reports[0]['imageUrl']) ?>" alt="Proof">
                <?php else: ?>
                  <span id="detailImgPlaceholder">🗑️</span>
                <?php endif; ?>
                <div class="detail-img-label">IMAGE PROOF</div>
              </div>
              <div class="detail-map">
                <div class="detail-map-grid"></div>
                <div class="detail-map-dot"></div>
                <div class="detail-map-coords" id="detailCoords">
                  <?= !empty($reports[0]['latitude']) ? $reports[0]['latitude'].'° N, '.$reports[0]['longitude'].'° E' : 'No coordinates' ?>
                </div>
              </div>
              <div class="detail-row">
                <span class="detail-key">Location</span>
                <span class="detail-val" id="detailLoc"><?= !empty($reports) ? htmlspecialchars($reports[0]['locationName'] ?? '—') : '—' ?></span>
              </div>
              <div class="detail-row">
                <span class="detail-key">Submitted</span>
                <span class="detail-val mono" id="detailDate"><?= !empty($reports) ? date('M d, Y · h:i A', strtotime($reports[0]['timestamp'])) : '—' ?></span>
              </div>
              <div class="detail-divider"></div>
              <div class="desc-text" id="detailDesc"><?= !empty($reports) ? htmlspecialchars($reports[0]['description']) : 'Select a report to view details.' ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- NOTIFICATIONS -->
      <div class="panel" id="notifications">
        <div class="panel-header">
          <div class="panel-title"><div class="dot" style="background:var(--accent)"></div>Updates from Barangay</div>
          <?php if ($unread_count > 0): ?><span class="panel-action"><?= $unread_count ?> unread</span><?php endif; ?>
        </div>
        <div class="notif-list">
          <?php if (empty($notifications)): ?>
            <div class="empty-state">No notifications yet.</div>
          <?php else: ?>
            <?php
            foreach ($notifications as $n):
              $ts = $n['report_ts'] ?? null;
              $time_str = $ts ? date('M d · h:i A', strtotime($ts)) : '—';
              $unread = empty($n['isRead']);
            ?>
            <div class="notif-item <?= $unread ? 'unread' : '' ?>" data-notif-id="<?= (int)$n['notification_ID'] ?>">
              <div class="notif-icon"><?= $n['status'] === 'resolved' ? '✅' : ($n['status'] === 'inprogress' ? '🔄' : '📋') ?></div>
              <div class="notif-body">
                <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                <div class="notif-meta">#RPT-<?= str_pad($n['report_ID'], 4, '0', STR_PAD_LEFT) ?> · <?= $time_str ?></div>
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
function selectReport(el) {
  document.querySelectorAll('.report-row').forEach(r => r.classList.remove('selected'));
  el.classList.add('selected');
  var d = el.dataset;
  document.getElementById('detailId').textContent     = '#RPT-' + String(d.id).padStart(4,'0');
  document.getElementById('detailLoc').textContent    = d.loc;
  document.getElementById('detailDate').textContent   = d.date;
  document.getElementById('detailDesc').textContent   = d.desc;
  document.getElementById('detailBadge').textContent  = statusLabel(d.status);
  document.getElementById('detailBadge').className    = 'status-pill ' + statusClass(d.status);
  document.getElementById('detailCoords').textContent = d.lat ? d.lat + '° N, ' + d.lng + '° E' : 'No coordinates';
  var imgEl = document.getElementById('detailImg');
  var placeholder = document.getElementById('detailImgPlaceholder');
  if (d.img) {
    if (!imgEl) {
      var wrap = document.getElementById('detailImgWrap');
      var img = document.createElement('img');
      img.id = 'detailImg'; img.alt = 'Proof'; img.src = d.img;
      wrap.prepend(img);
      if (placeholder) placeholder.style.display = 'none';
    } else { imgEl.src = d.img; imgEl.style.display = ''; if (placeholder) placeholder.style.display = 'none'; }
  } else {
    if (imgEl) imgEl.style.display = 'none';
    if (placeholder) placeholder.style.display = '';
  }
}
function statusClass(s) {
  var map = { verified:'verified', resolved:'resolved', rejected:'rejected', inprogress:'verified' };
  return map[s] || 'pending';
}
function statusLabel(s) {
  var map = { inprogress:'In-Progress', pending:'Pending', verified:'Verified', resolved:'Resolved', rejected:'Rejected' };
  return map[s] || (s ? s.charAt(0).toUpperCase() + s.slice(1) : '—');
}
</script>
</body>
</html>
