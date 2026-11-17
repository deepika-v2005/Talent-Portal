<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../login.php"); exit();
}

require_once '../includes/db.php';
$user_id = (int)$_SESSION['user_id'];

// ── SIDEBAR VARIABLES ──
$conn2 = new mysqli("localhost", "root", "", "skillconnect");
$my_profile    = $conn2->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending       = (int)$conn2->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='pending'")->fetch_assoc()['c'];
$unread_notif  = (int)$conn2->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs   = (int)$conn2->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$conn2->close();
$avatar_letter = strtoupper(substr($my_profile['name'] ?? $_SESSION['user_name'], 0, 1));
$first_name    = explode(' ', $my_profile['name'] ?? $_SESSION['user_name'])[0];

$worker_id = (int)($_GET['id'] ?? 0);
if (!$worker_id || $worker_id === $user_id) {
    header("Location: profile.php"); exit();
}

// ── FETCH WORKER ──
$worker = $conn->query("SELECT * FROM users WHERE id=$worker_id AND role='worker'")->fetch_assoc();
if (!$worker) { header("Location: feed.php"); exit(); }

// ── RATINGS ──
$rating_row = $conn->query("SELECT AVG(stars) as avg, COUNT(*) as total FROM ratings WHERE worker_id=$worker_id")->fetch_assoc();
$avg_rating = $rating_row['avg'] ? number_format($rating_row['avg'],1) : '—';
$total_ratings = (int)$rating_row['total'];

// ── PORTFOLIO / POSTS ──
$posts = false; $post_count = 0;
$tbl_check = $conn->query("SHOW TABLES LIKE 'posts'");
if ($tbl_check && $tbl_check->num_rows > 0) {
    $posts = $conn->query("SELECT posts.*, (SELECT COUNT(*) FROM post_likes WHERE post_id=posts.id) as like_count FROM posts WHERE worker_id=$worker_id ORDER BY created_at DESC");
    $post_count = $posts ? $posts->num_rows : 0;
}

// ── LIKE post ──
if (isset($_GET['like'])) {
    $pid = (int)$_GET['like'];
    $check = $conn->query("SELECT id FROM post_likes WHERE post_id=$pid AND user_id=$user_id");
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM post_likes WHERE post_id=$pid AND user_id=$user_id");
        $conn->query("UPDATE posts SET likes = likes - 1 WHERE id=$pid AND likes > 0");
    } else {
        $conn->query("INSERT INTO post_likes (post_id, user_id) VALUES ($pid, $user_id)");
        $conn->query("UPDATE posts SET likes = likes + 1 WHERE id=$pid");
    }
    header("Location: public_profile.php?id=$worker_id"); exit();
}

// ── CONNECTIONS COUNT ──
$connections_count = (int)$conn->query("SELECT COUNT(*) as c FROM connections WHERE (worker_id=$worker_id) AND status='accepted'")->fetch_assoc()['c'];

$conn->close();
$w_letter = strtoupper(substr($worker['name'],0,1));
$colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706'];
$w_color = $colors[crc32($worker['name']) % count($colors)];
$skill_arr = array_map('trim', explode(',', $worker['skills'] ?? ''));
$active_page = 'feed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?=htmlspecialchars($worker['name'])?> – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;
      --sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,0.07);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;
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
.sidebar{width:var(--sidebar);height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}.sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:900;color:#fff;text-decoration:none;}.sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}
    .sb-ava{width:40px;height:40px;border-radius:11px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:900;overflow:hidden;flex-shrink:0;}.sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
    .sb-uname{font-size:.86rem;font-weight:700;color:#fff;}.sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
    .sb-nav{flex:1;padding:10px 0;}.nav-section{font-size:.62rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:600;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}.sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}.nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:800;padding:2px 6px;border-radius:100px;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}.sb-logout a{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}
    .main{margin-left:var(--sidebar);flex:1;}.topbar{height:60px;padding:0 26px;background:var(--white);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}.hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;color:var(--ink);}
    .tb-crumb{font-size:.85rem;color:var(--muted);}.tb-crumb strong{color:var(--ink);font-weight:700;}
    .tb-right{display:flex;align-items:center;gap:9px;}.tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:800;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--white);}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:900;text-decoration:none;overflow:hidden;}.tb-ava img{width:35px;height:35px;object-fit:cover;}
    .content{padding:28px 32px;max-width:900px;}

    /* PROFILE HEADER */
    .profile-header{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);margin-bottom:20px;}
    .ph-top{display:flex;gap:20px;align-items:center;margin-bottom:16px;}
    .ph-avatar{width:90px;height:90px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:2rem;font-weight:900;color:#fff;overflow:hidden;}
    .ph-avatar img{width:90px;height:90px;object-fit:cover;border-radius:50%;}
    .ph-name{font-family:var(--font-h);font-size:1.6rem;font-weight:900;margin-bottom:4px;}
    .ph-loc{font-size:.85rem;color:var(--muted);margin-bottom:8px;}
    .ph-stats{display:flex;gap:20px;margin-top:8px;}
    .ph-stat{text-align:center;}.ph-stat strong{font-family:var(--font-h);font-size:1.1rem;display:block;}.ph-stat span{font-size:.72rem;color:var(--muted);}
    .ph-bio{font-size:.88rem;color:var(--ink2);line-height:1.6;margin-bottom:14px;}
    .skill-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;}
    .chip{font-size:.75rem;font-weight:700;padding:4px 12px;border-radius:100px;background:var(--teal-lt);color:var(--teal);}
    .ph-actions{display:flex;gap:8px;flex-wrap:wrap;}
    .btn{padding:9px 20px;border-radius:100px;font-family:var(--font-b);font-size:.82rem;font-weight:700;text-decoration:none;border:none;cursor:pointer;transition:all .18s;}
    .btn-primary{background:var(--orange);color:#fff;}.btn-primary:hover{background:#d45609;}
    .btn-outline{background:transparent;border:1.5px solid var(--teal);color:var(--teal);}.btn-outline:hover{background:var(--teal);color:#fff;}
    .btn-chat{background:var(--teal-lt);color:var(--teal);border:1.5px solid var(--teal);}

    /* POSTS GRID */
    .section-title{font-family:var(--font-h);font-size:1.1rem;font-weight:900;margin-bottom:14px;}
    .posts-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;margin-bottom:24px;}
    @media(max-width:600px){.posts-grid{grid-template-columns:repeat(2,1fr);}}
    .post-thumb{aspect-ratio:1;overflow:hidden;cursor:pointer;position:relative;background:var(--cream2);}
    .post-thumb img,.post-thumb video{width:100%;height:100%;object-fit:cover;display:block;transition:transform .2s;}
    .post-thumb:hover img,.post-thumb:hover video{transform:scale(1.05);}
    .post-thumb-overlay{position:absolute;inset:0;background:rgba(0,0,0,.45);opacity:0;transition:opacity .2s;display:flex;align-items:center;justify-content:center;gap:16px;color:#fff;font-size:.88rem;font-weight:700;}
    .post-thumb:hover .post-thumb-overlay{opacity:1;}

    /* INFO CARDS */
    .info-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px;}
    .info-label{font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;}
    .info-val{font-size:.88rem;color:var(--ink2);line-height:1.6;}
    .stars{color:#f59e0b;font-size:1.1rem;}

    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}.sb-overlay.show{display:block;}
    @media(max-width:768px){.sidebar{transform:translateX(-240px);transition:transform .28s ease;}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.content{padding:14px;}.hamburger{display:flex;color:var(--ink);}.info-grid-2{grid-template-columns:1fr;}}
    .info-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}

  </style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava"><?php if(!empty($my_profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($my_profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></div>
    <div><div class="sb-uname"><?=htmlspecialchars($first_name)?></div><div class="sb-urole">Service Provider</div></div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="feed.php" class="active"><span class="nav-ic"><img src="../assets/icons/explore.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Explore</a>
    <a href="browse_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Browse Jobs</a>
    <a href="applications.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applications<?php if($pending>0) echo "<span class='nav-bdg'>$pending</span>"; ?></a>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="portfolio.php"><span class="nav-ic"><img src="../assets/icons/total count.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Portfolio</a>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Profile</a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if($unread_notif>0) echo "<span class='nav-bdg'>$unread_notif</span>"; ?></a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if($unread_msgs>0) echo "<span class='nav-bdg'>$unread_msgs</span>"; ?></a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <div class="tb-crumb"><a href="feed.php" style="color:var(--muted);text-decoration:none;">Feed</a> / <strong><?=htmlspecialchars(explode(' ',$worker['name'])[0])?></strong></div>
    </div>
    <div class="tb-right">
      <a class="tb-btn" href="notifications.php">🔔<?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a>
      <a class="tb-btn" href="chat.php">💬<?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a>
      <a class="tb-ava" href="profile.php"><?php if(!empty($my_profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($my_profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a>
    </div>
  </div>

  <div class="content">
    <!-- Profile Header -->
    <div class="profile-header">
      <div class="ph-top">
        <div class="ph-avatar" style="background:<?=$w_color?>">
          <?php if(!empty($worker['profile_pic'])): ?>
            <img src="../uploads/profiles/<?=htmlspecialchars($worker['profile_pic'])?>" alt="">
          <?php else: ?><?=$w_letter?><?php endif;?>
        </div>
        <div style="flex:1;">
          <div class="ph-name"><?=htmlspecialchars($worker['name'])?></div>
          <div class="ph-loc">📍 <?=htmlspecialchars($worker['location'] ?? 'Location not set')?></div>
          <div class="ph-stats">
            <div class="ph-stat"><strong><?=$post_count?></strong><span>Posts</span></div>
            <div class="ph-stat"><strong><?=$connections_count?></strong><span>Connections</span></div>
            <div class="ph-stat"><strong><?=$avg_rating?></strong><span>Rating</span></div>
            <div class="ph-stat"><strong><?=$total_ratings?></strong><span>Reviews</span></div>
          </div>
        </div>
      </div>

      <?php if(!empty($worker['bio'])): ?>
        <div class="ph-bio"><?=nl2br(htmlspecialchars($worker['bio']))?></div>
      <?php endif; ?>

      <?php if(!empty($worker['skills'])): ?>
        <div class="skill-chips">
          <?php foreach($skill_arr as $sk): ?>
            <?php if(!empty(trim($sk))): ?>
              <span class="chip"><?=htmlspecialchars(trim($sk))?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="ph-actions">
        <a href="chat.php?to=<?=$worker_id?>" class="btn btn-chat">💬 Message</a>
        <a href="network.php" class="btn btn-outline">🔗 Connect via Network</a>
        <a href="feed.php" class="btn" style="background:var(--cream2);color:var(--ink2);">← Back to Feed</a>
      </div>
    </div>

    <!-- Posts Grid -->
    <?php if($post_count > 0): ?>
      <div class="section-title">Portfolio (<?=$post_count?> posts)</div>
      <div class="posts-grid">
        <?php $tbl2 = $conn->query("SHOW TABLES LIKE 'posts'"); $posts = ($tbl2 && $tbl2->num_rows > 0) ? $conn->query("SELECT posts.*, (SELECT COUNT(*) FROM post_likes WHERE post_id=posts.id) as like_count, (SELECT COUNT(*) FROM post_likes WHERE post_id=posts.id AND user_id=$user_id) as i_liked FROM posts WHERE posts.worker_id=$worker_id ORDER BY posts.created_at DESC") : false;
        while($p = $posts->fetch_assoc()): ?>
          <div class="post-thumb" onclick="openPost('<?=htmlspecialchars($p['media_file'])?>',<?=$p['is_video']?>,<?=$p['id']?>,<?=$p['like_count']?>)">
            <?php if($p['is_video']): ?>
              <video src="../uploads/portfolio/<?=htmlspecialchars($p['media_file'])?>"></video>
            <?php else: ?>
              <img src="../uploads/portfolio/<?=htmlspecialchars($p['media_file'])?>" alt="" loading="lazy">
            <?php endif; ?>
            <div class="post-thumb-overlay">
              <span><?=$p['i_liked']?'❤️':'🤍'?> <?=$p['like_count']?></span>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="info-card" style="text-align:center;padding:40px 20px;color:var(--muted);">
        <div style="font-size:2.5rem;margin-bottom:10px;">🖼️</div>
        <p>No portfolio posts yet.</p>
      </div>
    <?php endif; ?>

    <!-- Info Cards -->
    <div class="info-grid-2">
      <?php if(!empty($worker['education'])): ?>
      <div class="info-card">
        <div class="info-label">Education</div>
        <div class="info-val"><?=nl2br(htmlspecialchars($worker['education']))?></div>
      </div>
      <?php endif; ?>
      <?php if(!empty($worker['experience'])): ?>
      <div class="info-card">
        <div class="info-label">Experience</div>
        <div class="info-val"><?=nl2br(htmlspecialchars($worker['experience']))?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Rating Stars -->
    <?php if($avg_rating !== '—'): ?>
    <div class="info-card">
      <div class="info-label">⭐ Rating</div>
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="stars">
          <?php for($i=1;$i<=5;$i++) echo $i<=round($avg_rating)?'★':'☆'; ?>
        </div>
        <strong style="font-size:1.3rem;"><?=$avg_rating?></strong>
        <span style="color:var(--muted);font-size:.82rem;">from <?=$total_ratings?> review<?=$total_ratings>1?'s':''?></span>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Post lightbox -->
<div id="lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:999;align-items:center;justify-content:center;">
  <button onclick="closeLightbox()" style="position:absolute;top:16px;right:20px;background:none;border:none;color:#fff;font-size:1.8rem;cursor:pointer;">✕</button>
  <div style="max-width:600px;width:92%;background:#fff;border-radius:16px;overflow:hidden;">
    <img id="lb-img" style="width:100%;max-height:70vh;object-fit:contain;display:none;" alt="">
    <video id="lb-vid" style="width:100%;display:none;" controls></video>
    <div style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;">
      <div id="lb-likes" style="font-weight:700;"></div>
      <a id="lb-like-btn" href="#" style="color:var(--orange);font-weight:700;font-size:.9rem;">Like ❤️</a>
    </div>
  </div>
</div>

<script>
function openPost(file, isVideo, postId, likes) {
  const lb = document.getElementById('lightbox');
  const img = document.getElementById('lb-img');
  const vid = document.getElementById('lb-vid');
  lb.style.display = 'flex';
  document.getElementById('lb-likes').textContent = '❤️ ' + likes + ' likes';
  document.getElementById('lb-like-btn').href = '?like=' + postId + '&id=<?=$worker_id?>';
  const src = '../uploads/portfolio/' + file;
  if (isVideo) { img.style.display='none'; vid.style.display='block'; vid.src=src; }
  else { vid.style.display='none'; img.style.display='block'; img.src=src; }
}
function closeLightbox() {
  document.getElementById('lightbox').style.display='none';
  document.getElementById('lb-vid').pause();
}
document.getElementById('lightbox').addEventListener('click', e => { if(e.target===e.currentTarget) closeLightbox(); });
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
