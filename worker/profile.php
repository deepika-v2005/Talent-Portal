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


// ── HANDLE PROFILE UPDATE ──
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name     = trim($_POST['name']);
    $phone    = trim($_POST['phone']);
    $location = trim($_POST['location']);
    $bio      = trim($_POST['bio']);
    $skills   = trim($_POST['skills']);
    $edu      = trim($_POST['education']);
    $exp      = trim($_POST['experience']);

    // Handle profile picture upload
    $pic_sql = '';
    if (!empty($_FILES['profile_pic']['name'])) {
        $ext     = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $filename)) {
                $pic_sql = ", profile_pic='$filename'";
            }
        } else {
            $error_msg = 'Only JPG, PNG, WEBP allowed for profile picture.';
        }
    }

    if (empty($error_msg)) {
        $name     = $conn->real_escape_string($name);
        $phone    = $conn->real_escape_string($phone);
        $location = $conn->real_escape_string($location);
        $bio      = $conn->real_escape_string($bio);
        $skills   = $conn->real_escape_string($skills);
        $edu      = $conn->real_escape_string($edu);
        $exp      = $conn->real_escape_string($exp);

        $conn->query("UPDATE users SET
            name='$name', phone='$phone', location='$location',
            bio='$bio', skills='$skills', education='$edu', experience='$exp'
            $pic_sql
            WHERE id=$user_id");

        $_SESSION['user_name'] = $name;
        $success_msg = 'Profile updated successfully!';
    }
}

// ── FETCH PROFILE ──
$profile = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$conn->close();

$avatar_letter = strtoupper(substr($profile['name'], 0, 1));
$active_page = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF; --cream2:#EDE9FE; --white:#fff;
      --ink:#0B1F3A; --ink2:#1E3A5F; --muted:#6B7DB3;
      --orange:#7C3AED; --orange2:#9F67FA;
      --teal:#7C3AED; --teal-lt:#ede9fe;
      --border:#DDD6FE;
      --green:#16a34a; --green-lt:#f0fdf4;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --shadow-md:0 6px 24px rgba(11,31,58,0.10);
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

    body.dark-mode [style*="background:#fef2f2"]{background:rgba(220,38,38,.14) !important;border-color:#7f1d1d !important;}
/* ═══ SIDEBAR CSS ═══ */
    .sidebar{width:240px;height:100vh;background:#0B1F3A;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:900;color:#fff;text-decoration:none;}
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
    .form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main-wrap{margin-left:0;}
      .hamburger{display:flex;color:var(--ink);}
      .page-content{padding:14px;}
      .form-grid-2{grid-template-columns:1fr;}
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
    <a href="profile.php" class="active"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Profile</a>
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

  <!-- Topbar -->
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px;">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/my application.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>My Profile</strong></span></div>
    </div>
    <div class="topbar-right">
      <a href="notifications.php" class="topbar-notif"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><span class="notif-dot"></span></a>
    </div>
  </div>

  <div class="page-content">

    <?php if ($success_msg): ?>
      <div style="background:var(--green-lt);border:1px solid #86efac;color:var(--green);
                  padding:14px 20px;border-radius:var(--radius);margin-bottom:24px;font-weight:600;">
        ✅ <?= $success_msg ?>
      </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
      <div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;
                  padding:14px 20px;border-radius:var(--radius);margin-bottom:24px;font-weight:600;">
        ❌ <?= $error_msg ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

      <!-- Profile Header Card -->
      <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                  padding:28px;margin-bottom:24px;display:flex;align-items:center;gap:24px;
                  box-shadow:var(--shadow);">

        <!-- Avatar -->
        <div style="position:relative;flex-shrink:0;">
          <?php if (!empty($profile['profile_pic'])): ?>
            <img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>"
                 alt="Profile"
                 style="width:90px;height:90px;border-radius:50%;object-fit:cover;
                        border:3px solid var(--orange);">
          <?php else: ?>
            <div style="width:90px;height:90px;border-radius:50%;background:var(--orange);
                        display:flex;align-items:center;justify-content:center;
                        font-family:var(--font-h);font-size:2.2rem;font-weight:900;color:#fff;
                        border:3px solid var(--orange2);">
              <?= $avatar_letter ?>
            </div>
          <?php endif; ?>
          <label for="profile_pic"
                 style="position:absolute;bottom:0;right:0;
                        width:28px;height:28px;border-radius:50%;
                        background:var(--orange);color:#fff;
                        display:flex;align-items:center;justify-content:center;
                        font-size:0.75rem;cursor:pointer;border:2px solid #fff;">
            ✏️
          </label>
          <input type="file" id="profile_pic" name="profile_pic"
                 accept="image/*" style="display:none;"
                 onchange="previewPic(this)">
        </div>

        <!-- Name & Role -->
        <div style="flex:1;">
          <div style="font-family:var(--font-h);font-size:1.5rem;font-weight:400;color:var(--ink);">
            <?= htmlspecialchars($profile['name']) ?>
          </div>
          <div style="color:var(--muted);font-size:0.9rem;margin-top:4px;">
            📍 <?= htmlspecialchars($profile['location'] ?? 'Location not set') ?>
          </div>
          <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
            <?php
              $skills_arr = array_filter(array_map('trim', explode(',', $profile['skills'] ?? '')));
              foreach (array_slice($skills_arr, 0, 4) as $skill):
            ?>
              <span style="background:var(--teal-lt);color:var(--teal);
                           font-size:0.75rem;font-weight:700;padding:4px 12px;border-radius:100px;">
                <?= htmlspecialchars($skill) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Form Grid -->
      <div class="form-grid-2">

        <!-- Full Name -->
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);">
          <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:700;color:var(--muted);
                        text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
            <span>Full Name</span>
            <span class="field-edit-icon" onclick="toggleEdit('name_input', this)" title="Edit">✏️</span>
          </label>
          <input type="text" name="name" id="name_input"
                 value="<?= htmlspecialchars($profile['name']) ?>"
                 required readonly
                 style="width:100%;padding:10px 14px;border:1.5px solid var(--border);
                        border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
                        background:var(--cream2);color:var(--muted);outline:none;cursor:not-allowed;
                        transition:border-color 0.2s,background 0.2s,color 0.2s;"
                 onfocus="this.style.borderColor='var(--orange)'"
                 onblur="this.style.borderColor='var(--border)'">
        </div>

        <!-- Email (read-only) -->
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);">
          <label style="display:block;font-size:0.8rem;font-weight:700;color:var(--muted);
                        text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
            Email Address
          </label>
          <input type="email"
                 value="<?= htmlspecialchars($profile['email']) ?>"
                 readonly
                 style="width:100%;padding:10px 14px;border:1.5px solid var(--border);
                        border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
                        background:var(--cream2);color:var(--muted);cursor:not-allowed;">
        </div>

        <!-- Phone -->
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);">
          <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:700;color:var(--muted);
                        text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
            <span>Phone Number</span>
            <span class="field-edit-icon" onclick="toggleEdit('phone_input', this)" title="Edit">✏️</span>
          </label>
          <input type="tel" name="phone" id="phone_input"
                 value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"
                 placeholder="+91 9876543210" readonly
                 style="width:100%;padding:10px 14px;border:1.5px solid var(--border);
                        border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
                        background:var(--cream2);color:var(--muted);outline:none;cursor:not-allowed;
                        transition:border-color 0.2s,background 0.2s,color 0.2s;"
                 onfocus="this.style.borderColor='var(--orange)'"
                 onblur="this.style.borderColor='var(--border)'">
        </div>

        <!-- Location -->
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);">
          <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:700;color:var(--muted);
                        text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
            <span>Location</span>
            <span class="field-edit-icon" onclick="toggleEdit('location_input', this)" title="Edit">✏️</span>
          </label>
          <input type="text" name="location" id="location_input"
                 value="<?= htmlspecialchars($profile['location'] ?? '') ?>"
                 placeholder="Chennai, Tamil Nadu" readonly
                 style="width:100%;padding:10px 14px;border:1.5px solid var(--border);
                        border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
                        background:var(--cream2);color:var(--muted);outline:none;cursor:not-allowed;
                        transition:border-color 0.2s,background 0.2s,color 0.2s;"
                 onfocus="this.style.borderColor='var(--orange)'"
                 onblur="this.style.borderColor='var(--border)'">
        </div>

      </div><!-- end grid -->

      <!-- Skills -->
      <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                  padding:22px;margin-bottom:20px;box-shadow:var(--shadow);">
        <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:700;color:var(--muted);
                      text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
          <span>Skills <span style="font-weight:400;text-transform:none;letter-spacing:0;">(comma separated)</span></span>
          <span class="field-edit-icon" onclick="toggleEdit('skills_input', this)" title="Edit">✏️</span>
        </label>
        <input type="text" name="skills" id="skills_input"
               value="<?= htmlspecialchars($profile['skills'] ?? '') ?>"
               placeholder="Photography, Photo Editing, Mehndi, Bridal Makeup" readonly
               style="width:100%;padding:10px 14px;border:1.5px solid var(--border);
                      border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
                      background:var(--cream2);color:var(--muted);outline:none;cursor:not-allowed;
                      transition:border-color 0.2s,background 0.2s,color 0.2s;"
               onfocus="this.style.borderColor='var(--orange)'"
               onblur="this.style.borderColor='var(--border)'">
        <div id="skill-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;"></div>
      </div>

      <!-- Bio -->
      <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                  padding:22px;margin-bottom:20px;box-shadow:var(--shadow);">
        <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:700;color:var(--muted);
                      text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
          <span>About / Bio</span>
          <span class="field-edit-icon" onclick="toggleEdit('bio_input', this)" title="Edit">✏️</span>
        </label>
        <textarea name="bio" id="bio_input" rows="4" readonly
                  placeholder="Tell clients about yourself, your expertise and what makes you unique..."
                  style="width:100%;padding:10px 14px;border:1.5px solid var(--border);
                         border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
                         background:var(--cream2);color:var(--muted);outline:none;resize:vertical;cursor:not-allowed;
                         transition:border-color 0.2s,background 0.2s,color 0.2s;"
                  onfocus="this.style.borderColor='var(--orange)'"
                  onblur="this.style.borderColor='var(--border)'"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
      </div>

      <!-- Education -->
      <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                  padding:22px;margin-bottom:20px;box-shadow:var(--shadow);">
        <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:700;color:var(--muted);
                      text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
          <span style="display:flex;align-items:center;gap:7px;">
            <span style="width:26px;height:26px;border-radius:8px;background:var(--teal-lt);
                         display:flex;align-items:center;justify-content:center;font-size:0.9rem;">🎓</span>
            Education
          </span>
          <span class="field-edit-icon" onclick="toggleEdit('education_input', this)" title="Edit">✏️</span>
        </label>
        <div style="font-size:0.72rem;color:var(--muted);margin-bottom:8px;">
          One qualification per line — e.g. "B.Sc Photography – Chennai Arts College (2020)"
        </div>
        <textarea name="education" id="education_input" rows="3" readonly
                  placeholder="B.Sc Photography – Chennai Arts College (2020)&#10;Diploma in Mehndi Art – State Institute (2018)"
                  style="width:100%;padding:10px 14px;border:1.5px solid var(--border);
                         border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
                         background:var(--cream2);color:var(--muted);outline:none;resize:vertical;cursor:not-allowed;
                         transition:border-color 0.2s,background 0.2s,color 0.2s;"
                  onfocus="this.style.borderColor='var(--orange)'"
                  onblur="this.style.borderColor='var(--border)'"><?= htmlspecialchars($profile['education'] ?? '') ?></textarea>
        <div id="education-preview" style="display:flex;flex-direction:column;gap:8px;margin-top:12px;"></div>
      </div>

      <!-- Experience -->
      <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                  padding:22px;margin-bottom:28px;box-shadow:var(--shadow);">
        <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:700;color:var(--muted);
                      text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
          <span>Experience</span>
          <span class="field-edit-icon" onclick="toggleEdit('experience_input', this)" title="Edit">✏️</span>
        </label>
        <textarea name="experience" id="experience_input" rows="3" readonly
                  placeholder="Freelance Photographer – 3 years (Weddings, Events, Portraits)&#10;Mehndi Artist – Bridal Bliss Studio (2021–2023)"
                  style="width:100%;padding:10px 14px;border:1.5px solid var(--border);
                         border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
                         background:var(--cream2);color:var(--muted);outline:none;resize:vertical;cursor:not-allowed;
                         transition:border-color 0.2s,background 0.2s,color 0.2s;"
                  onfocus="this.style.borderColor='var(--orange)'"
                  onblur="this.style.borderColor='var(--border)'"><?= htmlspecialchars($profile['experience'] ?? '') ?></textarea>
      </div>

      <!-- Save Button -->
      <button type="submit" name="update_profile"
              style="padding:14px 40px;background:var(--orange);color:#fff;
                     font-family:var(--font-b);font-size:1rem;font-weight:400;
                     border:none;border-radius:100px;cursor:pointer;
                     box-shadow:0 4px 18px rgba(232,96,10,0.35);
                     transition:background 0.2s,transform 0.15s;">
        💾 Save Profile
      </button>

    </form>
  </div><!-- page-content -->
</div><!-- main-wrap -->

<script>
// Live skill tags preview
const skillInput = document.querySelector('input[name="skills"]');
const tagsDiv    = document.getElementById('skill-tags');

function renderTags(val) {
  const skills = val.split(',').map(s => s.trim()).filter(Boolean);
  tagsDiv.innerHTML = skills.map(s =>
    `<span style="background:var(--teal-lt);color:var(--teal);
                  font-size:0.78rem;font-weight:700;padding:4px 12px;
                  border-radius:100px;">${s}</span>`
  ).join('');
}

if (skillInput) {
  renderTags(skillInput.value);
  skillInput.addEventListener('input', () => renderTags(skillInput.value));
}

// Live education entries preview
const eduInput = document.getElementById('education_input');
const eduPreview = document.getElementById('education-preview');

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function renderEducation(val) {
  const lines = val.split('\n').map(s => s.trim()).filter(Boolean);
  eduPreview.innerHTML = lines.map(line =>
    `<div style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;
                 background:var(--cream);border:1px solid var(--border);border-radius:10px;">
       <span style="color:var(--teal);font-size:0.9rem;line-height:1;margin-top:1px;">🎓</span>
       <span style="font-size:0.88rem;color:var(--ink);line-height:1.4;">${escapeHtml(line)}</span>
     </div>`
  ).join('');
}

if (eduInput && eduPreview) {
  renderEducation(eduInput.value);
  eduInput.addEventListener('input', () => renderEducation(eduInput.value));
}

// Profile pic preview
function previewPic(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.querySelector('.sb-avatar img, img[alt="Profile"]');
      const avatarDiv = document.querySelector('.sb-avatar');
      if (img) {
        img.src = e.target.result;
      } else if (avatarDiv) {
        avatarDiv.innerHTML = `<img src="${e.target.result}"
          style="width:90px;height:90px;border-radius:50%;object-fit:cover;">`;
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// Save button hover
const saveBtn = document.querySelector('button[name="update_profile"]');
if (saveBtn) {
  saveBtn.addEventListener('mouseenter', () => {
    saveBtn.style.background = '#d4540a';
    saveBtn.style.transform  = 'translateY(-1px)';
  });
  saveBtn.addEventListener('mouseleave', () => {
    saveBtn.style.background = 'var(--orange)';
    saveBtn.style.transform  = 'none';
  });
}

// ── EDIT ICON TOGGLE ──
// Clicking the pencil icon next to a field unlocks it for editing.
// Clicking it again (now a check mark) locks it back to read-only view.
function toggleEdit(fieldId, iconEl) {
  const field = document.getElementById(fieldId);
  if (!field) return;

  const isLocked = field.hasAttribute('readonly');

  if (isLocked) {
    // Unlock for editing
    field.removeAttribute('readonly');
    field.style.background = 'var(--cream)';
    field.style.color      = 'var(--ink)';
    field.style.cursor     = 'text';
    field.style.borderColor = 'var(--orange)';
    iconEl.textContent = '✅';
    iconEl.title = 'Done editing';
    field.focus();
    if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
      const val = field.value;
      field.value = '';
      field.value = val; // moves cursor to end
    }
  } else {
    // Lock back to read-only view
    field.setAttribute('readonly', 'readonly');
    field.style.background = 'var(--cream2)';
    field.style.color      = 'var(--muted)';
    field.style.cursor     = 'not-allowed';
    field.style.borderColor = 'var(--border)';
    iconEl.textContent = '✏️';
    iconEl.title = 'Edit';
  }
}
</script>

<style>
.field-edit-icon{
  cursor:pointer;
  font-size:0.9rem;
  padding:3px 6px;
  border-radius:6px;
  transition:background 0.15s;
  flex-shrink:0;
}
.field-edit-icon:hover{
  background:var(--teal-lt);
}

  </style>

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