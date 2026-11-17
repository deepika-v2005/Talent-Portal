<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); exit();
}

$conn = new mysqli("localhost", "root", "", "skillconnect");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── SIDEBAR VARS ──
$profile      = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending_apps = (int)$conn->query("SELECT COUNT(*) as c FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.client_id=$user_id AND a.status='pending'")->fetch_assoc()['c'];
$unread_notif = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs  = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$avatar_letter = strtoupper(substr($user_name, 0, 1));
$first_name    = explode(' ', $user_name)[0];

$success = ''; $error = '';

// ── HANDLE STATUS TOGGLE (open/closed/paused) ──
if (isset($_GET['toggle'])) {
    $jid    = (int)$_GET['toggle'];
    $status = $conn->real_escape_string($_GET['s'] ?? 'open');
    if (in_array($status, ['open','closed','paused'])) {
        $conn->query("UPDATE jobs SET status='$status' WHERE id=$jid AND client_id=$user_id");
    }
    header("Location: manage_jobs.php"); exit();
}

// ── HANDLE DELETE ──
if (isset($_GET['delete'])) {
    $jid = (int)$_GET['delete'];
    $conn->query("DELETE FROM jobs WHERE id=$jid AND client_id=$user_id");
    header("Location: manage_jobs.php?deleted=1"); exit();
}

// ── HANDLE INLINE EDIT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_job'])) {
    $jid    = (int)$_POST['job_id'];
    $title  = $conn->real_escape_string(trim($_POST['title']));
    $desc   = $conn->real_escape_string(trim($_POST['description']));
    $skills = $conn->real_escape_string(trim($_POST['skills_required']));
    $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
    $jtype  = in_array($_POST['job_type'], ['Full-time','Part-time','Freelance','Contract']) ? $_POST['job_type'] : 'Full-time';
    $budget_val = $budget ? $budget : "NULL";
    $conn->query("UPDATE jobs SET title='$title', description='$desc',
                  skills_required='$skills', budget=$budget_val, job_type='$jtype'
                  WHERE id=$jid AND client_id=$user_id");
    $success = 'Job updated successfully!';
}

// ── FILTER ──
$filter = $_GET['status'] ?? 'all';
$where  = "client_id=$user_id";
if ($filter !== 'all') $where .= " AND status='" . $conn->real_escape_string($filter) . "'";

// ── FETCH JOBS ──
$jobs = $conn->query("
    SELECT j.*,
        (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS app_count,
        (SELECT COUNT(*) FROM applications WHERE job_id=j.id AND status='pending') AS pending_count,
        (SELECT COUNT(*) FROM applications WHERE job_id=j.id AND status='accepted') AS hired_count
    FROM jobs j
    WHERE $where
    ORDER BY j.created_at DESC
");

$total_all    = $conn->query("SELECT COUNT(*) as c FROM jobs WHERE client_id=$user_id")->fetch_assoc()['c'];
$total_open   = $conn->query("SELECT COUNT(*) as c FROM jobs WHERE client_id=$user_id AND status='open'")->fetch_assoc()['c'];
$total_closed = $conn->query("SELECT COUNT(*) as c FROM jobs WHERE client_id=$user_id AND status='closed'")->fetch_assoc()['c'];
$total_paused = $conn->query("SELECT COUNT(*) as c FROM jobs WHERE client_id=$user_id AND status='paused'")->fetch_assoc()['c'];

// ── FETCH SINGLE JOB FOR EDIT MODAL ──
$edit_job = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_job = $conn->query("SELECT * FROM jobs WHERE id=$eid AND client_id=$user_id")->fetch_assoc();
}

$conn->close();

$job_colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706','#dc2626','#059669'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,viewport-fit=cover"/>
  <title>Manage Jobs – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{-webkit-text-size-adjust:100%;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;
      --blue:#1d4ed8;--blue-lt:#eff6ff;
      --border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;
      --red:#dc2626;--red-lt:#fef2f2;
      --yellow:#d97706;--yellow-lt:#fef3c7;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --shadow-md:0 6px 24px rgba(11,31,58,0.10);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;overflow-x:hidden;width:100%;}
    img{max-width:100%;}
    .sidebar{width:var(--sidebar);height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}
    .sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}
    .sb-ava{width:40px;height:40px;border-radius:11px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:400;overflow:hidden;flex-shrink:0;}
    .sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
    .sb-uname{font-size:.86rem;font-weight:400;color:#fff;} .sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
    .sb-nav{flex:1;padding:10px 0;}
    .nav-section{font-size:.62rem;font-weight:400;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:400;padding:2px 6px;border-radius:100px;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}
    .sb-logout a{display:flex;align-items:center;justify-content:center;gap:9px;padding:10px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}
    .sb-logout a:hover{background:rgba(220,38,38,.2);}
    .main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-width:0;width:100%;}
    .topbar{height:60px;padding:0 26px;background:rgba(245,243,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;min-width:0;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;}
    .tb-crumb{font-size:.85rem;color:var(--muted);} .tb-crumb strong{color:var(--ink);font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;flex-shrink:0;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;flex-shrink:0;}
    .tb-btn:hover{background:var(--cream2);}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:400;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--cream);padding:0 2px;}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;text-decoration:none;overflow:hidden;flex-shrink:0;}
    .tb-ava img{width:35px;height:35px;object-fit:cover;}
    .content{padding:24px 26px;flex:1;min-width:0;max-width:100%;}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}

    /* PAGE */
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;}
    .page-title{font-family:var(--font-h);font-size:1.5rem;font-weight:400;color:var(--ink);}
    .btn-post{padding:9px 20px;background:var(--teal);color:#fff;font-family:var(--font-b);font-size:.84rem;font-weight:400;border:none;border-radius:100px;cursor:pointer;text-decoration:none;box-shadow:0 4px 14px rgba(124,58,237,.25);transition:background .2s;white-space:nowrap;}
    .btn-post:hover{background:#0b6b58;}

    /* FILTER TABS */
    .filter-tabs{display:flex;gap:5px;margin-bottom:20px;background:var(--white);border:1px solid var(--border);border-radius:100px;padding:4px;width:fit-content;max-width:100%;}
    .ft{padding:7px 18px;border-radius:100px;font-size:.82rem;font-weight:400;color:var(--muted);text-decoration:none;transition:all .2s;white-space:nowrap;}
    .ft:hover{color:var(--ink);}
    .ft.active{background:var(--teal);color:#fff;}

    /* ALERT */
    .alert{border-radius:10px;padding:12px 16px;font-size:.86rem;margin-bottom:18px;display:flex;align-items:center;gap:9px;}
    .alert.success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}

    /* JOB CARD */
    .job-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:14px;box-shadow:var(--shadow);transition:border-color .2s;max-width:100%;overflow:hidden;}
    .job-card:hover{border-color:rgba(124,58,237,.3);}
    .jc-top{display:flex;align-items:flex-start;gap:14px;margin-bottom:12px;flex-wrap:wrap;}
    .jc-logo{width:46px;height:46px;border-radius:13px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.1rem;font-weight:400;color:#fff;}
    .jc-title{font-family:var(--font-h);font-size:1.05rem;font-weight:400;color:var(--ink);margin-bottom:3px;overflow-wrap:break-word;word-break:break-word;}
    .jc-meta{font-size:.76rem;color:var(--muted);overflow-wrap:break-word;}
    .jc-top > div{min-width:0;}
    .status-pill{font-size:.68rem;font-weight:400;padding:3px 10px;border-radius:100px;white-space:nowrap;}
    .pill-open{background:var(--green-lt);color:var(--green);border:1px solid #bbf7d0;}
    .pill-closed{background:var(--red-lt);color:var(--red);}
    .pill-paused{background:var(--yellow-lt);color:var(--yellow);}

    /* STATS MINI */
    .jc-stats{display:flex;gap:20px;margin:12px 0;padding:12px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);flex-wrap:wrap;}
    .jcs{text-align:center;}
    .jcs-num{font-family:var(--font-h);font-size:1.2rem;font-weight:400;}
    .jcs-lbl{font-size:.67rem;color:var(--muted);font-weight:400;}

    /* TAGS */
    .jc-tags{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px;}
    .jc-tag{font-size:.7rem;font-weight:400;padding:2px 8px;border-radius:5px;background:var(--teal-lt);color:var(--teal);}

    /* ACTIONS */
    .jc-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
    .act-btn{padding:7px 14px;border-radius:100px;font-family:var(--font-b);font-size:.78rem;font-weight:400;text-decoration:none;border:none;cursor:pointer;transition:all .18s;white-space:nowrap;display:inline-flex;align-items:center;justify-content:center;}
    .act-view{background:var(--teal-lt);color:var(--teal);}
    .act-view:hover{background:rgba(124,58,237,.15);}
    .act-edit{background:var(--blue-lt);color:var(--blue);}
    .act-edit:hover{background:rgba(29,78,216,.15);}
    .act-pause{background:var(--yellow-lt);color:var(--yellow);}
    .act-pause:hover{background:rgba(217,119,6,.15);}
    .act-open{background:var(--green-lt);color:var(--green);}
    .act-open:hover{background:rgba(22,163,74,.15);}
    .act-close{background:var(--red-lt);color:var(--red);}
    .act-close:hover{background:rgba(220,38,38,.15);}
    .act-delete{background:var(--red-lt);color:var(--red);border:1px solid #fecaca;}
    .act-delete:hover{background:rgba(220,38,38,.15);}

    /* EMPTY */
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
    .empty-state .ei{font-size:2.8rem;margin-bottom:12px;}
    .empty-state h3{font-family:var(--font-h);font-size:1.2rem;color:var(--ink2);margin-bottom:8px;}

    /* MODAL */
    .modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;align-items:center;justify-content:center;padding:16px;}
    .modal-backdrop.open{display:flex;}
    .modal{background:var(--white);border-radius:var(--radius);padding:28px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);}
    .modal-title{font-family:var(--font-h);font-size:1.15rem;font-weight:400;margin-bottom:20px;}
    .fg-label{font-size:.82rem;font-weight:400;color:var(--ink2);margin-bottom:6px;display:block;}
    .fg-input,.fg-textarea,.fg-select{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-family:var(--font-b);font-size:.9rem;background:var(--cream);color:var(--ink);outline:none;transition:border-color .2s;}
    .fg-input:focus,.fg-textarea:focus,.fg-select:focus{border-color:var(--teal);}
    .fg-textarea{min-height:100px;resize:vertical;}
    .mg{margin-bottom:14px;}
    .edit-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;}

    /* ===== RESPONSIVE ===== */
    @media(max-width:1024px){
      :root{--sidebar:210px;}
    }

    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);width:240px;}
      .sidebar.open{transform:translateX(0);}
      .main{margin-left:0;}
      .content{padding:16px;}
      .hamburger{display:flex;}
      .edit-grid-2{grid-template-columns:1fr;}
      .jc-stats{gap:14px;justify-content:space-between;}
      .jc-actions{gap:6px;}
      .act-btn{padding:7px 11px;font-size:.74rem;}
      .page-header{gap:10px;}
      .page-header .btn-post{flex-shrink:0;}
      .filter-tabs{width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
      .topbar{padding:0 14px;}
      .tb-crumb{max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
      .btn-post{padding:8px 14px;font-size:.78rem;}
      .modal{padding:20px;}
      .jc-top{gap:10px;}
    }

    @media(max-width:480px){
      .page-title{font-size:1.25rem;}
      .page-header{flex-direction:column;align-items:flex-start;}
      .btn-post{width:100%;text-align:center;}
      .job-card{padding:14px;}
      .jc-logo{width:38px;height:38px;font-size:.95rem;}
      .jc-title{font-size:.95rem;}
      .jc-stats{justify-content:flex-start;gap:16px;}
      .jc-actions{gap:5px;}
      .act-btn{flex:1 1 calc(50% - 5px);text-align:center;padding:8px 6px;font-size:.72rem;}
      .filter-tabs{padding:3px;}
      .ft{padding:6px 13px;font-size:.76rem;}
      .status-pill{margin-left:auto;}
      .jc-top{flex-wrap:wrap;}
    }

    @media(max-width:360px){
      .content{padding:12px;}
      .act-btn{flex:1 1 100%;}
      .jc-stats{gap:10px;}
    }


/* ============ AUTO-GENERATED DARK MODE ============ */
body.dark-mode{background:#0B1220;color:#E2E8F0;}
body.dark-mode .topbar{background:rgba(11,18,32,.96);border-bottom:1px solid #1E293B;}
body.dark-mode .hamburger{background:#1E293B;border:1px solid #1E293B;}
body.dark-mode .tb-crumb{color:#94A3B8;}
body.dark-mode .tb-crumb strong{color:#E2E8F0;}
body.dark-mode .tb-btn{background:#0B1220;border:1px solid #1E293B;}
body.dark-mode .tb-btn:hover{background:#1E293B;}
body.dark-mode .tb-dot{border:2px solid #0B1220;}
body.dark-mode .page-title{color:#E2E8F0;}
body.dark-mode .ft{color:#94A3B8;}
body.dark-mode .ft:hover{color:#E2E8F0;}
body.dark-mode .alert.success{background:rgba(22,163,74,.14);}
body.dark-mode .jc-title{color:#E2E8F0;}
body.dark-mode .jc-meta{color:#94A3B8;}
body.dark-mode .pill-open{background:rgba(22,163,74,.14);}
body.dark-mode .pill-closed{background:rgba(220,38,38,.14);}
body.dark-mode .pill-paused{background:rgba(217,119,6,.16);}
body.dark-mode .jcs-lbl{color:#94A3B8;}
body.dark-mode .jc-tag{background:rgba(124,58,237,.18);}
body.dark-mode .act-view{background:rgba(124,58,237,.18);}
body.dark-mode .act-edit{background:rgba(29,78,216,.16);}
body.dark-mode .act-pause{background:rgba(217,119,6,.16);}
body.dark-mode .act-open{background:rgba(22,163,74,.14);}
body.dark-mode .act-close{background:rgba(220,38,38,.14);}
body.dark-mode .act-delete{background:rgba(220,38,38,.14);}
body.dark-mode .empty-state h3{color:#CBD5E1;}
body.dark-mode .modal{background:#111827;}
body.dark-mode .fg-label{color:#CBD5E1;}
body.dark-mode .fg-input, body.dark-mode .fg-textarea, body.dark-mode .fg-select{border:1.5px solid #1E293B;background:#0B1220;color:#E2E8F0;}
body.dark-mode .filter-tabs{background:#111827;border:1px solid #1E293B;}
body.dark-mode .job-card{background:#111827;border:1.5px solid #1E293B;}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>


<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava"><?php if (!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt=""><?php else: ?><?= $avatar_letter ?><?php endif; ?></div>
    <div><div class="sb-uname"><?= htmlspecialchars($first_name) ?></div><div class="sb-urole">Client</div></div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="post_job.php"><span class="nav-ic"><img src="../assets/icons/request.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Service Request</a>
    <a href="manage_jobs.php" class="active"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Jobs</a>
    <a href="applicants.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants<?php if ($pending_apps > 0): ?><span class="nav-bdg"><?= $pending_apps ?></span><?php endif; ?></a>
    
    <a href="hired.php"><span class="nav-ic"><img src="../assets/icons/job accepted.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Hired</a>
    <div class="nav-section">Connect</div>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if ($unread_msgs > 0): ?><span class="nav-bdg"><?= $unread_msgs ?></span><?php endif; ?></a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if ($unread_notif > 0): ?><span class="nav-bdg"><?= $unread_notif ?></span><?php endif; ?></a>
    <div class="nav-section">Account</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a>
    <a href="ratings.php"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/browse_jobs.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb">Dashboard / <strong>My Jobs</strong></span>
    </div>
    <div class="tb-right">
      <a href="chat.php" class="tb-btn"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if ($unread_msgs > 0): ?><span class="tb-dot"><?= $unread_msgs ?></span><?php endif; ?></a>
      <a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if ($unread_notif > 0): ?><span class="tb-dot"><?= $unread_notif ?></span><?php endif; ?></a>
      <a href="profile.php" class="tb-ava"><?php if (!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt=""><?php else: ?><?= $avatar_letter ?><?php endif; ?></a>
    </div>
  </div>

  <div class="content">

    <div class="page-header">
      <div class="page-title" style="display:flex;align-items:center;gap:10px;"><img src="../assets/icons/browse_jobs.png" alt="" style="width:45px;height:45px;object-fit:contain;"> My Posted Jobs</div>
      <a href="post_job.php" class="btn-post">➕ Post New Service Request</a>
    </div>

    <?php if ($success): ?><div class="alert success">✅ <?= $success ?></div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert success">✅ Job deleted successfully.</div><?php endif; ?>
    <?php if (isset($_GET['posted'])): ?><div class="alert success">🎉 Job posted successfully! Workers have been notified.</div><?php endif; ?>

    <!-- FILTER TABS -->
    <div class="filter-tabs">
      <a href="?status=all"    class="ft <?= $filter==='all'    ?'active':'' ?>">All (<?= $total_all ?>)</a>
      <a href="?status=open"   class="ft <?= $filter==='open'   ?'active':'' ?>">🟢 Open (<?= $total_open ?>)</a>
      <a href="?status=paused" class="ft <?= $filter==='paused' ?'active':'' ?>">⏸ Paused (<?= $total_paused ?>)</a>
      <a href="?status=closed" class="ft <?= $filter==='closed' ?'active':'' ?>">🔴 Closed (<?= $total_closed ?>)</a>
    </div>

    <!-- JOBS LIST -->
    <?php if ($jobs && $jobs->num_rows > 0):
      $ci = 0;
      while ($job = $jobs->fetch_assoc()):
        $col  = $job_colors[$ci++ % count($job_colors)];
        $tags = array_filter(array_map('trim', explode(',', $job['skills_required'] ?? '')));
        $diff = time() - strtotime($job['created_at']);
        $ago  = $diff < 86400 ? round($diff/3600).'h ago' : round($diff/86400).'d ago';
    ?>
      <div class="job-card">
        <div class="jc-top">
          <div class="jc-logo" style="background:<?= $col ?>;">
            <?= strtoupper(substr($job['title'],0,1)) ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div class="jc-title"><?= htmlspecialchars($job['title']) ?></div>
            <div class="jc-meta">
              📅 Posted <?= $ago ?>
              <?php if (!empty($job['budget'])): ?> · 💰 ₹<?= number_format($job['budget']) ?><?php endif; ?>
              · ⏱ <?= $job['job_type'] ?>
            </div>
          </div>
          <span class="status-pill pill-<?= $job['status'] ?>"><?= ucfirst($job['status']) ?></span>
        </div>

        <!-- Stats -->
        <div class="jc-stats">
          <div class="jcs">
            <div class="jcs-num" style="color:var(--orange);"><?= $job['app_count'] ?></div>
            <div class="jcs-lbl">Applicants</div>
          </div>
          <div class="jcs">
            <div class="jcs-num" style="color:var(--yellow);"><?= $job['pending_count'] ?></div>
            <div class="jcs-lbl">Pending</div>
          </div>
          <div class="jcs">
            <div class="jcs-num" style="color:var(--green);"><?= $job['hired_count'] ?></div>
            <div class="jcs-lbl">Hired</div>
          </div>
        </div>

        <!-- Tags -->
        <?php if (!empty($tags)): ?>
          <div class="jc-tags">
            <?php foreach (array_slice($tags,0,5) as $t): ?>
              <span class="jc-tag"><?= htmlspecialchars($t) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="jc-actions">
          <a href="applicants.php?job_id=<?= $job['id'] ?>" class="act-btn act-view">👥 View Applicants</a>
          <button class="act-btn act-edit" onclick="openEdit(<?= $job['id'] ?>)">✏️ Edit</button>

          <?php if ($job['status'] === 'open'): ?>
            <a href="?toggle=<?= $job['id'] ?>&s=paused" class="act-btn act-pause"
               onclick="return confirm('Pause this job?')">⏸ Pause</a>
            <a href="?toggle=<?= $job['id'] ?>&s=closed" class="act-btn act-close"
               onclick="return confirm('Close this job?')">🔴 Close</a>
          <?php elseif ($job['status'] === 'paused'): ?>
            <a href="?toggle=<?= $job['id'] ?>&s=open" class="act-btn act-open">🟢 Reopen</a>
            <a href="?toggle=<?= $job['id'] ?>&s=closed" class="act-btn act-close"
               onclick="return confirm('Close this job?')">🔴 Close</a>
          <?php else: ?>
            <a href="?toggle=<?= $job['id'] ?>&s=open" class="act-btn act-open">🟢 Reopen</a>
          <?php endif; ?>

          <a href="?delete=<?= $job['id'] ?>" class="act-btn act-delete"
             onclick="return confirm('Delete this job permanently? All applications will also be deleted.')">
            🗑 Delete
          </a>
        </div>

        <!-- Hidden data for edit modal -->
        <div id="job-data-<?= $job['id'] ?>" style="display:none;"
             data-title="<?= htmlspecialchars($job['title']) ?>"
             data-desc="<?= htmlspecialchars($job['description']) ?>"
             data-skills="<?= htmlspecialchars($job['skills_required'] ?? '') ?>"
             data-budget="<?= $job['budget'] ?>"
             data-type="<?= $job['job_type'] ?>">
        </div>

      </div>
    <?php endwhile; ?>

    <?php else: ?>
      <div class="empty-state">
        <div class="ei"><img src="../assets/icons/browse_jobs.png" alt="No jobs" style="width:45px;height:45px;object-fit:contain;"></div>
        <h3>No jobs <?= $filter !== 'all' ? "with status \"$filter\"" : 'posted yet' ?></h3>
        <p style="margin-bottom:16px;">
          <?= $filter !== 'all' ? 'Try a different filter.' : 'Start by posting your first job.' ?>
        </p>
        <a href="post_job.php" class="btn-post">➕ Service Request</a>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editModal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <div class="modal-title">✏️ Edit Job</div>
      <button onclick="closeEdit()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted);">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="job_id" id="edit_job_id">
      <div class="mg">
        <label class="fg-label">Event Name</label>
        <input type="text" name="title" id="edit_title" class="fg-input" required>
      </div>
      <div class="mg">
        <label class="fg-label">Event Description</label>
        <textarea name="description" id="edit_desc" class="fg-textarea"></textarea>
      </div>
      <div class="mg">
        <label class="fg-label">Skills Required</label>
        <input type="text" name="skills_required" id="edit_skills" class="fg-input">
      </div>
      <div class="edit-grid-2">
        <div>
          <label class="fg-label">Budget (₹)</label>
          <input type="number" name="budget" id="edit_budget" class="fg-input" min="0">
        </div>
        <div>
          <label class="fg-label">Job Type</label>
          <select name="job_type" id="edit_type" class="fg-select">
            <option>Full-time</option><option>Part-time</option>
            <option>Freelance</option><option>Contract</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" name="edit_job"
                style="flex:1;padding:11px;background:var(--teal);color:#fff;border:none;border-radius:100px;font-family:var(--font-b);font-size:.9rem;font-weight:400;cursor:pointer;">
          💾 Save Changes
        </button>
        <button type="button" onclick="closeEdit()"
                style="padding:11px 20px;background:var(--cream2);color:var(--ink2);border:1px solid var(--border);border-radius:100px;font-family:var(--font-b);font-size:.9rem;font-weight:400;cursor:pointer;">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id){
  const d = document.getElementById('job-data-'+id);
  document.getElementById('edit_job_id').value = id;
  document.getElementById('edit_title').value  = d.dataset.title;
  document.getElementById('edit_desc').value   = d.dataset.desc;
  document.getElementById('edit_skills').value = d.dataset.skills;
  document.getElementById('edit_budget').value = d.dataset.budget;
  document.getElementById('edit_type').value   = d.dataset.type;
  document.getElementById('editModal').classList.add('open');
}
function closeEdit(){ document.getElementById('editModal').classList.remove('open'); }
document.getElementById('editModal').addEventListener('click',function(e){ if(e.target===this) closeEdit(); });
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

</body>
</html>