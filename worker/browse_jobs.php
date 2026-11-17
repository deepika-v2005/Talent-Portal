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


// ── SEARCH + FILTER ──
$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');
$sort     = trim($_GET['sort']     ?? 'newest');

$where = "j.status='open'";
if (!empty($search))
    $where .= " AND (j.title LIKE '%" . $conn->real_escape_string($search) . "%'
                  OR j.description LIKE '%" . $conn->real_escape_string($search) . "%'
                  OR j.skills_required LIKE '%" . $conn->real_escape_string($search) . "%'
                  OR u.name LIKE '%" . $conn->real_escape_string($search) . "%')";
if (!empty($category))
    $where .= " AND j.skills_required LIKE '%" . $conn->real_escape_string($category) . "%'";

$order = $sort === 'budget_high' ? 'j.budget DESC' : ($sort === 'budget_low' ? 'j.budget ASC' : 'j.created_at DESC');

$jobs_result = $conn->query("
    SELECT j.*, u.name AS client_name, u.location AS client_loc, u.profile_pic AS client_pic
    FROM jobs j
    JOIN users u ON j.client_id = u.id
    WHERE $where
    ORDER BY $order
");

// Already applied IDs
$applied_ids = [];
$ar = $conn->query("SELECT job_id FROM applications WHERE worker_id=$user_id");
while ($row = $ar->fetch_assoc()) $applied_ids[] = $row['job_id'];

// Saved job IDs
$saved_ids = [];
$sr = $conn->query("SELECT job_id FROM saved_jobs WHERE worker_id=$user_id");
while ($row = $sr->fetch_assoc()) $saved_ids[] = $row['job_id'];

$total_jobs = $jobs_result ? $jobs_result->num_rows : 0;
$conn->close();

$categories = [
    ['id'=>'makeup','icon'=>'💄','label'=>'Makeup Artist'],
    ['id'=>'mehendi','icon'=>'🌿','label'=>'Mehendi'],
    ['id'=>'photography','icon'=>'📸','label'=>'Photography'],
    ['id'=>'catering','icon'=>'🍽️','label'=>'Catering'],
    ['id'=>'tailoring','icon'=>'🧵','label'=>'Tailoring'],
    ['id'=>'tutor','icon'=>'📚','label'=>'Tutor'],
    ['id'=>'music','icon'=>'🎵','label'=>'Music'],
    ['id'=>'poetry','icon'=>'✍️','label'=>'Poetry & Art'],
    ['id'=>'videography','icon'=>'🎬','label'=>'Videography'],
    ['id'=>'design','icon'=>'🎨','label'=>'Graphic Design'],
    ['id'=>'event','icon'=>'🎪','label'=>'Event Planning'],
    ['id'=>'dance','icon'=>'💃','label'=>'Dance & Performance'],
];

$active_page = 'browse';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Browse Jobs – SkillConnect</title>
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
      --green:#16a34a; --green-lt:#f0fdf4;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    html{scroll-behavior:smooth;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);min-height:100vh;display:flex;overflow-x:hidden;}
    /* ── Dark mode (syncs with Settings > Dark Mode) ── */
    body.dark-mode{
      --cream:#0B1220;--cream2:#0B1220;--white:#111827;
      --ink:#E2E8F0;--ink2:#CBD5E1;--muted:#94A3B8;
      --border:#1E293B;--teal-lt:rgba(124,58,237,.18);--blue-lt:rgba(29,78,216,.16);--green-lt:rgba(22,163,74,.14);
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

    body.dark-mode .btn-save.saved{background:rgba(217,119,6,.16) !important;}
/* ── JOB CARD ── */
    .job-card{
      background:var(--white);border:1px solid var(--border);
      border-radius:var(--radius);padding:22px;
      box-shadow:var(--shadow);
      transition:transform 0.2s,box-shadow 0.2s;
      min-width:0;
    }
    .job-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(11,31,58,0.12);}

    .job-card-top{display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;}
    .client-logo{
      width:46px;height:46px;border-radius:12px;
      display:flex;align-items:center;justify-content:center;
      font-family:var(--font-h);font-size:1.1rem;font-weight:900;color:#fff;
      flex-shrink:0;
    }
    .job-title{font-family:var(--font-h);font-size:1.05rem;font-weight:900;color:var(--ink);margin-bottom:4px;word-break:break-word;}
    .job-client{font-size:0.82rem;color:var(--muted);font-weight:600;}
    .job-loc{font-size:0.8rem;color:var(--muted);}

    .job-tags{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0;}
    .tag{
      font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:100px;
      background:var(--teal-lt);color:var(--teal);
    }

    .job-meta{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;}
    .job-meta span{font-size:0.8rem;color:var(--muted);font-weight:600;}
    .job-budget{font-family:var(--font-h);font-size:1.1rem;font-weight:900;color:var(--orange);}

    .job-actions{display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border);flex-wrap:wrap;}

    .btn-apply{
      flex:1;min-width:100px;padding:10px;border-radius:100px;
      background:var(--orange);color:#fff;
      font-family:var(--font-b);font-size:0.87rem;font-weight:700;
      border:none;cursor:pointer;text-align:center;text-decoration:none;
      transition:background 0.2s;display:block;
    }
    .btn-apply:hover{background:#d4540a;}
    .btn-apply.applied{background:var(--green-lt);color:var(--green);pointer-events:none;border:1px solid #86efac;}

    .btn-save{
      width:40px;height:40px;border-radius:100px;
      background:var(--cream2);border:1px solid var(--border);
      display:flex;align-items:center;justify-content:center;
      font-size:1.1rem;cursor:pointer;text-decoration:none;
      transition:background 0.2s;flex-shrink:0;
    }
    .btn-save:hover{background:var(--border);}
    .btn-save.saved{background:#fef3c7;border-color:#fde68a;}

    /* ── FILTER CHIPS ── */
    .filter-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;}
    .chip{
      padding:7px 16px;border-radius:100px;
      border:1.5px solid var(--border);background:var(--white);
      font-size:0.82rem;font-weight:400;color:var(--ink2);
      cursor:pointer;text-decoration:none;transition:all 0.2s;
      white-space:nowrap;
    }
    .chip:hover,.chip.active{
      background:var(--orange);border-color:var(--orange);color:#fff;
    }

    /* ── SEARCH BAR ── */
    .search-wrap{
      background:var(--white);border:1.5px solid var(--border);border-radius:100px;
      display:flex;align-items:center;gap:12px;padding:0 20px;
      margin-bottom:20px;box-shadow:var(--shadow);
    }
    .search-wrap input{
      flex:1;min-width:0;padding:13px 0;border:none;background:transparent;
      font-family:var(--font-b);font-size:0.95rem;color:var(--ink);outline:none;
    }
    .search-wrap button{
      padding:8px 22px;border-radius:100px;background:var(--orange);color:#fff;
      font-family:var(--font-b);font-size:0.85rem;font-weight:700;border:none;cursor:pointer;
      transition:background 0.2s;flex-shrink:0;
    }
    .search-wrap button:hover{background:#d4540a;}

    /* ── JOBS GRID ── */
    .jobs-grid{
      display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;
    }
    .empty-state{
      grid-column:1/-1;text-align:center;padding:60px 20px;
      color:var(--muted);
    }
    .empty-state .empty-icon{font-size:3rem;margin-bottom:12px;}
    .empty-state h3{font-family:var(--font-h);font-size:1.3rem;color:var(--ink2);margin-bottom:8px;}

    /* ── SORT ROW ── */
    .sort-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;}
    .sort-row form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}

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
    .nav-section{font-size:.62rem;font-weight:400;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;position:relative;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(232,96,10,.15);border-left-color:#7C3AED;}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{margin-left:auto;background:#7C3AED;color:#fff;font-size:.6rem;font-weight:800;padding:2px 6px;border-radius:100px;min-width:17px;text-align:center;flex-shrink:0;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logout a{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;transition:background .2s;}.sb-logout a img{filter:brightness(0) invert(1);}
    .sb-logout a:hover{background:rgba(220,38,38,.2);}
    .main-wrap{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh;min-width:0;}
    .topbar{height:60px;padding:0 26px;background:var(--white);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;gap:10px;}
    .tb-left{display:flex;align-items:center;gap:9px;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;color:var(--ink);}
    .tb-crumb{font-size:.85rem;color:var(--muted);}
    .tb-crumb strong{color:var(--ink);font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;cursor:pointer;}
    .tb-btn:hover{background:var(--border);}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:#7C3AED;color:#fff;font-size:.56rem;font-weight:800;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--white);padding:0 2px;}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:#7C3AED;color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:900;text-decoration:none;overflow:hidden;}
    .tb-ava img{width:35px;height:35px;object-fit:cover;}
    /* topbar-title / topbar-right / topbar-notif were used in markup but never styled — this is why the topbar broke on mobile */
    .topbar-title{font-family:var(--font-h);font-size:1.05rem;font-weight:400;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;}
    .topbar-right{display:flex;align-items:center;gap:12px;flex-shrink:0;}
    .topbar-right span{white-space:nowrap;}
    .topbar-notif{width:35px;height:35px;border-radius:9px;background:var(--cream2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;color:var(--ink);flex-shrink:0;transition:background .18s;}
    .topbar-notif:hover{background:var(--border);}
    .page-content{padding:24px 26px;flex:1;min-width:0;overflow-x:hidden;}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main-wrap{margin-left:0;}
      .hamburger{display:flex;color:var(--ink);}
      .page-content{padding:14px;}
      .jobs-grid{grid-template-columns:1fr;}
      .topbar{padding:0 14px;}
      .topbar-title{font-size:.92rem;}
    }
    @media(max-width:480px){
      .search-wrap{padding:0 14px;flex-wrap:wrap;border-radius:16px;}
      .search-wrap button{width:100%;margin:8px 0;}
      .filter-chips{overflow-x:auto;flex-wrap:nowrap;padding-bottom:6px;-webkit-overflow-scrolling:touch;}
      .topbar{padding:0 10px;}
      .topbar-title{font-size:.82rem;max-width:120px;}
      .topbar-right span{display:none;}
      .job-card{padding:16px;}
      .sort-row{flex-direction:column;align-items:flex-start;}
      .sort-row form{width:100%;}
      .sort-row select{flex:1;}
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
    <a href="browse_jobs.php" class="active"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Browse Jobs</a>
    <a href="applications.php" >
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
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/browse_jobs.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Browse Jobs</strong></span></div>
    </div>
    <div class="topbar-right">
      <span style="font-size:0.85rem;color:var(--muted);font-weight:600;">
        <?= $total_jobs ?> jobs found
      </span>
      <a href="notifications.php" class="topbar-notif"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"></a>
    </div>
  </div>

  <div class="page-content">

    <!-- Search Bar -->
    <form method="GET">
      <div class="search-wrap">
        <span style="font-size:1.1rem;">🔍</span>
        <input type="text" name="search"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="Search jobs, skills, or client names...">
        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <input type="hidden" name="sort"     value="<?= htmlspecialchars($sort) ?>">
        <button type="submit">Search</button>
      </div>
    </form>

    <!-- Category Filter Chips -->
    <div class="filter-chips">
      <a href="?search=<?= urlencode($search) ?>&sort=<?= $sort ?>"
         class="chip <?= empty($category) ? 'active' : '' ?>">
        All Jobs
      </a>
      <?php foreach ($categories as $cat): ?>
        <a href="?search=<?= urlencode($search) ?>&category=<?= $cat['id'] ?>&sort=<?= $sort ?>"
           class="chip <?= $category === $cat['id'] ? 'active' : '' ?>">
          <?= $cat['icon'] ?> <?= $cat['label'] ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Sort Row -->
    <div class="sort-row">
      <div style="font-size:0.88rem;color:var(--muted);">
        Showing <strong style="color:var(--ink);"><?= $total_jobs ?></strong> jobs
        <?php if (!empty($category)): ?>
          in <strong style="color:var(--orange);"><?= htmlspecialchars($category) ?></strong>
        <?php endif; ?>
      </div>
      <form method="GET">
        <input type="hidden" name="search"   value="<?= htmlspecialchars($search) ?>">
        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <label style="font-size:0.82rem;color:var(--muted);font-weight:600;">Sort:</label>
        <select name="sort" onchange="this.form.submit()"
                style="padding:6px 12px;border:1.5px solid var(--border);border-radius:100px;
                       background:var(--white);font-family:var(--font-b);font-size:0.82rem;
                       color:var(--ink);cursor:pointer;outline:none;">
          <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest First</option>
          <option value="budget_high"<?= $sort === 'budget_high'? 'selected' : '' ?>>Budget: High–Low</option>
          <option value="budget_low" <?= $sort === 'budget_low' ? 'selected' : '' ?>>Budget: Low–High</option>
        </select>
      </form>
    </div>

    <!-- Jobs Grid -->
    <div class="jobs-grid">
      <?php if ($jobs_result && $jobs_result->num_rows > 0): ?>
        <?php
          $colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706','#dc2626'];
          $ci = 0;
          while ($job = $jobs_result->fetch_assoc()):
            $is_applied = in_array($job['id'], $applied_ids);
            $is_saved   = in_array($job['id'], $saved_ids);
            $logo_color = $colors[$ci % count($colors)];
            $logo_letter = strtoupper(substr($job['client_name'], 0, 1));
            $ci++;
            $tags = array_filter(array_map('trim', explode(',', $job['skills_required'] ?? '')));
        ?>
        <div class="job-card">
          <!-- Top -->
          <div class="job-card-top">
            <div class="client-logo" style="background:<?= $logo_color ?>;">
              <?= $logo_letter ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
              <div class="job-client"><?= htmlspecialchars($job['client_name']) ?></div>
              <div class="job-loc">📍 <?= htmlspecialchars($job['client_loc'] ?? 'Remote') ?></div>
            </div>
          </div>

          <!-- Description -->
          <p style="font-size:0.85rem;color:var(--muted);line-height:1.6;
                    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
            <?= htmlspecialchars($job['description']) ?>
          </p>

          <!-- Tags -->
          <?php if (!empty($tags)): ?>
            <div class="job-tags">
              <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                <span class="tag"><?= htmlspecialchars($tag) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Meta -->
          <div class="job-meta">
            <span>📅 <?= date('d M Y', strtotime($job['created_at'])) ?></span>
            <?php if (!empty($job['job_type'])): ?>
              <span>⏱ <?= htmlspecialchars($job['job_type']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Budget -->
          <?php if (!empty($job['budget'])): ?>
            <div class="job-budget">₹<?= number_format($job['budget']) ?></div>
          <?php endif; ?>

          <!-- Actions -->
          <div class="job-actions">
            <?php if ($is_applied): ?>
              <span class="btn-apply applied">✅ Applied</span>
            <?php else: ?>
              <a href="job_detail.php?id=<?= $job['id'] ?>"
                 class="btn-apply">
                Apply Now
              </a>
            <?php endif; ?>

            <a href="?save=<?= $job['id'] ?>&<?= http_build_query($_GET) ?>"
               class="btn-save <?= $is_saved ? 'saved' : '' ?>"
               title="<?= $is_saved ? 'Remove from saved' : 'Save job' ?>">
              <?= $is_saved ? '🔖' : '📌' ?>
            </a>

            <a href="job_detail.php?id=<?= $job['id'] ?>"
               style="padding:10px 16px;border-radius:100px;
                      background:var(--cream2);border:1px solid var(--border);
                      font-size:0.82rem;font-weight:600;color:var(--ink2);
                      text-decoration:none;transition:background 0.2s;white-space:nowrap;"
               onmouseenter="this.style.background='var(--border)'"
               onmouseleave="this.style.background='var(--cream2)'">
              View →
            </a>
          </div>
        </div>
        <?php endwhile; ?>

      <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon">💼</div>
          <h3>No jobs found</h3>
          <p>Try a different search or category filter.</p>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- page-content -->
</div><!-- main-wrap -->

<?php
// ── HANDLE APPLY (quick apply from list) ──
if (isset($_GET['apply'])) {
    $jid = (int)$_GET['apply'];
    require_once '../includes/db.php';
    $check = $conn->query("SELECT id FROM applications WHERE worker_id=$user_id AND job_id=$jid");
    if ($check->num_rows === 0) {
        $conn->query("INSERT INTO applications (worker_id, job_id, status, applied_at)
                      VALUES ($user_id, $jid, 'pending', NOW())");
    }
    $conn->close();
    // redirect back without apply param
    $params = $_GET;
    unset($params['apply']);
    header("Location: browse_jobs.php?" . http_build_query($params));
    exit();
}

// ── HANDLE SAVE TOGGLE ──
if (isset($_GET['save'])) {
    $jid = (int)$_GET['save'];
    require_once '../includes/db.php';
    $check = $conn->query("SELECT id FROM saved_jobs WHERE worker_id=$user_id AND job_id=$jid");
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM saved_jobs WHERE worker_id=$user_id AND job_id=$jid");
    } else {
        $conn->query("INSERT INTO saved_jobs (worker_id, job_id, saved_at) VALUES ($user_id, $jid, NOW())");
    }
    $conn->close();
    $params = $_GET;
    unset($params['save']);
    header("Location: browse_jobs.php?" . http_build_query($params));
    exit();
}
?>


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