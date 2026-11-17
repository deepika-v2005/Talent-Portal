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

// ── HANDLE STATUS CHANGE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $app_id    = (int)$_POST['app_id'];
    $new_status = $conn->real_escape_string($_POST['new_status']);
    $allowed   = ['pending','accepted','rejected'];
    if (in_array($new_status, $allowed)) {
        $conn->query("UPDATE applications SET status='$new_status' WHERE id=$app_id");
        // notify worker
        $info = $conn->query("
            SELECT a.worker_id, j.title
            FROM applications a JOIN jobs j ON a.job_id=j.id
            WHERE a.id=$app_id AND j.client_id=$user_id
        ")->fetch_assoc();
        if ($info) {
            $msg = $conn->real_escape_string("Your application for \"{$info['title']}\" status updated to: $new_status");
            $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at)
                          VALUES ({$info['worker_id']},'app_update','$msg',0,NOW())");
        }
    }
    header("Location: applicants.php" . (isset($_GET['job_id']) ? "?job_id=".(int)$_GET['job_id'] : ''));
    exit();
}

// ── FILTERS ──
$job_filter    = isset($_GET['job_id'])  ? (int)$_GET['job_id'] : 0;
$status_filter = $_GET['status'] ?? 'all';
$search        = trim($_GET['search'] ?? '');

// ── BUILD WHERE ──
$where = "j.client_id=$user_id";
if ($job_filter > 0)       $where .= " AND a.job_id=$job_filter";
if ($status_filter !== 'all') $where .= " AND a.status='" . $conn->real_escape_string($status_filter) . "'";
if (!empty($search))       $where .= " AND (u.name LIKE '%" . $conn->real_escape_string($search) . "%' OR j.title LIKE '%" . $conn->real_escape_string($search) . "%')";

// ── FETCH APPLICANTS ──
$apps = $conn->query("
    SELECT a.id, a.status, a.applied_at,
           j.title AS job_title, j.id AS job_id,
           u.id AS worker_id, u.name AS worker_name,
           u.location AS worker_loc, u.skills AS worker_skills,
           u.profile_pic AS worker_pic, u.phone AS worker_phone,
           u.bio AS worker_bio
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.worker_id = u.id
    WHERE $where
    ORDER BY a.applied_at DESC
");

// ── COUNTS PER STATUS ──
$cnt = [];
foreach (['all','pending','accepted','rejected'] as $s) {
    $w = "j.client_id=$user_id" . ($job_filter > 0 ? " AND a.job_id=$job_filter" : '');
    if ($s !== 'all') $w .= " AND a.status='" . $conn->real_escape_string($s) . "'";
    $cnt[$s] = $conn->query("SELECT COUNT(*) as c FROM applications a JOIN jobs j ON a.job_id=j.id WHERE $w")->fetch_assoc()['c'];
}

// ── MY JOBS FOR FILTER DROPDOWN ──
$my_jobs = $conn->query("SELECT id, title FROM jobs WHERE client_id=$user_id ORDER BY created_at DESC");

$conn->close();
$app_colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Applicants – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;
      --blue:#1d4ed8;--blue-lt:#eff6ff;
      --border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;
      --red:#dc2626;--red-lt:#fef2f2;
      --yellow:#d97706;--yellow-lt:#fef3c7;
      --purple:#7c3aed;--purple-lt:#f3e8ff;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --shadow-md:0 6px 24px rgba(11,31,58,0.10);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;overflow-x:hidden;}

    /* SIDEBAR */
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

    /* MAIN */
    .main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-width:0;}
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
    .content{padding:24px 26px;flex:1;min-width:0;}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}

    /* STATUS BADGE */
    .badge{font-size:.67rem;font-weight:400;padding:3px 10px;border-radius:100px;}
    .b-pending     {background:var(--yellow-lt);color:var(--yellow);}
    .b-accepted    {background:var(--green-lt);color:var(--green);}
    .b-rejected    {background:var(--red-lt);color:var(--red);}
    .b-interview   {background:var(--purple-lt);color:var(--purple);}

    /* SEARCH + FILTERS */
    .filter-row{display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center;}
    .search-box{
      display:flex;align-items:center;gap:8px;
      background:var(--white);border:1.5px solid var(--border);
      border-radius:100px;padding:5px 5px 5px 14px;flex:1;min-width:200px;
      transition:border-color .2s;
    }
    .search-box:focus-within{border-color:var(--teal);}
    .search-box input{flex:1;border:none;background:transparent;font-family:var(--font-b);font-size:.88rem;color:var(--ink);outline:none;min-width:0;}
    .search-box input::placeholder{color:#b5a89a;}
    .search-box button{padding:7px 16px;background:var(--teal);color:#fff;border:none;border-radius:100px;font-family:var(--font-b);font-size:.8rem;font-weight:400;cursor:pointer;flex-shrink:0;}
    .filter-select{padding:9px 14px;border:1.5px solid var(--border);border-radius:100px;background:var(--white);font-family:var(--font-b);font-size:.82rem;color:var(--ink);cursor:pointer;outline:none;}
    .filter-select:focus{border-color:var(--teal);}

    /* STATUS TABS */
    .status-tabs{display:flex;gap:5px;margin-bottom:20px;flex-wrap:wrap;}
    .st{padding:7px 15px;border-radius:100px;font-size:.78rem;font-weight:400;text-decoration:none;border:1.5px solid var(--border);background:var(--white);color:var(--muted);transition:all .2s;white-space:nowrap;display:inline-flex;align-items:center;}
    .st:hover{border-color:var(--teal);color:var(--teal);}
    .st.active{background:var(--teal);border-color:var(--teal);color:#fff;}

    /* APPLICANT CARD */
    .app-card{
      background:var(--white);border:1.5px solid var(--border);
      border-radius:var(--radius);padding:20px;margin-bottom:14px;
      box-shadow:var(--shadow);transition:all .18s;
      display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:start;
    }
    .app-card:hover{border-color:rgba(124,58,237,.3);box-shadow:var(--shadow-md);}

    .ac-ava{width:50px;height:50px;border-radius:13px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;overflow:hidden;}
    .ac-ava img{width:50px;height:50px;object-fit:cover;}

    .ac-name{font-family:var(--font-h);font-size:1rem;font-weight:400;color:var(--ink);margin-bottom:3px;overflow-wrap:break-word;word-break:break-word;}
    .ac-meta{font-size:.76rem;color:var(--muted);margin-bottom:8px;display:flex;flex-wrap:wrap;align-items:center;row-gap:4px;}
    .ac-bio{font-size:.82rem;color:var(--ink2);line-height:1.5;margin-bottom:8px;
            display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .ac-skills{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px;}
    .ac-skill{font-size:.69rem;font-weight:400;padding:2px 8px;border-radius:5px;background:var(--teal-lt);color:var(--teal);}
    .ac-job-tag{font-size:.69rem;font-weight:400;padding:2px 8px;border-radius:5px;background:rgba(232,96,10,.08);color:var(--orange);display:inline-flex;align-items:center;}

    .ac-actions{display:flex;flex-direction:column;gap:7px;align-items:flex-end;min-width:140px;}

    /* Status change form */
    .status-form select{
      width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:9px;
      font-family:var(--font-b);font-size:.78rem;font-weight:400;
      background:var(--white);color:var(--ink);cursor:pointer;outline:none;
      transition:border-color .2s;margin-bottom:6px;
    }
    .status-form select:focus{border-color:var(--teal);}
    .status-form button{
      width:100%;padding:7px;background:var(--teal);color:#fff;
      font-family:var(--font-b);font-size:.78rem;font-weight:400;
      border:none;border-radius:9px;cursor:pointer;transition:background .18s;
      display:flex;align-items:center;justify-content:center;
    }
    .status-form button:hover{background:#0b6b58;}

    .btn-profile{
      display:flex;align-items:center;justify-content:center;width:100%;padding:7px;text-align:center;
      background:var(--blue-lt);color:var(--blue);
      font-family:var(--font-b);font-size:.78rem;font-weight:400;
      border-radius:9px;text-decoration:none;transition:background .18s;
    }
    .btn-profile:hover{background:rgba(29,78,216,.14);}
    .btn-chat{
      display:flex;align-items:center;justify-content:center;width:100%;padding:7px;text-align:center;
      background:var(--teal-lt);color:var(--teal);
      font-family:var(--font-b);font-size:.78rem;font-weight:400;
      border-radius:9px;text-decoration:none;transition:background .18s;
    }
    .btn-chat:hover{background:rgba(124,58,237,.14);}

    /* EMPTY */
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
    .empty-state .ei{margin-bottom:12px;display:flex;justify-content:center;}
    .empty-state h3{font-family:var(--font-h);font-size:1.2rem;color:var(--ink2);margin-bottom:8px;}

    /* HEADER */
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;}
    .page-title{font-family:var(--font-h);font-size:1.5rem;font-weight:400;display:flex;align-items:center;}
    .total-badge{background:var(--teal-lt);color:var(--teal);font-size:.8rem;font-weight:400;padding:4px 12px;border-radius:100px;}

    /* ============ RESPONSIVE ============ */

    /* Tablets / small laptops */
    @media(max-width:1024px){
      :root{--sidebar:210px;}
      .content{padding:20px;}
      .app-card{grid-template-columns:auto 1fr;grid-template-areas:"avatar info" "actions actions";}
      .ac-ava{grid-area:avatar;}
      .app-card > div:nth-child(2){grid-area:info;}
      .ac-actions{grid-area:actions;flex-direction:row;flex-wrap:wrap;min-width:0;align-items:stretch;width:100%;margin-top:6px;}
      .ac-actions > *{flex:1;min-width:130px;}
    }

    @media(max-width:768px){
      .sidebar{transform:translateX(-100%);width:240px;}
      .sidebar.open{transform:translateX(0);box-shadow:6px 0 24px rgba(0,0,0,.25);}
      .main{margin-left:0;width:100%;}
      .content{padding:14px;}
      .hamburger{display:flex;}
      .topbar{padding:0 14px;}
      .tb-crumb{max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
      .app-card{grid-template-columns:1fr;grid-template-areas:"avatar" "info" "actions";padding:16px;gap:12px;}
      .app-card > .ac-ava{margin-bottom:2px;}
      .ac-actions{flex-direction:column;flex-wrap:nowrap;min-width:unset;align-items:stretch;width:100%;margin-top:0;}
      .ac-actions > *{min-width:0;}
      .filter-row{flex-direction:column;align-items:stretch;}
      .search-box{width:100%;min-width:0;}
      .filter-select{width:100%;}
      .page-title{font-size:1.2rem;}
      .page-title img{width:32px;height:32px;}
      .status-tabs{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:6px;scrollbar-width:thin;}
      .status-tabs::-webkit-scrollbar{height:4px;}
      .status-tabs::-webkit-scrollbar-thumb{background:var(--border);border-radius:100px;}
      .st{flex-shrink:0;}
      .status-form[style],.btn-profile[style],.btn-chat[style]{width:100% !important;}
      /* bigger touch targets on mobile */
      .status-form select,.status-form button,.btn-profile,.btn-chat,.filter-select,.search-box input{min-height:40px;}
      .tb-btn,.hamburger,.tb-ava{width:38px;height:38px;}
    }

    @media(max-width:480px){
      .content{padding:12px;}
      .app-card{padding:14px;}
      .ac-ava{width:42px;height:42px;font-size:1rem;}
      .ac-name{font-size:.92rem;}
      .ac-meta{font-size:.7rem;gap:4px;}
      .ac-bio{font-size:.78rem;}
      .ac-actions{gap:6px;}
      .page-header{flex-direction:column;align-items:flex-start;}
      .st{padding:6px 12px;font-size:.74rem;}
      .search-box{padding:4px 4px 4px 12px;}
      .search-box input{font-size:.82rem;}
      .search-box button{padding:6px 13px;font-size:.76rem;}
      .total-badge{font-size:.74rem;padding:3px 10px;}
      .tb-crumb{max-width:90px;font-size:.78rem;}
    }

    @media(max-width:360px){
      .ac-actions > *{min-width:0;}
      .page-title{font-size:1.05rem;}
      .page-title img{width:26px;height:26px;}
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
body.dark-mode .b-pending{background:rgba(217,119,6,.16);}
body.dark-mode .b-accepted{background:rgba(22,163,74,.14);}
body.dark-mode .b-rejected{background:rgba(220,38,38,.14);}
body.dark-mode .b-interview{background:rgba(124,58,237,.18);}
body.dark-mode .search-box{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .search-box input{color:#E2E8F0;}
body.dark-mode .filter-select{border:1.5px solid #1E293B;background:#111827;color:#E2E8F0;}
body.dark-mode .st{border:1.5px solid #1E293B;background:#111827;color:#94A3B8;}
body.dark-mode .ac-name{color:#E2E8F0;}
body.dark-mode .ac-meta{color:#94A3B8;}
body.dark-mode .ac-bio{color:#CBD5E1;}
body.dark-mode .ac-skill{background:rgba(124,58,237,.18);}
body.dark-mode .btn-profile{background:rgba(29,78,216,.16);}
body.dark-mode .btn-chat{background:rgba(124,58,237,.18);}
body.dark-mode .empty-state h3{color:#CBD5E1;}
body.dark-mode .total-badge{background:rgba(124,58,237,.18);}
body.dark-mode .app-card{background:#111827;border:1.5px solid #1E293B;}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>


<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava"><?php if (!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt=""><?php else: ?><?= $avatar_letter ?><?php endif; ?></div>
    <div><div class="sb-uname"><?= htmlspecialchars($first_name) ?></div><div class="sb-urole"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:11px;height:11px;vertical-align:-1px;margin-right:3px;"><rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1"/><path d="M10 21v-3h4v3"/></svg>Client</div></div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="post_job.php"><span class="nav-ic"><img src="../assets/icons/request.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Service Request</a>
    <a href="manage_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Jobs</a>
    <a href="applicants.php" class="active"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants<?php if ($pending_apps > 0): ?><span class="nav-bdg"><?= $pending_apps ?></span><?php endif; ?></a>
    
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
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/application.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb">Dashboard / <strong>Applicants</strong></span>
    </div>
    <div class="tb-right">
      <a href="chat.php" class="tb-btn"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if ($unread_msgs > 0): ?><span class="tb-dot"><?= $unread_msgs ?></span><?php endif; ?></a>
      <a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if ($unread_notif > 0): ?><span class="tb-dot"><?= $unread_notif ?></span><?php endif; ?></a>
      <a href="profile.php" class="tb-ava"><?php if (!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt=""><?php else: ?><?= $avatar_letter ?><?php endif; ?></a>
    </div>
  </div>

  <div class="content">

    <div class="page-header">
      <div class="page-title"><img src="../assets/icons/application.png" alt="" style="width:45px;height:45px;margin-right:8px;">Applicants</div>
      <span class="total-badge"><?= $cnt['all'] ?> Total</span>
    </div>

    <!-- SEARCH + FILTER ROW -->
    <form method="GET">
      <div class="filter-row">
        <div class="search-box">
          <span style="color:var(--muted);display:flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;display:block;"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.35-4.35"/></svg></span>
          <input type="text" name="search" placeholder="Search by worker name or job..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit">Search</button>
        </div>
        <select name="job_id" class="filter-select" onchange="this.form.submit()">
          <option value="0">All Jobs</option>
          <?php if ($my_jobs): while ($j = $my_jobs->fetch_assoc()): ?>
            <option value="<?= $j['id'] ?>" <?= $job_filter === $j['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars(mb_strimwidth($j['title'], 0, 35, '…')) ?>
            </option>
          <?php endwhile; endif; ?>
        </select>
        <?php if (!empty($search) || $job_filter > 0): ?>
          <a href="applicants.php" style="font-size:.8rem;color:var(--orange);font-weight:400;text-decoration:none;padding:9px 14px;background:rgba(232,96,10,.07);border-radius:100px;">Clear ✕</a>
        <?php endif; ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
      </div>
    </form>

    <!-- STATUS TABS -->
    <div class="status-tabs">
      <?php
        $ic_deadline = "<img src='../assets/icons/pending.png' alt='' style='width:14px;height:14px;margin-right:5px;'>";
        $ic_check    = "<img src='../assets/icons/job accepted.png' alt='' style='width:14px;height:14px;margin-right:5px;'>";
        $ic_reject   = "<img src='../assets/icons/rejected (2).png' alt='' style='width:14px;height:14px;margin-right:5px;'>";
        $tab_labels = [
          'all'                  => "All ({$cnt['all']})",
          'pending'              => "$ic_deadline Pending ({$cnt['pending']})",
          'accepted'             => "$ic_check Accepted ({$cnt['accepted']})",
          'rejected'             => "$ic_reject Rejected ({$cnt['rejected']})",
        ];
        foreach ($tab_labels as $val => $lbl):
          $url = "?status=" . urlencode($val) . ($job_filter > 0 ? "&job_id=$job_filter" : '') . (!empty($search) ? "&search=" . urlencode($search) : '');
      ?>
        <a href="<?= $url ?>" class="st <?= $status_filter === $val ? 'active' : '' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

    <!-- APPLICANT CARDS -->
    <?php if ($apps && $apps->num_rows > 0):
      $ai = 0;
      while ($app = $apps->fetch_assoc()):
        $ac = $app_colors[$ai++ % count($app_colors)];
        $skills = array_filter(array_map('trim', explode(',', $app['worker_skills'] ?? '')));
        $s = strtolower($app['status']);
        $bc = str_contains($s,'interview') ? 'interview' : $s;
    ?>
      <div class="app-card">
        <!-- Avatar -->
        <div class="ac-ava" style="background:<?= $ac ?>;">
          <?php if (!empty($app['worker_pic'])): ?>
            <img src="../uploads/profiles/<?= htmlspecialchars($app['worker_pic']) ?>" alt="">
          <?php else: ?>
            <?= strtoupper(substr($app['worker_name'],0,1)) ?>
          <?php endif; ?>
        </div>

        <!-- Info -->
        <div>
          <div class="ac-name"><?= htmlspecialchars($app['worker_name']) ?></div>
          <div class="ac-meta">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;vertical-align:-2px;margin-right:2px;"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.3"/></svg><?= htmlspecialchars($app['worker_loc'] ?? 'N/A') ?>
            &nbsp;·&nbsp; <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;vertical-align:-2px;margin-right:2px;"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>Applied <?= date('d M Y', strtotime($app['applied_at'])) ?>
            <?php if (!empty($app['worker_phone'])): ?>
              &nbsp;·&nbsp; <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;vertical-align:-2px;margin-right:2px;"><path d="M6.6 10.8a15.6 15.6 0 0 0 6.6 6.6l2.2-2.2a1.2 1.2 0 0 1 1.3-.3c1 .3 2 .5 3.1.5.7 0 1.2.5 1.2 1.2V20c0 .7-.5 1.2-1.2 1.2C10.5 21.2 2.8 13.5 2.8 4.2 2.8 3.5 3.3 3 4 3h3.4c.7 0 1.2.5 1.2 1.2 0 1.1.2 2.1.5 3.1.1.5 0 1-.3 1.3z"/></svg><?= htmlspecialchars($app['worker_phone']) ?>
            <?php endif; ?>
          </div>
          <?php if (!empty($app['worker_bio'])): ?>
            <div class="ac-bio"><?= htmlspecialchars($app['worker_bio']) ?></div>
          <?php endif; ?>
          <div class="ac-skills">
            <span class="ac-job-tag"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:11px;height:11px;margin-right:3px;"><path d="M20.6 12.9L12.9 20.6a2 2 0 0 1-2.8 0l-6.7-6.7a2 2 0 0 1 0-2.8L11.1 3.4a2 2 0 0 1 1.4-.6H19a1 1 0 0 1 1 1v6.7a2 2 0 0 1-.4 1.4z"/><circle cx="15.5" cy="8.5" r="1.2" fill="currentColor" stroke="none"/></svg><?= htmlspecialchars(mb_strimwidth($app['job_title'],0,30,'…')) ?></span>
            <?php foreach (array_slice($skills,0,4) as $sk): ?>
              <span class="ac-skill"><?= htmlspecialchars($sk) ?></span>
            <?php endforeach; ?>
          </div>
          <span class="badge b-<?= $bc ?>"><?= ucfirst($app['status']) ?></span>
        </div>

        <!-- Actions -->
        <div class="ac-actions">
          <!-- Status change -->
          <form method="POST" class="status-form" style="width:140px;">
            <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
            <select name="new_status">
              <?php
                $statuses = ['pending','accepted','rejected'];
                foreach ($statuses as $sv):
                  $sel = ($app['status'] === $sv) ? 'selected' : '';
              ?>
                <option value="<?= $sv ?>" <?= $sel ?>><?= ucfirst($sv) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" name="change_status"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;margin-right:4px;"><path d="M4 12.5l5 5 11-11"/></svg>Update Status</button>
          </form>
          <a href="applicant_profile.php?id=<?= $app['worker_id'] ?>" class="btn-profile" style="width:140px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;margin-right:4px;"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>View Profile</a>
          <a href="chat.php?with=<?= $app['worker_id'] ?>" class="btn-chat" style="width:140px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;margin-right:4px;"><rect x="3" y="4" width="18" height="13" rx="3"/><path d="M8 17v3l4-3"/></svg>Message</a>
        </div>
      </div>
    <?php endwhile; ?>

    <?php else: ?>
      <div class="empty-state">
        <div class="ei"><img src="../assets/icons/application.png" alt="" style="width:45px;height:45px;"></div>
        <h3>No applicants found</h3>
        <p>Try a different filter or post a job to attract workers.</p>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

</body>
</html>