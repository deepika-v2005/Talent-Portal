<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') { header("Location: ../login.php"); exit(); }
$conn = new mysqli("localhost","root","","skillconnect");
$user_id = (int)$_SESSION['user_id']; $user_name = $_SESSION['user_name'];
$profile = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending_apps = (int)$conn->query("SELECT COUNT(*) as c FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.client_id=$user_id AND a.status='pending'")->fetch_assoc()['c'];
$unread_notif = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs  = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$avatar_letter = strtoupper(substr($user_name,0,1)); $first_name = explode(' ',$user_name)[0];
$success=''; $error='';

// ── CHANGE PASSWORD ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_password'])) {
    $curr = $_POST['current_password']??'';
    $newp = $_POST['new_password']??'';
    $conf = $_POST['confirm_password']??'';
    $user = $conn->query("SELECT password FROM users WHERE id=$user_id")->fetch_assoc();
    if (!password_verify($curr,$user['password'])) { $error='Current password is incorrect.'; }
    elseif (strlen($newp)<6) { $error='New password must be at least 6 characters.'; }
    elseif ($newp!==$conf) { $error='Passwords do not match.'; }
    else { $h=password_hash($newp,PASSWORD_DEFAULT); $conn->query("UPDATE users SET password='$h' WHERE id=$user_id"); $success='Password changed successfully!'; }
}

// ── PRIVACY ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_privacy'])) {
    $sp = isset($_POST['show_phone'])?1:0; $se=isset($_POST['show_email'])?1:0;
    $sl = isset($_POST['show_location'])?1:0; $pp=isset($_POST['profile_public'])?1:0;
    $conn->query("UPDATE users SET show_phone=$sp,show_email=$se,show_location=$sl,profile_public=$pp WHERE id=$user_id");
    $success='Privacy settings saved!';
    $profile = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
}
$conn->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Settings – SkillConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;--red:#dc2626;--red-lt:#fef2f2;--sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,.07);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;}
body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;overflow-x:hidden;}
.sidebar{width:var(--sidebar);height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
.sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}.sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}.sb-logo span{color:#9F67FA;}
.sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}
.sb-ava{width:40px;height:40px;border-radius:11px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:400;overflow:hidden;flex-shrink:0;}.sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
.sb-uname{font-size:.86rem;font-weight:400;color:#fff;}.sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
.sb-nav{flex:1;padding:10px 0;}.nav-section{font-size:.62rem;font-weight:400;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
.sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}
.sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}.sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
.nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}.nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:400;padding:2px 6px;border-radius:100px;}
.sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}.sb-logout a{display:flex;align-items:center;justify-content:center;gap:9px;padding:10px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}.sb-logout a:hover{background:rgba(220,38,38,.2);}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}
.topbar{height:60px;padding:0 26px;background:rgba(245,243,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.tb-left{display:flex;align-items:center;gap:9px;}.hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;}
.tb-crumb{font-size:.85rem;color:var(--muted);}.tb-crumb strong{color:var(--ink);font-weight:400;}.tb-right{display:flex;align-items:center;gap:9px;}
.tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;}.tb-btn:hover{background:var(--cream2);}
.tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:400;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--cream);padding:0 2px;}
.tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;text-decoration:none;overflow:hidden;}.tb-ava img{width:35px;height:35px;object-fit:cover;}
.content{padding:24px 26px;flex:1;}.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}.sb-overlay.show{display:block;}
/* SETTINGS */
.settings-wrap{max-width:640px;}
.page-title{font-family:var(--font-h);font-size:1.5rem;font-weight:400;margin-bottom:22px;}
.alert{border-radius:10px;padding:12px 16px;font-size:.86rem;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
.alert.success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
.alert.error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
.card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:20px;box-shadow:var(--shadow);}
.card-title{font-family:var(--font-h);font-size:1rem;font-weight:400;color:var(--ink);margin-bottom:5px;}
.card-sub{font-size:.78rem;color:var(--muted);margin-bottom:18px;}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);}
.toggle-row:last-of-type{border-bottom:none;}
.toggle-label{font-size:.9rem;font-weight:400;color:var(--ink2);}
.toggle-sub{font-size:.75rem;color:var(--muted);margin-top:2px;}
.toggle-sw{position:relative;width:44px;height:24px;background:var(--border);border-radius:100px;cursor:pointer;transition:background .3s;flex-shrink:0;}
.toggle-sw input{display:none;}
.toggle-sw::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .3s;}
.toggle-sw.on{background:var(--teal);}
.toggle-sw.on::after{transform:translateX(20px);}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:.82rem;font-weight:400;color:var(--ink2);margin-bottom:6px;}
.fg input{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-family:var(--font-b);font-size:.9rem;background:var(--cream);color:var(--ink);outline:none;transition:border-color .2s;}
.fg input:focus{border-color:var(--teal);}
.btn-save{padding:11px 28px;background:var(--teal);color:#fff;font-family:var(--font-b);font-size:.9rem;font-weight:400;border:none;border-radius:100px;cursor:pointer;box-shadow:0 4px 14px rgba(124,58,237,.25);transition:background .2s;}
.btn-save:hover{background:#0b6b58;}
@media(max-width:768px){.sidebar{transform:translateX(-240px);}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.content{padding:14px;}.hamburger{display:flex;}.topbar{padding:0 14px;}.tb-crumb{max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}.card{padding:18px;}}

@media(max-width:480px){
  .content{padding:10px;}
  .card{padding:14px;}
  .page-title{font-size:1.25rem;}
  .toggle-row{gap:10px;}
  .toggle-label{font-size:.86rem;}
  .btn-save{width:100%;}
  .card[style*="border-color:#fca5a5"] div[style*="display:flex"]{flex-direction:column;}
  .card[style*="border-color:#fca5a5"] a,.card[style*="border-color:#fca5a5"] button{width:100%;text-align:center;}
}


/* ============ AUTO-GENERATED DARK MODE ============ */
body.dark-mode .card[style*="border-color:#fca5a5"]{border-color:#7f1d1d;}
body.dark-mode a[style*="background:#fef2f2"]{background:rgba(220,38,38,.14) !important;border-color:#7f1d1d !important;}
body.dark-mode{background:#0B1220;color:#E2E8F0;}
body.dark-mode .topbar{background:rgba(11,18,32,.96);border-bottom:1px solid #1E293B;}
body.dark-mode .hamburger{background:#1E293B;border:1px solid #1E293B;}
body.dark-mode .tb-crumb{color:#94A3B8;}
body.dark-mode .tb-crumb strong{color:#E2E8F0;}
body.dark-mode .tb-btn{background:#0B1220;border:1px solid #1E293B;}
body.dark-mode .tb-btn:hover{background:#1E293B;}
body.dark-mode .tb-dot{border:2px solid #0B1220;}
body.dark-mode .alert.success{background:rgba(22,163,74,.14);}
body.dark-mode .alert.error{background:rgba(220,38,38,.14);}
body.dark-mode .card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .card-title{color:#E2E8F0;}
body.dark-mode .card-sub{color:#94A3B8;}
body.dark-mode .toggle-row{border-bottom:1px solid #1E293B;}
body.dark-mode .toggle-label{color:#CBD5E1;}
body.dark-mode .toggle-sub{color:#94A3B8;}
body.dark-mode .toggle-sw{background:#1E293B;}
body.dark-mode .fg label{color:#CBD5E1;}
body.dark-mode .fg input{border:1.5px solid #1E293B;background:#0B1220;color:#E2E8F0;}
</style>
</head>
<body id="body-el">
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>

<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user"><div class="sb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></div><div><div class="sb-uname"><?=htmlspecialchars($first_name)?></div><div class="sb-urole">Client</div></div></div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="post_job.php"><span class="nav-ic"><img src="../assets/icons/request.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Service Request</a>
    <a href="manage_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Jobs</a>
    <a href="applicants.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants<?php if($pending_apps>0): ?><span class="nav-bdg"><?=$pending_apps?></span><?php endif;?></a>
    
    <a href="hired.php"><span class="nav-ic"><img src="../assets/icons/job accepted.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Hired</a>
    <div class="nav-section">Connect</div>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if($unread_msgs>0): ?><span class="nav-bdg"><?=$unread_msgs?></span><?php endif;?></a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if($unread_notif>0): ?><span class="nav-bdg"><?=$unread_notif?></span><?php endif;?></a>
    <div class="nav-section">Account</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a>
    <a href="ratings.php"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php" class="active"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <div class="tb-left"><div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/settings (2).png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;"><span class="tb-crumb">Dashboard / <strong>Settings</strong></span></div>
    <div class="tb-right"><a href="chat.php" class="tb-btn"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a><a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a><a href="profile.php" class="tb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a></div>
  </div>
  <div class="content">
    <div class="settings-wrap">
      <div class="page-title" style="display:flex;align-items:center;gap:9px;"><img src="../assets/icons/settings (2).png" alt="" style="width:45px;height:45px;object-fit:contain;"> Settings</div>
      <?php if($success): ?><div class="alert success">✅ <?=$success?></div><?php endif;?>
      <?php if($error): ?><div class="alert error">⚠️ <?=$error?></div><?php endif;?>

      <!-- Dark Mode -->
      <div class="card">
        <div class="card-title">Appearance</div>
        <div class="card-sub">Customize how SkillConnect looks for you.</div>
        <div class="toggle-row">
          <div><div class="toggle-label">Dark Mode</div><div class="toggle-sub">Switch to a darker interface.</div></div>
          <div class="toggle-sw <?=isset($_COOKIE['dark_mode'])&&$_COOKIE['dark_mode']==='1'?'on':''?>" id="dark-toggle" onclick="toggleDark()"></div>
        </div>
      </div>

      <!-- Change Password -->
      <div class="card">
        <div class="card-title">Change Password</div>
        <div class="card-sub">Keep your account secure with a strong password.</div>
        <form method="POST">
          <div class="fg"><label>Current Password</label><input type="password" name="current_password" placeholder="Enter current password" required></div>
          <div class="fg"><label>New Password</label><input type="password" name="new_password" placeholder="Min. 6 characters" required></div>
          <div class="fg"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Repeat new password" required></div>
          <button type="submit" name="change_password" class="btn-save">🔒 Update Password</button>
        </form>
      </div>

      <!-- Privacy -->
      <div class="card">
        <div class="card-title">Privacy Settings</div>
        <div class="card-sub">Control what workers and visitors can see.</div>
        <form method="POST">
          <div class="toggle-row"><div><div class="toggle-label">Public Profile</div><div class="toggle-sub">Workers can find and view your profile.</div></div><label style="cursor:pointer;"><input type="checkbox" name="profile_public" <?=($profile['profile_public']??1)?'checked':''?> style="width:18px;height:18px;accent-color:var(--teal);cursor:pointer;"></label></div>
          <div class="toggle-row"><div><div class="toggle-label">Show Phone Number</div><div class="toggle-sub">Workers can see your phone number.</div></div><label style="cursor:pointer;"><input type="checkbox" name="show_phone" <?=($profile['show_phone']??0)?'checked':''?> style="width:18px;height:18px;accent-color:var(--teal);cursor:pointer;"></label></div>
          <div class="toggle-row"><div><div class="toggle-label">Show Email Address</div><div class="toggle-sub">Your email visible to connected workers.</div></div><label style="cursor:pointer;"><input type="checkbox" name="show_email" <?=($profile['show_email']??0)?'checked':''?> style="width:18px;height:18px;accent-color:var(--teal);cursor:pointer;"></label></div>
          <div class="toggle-row"><div><div class="toggle-label">Show Location</div><div class="toggle-sub">Your city appears on your profile card.</div></div><label style="cursor:pointer;"><input type="checkbox" name="show_location" <?=($profile['show_location']??1)?'checked':''?> style="width:18px;height:18px;accent-color:var(--teal);cursor:pointer;"></label></div>
          <div style="margin-top:18px;"><button type="submit" name="save_privacy" class="btn-save">🛡️ Save Privacy</button></div>
        </form>
      </div>

      <!-- Danger Zone -->
      <div class="card" style="border-color:#fca5a5;">
        <div class="card-title" style="color:var(--red);">⚠️ Danger Zone</div>
        <div class="card-sub">Permanent actions that cannot be undone.</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="../logout.php" style="padding:10px 22px;background:#fef2f2;color:var(--red);border:1px solid #fca5a5;border-radius:100px;font-size:.86rem;font-weight:400;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;display:block;"><path d="M6 3h9v18H6z"/><path d="M15 3.5l3 1v15l-3 1"/><circle cx="11.5" cy="12" r="0.9" fill="currentColor" stroke="none"/></svg>Logout</a>
          <button onclick="if(confirm('Delete your account permanently?')) window.location='delete_account.php'" style="padding:10px 22px;background:var(--red);color:#fff;border:none;border-radius:100px;font-size:.86rem;font-weight:400;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;">🗑️ Delete Account</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function toggleDark(){const b=document.getElementById('body-el');const t=document.getElementById('dark-toggle');const d=b.classList.contains('dark-mode');if(d){b.classList.remove('dark-mode');t.classList.remove('on');document.cookie='dark_mode=0;path=/;max-age=31536000';}else{b.classList.add('dark-mode');t.classList.add('on');document.cookie='dark_mode=1;path=/;max-age=31536000';}}
window.addEventListener('DOMContentLoaded',()=>{const c=document.cookie.split(';').find(x=>x.trim().startsWith('dark_mode='));if(c&&c.split('=')[1]==='1'){document.getElementById('body-el').classList.add('dark-mode');document.getElementById('dark-toggle').classList.add('on');}});
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

</body></html>