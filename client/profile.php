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

// ── HANDLE UPDATE ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_profile'])) {
    $name     = trim($conn->real_escape_string($_POST['name']??''));
    $phone    = trim($conn->real_escape_string($_POST['phone']??''));
    $location = trim($conn->real_escape_string($_POST['location']??''));
    $bio      = trim($conn->real_escape_string($_POST['bio']??''));
    $pic_sql  = '';
    if (!empty($_FILES['profile_pic']['name'])) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','webp'])) {
            $dir = '../uploads/profiles/';
            if (!is_dir($dir)) mkdir($dir,0755,true);
            $fn = 'client_'.$user_id.'_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'],$dir.$fn))
                $pic_sql = ", profile_pic='$fn'";
        } else { $error='Only JPG/PNG/WEBP allowed.'; }
    }
    if (empty($error)) {
        $conn->query("UPDATE users SET name='$name',phone='$phone',location='$location',bio='$bio'$pic_sql WHERE id=$user_id");
        $_SESSION['user_name'] = $name;
        $success='Profile updated!';
        $profile = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
    }
}
$conn->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Company Profile – SkillConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;--red:#dc2626;--red-lt:#fef2f2;--sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,.07);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;}
body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;overflow-x:hidden;}
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
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}
.topbar{height:60px;padding:0 26px;background:rgba(245,243,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.tb-left{display:flex;align-items:center;gap:9px;}
.hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;}
.tb-crumb{font-size:.85rem;color:var(--muted);} .tb-crumb strong{color:var(--ink);font-weight:400;}
.tb-right{display:flex;align-items:center;gap:9px;}
.tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;}
.tb-btn:hover{background:var(--cream2);}
.tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:400;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--cream);padding:0 2px;}
.tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;text-decoration:none;overflow:hidden;}
.tb-ava img{width:35px;height:35px;object-fit:cover;}
.content{padding:28px 26px;flex:1;}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
.sb-overlay.show{display:block;}
/* FORM */
.form-wrap{max-width:680px;}
.page-title{font-family:var(--font-h);font-size:1.5rem;font-weight:400;margin-bottom:22px;}
.alert{border-radius:10px;padding:12px 16px;font-size:.86rem;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
.alert.success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
.alert.error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
/* Profile header card */
.profile-header{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:26px;margin-bottom:20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:22px;}
.ph-ava{position:relative;flex-shrink:0;}
.ph-img{width:84px;height:84px;border-radius:18px;object-fit:cover;border:3px solid var(--teal);}
.ph-initial{width:84px;height:84px;border-radius:18px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:2rem;font-weight:400;border:3px solid rgba(124,58,237,.3);}
.ph-edit-btn{position:absolute;bottom:-4px;right:-4px;width:28px;height:28px;border-radius:50%;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;cursor:pointer;border:2px solid #fff;}
/* Fields */
.card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:18px;box-shadow:var(--shadow);}
.card-title{font-family:var(--font-h);font-size:1rem;font-weight:400;color:var(--teal);margin-bottom:16px;padding-bottom:10px;border-bottom:1.5px solid var(--teal-lt);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.fg{margin-bottom:16px;}
.fg:last-child{margin-bottom:0;}
.fg label{display:block;font-size:.82rem;font-weight:400;color:var(--ink2);margin-bottom:7px;}
.fg input,.fg textarea{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;background:var(--cream);color:var(--ink);font-family:var(--font-b);font-size:.92rem;outline:none;transition:border-color .2s,background .2s;}
.fg input:focus,.fg textarea:focus{border-color:var(--teal);background:var(--white);box-shadow:0 0 0 3px rgba(124,58,237,.08);}
.fg input[readonly]{background:var(--cream2);color:var(--muted);cursor:not-allowed;}
.fg textarea{resize:vertical;min-height:90px;}
.fg input::placeholder,.fg textarea::placeholder{color:#b5a89a;}
.fg-wrap{position:relative;}
.fg-wrap input,.fg-wrap textarea{padding-right:38px;}
.fg-edit-ic{position:absolute;top:50%;right:12px;transform:translateY(-50%);font-size:.85rem;pointer-events:none;opacity:.75;}
.fg-wrap textarea ~ .fg-edit-ic{top:16px;transform:none;}
.btn-save{padding:12px 34px;background:var(--teal);color:#fff;font-family:var(--font-b);font-size:.95rem;font-weight:400;border:none;border-radius:100px;cursor:pointer;box-shadow:0 4px 16px rgba(124,58,237,.28);transition:background .2s,transform .15s;}
.btn-save:hover{background:#0b6b58;transform:translateY(-1px);}
@media(max-width:768px){.sidebar{transform:translateX(-240px);}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.content{padding:16px;}.hamburger{display:flex;}.topbar{padding:0 14px;}.tb-crumb{max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}.form-row{grid-template-columns:1fr;}.profile-header{flex-direction:column;text-align:center;}}

@media(max-width:480px){
  .content{padding:12px;}
  .card{padding:16px;}
  .profile-header{padding:18px;}
  .ph-img,.ph-initial{width:68px;height:68px;}
  .page-title{font-size:1.25rem;}
  .btn-save{width:100%;}
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
body.dark-mode .alert.success{background:rgba(22,163,74,.14);}
body.dark-mode .alert.error{background:rgba(220,38,38,.14);}
body.dark-mode .ph-edit-btn{border:2px solid #111827;}
body.dark-mode .card-title{border-bottom:1.5px solid rgba(124,58,237,.18);}
body.dark-mode .fg label{color:#CBD5E1;}
body.dark-mode .fg input, body.dark-mode .fg textarea{border:1.5px solid #1E293B;background:#0B1220;color:#E2E8F0;}
body.dark-mode .fg input:focus, body.dark-mode .fg textarea:focus{background:#111827;}
body.dark-mode .fg input[readonly]{background:#1E293B;color:#94A3B8;}
body.dark-mode .card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .profile-header{background:#111827;border:1.5px solid #1E293B;}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></div>
    <div><div class="sb-uname"><?=htmlspecialchars($first_name)?></div><div class="sb-urole">Client</div></div>
  </div>
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
    <a href="profile.php" class="active"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a>
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
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/my application.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb">Dashboard / <strong>Company Profile</strong></span>
    </div>
    <div class="tb-right">
      <a href="chat.php" class="tb-btn"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a>
      <a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a>
      <a href="profile.php" class="tb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a>
    </div>
  </div>

  <div class="content">
    <div class="form-wrap">
      <div class="page-title" style="display:flex;align-items:center;gap:9px;"><img src="../assets/icons/my application.png" alt="" style="width:45px;height:45px;object-fit:contain;"> Company Profile</div>

      <?php if($success): ?><div class="alert success">✅ <?=$success?></div><?php endif;?>
      <?php if($error): ?><div class="alert error">⚠️ <?=$error?></div><?php endif;?>

      <form method="POST" enctype="multipart/form-data">

        <!-- Profile Header -->
        <div class="profile-header">
          <div class="ph-ava">
            <?php if(!empty($profile['profile_pic'])): ?>
              <img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt="" class="ph-img" id="avatarPreview">
            <?php else: ?>
              <div class="ph-initial" id="avatarPreview"><?=$avatar_letter?></div>
            <?php endif;?>
            <label for="profile_pic" class="ph-edit-btn" title="Change photo">✏️</label>
            <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="display:none;" onchange="previewPic(this)">
          </div>
          <div>
            <div style="font-family:var(--font-h);font-size:1.4rem;font-weight:400;"><?=htmlspecialchars($profile['name'])?></div>
            <div style="font-size:.82rem;color:var(--teal);font-weight:400;margin:3px 0;">🏢 Client / Employer</div>
            <div style="font-size:.8rem;color:var(--muted);">📍 <?=htmlspecialchars($profile['location']??'Location not set')?></div>
            <div style="font-size:.78rem;color:var(--muted);margin-top:3px;">✉️ <?=htmlspecialchars($profile['email'])?></div>
          </div>
        </div>

        <!-- Basic Info -->
        <div class="card">
          <div class="card-title">Basic Information</div>
          <div class="form-row">
            <div class="fg">
              <label>Company / Full Name <span style="color:var(--orange);">*</span></label>
              <div class="fg-wrap">
                <input type="text" name="name" value="<?=htmlspecialchars($profile['name'])?>" placeholder="Your company or full name" required>
                <span class="fg-edit-ic">✏️</span>
              </div>
            </div>
            <div class="fg">
              <label>Email Address</label>
              <input type="email" value="<?=htmlspecialchars($profile['email'])?>" readonly>
            </div>
            <div class="fg">
              <label>Phone Number</label>
              <div class="fg-wrap">
                <input type="tel" name="phone" value="<?=htmlspecialchars($profile['phone']??'')?>" placeholder="+91 98765 43210">
                <span class="fg-edit-ic">✏️</span>
              </div>
            </div>
            <div class="fg">
              <label>City / Location <span style="color:var(--orange);">*</span></label>
              <div class="fg-wrap">
                <input type="text" name="location" value="<?=htmlspecialchars($profile['location']??'')?>" placeholder="e.g. Chennai, Tamil Nadu" required>
                <span class="fg-edit-ic">✏️</span>
              </div>
            </div>
          </div>
        </div>

        <!-- About -->
        <div class="card">
          <div class="card-title">About Your Company</div>
          <div class="fg">
            <label>Company Bio / Description</label>
            <div class="fg-wrap">
              <textarea name="bio" placeholder="Describe your company, what you do, and what kind of workers you're looking for..."><?=htmlspecialchars($profile['bio']??'')?></textarea>
              <span class="fg-edit-ic">✏️</span>
            </div>
          </div>
        </div>

        <button type="submit" name="update_profile" class="btn-save">💾 Save Profile</button>
      </form>
    </div>
  </div>
</div>

<script>
function previewPic(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const el = document.getElementById('avatarPreview');
      el.outerHTML = `<img src="${e.target.result}" class="ph-img" id="avatarPreview" style="width:84px;height:84px;border-radius:18px;object-fit:cover;">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

</body></html>