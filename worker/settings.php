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


$success = '';
$error   = '';

// ── CHANGE PASSWORD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current  = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    $user = $conn->query("SELECT password FROM users WHERE id=$user_id")->fetch_assoc();

    if (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_pass) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new_pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hash' WHERE id=$user_id");
        $success = 'Password changed successfully!';
    }
}

// ── PRIVACY SETTINGS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_privacy'])) {
    $show_phone    = isset($_POST['show_phone'])    ? 1 : 0;
    $show_email    = isset($_POST['show_email'])    ? 1 : 0;
    $show_location = isset($_POST['show_location']) ? 1 : 0;
    $profile_public= isset($_POST['profile_public'])? 1 : 0;
    $conn->query("UPDATE users SET
        show_phone=$show_phone, show_email=$show_email,
        show_location=$show_location, profile_public=$profile_public
        WHERE id=$user_id");
    $success = 'Privacy settings saved!';
}

$profile = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$conn->close();

$active_page = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Settings – SkillConnect</title>
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
      --red:#dc2626;
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
.settings-card{
      background:var(--white);border:1px solid var(--border);
      border-radius:var(--radius);padding:28px;margin-bottom:22px;
      box-shadow:var(--shadow);
    }
    .settings-title{
      font-family:var(--font-h);font-size:1.1rem;font-weight:400;
      color:var(--ink);margin-bottom:6px;
    }
    .settings-desc{font-size:0.85rem;color:var(--muted);margin-bottom:20px;}
    .settings-divider{height:1px;background:var(--border);margin:20px 0;}

    /* Toggle switch */
    .toggle-row{
      display:flex;align-items:center;justify-content:space-between;
      padding:12px 0;border-bottom:1px solid var(--border);
    }
    .toggle-row:last-child{border-bottom:none;}
    .toggle-label{font-size:0.9rem;font-weight:400;color:var(--ink2);}
    .toggle-sub{font-size:0.78rem;color:var(--muted);margin-top:2px;}
    .toggle-switch{
      position:relative;width:46px;height:26px;
      background:var(--border);border-radius:100px;
      cursor:pointer;transition:background 0.3s;flex-shrink:0;
    }
    .toggle-switch input{display:none;}
    .toggle-switch::after{
      content:'';position:absolute;top:3px;left:3px;
      width:20px;height:20px;border-radius:50%;
      background:#fff;transition:transform 0.3s;
    }
    .toggle-switch.on{background:var(--orange);}
    .toggle-switch.on::after{transform:translateX(20px);}

    /* Input style */
    .field-input{
      width:100%;padding:10px 14px;border:1.5px solid var(--border);
      border-radius:10px;font-family:var(--font-b);font-size:0.95rem;
      background:var(--cream);color:var(--ink);outline:none;
      transition:border-color 0.2s;margin-top:6px;
    }
    .field-input:focus{border-color:var(--orange);}
    .field-label{font-size:0.8rem;font-weight:400;color:var(--muted);
                 text-transform:uppercase;letter-spacing:1px;}

    .btn-save{
      padding:11px 28px;background:var(--orange);color:#fff;
      font-family:var(--font-b);font-size:0.9rem;font-weight:400;
      border:none;border-radius:100px;cursor:pointer;
      box-shadow:0 4px 14px rgba(232,96,10,0.3);transition:background 0.2s;
    }
    .btn-save:hover{background:#d4540a;}
    .btn-danger{
      padding:11px 28px;background:var(--red);color:#fff;
      font-family:var(--font-b);font-size:0.9rem;font-weight:400;
      border:none;border-radius:100px;cursor:pointer;transition:background 0.2s;
    }
    .btn-danger:hover{background:#b91c1c;}
  
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
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main-wrap{margin-left:0;}
      .hamburger{display:flex;color:var(--ink);}
    }

  </style>
</head>
<body id="body-el">
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
    <a href="profile.php" ><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Profile</a>
    <a href="skill_progress.php" ><span class="nav-ic"><img src="../assets/icons/skill program.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Skill Progress</a>
    <a href="ratings.php" ><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php" class="active"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout">
    <a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a>
  </div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>




<div class="main-wrap">

  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px;">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/settings (2).png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Settings</strong></span></div>
    </div>
  </div>

  <div class="page-content" style="max-width:700px;">

    <?php if ($success): ?>
      <div style="background:var(--green-lt);border:1px solid #86efac;color:var(--green);
                  padding:14px 20px;border-radius:var(--radius);margin-bottom:20px;font-weight:600;">
        ✅ <?= $success ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div style="background:#fef2f2;border:1px solid #fca5a5;color:var(--red);
                  padding:14px 20px;border-radius:var(--radius);margin-bottom:20px;font-weight:600;">
        ❌ <?= $error ?>
      </div>
    <?php endif; ?>

    <!-- ── APPEARANCE ── -->
    <div class="settings-card">
      <div class="settings-title">Appearance</div>
      <div class="settings-desc">Customize how SkillConnect looks for you.</div>

      <div class="toggle-row">
        <div>
          <div class="toggle-label">Dark Mode</div>
          <div class="toggle-sub">Switch to a darker interface to reduce eye strain.</div>
        </div>
        <div class="toggle-switch <?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1') ? 'on' : '' ?>"
             id="dark-toggle"
             onclick="toggleDark()">
        </div>
      </div>
    </div>

    <!-- ── CHANGE PASSWORD ── -->
    <div class="settings-card">
      <div class="settings-title">Change Password</div>
      <div class="settings-desc">Keep your account secure with a strong password.</div>

      <form method="POST">
        <div style="margin-bottom:16px;">
          <div class="field-label">Current Password</div>
          <input type="password" name="current_password" class="field-input"
                 placeholder="Enter current password" required>
        </div>
        <div style="margin-bottom:16px;">
          <div class="field-label">New Password</div>
          <input type="password" name="new_password" class="field-input"
                 placeholder="Min. 6 characters" required>
        </div>
        <div style="margin-bottom:20px;">
          <div class="field-label">Confirm New Password</div>
          <input type="password" name="confirm_password" class="field-input"
                 placeholder="Repeat new password" required>
        </div>
        <button type="submit" name="change_password" class="btn-save">
          🔒 Update Password
        </button>
      </form>
    </div>

    <!-- ── PRIVACY SETTINGS ── -->
    <div class="settings-card">
      <div class="settings-title">Privacy Settings</div>
      <div class="settings-desc">Control what information is visible to clients and recruiters.</div>

      <form method="POST">
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Public Profile</div>
            <div class="toggle-sub">Allow clients to find and view your profile.</div>
          </div>
          <label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="profile_public"
                   <?= ($profile['profile_public'] ?? 1) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--orange);cursor:pointer;">
          </label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Show Phone Number</div>
            <div class="toggle-sub">Clients can see your phone number on your profile.</div>
          </div>
          <label style="cursor:pointer;">
            <input type="checkbox" name="show_phone"
                   <?= ($profile['show_phone'] ?? 0) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--orange);cursor:pointer;">
          </label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Show Email Address</div>
            <div class="toggle-sub">Your email will be visible to connected clients.</div>
          </div>
          <label style="cursor:pointer;">
            <input type="checkbox" name="show_email"
                   <?= ($profile['show_email'] ?? 0) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--orange);cursor:pointer;">
          </label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">Show Location</div>
            <div class="toggle-sub">Your city/area will appear on your profile card.</div>
          </div>
          <label style="cursor:pointer;">
            <input type="checkbox" name="show_location"
                   <?= ($profile['show_location'] ?? 1) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--orange);cursor:pointer;">
          </label>
        </div>
        <div style="margin-top:20px;">
          <button type="submit" name="save_privacy" class="btn-save">
            🛡️ Save Privacy Settings
          </button>
        </div>
      </form>
    </div>

    <!-- ── DANGER ZONE ── -->
    <div class="settings-card" style="border-color:#fca5a5;">
      <div class="settings-title" style="color:var(--red);">⚠️ Danger Zone</div>
      <div class="settings-desc">Permanent actions that cannot be undone.</div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="../logout.php"
           style="padding:11px 24px;background:var(--red);color:#fff;
                  border:none;border-radius:100px;display:inline-flex;align-items:center;
                  font-size:0.88rem;font-weight:400;text-decoration:none;
                  transition:background 0.2s;"
           onmouseenter="this.style.background='#b91c1c'"
           onmouseleave="this.style.background='var(--red)'">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;display:block;margin-right:4px;"><path d="M6 3h9v18H6z"/><path d="M15 3.5l3 1v15l-3 1"/><circle cx="11.5" cy="12" r="0.9" fill="currentColor" stroke="none"/></svg>Logout
        </a>
        <button onclick="confirmDelete()" class="btn-danger">
          🗑️ Delete Account
        </button>
      </div>
    </div>

  </div>
</div>

<script>
// ── DARK MODE ──
function toggleDark() {
  const body   = document.getElementById('body-el');
  const toggle = document.getElementById('dark-toggle');
  const isDark = body.classList.contains('dark-mode');

  if (isDark) {
    body.classList.remove('dark-mode');
    toggle.classList.remove('on');
    document.cookie = 'dark_mode=0;path=/;max-age=31536000';
  } else {
    body.classList.add('dark-mode');
    toggle.classList.add('on');
    document.cookie = 'dark_mode=1;path=/;max-age=31536000';
  }
}

// Apply dark mode on load from cookie
window.addEventListener('DOMContentLoaded', () => {
  const cookies = document.cookie.split(';').map(c => c.trim());
  const dmCookie = cookies.find(c => c.startsWith('dark_mode='));
  if (dmCookie && dmCookie.split('=')[1] === '1') {
    document.getElementById('body-el').classList.add('dark-mode');
    document.getElementById('dark-toggle').classList.add('on');
  }
});

// ── DELETE ACCOUNT CONFIRM ──
function confirmDelete() {
  if (confirm('Are you absolutely sure you want to delete your account?\n\nThis action CANNOT be undone. All your data will be permanently removed.')) {
    if (confirm('Last chance — Delete your account permanently?')) {
      window.location.href = 'delete_account.php';
    }
  }
}
</script>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
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
