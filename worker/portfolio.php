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

// ── UPLOAD POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_post'])) {
    $caption = trim($conn->real_escape_string($_POST['caption'] ?? ''));
    $post_type = $_POST['post_type'] ?? 'photo'; // photo | video | reel

    if (!empty($_FILES['media']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
        $allowed_img = ['jpg','jpeg','png','webp','gif'];
        $allowed_vid = ['mp4','mov','webm','mkv'];
        $allowed     = array_merge($allowed_img, $allowed_vid);

        if (!in_array($ext, $allowed)) {
            $error = 'Only image (JPG, PNG, WEBP) or video (MP4, MOV) files allowed.';
        } elseif ($_FILES['media']['size'] > 50 * 1024 * 1024) {
            $error = 'File size must be under 50MB.';
        } else {
            $upload_dir = '../uploads/portfolio/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'post_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['media']['tmp_name'], $upload_dir . $filename)) {
                $is_video = in_array($ext, $allowed_vid) ? 1 : 0;
                $conn->query("INSERT INTO posts (worker_id, media_file, caption, post_type, is_video, created_at)
                              VALUES ($user_id, '$filename', '$caption', '$post_type', $is_video, NOW())");
                $success = 'Post uploaded successfully!';
            } else {
                $error = 'Upload failed. Please try again.';
            }
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

// ── DELETE POST ──
if (isset($_GET['del'])) {
    $pid = (int)$_GET['del'];
    $post = $conn->query("SELECT media_file FROM posts WHERE id=$pid AND worker_id=$user_id")->fetch_assoc();
    if ($post) {
        @unlink('../uploads/portfolio/' . $post['media_file']);
        $conn->query("DELETE FROM posts WHERE id=$pid AND worker_id=$user_id");
    }
    header("Location: portfolio.php"); exit();
}

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
    header("Location: portfolio.php"); exit();
}

// ── FETCH MY POSTS ──
$posts = $conn->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id) AS like_count,
           (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id AND user_id=$user_id) AS i_liked
    FROM posts p
    WHERE p.worker_id = $user_id
    ORDER BY p.created_at DESC
");

$post_count = $posts ? $posts->num_rows : 0;
$conn->close();
$active_page = 'portfolio';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Portfolio – SkillConnect</title>
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
    body.dark-mode .type-video{background:rgba(29,78,216,.16) !important;}
    body.dark-mode .type-reel{background:rgba(157,23,77,.18) !important;}
    body.dark-mode .upload-zone:hover,body.dark-mode .upload-zone.drag-over{background:rgba(124,58,237,.08) !important;}
/* Upload area */
    .upload-zone{
      border:2px dashed var(--border);border-radius:var(--radius);
      padding:32px;text-align:center;cursor:pointer;
      transition:border-color 0.2s,background 0.2s;
      background:var(--white);
    }
    .upload-zone:hover,.upload-zone.drag-over{
      border-color:var(--orange);background:#fffbf5;
    }
    .upload-zone .icon{font-size:2.5rem;margin-bottom:8px;}
    .upload-zone p{font-size:0.9rem;color:var(--muted);font-weight:400;}
    .upload-zone strong{color:var(--orange);}

    /* Portfolio grid */
    .portfolio-grid{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:14px;
    }
    @media(max-width:900px){.portfolio-grid{grid-template-columns:repeat(2,1fr);}}

    .post-card{
      background:var(--white);border:1px solid var(--border);
      border-radius:var(--radius);overflow:hidden;
      box-shadow:var(--shadow);transition:transform 0.2s;
      position:relative;
    }
    .post-card:hover{transform:translateY(-2px);}
    .post-card:hover .post-overlay{opacity:1;}

    .post-media{width:100%;aspect-ratio:1/1;object-fit:cover;display:block;}
    .post-video{width:100%;aspect-ratio:1/1;object-fit:cover;display:block;}

    .post-overlay{
      position:absolute;inset:0;
      background:rgba(11,31,58,0.55);
      display:flex;align-items:center;justify-content:center;gap:16px;
      opacity:0;transition:opacity 0.2s;
    }
    .overlay-btn{
      display:flex;align-items:center;gap:6px;
      color:#fff;font-size:0.85rem;font-weight:700;
      background:rgba(255,255,255,0.15);
      border:1px solid rgba(255,255,255,0.3);
      border-radius:100px;padding:6px 14px;
      text-decoration:none;cursor:pointer;border:none;
      transition:background 0.2s;
    }
    .overlay-btn:hover{background:rgba(255,255,255,0.25);}

    .post-info{padding:12px 14px;}
    .post-caption{font-size:0.85rem;color:var(--ink2);line-height:1.5;margin-bottom:8px;
                  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .post-meta{display:flex;justify-content:space-between;align-items:center;}
    .post-likes{font-size:0.8rem;font-weight:700;color:var(--muted);}
    .post-type-badge{
      font-size:0.68rem;font-weight:800;padding:2px 8px;border-radius:100px;
      text-transform:uppercase;letter-spacing:0.5px;
    }
    .type-photo{background:var(--teal-lt);color:var(--teal);}
    .type-video{background:#eff6ff;color:#1d4ed8;}
    .type-reel{background:#fdf2f8;color:#9d174d;}

    /* Modal */
    .modal-backdrop{
      display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);
      z-index:999;align-items:center;justify-content:center;
    }
    .modal-backdrop.open{display:flex;}
    .modal{
      background:var(--white);border-radius:var(--radius);
      padding:28px;width:90%;max-width:500px;
      box-shadow:0 20px 60px rgba(0,0,0,0.3);
    }
    .modal-title{font-family:var(--font-h);font-size:1.2rem;font-weight:900;margin-bottom:20px;}

    .field-label{font-size:0.78rem;font-weight:700;color:var(--muted);
                 text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:block;}
    .field-input{
      width:100%;padding:10px 14px;border:1.5px solid var(--border);
      border-radius:10px;font-family:var(--font-b);font-size:0.9rem;
      background:var(--cream);color:var(--ink);outline:none;transition:border-color 0.2s;
    }
    .field-input:focus{border-color:var(--orange);}
  
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
    <a href="recommended.php" ><span class="nav-ic"><img src="../assets/icons/recommend jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Recommended</a>
    <div class="nav-section">Connect</div>
    <a href="network.php" ><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="portfolio.php" class="active"><span class="nav-ic"><img src="../assets/icons/total count.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Portfolio</a>
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
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/total count.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Portfolio & Posts</strong></span></div>
    </div>
    <div class="topbar-right">
      <span style="font-size:0.85rem;color:var(--muted);font-weight:600;"><?= $post_count ?> posts</span>
      <button onclick="document.getElementById('upload-modal').classList.add('open')"
              style="padding:8px 20px;background:var(--orange);color:#fff;border:none;
                     border-radius:100px;font-family:var(--font-b);font-size:0.85rem;
                     font-weight:400;cursor:pointer;">
        + Upload
      </button>
    </div>
  </div>

  <div class="page-content">

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

    <!-- Upload Zone (click to open modal) -->
    <div class="upload-zone" style="margin-bottom:28px;"
         onclick="document.getElementById('upload-modal').classList.add('open')">
      <div class="icon">📸</div>
      <p><strong>Click to upload</strong> photos, videos, or reels</p>
      <p style="margin-top:4px;font-size:0.8rem;">Showcase your work to recruiters · Max 50MB</p>
    </div>

    <!-- Portfolio Grid -->
    <?php if ($post_count > 0): ?>
      <div class="portfolio-grid">
        <?php while ($post = $posts->fetch_assoc()): ?>
          <div class="post-card">
            <!-- Media -->
            <?php if ($post['is_video']): ?>
              <video class="post-video" preload="none" poster="">
                <source src="../uploads/portfolio/<?= htmlspecialchars($post['media_file']) ?>">
              </video>
              <div style="position:absolute;top:10px;left:10px;background:rgba(0,0,0,0.6);
                          color:#fff;font-size:0.75rem;font-weight:400;padding:3px 8px;border-radius:100px;">
                🎬 Video
              </div>
            <?php else: ?>
              <img class="post-media"
                   src="../uploads/portfolio/<?= htmlspecialchars($post['media_file']) ?>"
                   alt="Post" loading="lazy">
            <?php endif; ?>

            <!-- Hover Overlay -->
            <div class="post-overlay">
              <a href="?like=<?= $post['id'] ?>" class="overlay-btn">
                <?= $post['i_liked'] ? '❤️' : '🤍' ?> <?= $post['like_count'] ?>
              </a>
              <button onclick="openViewModal('<?= htmlspecialchars($post['media_file']) ?>',<?= $post['is_video'] ?>)"
                      class="overlay-btn">
                👁 View
              </button>
            </div>

            <!-- Info -->
            <div class="post-info">
              <?php if (!empty($post['caption'])): ?>
                <div class="post-caption"><?= htmlspecialchars($post['caption']) ?></div>
              <?php endif; ?>
              <div class="post-meta">
                <span class="post-likes">❤️ <?= $post['like_count'] ?> likes</span>
                <div style="display:flex;gap:6px;align-items:center;">
                  <span class="post-type-badge type-<?= $post['post_type'] ?>">
                    <?= $post['post_type'] ?>
                  </span>
                  <a href="?del=<?= $post['id'] ?>"
                     onclick="return confirm('Delete this post?')"
                     style="color:var(--muted);font-size:0.8rem;text-decoration:none;"
                     title="Delete">🗑</a>
                </div>
              </div>
              <div style="font-size:0.72rem;color:var(--muted);margin-top:4px;font-weight:400;">
                <?= date('d M Y', strtotime($post['created_at'])) ?>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>

    <?php else: ?>
      <div style="text-align:center;padding:60px 20px;color:var(--muted);">
        <div style="font-size:3rem;margin-bottom:14px;">🖼️</div>
        <h3 style="font-family:var(--font-h);font-size:1.3rem;color:var(--ink2);margin-bottom:8px;">
          Your portfolio is empty
        </h3>
        <p>Upload photos and videos to showcase your work to recruiters.</p>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- ── UPLOAD MODAL ── -->
<div class="modal-backdrop" id="upload-modal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <div class="modal-title">📤 Upload Post</div>
      <button onclick="document.getElementById('upload-modal').classList.remove('open')"
              style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted);">✕</button>
    </div>

    <form method="POST" enctype="multipart/form-data">

      <!-- File Drop Zone -->
      <div class="upload-zone" id="drop-zone" style="margin-bottom:16px;"
           ondragover="event.preventDefault();this.classList.add('drag-over')"
           ondragleave="this.classList.remove('drag-over')"
           ondrop="handleDrop(event)">
        <div class="icon" id="drop-icon">📁</div>
        <p id="drop-text">Drag & drop or <strong>click to browse</strong></p>
        <input type="file" name="media" id="media-input" accept="image/*,video/*"
               style="display:none;" onchange="previewFile(this)">
      </div>
      <div id="file-preview" style="margin-bottom:16px;display:none;">
        <img id="img-preview" style="width:100%;border-radius:10px;max-height:200px;object-fit:cover;display:none;">
        <video id="vid-preview" style="width:100%;border-radius:10px;max-height:200px;display:none;" controls></video>
        <div id="file-name" style="font-size:0.8rem;color:var(--muted);margin-top:6px;font-weight:400;"></div>
      </div>

      <!-- Caption -->
      <div style="margin-bottom:16px;">
        <label class="field-label">Caption</label>
        <textarea name="caption" rows="3" class="field-input"
                  placeholder="Describe your work, technique, or the story behind it..."></textarea>
      </div>

      <!-- Post Type -->
      <div style="margin-bottom:20px;">
        <label class="field-label">Post Type</label>
        <div class="post-type-group" style="display:flex;gap:8px;">
          <?php foreach (['photo'=>'📸 Photo','video'=>'🎬 Video','reel'=>'🎞️ Reel'] as $val=>$lbl): ?>
            <label style="flex:1;text-align:center;cursor:pointer;">
              <input type="radio" name="post_type" value="<?= $val ?>"
                     <?= $val==='photo'?'checked':'' ?> class="post-type-radio" style="display:none;">
              <span class="post-type-pill" style="display:block;padding:8px;border:1.5px solid var(--border);border-radius:10px;
                           font-size:0.82rem;font-weight:400;transition:all 0.2s;">
                <?= $lbl ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" name="upload_post"
                style="flex:1;padding:12px;background:var(--orange);color:#fff;border:none;
                       border-radius:100px;font-family:var(--font-b);font-size:0.9rem;
                       font-weight:400;cursor:pointer;">
          📤 Upload Post
        </button>
        <button type="button"
                onclick="document.getElementById('upload-modal').classList.remove('open')"
                style="padding:12px 20px;background:var(--cream2);color:var(--ink2);
                       border:1px solid var(--border);border-radius:100px;
                       font-family:var(--font-b);font-size:0.9rem;font-weight:400;cursor:pointer;">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── VIEW MODAL ── -->
<div class="modal-backdrop" id="view-modal">
  <div style="max-width:650px;width:92%;position:relative;">
    <button onclick="document.getElementById('view-modal').classList.remove('open')"
            style="position:absolute;top:-14px;right:-14px;width:36px;height:36px;border-radius:50%;
                   background:#fff;border:none;font-size:1.1rem;cursor:pointer;z-index:10;
                   box-shadow:0 2px 8px rgba(0,0,0,0.3);">✕</button>
    <img id="view-img" style="width:100%;border-radius:var(--radius);display:block;max-height:80vh;object-fit:contain;display:none;">
    <video id="view-vid" style="width:100%;border-radius:var(--radius);display:none;" controls></video>
  </div>
</div>

<script>
// Open file browser on drop zone click
document.getElementById('drop-zone').addEventListener('click', () => {
  document.getElementById('media-input').click();
});

// Preview selected file
function previewFile(input) {
  const file = input.files[0];
  if (!file) return;
  const preview = document.getElementById('file-preview');
  const img = document.getElementById('img-preview');
  const vid = document.getElementById('vid-preview');
  const fname = document.getElementById('file-name');
  preview.style.display = 'block';
  fname.textContent = file.name + ' (' + (file.size/1024/1024).toFixed(2) + ' MB)';
  const url = URL.createObjectURL(file);
  if (file.type.startsWith('video')) {
    img.style.display = 'none';
    vid.style.display = 'block';
    vid.src = url;
  } else {
    vid.style.display = 'none';
    img.style.display = 'block';
    img.src = url;
  }
  document.getElementById('drop-icon').textContent = '✅';
  document.getElementById('drop-text').innerHTML = '<strong>File ready to upload</strong>';
}

// Drag and drop
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('drop-zone').classList.remove('drag-over');
  const input = document.getElementById('media-input');
  input.files = e.dataTransfer.files;
  previewFile(input);
}

// View full post
function openViewModal(filename, isVideo) {
  const modal = document.getElementById('view-modal');
  const img = document.getElementById('view-img');
  const vid = document.getElementById('view-vid');
  const src = '../uploads/portfolio/' + filename;
  if (isVideo) {
    img.style.display = 'none';
    vid.style.display = 'block';
    vid.src = src;
  } else {
    vid.style.display = 'none';
    img.style.display = 'block';
    img.src = src;
  }
  modal.classList.add('open');
}

// Close modals on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', e => {
    if (e.target === m) {
      m.classList.remove('open');
      const vid = document.getElementById('view-vid');
      if (vid) vid.pause();
    }
  });
});

// ── POST TYPE PILL SELECTOR (Photo / Video / Reel) ──
// Fixed: previous inline onchange referenced a non-existent ".radio-group-wrap"
// element, so clicking never updated the highlighted pill correctly.
function updatePostTypeStyles() {
  document.querySelectorAll('.post-type-radio').forEach(radio => {
    const pill = radio.nextElementSibling;
    if (!pill) return;
    if (radio.checked) {
      pill.style.background = 'var(--orange)';
      pill.style.color = '#fff';
      pill.style.borderColor = 'var(--orange)';
    } else {
      pill.style.background = 'var(--white)';
      pill.style.color = 'var(--ink)';
      pill.style.borderColor = 'var(--border)';
    }
  });
}
document.querySelectorAll('.post-type-radio').forEach(radio => {
  radio.addEventListener('change', updatePostTypeStyles);
});
// Highlight the default-checked option (Photo) as soon as the page loads
updatePostTypeStyles();
</script>

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