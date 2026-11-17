<?php
session_start();

// ── SESSION GUARD ──
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php");
    exit();
}

// ── DB ──
$conn = new mysqli("localhost", "root", "", "skillconnect");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── FETCH CLIENT PROFILE ──
$profile = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

// ── STATS ──
$total_jobs   = $conn->query("SELECT COUNT(*) as c FROM jobs WHERE client_id=$user_id")->fetch_assoc()['c'];
$open_jobs    = $conn->query("SELECT COUNT(*) as c FROM jobs WHERE client_id=$user_id AND status='open'")->fetch_assoc()['c'];
$closed_jobs  = $conn->query("SELECT COUNT(*) as c FROM jobs WHERE client_id=$user_id AND status='closed'")->fetch_assoc()['c'];

$total_apps   = $conn->query("
    SELECT COUNT(*) as c FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.client_id = $user_id
")->fetch_assoc()['c'];

$pending_apps = $conn->query("
    SELECT COUNT(*) as c FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.client_id = $user_id AND a.status = 'pending'
")->fetch_assoc()['c'];

$hired        = $conn->query("
    SELECT COUNT(*) as c FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.client_id = $user_id AND a.status = 'accepted'
")->fetch_assoc()['c'];

$connections  = $conn->query("
    SELECT COUNT(*) as c FROM connections
    WHERE client_id=$user_id AND status='accepted'
")->fetch_assoc()['c'];

// ── UNREAD ──
$unread_notif = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs  = $conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];

// ── MY RECENT JOBS (with applicant count) ──
$my_jobs = $conn->query("
    SELECT j.*,
        (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS app_count,
        (SELECT COUNT(*) FROM applications WHERE job_id=j.id AND status='pending') AS pending_count
    FROM jobs j
    WHERE j.client_id = $user_id
    ORDER BY j.created_at DESC
    LIMIT 5
");

// ── RECENT APPLICANTS ──
$recent_apps = $conn->query("
    SELECT a.*, a.status,
           j.title AS job_title,
           u.name AS worker_name,
           u.location AS worker_loc,
           u.skills AS worker_skills,
           u.profile_pic AS worker_pic,
           u.id AS worker_id
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.worker_id = u.id
    WHERE j.client_id = $user_id
    ORDER BY a.applied_at DESC
    LIMIT 6
");

// ── RECENT NOTIFICATIONS ──
$notifs = $conn->query("
    SELECT * FROM notifications
    WHERE user_id=$user_id
    ORDER BY created_at DESC LIMIT 5
");

// ── HANDLE APPLICATION STATUS CHANGE (quick action) ──
if (isset($_GET['status_change'])) {
    $app_id    = (int)$_GET['app_id'];
    $new_status = $conn->real_escape_string($_GET['status_change']);
    $allowed   = ['pending','accepted','rejected','interview scheduled'];
    if (in_array($new_status, $allowed)) {
        $conn->query("UPDATE applications SET status='$new_status' WHERE id=$app_id");
        // Notify the worker
        $app_info = $conn->query("
            SELECT a.worker_id, j.title, u.name as cname
            FROM applications a
            JOIN jobs j ON a.job_id=j.id
            JOIN users u ON u.id=$user_id
            WHERE a.id=$app_id
        ")->fetch_assoc();
        if ($app_info) {
            $msg = $conn->real_escape_string("Your application for \"{$app_info['title']}\" has been updated to: $new_status");
            $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at)
                          VALUES ({$app_info['worker_id']},'app_update','$msg',0,NOW())");
        }
    }
    header("Location: dashboard.php"); exit();
}

$conn->close();

// ── HELPERS ──
$avatar_letter = strtoupper(substr($user_name, 0, 1));
$first_name    = explode(' ', $user_name)[0];

$job_colors = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706','#dc2626','#059669'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Client Dashboard – SkillConnect</title>
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
      --red:#dc2626;--red-lt:#fef2f2;
      --green:#16a34a;--green-lt:#f0fdf4;
      --yellow:#d97706;--yellow-lt:#fef3c7;
      --purple:#7c3aed;--purple-lt:#f3e8ff;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --shadow-md:0 6px 24px rgba(11,31,58,0.10);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    html{scroll-behavior:smooth;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;overflow-x:hidden;}

    /* ═══════════════════════════════
       SIDEBAR
    ═══════════════════════════════ */
    .sidebar{
      width:var(--sidebar);height:100vh;
      background:var(--ink);
      display:flex;flex-direction:column;
      position:fixed;top:0;left:0;z-index:200;
      overflow-y:auto;transition:transform .28s ease;
    }
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}
    .sb-logo span{color:var(--orange2);}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;flex-shrink:0;}
    .sb-ava{width:40px;height:40px;border-radius:11px;flex-shrink:0;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:400;overflow:hidden;}
    .sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
    .sb-uname{font-size:.86rem;font-weight:400;color:#fff;line-height:1.3;}
    .sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);margin-top:1px;}
    .sb-nav{flex:1;padding:10px 0;overflow-y:auto;}
    .nav-section{font-size:.62rem;font-weight:400;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;position:relative;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--teal);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{margin-left:auto;background:var(--orange);color:#fff;font-size:.6rem;font-weight:400;padding:2px 6px;border-radius:100px;min-width:17px;text-align:center;flex-shrink:0;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logout a{display:flex;align-items:center;justify-content:center;gap:9px;padding:10px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;transition:background .2s;}.sb-logout a img{filter:brightness(0) invert(1);}
    .sb-logout a:hover{background:rgba(220,38,38,.2);}

    /* ═══════════════════════════════
       MAIN
    ═══════════════════════════════ */
    .main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh;}

    /* topbar */
    .topbar{height:60px;padding:0 26px;background:#000;backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:#1a1a1a;border:1px solid #2a2a2a;align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;color:#fff;}
    .tb-crumb{font-size:.85rem;color:#a1a1aa;}
    .tb-crumb strong{color:#fff;font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:#1a1a1a;border:1px solid #2a2a2a;display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;}
    .tb-btn:hover{background:#2a2a2a;}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:var(--orange);color:#fff;font-size:.56rem;font-weight:400;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid #000;padding:0 2px;}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;text-decoration:none;overflow:hidden;}
    .tb-ava img{width:35px;height:35px;object-fit:cover;}

    /* content */
    .content{padding:24px 26px;flex:1;}

    /* ═══════════════════════════════
       WELCOME BANNER
    ═══════════════════════════════ */
    .welcome{
      background:linear-gradient(135deg,var(--teal) 0%,#0fa880 100%);
      border-radius:18px;padding:24px 28px;
      display:flex;align-items:center;justify-content:space-between;
      margin-bottom:22px;overflow:hidden;position:relative;
    }
    .welcome::before{content:'';position:absolute;top:-55px;right:-55px;width:190px;height:190px;background:rgba(255,255,255,.08);border-radius:50%;}
    .welcome::after{content:'';position:absolute;bottom:-30px;right:130px;width:120px;height:120px;background:rgba(255,255,255,.05);border-radius:50%;}
    .wl{position:relative;z-index:1;}
    .wl-hi{font-size:.77rem;color:rgba(255,255,255,.72);font-weight:400;margin-bottom:3px;letter-spacing:.4px;}
    .wl-name{font-family:var(--font-h);font-size:1.75rem;font-weight:400;color:#fff;line-height:1.1;margin-bottom:7px;}
    .wl-sub{font-size:.85rem;color:rgba(255,255,255,.82);max-width:380px;line-height:1.55;}
    .wl-sub strong{color:#fff;}
    .wl-btns{display:flex;gap:9px;margin-top:16px;}
    .wl-btn{padding:8px 18px;border-radius:100px;font-family:var(--font-b);font-size:.81rem;font-weight:400;text-decoration:none;transition:all .2s;}
    .wl-btn-w{background:#fff;color:var(--teal);}
    .wl-btn-w:hover{background:rgba(255,255,255,.9);}
    .wl-btn-o{background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.38);}
    .wl-btn-o:hover{background:rgba(255,255,255,.22);}
    .wr{position:relative;z-index:1;text-align:center;}
    .wr-ava{width:70px;height:70px;border-radius:16px;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.32);display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.9rem;font-weight:400;color:#fff;overflow:hidden;}
    .wr-ava img{width:70px;height:70px;object-fit:cover;}
    .wr-lbl{font-size:.72rem;color:rgba(255,255,255,.75);margin-top:6px;font-weight:400;}

    /* ═══════════════════════════════
       STATS ROW
    ═══════════════════════════════ */
    .stats{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:22px;}
    .sc{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:15px;text-decoration:none;display:block;transition:border-color .2s,box-shadow .2s;}
    .sc:hover{border-color:var(--teal);box-shadow:var(--shadow-md);}
    .sc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
    .sc-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;}
    .sc-badge{font-size:.65rem;font-weight:400;padding:2px 7px;border-radius:100px;}
    .sc-num{font-family:var(--font-h);font-size:1.65rem;font-weight:400;color:var(--ink);line-height:1;}
    .sc-lbl{font-size:.7rem;color:var(--muted);margin-top:3px;font-weight:400;}

    /* ═══════════════════════════════
       TWO COLUMN LAYOUT
    ═══════════════════════════════ */
    .two-col{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;}
    .card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:18px;}
    .card:last-child{margin-bottom:0;}
    .card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
    .card-title{font-family:var(--font-h);font-size:1rem;font-weight:400;color:var(--ink);}
    .card-sub{font-size:.74rem;color:var(--muted);margin-top:1px;}
    .view-all{font-size:.78rem;font-weight:400;color:var(--teal);text-decoration:none;padding:4px 11px;border-radius:100px;background:rgba(124,58,237,.07);transition:background .2s;}
    .view-all:hover{background:rgba(124,58,237,.14);}

    /* ── POST JOB CTA ── */
    .post-cta{
      background:linear-gradient(135deg,rgba(124,58,237,.08),rgba(124,58,237,.04));
      border:1.5px dashed rgba(124,58,237,.3);
      border-radius:12px;padding:18px;text-align:center;margin-bottom:16px;
      transition:all .2s;cursor:pointer;text-decoration:none;display:block;
    }
    .post-cta:hover{background:rgba(124,58,237,.1);border-color:var(--teal);}
    .post-cta .cta-icon{font-size:1.8rem;margin-bottom:6px;}
    .post-cta .cta-text{font-size:.88rem;font-weight:400;color:var(--teal);}
    .post-cta .cta-sub{font-size:.75rem;color:var(--muted);margin-top:2px;}

    /* ── JOB ROW ── */
    .job-row{
      display:flex;align-items:center;gap:12px;
      padding:13px 0;border-bottom:1px solid var(--border);
    }
    .job-row:last-child{border-bottom:none;padding-bottom:0;}
    .jr-logo{width:38px;height:38px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;color:#fff;}
    .jr-info{flex:1;min-width:0;}
    .jr-title{font-size:.88rem;font-weight:400;color:var(--ink);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .jr-meta{font-size:.72rem;color:var(--muted);}
    .jr-apps{text-align:right;flex-shrink:0;}
    .jr-count{font-family:var(--font-h);font-size:1.1rem;font-weight:400;color:var(--teal);}
    .jr-lbl{font-size:.65rem;color:var(--muted);font-weight:400;}
    .status-pill{font-size:.65rem;font-weight:400;padding:2px 8px;border-radius:100px;white-space:nowrap;}
    .pill-open{background:var(--green-lt);color:var(--green);border:1px solid #bbf7d0;}
    .pill-closed{background:var(--red-lt);color:var(--red);}
    .pill-paused{background:var(--yellow-lt);color:var(--yellow);}

    /* ── APPLICANT CARD ── */
    .app-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .app-card{
      background:var(--cream);border:1.5px solid var(--border);
      border-radius:12px;padding:14px;
      transition:all .18s;
    }
    .app-card:hover{border-color:var(--teal);background:var(--white);box-shadow:var(--shadow);}
    .ac-top{display:flex;gap:10px;align-items:center;margin-bottom:10px;}
    .ac-ava{width:38px;height:38px;border-radius:10px;flex-shrink:0;background:var(--orange);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;overflow:hidden;}
    .ac-ava img{width:38px;height:38px;object-fit:cover;}
    .ac-name{font-size:.87rem;font-weight:400;color:var(--ink);margin-bottom:1px;}
    .ac-job{font-size:.72rem;color:var(--muted);}
    .ac-skills{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px;}
    .ac-skill{font-size:.67rem;font-weight:400;padding:2px 7px;border-radius:5px;background:var(--teal-lt);color:var(--teal);}
    .ac-foot{display:flex;align-items:center;justify-content:space-between;gap:6px;}
    .ac-date{font-size:.68rem;color:var(--muted);}

    /* status badge */
    .app-status{font-size:.65rem;font-weight:400;padding:2px 7px;border-radius:100px;}
    .s-pending     {background:var(--yellow-lt);color:var(--yellow);}
    .s-accepted    {background:var(--green-lt);color:var(--green);}
    .s-rejected    {background:var(--red-lt);color:var(--red);}
    .s-interview   {background:var(--purple-lt);color:var(--purple);}

    /* status change dropdown */
    .status-select{
      font-size:.68rem;font-weight:400;
      padding:3px 6px;border-radius:6px;
      border:1.5px solid var(--border);
      background:var(--white);color:var(--ink2);
      cursor:pointer;outline:none;
      font-family:var(--font-b);
      transition:border-color .2s;
    }
    .status-select:focus{border-color:var(--teal);}

    /* ── NOTIF ITEM ── */
    .notif-item{display:flex;gap:9px;align-items:flex-start;padding:9px 0;border-bottom:1px solid var(--border);}
    .notif-item:last-child{border-bottom:none;padding-bottom:0;}
    .n-dot{width:7px;height:7px;border-radius:50%;background:var(--orange);flex-shrink:0;margin-top:5px;}
    .n-dot.read{background:var(--border);}
    .n-msg{font-size:.79rem;color:var(--ink2);line-height:1.4;}
    .n-time{font-size:.67rem;color:var(--muted);margin-top:1px;}

    /* ── PROFILE CARD ── */
    .profile-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:20px;text-align:center;}
    .pc-ava{width:58px;height:58px;border-radius:14px;background:var(--teal);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.45rem;font-weight:400;margin:0 auto 10px;overflow:hidden;}
    .pc-ava img{width:58px;height:58px;object-fit:cover;}
    .pc-name{font-family:var(--font-h);font-size:.97rem;font-weight:400;color:var(--ink);margin-bottom:2px;}
    .pc-role{font-size:.72rem;color:var(--teal);font-weight:400;margin-bottom:4px;}
    .pc-loc{font-size:.75rem;color:var(--muted);margin-bottom:12px;}
    .btn-edit{display:block;width:100%;padding:8px;border-radius:100px;border:1.5px solid var(--border);background:transparent;color:var(--ink2);font-family:var(--font-b);font-size:.8rem;font-weight:400;cursor:pointer;text-decoration:none;transition:border-color .2s,background .2s;}
    .btn-edit:hover{border-color:var(--teal);background:rgba(124,58,237,.04);}

    /* ── QUICK STATS MINI ── */
    .mini-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;}
    .ms{background:var(--cream);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center;}
    .ms-num{font-family:var(--font-h);font-size:1.4rem;font-weight:400;}
    .ms-lbl{font-size:.68rem;color:var(--muted);font-weight:400;margin-top:2px;}

    /* ── OVERLAY ── */
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}

    /* ── RESPONSIVE ── */
    @media(max-width:1100px){
      .stats{grid-template-columns:repeat(3,1fr);}
      .two-col{grid-template-columns:1fr;}
      .right-col{display:none;}
    }
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main{margin-left:0;}
      .content{padding:14px;}
      .stats{grid-template-columns:repeat(2,1fr);}
      .welcome{flex-direction:column;gap:12px;padding:18px;}
      .wr{display:none;}
      .hamburger{display:flex;}
      .app-grid{grid-template-columns:1fr;}
    }
    @media(max-width:480px){.stats{grid-template-columns:1fr 1fr;}}



/* ============ AUTO-GENERATED DARK MODE ============ */
body.dark-mode{background:#0B1220;color:#E2E8F0;}
body.dark-mode .hamburger{background:#1E293B;border:1px solid #1E293B;}
body.dark-mode .tb-crumb{color:#94A3B8;}
body.dark-mode .tb-crumb strong{color:#E2E8F0;}
body.dark-mode .tb-btn{background:#0B1220;border:1px solid #1E293B;}
body.dark-mode .tb-btn:hover{background:#1E293B;}
body.dark-mode .tb-dot{border:2px solid #0B1220;}
body.dark-mode .wl-btn-w{background:#111827;}
body.dark-mode .wl-btn-w:hover{background:rgba(17,24,39,.92);}
body.dark-mode .sc{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .sc-num{color:#E2E8F0;}
body.dark-mode .sc-lbl{color:#94A3B8;}
body.dark-mode .card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .card-title{color:#E2E8F0;}
body.dark-mode .card-sub{color:#94A3B8;}
body.dark-mode .post-cta .cta-sub{color:#94A3B8;}
body.dark-mode .jr-title{color:#E2E8F0;}
body.dark-mode .jr-meta{color:#94A3B8;}
body.dark-mode .jr-lbl{color:#94A3B8;}
body.dark-mode .pill-open{background:rgba(22,163,74,.14);}
body.dark-mode .pill-closed{background:rgba(220,38,38,.14);}
body.dark-mode .pill-paused{background:rgba(217,119,6,.16);}
body.dark-mode .app-card{background:#0B1220;border:1.5px solid #1E293B;}
body.dark-mode .app-card:hover{background:#111827;}
body.dark-mode .ac-name{color:#E2E8F0;}
body.dark-mode .ac-job{color:#94A3B8;}
body.dark-mode .ac-skill{background:rgba(124,58,237,.18);}
body.dark-mode .ac-date{color:#94A3B8;}
body.dark-mode .s-pending{background:rgba(217,119,6,.16);}
body.dark-mode .s-accepted{background:rgba(22,163,74,.14);}
body.dark-mode .s-rejected{background:rgba(220,38,38,.14);}
body.dark-mode .s-interview{background:rgba(124,58,237,.18);}
body.dark-mode .n-dot.read{background:#1E293B;}
body.dark-mode .n-msg{color:#CBD5E1;}
body.dark-mode .n-time{color:#94A3B8;}
body.dark-mode .pc-name{color:#E2E8F0;}
body.dark-mode .pc-loc{color:#94A3B8;}
body.dark-mode .btn-edit{border:1.5px solid #1E293B;color:#CBD5E1;}
body.dark-mode .profile-card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .status-select{background:#0B1220;border:1.5px solid #1E293B;color:#E2E8F0;}
body.dark-mode .ms{background:#0B1220;border:1px solid #1E293B;}
body.dark-mode .ms-lbl{color:#94A3B8;}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>


<!-- ═══════ SIDEBAR ═══════ -->
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
      <div class="sb-urole">Client</div>
    </div>
  </div>

  <nav class="sb-nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php" class="active"><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="post_job.php"><span class="nav-ic"><img src="../assets/icons/request.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Service Request</a>
    <a href="manage_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Jobs</a>
    <a href="applicants.php">
      <span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants
      <?php if ($pending_apps > 0): ?><span class="nav-bdg"><?= $pending_apps ?></span><?php endif; ?>
    </a>
    
    <a href="hired.php"><span class="nav-ic"><img src="../assets/icons/job accepted.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Hired</a>

    <div class="nav-section">Connect</div>
    <a href="network.php"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="chat.php">
      <span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat
      <?php if ($unread_msgs > 0): ?><span class="nav-bdg"><?= $unread_msgs ?></span><?php endif; ?>
    </a>
    <a href="notifications.php">
      <span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications
      <?php if ($unread_notif > 0): ?><span class="nav-bdg"><?= $unread_notif ?></span><?php endif; ?>
    </a>

    <div class="nav-section">Account</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a>
    <a href="ratings.php"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>

  <div class="sb-logout">
    <a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a>
  </div>
</div>

<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ═══════ MAIN ═══════ -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="tb-left">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/dashboard (3).png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb">SkillConnect / <strong>Dashboard</strong></span>
    </div>
    <div class="tb-right">
      <a href="post_job.php"
         style="padding:7px 16px;background:var(--teal);color:#fff;border-radius:100px;
                font-size:.8rem;font-weight:400;text-decoration:none;
                box-shadow:0 3px 10px rgba(124,58,237,.28);">
        + Post Job
      </a>
      <a href="chat.php" class="tb-btn">
        <img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;">
        <?php if ($unread_msgs > 0): ?><span class="tb-dot"><?= $unread_msgs ?></span><?php endif; ?>
      </a>
      <a href="notifications.php" class="tb-btn">
        <img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;">
        <?php if ($unread_notif > 0): ?><span class="tb-dot"><?= $unread_notif ?></span><?php endif; ?>
      </a>
      <a href="profile.php" class="tb-ava">
        <?php if (!empty($profile['profile_pic'])): ?>
          <img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt="">
        <?php else: ?>
          <?= $avatar_letter ?>
        <?php endif; ?>
      </a>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <!-- WELCOME BANNER -->
    <div class="welcome">
      <div class="wl">
       
        <div class="wl-name"><?= htmlspecialchars($first_name) ?>'s Dashboard</div>
        <div class="wl-sub">
          You have <strong><?= $open_jobs ?> open job<?= $open_jobs != 1 ? 's' : '' ?></strong>
          with <strong><?= $pending_apps ?> new application<?= $pending_apps != 1 ? 's' : '' ?></strong> waiting.
        </div>
        <div class="wl-btns">
          <a href="post_job.php" class="wl-btn wl-btn-w">➕ Post New Job</a>
          <a href="applicants.php" class="wl-btn wl-btn-o">👥 View Applicants</a>
        </div>
      </div>
      <div class="wr">
        <div class="wr-ava">
          <?php if (!empty($profile['profile_pic'])): ?>
            <img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt="">
          <?php else: ?>
            <?= $avatar_letter ?>
          <?php endif; ?>
        </div>
        <div class="wr-lbl">🏢 Client Account</div>
      </div>
    </div>

    <!-- STATS ROW -->
    <div class="stats">
  <a href="manage_jobs.php" class="sc" style="text-decoration:none;">
    <div class="sc-top">
      <div class="sc-icon" style="background:rgba(124,58,237,.1);">
        <img src="../assets/icons/browse_jobs.png" alt="" style="width:45px;height:45px;">
      </div>
      <span class="sc-badge" style="background:rgba(124,58,237,.1);color:var(--teal);">Total</span>
    </div>
    <div class="sc-num"><?= $total_jobs ?></div>
    <div class="sc-lbl">Jobs Posted</div>
  </a>

  <a href="manage_jobs.php?status=open" class="sc" style="text-decoration:none;">
    <div class="sc-top">
      <div class="sc-icon" style="background:var(--green-lt);"><img src="../assets/icons/activity.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div>
      <span class="sc-badge" style="background:var(--green-lt);color:var(--green);">Active</span>
    </div>
    <div class="sc-num" style="color:var(--green);"><?= $open_jobs ?></div>
    <div class="sc-lbl">Open Jobs</div>
  </a>

  <a href="applicants.php" class="sc" style="text-decoration:none;">
    <div class="sc-top">
      <div class="sc-icon" style="background:rgba(232,96,10,.1);">
        <img src="../assets/icons/application.png" alt="" style="width:45px;height:45px;">
      </div>
      <span class="sc-badge" style="background:rgba(232,96,10,.1);color:var(--orange);">All</span>
    </div>
    <div class="sc-num" style="color:var(--orange);"><?= $total_apps ?></div>
    <div class="sc-lbl">Total Applicants</div>
  </a>

  <a href="applicants.php?status=pending" class="sc" style="text-decoration:none;">
    <div class="sc-top">
      <div class="sc-icon" style="background:var(--yellow-lt);">
        <img src="../assets/icons/pending.png" alt="" style="width:45px;height:45px;">
      </div>
      <span class="sc-badge" style="background:var(--yellow-lt);color:var(--yellow);">New</span>
    </div>
    <div class="sc-num" style="color:var(--yellow);"><?= $pending_apps ?></div>
    <div class="sc-lbl">Pending Review</div>
  </a>

  <a href="hired.php" class="sc" style="text-decoration:none;">
    <div class="sc-top">
      <div class="sc-icon" style="background:var(--purple-lt);">
        <img src="../assets/icons/job accepted.png" alt="" style="width:45px;height:45px;">
      </div>
      <span class="sc-badge" style="background:var(--purple-lt);color:var(--purple);">Hired</span>
    </div>
    <div class="sc-num" style="color:var(--purple);"><?= $hired ?></div>
    <div class="sc-lbl">Hired Workers</div>
  </a>
</div>

    <!-- TWO COLUMN -->
    <div class="two-col">

      <!-- LEFT -->
      <div>

        <!-- MY JOBS -->
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">My Posted Jobs</div>
              <div class="card-sub">Your recent job postings</div>
            </div>
            <a href="manage_jobs.php" class="view-all">Manage All →</a>
          </div>

          <!-- Post Job CTA -->
          <a href="post_job.php" class="post-cta">
            <div class="cta-icon">➕</div>
            <div class="cta-text">New Service Request</div>
            <div class="cta-sub">Find skilled workers for your next project</div>
          </a>

          <?php if ($my_jobs && $my_jobs->num_rows > 0):
            $ci = 0;
            while ($job = $my_jobs->fetch_assoc()):
              $col = $job_colors[$ci++ % count($job_colors)];
              $diff = time() - strtotime($job['created_at']);
              $ago  = $diff < 3600 ? round($diff/60).'m ago' : ($diff < 86400 ? round($diff/3600).'h ago' : round($diff/86400).'d ago');
          ?>
            <div class="job-row">
              <div class="jr-logo" style="background:<?= $col ?>;">
                <?= strtoupper(substr($job['title'],0,1)) ?>
              </div>
              <div class="jr-info">
                <div class="jr-title"><?= htmlspecialchars($job['title']) ?></div>
                <div class="jr-meta">
                  📅 <?= $ago ?>
                  <?php if (!empty($job['budget'])): ?>
                    · 💰 ₹<?= number_format($job['budget']) ?>
                  <?php endif; ?>
                  <?php if ((int)$job['pending_count'] > 0): ?>
                    · <span style="color:var(--orange);font-weight:400;"><?= $job['pending_count'] ?> new</span>
                  <?php endif; ?>
                </div>
              </div>
              <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;">
                <span class="status-pill pill-<?= $job['status'] ?>"><?= ucfirst($job['status']) ?></span>
                <div class="jr-apps">
                  <div class="jr-count"><?= $job['app_count'] ?></div>
                  <div class="jr-lbl">applicants</div>
                </div>
              </div>
              <div style="display:flex;gap:5px;">
                <a href="applicants.php?job_id=<?= $job['id'] ?>"
                   style="padding:5px 10px;background:var(--teal-lt);color:var(--teal);border-radius:8px;font-size:.72rem;font-weight:400;text-decoration:none;white-space:nowrap;">
                  View →
                </a>
              </div>
            </div>
          <?php endwhile; ?>

          <?php else: ?>
            <div style="text-align:center;padding:30px;color:var(--muted);">
              <div style="font-size:2rem;margin-bottom:8px;">💼</div>
              <div style="font-size:.85rem;">No jobs posted yet.</div>
            </div>
          <?php endif; ?>
        </div>

        <!-- RECENT APPLICANTS -->
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title"><img src="../assets/icons/all members.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">Recent Applicants</div>
              <div class="card-sub">Latest workers who applied to your jobs</div>
            </div>
            <a href="applicants.php" class="view-all">View All →</a>
          </div>

          <?php if ($recent_apps && $recent_apps->num_rows > 0): ?>
            <div class="app-grid">
              <?php
                $colors2 = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706'];
                $ai = 0;
                while ($app = $recent_apps->fetch_assoc()):
                  $ac = $colors2[$ai++ % count($colors2)];
                  $skills = array_slice(array_filter(array_map('trim', explode(',', $app['worker_skills'] ?? ''))), 0, 3);
                  $s = strtolower($app['status']);
                  $bc = str_contains($s,'interview') ? 'interview' : $s;
              ?>
                <div class="app-card">
                  <div class="ac-top">
                    <div class="ac-ava" style="background:<?= $ac ?>;">
                      <?php if (!empty($app['worker_pic'])): ?>
                        <img src="../uploads/profiles/<?= htmlspecialchars($app['worker_pic']) ?>" alt="">
                      <?php else: ?>
                        <?= strtoupper(substr($app['worker_name'],0,1)) ?>
                      <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                      <div class="ac-name"><?= htmlspecialchars(mb_strimwidth($app['worker_name'],0,22,'…')) ?></div>
                      <div class="ac-job">📌 <?= htmlspecialchars(mb_strimwidth($app['job_title'],0,22,'…')) ?></div>
                    </div>
                  </div>

                  <?php if (!empty($skills)): ?>
                    <div class="ac-skills">
                      <?php foreach ($skills as $sk): ?>
                        <span class="ac-skill"><?= htmlspecialchars($sk) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <div class="ac-foot">
                    <div class="ac-date"><?= date('d M', strtotime($app['applied_at'])) ?></div>
                    <div style="display:flex;align-items:center;gap:6px;">
                      <!-- Quick status change -->
                      <form method="GET" style="margin:0;">
                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                        <select name="status_change" class="status-select"
                                onchange="this.form.submit()"
                                title="Change status">
                          <option value="" disabled selected><?= ucfirst($app['status']) ?></option>
                          <option value="pending">⏳ Pending</option>
                          <option value="accepted">✅ Accept</option>
                          <option value="interview scheduled">🎯 Interview</option>
                          <option value="rejected">❌ Reject</option>
                        </select>
                      </form>
                      <a href="applicant_profile.php?id=<?= $app['worker_id'] ?>"
                         style="font-size:.7rem;font-weight:400;color:var(--teal);text-decoration:none;
                                padding:3px 8px;background:var(--teal-lt);border-radius:6px;">
                        View
                      </a>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>

          <?php else: ?>
            <div style="text-align:center;padding:30px;color:var(--muted);">
              <div style="font-size:2rem;margin-bottom:8px;">👥</div>
              <div style="font-size:.85rem;">No applicants yet. Post a job to get started!</div>
            </div>
          <?php endif; ?>
        </div>

      </div><!-- /left -->

      <!-- RIGHT COLUMN -->
      <div class="right-col">

        <!-- Profile Card -->
        <div class="profile-card" style="margin-bottom:16px;">
          <div class="pc-ava">
            <?php if (!empty($profile['profile_pic'])): ?>
              <img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt="">
            <?php else: ?>
              <?= $avatar_letter ?>
            <?php endif; ?>
          </div>
          <div class="pc-name"><?= htmlspecialchars($user_name) ?></div>
          <div class="pc-role">🏢 Client / Employer</div>
          <div class="pc-loc">📍 <?= htmlspecialchars($profile['location'] ?? 'Location not set') ?></div>
          <a href="profile.php" class="btn-edit">✏️ Edit Company Profile</a>
        </div>

        <!-- Mini Stats -->
        <div class="mini-stats">
          <div class="ms">
            <div class="ms-num" style="color:var(--teal);"><?= $open_jobs ?></div>
            <div class="ms-lbl">Open Jobs</div>
          </div>
          <div class="ms">
            <div class="ms-num" style="color:var(--orange);"><?= $pending_apps ?></div>
            <div class="ms-lbl">New Applications</div>
          </div>
          <div class="ms">
            <div class="ms-num" style="color:var(--purple);"><?= $hired ?></div>
            <div class="ms-lbl">Hired</div>
          </div>
        </div>

        <!-- Notifications -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-head">
            <div class="card-title"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">Notifications</div>
            <a href="notifications.php" class="view-all">All →</a>
          </div>
          <?php if ($notifs && $notifs->num_rows > 0): ?>
            <?php while ($n = $notifs->fetch_assoc()): ?>
              <div class="notif-item">
                <div class="n-dot <?= $n['is_read'] ? 'read' : '' ?>"></div>
                <div>
                  <div class="n-msg"><?= htmlspecialchars($n['message']) ?></div>
                  <div class="n-time"><?= date('d M, h:i A', strtotime($n['created_at'])) ?></div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p style="text-align:center;color:var(--muted);font-size:.82rem;padding:12px 0;">
              No notifications yet.
            </p>
          <?php endif; ?>
        </div>

      </div><!-- /right-col -->
    </div><!-- /two-col -->

  </div><!-- /content -->
</div><!-- /main -->

<script>
function openSidebar(){
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('overlay').classList.add('show');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('show');
}
</script>

</body>
</html>