<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); exit();
}
require_once '../includes/db.php';
$user_id  = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$profile  = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending_apps = (int)$conn->query("SELECT COUNT(*) as c FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.client_id=$user_id AND a.status='pending'")->fetch_assoc()['c'];
$unread_notif = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs  = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$avatar_letter = strtoupper(substr($user_name,0,1));
$first_name = explode(' ',$user_name)[0];

// ── LIKE POST ──
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
    header("Location: feed.php"); exit();
}

// ── ADD COMMENT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $pid = (int)$_POST['post_id'];
    $comment = trim($conn->real_escape_string($_POST['comment']));
    if (!empty($comment)) {
        $conn->query("INSERT INTO post_comments (post_id, user_id, comment, created_at) VALUES ($pid, $user_id, '$comment', NOW())");
        $post_owner = $conn->query("SELECT worker_id FROM posts WHERE id=$pid")->fetch_assoc();
        if ($post_owner) {
            $cn = $conn->real_escape_string($user_name);
            $msg = "$cn commented on your post.";
            $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at) VALUES ({$post_owner['worker_id']},'general','$msg',0,NOW())");
        }
    }
    header("Location: feed.php"); exit();
}

// ── SEND CONNECTION REQUEST ──
if (isset($_GET['connect'])) {
    $wid = (int)$_GET['connect'];
    $check = $conn->query("SELECT id FROM connections WHERE worker_id=$wid AND client_id=$user_id");
    if ($check->num_rows === 0) {
        $conn->query("INSERT INTO connections (worker_id, client_id, status, created_at) VALUES ($wid, $user_id, 'pending', NOW())");
        $msg = $conn->real_escape_string("$user_name wants to connect with you!");
        $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at) VALUES ($wid,'connection','$msg',0,NOW())");
    }
    header("Location: feed.php?connected=1"); exit();
}

// ── FETCH FEED ──
$_tbl = $conn->query("SHOW TABLES LIKE 'posts'");
$feed_posts = false;
if ($_tbl && $_tbl->num_rows > 0) {
$feed_posts = $conn->query("
    SELECT p.*, u.name, u.profile_pic, u.location, u.skills, u.id as worker_id,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id) AS like_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id AND user_id=$user_id) AS i_liked,
           (SELECT COUNT(*) FROM post_comments WHERE post_id=p.id) AS comment_count,
           (SELECT status FROM connections WHERE worker_id=u.id AND client_id=$user_id) AS conn_status
    FROM posts p
    JOIN users u ON u.id=p.worker_id
    WHERE u.role='worker'
    ORDER BY p.created_at DESC LIMIT 30
");}

// ── SUGGESTED WORKERS ──
$suggested = $conn->query("
    SELECT u.id, u.name, u.location, u.skills, u.profile_pic,
           (SELECT COUNT(*) FROM posts WHERE worker_id=u.id) AS post_count,
           (SELECT status FROM connections WHERE worker_id=u.id AND client_id=$user_id) AS conn_status
    FROM users u
    WHERE u.role='worker'
    ORDER BY post_count DESC LIMIT 8
");

$conn->close();
$colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706'];
$active_page = 'feed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Feed – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--orange2:#9F67FA;--teal:#7C3AED;--teal-lt:#ede9fe;--blue:#1d4ed8;--blue-lt:#eff6ff;--border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;--red:#dc2626;--sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,.07);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;}
    .sidebar{width:var(--sidebar);height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}.sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}.sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}.sb-ava{width:40px;height:40px;border-radius:11px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:400;overflow:hidden;flex-shrink:0;}.sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}.sb-uname{font-size:.86rem;font-weight:400;color:#fff;}.sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
    .sb-nav{flex:1;padding:10px 0;}.nav-section{font-size:.62rem;font-weight:400;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}.sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}.sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}.sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}.nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:400;padding:2px 6px;border-radius:100px;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}.sb-logout a{display:flex;align-items:center;justify-content:center;gap:9px;padding:10px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}
    .main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}.topbar{height:60px;padding:0 26px;background:rgba(245,243,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}.hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;}.tb-crumb{font-size:.85rem;color:var(--muted);}.tb-crumb strong{color:var(--ink);font-weight:400;}.tb-right{display:flex;align-items:center;gap:9px;}.tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;}.tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:400;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--cream);}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;text-decoration:none;overflow:hidden;}.tb-ava img{width:35px;height:35px;object-fit:cover;}
    .content-wrap{display:grid;grid-template-columns:1fr 320px;gap:24px;padding:24px 26px;flex:1;max-width:1100px;margin:0 auto;width:100%;}
    .feed-post{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);margin-bottom:20px;}
    .post-header{display:flex;align-items:center;gap:12px;padding:14px 16px;}
    .post-avatar{width:44px;height:44px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.1rem;font-weight:400;color:#fff;overflow:hidden;}.post-avatar img{width:44px;height:44px;object-fit:cover;border-radius:50%;}
    .post-author-name{font-weight:400;font-size:.9rem;color:var(--ink);}.post-author-name a{color:var(--ink);text-decoration:none;}.post-author-name a:hover{color:var(--orange);}
    .post-author-loc{font-size:.74rem;color:var(--muted);}.post-time{margin-left:auto;font-size:.72rem;color:var(--muted);}
    .post-media{width:100%;max-height:480px;object-fit:cover;display:block;}.post-media-video{width:100%;max-height:480px;display:block;}
    .post-actions{display:flex;align-items:center;gap:4px;padding:10px 16px 4px;}
    .action-btn{background:none;border:none;cursor:pointer;font-size:1.4rem;padding:4px 6px;border-radius:8px;transition:transform .15s;display:flex;align-items:center;gap:5px;}.action-btn:hover{transform:scale(1.12);}.action-btn.liked{color:#7C3AED;}
    .action-count{font-size:.8rem;font-weight:400;color:var(--ink2);}
    .post-caption{padding:6px 16px 4px;font-size:.86rem;line-height:1.5;}.post-caption strong{font-weight:400;}
    .post-skill-tags{display:flex;flex-wrap:wrap;gap:4px;padding:4px 16px 10px;}
    .skill-tag{font-size:.68rem;font-weight:400;padding:2px 8px;border-radius:5px;background:var(--teal-lt);color:var(--teal);}
    .connect-bar{padding:10px 16px;border-top:1px solid var(--border);background:var(--cream);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
    .connect-bar span{font-size:.8rem;color:var(--muted);}
    .btn-connect{padding:7px 18px;border-radius:100px;background:var(--teal);color:#fff;border:none;font-family:var(--font-b);font-size:.78rem;font-weight:400;cursor:pointer;text-decoration:none;}
    .btn-connect.pending{background:var(--cream2);color:var(--muted);border:1.5px solid var(--border);}
    .btn-connect.connected{background:var(--green-lt);color:var(--green);border:1.5px solid var(--green);}
    .btn-view-profile{padding:7px 18px;border-radius:100px;background:var(--blue-lt);color:var(--blue);border:none;font-family:var(--font-b);font-size:.78rem;font-weight:400;cursor:pointer;text-decoration:none;}
    .comments-section{padding:0 16px 14px;border-top:1px solid var(--border);margin-top:8px;}
    .comments-toggle{background:none;border:none;color:var(--muted);font-size:.78rem;font-weight:400;cursor:pointer;padding:8px 0;display:block;}
    .comments-list{display:none;margin-bottom:8px;}.comments-list.open{display:block;}
    .comment-item{display:flex;gap:8px;margin-bottom:8px;align-items:flex-start;}
    .comment-ava{width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:400;color:#fff;overflow:hidden;}.comment-ava img{width:28px;height:28px;object-fit:cover;border-radius:50%;}
    .comment-body{background:var(--cream);border-radius:12px;padding:7px 12px;font-size:.8rem;flex:1;}.comment-body strong{font-weight:400;display:block;font-size:.75rem;margin-bottom:2px;}
    .comment-form{display:flex;gap:8px;margin-top:8px;}.comment-input{flex:1;border:1px solid var(--border);border-radius:20px;padding:7px 14px;font-family:var(--font-b);font-size:.82rem;background:var(--cream);outline:none;}.comment-input:focus{border-color:var(--teal);}
    .comment-submit{background:var(--teal);color:#fff;border:none;border-radius:20px;padding:7px 16px;font-family:var(--font-b);font-size:.8rem;font-weight:400;cursor:pointer;}
    .sidebar-right{position:sticky;top:84px;align-self:start;}
    .sr-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);margin-bottom:16px;}
    .sr-title{font-family:var(--font-h);font-size:1rem;font-weight:400;margin-bottom:14px;}
    .suggest-item{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
    .suggest-ava{width:38px;height:38px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;color:#fff;overflow:hidden;}.suggest-ava img{width:38px;height:38px;object-fit:cover;border-radius:50%;}
    .suggest-name{font-weight:400;font-size:.84rem;}.suggest-name a{color:var(--ink);text-decoration:none;}.suggest-name a:hover{color:var(--teal);}
    .suggest-meta{font-size:.7rem;color:var(--muted);}
    .suggest-btn{margin-left:auto;padding:5px 12px;border-radius:100px;font-size:.72rem;font-weight:400;white-space:nowrap;text-decoration:none;cursor:pointer;border:1.5px solid var(--teal);color:var(--teal);background:transparent;}.suggest-btn:hover{background:var(--teal);color:#fff;}
    .suggest-btn.pending{border-color:var(--muted);color:var(--muted);}.suggest-btn.connected{border-color:var(--green);color:var(--green);}
    .toast{position:fixed;bottom:24px;right:24px;background:var(--teal);color:#fff;padding:12px 22px;border-radius:100px;font-weight:400;font-size:.86rem;box-shadow:var(--shadow);z-index:9999;animation:fadeUp .3s ease;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}.sb-overlay.show{display:block;}
    @media(max-width:900px){.content-wrap{grid-template-columns:1fr;}.sidebar-right{display:none;}}
    @media(max-width:768px){.sidebar{transform:translateX(-240px);transition:transform .28s ease;}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.content-wrap{padding:14px;}.hamburger{display:flex;}}


/* ============ AUTO-GENERATED DARK MODE ============ */
body.dark-mode{background:#0B1220;color:#E2E8F0;}
body.dark-mode .topbar{background:rgba(11,18,32,.96);border-bottom:1px solid #1E293B;}
body.dark-mode .hamburger{background:#1E293B;border:1px solid #1E293B;}
body.dark-mode .tb-crumb{color:#94A3B8;}
body.dark-mode .tb-crumb strong{color:#E2E8F0;}
body.dark-mode .tb-btn{background:#0B1220;border:1px solid #1E293B;}
body.dark-mode .tb-dot{border:2px solid #0B1220;}
body.dark-mode .feed-post{background:#111827;border:1px solid #1E293B;}
body.dark-mode .post-author-name{color:#E2E8F0;}
body.dark-mode .post-author-name a{color:#E2E8F0;}
body.dark-mode .post-author-loc{color:#94A3B8;}
body.dark-mode .post-time{color:#94A3B8;}
body.dark-mode .action-count{color:#CBD5E1;}
body.dark-mode .skill-tag{background:rgba(124,58,237,.18);}
body.dark-mode .connect-bar{border-top:1px solid #1E293B;background:#0B1220;}
body.dark-mode .connect-bar span{color:#94A3B8;}
body.dark-mode .btn-connect.pending{background:#1E293B;color:#94A3B8;border:1.5px solid #1E293B;}
body.dark-mode .btn-connect.connected{background:rgba(22,163,74,.14);}
body.dark-mode .btn-view-profile{background:rgba(29,78,216,.16);}
body.dark-mode .comments-section{border-top:1px solid #1E293B;}
body.dark-mode .comments-toggle{color:#94A3B8;}
body.dark-mode .comment-body{background:#0B1220;}
body.dark-mode .comment-input{border:1px solid #1E293B;background:#0B1220;}
body.dark-mode .sr-card{background:#111827;border:1px solid #1E293B;}
body.dark-mode .suggest-name a{color:#E2E8F0;}
body.dark-mode .suggest-meta{color:#94A3B8;}
body.dark-mode .suggest-btn.pending{color:#94A3B8;}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>

<?php if(isset($_GET['connected'])): ?>
<div class="toast" id="toast">✅ Connection request sent!</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.style.opacity=0;},3000);</script>
<?php endif;?>

<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></div>
    <div><div class="sb-uname"><?=htmlspecialchars($first_name)?></div><div class="sb-urole">Client</div></div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="feed.php" class="active"><span class="nav-ic"><img src="../assets/icons/explore.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Explore</a>
    <a href="post_job.php"><span class="nav-ic"><img src="../assets/icons/request.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Service Request</a>
    <a href="manage_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Jobs</a>
    <a href="applicants.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants<?php if($pending_apps>0) echo "<span class='nav-bdg'>$pending_apps</span>"; ?></a>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if($unread_notif>0) echo "<span class='nav-bdg'>$unread_notif</span>"; ?></a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if($unread_msgs>0) echo "<span class='nav-bdg'>$unread_msgs</span>"; ?></a>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <img src="../assets/icons/explore.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;margin-right:2px;">
      <div class="tb-crumb"><strong>Explore</strong></div>
    </div>
    <div class="tb-right">
      <a class="tb-btn" href="notifications.php"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a>
      <a class="tb-btn" href="chat.php"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a>
      <a class="tb-ava" href="profile.php"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a>
    </div>
  </div>

  <div class="content-wrap">
    <div>
      <?php if($feed_posts && $feed_posts->num_rows > 0):
        while($post = $feed_posts->fetch_assoc()):
          $letter = strtoupper(substr($post['name'],0,1));
          $color = $colors[crc32($post['name']) % count($colors)];
          $skill_list = array_slice(array_map('trim', explode(',', $post['skills'] ?? '')), 0, 3);
          $conn_status = $post['conn_status'];
      ?>
      <div class="feed-post">
        <div class="post-header">
          <div class="post-avatar" style="background:<?=$color?>">
            <?php if(!empty($post['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($post['profile_pic'])?>" alt=""><?php else: ?><?=$letter?><?php endif;?>
          </div>
          <div>
            <div class="post-author-name"><a href="applicant_profile.php?id=<?=$post['worker_id']?>"><?=htmlspecialchars($post['name'])?></a></div>
            <div class="post-author-loc">📍 <?=htmlspecialchars($post['location'] ?? '')?></div>
          </div>
          <div class="post-time"><?=date('d M', strtotime($post['created_at']))?></div>
        </div>

        <?php if($post['is_video']): ?>
          <video class="post-media-video" src="../uploads/portfolio/<?=htmlspecialchars($post['media_file'])?>" controls></video>
        <?php else: ?>
          <img class="post-media" src="../uploads/portfolio/<?=htmlspecialchars($post['media_file'])?>" alt="" loading="lazy">
        <?php endif;?>

        <div class="post-actions">
          <a href="?like=<?=$post['id']?>" class="action-btn <?=$post['i_liked']?'liked':''?>">
            <?=$post['i_liked']?'❤️':'🤍'?>
          </a>
          <span class="action-count"><?=$post['like_count']?></span>
          <button class="action-btn" onclick="toggleComments(<?=$post['id']?>)">💬</button>
          <span class="action-count"><?=$post['comment_count']?></span>
          <a href="applicant_profile.php?id=<?=$post['worker_id']?>" class="action-btn" style="margin-left:auto;" title="View Profile">👁️</a>
        </div>

        <?php if(!empty($post['caption'])): ?>
          <div class="post-caption"><strong><?=htmlspecialchars(explode(' ',$post['name'])[0])?></strong> <?=htmlspecialchars($post['caption'])?></div>
        <?php endif;?>

        <?php if(!empty($post['skills'])): ?>
          <div class="post-skill-tags">
            <?php foreach($skill_list as $sk): ?><span class="skill-tag"><?=htmlspecialchars($sk)?></span><?php endforeach;?>
          </div>
        <?php endif;?>

        <!-- Connect Bar -->
        <div class="connect-bar">
          <span>Want to work with <?=htmlspecialchars(explode(' ',$post['name'])[0])?>?</span>
          <div style="display:flex;gap:6px;align-items:center;">
            <a href="applicant_profile.php?id=<?=$post['worker_id']?>" class="btn-view-profile">View Profile</a>
            <?php if($conn_status === 'accepted'): ?>
              <span class="btn-connect connected">✅ Connected</span>
            <?php elseif($conn_status === 'pending'): ?>
              <span class="btn-connect pending">⏳ Request Sent</span>
            <?php else: ?>
              <a href="?connect=<?=$post['worker_id']?>" class="btn-connect">🔗 Connect</a>
            <?php endif;?>
          </div>
        </div>

        <!-- Comments -->
        <div class="comments-section">
          <?php
          $conn3 = new mysqli("localhost","root","","skillconnect");
          $cmts = $conn3->query("SELECT pc.*, u.name as uname, u.profile_pic FROM post_comments pc JOIN users u ON u.id=pc.user_id WHERE pc.post_id={$post['id']} ORDER BY pc.created_at ASC LIMIT 10");
          $cmt_count = $cmts ? $cmts->num_rows : 0;
          ?>
          <?php if($cmt_count > 0): ?>
            <button class="comments-toggle" onclick="toggleComments(<?=$post['id']?>)">View <?=$cmt_count?> comment<?=$cmt_count>1?'s':''?></button>
            <div class="comments-list" id="comments-<?=$post['id']?>">
              <?php while($c = $cmts->fetch_assoc()):
                $cl = strtoupper(substr($c['uname'],0,1));
                $cc = $colors[crc32($c['uname']) % count($colors)];
              ?>
              <div class="comment-item">
                <div class="comment-ava" style="background:<?=$cc?>"><?php if(!empty($c['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($c['profile_pic'])?>" alt=""><?php else: ?><?=$cl?><?php endif;?></div>
                <div class="comment-body"><strong><?=htmlspecialchars($c['uname'])?></strong><?=htmlspecialchars($c['comment'])?></div>
              </div>
              <?php endwhile;?>
            </div>
          <?php endif; $conn3->close();?>
          <form method="POST" class="comment-form">
            <input type="hidden" name="post_id" value="<?=$post['id']?>">
            <input type="text" name="comment" class="comment-input" placeholder="Add a comment...">
            <button type="submit" name="add_comment" class="comment-submit">Post</button>
          </form>
        </div>
      </div>
      <?php endwhile;
      else: ?>
        <div style="text-align:center;padding:60px 20px;background:var(--white);border:1px solid var(--border);border-radius:var(--radius);">
          <div style="font-size:3rem;margin-bottom:14px;">📰</div>
          <h3 style="font-family:var(--font-h);font-size:1.3rem;color:var(--ink2);">No posts yet</h3>
          <p style="color:var(--muted);">Service Provider haven't posted any portfolio content yet.</p>
        </div>
      <?php endif;?>
    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="sidebar-right">
      <div class="sr-card">
        <div class="sr-title">👥 Discover Talent</div>
        <?php if($suggested && $suggested->num_rows > 0):
          while($s = $suggested->fetch_assoc()):
            $sl = strtoupper(substr($s['name'],0,1));
            $sc = $colors[crc32($s['name']) % count($colors)];
            $s_conn = $s['conn_status'];
        ?>
          <div class="suggest-item">
            <div class="suggest-ava" style="background:<?=$sc?>"><?php if(!empty($s['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($s['profile_pic'])?>" alt=""><?php else: ?><?=$sl?><?php endif;?></div>
            <div>
              <div class="suggest-name"><a href="applicant_profile.php?id=<?=$s['id']?>"><?=htmlspecialchars(explode(' ',$s['name'])[0])?></a></div>
              <div class="suggest-meta"><?=$s['post_count']?> posts</div>
            </div>
            <?php if($s_conn === 'accepted'): ?>
              <span class="suggest-btn connected">✅</span>
            <?php elseif($s_conn === 'pending'): ?>
              <span class="suggest-btn pending">Sent</span>
            <?php else: ?>
              <a class="suggest-btn" href="?connect=<?=$s['id']?>">Connect</a>
            <?php endif;?>
          </div>
        <?php endwhile; endif; ?>
        <a href="job_seekers.php" style="display:block;text-align:center;font-size:.78rem;font-weight:400;color:var(--teal);margin-top:8px;text-decoration:none;">View all workers →</a>
      </div>

      <div class="sr-card" style="background:linear-gradient(135deg,#7C3AED,#1d4ed8);color:#fff;border:none;">
        <div style="font-family:var(--font-h);font-size:1rem;font-weight:400;margin-bottom:8px;">💼 Hire Talent</div>
        <p style="font-size:.82rem;opacity:.9;margin-bottom:12px;">Connect with workers and send them job offers after accepting their request!</p>
        <a href="network.php" style="display:block;background:#fff;color:var(--teal);text-align:center;padding:8px;border-radius:100px;font-weight:400;font-size:.82rem;text-decoration:none;">Go to Network</a>
      </div>
    </div>
  </div>
</div>

<script>
function toggleComments(id){const el=document.getElementById('comments-'+id);if(el)el.classList.toggle('open');}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

</body>
</html>
