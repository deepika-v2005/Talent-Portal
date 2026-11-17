<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); exit();
}

$conn = new mysqli("localhost", "root", "", "skillconnect");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── SIDEBAR VARS ──
$profile      = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending_apps = (int)$conn->query("SELECT COUNT(*) as c FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.client_id=$user_id AND a.status='pending'")->fetch_assoc()['c'];
$unread_notif = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs  = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];

$avatar_letter = strtoupper(substr($user_name, 0, 1));
$first_name    = explode(' ', $user_name)[0];

$success = '';
$error   = '';

// ── HANDLE JOB POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_job'])) {
    $title       = trim($conn->real_escape_string($_POST['title'] ?? ''));
    $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
    $skills      = trim($conn->real_escape_string($_POST['skills_required'] ?? ''));
    $budget_raw  = trim($_POST['budget'] ?? '');
    $job_type    = in_array($_POST['job_type'] ?? '', ['Full-time','Part-time','Freelance','Contract']) ? $_POST['job_type'] : 'Full-time';
    $timing      = in_array($_POST['timing'] ?? '', ['Morning','Afternoon','Evening','Night','Full Day','Flexible']) ? $_POST['timing'] : 'Flexible';

    // ── BUDGET VALIDATION ──
    $budget     = null;
    $MAX_BUDGET = 10000000; // ₹1 crore ceiling — adjust if needed
    if ($budget_raw !== '') {
        if (!is_numeric($budget_raw)) {
            $error = 'Budget must be a valid number.';
        } elseif ((float)$budget_raw <= 0) {
            $error = 'Budget must be greater than ₹0.';
        } elseif ((float)$budget_raw > $MAX_BUDGET) {
            $error = 'Budget seems unrealistically high. Please enter a value under ₹' . number_format($MAX_BUDGET) . '.';
        } else {
            $budget = round((float)$budget_raw, 2);
        }
    }

    if (empty($title) || empty($description)) {
        $error = 'Event Name and Event Description are required.';
    } elseif ($error) {
        // budget error already set above — fall through to re-render the form with $error shown
    } else {
        $budget_val = $budget ? "'$budget'" : "NULL";
        $result = $conn->query("INSERT INTO jobs (client_id, title, description, skills_required, budget, job_type, timing, status, created_at)
                      VALUES ($user_id, '$title', '$description', '$skills', $budget_val, '$job_type', '$timing', 'open', NOW())");
        if ($result) {
            $new_job_id = $conn->insert_id;
            // Notify all workers about new job
            $workers = $conn->query("SELECT id FROM users WHERE role='worker'");
            if ($workers) {
                while ($w = $workers->fetch_assoc()) {
                    $notif_msg = $conn->real_escape_string("New service request posted: $title");
                    $conn->query("INSERT INTO notifications (user_id, type, message, is_read, created_at)
                                  VALUES ({$w['id']}, 'job_alert', '$notif_msg', 0, NOW())");
                }
            }
            $conn->close();
            header("Location: manage_jobs.php?posted=1");
            exit();
        } else {
            $error = 'Failed to post service request. Please try again. Error: ' . $conn->error;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Post a Service Request – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;
      --border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;
      --red:#dc2626;--red-lt:#fef2f2;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --shadow-md:0 6px 24px rgba(11,31,58,0.10);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;overflow-x:hidden;}

    /* SIDEBAR */
    .sidebar{width:var(--sidebar);height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}
    .sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}
    .sb-ava{width:40px;height:40px;border-radius:11px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:400;overflow:hidden;flex-shrink:0;}
    .sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
    .sb-uname{font-size:.86rem;font-weight:400;color:#fff;}
    .sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
    .sb-nav{flex:1;padding:10px 0;}
    .nav-section{font-size:.62rem;font-weight:400;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:400;padding:2px 6px;border-radius:100px;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}
    .sb-logout a{display:flex;align-items:center;justify-content:center;gap:9px;padding:10px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}
    .sb-logout a:hover{background:rgba(220,38,38,.2);}

    /* MAIN */
    .main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}
    .topbar{height:60px;padding:0 26px;background:rgba(245,243,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;}
    .tb-crumb{font-size:.85rem;color:var(--muted);}
    .tb-crumb strong{color:var(--ink);font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;}
    .tb-btn:hover{background:var(--cream2);}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:400;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--cream);padding:0 2px;}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;text-decoration:none;overflow:hidden;}
    .tb-ava img{width:35px;height:35px;object-fit:cover;}
    .content{padding:28px 26px;flex:1;}

    /* FORM */
    .form-wrap{max-width:740px;}
    .page-header{margin-bottom:24px;}
    .page-title{font-family:var(--font-h);font-size:1.6rem;font-weight:400;color:var(--ink);margin-bottom:4px;}
    .page-sub{font-size:.88rem;color:var(--muted);}

    .form-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:28px;margin-bottom:20px;box-shadow:var(--shadow);}
    .section-label{font-size:.72rem;font-weight:400;letter-spacing:1.5px;text-transform:uppercase;color:var(--teal);margin-bottom:16px;display:flex;align-items:center;gap:7px;}
    .section-label::after{content:'';flex:1;height:1px;background:var(--teal-lt);}

    .form-group{margin-bottom:18px;}
    .form-group:last-child{margin-bottom:0;}
    .fg-label{font-size:.82rem;font-weight:400;color:var(--ink2);margin-bottom:7px;display:block;}
    .fg-hint{font-size:.72rem;color:var(--muted);font-weight:400;margin-left:4px;}
    .fg-req{color:var(--orange);}

    .fg-input,.fg-textarea,.fg-select{
      width:100%;padding:11px 14px;
      border:1.5px solid var(--border);border-radius:10px;
      background:var(--cream);color:var(--ink);
      font-family:var(--font-b);font-size:.92rem;font-weight:400;
      outline:none;transition:border-color .2s,box-shadow .2s,background .2s;
    }
    .fg-input:focus,.fg-textarea:focus,.fg-select:focus{
      border-color:var(--teal);background:var(--white);
      box-shadow:0 0 0 3px rgba(124,58,237,.1);
    }
    .fg-textarea{resize:vertical;min-height:130px;}
    .fg-select{cursor:pointer;}
    .fg-input::placeholder,.fg-textarea::placeholder{color:#b5a89a;font-weight:400;}

    /* job type radio */
    .job-type-group{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
    .jt-opt{display:none;}
    .jt-label{display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 8px;border:1.5px solid var(--border);border-radius:11px;cursor:pointer;transition:all .2s;background:var(--cream);text-align:center;}
    .jt-label:hover{border-color:var(--teal);background:rgba(124,58,237,.04);}
    .jt-icon{font-size:1.4rem;}
    .jt-text{font-size:.77rem;font-weight:400;color:var(--ink2);}
    .jt-opt:checked + .jt-label{border-color:var(--teal);background:rgba(124,58,237,.08);box-shadow:0 0 0 3px rgba(124,58,237,.1);}
    .jt-opt:checked + .jt-label .jt-text{color:var(--teal);}

    /* skill tags */
    .skill-preview{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
    .skill-tag{background:var(--teal-lt);color:var(--teal);font-size:.74rem;font-weight:400;padding:3px 10px;border-radius:100px;}

    /* submit */
    .btn-post{
      padding:13px 36px;background:var(--teal);color:#fff;
      font-family:var(--font-b);font-size:1rem;font-weight:400;
      border:none;border-radius:100px;cursor:pointer;
      box-shadow:0 5px 18px rgba(124,58,237,.3);
      transition:background .2s,transform .15s;
      display:inline-flex;align-items:center;gap:8px;
    }
    .btn-post:hover{background:#0b6b58;transform:translateY(-1px);}
    .btn-cancel{
      padding:13px 24px;background:transparent;color:var(--ink2);
      font-family:var(--font-b);font-size:.95rem;font-weight:400;
      border:1.5px solid var(--border);border-radius:100px;cursor:pointer;
      text-decoration:none;transition:background .2s;
    }
    .btn-cancel:hover{background:var(--cream2);}

    /* alert */
    .alert{border-radius:10px;padding:13px 16px;font-size:.87rem;line-height:1.5;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;}
    .alert.success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
    .alert.error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
    .alert a{color:inherit;font-weight:400;}

    /* tips box */
    .tips-box{background:linear-gradient(135deg,var(--teal-lt),rgba(124,58,237,.03));border:1.5px solid rgba(124,58,237,.2);border-radius:var(--radius);padding:20px;}
    .tips-title{font-family:var(--font-h);font-size:.95rem;font-weight:400;color:var(--teal);margin-bottom:12px;}
    .tip-item{display:flex;gap:9px;margin-bottom:9px;font-size:.82rem;color:var(--ink2);line-height:1.5;}
    .tip-item:last-child{margin-bottom:0;}
    .tip-dot{width:6px;height:6px;border-radius:50%;background:var(--teal);flex-shrink:0;margin-top:7px;}

    /* overlay */
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}

    .spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none;}
    @keyframes spin{to{transform:rotate(360deg);}}
    .post-layout{display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;}

    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main{margin-left:0;}
      .content{padding:16px;}
      .hamburger{display:flex;}
      .job-type-group{grid-template-columns:repeat(2,1fr);}
      .post-layout{grid-template-columns:1fr;}
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
body.dark-mode .page-title{color:#E2E8F0;}
body.dark-mode .page-sub{color:#94A3B8;}
body.dark-mode .form-card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .section-label::after{background:rgba(124,58,237,.18);}
body.dark-mode .fg-label{color:#CBD5E1;}
body.dark-mode .fg-hint{color:#94A3B8;}
body.dark-mode .fg-input, body.dark-mode .fg-textarea, body.dark-mode .fg-select{border:1.5px solid #1E293B;background:#0B1220;color:#E2E8F0;}
body.dark-mode .fg-input:focus, body.dark-mode .fg-textarea:focus, body.dark-mode .fg-select:focus{background:#111827;}
body.dark-mode .jt-label{border:1.5px solid #1E293B;background:#0B1220;}
body.dark-mode .jt-text{color:#CBD5E1;}
body.dark-mode .skill-tag{background:rgba(124,58,237,.18);}
body.dark-mode .btn-cancel{color:#CBD5E1;border:1.5px solid #1E293B;}
body.dark-mode .btn-cancel:hover{background:#1E293B;}
body.dark-mode .alert.success{background:rgba(22,163,74,.14);}
body.dark-mode .alert.error{background:rgba(220,38,38,.14);}
body.dark-mode .tip-item{color:#CBD5E1;}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>
<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>


<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sb-logo"><a href="../index.php">Skill<span>Connect</span></a></div>
  <div class="sb-user">
    <div class="sb-ava">
      <?php if (!empty($profile['profile_pic'])): ?>
        <img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt="">
      <?php else: ?><?= $avatar_letter ?><?php endif; ?>
    </div>
    <div><div class="sb-uname"><?= htmlspecialchars($first_name) ?></div><div class="sb-urole">Client</div></div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="post_job.php" class="active"><span class="nav-ic"><img src="../assets/icons/request.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Service Request</a>
    <a href="manage_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Jobs</a>
    <a href="applicants.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants<?php if ($pending_apps > 0): ?><span class="nav-bdg"><?= $pending_apps ?></span><?php endif; ?></a>
    
    <a href="hired.php"><span class="nav-ic"><img src="../assets/icons/job accepted.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Hired</a>
    <div class="nav-section">Connect</div>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if ($unread_msgs > 0): ?><span class="nav-bdg"><?= $unread_msgs ?></span><?php endif; ?></a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if ($unread_notif > 0): ?><span class="nav-bdg"><?= $unread_notif ?></span><?php endif; ?></a>
    <div class="nav-section">Account</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a>
    <a href="ratings.php"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/request.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb">Dashboard / <strong>Service Request</strong></span>
    </div>
    <div class="tb-right">
      <a href="chat.php" class="tb-btn"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if ($unread_msgs > 0): ?><span class="tb-dot"><?= $unread_msgs ?></span><?php endif; ?></a>
      <a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if ($unread_notif > 0): ?><span class="tb-dot"><?= $unread_notif ?></span><?php endif; ?></a>
      <a href="profile.php" class="tb-ava"><?php if (!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt=""><?php else: ?><?= $avatar_letter ?><?php endif; ?></a>
    </div>
  </div>

  <div class="content">
    <div class="form-wrap">

      <div class="page-header">
        <div class="page-title">➕ New Service Request</div>
        <div class="page-sub">Fill in the details below to attract the right skilled workers.</div>
      </div>

      <?php if ($success): ?>
        <div class="alert success">✅ <span><?= $success ?></span></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert error">⚠️ <span><?= $error ?></span></div>
      <?php endif; ?>

      <div class="post-layout">

        <!-- FORM -->
        <form method="POST" id="jobForm">

          <!-- Basic Info -->
          <div class="form-card">
            <div class="section-label">📋 Event Details</div>

            <div class="form-group">
              <label class="fg-label">Event Name <span class="fg-req">*</span></label>
              <input type="text" name="title" class="fg-input"
                     placeholder="e.g. Wedding Photographer, Mehndi Artist, Graphic Designer..."
                     value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label class="fg-label">Event Description <span class="fg-req">*</span></label>
              <textarea name="description" class="fg-textarea"
                        placeholder="Describe the job in detail. Include what's expected, when, where, and any special requirements..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label class="fg-label">
                Skills Required
                <span class="fg-hint">(comma separated)</span>
              </label>
              <input type="text" name="skills_required" id="skillsInput" class="fg-input"
                     placeholder="e.g. Photography, Editing, Lighting, Mehendi..."
                     value="<?= htmlspecialchars($_POST['skills_required'] ?? '') ?>">
              <div class="skill-preview" id="skillPreview"></div>
            </div>
          </div>

          <!-- Job Type & Budget -->
          <div class="form-card">
            <div class="section-label">💰 Job Type & Budget</div>

            <div class="form-group">
              <label class="fg-label">Job Type</label>
              <div class="job-type-group">
                <?php
                  $types = [
                    ['Full-time','🕐','Full-time'],
                    ['Part-time','⏱️','Part-time'],
                    ['Freelance','🎯','Freelance'],
                    ['Contract','📄','Contract'],
                  ];
                  $selected_type = $_POST['job_type'] ?? 'Freelance';
                  foreach ($types as [$val, $icon, $lbl]):
                ?>
                  <input type="radio" name="job_type" id="jt_<?= $val ?>"
                         value="<?= $val ?>" class="jt-opt"
                         <?= $selected_type === $val ? 'checked' : '' ?>>
                  <label for="jt_<?= $val ?>" class="jt-label">
                    <span class="jt-icon"><?= $icon ?></span>
                    <span class="jt-text"><?= $lbl ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="form-group" style="margin-top:18px;">
              <label class="fg-label">
                Timing <span class="fg-req">*</span>
                <span class="fg-hint">(preferred time for the work)</span>
              </label>
              <select name="timing" class="fg-select" required>
                <?php
                  $timing_opts   = ['Morning','Afternoon','Evening','Night','Full Day','Flexible'];
                  $selected_time = $_POST['timing'] ?? 'Flexible';
                  foreach ($timing_opts as $t):
                ?>
                  <option value="<?= $t ?>" <?= $selected_time === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
              <div style="font-size:.72rem;color:var(--muted);margin-top:5px;">
                Let workers know when this job needs to happen — helps them plan around it.
              </div>
            </div>

            <div class="form-group" style="margin-top:18px;">
              <label class="fg-label">
                Budget / Pay
                <span class="fg-hint">(optional — in ₹)</span>
              </label>
              <div style="position:relative;">
                <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);font-weight:400;color:var(--muted);">₹</span>
                <input type="number" name="budget" class="fg-input"
                       style="padding-left:30px;"
                       placeholder="e.g. 5000"
                       min="1" max="10000000" step="0.01"
                       value="<?= htmlspecialchars($_POST['budget'] ?? '') ?>">
              </div>
              <div style="font-size:.72rem;color:var(--muted);margin-top:5px;">
                Enter total budget or per-hour rate. Workers will see this.
              </div>
            </div>
          </div>

          <!-- Submit -->
          <div style="display:flex;gap:12px;align-items:center;">
            <button type="submit" name="post_job" class="btn-post" id="submitBtn">
              <span id="btnTxt">🚀 Post Service Request</span>
              <span class="spinner" id="spinner"></span>
            </button>
            <a href="dashboard.php" class="btn-cancel">Cancel</a>
          </div>

        </form>

        <!-- TIPS -->
        <div class="tips-box">
          <div class="tips-title">💡 Tips for a great service request</div>
          <div class="tip-item"><div class="tip-dot"></div>Use a clear, specific event name — "Bridal Mehndi Artist" beats "Mehndi"</div>
          <div class="tip-item"><div class="tip-dot"></div>Add all relevant skills so the right workers find your post</div>
          <div class="tip-item"><div class="tip-dot"></div>Mention the date, location, and duration in the description</div>
          <div class="tip-item"><div class="tip-dot"></div>Set a realistic budget — it attracts more applications</div>
          <div class="tip-item"><div class="tip-dot"></div>Be specific about what output you expect</div>
        </div>

      </div><!-- /grid -->
    </div>
  </div>
</div>


</body>
</html>