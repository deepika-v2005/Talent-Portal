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


// ── SEND MESSAGE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $message     = trim($conn->real_escape_string($_POST['message']));
    if (!empty($message) && $receiver_id > 0) {
        $conn->query("INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at)
                      VALUES ($user_id, $receiver_id, '$message', 0, NOW())");
    }
    header("Location: chat.php?with=" . $receiver_id); exit();
}

// ── ACTIVE CHAT PARTNER ──
$with_id = (int)($_GET['with'] ?? 0);

// ── FETCH CONVERSATION PARTNERS ──
$convs = $conn->query("
    SELECT DISTINCT
        CASE WHEN m.sender_id = $user_id THEN m.receiver_id ELSE m.sender_id END AS partner_id,
        u.name AS partner_name,
        u.role AS partner_role,
        (SELECT message FROM messages
         WHERE (sender_id=$user_id AND receiver_id=u.id)
            OR (sender_id=u.id AND receiver_id=$user_id)
         ORDER BY created_at DESC LIMIT 1) AS last_msg,
        (SELECT created_at FROM messages
         WHERE (sender_id=$user_id AND receiver_id=u.id)
            OR (sender_id=u.id AND receiver_id=$user_id)
         ORDER BY created_at DESC LIMIT 1) AS last_time,
        (SELECT COUNT(*) FROM messages
         WHERE sender_id=u.id AND receiver_id=$user_id AND is_read=0) AS unread
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id=$user_id THEN m.receiver_id ELSE m.sender_id END
    WHERE m.sender_id=$user_id OR m.receiver_id=$user_id
    ORDER BY last_time DESC
");

// ── FETCH MESSAGES WITH ACTIVE PARTNER ──
$messages = [];
$partner  = null;
if ($with_id > 0) {
    $partner = $conn->query("SELECT id, name, role FROM users WHERE id=$with_id")->fetch_assoc();
    // Mark as read
    $conn->query("UPDATE messages SET is_read=1 WHERE sender_id=$with_id AND receiver_id=$user_id");
    $msg_res = $conn->query("
        SELECT m.*, u.name AS sender_name
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE (m.sender_id=$user_id AND m.receiver_id=$with_id)
           OR (m.sender_id=$with_id AND m.receiver_id=$user_id)
        ORDER BY m.created_at ASC
    ");
    while ($r = $msg_res->fetch_assoc()) $messages[] = $r;
}

$conn->close();
$active_page = 'chat';
$my_initial  = strtoupper(substr($_SESSION['user_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Chat – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--orange2:#9F67FA;
      --teal:#7C3AED;--border:#DDD6FE;
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
      --border:#1E293B;
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
/* Chat layout */
    .chat-layout{
      display:grid;grid-template-columns:280px 1fr;
      height:calc(100vh - 62px);
      background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
      overflow:hidden;box-shadow:var(--shadow);
    }

    /* Conversations list */
    .conv-list{border-right:1px solid var(--border);overflow-y:auto;}
    .conv-header{
      padding:18px 16px;border-bottom:1px solid var(--border);
      font-family:var(--font-h);font-size:0.95rem;font-weight:400;color:var(--ink);
    }
    .conv-item{
      display:flex;gap:10px;align-items:center;
      padding:14px 16px;cursor:pointer;border-bottom:1px solid var(--border);
      text-decoration:none;transition:background 0.2s;
    }
    .conv-item:hover,.conv-item.active{background:var(--cream2);}
    .conv-item.active{border-left:3px solid var(--orange);}
    .conv-avatar{
      width:38px;height:38px;border-radius:50%;
      background:var(--orange);color:#fff;
      display:flex;align-items:center;justify-content:center;
      font-family:var(--font-h);font-size:0.95rem;font-weight:900;flex-shrink:0;
    }
    .conv-name{font-size:0.87rem;font-weight:700;color:var(--ink);line-height:1.3;}
    .conv-last{font-size:0.75rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;}
    .conv-badge{
      background:var(--orange);color:#fff;font-size:0.65rem;font-weight:800;
      padding:2px 7px;border-radius:100px;flex-shrink:0;
    }

    /* Message area */
    .msg-area{display:flex;flex-direction:column;}
    .msg-header{
      padding:14px 20px;border-bottom:1px solid var(--border);
      display:flex;align-items:center;gap:10px;
    }
    .msg-partner-name{font-family:var(--font-h);font-size:1rem;font-weight:900;color:var(--ink);}
    .msg-partner-role{font-size:0.75rem;color:var(--muted);font-weight:600;}

    .msg-body{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;}

    .msg-bubble{max-width:65%;display:flex;flex-direction:column;}
    .msg-bubble.mine{align-self:flex-end;align-items:flex-end;}
    .msg-bubble.theirs{align-self:flex-start;}

    .bubble-text{
      padding:10px 14px;border-radius:16px;
      font-size:0.9rem;line-height:1.5;
    }
    .mine .bubble-text{background:var(--orange);color:#fff;border-bottom-right-radius:4px;}
    .theirs .bubble-text{background:var(--cream2);color:var(--ink);border-bottom-left-radius:4px;}

    .bubble-time{font-size:0.7rem;color:var(--muted);margin-top:4px;font-weight:600;}

    /* Input area */
    .msg-input-area{
      padding:14px 20px;border-top:1px solid var(--border);
      display:flex;gap:10px;align-items:center;
    }
    .msg-input-area textarea{
      flex:1;padding:10px 14px;border:1.5px solid var(--border);border-radius:12px;
      font-family:var(--font-b);font-size:0.9rem;color:var(--ink);
      background:var(--cream);resize:none;outline:none;
      transition:border-color 0.2s;
    }
    .msg-input-area textarea:focus{border-color:var(--orange);}
    .send-btn{
      width:42px;height:42px;border-radius:50%;
      background:var(--orange);color:#fff;border:none;cursor:pointer;
      font-size:1.1rem;transition:background 0.2s;flex-shrink:0;
    }
    .send-btn:hover{background:#d4540a;}

    .no-chat{
      flex:1;display:flex;align-items:center;justify-content:center;
      flex-direction:column;color:var(--muted);
    }
    .no-chat .ei{font-size:3rem;margin-bottom:12px;}
    .no-chat h3{font-family:var(--font-h);font-size:1.1rem;color:var(--ink2);}
  
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
      .page-content{padding:0;}
      .chat-layout{grid-template-columns:1fr;height:calc(100vh - 62px);border-radius:0;border:none;}
      .conv-list{display:block;border-right:none;}
      .msg-area{display:none;}
      .chat-layout.has-chat .conv-list{display:none;}
      .chat-layout.has-chat .msg-area{display:flex;}
      .chat-back{display:inline-flex;}
    }
    .chat-back{
      display:none;align-items:center;justify-content:center;
      width:32px;height:32px;border-radius:8px;background:var(--cream2);
      color:var(--ink);text-decoration:none;font-size:1.1rem;margin-right:6px;flex-shrink:0;
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
    <a href="portfolio.php" ><span class="nav-ic"><img src="../assets/icons/total count.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Portfolio</a>
    <a href="chat.php" class="active">
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
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/chat.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Chat</strong></span></div>
    </div>
  </div>

  <div class="page-content" style="padding:20px 32px;">
    <div class="chat-layout<?= $partner ? ' has-chat' : '' ?>">

      <!-- Conversations List -->
      <div class="conv-list">
        <div class="conv-header">💬 Messages</div>
        <?php if ($convs && $convs->num_rows > 0): ?>
          <?php while ($c = $convs->fetch_assoc()): ?>
            <a href="chat.php?with=<?= $c['partner_id'] ?>"
               class="conv-item <?= $with_id === (int)$c['partner_id'] ? 'active' : '' ?>">
              <div class="conv-avatar" style="background:<?= $c['partner_role']==='client' ? 'var(--teal)' : 'var(--orange)' ?>;">
                <?= strtoupper(substr($c['partner_name'],0,1)) ?>
              </div>
              <div style="flex:1;min-width:0;">
                <div class="conv-name"><?= htmlspecialchars($c['partner_name']) ?></div>
                <div class="conv-last"><?= htmlspecialchars($c['last_msg'] ?? '') ?></div>
              </div>
              <?php if ((int)$c['unread'] > 0): ?>
                <span class="conv-badge"><?= $c['unread'] ?></span>
              <?php endif; ?>
            </a>
          <?php endwhile; ?>
        <?php else: ?>
          <div style="padding:24px 16px;text-align:center;color:var(--muted);font-size:0.85rem;">
            No conversations yet.<br>Apply to jobs to start chatting!
          </div>
        <?php endif; ?>
      </div>

      <!-- Message Area -->
      <div class="msg-area">
        <?php if ($partner): ?>

          <!-- Header -->
          <div class="msg-header">
            <a href="chat.php" class="chat-back">←</a>
            <div style="width:38px;height:38px;border-radius:50%;
                        background:var(--teal);color:#fff;
                        display:flex;align-items:center;justify-content:center;
                        font-family:var(--font-h);font-size:0.95rem;font-weight:900;">
              <?= strtoupper(substr($partner['name'],0,1)) ?>
            </div>
            <div>
              <div class="msg-partner-name"><?= htmlspecialchars($partner['name']) ?></div>
              <div class="msg-partner-role">
                <?= ucfirst($partner['role']) ?> · 🟢 Active
              </div>
            </div>
          </div>

          <!-- Messages -->
          <div class="msg-body" id="msg-body">
            <?php if (empty($messages)): ?>
              <div style="text-align:center;padding:30px;color:var(--muted);font-size:0.9rem;">
                Say hello to <?= htmlspecialchars($partner['name']) ?>! 👋
              </div>
            <?php else: ?>
              <?php foreach ($messages as $msg):
                $is_mine = ((int)$msg['sender_id'] === $user_id);
              ?>
                <div class="msg-bubble <?= $is_mine ? 'mine' : 'theirs' ?>">
                  <div class="bubble-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                  <div class="bubble-time"><?= date('h:i A', strtotime($msg['created_at'])) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Input -->
          <form method="POST" class="msg-input-area">
            <input type="hidden" name="receiver_id" value="<?= $partner['id'] ?>">
            <textarea name="message" rows="2"
                      placeholder="Type a message..."
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
            <button type="submit" name="send_msg" class="send-btn">➤</button>
          </form>

        <?php else: ?>
          <div class="no-chat">
            <div class="ei">💬</div>
            <h3>Select a conversation</h3>
            <p style="font-size:0.85rem;margin-top:6px;">Choose from your messages on the left.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
// Auto-scroll to bottom of messages
const msgBody = document.getElementById('msg-body');
if (msgBody) msgBody.scrollTop = msgBody.scrollHeight;
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
