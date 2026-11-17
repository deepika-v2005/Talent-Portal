<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); exit();
}
require_once '../includes/db.php';
$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$profile   = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending_apps = (int)$conn->query("SELECT COUNT(*) as c FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.client_id=$user_id AND a.status='pending'")->fetch_assoc()['c'];
$unread_notif = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs  = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$avatar_letter = strtoupper(substr($user_name,0,1));
$first_name = explode(' ',$user_name)[0];

// ── SEND CONNECTION ──
if (isset($_GET['connect'])) {
    $wid = (int)$_GET['connect'];
    $check = $conn->query("SELECT id FROM connections WHERE worker_id=$wid AND client_id=$user_id");
    if ($check->num_rows === 0) {
        $conn->query("INSERT INTO connections (worker_id, client_id, status, created_at) VALUES ($wid, $user_id, 'pending', NOW())");
        $cn = $conn->real_escape_string($user_name);
        $msg = "$cn wants to connect with you!";
        $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at) VALUES ($wid,'connection','$msg',0,NOW())");
    }
    header("Location: job_seekers.php?sent=1"); exit();
}

$search = trim($_GET['search'] ?? '');
$skill_filter = trim($_GET['skill'] ?? '');
$where = "role='worker'";
if (!empty($search)) $where .= " AND (name LIKE '%" . $conn->real_escape_string($search) . "%' OR location LIKE '%" . $conn->real_escape_string($search) . "%')";
if (!empty($skill_filter)) $where .= " AND skills LIKE '%" . $conn->real_escape_string($skill_filter) . "%'";

$workers = $conn->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM posts WHERE worker_id=u.id) AS post_count,
           (SELECT AVG(stars) FROM ratings WHERE worker_id=u.id) AS avg_rating,
           (SELECT COUNT(*) FROM ratings WHERE worker_id=u.id) AS rating_count,
           (SELECT status FROM connections WHERE worker_id=u.id AND client_id=$user_id) AS conn_status,
           (SELECT COUNT(*) FROM applications WHERE worker_id=u.id) AS application_count
    FROM users u WHERE $where ORDER BY post_count DESC, avg_rating DESC
");
$conn->close();

$colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Job Seekers – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--blue:#1d4ed8;--blue-lt:#eff6ff;--border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;--sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,.07);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;}
    .sidebar{width:var(--sidebar);height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}.sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}.sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}.sb-ava{width:40px;height:40px;border-radius:11px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:400;overflow:hidden;flex-shrink:0;}.sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}.sb-uname{font-size:.86rem;font-weight:400;color:#fff;}.sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
    .sb-nav{flex:1;padding:10px 0;}.nav-section{font-size:.62rem;font-weight:400;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}.sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}.sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}.sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}.nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:400;padding:2px 6px;border-radius:100px;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}.sb-logout a{display:flex;align-items:center;justify-content:center;gap:9px;padding:10px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}
    .main{margin-left:var(--sidebar);flex:1;}.topbar{height:60px;padding:0 26px;background:rgba(245,243,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}.hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;}.tb-crumb{font-size:.85rem;color:var(--muted);}.tb-crumb strong{color:var(--ink);font-weight:400;}.tb-right{display:flex;align-items:center;gap:9px;}.tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;}.tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:400;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--cream);}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;text-decoration:none;overflow:hidden;}.tb-ava img{width:35px;height:35px;object-fit:cover;}
    .content{padding:24px 26px;}
    .page-title{font-family:var(--font-h);font-size:1.5rem;font-weight:400;margin-bottom:6px;}
    .page-sub{font-size:.85rem;color:var(--muted);margin-bottom:20px;}
    .filters{display:flex;gap:10px;margin-bottom:22px;flex-wrap:wrap;}
    .search-input{flex:1;min-width:200px;border:1.5px solid var(--border);border-radius:100px;padding:10px 18px;font-family:var(--font-b);font-size:.88rem;background:var(--white);outline:none;}.search-input:focus{border-color:var(--orange);}
    .search-btn{padding:10px 22px;border-radius:100px;background:var(--orange);color:#fff;border:none;font-family:var(--font-b);font-size:.86rem;font-weight:400;cursor:pointer;}
    .workers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;}
    .worker-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);transition:transform .18s;}.worker-card:hover{transform:translateY(-2px);}
    .wc-header{height:4px;background:linear-gradient(90deg,var(--teal),var(--blue));}
    .wc-body{padding:18px;}
    .wc-top{display:flex;gap:12px;align-items:center;margin-bottom:12px;}
    .wc-ava{width:54px;height:54px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;overflow:hidden;}.wc-ava img{width:54px;height:54px;object-fit:cover;border-radius:50%;}
    .wc-name{font-family:var(--font-h);font-size:.98rem;font-weight:400;}.wc-name a{color:var(--ink);text-decoration:none;}.wc-name a:hover{color:var(--orange);}
    .wc-loc{font-size:.74rem;color:var(--muted);margin-top:2px;}
    .wc-stats{display:flex;gap:14px;margin-bottom:10px;flex-wrap:wrap;}
    .wc-stat{font-size:.72rem;color:var(--muted);}.wc-stat strong{font-weight:400;color:var(--ink2);}
    .wc-skills{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:14px;}
    .chip{font-size:.68rem;font-weight:400;padding:2px 8px;border-radius:5px;background:var(--teal-lt);color:var(--teal);}
    .wc-bio{font-size:.78rem;color:var(--muted);line-height:1.5;margin-bottom:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .wc-actions{display:flex;gap:6px;border-top:1px solid var(--border);padding-top:12px;}
    .btn{padding:7px 16px;border-radius:100px;font-family:var(--font-b);font-size:.76rem;font-weight:400;text-decoration:none;border:none;cursor:pointer;transition:all .18s;}
    .btn-view{background:var(--orange);color:#fff;}.btn-view:hover{background:#d45609;}
    .btn-connect{background:var(--teal);color:#fff;}.btn-connect:hover{background:#0a6a57;}
    .btn-pending{background:var(--cream2);color:var(--muted);border:1.5px solid var(--border);}
    .btn-connected{background:var(--green-lt);color:var(--green);border:1.5px solid var(--green);}
    .btn-chat{background:var(--blue-lt);color:var(--blue);}
    .stars{color:#f59e0b;font-size:.82rem;}
    .badge-new{background:#fef3c7;color:#d97706;font-size:.62rem;font-weight:400;padding:2px 7px;border-radius:100px;margin-left:4px;}
    .toast{position:fixed;bottom:24px;right:24px;background:var(--teal);color:#fff;padding:12px 22px;border-radius:100px;font-weight:400;font-size:.86rem;box-shadow:var(--shadow);z-index:9999;}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}.sb-overlay.show{display:block;}
    @media(max-width:768px){.sidebar{transform:translateX(-240px);transition:transform .28s ease;}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.content{padding:14px;}.hamburger{display:flex;}}


/* ============ AUTO-GENERATED DARK MODE ============ */
body.dark-mode{background:#0B1220;color:#E2E8F0;}
body.dark-mode .topbar{background:rgba(11,18,32,.96);border-bottom:1px solid #1E293B;}
body.dark-mode .hamburger{background:#1E293B;border:1px solid #1E293B;}
body.dark-mode .tb-crumb{color:#94A3B8;}
body.dark-mode .tb-crumb strong{color:#E2E8F0;}
body.dark-mode .tb-btn{background:#0B1220;border:1px solid #1E293B;}
body.dark-mode .tb-dot{border:2px solid #0B1220;}
body.dark-mode .page-sub{color:#94A3B8;}
body.dark-mode .search-input{border:1.5px solid #1E293B;background:#111827;}
body.dark-mode .worker-card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .wc-name a{color:#E2E8F0;}
body.dark-mode .wc-loc{color:#94A3B8;}
body.dark-mode .wc-stat{color:#94A3B8;}
body.dark-mode .wc-stat strong{color:#CBD5E1;}
body.dark-mode .chip{background:rgba(124,58,237,.18);}
body.dark-mode .wc-bio{color:#94A3B8;}
body.dark-mode .wc-actions{border-top:1px solid #1E293B;}
body.dark-mode .btn-pending{background:#1E293B;color:#94A3B8;border:1.5px solid #1E293B;}
body.dark-mode .btn-connected{background:rgba(22,163,74,.14);}
body.dark-mode .btn-chat{background:rgba(29,78,216,.16);}
body.dark-mode .badge-new{background:rgba(217,119,6,.16);}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>

<?php if(isset($_GET['sent'])): ?>
<div class="toast" id="toast">✅ Connection request sent!</div>
<script>setTimeout(()=>{document.getElementById('toast').style.opacity=0;},3000);</script>
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
    <a href="feed.php"><span class="nav-ic"><img src="../assets/icons/explore.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Feed</a>
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
      <div class="tb-crumb"><strong>Job Seekers</strong></div>
    </div>
    <div class="tb-right">
      <a class="tb-btn" href="notifications.php">🔔<?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a>
      <a class="tb-btn" href="chat.php">💬<?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a>
      <a class="tb-ava" href="profile.php"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a>
    </div>
  </div>
  <div class="content">
    <div class="page-title">👥 All Registered Job Seekers</div>
    <div class="page-sub">Connect with workers and send them job offers after they accept your request</div>
    <form method="GET" class="filters">
      <input type="text" name="search" class="search-input" placeholder="Search by name or location..." value="<?=htmlspecialchars($search)?>">
      <input type="text" name="skill" class="search-input" style="max-width:220px;" placeholder="Filter by skill..." value="<?=htmlspecialchars($skill_filter)?>">
      <button type="submit" class="search-btn">🔍 Search</button>
    </form>
    <div class="workers-grid">
      <?php if($workers && $workers->num_rows > 0):
        while($w = $workers->fetch_assoc()):
          $wl = strtoupper(substr($w['name'],0,1));
          $wc = $colors[crc32($w['name']) % count($colors)];
          $skills = array_slice(array_map('trim', explode(',', $w['skills'] ?? '')), 0, 4);
          $rating = $w['avg_rating'] ? number_format($w['avg_rating'],1) : null;
          $conn_st = $w['conn_status'];
          // Check if recently joined (within 7 days)
          $is_new = (strtotime($w['created_at']) > strtotime('-7 days'));
      ?>
      <div class="worker-card">
        <div class="wc-header" style="background:linear-gradient(90deg,<?=$wc?>,<?=$colors[(array_search($wc,$colors)+2)%count($colors)]?>);"></div>
        <div class="wc-body">
          <div class="wc-top">
            <div class="wc-ava" style="background:<?=$wc?>"><?php if(!empty($w['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($w['profile_pic'])?>" alt=""><?php else: ?><?=$wl?><?php endif;?></div>
            <div style="flex:1;">
              <div class="wc-name">
                <a href="applicant_profile.php?id=<?=$w['id']?>"><?=htmlspecialchars($w['name'])?></a>
                <?php if($is_new): ?><span class="badge-new">NEW</span><?php endif;?>
              </div>
              <div class="wc-loc">📍 <?=htmlspecialchars($w['location'] ?? '—')?></div>
            </div>
          </div>
          <div class="wc-stats">
            <div class="wc-stat"><strong><?=$w['post_count']?></strong> posts</div>
            <div class="wc-stat"><strong><?=$w['application_count']?></strong> apps</div>
            <?php if($rating): ?><div class="wc-stat"><span class="stars">★</span> <?=$rating?> (<?=$w['rating_count']?>)</div><?php endif;?>
          </div>
          <?php if(!empty($w['bio'])): ?><div class="wc-bio"><?=htmlspecialchars($w['bio'])?></div><?php endif;?>
          <?php if(!empty($w['skills'])): ?>
            <div class="wc-skills">
              <?php foreach($skills as $sk): if(!empty(trim($sk))): ?><span class="chip"><?=htmlspecialchars(trim($sk))?></span><?php endif; endforeach;?>
            </div>
          <?php endif;?>
          <div class="wc-actions">
            <a href="applicant_profile.php?id=<?=$w['id']?>" class="btn btn-view">👁️ Profile</a>
            <a href="chat.php?to=<?=$w['id']?>" class="btn btn-chat">💬 Chat</a>
            <?php if($conn_st === 'accepted'): ?>
              <span class="btn btn-connected">✅ Connected</span>
            <?php elseif($conn_st === 'pending'): ?>
              <span class="btn btn-pending">⏳ Sent</span>
            <?php else: ?>
              <a href="?connect=<?=$w['id']?>&search=<?=urlencode($search)?>&skill=<?=urlencode($skill_filter)?>" class="btn btn-connect">🔗 Connect</a>
            <?php endif;?>
          </div>
        </div>
      </div>
      <?php endwhile;
      else: ?>
        <div style="grid-column:1/-1;text-align:center;padding:60px 20px;background:var(--white);border:1px solid var(--border);border-radius:var(--radius);color:var(--muted);">
          <div style="font-size:3rem;margin-bottom:12px;">👥</div>
          <p>No job seekers found<?=!empty($search)?' matching your search':''?>.</p>
        </div>
      <?php endif;?>
    </div>
  </div>
</div>
<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

</body>
</html>
