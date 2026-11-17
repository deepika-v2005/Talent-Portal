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

// ── SUBMIT RATING ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_rating'])) {
    $worker_id = (int)$_POST['worker_id'];
    $stars     = min(5,max(1,(int)$_POST['stars']));
    $review    = trim($conn->real_escape_string($_POST['review_text']??''));
    // Verify hired relationship
    $check = $conn->query("SELECT a.id FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.client_id=$user_id AND a.worker_id=$worker_id AND a.status='accepted' LIMIT 1");
    if ($check->num_rows === 0) { $error = 'You can only rate workers you have hired.'; }
    else {
        $existing = $conn->query("SELECT id FROM ratings WHERE worker_id=$worker_id AND client_id=$user_id");
        if ($existing->num_rows > 0)
            $conn->query("UPDATE ratings SET stars=$stars,review='$review',created_at=NOW() WHERE worker_id=$worker_id AND client_id=$user_id");
        else
            $conn->query("INSERT INTO ratings (worker_id,client_id,stars,review,created_at) VALUES ($worker_id,$user_id,$stars,'$review',NOW())");
        $success = 'Rating submitted!';
    }
}

// ── WORKERS I CAN RATE (hired) ──
$rateable = $conn->query("
    SELECT DISTINCT u.id,u.name,u.location,u.profile_pic,
           (SELECT stars FROM ratings WHERE worker_id=u.id AND client_id=$user_id LIMIT 1) AS my_rating,
           (SELECT review FROM ratings WHERE worker_id=u.id AND client_id=$user_id LIMIT 1) AS my_review,
           j.title AS job_title
    FROM applications a JOIN jobs j ON a.job_id=j.id JOIN users u ON u.id=a.worker_id
    WHERE j.client_id=$user_id AND a.status='accepted'
");

// Pre-select if coming from hired.php
$pre_worker = isset($_GET['rate']) ? (int)$_GET['rate'] : 0;
$conn->close();
$colors=['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706'];
function stars_html($n){$o='';for($i=1;$i<=5;$i++) $o.=$i<=$n?'★':'☆';return $o;}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Ratings – SkillConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;--red:#dc2626;--red-lt:#fef2f2;--yellow:#d97706;--sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,.07);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;}
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
.page-title{font-family:var(--font-h);font-size:1.5rem;font-weight:400;margin-bottom:22px;}
.alert{border-radius:10px;padding:12px 16px;font-size:.86rem;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
.alert.success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
.alert.error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
.two-col{display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start;}
.card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:22px;margin-bottom:16px;box-shadow:var(--shadow);}
.card:last-child{margin-bottom:0;}
.card-title{font-family:var(--font-h);font-size:1rem;font-weight:400;margin-bottom:16px;}
.worker-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);cursor:pointer;transition:background .18s;border-radius:8px;padding:10px;}
.worker-row:last-child{border-bottom:none;}
.worker-row:hover{background:var(--cream2);}
.worker-row.selected{background:rgba(124,58,237,.07);border:1px solid rgba(124,58,237,.2);border-radius:10px;}
.w-ava{width:40px;height:40px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.95rem;font-weight:400;color:#fff;overflow:hidden;}
.w-ava img{width:40px;height:40px;object-fit:cover;}
.w-name{font-size:.88rem;font-weight:400;color:var(--ink);}
.w-job{font-size:.72rem;color:var(--muted);}
.w-rated{font-size:.78rem;color:var(--yellow);margin-left:auto;}
/* Star select */
.star-select{display:flex;gap:6px;flex-direction:row-reverse;justify-content:flex-end;}
.star-select input{display:none;}
.star-select label{font-size:1.9rem;cursor:pointer;color:#e4ddd1;transition:color .15s;}
.star-select input:checked ~ label,.star-select label:hover,.star-select label:hover ~ label{color:#f59e0b;}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:.82rem;font-weight:400;color:var(--ink2);margin-bottom:6px;}
.fg textarea{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-family:var(--font-b);font-size:.88rem;background:var(--cream);color:var(--ink);resize:vertical;min-height:90px;outline:none;transition:border-color .2s;}
.fg textarea:focus{border-color:var(--teal);}
.btn-rate{width:100%;padding:11px;background:var(--teal);color:#fff;font-family:var(--font-b);font-size:.9rem;font-weight:400;border:none;border-radius:100px;cursor:pointer;box-shadow:0 4px 14px rgba(124,58,237,.25);transition:background .2s;}
.btn-rate:hover{background:#0b6b58;}
.empty-state{text-align:center;padding:40px 20px;color:var(--muted);}
.empty-state .ei{font-size:2.2rem;margin-bottom:10px;}
.empty-state h3{font-family:var(--font-h);font-size:1.1rem;color:var(--ink2);}
@media(max-width:1000px){.two-col{grid-template-columns:1fr;}}
@media(max-width:768px){.sidebar{transform:translateX(-240px);}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.content{padding:14px;}.hamburger{display:flex;}}


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
body.dark-mode .card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .worker-row{border-bottom:1px solid #1E293B;}
body.dark-mode .worker-row:hover{background:#1E293B;}
body.dark-mode .w-name{color:#E2E8F0;}
body.dark-mode .w-job{color:#94A3B8;}
body.dark-mode .fg label{color:#CBD5E1;}
body.dark-mode .fg textarea{border:1.5px solid #1E293B;background:#0B1220;color:#E2E8F0;}
body.dark-mode .empty-state{color:#94A3B8;}
body.dark-mode .empty-state h3{color:#CBD5E1;}
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
    <a href="applicants.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants<?php if($pending_apps>0): ?><span class="nav-bdg"><?=$pending_apps?></span><?php endif;?></a>
    <a href="hired.php"><span class="nav-ic"><img src="../assets/icons/job accepted.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Hired</a>
    <div class="nav-section">Connect</div>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if($unread_msgs>0): ?><span class="nav-bdg"><?=$unread_msgs?></span><?php endif;?></a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if($unread_notif>0): ?><span class="nav-bdg"><?=$unread_notif?></span><?php endif;?></a>
    <div class="nav-section">Account</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a>
    <a href="ratings.php" class="active"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <div class="tb-left"><div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/rating.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;"><span class="tb-crumb">Dashboard / <strong>Ratings</strong></span></div>
    <div class="tb-right"><a href="chat.php" class="tb-btn"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a><a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a><a href="profile.php" class="tb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a></div>
  </div>
  <div class="content">
    <div class="page-title" style="display:flex;align-items:center;gap:9px;"><img src="../assets/icons/rating.png" alt="" style="width:45px;height:45px;object-fit:contain;"> Rate Workers</div>
    <?php if($success): ?><div class="alert success">✅ <?=$success?></div><?php endif;?>
    <?php if($error): ?><div class="alert error">⚠️ <?=$error?></div><?php endif;?>

    <?php if($rateable&&$rateable->num_rows>0): $workers=[];while($r=$rateable->fetch_assoc()) $workers[]=$r; ?>
    <div class="two-col">
      <!-- Worker List -->
      <div class="card">
        <div class="card-title"><img src="../assets/icons/job accepted.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">Hired Workers</div>
        <?php foreach($workers as $i=>$w): $c=$colors[$i%count($colors)]; ?>
          <div class="worker-row <?=$pre_worker===$w['id']?'selected':''?>" onclick="selectWorker(<?=$w['id']?>,<?=$w['my_rating']??0?>,'<?=addslashes($w['name'])?>','<?=addslashes($w['my_review']??'')?>','<?=$c?>')">
            <div class="w-ava" style="background:<?=$c?>;"><?php if(!empty($w['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($w['profile_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($w['name'],0,1))?><?php endif;?></div>
            <div>
              <div class="w-name"><?=htmlspecialchars($w['name'])?></div>
              <div class="w-job">📌 <?=htmlspecialchars(mb_strimwidth($w['job_title'],0,30,'…'))?></div>
            </div>
            <?php if($w['my_rating']): ?>
              <div class="w-rated" style="margin-left:auto;color:#f59e0b;"><?=stars_html($w['my_rating'])?></div>
            <?php else: ?>
              <span style="margin-left:auto;font-size:.7rem;color:var(--muted);">Not rated</span>
            <?php endif;?>
          </div>
        <?php endforeach;?>
      </div>

      <!-- Rating Form -->
      <div class="card" id="rating-card">
        <div class="card-title" id="rating-title">⭐ Select a Worker to Rate</div>
        <form method="POST" id="rating-form" style="display:none;">
          <input type="hidden" name="worker_id" id="sel-worker-id">
          <div class="fg">
            <label>Your Rating</label>
            <div class="star-select" id="star-select">
              <?php for($s=5;$s>=1;$s--): ?><input type="radio" name="stars" id="st<?=$s?>" value="<?=$s?>"><label for="st<?=$s?>">★</label><?php endfor;?>
            </div>
          </div>
          <div class="fg">
            <label>Your Review</label>
            <textarea name="review_text" id="review-text" placeholder="Describe your experience working with this person..."></textarea>
          </div>
          <button type="submit" name="submit_rating" class="btn-rate">⭐ Submit Rating</button>
        </form>
        <div id="rate-placeholder" style="text-align:center;padding:30px;color:var(--muted);">
          <div style="font-size:2rem;margin-bottom:8px;">👆</div>
          <div style="font-size:.85rem;">Click on a worker from the left to rate them.</div>
        </div>
      </div>
    </div>

    <?php else: ?>
      <div class="empty-state"><div class="ei"><img src="../assets/icons/rating.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div><h3>No hired workers yet</h3><p style="margin-top:6px;">Hire workers to rate them after the job is done.</p><a href="applicants.php" style="display:inline-block;margin-top:14px;padding:9px 22px;background:var(--teal);color:#fff;border-radius:100px;font-weight:400;text-decoration:none;font-size:.85rem;">View Applicants</a></div>
    <?php endif;?>
  </div>
</div>
<script>
function selectWorker(id, existing_stars, name, existing_review, color) {
  document.querySelectorAll('.worker-row').forEach(r=>r.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  document.getElementById('sel-worker-id').value = id;
  document.getElementById('rating-title').textContent = '⭐ Rate: ' + name;
  document.getElementById('rating-form').style.display = 'block';
  document.getElementById('rate-placeholder').style.display = 'none';
  document.getElementById('review-text').value = existing_review || '';
  // Set stars
  if (existing_stars > 0) {
    const radio = document.getElementById('st' + existing_stars);
    if (radio) radio.checked = true;
  }
}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
// Auto-open if pre_worker set
<?php if($pre_worker>0): foreach($workers as $i=>$w): if($w['id']===$pre_worker): ?>
window.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.worker-row')[<?=$i?>]?.click();
});
<?php endif; endforeach; endif;?>
</script>

</body></html>
