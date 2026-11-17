<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../login.php"); exit();
}
require_once '../includes/db.php';
$user_id = (int)$_SESSION['user_id'];

$conn2 = new mysqli("localhost", "root", "", "skillconnect");
$profile       = $conn2->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending       = (int)$conn2->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='pending'")->fetch_assoc()['c'];
$saved_count   = (int)$conn2->query("SELECT COUNT(*) as c FROM saved_jobs WHERE worker_id=$user_id")->fetch_assoc()['c'];
$unread_notif  = (int)$conn2->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs   = (int)$conn2->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$conn2->close();
$avatar_letter = strtoupper(substr($profile['name'] ?? $_SESSION['user_name'], 0, 1));
$first_name    = explode(' ', $profile['name'] ?? $_SESSION['user_name'])[0];

// ── SEND CONNECTION REQUEST ──
if (isset($_GET['connect'])) {
    $cid = (int)$_GET['connect'];
    $check = $conn->query("SELECT id FROM connections WHERE worker_id=$user_id AND client_id=$cid");
    if ($check->num_rows === 0) {
        $conn->query("INSERT INTO connections (worker_id, client_id, status, created_at) VALUES ($user_id, $cid, 'pending', NOW())");
        $uname = $conn->real_escape_string($profile['name']);
        $msg = "$uname wants to connect with you!";
        $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at) VALUES ($cid,'connection','$msg',0,NOW())");
    }
    header("Location: network.php?tab=discover&sent=1"); exit();
}

$tab = $_GET['tab'] ?? 'discover';

// ── ALL REGISTERED USERS ──
$all_users = $conn->query("
    SELECT u.id, u.name, u.location, u.bio, u.profile_pic, u.skills, u.role,
           (SELECT COUNT(*) FROM jobs WHERE client_id=u.id AND status='open') AS open_jobs,
           (SELECT status FROM connections WHERE worker_id=$user_id AND client_id=u.id) AS conn_status
    FROM users u
    WHERE u.id != $user_id AND u.role IN ('worker','client')
    ORDER BY u.name ASC
");
$all_count = $all_users ? $all_users->num_rows : 0;

// ── MY CONNECTIONS (accepted) ──
$connected = $conn->query("
    SELECT u.id, u.name, u.location, u.bio, u.profile_pic, u.skills, u.role,
           c.status, c.created_at
    FROM connections c
    JOIN users u ON u.id = CASE WHEN c.worker_id=$user_id THEN c.client_id ELSE c.worker_id END
    WHERE (c.worker_id=$user_id OR c.client_id=$user_id) AND c.status='accepted'
    ORDER BY c.created_at DESC
");
$con_count = $connected ? $connected->num_rows : 0;

// ── SENT REQUESTS (pending) ──
$sent = $conn->query("
    SELECT c.*, u.name, u.location, u.profile_pic, u.bio, u.role
    FROM connections c
    JOIN users u ON u.id = c.client_id
    WHERE c.worker_id=$user_id AND c.status='pending'
    ORDER BY c.created_at DESC
");
$sent_count = $sent ? $sent->num_rows : 0;

$conn->close();
$colors = ['#7C3AED','#1d4ed8','#db2777','#d97706','#16a34a','#0891b2'];
$active_page = 'network';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Network – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --purple:#7C3AED;--purple-lt:#ede9fe;
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
      --border:#1E293B;--purple-lt:rgba(124,58,237,.18);--blue-lt:rgba(29,78,216,.16);--red-lt:rgba(220,38,38,.14);--green-lt:rgba(22,163,74,.14);
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

    body.dark-mode .btn-pending{background:rgba(217,119,6,.16) !important;}
/* SIDEBAR */
    .sidebar{width:var(--sidebar);height:100vh;background:#0B1F3A;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}
    .sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}
    .sb-ava{width:40px;height:40px;border-radius:11px;background:var(--purple);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:900;overflow:hidden;flex-shrink:0;}
    .sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
    .sb-uname{font-size:.86rem;font-weight:400;color:#fff;}
    .sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
    .sb-nav{flex:1;padding:10px 0;}
    .nav-section{font-size:.62rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--purple);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{margin-left:auto;background:var(--purple);color:#fff;font-size:.6rem;font-weight:800;padding:2px 6px;border-radius:100px;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}
    .sb-logout a{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}

    /* MAIN */
    .main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}
    .topbar{height:60px;padding:0 26px;background:var(--white);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;color:var(--ink);}
    .tb-crumb{font-size:.85rem;color:var(--muted);}
    .tb-crumb strong{color:var(--ink);font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:var(--purple);color:#fff;font-size:.56rem;font-weight:800;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--white);}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--purple);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:900;text-decoration:none;overflow:hidden;}
    .tb-ava img{width:35px;height:35px;object-fit:cover;}

    .content{padding:24px 26px;flex:1;}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}

    .page-title{font-family:var(--font-h);font-size:1.5rem;font-weight:400;margin-bottom:6px;}
    .page-sub{font-size:.84rem;color:var(--muted);margin-bottom:22px;}

    .stats-bar{display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
    .stat-chip{background:var(--white);border:1px solid var(--border);border-radius:100px;padding:7px 16px;font-size:.8rem;font-weight:400;color:var(--ink2);display:flex;align-items:center;gap:6px;}

    .tabs{display:flex;gap:5px;background:var(--white);border:1px solid var(--border);border-radius:100px;padding:4px;width:fit-content;margin-bottom:24px;flex-wrap:wrap;}
    .tab-btn{padding:8px 18px;border-radius:100px;border:none;background:transparent;font-family:var(--font-b);font-size:.82rem;font-weight:400;color:var(--muted);cursor:pointer;transition:all .2s;}
    .tab-btn.active{background:var(--purple);color:#fff;}
    .tab-btn:hover:not(.active){background:var(--cream2);color:var(--ink);}

    .search-wrap{margin-bottom:20px;position:relative;}
    .search-input{width:100%;max-width:420px;padding:10px 16px 10px 40px;border:1.5px solid var(--border);border-radius:100px;font-family:var(--font-b);font-size:.87rem;background:var(--white);outline:none;color:var(--ink);}
    .search-input:focus{border-color:var(--purple);}
    .search-ic{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:.95rem;pointer-events:none;}

    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;}
    .card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);transition:transform .18s;height:100%;display:flex;flex-direction:column;}
    .card:hover{transform:translateY(-2px);}
    .card-accent{height:4px;flex-shrink:0;}
    .card-body{padding:18px;flex:1;display:flex;flex-direction:column;}
    .c-top{display:flex;gap:12px;align-items:center;margin-bottom:10px;}
    .c-ava{width:48px;height:48px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.1rem;font-weight:900;color:#fff;overflow:hidden;}
    .c-ava img{width:48px;height:48px;object-fit:cover;border-radius:50%;}
    .c-name{font-family:var(--font-h);font-size:.95rem;font-weight:400;}
    .c-name a{color:var(--ink);text-decoration:none;}
    .c-name a:hover{color:var(--purple);}
    .c-loc{font-size:.74rem;color:var(--muted);}
    .c-role-badge{display:inline-block;font-size:.62rem;font-weight:800;padding:2px 8px;border-radius:100px;margin-top:3px;}
    .c-role-badge{display:inline-flex;align-items:center;gap:3px;font-size:.62rem;font-weight:400;padding:3px 10px;border-radius:100px;margin-top:4px;transition:transform .15s;}
    .c-role-badge:hover{transform:scale(1.05);}
    .role-worker{background:linear-gradient(135deg,#059669,#10b981);color:#fff;box-shadow:0 2px 8px rgba(5,150,105,.35);letter-spacing:.4px;}
    .role-client{background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;box-shadow:0 2px 8px rgba(217,119,6,.35);letter-spacing:.4px;}
    .c-bio{font-size:.78rem;color:var(--muted);line-height:1.5;margin-bottom:10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .c-skills{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;}
    .c-skill{font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:5px;background:var(--purple-lt);color:var(--purple);}
    .open-jobs-badge{font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:100px;background:#dbeafe;color:#1d4ed8;margin-left:6px;}
    .c-actions{display:flex;gap:7px;flex-wrap:wrap;padding-top:12px;border-top:1px solid var(--border);margin-top:auto;}
    .btn{padding:7px 15px;border-radius:100px;font-family:var(--font-b);font-size:.75rem;font-weight:400;text-decoration:none;border:none;cursor:pointer;transition:all .18s;}
    .btn-connect{background:var(--purple);color:#fff;}.btn-connect:hover{background:#6D28D9;}
    .btn-chat{background:var(--purple-lt);color:var(--purple);border:1.5px solid var(--purple);}.btn-chat:hover{background:var(--purple);color:#fff;}
    .btn-view{background:var(--blue-lt);color:var(--blue);border:1.5px solid var(--blue);}.btn-view:hover{background:var(--blue);color:#fff;}
    .btn-pending{background:#fef3c7;color:#d97706;border:1.5px solid #d97706;cursor:default;}
    .btn-connected{background:var(--green-lt);color:var(--green);border:1.5px solid var(--green);cursor:default;}
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);background:var(--white);border:1px solid var(--border);border-radius:var(--radius);}
    .empty-state .ei{font-size:3rem;margin-bottom:12px;}
    .empty-state h3{font-family:var(--font-h);font-size:1.1rem;color:var(--ink2);margin-bottom:6px;}
    .toast{position:fixed;bottom:24px;right:24px;background:var(--purple);color:#fff;padding:12px 22px;border-radius:100px;font-weight:700;font-size:.86rem;box-shadow:var(--shadow);z-index:9999;animation:fadeUp .3s ease;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main{margin-left:0;}
      .content{padding:14px;}
      .hamburger{display:flex;color:var(--ink);}
      .tabs{gap:3px;}
      .tab-btn{padding:7px 12px;font-size:.77rem;}
    }

  </style>
</head>
<body>

<?php if(isset($_GET['sent'])): ?>
<div class="toast" id="toast">✅ Connection request sent!</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.style.opacity=0;},3500);</script>
<?php endif;?>

<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava">
      <?php if(!empty($profile['profile_pic'])): ?>
        <img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt="">
      <?php else: ?><?=$avatar_letter?><?php endif;?>
    </div>
    <div>
      <div class="sb-uname"><?=htmlspecialchars($first_name)?></div>
      <div class="sb-urole">Service Provider</div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="feed.php"><span class="nav-ic"><img src="../assets/icons/explore.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Explore</a>
    <a href="browse_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Browse Jobs</a>
    <a href="recommended.php"><span class="nav-ic"><img src="../assets/icons/recommend jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Recommended</a>
    <a href="applications.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Applications
      <?php if($pending>0): ?><span class="nav-bdg"><?=$pending?></span><?php endif;?>
    </a>
    <a href="saved_jobs.php"><span class="nav-ic"><img src="../assets/icons/saved jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Saved Jobs
      <?php if($saved_count>0): ?><span class="nav-bdg"><?=$saved_count?></span><?php endif;?>
    </a>
    <div class="nav-section">Connect</div>
    <a href="network.php" class="active"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat
      <?php if($unread_msgs>0): ?><span class="nav-bdg"><?=$unread_msgs?></span><?php endif;?>
    </a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications
      <?php if($unread_notif>0): ?><span class="nav-bdg"><?=$unread_notif?></span><?php endif;?>
    </a>
    <div class="nav-section">Profile</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Profile</a>
    <a href="portfolio.php"><span class="nav-ic"><img src="../assets/icons/total count.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Portfolio</a>
    <a href="skill_progress.php"><span class="nav-ic"><img src="../assets/icons/skill program.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Skills</a>
    <a href="ratings.php"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/network.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Network</strong></span>
    </div>
    <div class="tb-right">
      <a href="chat.php" class="tb-btn"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a>
      <a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a>
      <a href="profile.php" class="tb-ava">
        <?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?>
      </a>
    </div>
  </div>

  <div class="content">
    <div class="page-title" style="display:flex;align-items:center;gap:8px;"><img src="../assets/icons/connect.png" alt="" style="width:45px;height:45px;object-fit:contain;"> Network</div>
    <div class="page-sub">Browse everyone on SkillConnect — workers, clients, and job seekers.</div>

    <div class="stats-bar">
      <div class="stat-chip"><?=$all_count?> Members</div>
      <div class="stat-chip"><?=$con_count?> Connected</div>
      <div class="stat-chip"><img src="../assets/icons/request.png" alt="" style="width:16px;height:16px;object-fit:cover;border-radius:3px;vertical-align:-3px;margin-right:3px;"><?=$sent_count?> Requests</div>
    </div>

    <div class="tabs">
      <button class="tab-btn <?=$tab==='discover'?'active':''?>" onclick="switchTab('discover')"><img src="../assets/icons/all members.png" alt="" style="width:18px;height:18px;object-fit:contain;vertical-align:-3px;margin-right:4px;">All Members (<?=$all_count?>)</button>
      <button class="tab-btn <?=$tab==='sent'?'active':''?>" onclick="switchTab('sent')">
        <img src="../assets/icons/request.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;vertical-align:-4px;margin-right:4px;">Requests <?php if($sent_count>0): ?><span style="background:#d97706;color:#fff;font-size:.6rem;padding:1px 6px;border-radius:100px;margin-left:2px;"><?=$sent_count?></span><?php endif;?>
      </button>
      <button class="tab-btn <?=$tab==='connected'?'active':''?>" onclick="switchTab('connected')"><img src="../assets/icons/connect.png" alt="" style="width:18px;height:18px;object-fit:contain;vertical-align:-3px;margin-right:4px;">Connected (<?=$con_count?>)</button>
    </div>

    <!-- ── ALL MEMBERS TAB ── -->
    <div id="tab-discover" style="display:<?=$tab==='discover'?'block':'none'?>;">
      <div class="search-wrap">
        <span class="search-ic">🔍</span>
        <input type="text" class="search-input" id="memberSearch" placeholder="Search by name, location, or skills..." oninput="filterMembers()">
      </div>
      <?php if($all_users && $all_users->num_rows > 0):
        $ai = 0; $rows = [];
        while($r = $all_users->fetch_assoc()) $rows[] = $r;
      ?>
      <div class="grid" id="memberGrid">
        <?php foreach($rows as $r):
          $c = $colors[$ai++ % count($colors)];
          $skills = array_filter(array_map('trim', explode(',', $r['skills'] ?? '')));
          $is_client = ($r['role'] === 'client');
          $conn_st = $r['conn_status'];
        ?>
        <div class="card member-card"
             data-name="<?=strtolower(htmlspecialchars($r['name']))?>"
             data-loc="<?=strtolower(htmlspecialchars($r['location'] ?? ''))?>"
             data-skills="<?=strtolower(htmlspecialchars($r['skills'] ?? ''))?>">
          <div class="card-accent" style="background:linear-gradient(90deg,<?=$c?>,#7C3AED);"></div>
          <div class="card-body">
            <div class="c-top">
              <div class="c-ava" style="background:<?=$c?>;">
                <?php if(!empty($r['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($r['profile_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($r['name'],0,1))?><?php endif;?>
              </div>
              <div style="flex:1;">
                <div class="c-name">
                  <a href="<?=$is_client?'#':'public_profile.php'?>?id=<?=$r['id']?>"><?=htmlspecialchars($r['name'])?></a>
                  <?php if($is_client && $r['open_jobs'] > 0): ?>
                    <span class="open-jobs-badge"><?=$r['open_jobs']?> jobs</span>
                  <?php endif;?>
                </div>
                <div class="c-loc">📍 <?=htmlspecialchars($r['location'] ?? 'N/A')?></div>
                <span class="c-role-badge <?=$is_client?'role-client':'role-worker'?>"><?=$is_client?'🏢 Client':'👷 Worker'?></span>
              </div>
            </div>
            <?php if(!empty($r['bio'])): ?><div class="c-bio"><?=htmlspecialchars($r['bio'])?></div><?php endif;?>
            <?php if(!empty($skills)): ?>
              <div class="c-skills"><?php foreach(array_slice($skills,0,3) as $sk): ?><span class="c-skill"><?=htmlspecialchars($sk)?></span><?php endforeach;?></div>
            <?php endif;?>
            <div class="c-actions">
              <?php if(!$is_client): ?>
                <a href="public_profile.php?id=<?=$r['id']?>" class="btn btn-view">👤 Profile</a>
              <?php endif;?>
              <?php if($conn_st === 'accepted'): ?>
                <span class="btn btn-connected">✅ Connected</span>
                <a href="chat.php?with=<?=$r['id']?>" class="btn btn-chat">💬 Chat</a>
              <?php elseif($conn_st === 'pending'): ?>
                <span class="btn btn-pending">⏳ Pending</span>
              <?php else: ?>
                <?php if($is_client): ?>
                  <a href="?connect=<?=$r['id']?>&tab=discover" class="btn btn-connect">➕ Connect</a>
                <?php else: ?>
                  <a href="?connect=<?=$r['id']?>&tab=discover" class="btn btn-connect">➕ Connect</a>
                <?php endif;?>
              <?php endif;?>
            </div>
          </div>
        </div>
        <?php endforeach;?>
      </div>
      <?php else: ?>
        <div class="empty-state"><div class="ei">👥</div><h3>No members yet</h3><p>No other users have registered yet.</p></div>
      <?php endif;?>
    </div>

    <!-- ── CONNECTED TAB ── -->
    <div id="tab-connected" style="display:<?=$tab==='connected'?'block':'none'?>;">
      <?php if($connected && $connected->num_rows > 0): ?>
        <div class="grid">
        <?php $ai = 0; while($r = $connected->fetch_assoc()):
          $c = $colors[$ai++ % count($colors)];
          $skills = array_filter(array_map('trim', explode(',', $r['skills'] ?? '')));
          $is_client = ($r['role'] === 'client');
        ?>
          <div class="card">
            <div class="card-accent" style="background:linear-gradient(90deg,<?=$c?>,#7C3AED);"></div>
            <div class="card-body">
              <div class="c-top">
                <div class="c-ava" style="background:<?=$c?>;">
                  <?php if(!empty($r['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($r['profile_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($r['name'],0,1))?><?php endif;?>
                </div>
                <div style="flex:1;">
                  <div class="c-name"><a href="<?=$is_client?'#':'public_profile.php'?>?id=<?=$r['id']?>"><?=htmlspecialchars($r['name'])?></a></div>
                  <div class="c-loc">📍 <?=htmlspecialchars($r['location'] ?? 'N/A')?></div>
                  <span class="c-role-badge <?=$is_client?'role-client':'role-worker'?>"><?=$is_client?'🏢 Client':'👷 Worker'?></span>
                </div>
                <span style="font-size:.65rem;font-weight:800;padding:2px 9px;border-radius:100px;background:var(--green-lt);color:var(--green);">✅</span>
              </div>
              <?php if(!empty($r['bio'])): ?><div class="c-bio"><?=htmlspecialchars($r['bio'])?></div><?php endif;?>
              <?php if(!empty($skills)): ?>
                <div class="c-skills"><?php foreach(array_slice($skills,0,3) as $sk): ?><span class="c-skill"><?=htmlspecialchars($sk)?></span><?php endforeach;?></div>
              <?php endif;?>
              <div class="c-actions">
                <a href="chat.php?with=<?=$r['id']?>" class="btn btn-chat">💬 Message</a>
                <?php if(!$is_client): ?>
                  <a href="public_profile.php?id=<?=$r['id']?>" class="btn btn-view">👤 Profile</a>
                <?php endif;?>
              </div>
            </div>
          </div>
        <?php endwhile;?>
        </div>
      <?php else: ?>
        <div class="empty-state"><div class="ei">🔗</div><h3>No connections yet</h3><p>Go to <strong>All Members</strong> and connect with clients or workers.</p></div>
      <?php endif;?>
    </div>

    <!-- ── SENT REQUESTS TAB ── -->
    <div id="tab-sent" style="display:<?=$tab==='sent'?'block':'none'?>;">
      <?php if($sent && $sent->num_rows > 0):
        $ai = 0;
        while($r = $sent->fetch_assoc()):
          $c = $colors[$ai++ % count($colors)];
      ?>
        <div class="card" style="margin-bottom:14px;">
          <div class="card-accent" style="background:<?=$c?>;"></div>
          <div class="card-body">
            <div class="c-top">
              <div class="c-ava" style="background:<?=$c?>;">
                <?php if(!empty($r['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($r['profile_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($r['name'],0,1))?><?php endif;?>
              </div>
              <div style="flex:1;">
                <div class="c-name"><?=htmlspecialchars($r['name'])?></div>
                <div class="c-loc">📍 <?=htmlspecialchars($r['location'] ?? 'N/A')?></div>
                <span class="c-role-badge role-client">🏢 Client</span>
              </div>
              <span class="btn btn-pending">⏳ Pending</span>
            </div>
            <?php if(!empty($r['bio'])): ?><div class="c-bio"><?=htmlspecialchars($r['bio'])?></div><?php endif;?>
          </div>
        </div>
      <?php endwhile;
      else: ?>
        <div class="empty-state"><div class="ei"><img src="../assets/icons/request.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div><h3>No requests sent</h3><p>Connect with clients from the <strong>All Members</strong> tab.</p></div>
      <?php endif;?>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
function switchTab(k){
  ['discover','connected','sent'].forEach(t => document.getElementById('tab-'+t).style.display = 'none');
  document.getElementById('tab-'+k).style.display = 'block';
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  event.currentTarget.classList.add('active');
  history.replaceState(null,'','?tab='+k);
}
function filterMembers(){
  const q = document.getElementById('memberSearch').value.toLowerCase();
  document.querySelectorAll('.member-card').forEach(card => {
    const name = card.dataset.name || '';
    const loc = card.dataset.loc || '';
    const skills = card.dataset.skills || '';
    card.style.display = (name.includes(q) || loc.includes(q) || skills.includes(q)) ? '' : 'none';
  });
}
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
