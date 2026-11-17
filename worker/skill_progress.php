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


// ── SAVE SKILL PROGRESS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_skills'])) {
    foreach ($_POST['skill_name'] as $i => $sname) {
        $sname = $conn->real_escape_string(trim($sname));
        $spct  = min(100, max(0, (int)$_POST['skill_pct'][$i]));
        if (empty($sname)) continue;

        $sid = isset($_POST['skill_id'][$i]) ? (int)$_POST['skill_id'][$i] : 0;
        if ($sid > 0) {
            $conn->query("UPDATE worker_skills SET skill_name='$sname', progress=$spct WHERE id=$sid AND worker_id=$user_id");
        } else {
            $conn->query("INSERT INTO worker_skills (worker_id, skill_name, progress) VALUES ($user_id, '$sname', $spct)");
        }
    }
    header("Location: skill_progress.php?saved=1"); exit();
}

// ── DELETE SKILL ──
if (isset($_GET['del'])) {
    $sid = (int)$_GET['del'];
    $conn->query("DELETE FROM worker_skills WHERE id=$sid AND worker_id=$user_id");
    header("Location: skill_progress.php"); exit();
}

// ── FETCH SKILLS ──
$skills_res = $conn->query("SELECT * FROM worker_skills WHERE worker_id=$user_id ORDER BY id ASC");
$skills = [];
while ($r = $skills_res->fetch_assoc()) $skills[] = $r;
$conn->close();

$active_page = 'skills';

// Color palette for skill bars
$bar_colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706','#dc2626','#059669'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Skill Progress – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--orange2:#9F67FA;
      --teal:#7C3AED;--border:#DDD6FE;
      --green:#16a34a;--green-lt:#f0fdf4;
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
      --border:#1E293B;--green-lt:rgba(22,163,74,.14);
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
.skill-card{
      background:var(--white);border:1px solid var(--border);
      border-radius:var(--radius);padding:22px;margin-bottom:16px;
      box-shadow:var(--shadow);
    }
    .skill-name-row{
      display:flex;justify-content:space-between;align-items:center;
      margin-bottom:10px;
    }
    .skill-name{font-size:0.95rem;font-weight:700;color:var(--ink2);}
    .skill-pct{font-family:var(--font-h);font-size:1.2rem;font-weight:900;}
    .progress-bar-bg{
      height:12px;border-radius:100px;
      background:var(--cream2);overflow:hidden;
    }
    .progress-bar-fill{
      height:100%;border-radius:100px;
      width:0%; /* animated by JS */
      transition:width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .skill-level{font-size:0.75rem;font-weight:700;margin-top:6px;}

    /* Edit form */
    .edit-row{
      display:grid;grid-template-columns:1fr 100px auto;
      gap:10px;align-items:center;
      background:var(--cream2);border-radius:10px;padding:12px;margin-bottom:10px;
    }
    .edit-input{
      padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;
      font-family:var(--font-b);font-size:0.9rem;background:var(--white);
      color:var(--ink);outline:none;
    }
    .edit-input:focus{border-color:var(--orange);}
  
    /* ═══ SIDEBAR CSS ═══ */
    .sidebar{width:240px;height:100vh;background:#0B1F3A;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:900;color:#fff;text-decoration:none;}
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
      .page-content{padding:14px;}
    }
    @media(max-width:480px){
      .edit-row{grid-template-columns:1fr;}
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
    <a href="skill_progress.php" class="active"><span class="nav-ic"><img src="../assets/icons/skill program.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Skill Progress</a>
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
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/skill program.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Skill Progress</strong></span></div>
    </div>
    <div class="topbar-right">
      <button onclick="toggleEdit()"
              style="padding:8px 20px;background:var(--orange);color:#fff;border:none;
                     border-radius:100px;font-family:var(--font-b);font-size:0.85rem;font-weight:400;
                     cursor:pointer;" id="edit-btn">
        ✏️ Edit Skills
      </button>
    </div>
  </div>

  <div class="page-content" style="<?= empty($skills) ? '' : 'max-width:700px;' ?>">

    <?php if (isset($_GET['saved'])): ?>
      <div style="background:var(--green-lt);border:1px solid #86efac;color:var(--green);
                  padding:14px 20px;border-radius:var(--radius);margin-bottom:20px;font-weight:600;">
        ✅ Skills updated successfully!
      </div>
    <?php endif; ?>

    <!-- ── VIEW MODE ── -->
    <div id="view-mode">
      <?php if (empty($skills)): ?>
        <div style="min-height:calc(100vh - 140px);display:flex;align-items:center;justify-content:center;">
          <div style="text-align:center;padding:50px 20px;color:var(--muted);width:400px;max-width:90%;">
            <div style="font-size:2.5rem;margin-bottom:12px;">📊</div>
            <h3 style="font-family:var(--font-h);color:var(--ink2);margin-bottom:8px;">No skills added yet</h3>
            <p>Click "Edit Skills" to add your skills and track your progress.</p>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($skills as $i => $skill):
          $color = $bar_colors[$i % count($bar_colors)];
          $pct   = (int)$skill['progress'];
          $level = $pct >= 80 ? 'Expert' : ($pct >= 60 ? 'Advanced' : ($pct >= 40 ? 'Intermediate' : 'Beginner'));
        ?>
          <div class="skill-card">
            <div class="skill-name-row">
              <div class="skill-name"><?= htmlspecialchars($skill['skill_name']) ?></div>
              <div class="skill-pct" style="color:<?= $color ?>;"><?= $pct ?>%</div>
            </div>
            <div class="progress-bar-bg">
              <div class="progress-bar-fill" data-pct="<?= $pct ?>" style="background:<?= $color ?>;"></div>
            </div>
            <div class="skill-level" style="color:<?= $color ?>;"><?= $level ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ── EDIT MODE ── -->
    <div id="edit-mode" style="display:none;">
      <form method="POST">
        <div id="skills-form-container">
          <?php foreach ($skills as $i => $skill): ?>
            <div class="edit-row">
              <input type="hidden" name="skill_id[]" value="<?= $skill['id'] ?>">
              <input type="text" name="skill_name[]" class="edit-input"
                     value="<?= htmlspecialchars($skill['skill_name']) ?>"
                     placeholder="Skill name" required>
              <input type="number" name="skill_pct[]" class="edit-input"
                     value="<?= $skill['progress'] ?>"
                     min="0" max="100" placeholder="%">
              <a href="?del=<?= $skill['id'] ?>"
                 onclick="return confirm('Remove this skill?')"
                 style="width:36px;height:36px;border-radius:50%;
                        background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;
                        display:flex;align-items:center;justify-content:center;
                        text-decoration:none;font-size:1rem;">🗑</a>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Add new skill -->
        <div id="new-skills-container"></div>

        <button type="button" onclick="addSkill()"
                style="padding:8px 20px;background:var(--cream2);color:var(--ink2);
                       border:1.5px dashed var(--border);border-radius:100px;
                       font-family:var(--font-b);font-size:0.85rem;font-weight:700;
                       cursor:pointer;margin-bottom:20px;transition:background 0.2s;"
                onmouseenter="this.style.background='var(--border)'"
                onmouseleave="this.style.background='var(--cream2)'">
          + Add Skill
        </button>

        <div style="display:flex;gap:10px;">
          <button type="submit" name="save_skills"
                  style="padding:11px 28px;background:var(--orange);color:#fff;
                         font-family:var(--font-b);font-size:0.9rem;font-weight:700;
                         border:none;border-radius:100px;cursor:pointer;
                         box-shadow:0 4px 14px rgba(232,96,10,0.3);">
            💾 Save Skills
          </button>
          <button type="button" onclick="toggleEdit()"
                  style="padding:11px 28px;background:var(--cream2);color:var(--ink2);
                         border:1px solid var(--border);border-radius:100px;
                         font-family:var(--font-b);font-size:0.9rem;font-weight:700;cursor:pointer;">
            Cancel
          </button>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
// Animate progress bars on load
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    document.querySelectorAll('.progress-bar-fill').forEach(bar => {
      bar.style.width = bar.dataset.pct + '%';
    });
  }, 200);
});

// Toggle edit/view mode
function toggleEdit() {
  const view = document.getElementById('view-mode');
  const edit = document.getElementById('edit-mode');
  const btn  = document.getElementById('edit-btn');
  if (edit.style.display === 'none') {
    view.style.display = 'none';
    edit.style.display = 'block';
    btn.textContent = '👁 View Mode';
  } else {
    view.style.display = 'block';
    edit.style.display = 'none';
    btn.textContent = '✏️ Edit Skills';
  }
}

// Add new skill row dynamically
let newCount = 0;
function addSkill() {
  newCount++;
  const container = document.getElementById('new-skills-container');
  const div = document.createElement('div');
  div.className = 'edit-row';
  div.innerHTML = `
    <input type="hidden" name="skill_id[]" value="0">
    <input type="text" name="skill_name[]" class="edit-input"
           placeholder="Skill name (e.g. Photography)" required>
    <input type="number" name="skill_pct[]" class="edit-input"
           value="50" min="0" max="100" placeholder="%">
    <button type="button" onclick="this.parentElement.remove()"
            style="width:36px;height:36px;border-radius:50%;
                   background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;
                   cursor:pointer;font-size:1rem;">🗑</button>
  `;
  container.appendChild(div);
}
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