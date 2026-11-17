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


// ── FETCH WORKER SKILLS ──
$profile = $conn->query("SELECT skills FROM users WHERE id=$user_id")->fetch_assoc();
$skills_raw = $profile['skills'] ?? '';
$skills_arr = array_filter(array_map('trim', explode(',', $skills_raw)));

// ── BUILD SKILL-BASED JOB QUERY ──
$applied_ids = [];
$ar = $conn->query("SELECT job_id FROM applications WHERE worker_id=$user_id");
while ($r = $ar->fetch_assoc()) $applied_ids[] = $r['job_id'];

$saved_ids = [];
$sr = $conn->query("SELECT job_id FROM saved_jobs WHERE worker_id=$user_id");
while ($r = $sr->fetch_assoc()) $saved_ids[] = $r['job_id'];

$recommended = [];
$other_jobs  = [];

if (!empty($skills_arr)) {
    // Jobs that match any of the worker's skills
    $skill_conditions = implode(' OR ', array_map(function($s) use ($conn) {
        $s = $conn->real_escape_string($s);
        return "j.skills_required LIKE '%$s%' OR j.title LIKE '%$s%'";
    }, $skills_arr));

    $rec_res = $conn->query("
        SELECT j.*, u.name AS client_name, u.location AS client_loc
        FROM jobs j
        JOIN users u ON j.client_id = u.id
        WHERE j.status='open' AND ($skill_conditions)
        ORDER BY j.created_at DESC
        LIMIT 20
    ");
    while ($r = $rec_res->fetch_assoc()) $recommended[] = $r;
}

// ── OTHER OPEN JOBS (if skills not set or to fill page) ──
$exclude_ids = array_merge(
    array_column($recommended, 'id'),
    [0]
);
$excl = implode(',', $exclude_ids);
$others_res = $conn->query("
    SELECT j.*, u.name AS client_name, u.location AS client_loc
    FROM jobs j
    JOIN users u ON j.client_id = u.id
    WHERE j.status='open' AND j.id NOT IN ($excl)
    ORDER BY j.created_at DESC
    LIMIT 12
");
while ($r = $others_res->fetch_assoc()) $other_jobs[] = $r;

$conn->close();
$active_page = 'recommended';
$colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706','#dc2626','#059669'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Recommended Jobs – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--orange2:#9F67FA;
      --teal:#7C3AED;--teal-lt:#ede9fe;
      --border:#DDD6FE;
      --green:#16a34a;--green-lt:#f0fdf4;
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
      --border:#1E293B;--teal-lt:rgba(124,58,237,.18);--green-lt:rgba(22,163,74,.14);
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

    body.dark-mode .matched-tag{background:rgba(124,58,237,.18) !important;}
    body.dark-mode [style*="background:#fff7ed"]{background:rgba(124,58,237,.18) !important;}
.section-title{
      font-family:var(--font-h);font-size:1.15rem;font-weight:900;color:var(--ink);
      margin-bottom:18px;display:flex;align-items:center;gap:8px;
    }
    .jobs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:18px;margin-bottom:36px;}

    .job-card{
      background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
      padding:20px;box-shadow:var(--shadow);transition:transform 0.2s,box-shadow 0.2s;
      position:relative;
    }
    .job-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(11,31,58,0.12);}
    .match-badge{
      position:absolute;top:14px;right:14px;
      background:var(--orange);color:#fff;
      font-size:0.68rem;font-weight:800;padding:3px 10px;
      border-radius:100px;letter-spacing:0.5px;
    }
    .client-logo{
      width:44px;height:44px;border-radius:12px;flex-shrink:0;
      display:flex;align-items:center;justify-content:center;
      font-family:var(--font-h);font-size:1rem;font-weight:900;color:#fff;
    }
    .job-title{font-family:var(--font-h);font-size:1rem;font-weight:400;color:var(--ink);margin-bottom:3px;}
    .job-client{font-size:0.82rem;color:var(--muted);font-weight:600;}
    .tag{font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:100px;background:var(--teal-lt);color:var(--teal);}
    .matched-tag{background:#fff7ed;color:var(--orange);}

    .btn-apply{
      display:block;width:100%;padding:9px;border-radius:100px;text-align:center;
      background:var(--orange);color:#fff;font-family:var(--font-b);
      font-size:0.85rem;font-weight:700;text-decoration:none;border:none;cursor:pointer;
      transition:background 0.2s;margin-top:14px;
    }
    .btn-apply:hover{background:#d4540a;}
    .btn-applied{background:var(--green-lt);color:var(--green);pointer-events:none;border:1px solid #86efac;}
  
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
    .main-wrap{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh;}
    .topbar{height:60px;padding:0 26px;background:var(--white);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
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
    .page-content{padding:24px 26px;flex:1;}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main-wrap{margin-left:0;}
      .hamburger{display:flex;color:var(--ink);}
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
    <a href="applications.php" >
      <span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Applications
      <?php if ($pending > 0): ?><span class="nav-bdg"><?= $pending ?></span><?php endif; ?>
    </a>
    <a href="saved_jobs.php" >
      <span class="nav-ic"><img src="../assets/icons/saved jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Saved Jobs
      <?php if ($saved_count > 0): ?><span class="nav-bdg"><?= $saved_count ?></span><?php endif; ?>
    </a>
    <a href="recommended.php" class="active"><span class="nav-ic"><img src="../assets/icons/recommend jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Recommended</a>
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
    <div style="display:flex;align-items:center;gap:10px;">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/recommend jobs.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Recommended Jobs</strong></span></div>
    </div>
    <div class="topbar-right">
      <?php if (empty($skills_arr)): ?>
        <a href="profile.php"
           style="font-size:0.82rem;background:#fff7ed;color:var(--orange);
                  border:1px solid #fed7aa;border-radius:100px;padding:6px 14px;
                  font-weight:700;text-decoration:none;">
          ⚠️ Add skills for better matches
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="page-content">

    <!-- Your Skills Banner -->
    <?php if (!empty($skills_arr)): ?>
      <div style="background:linear-gradient(135deg,#fff7ed,#fef3c7);
                  border:1px solid #fed7aa;border-radius:var(--radius);
                  padding:18px 22px;margin-bottom:28px;
                  display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <span style="font-size:1.3rem;">✨</span>
        <div style="flex:1;">
          <div style="font-weight:400;color:var(--ink2);margin-bottom:6px;">
            Matching jobs based on your skills:
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($skills_arr as $sk): ?>
              <span style="background:var(--orange);color:#fff;font-size:0.75rem;
                           font-weight:400;padding:3px 12px;border-radius:100px;">
                <?= htmlspecialchars($sk) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="profile.php"
           style="font-size:0.82rem;color:var(--orange);font-weight:400;text-decoration:none;">
          Edit Skills →
        </a>
      </div>
    <?php endif; ?>

    <!-- Recommended Section -->
    <?php if (!empty($recommended)): ?>
      <div class="section-title">✨ Recommended For You
        <span style="font-size:0.82rem;font-family:var(--font-b);font-weight:400;
                     color:var(--muted);margin-left:4px;"><?= count($recommended) ?> matches</span>
      </div>
      <div class="jobs-grid">
        <?php foreach ($recommended as $i => $job):
          $is_applied = in_array($job['id'], $applied_ids);
          $is_saved   = in_array($job['id'], $saved_ids);
          $col        = $colors[$i % count($colors)];
          $tags       = array_filter(array_map('trim', explode(',', $job['skills_required'] ?? '')));

          // Find which skills matched
          $matched = array_filter($tags, function($t) use ($skills_arr) {
              foreach ($skills_arr as $s) {
                  if (stripos($t,$s)!==false || stripos($s,$t)!==false) return true;
              }
              return false;
          });
        ?>
          <div class="job-card">
            <?php if (!empty($matched)): ?>
              <div class="match-badge">✨ Skill Match</div>
            <?php endif; ?>

            <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;padding-right:<?= !empty($matched)?'80px':'0' ?>;">
              <div class="client-logo" style="background:<?= $col ?>;">
                <?= strtoupper(substr($job['client_name'],0,1)) ?>
              </div>
              <div>
                <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
                <div class="job-client"><?= htmlspecialchars($job['client_name']) ?> · 📍 <?= htmlspecialchars($job['client_loc'] ?? 'N/A') ?></div>
              </div>
            </div>

            <p style="font-size:0.83rem;color:var(--muted);line-height:1.55;margin-bottom:10px;
                      display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
              <?= htmlspecialchars($job['description']) ?>
            </p>

            <?php if (!empty($tags)): ?>
              <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px;">
                <?php foreach (array_slice($tags,0,4) as $t): ?>
                  <span class="tag <?= in_array($t, $matched)?'matched-tag':'' ?>">
                    <?= in_array($t,$matched)?'✓ ':'' ?><?= htmlspecialchars($t) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($job['budget'])): ?>
              <div style="font-family:var(--font-h);font-size:1rem;font-weight:900;color:var(--orange);">
                ₹<?= number_format($job['budget']) ?>
              </div>
            <?php endif; ?>

            <?php if ($is_applied): ?>
              <span class="btn-apply btn-applied">✅ Already Applied</span>
            <?php else: ?>
              <a href="browse_jobs.php?apply=<?= $job['id'] ?>" class="btn-apply"
                 onclick="return confirm('Apply for this job?')">Apply Now</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align:center;padding:40px 20px;color:var(--muted);
                  background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                  margin-bottom:28px;">
        <div style="font-size:2rem;margin-bottom:10px;">🎯</div>
        <div style="font-weight:400;color:var(--ink2);margin-bottom:6px;">No skill-based matches yet</div>
        <div style="font-size:0.85rem;">
          <a href="profile.php" style="color:var(--orange);font-weight:400;">Add your skills</a> 
          to get personalized job recommendations.
        </div>
      </div>
    <?php endif; ?>

    <!-- Other Jobs -->
    <?php if (!empty($other_jobs)): ?>
      <div class="section-title">💼 Other Open Jobs</div>
      <div class="jobs-grid">
        <?php foreach ($other_jobs as $i => $job):
          $is_applied = in_array($job['id'], $applied_ids);
          $col  = $colors[($i+4) % count($colors)];
          $tags = array_filter(array_map('trim', explode(',', $job['skills_required'] ?? '')));
        ?>
          <div class="job-card">
            <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;">
              <div class="client-logo" style="background:<?= $col ?>;">
                <?= strtoupper(substr($job['client_name'],0,1)) ?>
              </div>
              <div>
                <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
                <div class="job-client"><?= htmlspecialchars($job['client_name']) ?> · 📍 <?= htmlspecialchars($job['client_loc'] ?? 'N/A') ?></div>
              </div>
            </div>
            <?php if (!empty($tags)): ?>
              <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px;">
                <?php foreach (array_slice($tags,0,3) as $t): ?>
                  <span class="tag"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($job['budget'])): ?>
              <div style="font-family:var(--font-h);font-size:0.95rem;font-weight:900;color:var(--orange);margin-bottom:10px;">
                ₹<?= number_format($job['budget']) ?>
              </div>
            <?php endif; ?>
            <?php if ($is_applied): ?>
              <span class="btn-apply btn-applied">✅ Applied</span>
            <?php else: ?>
              <a href="browse_jobs.php?apply=<?= $job['id'] ?>" class="btn-apply"
                 onclick="return confirm('Apply for this job?')">Apply Now</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

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
