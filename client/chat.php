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

// ── SEND MESSAGE ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_msg'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $message = trim($conn->real_escape_string($_POST['message']??''));
    if (!empty($message) && $receiver_id > 0) {
        $conn->query("INSERT INTO messages (sender_id,receiver_id,message,is_read,created_at) VALUES ($user_id,$receiver_id,'$message',0,NOW())");
    }
    header("Location: chat.php?with=$receiver_id"); exit();
}

// ── ACTIVE PARTNER ──
$with_id = (int)($_GET['with'] ?? 0);

// ── CONVERSATION LIST ──
$convs = $conn->query("
    SELECT DISTINCT
        CASE WHEN m.sender_id=$user_id THEN m.receiver_id ELSE m.sender_id END AS partner_id,
        u.name AS partner_name, u.role AS partner_role, u.profile_pic AS partner_pic,
        (SELECT message FROM messages WHERE (sender_id=$user_id AND receiver_id=u.id) OR (sender_id=u.id AND receiver_id=$user_id) ORDER BY created_at DESC LIMIT 1) AS last_msg,
        (SELECT created_at FROM messages WHERE (sender_id=$user_id AND receiver_id=u.id) OR (sender_id=u.id AND receiver_id=$user_id) ORDER BY created_at DESC LIMIT 1) AS last_time,
        (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=$user_id AND is_read=0) AS unread
    FROM messages m
    JOIN users u ON u.id=CASE WHEN m.sender_id=$user_id THEN m.receiver_id ELSE m.sender_id END
    WHERE m.sender_id=$user_id OR m.receiver_id=$user_id
    ORDER BY last_time DESC
");

// ── MESSAGES WITH ACTIVE PARTNER ──
$messages = []; $partner = null;
if ($with_id > 0) {
    $partner = $conn->query("SELECT id,name,role,profile_pic FROM users WHERE id=$with_id")->fetch_assoc();
    $conn->query("UPDATE messages SET is_read=1 WHERE sender_id=$with_id AND receiver_id=$user_id");
    $msg_res = $conn->query("SELECT m.*,u.name AS sender_name FROM messages m JOIN users u ON u.id=m.sender_id WHERE (m.sender_id=$user_id AND m.receiver_id=$with_id) OR (m.sender_id=$with_id AND m.receiver_id=$user_id) ORDER BY m.created_at ASC");
    while ($r=$msg_res->fetch_assoc()) $messages[]=$r;
}
$conn->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Chat – SkillConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--border:#DDD6FE;--green:#16a34a;--sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,.07);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;}
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
.content{padding:20px 26px;flex:1;display:flex;flex-direction:column;}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}.sb-overlay.show{display:block;}

/* CHAT LAYOUT */
.chat-layout{display:grid;grid-template-columns:280px 1fr;height:calc(100vh - 100px);height:calc(100dvh - 100px);background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
.conv-list{border-right:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column;}
.conv-header{padding:16px;border-bottom:1px solid var(--border);font-family:var(--font-h);font-size:.95rem;font-weight:400;}
.conv-item{display:flex;gap:10px;align-items:center;padding:13px 16px;cursor:pointer;border-bottom:1px solid var(--border);text-decoration:none;transition:background .18s;}
.conv-item:hover,.conv-item.active{background:var(--cream2);}
.conv-item.active{border-left:3px solid var(--teal);}
.cv-ava{width:36px;height:36px;border-radius:50%;background:var(--orange);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;flex-shrink:0;overflow:hidden;}
.cv-ava img{width:36px;height:36px;object-fit:cover;}
.cv-name{font-size:.85rem;font-weight:400;color:var(--ink);}
.cv-last{font-size:.72rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;}
.cv-badge{margin-left:auto;background:var(--orange);color:#fff;font-size:.62rem;font-weight:400;padding:2px 6px;border-radius:100px;flex-shrink:0;}
.no-convs{padding:20px;text-align:center;color:var(--muted);font-size:.83rem;}

/* MESSAGE AREA */
.msg-area{display:flex;flex-direction:column;overflow:hidden;}
.msg-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.mh-ava{width:36px;height:36px;border-radius:50%;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;overflow:hidden;flex-shrink:0;}
.mh-ava img{width:36px;height:36px;object-fit:cover;}
.mh-name{font-family:var(--font-h);font-size:.95rem;font-weight:400;}
.mh-role{font-size:.72rem;color:var(--muted);}
.msg-body{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:10px;}
.bubble{max-width:62%;display:flex;flex-direction:column;}
.bubble.mine{align-self:flex-end;align-items:flex-end;}
.bubble.theirs{align-self:flex-start;}
.bubble-text{padding:10px 14px;border-radius:14px;font-size:.88rem;line-height:1.5;}
.mine .bubble-text{background:var(--teal);color:#fff;border-bottom-right-radius:3px;}
.theirs .bubble-text{background:var(--cream2);color:var(--ink);border-bottom-left-radius:3px;}
.bubble-time{font-size:.68rem;color:var(--muted);margin-top:3px;}
.msg-input{padding:12px 16px;padding-bottom:calc(12px + env(safe-area-inset-bottom));border-top:1px solid var(--border);display:flex;gap:10px;align-items:center;flex-shrink:0;background:var(--white);}
.msg-input textarea{flex:1;min-width:0;padding:9px 13px;border:1.5px solid var(--border);border-radius:10px;font-family:var(--font-b);font-size:.88rem;color:var(--ink);background:var(--cream);resize:none;outline:none;transition:border-color .2s;}
.msg-input textarea:focus{border-color:var(--teal);}
.send-btn{width:40px;height:40px;min-width:40px;border-radius:50%;background:var(--teal);color:#fff;border:none;cursor:pointer;font-size:1.1rem;transition:background .18s;flex-shrink:0;}
.send-btn:hover{background:#0b6b58;}
.no-chat{flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;color:var(--muted);}
.no-chat .ei{font-size:2.5rem;margin-bottom:10px;}
.no-chat h3{font-family:var(--font-h);font-size:1rem;color:var(--ink2);}

@media(max-width:768px){.sidebar{transform:translateX(-240px);}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.hamburger{display:flex;}.content{padding:12px;}.chat-layout{grid-template-columns:1fr;height:calc(100vh - 84px);height:calc(100dvh - 84px);}.conv-list{display:<?=$with_id>0?'none':'flex'?>;}.msg-area{display:<?=$with_id>0?'flex':'none'?>;}.chat-back{display:inline-flex;}.bubble{max-width:80%;}}
.chat-back{display:none;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:var(--cream2);color:var(--ink);text-decoration:none;font-size:1.1rem;margin-right:4px;flex-shrink:0;}

@media(max-width:480px){
  .content{padding:8px;}
  .msg-header{padding:10px 12px;}
  .msg-body{padding:12px;}
  .msg-input{padding:10px 12px;padding-bottom:calc(10px + env(safe-area-inset-bottom));gap:8px;}
  .bubble{max-width:88%;}
  .conv-header{padding:12px;}
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
body.dark-mode .conv-list{border-right:1px solid #1E293B;}
body.dark-mode .conv-header{border-bottom:1px solid #1E293B;}
body.dark-mode .conv-item{border-bottom:1px solid #1E293B;}
body.dark-mode .conv-item:hover, body.dark-mode .conv-item.active{background:#1E293B;}
body.dark-mode .cv-name{color:#E2E8F0;}
body.dark-mode .cv-last{color:#94A3B8;}
body.dark-mode .no-convs{color:#94A3B8;}
body.dark-mode .msg-header{border-bottom:1px solid #1E293B;}
body.dark-mode .mh-role{color:#94A3B8;}
body.dark-mode .theirs .bubble-text{background:#1E293B;color:#E2E8F0;}
body.dark-mode .bubble-time{color:#94A3B8;}
body.dark-mode .msg-input{border-top:1px solid #1E293B;}
body.dark-mode .msg-input textarea{border:1.5px solid #1E293B;color:#E2E8F0;background:#0B1220;}
body.dark-mode .no-chat{color:#94A3B8;}
body.dark-mode .no-chat h3{color:#CBD5E1;}
body.dark-mode .chat-layout{background:#111827;border:1.5px solid #1E293B;}
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
    <a href="chat.php" class="active"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if($unread_msgs>0): ?><span class="nav-bdg"><?=$unread_msgs?></span><?php endif;?></a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if($unread_notif>0): ?><span class="nav-bdg"><?=$unread_notif?></span><?php endif;?></a>
    <div class="nav-section">Account</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a><a href="ratings.php"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a><a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <div class="tb-left"><div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/chat.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;"><span class="tb-crumb">Dashboard / <strong>Chat</strong></span></div>
    <div class="tb-right"><a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a><a href="profile.php" class="tb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a></div>
  </div>
  <div class="content">
    <div class="chat-layout">

      <!-- CONVERSATION LIST -->
      <div class="conv-list">
        <div class="conv-header">💬 Messages</div>
        <?php if($convs&&$convs->num_rows>0): while($c=$convs->fetch_assoc()): ?>
          <a href="chat.php?with=<?=$c['partner_id']?>" class="conv-item <?=$with_id===(int)$c['partner_id']?'active':''?>">
            <div class="cv-ava" style="background:<?=$c['partner_role']==='worker'?'var(--orange)':'var(--teal)'?>;">
              <?php if(!empty($c['partner_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($c['partner_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($c['partner_name'],0,1))?><?php endif;?>
            </div>
            <div style="flex:1;min-width:0;">
              <div class="cv-name"><?=htmlspecialchars($c['partner_name'])?></div>
              <div class="cv-last"><?=htmlspecialchars($c['last_msg']??'')?></div>
            </div>
            <?php if((int)$c['unread']>0): ?><span class="cv-badge"><?=$c['unread']?></span><?php endif;?>
          </a>
        <?php endwhile; else: ?>
          <div class="no-convs">No conversations yet.<br>Message workers from Applicants or Hired pages.</div>
        <?php endif;?>
      </div>

      <!-- MESSAGE AREA -->
      <div class="msg-area">
        <?php if($partner): ?>
          <div class="msg-header">
            <a href="chat.php" class="chat-back">←</a>
            <div class="mh-ava" style="background:var(--orange);">
              <?php if(!empty($partner['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($partner['profile_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($partner['name'],0,1))?><?php endif;?>
            </div>
            <div>
              <div class="mh-name"><?=htmlspecialchars($partner['name'])?></div>
              <div class="mh-role"><?=ucfirst($partner['role'])?> · 🟢 Active</div>
            </div>
            <a href="applicant_profile.php?id=<?=$partner['id']?>" style="margin-left:auto;font-size:.78rem;font-weight:400;color:var(--teal);text-decoration:none;padding:6px 12px;background:var(--teal-lt);border-radius:100px;">View Profile</a>
          </div>
          <div class="msg-body" id="msg-body">
            <?php if(empty($messages)): ?>
              <div style="text-align:center;padding:30px;color:var(--muted);font-size:.88rem;">Say hello to <?=htmlspecialchars($partner['name'])?>! 👋</div>
            <?php else: foreach($messages as $msg): $is_mine=((int)$msg['sender_id']===$user_id); ?>
              <div class="bubble <?=$is_mine?'mine':'theirs'?>">
                <div class="bubble-text"><?=nl2br(htmlspecialchars($msg['message']))?></div>
                <div class="bubble-time"><?=date('h:i A',strtotime($msg['created_at']))?></div>
              </div>
            <?php endforeach; endif;?>
          </div>
          <form method="POST" class="msg-input">
            <input type="hidden" name="receiver_id" value="<?=$partner['id']?>">
            <textarea name="message" rows="2" placeholder="Type a message..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
            <button type="submit" name="send_msg" class="send-btn">➤</button>
          </form>
        <?php else: ?>
          <div class="no-chat"><div class="ei">💬</div><h3>Select a conversation</h3><p style="font-size:.82rem;margin-top:5px;">Choose from the list on the left.</p></div>
        <?php endif;?>
      </div>

    </div>
  </div>
</div>
<script>
const b=document.getElementById('msg-body');if(b)b.scrollTop=b.scrollHeight;
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

</body></html>