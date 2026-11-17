<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../login.php"); exit();
}

require_once '../includes/db.php';
$user_id = (int)$_SESSION['user_id'];

// ── SIDEBAR VARS ──
$conn2 = new mysqli("localhost", "root", "", "skillconnect");
$profile      = $conn2->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending      = (int)$conn2->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='pending'")->fetch_assoc()['c'];
$unread_notif = (int)$conn2->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs  = (int)$conn2->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$conn2->close();
$avatar_letter = strtoupper(substr($profile['name'] ?? $_SESSION['user_name'], 0, 1));
$first_name    = explode(' ', $profile['name'] ?? $_SESSION['user_name'])[0];

$job_id = (int)($_GET['id'] ?? 0);
if (!$job_id) { header("Location: dashboard.php"); exit(); }

// ── FETCH JOB ──
$job = $conn->query("
    SELECT j.*, u.name as client_name, u.profile_pic as client_pic, u.location as client_location, u.bio as client_bio,
           (SELECT COUNT(*) FROM jobs WHERE client_id=u.id AND status='open') as open_jobs_count,
           (SELECT COUNT(*) FROM applications WHERE job_id=j.id) as total_applicants
    FROM jobs j
    JOIN users u ON u.id = j.client_id
    WHERE j.id = $job_id AND j.status='open'
")->fetch_assoc();

if (!$job) { header("Location: dashboard.php"); exit(); }

// ── CHECK ALREADY APPLIED ──
$is_applied = (int)$conn->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND job_id=$job_id")->fetch_assoc()['c'];

// ── CHECK SAVED ──
$is_saved = (int)$conn->query("SELECT COUNT(*) as c FROM saved_jobs WHERE worker_id=$user_id AND job_id=$job_id")->fetch_assoc()['c'];

// ── HANDLE APPLY ──
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
    if ($is_applied) {
        $error = 'You have already applied for this job.';
    } else {
        $cover_note = trim($conn->real_escape_string($_POST['cover_note'] ?? ''));
        $conn->query("INSERT INTO applications (worker_id, job_id, status, cover_note, applied_at)
                      VALUES ($user_id, $job_id, 'pending', '$cover_note', NOW())");
        // Notify client
        $wname = $conn->real_escape_string($profile['name']);
        $jtitle = $conn->real_escape_string($job['title']);
        $msg = "$wname applied for your job: \"$jtitle\"";
        $conn->query("INSERT INTO notifications (user_id, type, message, is_read, link, created_at)
                      VALUES ({$job['client_id']}, 'application', '$msg', 0, '../client/applicants.php', NOW())");
        $is_applied = 1;
        $success = 'Application submitted successfully!';
    }
}

// ── HANDLE SAVE TOGGLE ──
if (isset($_GET['save'])) {
    if ($is_saved) {
        $conn->query("DELETE FROM saved_jobs WHERE worker_id=$user_id AND job_id=$job_id");
        $is_saved = 0;
    } else {
        $conn->query("INSERT INTO saved_jobs (worker_id, job_id, saved_at) VALUES ($user_id, $job_id, NOW())");
        $is_saved = 1;
    }
    header("Location: job_detail.php?id=$job_id"); exit();
}

// ── RELATED JOBS ──
$skill_list = array_map('trim', explode(',', $job['skills_required'] ?? ''));
$first_skill = $conn->real_escape_string($skill_list[0] ?? '');
$related = $conn->query("
    SELECT j.*, u.name as client_name
    FROM jobs j JOIN users u ON u.id=j.client_id
    WHERE j.status='open' AND j.id != $job_id
    AND (j.skills_required LIKE '%$first_skill%' OR j.client_id={$job['client_id']})
    LIMIT 4
");

$conn->close();

$cl_letter = strtoupper(substr($job['client_name'], 0, 1));
$colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706'];
$cl_color = $colors[crc32($job['client_name']) % count($colors)];

// Time ago
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}
$active_page = 'browse';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($job['title']) ?> – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--orange2:#9F67FA;
      --teal:#7C3AED;--teal-lt:#ede9fe;
      --blue:#1d4ed8;--blue-lt:#eff6ff;
      --border:#DDD6FE;
      --green:#16a34a;--green-lt:#f0fdf4;
      --red:#dc2626;--red-lt:#fef2f2;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);min-height:100vh;display:flex;}
    /* ── Dark mode (syncs with Settings > Dark Mode) ── */
    body.dark-mode{
      --cream:#0B1220;--cream2:#0B1220;--white:#111827;
      --ink:#E2E8F0;--ink2:#CBD5E1;--muted:#94A3B8;
      --border:#1E293B;--teal-lt:rgba(124,58,237,.18);--blue-lt:rgba(29,78,216,.16);--red-lt:rgba(220,38,38,.14);--green-lt:rgba(22,163,74,.14);
      background:#0B1220;color:#E2E8F0;
    }
    body.dark-mode .sidebar{background:#0B1F3A;}
    body.dark-mode .hamburger{background:#1E293B;border-color:#1E293B;color:#fff;}
    body.dark-mode .tb-crumb{color:#94A3B8;}
    body.dark-mode .tb-crumb strong{color:#E2E8F0;}
    body.dark-mode .tb-btn{background:#0B1220;border-color:#1E293B;}
    body.dark-mode .tb-btn:hover{background:#1E293B;}
    body.dark-mode .tb-dot{border-color:#0B1220;}
    body.dark-mode .topbar{border-color:#1E293B;}
    body.dark-mode input, body.dark-mode textarea, body.dark-mode select{background:#0B1220;color:#E2E8F0;border-color:#1E293B;}
    body.dark-mode input::placeholder, body.dark-mode textarea::placeholder{color:#64748B;}

    body.dark-mode .btn-save-main.saved{background:rgba(217,119,6,.16) !important;}
/* SIDEBAR */
    .sidebar{width:var(--sidebar);height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}.sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:900;color:#fff;text-decoration:none;}.sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}
    .sb-ava{width:40px;height:40px;border-radius:11px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:900;overflow:hidden;flex-shrink:0;}.sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
    .sb-uname{font-size:.86rem;font-weight:700;color:#fff;}.sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
    .sb-nav{flex:1;padding:10px 0;}.nav-section{font-size:.62rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:600;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}.nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:800;padding:2px 6px;border-radius:100px;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}.sb-logout a{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}

    /* MAIN */
    .main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}
    .topbar{height:60px;padding:0 26px;background:var(--white);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;color:var(--ink);}
    .tb-crumb{font-size:.85rem;color:var(--muted);}.tb-crumb a{color:var(--muted);text-decoration:none;}.tb-crumb a:hover{color:var(--orange);}.tb-crumb strong{color:var(--ink);font-weight:700;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:800;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--white);}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:900;text-decoration:none;overflow:hidden;}.tb-ava img{width:35px;height:35px;object-fit:cover;}

    /* LAYOUT */
    .content{padding:28px 26px;flex:1;}
    .layout{display:grid;grid-template-columns:1fr 320px;gap:24px;max-width:1100px;}

    /* JOB HEADER CARD */
    .job-header{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);margin-bottom:20px;}
    .job-header-accent{height:5px;background:linear-gradient(90deg,var(--orange),var(--orange2));}
    .job-header-body{padding:26px;}
    .job-type-badge{display:inline-block;font-size:.72rem;font-weight:800;padding:3px 12px;border-radius:100px;background:var(--orange);color:#fff;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;}
    .job-title{font-family:var(--font-h);font-size:1.7rem;font-weight:900;color:var(--ink);margin-bottom:10px;line-height:1.2;}
    .job-meta{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:18px;}
    .job-meta-item{display:flex;align-items:center;gap:5px;font-size:.82rem;color:var(--muted);font-weight:600;}
    .job-meta-item strong{color:var(--ink2);}
    .job-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
    .btn-apply-main{padding:12px 32px;background:var(--orange);color:#fff;border:none;border-radius:100px;font-family:var(--font-b);font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 4px 16px rgba(232,96,10,.3);transition:all .18s;text-decoration:none;display:inline-flex;align-items:center;gap:7px;}
    .btn-apply-main:hover{background:#d45609;transform:translateY(-1px);}
    .btn-apply-main.applied{background:var(--green-lt);color:var(--green);box-shadow:none;border:1.5px solid var(--green);cursor:default;}
    .btn-save-main{padding:12px 20px;background:var(--cream2);color:var(--ink2);border:1.5px solid var(--border);border-radius:100px;font-family:var(--font-b);font-size:.88rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .18s;}
    .btn-save-main:hover{background:var(--cream);border-color:var(--orange);color:var(--orange);}
    .btn-save-main.saved{background:#fef3c7;color:#d97706;border-color:#fde68a;}

    /* SECTIONS */
    .section-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:16px;}
    .sec-title{font-family:var(--font-h);font-size:1rem;font-weight:900;color:var(--ink);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
    .sec-title::after{content:'';flex:1;height:1px;background:var(--border);}
    .job-desc{font-size:.9rem;color:var(--ink2);line-height:1.8;white-space:pre-wrap;}
    .skill-chips{display:flex;flex-wrap:wrap;gap:7px;}
    .chip{font-size:.78rem;font-weight:700;padding:5px 14px;border-radius:100px;background:var(--teal-lt);color:var(--teal);}

    /* APPLY FORM */
    .apply-form-card{background:linear-gradient(135deg,#fffbf5,var(--white));border:2px solid var(--orange);border-radius:var(--radius);padding:24px;box-shadow:0 4px 20px rgba(232,96,10,.1);margin-bottom:16px;}
    .apply-title{font-family:var(--font-h);font-size:1.1rem;font-weight:900;margin-bottom:6px;}
    .apply-sub{font-size:.8rem;color:var(--muted);margin-bottom:16px;}
    .field-label{display:block;font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
    .field-textarea{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:11px 14px;font-family:var(--font-b);font-size:.88rem;background:var(--cream);outline:none;resize:vertical;min-height:100px;}
    .field-textarea:focus{border-color:var(--orange);background:var(--white);}
    .btn-submit{width:100%;padding:13px;background:var(--orange);color:#fff;border:none;border-radius:100px;font-family:var(--font-b);font-size:.95rem;font-weight:700;cursor:pointer;margin-top:12px;transition:all .18s;}
    .btn-submit:hover{background:#d45609;}
    .alert{border-radius:10px;padding:12px 16px;font-size:.86rem;margin-bottom:14px;font-weight:600;}
    .alert.success{background:var(--green-lt);color:var(--green);border:1px solid #bbf7d0;}
    .alert.error{background:var(--red-lt);color:var(--red);border:1px solid #fecaca;}
    .already-applied{background:var(--green-lt);border:2px solid var(--green);border-radius:var(--radius);padding:20px;text-align:center;}
    .already-applied .icon{font-size:2.5rem;margin-bottom:8px;}
    .already-applied h3{font-family:var(--font-h);font-size:1rem;color:var(--green);margin-bottom:4px;}
    .already-applied p{font-size:.82rem;color:var(--muted);}

    /* CLIENT CARD (sidebar) */
    .client-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px;}
    .client-card-title{font-family:var(--font-h);font-size:.95rem;font-weight:900;margin-bottom:14px;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:1px;}
    .client-top{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
    .client-ava{width:48px;height:48px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.1rem;font-weight:900;color:#fff;overflow:hidden;}
    .client-ava img{width:48px;height:48px;object-fit:cover;border-radius:12px;}
    .client-name{font-family:var(--font-h);font-size:.95rem;font-weight:900;}
    .client-loc{font-size:.74rem;color:var(--muted);}
    .client-bio{font-size:.8rem;color:var(--muted);line-height:1.5;margin-bottom:12px;}
    .client-stat{display:flex;gap:16px;margin-bottom:12px;}
    .cs-item{font-size:.76rem;color:var(--muted);}.cs-item strong{font-weight:700;color:var(--ink2);}
    .btn-msg{display:block;text-align:center;padding:9px;background:var(--teal-lt);color:var(--teal);border:1.5px solid var(--teal);border-radius:100px;font-family:var(--font-b);font-size:.82rem;font-weight:700;text-decoration:none;}
    .btn-msg:hover{background:var(--teal);color:#fff;}

    /* RELATED JOBS */
    .related-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
    .related-title{font-family:var(--font-h);font-size:.9rem;font-weight:900;margin-bottom:14px;}
    .rj-item{padding:12px 0;border-bottom:1px solid var(--border);}.rj-item:last-child{border-bottom:none;padding-bottom:0;}
    .rj-name{font-weight:700;font-size:.86rem;color:var(--ink);text-decoration:none;}.rj-name:hover{color:var(--orange);}
    .rj-meta{font-size:.72rem;color:var(--muted);margin-top:3px;}
    .rj-budget{font-size:.78rem;font-weight:700;color:var(--teal);}

    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}.sb-overlay.show{display:block;}
    @media(max-width:900px){.layout{grid-template-columns:1fr;}}
    @media(max-width:768px){.sidebar{transform:translateX(-240px);}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.content{padding:14px 16px;}.hamburger{display:flex;color:var(--ink);}.job-title{font-size:1.3rem;}}

  </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></div>
    <div><div class="sb-uname"><?=htmlspecialchars($first_name)?></div><div class="sb-urole">Service Provider</div></div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="feed.php"><span class="nav-ic"><img src="../assets/icons/explore.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Explore</a>
    <a href="browse_jobs.php" class="active"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Browse Jobs</a>
    <a href="applications.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applications<?php if($pending>0) echo "<span class='nav-bdg'>$pending</span>"; ?></a>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="portfolio.php"><span class="nav-ic"><img src="../assets/icons/total count.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Portfolio</a>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Profile</a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if($unread_notif>0) echo "<span class='nav-bdg'>$unread_notif</span>"; ?></a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if($unread_msgs>0) echo "<span class='nav-bdg'>$unread_msgs</span>"; ?></a>
    <a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <div class="tb-crumb">
        <a href="dashboard.php">Dashboard</a> /
        <a href="browse_jobs.php">Jobs</a> /
        <strong><?= htmlspecialchars(substr($job['title'],0,30)) ?>...</strong>
      </div>
    </div>
    <div class="tb-right">
      <a class="tb-btn" href="notifications.php">🔔<?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a>
      <a class="tb-btn" href="chat.php">💬<?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a>
      <a class="tb-ava" href="profile.php"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a>
    </div>
  </div>

  <div class="content">
    <div class="layout">

      <!-- LEFT: JOB DETAILS -->
      <div>
        <!-- Job Header -->
        <div class="job-header">
          <div class="job-header-accent"></div>
          <div class="job-header-body">
            <span class="job-type-badge"><?= htmlspecialchars($job['job_type']) ?></span>
            <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
            <div class="job-meta">
              <div class="job-meta-item">🏢 <strong><?= htmlspecialchars($job['client_name']) ?></strong></div>
              <?php if($job['client_location']): ?>
              <div class="job-meta-item">📍 <?= htmlspecialchars($job['client_location']) ?></div>
              <?php endif;?>
              <?php if($job['budget']): ?>
              <div class="job-meta-item">💰 <strong style="color:var(--teal);">₹<?= number_format($job['budget']) ?></strong></div>
              <?php endif;?>
              <div class="job-meta-item">👥 <?= $job['total_applicants'] ?> applicants</div>
              <div class="job-meta-item">🕐 <?= time_ago($job['created_at']) ?></div>
            </div>
            <div class="job-actions">
              <?php if($is_applied): ?>
                <span class="btn-apply-main applied">✅ Already Applied</span>
              <?php else: ?>
                <button class="btn-apply-main" onclick="document.getElementById('apply-section').scrollIntoView({behavior:'smooth'})">
                  📩 Apply Now
                </button>
              <?php endif;?>
              <a href="?id=<?=$job_id?>&save=1" class="btn-save-main <?=$is_saved?'saved':''?>">
                <?=$is_saved?'🔖 Saved':'📌 Save Job'?>
              </a>
            </div>
          </div>
        </div>

        <!-- Description -->
        <div class="section-card">
          <div class="sec-title">📋 Job Description</div>
          <div class="job-desc"><?= htmlspecialchars($job['description']) ?></div>
        </div>

        <!-- Skills -->
        <?php if(!empty($job['skills_required'])): ?>
        <div class="section-card">
          <div class="sec-title">🛠️ Skills Required</div>
          <div class="skill-chips">
            <?php foreach(array_map('trim', explode(',', $job['skills_required'])) as $sk): ?>
              <?php if(!empty($sk)): ?><span class="chip"><?= htmlspecialchars($sk) ?></span><?php endif;?>
            <?php endforeach;?>
          </div>
        </div>
        <?php endif;?>

        <!-- Apply Form -->
        <div id="apply-section">
          <?php if($is_applied): ?>
            <div class="already-applied">
              <div class="icon">✅</div>
              <h3>Application Submitted!</h3>
              <p>You have already applied for this job. The client will review your profile and get back to you.</p>
              <a href="applications.php" style="display:inline-block;margin-top:12px;color:var(--teal);font-weight:700;font-size:.84rem;">View My Applications →</a>
            </div>
          <?php else: ?>
            <div class="apply-form-card">
              <div class="apply-title">📩 Apply for this Job</div>
              <div class="apply-sub">Write a short cover note to stand out from other applicants (optional but recommended)</div>
              <?php if($success): ?><div class="alert success">✅ <?=$success?></div><?php endif;?>
              <?php if($error): ?><div class="alert error">⚠️ <?=$error?></div><?php endif;?>
              <form method="POST">
                <label class="field-label">Cover Note (Optional)</label>
                <textarea name="cover_note" class="field-textarea"
                  placeholder="e.g. Hi, I'm a professional photographer with 3 years of experience in weddings. I have seen your requirements and I'm confident I can deliver excellent results..."></textarea>
                <button type="submit" name="apply_job" class="btn-submit">
                  🚀 Submit Application
                </button>
              </form>
            </div>
          <?php endif;?>
        </div>
      </div>

      <!-- RIGHT SIDEBAR -->
      <div>
        <!-- Client Info -->
        <div class="client-card">
          <div class="client-card-title">About the Client</div>
          <div class="client-top">
            <div class="client-ava" style="background:<?=$cl_color?>">
              <?php if(!empty($job['client_pic'])): ?>
                <img src="../uploads/profiles/<?=htmlspecialchars($job['client_pic'])?>" alt="">
              <?php else: ?><?=$cl_letter?><?php endif;?>
            </div>
            <div>
              <div class="client-name"><?=htmlspecialchars($job['client_name'])?></div>
              <div class="client-loc">📍 <?=htmlspecialchars($job['client_location'] ?? 'N/A')?></div>
            </div>
          </div>
          <?php if(!empty($job['client_bio'])): ?>
            <div class="client-bio"><?=htmlspecialchars(substr($job['client_bio'],0,120))?>...</div>
          <?php endif;?>
          <div class="client-stat">
            <div class="cs-item"><strong><?=$job['open_jobs_count']?></strong> open jobs</div>
          </div>
          <a href="chat.php?to=<?=$job['client_id']?>" class="btn-msg">💬 Message Client</a>
        </div>

        <!-- Job Summary -->
        <div class="client-card">
          <div class="client-card-title">Job Summary</div>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;justify-content:space-between;font-size:.83rem;">
              <span style="color:var(--muted);">Job Type</span>
              <strong><?=htmlspecialchars($job['job_type'])?></strong>
            </div>
            <?php if($job['budget']): ?>
            <div style="display:flex;justify-content:space-between;font-size:.83rem;">
              <span style="color:var(--muted);">Budget</span>
              <strong style="color:var(--teal);">₹<?=number_format($job['budget'])?></strong>
            </div>
            <?php endif;?>
            <div style="display:flex;justify-content:space-between;font-size:.83rem;">
              <span style="color:var(--muted);">Applicants</span>
              <strong><?=$job['total_applicants']?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:.83rem;">
              <span style="color:var(--muted);">Posted</span>
              <strong><?=time_ago($job['created_at'])?></strong>
            </div>
          </div>
        </div>

        <!-- Related Jobs -->
        <?php if($related && $related->num_rows > 0): ?>
        <div class="related-card">
          <div class="related-title">🔍 Similar Jobs</div>
          <?php while($r = $related->fetch_assoc()): ?>
            <div class="rj-item">
              <a href="job_detail.php?id=<?=$r['id']?>" class="rj-name"><?=htmlspecialchars($r['title'])?></a>
              <div class="rj-meta"><?=htmlspecialchars($r['client_name'])?> · <?=$r['job_type']?></div>
              <?php if($r['budget']): ?><div class="rj-budget">₹<?=number_format($r['budget'])?></div><?php endif;?>
            </div>
          <?php endwhile;?>
        </div>
        <?php endif;?>
      </div>

    </div>
  </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>
<script>
// Apply dark mode from saved preference (syncs with Settings > Dark Mode)
(function(){
  var cookies = document.cookie.split(';').map(function(c){return c.trim();});
  var dm = cookies.find(function(c){return c.indexOf('dark_mode=') === 0;});
  if (dm && dm.split('=')[1] === '1') {
    document.body.classList.add('dark-mode');
  }
})();
</script>
<script>
// ── Sequential (chase) button blink — each button lights up in turn, looping ──
(function(){
  var els = document.querySelectorAll('button, .btn, a[class*="btn"], input[type="submit"], input[type="button"]');
  var step = 0.15; // seconds between each button's turn
  els.forEach(function(el, i){
    el.style.animationDelay = (i * step) + 's';
  });
})();
</script>
</body>
</html>
