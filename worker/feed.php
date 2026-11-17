<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../login.php"); exit();
}

require_once '../includes/db.php';
$user_id = (int)$_SESSION['user_id'];

// ── SIDEBAR VARIABLES ──
$conn2 = new mysqli("localhost", "root", "", "skillconnect");
$profile       = $conn2->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending       = (int)$conn2->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='pending'")->fetch_assoc()['c'];
$saved_count   = (int)$conn2->query("SELECT COUNT(*) as c FROM saved_jobs WHERE worker_id=$user_id")->fetch_assoc()['c'];
$unread_notif  = (int)$conn2->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs   = (int)$conn2->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$conn2->close();
$avatar_letter = strtoupper(substr($profile['name'] ?? $_SESSION['user_name'], 0, 1));
$first_name    = explode(' ', $profile['name'] ?? $_SESSION['user_name'])[0];

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
        // Notify post owner
        $post_owner = $conn->query("SELECT worker_id FROM posts WHERE id=$pid")->fetch_assoc();
        if ($post_owner && $post_owner['worker_id'] != $user_id) {
            $liker_name = $conn->real_escape_string($profile['name']);
            $msg = "$liker_name liked your post!";
            $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at) VALUES ({$post_owner['worker_id']},'general','$msg',0,NOW())");
        }
    }
    header("Location: feed.php"); exit();
}

// ── ADD COMMENT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $pid = (int)$_POST['post_id'];
    $comment = trim($conn->real_escape_string($_POST['comment']));
    if (!empty($comment)) {
        $conn->query("INSERT INTO post_comments (post_id, user_id, comment, created_at) VALUES ($pid, $user_id, '$comment', NOW())");
        // Notify post owner
        $post_owner = $conn->query("SELECT worker_id FROM posts WHERE id=$pid")->fetch_assoc();
        if ($post_owner && $post_owner['worker_id'] != $user_id) {
            $liker_name = $conn->real_escape_string($profile['name']);
            $msg = "$liker_name commented on your post.";
            $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at) VALUES ({$post_owner['worker_id']},'general','$msg',0,NOW())");
        }
    }
    header("Location: feed.php"); exit();
}

// ── FETCH FEED POSTS ──
$_tbl = $conn->query("SHOW TABLES LIKE 'posts'");
if (!$_tbl || $_tbl->num_rows === 0) { $feed_posts = false; } else {
// Show posts from: 1) connected workers, 2) all workers (discover)
$feed_posts = $conn->query("
    SELECT p.*, u.name, u.profile_pic, u.location, u.skills,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id) AS like_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id AND user_id=$user_id) AS i_liked,
           (SELECT COUNT(*) FROM post_comments WHERE post_id=p.id) AS comment_count
    FROM posts p
    JOIN users u ON u.id=p.worker_id
    WHERE u.role='worker'
    ORDER BY p.created_at DESC
    LIMIT 30
");

}
// ── FETCH SUGGESTED WORKERS (not yet connected) ──
$suggested = $conn->query("
    SELECT u.id, u.name, u.location, u.skills, u.profile_pic,
           (SELECT COUNT(*) FROM posts WHERE worker_id=u.id) AS post_count,
           (SELECT COUNT(*) FROM connections WHERE (worker_id=u.id OR client_id=u.id) AND status='accepted') AS connections_count
    FROM users u
    WHERE u.role='worker' AND u.id != $user_id
    ORDER BY post_count DESC
    LIMIT 8
");

$conn->close();
$active_page = 'feed';
$colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Feed – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--orange2:#9F67FA;
      --teal:#7C3AED;--teal-lt:#ede9fe;
      --blue:#1d4ed8;--blue-lt:#eff6ff;
      --border:#DDD6FE;
      --green:#16a34a;--green-lt:#f0fdf4;
      --red:#dc2626;
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
/* SIDEBAR */
    .sidebar{width:var(--sidebar);height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}
    .sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}
    .sb-ava{width:40px;height:40px;border-radius:11px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:900;overflow:hidden;flex-shrink:0;}
    .sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
    .sb-uname{font-size:.86rem;font-weight:400;color:#fff;}
    .sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
    .sb-nav{flex:1;padding:10px 0;}
    .nav-section{font-size:.62rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:800;padding:2px 6px;border-radius:100px;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}
    .sb-logout a{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}
    .sb-logout a:hover{background:rgba(220,38,38,.2);}

    /* MAIN */
    .main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}
    .topbar{height:60px;padding:0 26px;background:var(--white);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;color:var(--ink);}
    .tb-crumb{font-size:.85rem;color:var(--muted);}
    .tb-crumb strong{color:var(--ink);font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;}
    .tb-btn:hover{background:var(--border);}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:800;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--white);padding:0 2px;}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:900;text-decoration:none;overflow:hidden;}
    .tb-ava img{width:35px;height:35px;object-fit:cover;}

    /* CONTENT LAYOUT */
    .content-wrap{display:grid;grid-template-columns:1fr 320px;gap:24px;padding:24px 26px;flex:1;max-width:1100px;margin:0 auto;width:100%;}

    /* FEED POSTS */
    .feed-post{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);margin-bottom:20px;}
    .post-header{display:flex;align-items:center;gap:12px;padding:14px 16px;}
    .post-avatar{width:44px;height:44px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.1rem;font-weight:900;color:#fff;overflow:hidden;}
    .post-avatar img{width:44px;height:44px;object-fit:cover;border-radius:50%;}
    .post-author-name{font-weight:700;font-size:.9rem;color:var(--ink);}
    .post-author-name a{color:var(--ink);text-decoration:none;}
    .post-author-name a:hover{color:var(--orange);}
    .post-author-loc{font-size:.74rem;color:var(--muted);}
    .post-time{margin-left:auto;font-size:.72rem;color:var(--muted);}
    .post-media{width:100%;max-height:480px;object-fit:cover;display:block;}
    .post-media-video{width:100%;max-height:480px;display:block;}
    .post-actions{display:flex;align-items:center;gap:4px;padding:10px 16px 4px;}
    .action-btn{background:none;border:none;cursor:pointer;font-size:1.4rem;padding:4px 6px;border-radius:8px;transition:transform .15s;display:flex;align-items:center;gap:5px;}
    .action-btn:hover{transform:scale(1.12);}
    .action-btn.liked{color:#7C3AED;}
    .action-count{font-size:.8rem;font-weight:700;color:var(--ink2);}
    .post-caption{padding:6px 16px 4px;font-size:.86rem;line-height:1.5;}
    .post-caption strong{font-weight:700;}
    .post-skill-tags{display:flex;flex-wrap:wrap;gap:4px;padding:4px 16px 10px;}
    .skill-tag{font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:5px;background:var(--teal-lt);color:var(--teal);}

    /* COMMENTS */
    .comments-section{padding:0 16px 14px;border-top:1px solid var(--border);margin-top:8px;}
    .comments-toggle{background:none;border:none;color:var(--muted);font-size:.78rem;font-weight:600;cursor:pointer;padding:8px 0;display:block;}
    .comments-list{display:none;margin-bottom:8px;}
    .comments-list.open{display:block;}
    .comment-item{display:flex;gap:8px;margin-bottom:8px;align-items:flex-start;}
    .comment-ava{width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:900;color:#fff;overflow:hidden;}
    .comment-ava img{width:28px;height:28px;object-fit:cover;border-radius:50%;}
    .comment-body{background:var(--cream);border-radius:12px;padding:7px 12px;font-size:.8rem;flex:1;}
    .comment-body strong{font-weight:700;display:block;font-size:.75rem;margin-bottom:2px;}
    .comment-form{display:flex;gap:8px;margin-top:8px;}
    .comment-input{flex:1;border:1px solid var(--border);border-radius:20px;padding:7px 14px;font-family:var(--font-b);font-size:.82rem;background:var(--cream);outline:none;}
    .comment-input:focus{border-color:var(--orange);}
    .comment-submit{background:var(--orange);color:#fff;border:none;border-radius:20px;padding:7px 16px;font-family:var(--font-b);font-size:.8rem;font-weight:700;cursor:pointer;}

    /* SIDEBAR RIGHT */
    .sidebar-right{position:sticky;top:84px;align-self:start;}
    .sr-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);margin-bottom:16px;}
    .sr-title{font-family:var(--font-h);font-size:1rem;font-weight:400;margin-bottom:14px;}
    .suggest-item{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
    .suggest-ava{width:38px;height:38px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:900;color:#fff;overflow:hidden;}
    .suggest-ava img{width:38px;height:38px;object-fit:cover;border-radius:50%;}
    .suggest-name{font-weight:700;font-size:.84rem;color:var(--ink);}
    .suggest-name a{color:var(--ink);text-decoration:none;}
    .suggest-name a:hover{color:var(--teal);}
    .suggest-meta{font-size:.7rem;color:var(--muted);}
    .suggest-btn{margin-left:auto;padding:5px 12px;border-radius:100px;border:1.5px solid var(--teal);color:var(--teal);font-size:.72rem;font-weight:700;background:transparent;cursor:pointer;white-space:nowrap;text-decoration:none;}
    .suggest-btn:hover{background:var(--teal);color:#fff;}

    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}

    @media(max-width:900px){
      .content-wrap{grid-template-columns:1fr;}
      .sidebar-right{display:none;}
    }
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);transition:transform .28s ease;}
      .sidebar.open{transform:translateX(0);}
      .main{margin-left:0;}
      .content-wrap{padding:14px;}
      .hamburger{display:flex;color:var(--ink);}
    }

  </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava">
      <?php if(!empty($profile['profile_pic'])): ?>
        <img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt="">
      <?php else: ?><?=$avatar_letter?><?php endif;?>
    </div>
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
    <a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ── MAIN ── -->
<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <div class="tb-crumb"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Feed</strong></span></div>
    </div>
    <div class="tb-right">
      <a class="tb-btn" href="notifications.php" title="Notifications">
        <img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;"><?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?>
      </a>
      <a class="tb-btn" href="chat.php" title="Messages">
        <img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;"><?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?>
      </a>
      <a class="tb-ava" href="profile.php">
        <?php if(!empty($profile['profile_pic'])): ?>
          <img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt="">
        <?php else: ?><?=$avatar_letter?><?php endif;?>
      </a>
    </div>
  </div>

  <div class="content-wrap">
    <!-- LEFT: FEED -->
    <div>
      <?php if($feed_posts && $feed_posts->num_rows > 0):
        while($post = $feed_posts->fetch_assoc()):
          $letter = strtoupper(substr($post['name'], 0, 1));
          $color = $colors[crc32($post['name']) % count($colors)];
          $skill_list = array_slice(array_map('trim', explode(',', $post['skills'])), 0, 3);
      ?>
      <div class="feed-post">
        <!-- Post Header -->
        <div class="post-header">
          <div class="post-avatar" style="background:<?=$color?>">
            <?php if(!empty($post['profile_pic'])): ?>
              <img src="../uploads/profiles/<?=htmlspecialchars($post['profile_pic'])?>" alt="">
            <?php else: ?><?=$letter?><?php endif;?>
          </div>
          <div>
            <div class="post-author-name">
              <a href="public_profile.php?id=<?=$post['worker_id']?>"><?=htmlspecialchars($post['name'])?></a>
            </div>
            <div class="post-author-loc">
              <?=htmlspecialchars($post['location'] ?? '')?>
              <?php if($post['post_type'] !== 'photo'): ?>
                · <span style="font-size:.68rem;font-weight:800;color:var(--orange);text-transform:uppercase;"><?=$post['post_type']?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="post-time"><?=date('d M', strtotime($post['created_at']))?></div>
        </div>

        <!-- Media -->
        <?php if($post['is_video']): ?>
          <video class="post-media-video" src="../uploads/portfolio/<?=htmlspecialchars($post['media_file'])?>" controls></video>
        <?php else: ?>
          <img class="post-media" src="../uploads/portfolio/<?=htmlspecialchars($post['media_file'])?>" alt="Post" loading="lazy">
        <?php endif; ?>

        <!-- Actions -->
        <div class="post-actions">
          <a href="?like=<?=$post['id']?>" class="action-btn <?=$post['i_liked']?'liked':''?>" title="Like">
            <?=$post['i_liked']?'❤️':'🤍'?>
          </a>
          <span class="action-count"><?=$post['like_count']?></span>
          <button class="action-btn" onclick="toggleComments(<?=$post['id']?>)" title="Comments">💬</button>
          <span class="action-count"><?=$post['comment_count']?></span>
          <a href="public_profile.php?id=<?=$post['worker_id']?>" class="action-btn" title="View Profile" style="margin-left:auto;">👁️</a>
        </div>

        <!-- Caption -->
        <?php if(!empty($post['caption'])): ?>
          <div class="post-caption"><strong><?=htmlspecialchars(explode(' ', $post['name'])[0])?></strong> <?=htmlspecialchars($post['caption'])?></div>
        <?php endif;?>

        <!-- Skill tags -->
        <?php if(!empty($post['skills'])): ?>
          <div class="post-skill-tags">
            <?php foreach($skill_list as $sk): ?>
              <span class="skill-tag"><?=htmlspecialchars($sk)?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Comments Section -->
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
                <div class="comment-ava" style="background:<?=$cc?>">
                  <?php if(!empty($c['profile_pic'])): ?>
                    <img src="../uploads/profiles/<?=htmlspecialchars($c['profile_pic'])?>" alt="">
                  <?php else: ?><?=$cl?><?php endif;?>
                </div>
                <div class="comment-body">
                  <strong><?=htmlspecialchars($c['uname'])?></strong>
                  <?=htmlspecialchars($c['comment'])?>
                </div>
              </div>
              <?php endwhile; ?>
            </div>
          <?php endif; $conn3->close(); ?>
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
          <h3 style="font-family:var(--font-h);font-size:1.3rem;color:var(--ink2);margin-bottom:8px;">Feed is empty</h3>
          <p style="color:var(--muted);">No posts yet. Be the first to share your work on <a href="portfolio.php" style="color:var(--orange);font-weight:400;">Portfolio</a>!</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT SIDEBAR: Suggested Workers -->
    <div class="sidebar-right">
      <div class="sr-card">
        <div class="sr-title">👥 Discover Workers</div>
        <?php if($suggested && $suggested->num_rows > 0):
          while($s = $suggested->fetch_assoc()):
            $sl = strtoupper(substr($s['name'],0,1));
            $sc = $colors[crc32($s['name']) % count($colors)];
        ?>
          <div class="suggest-item">
            <div class="suggest-ava" style="background:<?=$sc?>">
              <?php if(!empty($s['profile_pic'])): ?>
                <img src="../uploads/profiles/<?=htmlspecialchars($s['profile_pic'])?>" alt="">
              <?php else: ?><?=$sl?><?php endif;?>
            </div>
            <div>
              <div class="suggest-name"><a href="public_profile.php?id=<?=$s['id']?>"><?=htmlspecialchars(explode(' ',$s['name'])[0])?></a></div>
              <div class="suggest-meta"><?=$s['post_count']?> posts</div>
            </div>
            <a class="suggest-btn" href="public_profile.php?id=<?=$s['id']?>">View</a>
          </div>
        <?php endwhile; endif; ?>
        <a href="job_seekers.php" style="display:block;text-align:center;font-size:.78rem;font-weight:700;color:var(--teal);margin-top:8px;text-decoration:none;">View all workers →</a>
      </div>

      <div class="sr-card" style="background:linear-gradient(135deg,#7C3AED,#9F67FA);color:#fff;border:none;">
        <div style="font-family:var(--font-h);font-size:1rem;font-weight:400;margin-bottom:8px;">📤 Share Your Work</div>
        <p style="font-size:.82rem;opacity:.9;margin-bottom:12px;">Upload photos & videos to get noticed by clients!</p>
        <a href="portfolio.php" style="display:block;background:#fff;color:var(--orange);text-align:center;padding:8px;border-radius:100px;font-weight:400;font-size:.82rem;text-decoration:none;">Upload Now</a>
      </div>
    </div>
  </div>
</div>

<script>
function toggleComments(id) {
  const el = document.getElementById('comments-' + id);
  if (el) el.classList.toggle('open');
}
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
