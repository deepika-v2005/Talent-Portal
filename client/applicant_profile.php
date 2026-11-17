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

$worker_id = (int)($_GET['id'] ?? 0);
if (!$worker_id) { header("Location: applicants.php"); exit(); }

// ── FETCH WORKER ──
$worker = $conn->query("SELECT * FROM users WHERE id=$worker_id AND role='worker'")->fetch_assoc();
if (!$worker) { header("Location: applicants.php"); exit(); }

// ── RATINGS ──
$rating_row = $conn->query("SELECT AVG(stars) as avg, COUNT(*) as total FROM ratings WHERE worker_id=$worker_id")->fetch_assoc();
$avg_rating = $rating_row['avg'] ? number_format($rating_row['avg'],1) : '—';
$total_ratings = (int)$rating_row['total'];

// ── PORTFOLIO ──
$posts = $conn->query("SELECT * FROM posts WHERE worker_id=$worker_id ORDER BY created_at DESC LIMIT 9");

// ── SKILL PROGRESS ──
$skills_progress = $conn->query("SELECT * FROM worker_skills WHERE worker_id=$worker_id ORDER BY id ASC LIMIT 6");

// ── APPLICATION STATUS with this client ──
$app_status = $conn->query("
    SELECT a.id, a.status FROM applications a
    JOIN jobs j ON a.job_id=j.id
    WHERE j.client_id=$user_id AND a.worker_id=$worker_id
    ORDER BY a.applied_at DESC LIMIT 1
")->fetch_assoc();

// ── REVIEWS ON THIS WORKER ──
$reviews = $conn->query("
    SELECT r.*,u.name AS reviewer_name FROM ratings r
    JOIN users u ON u.id=r.client_id
    WHERE r.worker_id=$worker_id ORDER BY r.created_at DESC LIMIT 5
");

$conn->close();

$w_letter = strtoupper(substr($worker['name'],0,1));
$bar_colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed'];
function stars_html($n){$o='';for($i=1;$i<=5;$i++) $o.=$i<=$n?'★':'☆';return $o;}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?=htmlspecialchars($worker['name'])?> – SkillConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--blue:#1d4ed8;--blue-lt:#eff6ff;--border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;--yellow:#d97706;--yellow-lt:#fef3c7;--purple:#7c3aed;--purple-lt:#f3e8ff;--sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,.07);--shadow-md:0 6px 24px rgba(11,31,58,.1);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;}
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

/* PROFILE HERO */
.profile-hero{background:linear-gradient(135deg,var(--orange) 0%,#9F67FA 100%);border-radius:18px;padding:26px 30px;margin-bottom:22px;display:flex;gap:22px;align-items:center;position:relative;overflow:hidden;}
.profile-hero::before{content:'';position:absolute;top:-50px;right:-50px;width:180px;height:180px;background:rgba(255,255,255,.08);border-radius:50%;}
.ph-ava{width:80px;height:80px;border-radius:18px;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:2rem;font-weight:400;color:#fff;flex-shrink:0;overflow:hidden;position:relative;z-index:1;}
.ph-ava img{width:80px;height:80px;object-fit:cover;}
.ph-info{flex:1;position:relative;z-index:1;}
.ph-name{font-family:var(--font-h);font-size:1.7rem;font-weight:400;color:#fff;margin-bottom:4px;}
.ph-loc{font-size:.85rem;color:rgba(255,255,255,.82);margin-bottom:10px;}
.ph-skills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
.ph-skill{background:rgba(255,255,255,.2);color:#fff;font-size:.74rem;font-weight:400;padding:3px 10px;border-radius:100px;border:1px solid rgba(255,255,255,.3);}
.ph-rating{font-size:.85rem;color:rgba(255,255,255,.85);font-weight:400;}
.ph-actions{display:flex;flex-direction:column;gap:8px;z-index:1;}
.btn-action{padding:9px 18px;border-radius:100px;font-family:var(--font-b);font-size:.82rem;font-weight:400;text-decoration:none;border:none;cursor:pointer;text-align:center;white-space:nowrap;transition:all .2s;}
.btn-chat{background:#fff;color:var(--orange);}
.btn-chat:hover{background:rgba(255,255,255,.9);}
.btn-rate-link{background:rgba(255,255,255,.18);color:#fff;border:1.5px solid rgba(255,255,255,.38);}
.btn-rate-link:hover{background:rgba(255,255,255,.25);}

/* STATUS BANNER */
.status-banner{border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:.88rem;font-weight:400;display:flex;align-items:center;gap:9px;}
.sb-pending{background:var(--yellow-lt);border:1px solid #fde68a;color:var(--yellow);}
.sb-accepted{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
.sb-rejected{background:#fef2f2;border:1px solid #fecaca;color:var(--red);}
.sb-interview{background:var(--purple-lt);border:1px solid #ddd6fe;color:var(--purple);}

/* TWO COL */
.two-col{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;}
.card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;box-shadow:var(--shadow);}
.card:last-child{margin-bottom:0;}
.card-title{font-family:var(--font-h);font-size:1rem;font-weight:400;margin-bottom:14px;}

/* PORTFOLIO */
.portfolio-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
.p-item{aspect-ratio:1/1;border-radius:10px;overflow:hidden;position:relative;cursor:pointer;}
.p-item img,.p-item video{width:100%;height:100%;object-fit:cover;}
.p-item:hover .p-overlay{opacity:1;}
.p-overlay{position:absolute;inset:0;background:rgba(0,0,0,.5);opacity:0;transition:opacity .2s;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:400;}

/* SKILL BARS */
.sk-row{margin-bottom:11px;}.sk-row:last-child{margin-bottom:0;}
.sk-top{display:flex;justify-content:space-between;margin-bottom:4px;}
.sk-name{font-size:.82rem;font-weight:400;color:var(--ink2);}
.sk-pct{font-size:.74rem;font-weight:400;}
.sk-bg{height:7px;border-radius:100px;background:var(--cream2);overflow:hidden;}
.sk-fill{height:100%;border-radius:100px;width:0;transition:width 1.2s ease;}

/* REVIEWS */
.review-card{background:var(--cream);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:10px;}
.review-card:last-child{margin-bottom:0;}
.rv-stars{color:#f59e0b;font-size:.9rem;margin-bottom:5px;}
.rv-text{font-size:.83rem;color:var(--ink2);line-height:1.5;margin-bottom:5px;}
.rv-author{font-size:.72rem;color:var(--muted);}

@media(max-width:1000px){.two-col{grid-template-columns:1fr;}}
@media(max-width:768px){.sidebar{transform:translateX(-240px);}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.content{padding:14px;}.hamburger{display:flex;}.profile-hero{flex-direction:column;}.ph-actions{flex-direction:row;flex-wrap:wrap;}.portfolio-grid{grid-template-columns:repeat(2,1fr);}}


/* ============ AUTO-GENERATED DARK MODE ============ */
body.dark-mode{background:#0B1220;color:#E2E8F0;}
body.dark-mode .topbar{background:rgba(11,18,32,.96);border-bottom:1px solid #1E293B;}
body.dark-mode .hamburger{background:#1E293B;border:1px solid #1E293B;}
body.dark-mode .tb-crumb{color:#94A3B8;}
body.dark-mode .tb-crumb strong{color:#E2E8F0;}
body.dark-mode .tb-btn{background:#0B1220;border:1px solid #1E293B;}
body.dark-mode .tb-btn:hover{background:#1E293B;}
body.dark-mode .tb-dot{border:2px solid #0B1220;}
body.dark-mode .btn-chat{background:#111827;}
body.dark-mode .btn-chat:hover{background:rgba(17,24,39,.92);}
body.dark-mode .card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .sk-name{color:#CBD5E1;}
body.dark-mode .sk-bg{background:#1E293B;}
body.dark-mode .rv-text{color:#CBD5E1;}
body.dark-mode .rv-author{color:#94A3B8;}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>

<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user"><div class="sb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></div><div><div class="sb-uname"><?=htmlspecialchars($first_name)?></div><div class="sb-urole">Client</div></div></div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a><a href="post_job.php"><span class="nav-ic"><img src="../assets/icons/request.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Service Request</a><a href="manage_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Jobs</a>
    <a href="applicants.php" class="active"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants<?php if($pending_apps>0): ?><span class="nav-bdg"><?=$pending_apps?></span><?php endif;?></a>
    <a href="hired.php"><span class="nav-ic"><img src="../assets/icons/job accepted.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Hired</a>
    <div class="nav-section">Connect</div>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if($unread_msgs>0): ?><span class="nav-bdg"><?=$unread_msgs?></span><?php endif;?></a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if($unread_notif>0): ?><span class="nav-bdg"><?=$unread_notif?></span><?php endif;?></a>
    <div class="nav-section">Account</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a><a href="ratings.php"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a><a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/application.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb"><a href="applicants.php" style="color:var(--teal);text-decoration:none;">Applicants</a> / <strong><?=htmlspecialchars($worker['name'])?></strong></span>
    </div>
    <div class="tb-right">
      <a href="chat.php" class="tb-btn">💬<?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a>
      <a href="notifications.php" class="tb-btn">🔔<?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a>
      <a href="profile.php" class="tb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a>
    </div>
  </div>

  <div class="content">

    <!-- PROFILE HERO -->
    <div class="profile-hero">
      <div class="ph-ava">
        <?php if(!empty($worker['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($worker['profile_pic'])?>" alt=""><?php else: ?><?=$w_letter?><?php endif;?>
      </div>
      <div class="ph-info">
        <div class="ph-name"><?=htmlspecialchars($worker['name'])?></div>
        <div class="ph-loc">📍 <?=htmlspecialchars($worker['location']??'N/A')?><?php if(!empty($worker['phone'])): ?> · 📱 <?=htmlspecialchars($worker['phone'])?><?php endif;?></div>
        <?php $skills=array_filter(array_map('trim',explode(',',$worker['skills']??''))); if(!empty($skills)): ?>
          <div class="ph-skills"><?php foreach(array_slice($skills,0,5) as $sk): ?><span class="ph-skill"><?=htmlspecialchars($sk)?></span><?php endforeach;?></div>
        <?php endif;?>
        <div class="ph-rating">⭐ <?=$avg_rating?> / 5.0<?php if($total_ratings>0): ?> · <?=$total_ratings?> review<?=$total_ratings!=1?'s':''?><?php endif;?></div>
      </div>
      <div class="ph-actions">
        <a href="chat.php?with=<?=$worker_id?>" class="btn-action btn-chat">💬 Message</a>
        <?php if($app_status && $app_status['status']==='accepted'): ?>
          <a href="ratings.php?rate=<?=$worker_id?>" class="btn-action btn-rate-link">⭐ Rate Worker</a>
        <?php endif;?>
      </div>
    </div>

    <!-- APPLICATION STATUS BANNER -->
    <?php if($app_status): $s=strtolower($app_status['status']); $bc=str_contains($s,'interview')?'interview':$s; ?>
      <div class="status-banner sb-<?=$bc?>">
        Application Status: <strong><?=ucfirst($app_status['status'])?></strong>
        <a href="applicants.php" style="margin-left:auto;font-size:.78rem;font-weight:400;color:inherit;text-decoration:underline;">Change Status →</a>
      </div>
    <?php endif;?>

    <div class="two-col">
      <!-- LEFT -->
      <div>
        <!-- About -->
        <?php if(!empty($worker['bio'])): ?>
          <div class="card">
            <div class="card-title">About</div>
            <p style="font-size:.88rem;color:var(--ink2);line-height:1.65;"><?=nl2br(htmlspecialchars($worker['bio']))?></p>
          </div>
        <?php endif;?>

        <!-- Education & Experience -->
        <?php if(!empty($worker['education'])||!empty($worker['experience'])): ?>
          <div class="card">
            <?php if(!empty($worker['education'])): ?>
              <div class="card-title">Education</div>
              <p style="font-size:.86rem;color:var(--ink2);line-height:1.6;margin-bottom:16px;"><?=nl2br(htmlspecialchars($worker['education']))?></p>
            <?php endif;?>
            <?php if(!empty($worker['experience'])): ?>
              <div class="card-title">Experience</div>
              <p style="font-size:.86rem;color:var(--ink2);line-height:1.6;"><?=nl2br(htmlspecialchars($worker['experience']))?></p>
            <?php endif;?>
          </div>
        <?php endif;?>

        <!-- Portfolio -->
        <?php if($posts&&$posts->num_rows>0): ?>
          <div class="card">
            <div class="card-title">Portfolio</div>
            <div class="portfolio-grid">
              <?php while($p=$posts->fetch_assoc()): ?>
                <div class="p-item" onclick="openMedia('../uploads/portfolio/<?=htmlspecialchars($p['media_file'])?>',<?=$p['is_video']?>)">
                  <?php if($p['is_video']): ?>
                    <video src="../uploads/portfolio/<?=htmlspecialchars($p['media_file'])?>" preload="none"></video>
                    <div style="position:absolute;top:6px;left:6px;background:rgba(0,0,0,.6);color:#fff;font-size:.65rem;font-weight:400;padding:2px 6px;border-radius:5px;">🎬</div>
                  <?php else: ?>
                    <img src="../uploads/portfolio/<?=htmlspecialchars($p['media_file'])?>" alt="" loading="lazy">
                  <?php endif;?>
                  <div class="p-overlay">
                    ❤️ <?=$p['likes']?> &nbsp; 👁 <?=$p['views']?>
                  </div>
                </div>
              <?php endwhile;?>
            </div>
          </div>
        <?php endif;?>

        <!-- Reviews -->
        <?php if($reviews&&$reviews->num_rows>0): ?>
          <div class="card">
            <div class="card-title"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">Reviews (<?=$total_ratings?>)</div>
            <?php while($rv=$reviews->fetch_assoc()): ?>
              <div class="review-card">
                <div class="rv-stars"><?=stars_html($rv['stars'])?> <span style="font-size:.8rem;color:var(--muted);font-weight:400;"><?=$rv['stars']?>/5</span></div>
                <?php if(!empty($rv['review'])): ?><div class="rv-text">"<?=htmlspecialchars($rv['review'])?>"</div><?php endif;?>
                <div class="rv-author">🏢 <?=htmlspecialchars($rv['reviewer_name'])?> · <?=date('d M Y',strtotime($rv['created_at']))?></div>
              </div>
            <?php endwhile;?>
          </div>
        <?php endif;?>
      </div>

      <!-- RIGHT -->
      <div>
        <!-- Contact Info -->
        <div class="card">
          <div class="card-title">Contact Info</div>
          <div style="font-size:.85rem;color:var(--muted);line-height:1.9;">
            <div>✉️ <?=htmlspecialchars($worker['email'])?></div>
            <?php if(!empty($worker['phone'])): ?><div>📱 <?=htmlspecialchars($worker['phone'])?></div><?php endif;?>
            <?php if(!empty($worker['location'])): ?><div>📍 <?=htmlspecialchars($worker['location'])?></div><?php endif;?>
          </div>
          <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px;">
            <a href="chat.php?with=<?=$worker_id?>" style="display:block;padding:9px;text-align:center;background:var(--teal);color:#fff;border-radius:100px;font-family:var(--font-b);font-size:.82rem;font-weight:400;text-decoration:none;">💬 Send Message</a>
            <?php if($app_status && $app_status['status']==='accepted'): ?>
              <a href="ratings.php?rate=<?=$worker_id?>" style="display:block;padding:9px;text-align:center;background:#fef3c7;color:#d97706;border-radius:100px;font-family:var(--font-b);font-size:.82rem;font-weight:400;text-decoration:none;">⭐ Rate Worker</a>
            <?php endif;?>
          </div>
        </div>

        <!-- Skills -->
        <?php if(!empty($skills)): ?>
          <div class="card">
            <div class="card-title">Skills</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
              <?php foreach($skills as $sk): ?>
                <span style="background:var(--teal-lt);color:var(--teal);font-size:.75rem;font-weight:400;padding:4px 10px;border-radius:100px;"><?=htmlspecialchars($sk)?></span>
              <?php endforeach;?>
            </div>
          </div>
        <?php endif;?>

        <!-- Skill Progress -->
        <?php if($skills_progress&&$skills_progress->num_rows>0): ?>
          <div class="card">
            <div class="card-title"><img src="../assets/icons/skill program.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">Skill Progress</div>
            <?php $bi=0; while($sk=$skills_progress->fetch_assoc()): $bc=$bar_colors[$bi++%count($bar_colors)];?>
              <div class="sk-row">
                <div class="sk-top"><div class="sk-name"><?=htmlspecialchars($sk['skill_name'])?></div><div class="sk-pct" style="color:<?=$bc?>;"><?=$sk['progress']?>%</div></div>
                <div class="sk-bg"><div class="sk-fill" data-w="<?=$sk['progress']?>%" style="background:<?=$bc?>;"></div></div>
              </div>
            <?php endwhile;?>
          </div>
        <?php endif;?>
      </div>
    </div>
  </div>
</div>

<!-- MEDIA MODAL -->
<div id="media-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999;align-items:center;justify-content:center;" onclick="closeMedia()">
  <div style="max-width:700px;width:92%;position:relative;" onclick="event.stopPropagation()">
    <button onclick="closeMedia()" style="position:absolute;top:-14px;right:-14px;width:34px;height:34px;border-radius:50%;background:#fff;border:none;font-size:1.1rem;cursor:pointer;z-index:10;">✕</button>
    <img id="modal-img" style="width:100%;border-radius:12px;display:none;max-height:80vh;object-fit:contain;">
    <video id="modal-vid" style="width:100%;border-radius:12px;display:none;" controls></video>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded',()=>{
  setTimeout(()=>{document.querySelectorAll('[data-w]').forEach(e=>{e.style.width=e.dataset.w;});},300);
});
function openMedia(src,isVideo){
  const m=document.getElementById('media-modal');
  const img=document.getElementById('modal-img');
  const vid=document.getElementById('modal-vid');
  if(isVideo){img.style.display='none';vid.style.display='block';vid.src=src;}
  else{vid.style.display='none';img.style.display='block';img.src=src;}
  m.style.display='flex';
}
function closeMedia(){
  document.getElementById('media-modal').style.display='none';
  const v=document.getElementById('modal-vid');if(v)v.pause();
}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

</body></html>
