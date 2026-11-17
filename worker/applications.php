<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../login.php"); exit();
}

require_once '../includes/db.php';
$user_id = (int)$_SESSION['user_id'];

// ── SIDEBAR VARIABLES (needed by embedded sidebar) ──
$conn2 = new mysqli("localhost", "root", "", "skillconnect");
$profile       = $conn2->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending       = (int)$conn2->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='pending'")->fetch_assoc()['c'];
$saved_count   = (int)$conn2->query("SELECT COUNT(*) as c FROM saved_jobs WHERE worker_id=$user_id")->fetch_assoc()['c'];
$unread_notif  = (int)$conn2->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs   = (int)$conn2->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$conn2->close();
$avatar_letter = strtoupper(substr($profile['name'] ?? $_SESSION['user_name'], 0, 1));
$first_name    = explode(' ', $profile['name'] ?? $_SESSION['user_name'])[0];
// ── END SIDEBAR VARIABLES ──


// ── FETCH ALL APPLICATIONS ──
$apps = $conn->query("
    SELECT a.*, j.title, j.description, j.budget, j.skills_required,
           u.name AS client_name, u.location AS client_loc
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON j.client_id = u.id
    WHERE a.worker_id = $user_id
    ORDER BY a.applied_at DESC
");

$all = ['pending'=>[], 'accepted'=>[], 'rejected'=>[]];
while ($row = $apps->fetch_assoc()) {
    $s = strtolower($row['status']);
    if (isset($all[$s])) {
        $all[$s][] = $row;
    } else {
        $all['pending'][] = $row;
    }
}
$all['applied'] = $all['pending'];

$conn->close();

$active_tab  = $_GET['tab'] ?? 'all';
$active_page = 'applications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Applications – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF; --cream2:#EDE9FE; --white:#fff;
      --ink:#0B1F3A; --ink2:#1E3A5F; --muted:#6B7DB3;
      --orange:#7C3AED; --orange2:#9F67FA;
      --teal:#7C3AED; --teal-lt:#ede9fe;
      --blue:#1d4ed8; --blue-lt:#eff6ff;
      --border:#DDD6FE;
      --red:#dc2626; --red-lt:#fef2f2;
      --green:#16a34a; --green-lt:#f0fdf4;
      --yellow:#d97706; --yellow-lt:#fef3c7;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    html{scroll-behavior:smooth;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);min-height:100vh;display:flex;}
    /* ── Dark mode (syncs with Settings > Dark Mode) ── */
    body.dark-mode{
      --cream:#0B1220;--cream2:#0B1220;--white:#111827;
      --ink:#E2E8F0;--ink2:#CBD5E1;--muted:#94A3B8;
      --border:#1E293B;--teal-lt:rgba(227, 225, 231, 0.88);--blue-lt:rgba(29,78,216,.16);--red-lt:rgba(220,38,38,.14);--green-lt:rgba(22,163,74,.14);--yellow-lt:rgba(217,119,6,.16);
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

    body.dark-mode .badge-interview{background:rgba(124,58,237,.18) !important;}
    body.dark-mode [style*="background:#f3e8ff"]{background:rgba(124,58,237,.18) !important;}
/* ── TABS ── */
    .tabs{display:flex;gap:4px;background:var(--white);border:1px solid var(--border);
          border-radius:100px;padding:4px;margin-bottom:28px;width:fit-content;
          max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;}
    .tabs::-webkit-scrollbar{display:none;}
    .tab-btn{
      padding:9px 20px;border-radius:100px;border:none;background:transparent;
      font-family:var(--font-b);font-size:0.85rem;font-weight:600;color:var(--muted);
      cursor:pointer;transition:all 0.2s;white-space:nowrap;flex-shrink:0;
    }
    .tab-btn.active{background:var(--orange);color:#fff;}
    .tab-btn:hover:not(.active){background:var(--cream2);color:var(--ink);}

    /* ── STATUS BADGE ── */
    .badge{
      font-size:0.72rem;font-weight:800;padding:4px 12px;border-radius:100px;
      text-transform:uppercase;letter-spacing:0.5px;display:inline-block;
    }
    .badge-pending   {background:var(--yellow-lt);color:var(--yellow);}
    .badge-accepted  {background:var(--green-lt);color:var(--green);}
    .badge-shortlisted{background:var(--blue-lt);color:var(--blue);}
    .badge-rejected  {background:var(--red-lt);color:var(--red);}
    .badge-interview {background:#f3e8ff;color:#7c3aed;}

    /* ── APP CARD ── */
    .app-card{
      background:var(--white);border:1px solid var(--border);
      border-radius:var(--radius);padding:22px;margin-bottom:16px;
      box-shadow:var(--shadow);
      display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start;
    }
    .app-title{font-family:var(--font-h);font-size:1.05rem;font-weight:900;color:var(--ink);margin-bottom:4px;word-break:break-word;}
    .app-company{font-size:0.85rem;color:var(--muted);font-weight:600;margin-bottom:8px;word-break:break-word;}
    .app-meta{display:flex;gap:14px;flex-wrap:wrap;}
    .app-meta span{font-size:0.78rem;color:var(--muted);font-weight:600;}
    .app-budget{font-family:var(--font-h);font-size:1rem;font-weight:900;color:var(--orange);}

    /* ── STATS ROW ── */
    .stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:28px;}
    .stat-card{
      background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
      padding:18px;text-align:center;box-shadow:var(--shadow);
    }
    .stat-num{font-family:var(--font-h);font-size:1.8rem;font-weight:400;line-height:1;}
    .stat-lbl{font-size:0.75rem;font-weight:700;color:var(--muted);margin-top:4px;text-transform:uppercase;letter-spacing:1px;}

    .empty-state{text-align:center;padding:50px 20px;color:var(--muted);}
    .empty-state .ei{font-size:2.5rem;margin-bottom:10px;}
    .empty-state h3{font-family:var(--font-h);font-size:1.2rem;color:var(--ink2);margin-bottom:6px;}
  
    /* ═══ SIDEBAR CSS ═══ */
    .sidebar{width:240px;height:100vh;background:#0B1F3A;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}
    .sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;flex-shrink:0;}
    .sb-ava{width:40px;height:40px;border-radius:11px;flex-shrink:0;background:#7C3AED;color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:900;overflow:hidden;}
    .sb-ava img{width:40px;height:40px;object-fit:cover;}
    .sb-uname{font-size:.86rem;font-weight:400;color:#fff;line-height:1.3;}
    .sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);margin-top:1px;}
    .sb-nav{flex:1;padding:10px 0;overflow-y:auto;}
    .nav-section{font-size:.62rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;position:relative;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(232,96,10,.15);border-left-color:#7C3AED;}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{margin-left:auto;background:#7C3AED;color:#fff;font-size:.6rem;font-weight:800;padding:2px 6px;border-radius:100px;min-width:17px;text-align:center;flex-shrink:0;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logout a{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;transition:background .2s;}.sb-logout a img{filter:brightness(0) invert(1);}
    .sb-logout a:hover{background:rgba(220,38,38,.2);}
    .main-wrap{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh;min-width:0;}
    .topbar{min-height:60px;padding:0 26px;background:var(--white);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;flex-wrap:wrap;gap:10px;row-gap:8px;}
    .tb-left{display:flex;align-items:center;gap:9px;min-width:0;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;color:var(--ink);}
    .tb-crumb{font-size:.85rem;color:var(--muted);}
    .tb-crumb strong{color:var(--ink);font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;cursor:pointer;}
    .tb-btn:hover{background:var(--border);}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:#7C3AED;color:#fff;font-size:.56rem;font-weight:800;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--white);padding:0 2px;}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:#7C3AED;color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:900;text-decoration:none;overflow:hidden;}
    .tb-ava img{width:35px;height:35px;object-fit:cover;}
    .page-content{padding:24px 26px;flex:1;min-width:0;}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}
    .topbar-title{display:flex;align-items:center;gap:8px;font-weight:400;white-space:nowrap;}
    .topbar-title img{width:45px;height:45px;object-fit:contain;flex-shrink:0;}

    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main-wrap{margin-left:0;}
      .hamburger{display:flex;color:var(--ink);}
      .page-content{padding:14px;}
      .stats-row{grid-template-columns:repeat(2,1fr);}

      .topbar{padding:0 14px;height:auto;min-height:56px;}
      .topbar-title{font-size:0.95rem;}
      .topbar-title img{width:32px;height:32px;}
      .topbar-right a{padding:7px 14px;font-size:0.78rem;white-space:nowrap;}

      .tabs{width:100%;margin-bottom:20px;}
      .tab-btn{padding:8px 14px;font-size:0.8rem;}

      .app-card{grid-template-columns:1fr;padding:16px;gap:10px;}
      .app-card > div:last-child{text-align:left;order:-1;}
      .app-meta{gap:10px;}
      .app-title{font-size:1rem;}

      .stat-card{padding:12px;}
      .stat-card > div:first-child img{width:30px;height:30px;}
      .stat-num{font-size:1.4rem;}
      .stat-lbl{font-size:0.65rem;}
    }
    @media(max-width:480px){
      .stats-row{grid-template-columns:1fr;}
      .page-content{padding:10px;}
      .topbar{padding:0 10px;}
      .topbar-title{font-size:0.88rem;}
      .app-card{padding:14px;}
      .app-title{font-size:0.95rem;}
      .app-company{font-size:0.78rem;}
      .app-meta span{font-size:0.72rem;}
      .empty-state{padding:36px 14px;}
    }

  </style>
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sb-logo">
    <a href="../index.php">Skill<span>Connect</span></a>
  </div>
  <div class="sb-user">
    <div class="sb-ava">
      <?php if (!empty($profile['profile_pic'])): ?>
        <img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt="">
      <?php else: ?>
        <?= $avatar_letter ?>
      <?php endif; ?>
    </div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars($first_name) ?></div>
      <div class="sb-urole">Service Provider</div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php" ><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="browse_jobs.php" ><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Browse Jobs</a>
    <a href="applications.php" class="active">
      <span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Applications
      <?php if ($pending > 0): ?><span class="nav-bdg"><?= $pending ?></span><?php endif; ?>
    </a>
    <a href="saved_jobs.php" >
      <span class="nav-ic"><img src="../assets/icons/saved jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Saved Jobs
      <?php if ($saved_count > 0): ?><span class="nav-bdg"><?= $saved_count ?></span><?php endif; ?>
    </a>
    <a href="recommended.php" ><span class="nav-ic"><img src="../assets/icons/recommend jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Recommended</a>
    <div class="nav-section">Connect</div>
    <a href="network.php" ><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="portfolio.php" ><span class="nav-ic"><img src="../assets/icons/total count.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Portfolio</a>
    <a href="chat.php" >
      <span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat
      <?php if ($unread_msgs > 0): ?><span class="nav-bdg"><?= $unread_msgs ?></span><?php endif; ?>
    </a>
    <a href="notifications.php" >
      <span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications
      <?php if ($unread_notif > 0): ?><span class="nav-bdg"><?= $unread_notif ?></span><?php endif; ?>
    </a>
    <div class="nav-section">Profile</div>
    <a href="profile.php" ><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Profile</a>
    <a href="skill_progress.php" ><span class="nav-ic"><img src="../assets/icons/skill program.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Skill Progress</a>
    <a href="ratings.php" ><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php" ><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout">
    <a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a>
  </div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>




<div class="main-wrap">

  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/application.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>My Applications</strong></span></div>
    </div>
    <div class="topbar-right">
      <a href="browse_jobs.php"
         style="padding:8px 20px;background:var(--orange);color:#fff;border-radius:100px;
                font-size:0.85rem;font-weight:400;text-decoration:none;">
        + Apply More
      </a>
    </div>
  </div>

  <div class="page-content">

    <!-- Stats Row -->
    <?php
      $total_all  = array_sum(array_map('count', $all));
      $stats = [
        ['num'=>$total_all,             'lbl'=>'Total',     'color'=>'var(--ink)',    'icon'=>'<img src="../assets/icons/application.png" alt="" style="width:45px;height:45px;object-fit:contain;">'],
        ['num'=>count($all['pending']), 'lbl'=>'Pending',   'color'=>'var(--yellow)', 'icon'=>'<img src="../assets/icons/pending.png" alt="" style="width:45px;height:45px;object-fit:contain;">'],
        ['num'=>count($all['applied']),  'lbl'=>'Applied',    'color'=>'var(--blue)',  'icon'=>'<img src="../assets/icons/apply.png" alt="" style="width:45px;height:45px;object-fit:contain;">'],
        ['num'=>count($all['accepted']),'lbl'=>'Accepted',  'color'=>'var(--green)',  'icon'=>'<img src="../assets/icons/job accepted.png" alt="" style="width:45px;height:45px;object-fit:contain;">'],
        ['num'=>count($all['rejected']),'lbl'=>'Rejected',  'color'=>'var(--red)',    'icon'=>'<img src="../assets/icons/rejected (2).png" alt="" style="width:45px;height:45px;object-fit:contain;">'],
      ];
    ?>
    <div class="stats-row">
      <?php foreach ($stats as $s): ?>
        <div class="stat-card">
          <div style="font-size:1.4rem;margin-bottom:4px;"><?= $s['icon'] ?></div>
          <div class="stat-num" style="color:<?= $s['color'] ?>;"><?= $s['num'] ?></div>
          <div class="stat-lbl"><?= $s['lbl'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <?php
        $tabs = [
          'all'         => 'All (' . $total_all . ')',
          'pending'     => 'Pending (' . count($all['pending']) . ')',
          'applied'     => 'Applied (' . count($all['applied']) . ')',
          'accepted'    => 'Accepted (' . count($all['accepted']) . ')',
          'rejected'    => 'Rejected (' . count($all['rejected']) . ')',
        ];
      ?>
      <?php foreach ($tabs as $key => $label): ?>
        <button class="tab-btn <?= $active_tab === $key ? 'active' : '' ?>"
                onclick="switchTab('<?= $key ?>')">
          <?= $label ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Tab Content -->
    <?php
      $tab_data = [
        'all' => array_merge(
          $all['pending'],
          $all['accepted'], $all['rejected']
        ),
        'pending'     => $all['pending'],
        'applied'     => $all['applied'],
        'accepted'    => $all['accepted'],
        'rejected'    => $all['rejected'],
      ];
    ?>

    <?php foreach ($tab_data as $tab_key => $rows): ?>
      <div id="tab-<?= $tab_key ?>"
           style="display:<?= $active_tab === $tab_key ? 'block' : 'none' ?>;">

        <?php if (empty($rows)): ?>
          <div class="empty-state">
            <div class="ei">📭</div>
            <h3>No applications here</h3>
            <p>Start applying to jobs from <a href="browse_jobs.php" style="color:var(--orange);font-weight:400;">Browse Jobs</a>.</p>
          </div>
        <?php else: ?>
          <?php foreach ($rows as $app):
            $s = strtolower($app['status']);
            $badge_class = str_contains($s,'interview') ? 'interview' : ($s === 'accepted' ? 'accepted' : ($s === 'rejected' ? 'rejected' : ($s === 'shortlisted' ? 'shortlisted' : 'pending')));
            $tags = array_filter(array_map('trim', explode(',', $app['skills_required'] ?? '')));
          ?>
            <div class="app-card">
              <div>
                <div class="app-title"><?= htmlspecialchars($app['title']) ?></div>
                <div class="app-company">
                  🏢 <?= htmlspecialchars($app['client_name']) ?>
                  · 📍 <?= htmlspecialchars($app['client_loc'] ?? 'N/A') ?>
                </div>
                <div class="app-meta">
                  <span>📅 Applied: <?= date('d M Y', strtotime($app['applied_at'])) ?></span>
                  <?php if (!empty($app['budget'])): ?>
                    <span class="app-budget">₹<?= number_format($app['budget']) ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($tags)): ?>
                  <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:10px;">
                    <?php foreach (array_slice($tags, 0, 3) as $t): ?>
                      <span style="background:var(--teal-lt);color:var(--teal);
                                   font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:100px;">
                        <?= htmlspecialchars($t) ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if ($badge_class === 'interview'): ?>
                  <div style="margin-top:12px;padding:12px 16px;background:#f3e8ff;
                               border-radius:10px;font-size:0.85rem;color:#7c3aed;font-weight:600;">
                    🎯 Interview scheduled! Check your notifications for details.
                  </div>
                <?php endif; ?>
              </div>
              <div style="text-align:right;">
                <span class="badge badge-<?= $badge_class ?>">
                  <?= htmlspecialchars(ucfirst($app['status'])) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>
    <?php endforeach; ?>

  </div><!-- page-content -->
</div><!-- main-wrap -->

<script>
function switchTab(key) {
  document.querySelectorAll('[id^="tab-"]').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + key).style.display = 'block';
  event.currentTarget.classList.add('active');
  history.replaceState(null, '', '?tab=' + key);
}
</script>

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