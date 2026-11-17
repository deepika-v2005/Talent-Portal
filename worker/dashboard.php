<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "skillconnect");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── FETCH PROFILE ──
$profile = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

// ── STATS ──
$total_apps  = $conn->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id")->fetch_assoc()['c'];
$accepted    = $conn->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='accepted'")->fetch_assoc()['c'];
$pending     = $conn->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='pending'")->fetch_assoc()['c'];
$shortlisted = $conn->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='shortlisted'")->fetch_assoc()['c'];
$saved_count = $conn->query("SELECT COUNT(*) as c FROM saved_jobs WHERE worker_id=$user_id")->fetch_assoc()['c'];
$rating_row  = $conn->query("SELECT AVG(stars) as avg, COUNT(*) as total FROM ratings WHERE worker_id=$user_id")->fetch_assoc();
$avg_rating  = $rating_row['avg'] ? number_format($rating_row['avg'], 1) : '—';
$total_ratings = (int)$rating_row['total'];

// ── UNREAD COUNTS ──
$unread_notif = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs  = $conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];

// ── SEARCH + FILTER ──
$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');

$where = "j.status='open'";
if (!empty($search))
    $where .= " AND (j.title LIKE '%" . $conn->real_escape_string($search) . "%'
                  OR j.description LIKE '%" . $conn->real_escape_string($search) . "%'
                  OR j.skills_required LIKE '%" . $conn->real_escape_string($search) . "%')";
if (!empty($category))
    $where .= " AND j.skills_required LIKE '%" . $conn->real_escape_string($category) . "%'";

// ── HANDLE APPLY ──
if (isset($_GET['apply'])) {
    $jid = (int)$_GET['apply'];
    $chk = $conn->query("SELECT id FROM applications WHERE worker_id=$user_id AND job_id=$jid");
    if ($chk->num_rows === 0) {
        $conn->query("INSERT INTO applications (worker_id, job_id, status, applied_at)
                      VALUES ($user_id, $jid, 'pending', NOW())");
        $ji = $conn->query("SELECT j.title, j.client_id, u.name as wn
                             FROM jobs j JOIN users u ON u.id=$user_id
                             WHERE j.id=$jid")->fetch_assoc();
        if ($ji) {
            $msg = $conn->real_escape_string("{$ji['wn']} applied for: {$ji['title']}");
            $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at)
                          VALUES ({$ji['client_id']},'application','$msg',0,NOW())");
        }
    }
    $p = $_GET; unset($p['apply']);
    header("Location: dashboard.php?" . http_build_query($p)); exit();
}

// ── HANDLE SAVE TOGGLE ──
if (isset($_GET['save'])) {
    $jid = (int)$_GET['save'];
    $chk = $conn->query("SELECT id FROM saved_jobs WHERE worker_id=$user_id AND job_id=$jid");
    if ($chk->num_rows > 0)
        $conn->query("DELETE FROM saved_jobs WHERE worker_id=$user_id AND job_id=$jid");
    else
        $conn->query("INSERT INTO saved_jobs (worker_id,job_id,saved_at) VALUES ($user_id,$jid,NOW())");
    $p = $_GET; unset($p['save']);
    header("Location: dashboard.php?" . http_build_query($p)); exit();
}

// ── FETCH JOBS ──
$jobs_query = $conn->query("
    SELECT j.*, u.name AS client_name, u.location AS client_loc
    FROM jobs j JOIN users u ON j.client_id = u.id
    WHERE $where ORDER BY j.created_at DESC LIMIT 10
");

$applied_ids = [];
$ar = $conn->query("SELECT job_id FROM applications WHERE worker_id=$user_id");
while ($r = $ar->fetch_assoc()) $applied_ids[] = $r['job_id'];

$saved_ids = [];
$sr = $conn->query("SELECT job_id FROM saved_jobs WHERE worker_id=$user_id");
while ($r = $sr->fetch_assoc()) $saved_ids[] = $r['job_id'];

// ── RIGHT SIDEBAR DATA ──
$recent_apps = $conn->query("
    SELECT a.status, a.applied_at, j.title
    FROM applications a JOIN jobs j ON a.job_id = j.id
    WHERE a.worker_id=$user_id ORDER BY a.applied_at DESC LIMIT 5
");

$recent_notifs = $conn->query("
    SELECT * FROM notifications WHERE user_id=$user_id
    ORDER BY created_at DESC LIMIT 4
");

$skills_progress = $conn->query("
    SELECT * FROM worker_skills WHERE worker_id=$user_id ORDER BY id ASC LIMIT 4
");

$conn->close();

// ── HELPERS ──
$categories = [
    ['id'=>'makeup','icon'=>'💄','label'=>'Makeup Artist'],
    ['id'=>'mehendi','icon'=>'🌿','label'=>'Mehendi'],
    ['id'=>'photography','icon'=>'📸','label'=>'Photography'],
    ['id'=>'catering','icon'=>'🍽️','label'=>'Catering'],
    ['id'=>'tailoring','icon'=>'🧵','label'=>'Tailoring'],
    ['id'=>'tutor','icon'=>'📚','label'=>'Tutor'],
    ['id'=>'music','icon'=>'🎵','label'=>'Music'],
    ['id'=>'poetry','icon'=>'✍️','label'=>'Poetry & Art'],
    ['id'=>'videography','icon'=>'🎬','label'=>'Videography'],
    ['id'=>'design','icon'=>'🎨','label'=>'Graphic Design'],
    ['id'=>'event','icon'=>'🎪','label'=>'Event Planning'],
    ['id'=>'dance','icon'=>'💃','label'=>'Dance & Performance'],
];

$job_colors    = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed','#db2777','#d97706','#dc2626','#059669'];
$avatar_letter = strtoupper(substr($user_name, 0, 1));
$first_name    = explode(' ', $user_name)[0];

// Profile completion %
$filled = 0;
foreach (['name','phone','bio','skills','education','experience'] as $f)
    if (!empty($profile[$f])) $filled++;
$complete_pct = round(($filled / 6) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Dashboard – SkillConnect</title>
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
      --sidebar:240px;
      --topbar:60px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --shadow-md:0 6px 24px rgba(11,31,58,0.10);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    html{scroll-behavior:smooth;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);min-height:100vh;display:flex;overflow-x:hidden;}
    /* ── Dark mode (syncs with Settings > Dark Mode) ── */
    body.dark-mode{
      --cream:#0B1220;--cream2:#0B1220;--white:#111827;
      --ink:#E2E8F0;--ink2:#CBD5E1;--muted:#94A3B8;
      --border:#1E293B;--teal-lt:rgba(124,58,237,.18);--blue-lt:rgba(29,78,216,.16);--red-lt:rgba(220,38,38,.14);--green-lt:rgba(22,163,74,.14);--yellow-lt:rgba(217,119,6,.16);
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

    body.dark-mode .s-interview{background:rgba(124,58,237,.18) !important;}
    body.dark-mode .btn-save.saved{background:rgba(217,119,6,.16) !important;}
/* ═══════════════════════════════
       SIDEBAR  (self-contained)
    ═══════════════════════════════ */
    .sidebar{
      width:var(--sidebar);height:100vh;
      background:var(--ink);
      display:flex;flex-direction:column;
      position:fixed;top:0;left:0;z-index:200;
      overflow-y:auto;transition:transform 0.28s ease;
    }
    /* ── logo ── */
    .sb-logo{
      padding:20px;border-bottom:1px solid rgba(255,255,255,0.08);
      flex-shrink:0;
    }
    .sb-logo a{
      font-family:var(--font-h);font-size:1.2rem;font-weight:400;
      color:#fff;text-decoration:none;
    }
    .sb-logo span{color:var(--orange2);}

    /* ── user chip ── */
    .sb-user{
      padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.08);
      display:flex;align-items:center;gap:11px;flex-shrink:0;
    }
    .sb-ava{
      width:40px;height:40px;border-radius:11px;flex-shrink:0;
      background:var(--orange);color:#fff;
      display:flex;align-items:center;justify-content:center;
      font-family:var(--font-h);font-size:1rem;font-weight:900;overflow:hidden;
    }
    .sb-ava img{width:40px;height:40px;object-fit:cover;}
    .sb-uname{font-size:0.86rem;font-weight:400;color:#fff;line-height:1.3;}
    .sb-urole{font-size:0.7rem;color:rgba(255,255,255,0.42);margin-top:1px;}

    /* ── nav ── */
    .sb-nav{flex:1;padding:10px 0;overflow-y:auto;}
    .nav-section{
      font-size:0.62rem;font-weight:400;letter-spacing:2px;
      text-transform:uppercase;color:rgba(255,255,255,0.2);
      padding:12px 20px 5px;
    }
    .sb-nav a{
      display:flex;align-items:center;gap:11px;
      padding:9px 20px;
      font-size:0.84rem;font-weight:400;color:rgba(255,255,255,0.52);
      text-decoration:none;border-left:3px solid transparent;
      transition:all 0.18s;position:relative;
    }
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,0.05);border-left-color:rgba(255,255,255,0.18);}
    .sb-nav a.active{color:#fff;background:rgba(232,96,10,0.15);border-left-color:var(--orange);}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{
      margin-left:auto;background:var(--orange);color:#fff;
      font-size:0.6rem;font-weight:800;padding:2px 6px;
      border-radius:100px;min-width:17px;text-align:center;flex-shrink:0;
    }

    /* ── logout ── */
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,0.08);flex-shrink:0;}
    .sb-logout a{
      display:flex;align-items:center;gap:9px;padding:9px 13px;
      border-radius:9px;background:rgba(220,38,38,0.11);
      border:1px solid rgba(220,38,38,0.18);color:#f87171;
      font-size:0.83rem;font-weight:400;text-decoration:none;transition:background 0.2s;
    }.sb-logout a img{filter:brightness(0) invert(1);}
    .sb-logout a:hover{background:rgba(220,38,38,0.2);}

    /* ═══════════════════════════════
       MAIN WRAPPER
    ═══════════════════════════════ */
    .main{
      margin-left:var(--sidebar);flex:1;
      display:flex;flex-direction:column;min-height:100vh;
    }

    /* ── topbar ── */
    .topbar{
      height:var(--topbar);padding:0 26px;
      background:var(--white);backdrop-filter:blur(14px);
      border-bottom:1px solid var(--border);
      display:flex;align-items:center;justify-content:space-between;
      position:sticky;top:0;z-index:100;
    }
    .tb-left{display:flex;align-items:center;gap:9px;}
    .hamburger{
      display:none;width:33px;height:33px;border-radius:8px;
      background:var(--cream2);border:1px solid var(--border);
      align-items:center;justify-content:center;
      font-size:1rem;cursor:pointer;flex-shrink:0;
    color:var(--ink);}
    .tb-crumb{font-size:0.85rem;color:var(--muted);}
    .tb-crumb strong{color:var(--ink);font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{
      position:relative;width:35px;height:35px;border-radius:9px;
      background:var(--cream2);border:1px solid var(--border);
      display:flex;align-items:center;justify-content:center;
      font-size:1rem;text-decoration:none;transition:background 0.18s;cursor:pointer;
    }
    .tb-btn:hover{background:var(--border);}
    .tb-dot{
      position:absolute;top:-3px;right:-3px;
      background:var(--orange);color:#fff;
      font-size:0.56rem;font-weight:800;
      min-width:15px;height:15px;border-radius:100px;
      display:flex;align-items:center;justify-content:center;
      border:2px solid var(--white);padding:0 2px;
    }
    .tb-ava{
      width:35px;height:35px;border-radius:9px;
      background:var(--orange);color:#fff;
      display:flex;align-items:center;justify-content:center;
      font-family:var(--font-h);font-size:0.9rem;font-weight:900;
      text-decoration:none;overflow:hidden;
    }
    .tb-ava img{width:35px;height:35px;object-fit:cover;}

    /* ── page content ── */
    .content{padding:24px 26px;flex:1;}

    /* ═══════════════════════════════
       WELCOME BANNER
    ═══════════════════════════════ */
    .welcome{
      background:linear-gradient(135deg,var(--orange) 0%,#9F67FA 100%);
      border-radius:18px;padding:24px 28px;
      display:flex;align-items:center;justify-content:space-between;
      margin-bottom:22px;overflow:hidden;position:relative;
    }
    .welcome::before{
      content:'';position:absolute;top:-55px;right:-55px;
      width:190px;height:190px;background:rgba(255,255,255,0.08);border-radius:50%;
    }
    .welcome::after{
      content:'';position:absolute;bottom:-30px;right:130px;
      width:120px;height:120px;background:rgba(255,255,255,0.05);border-radius:50%;
    }
    .wl{position:relative;z-index:1;}
    .wl-hi{font-size:0.77rem;color:rgba(255,255,255,0.72);font-weight:600;margin-bottom:3px;letter-spacing:.4px;}
    .wl-name{font-family:var(--font-h);font-size:1.75rem;font-weight:400;color:#fff;line-height:1.1;margin-bottom:7px;}
    .wl-sub{font-size:0.85rem;color:rgba(255,255,255,0.82);max-width:380px;line-height:1.55;}
    .wl-sub strong{color:#fff;}
    .wl-btns{display:flex;gap:9px;margin-top:16px;}
    .wl-btn{
      padding:8px 18px;border-radius:100px;
      font-family:var(--font-b);font-size:0.81rem;font-weight:700;
      text-decoration:none;transition:all 0.2s;
    }
    .wl-btn-w{background:#fff;color:var(--orange);}
    .wl-btn-w:hover{background:rgba(255,255,255,0.9);}
    .wl-btn-o{background:rgba(255,255,255,0.15);color:#fff;border:1.5px solid rgba(255,255,255,0.38);}
    .wl-btn-o:hover{background:rgba(255,255,255,0.22);}
    .wr{position:relative;z-index:1;text-align:center;}
    .wr-ava{
      width:70px;height:70px;border-radius:16px;
      background:rgba(255,255,255,0.2);border:2px solid rgba(255,255,255,0.32);
      display:flex;align-items:center;justify-content:center;
      font-family:var(--font-h);font-size:1.9rem;font-weight:900;color:#fff;overflow:hidden;
    }
    .wr-ava img{width:70px;height:70px;object-fit:cover;}
    .wr-rating{font-size:0.72rem;color:rgba(255,255,255,0.78);margin-top:5px;font-weight:600;}

    /* ═══════════════════════════════
       STATS ROW
    ═══════════════════════════════ */
    .stats{display:grid;grid-template-columns:repeat(5,1fr);gap:13px;margin-bottom:22px;}
    .sc{
      background:var(--white);border:1.5px solid var(--border);
      border-radius:var(--radius);padding:16px;
      text-decoration:none;display:block;
      transition:border-color 0.2s,box-shadow 0.2s;
    }
    .sc:hover{border-color:var(--orange);box-shadow:var(--shadow-md);}
    .sc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:9px;}
    .sc-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.95rem;}
    .sc-badge{font-size:0.67rem;font-weight:800;padding:2px 7px;border-radius:100px;}
    .sc-num{font-family:var(--font-h);font-size:1.7rem;font-weight:400;color:var(--ink);line-height:1;}
    .sc-lbl{font-size:0.72rem;color:var(--muted);margin-top:3px;font-weight:600;}

    /* ═══════════════════════════════
       TWO COLUMN
    ═══════════════════════════════ */
    .two-col{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;}

    /* section card */
    .card{
      background:var(--white);border:1.5px solid var(--border);
      border-radius:var(--radius);padding:20px;margin-bottom:18px;
    }
    .card:last-child{margin-bottom:0;}
    .card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
    .card-title{font-family:var(--font-h);font-size:1rem;font-weight:400;color:var(--ink);}
    .card-sub{font-size:0.75rem;color:var(--muted);margin-top:1px;}
    .view-all{
      font-size:0.78rem;font-weight:700;color:var(--orange);
      text-decoration:none;padding:4px 11px;border-radius:100px;
      background:rgba(232,96,10,0.07);transition:background 0.2s;
    }
    .view-all:hover{background:rgba(232,96,10,0.14);}

    /* ── search bar ── */
    .search-bar{
      display:flex;gap:8px;align-items:center;
      background:var(--cream);border:1.5px solid var(--border);
      border-radius:100px;padding:5px 5px 5px 14px;
      margin-bottom:13px;transition:border-color 0.2s;
    }
    .search-bar:focus-within{border-color:var(--orange);}
    .search-bar input{
      flex:1;border:none;background:transparent;
      color:var(--ink);font-family:var(--font-b);font-size:0.88rem;outline:none;
    }
    .search-bar input::placeholder{color:#b5a89a;}
    .search-bar button{
      padding:8px 18px;border-radius:100px;background:var(--orange);color:#fff;
      font-family:var(--font-b);font-size:0.82rem;font-weight:700;
      border:none;cursor:pointer;transition:background 0.2s;flex-shrink:0;
    }
    .search-bar button:hover{background:#d4540a;}

    /* category chips */
    .chips{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:15px;}
    .chip{
      padding:5px 12px;border-radius:100px;
      border:1.5px solid var(--border);background:var(--white);
      color:var(--ink2);font-family:var(--font-b);font-size:0.75rem;font-weight:400;
      cursor:pointer;text-decoration:none;transition:all 0.18s;white-space:nowrap;
      display:flex;align-items:center;gap:4px;
    }
    .chip:hover,.chip.active{border-color:var(--orange);background:rgba(232,96,10,0.07);color:var(--orange);}

    /* ── job card ── */
    .jobs-list{display:flex;flex-direction:column;gap:10px;}
    .jc{
      background:var(--cream);border:1.5px solid var(--border);
      border-radius:12px;padding:16px 18px;
      transition:all 0.18s;
    }
    .jc:hover{border-color:var(--orange);background:var(--white);box-shadow:var(--shadow-md);}
    .jc-top{display:flex;align-items:flex-start;gap:12px;margin-bottom:9px;}
    .jc-logo{
      width:42px;height:42px;border-radius:11px;flex-shrink:0;
      display:flex;align-items:center;justify-content:center;
      font-family:var(--font-h);font-size:0.95rem;font-weight:900;color:#fff;
    }
    .jc-title{font-family:var(--font-h);font-size:0.95rem;font-weight:900;color:var(--ink);margin-bottom:2px;}
    .jc-client{font-size:0.76rem;color:var(--muted);}
    .jc-client strong{color:var(--ink2);font-weight:700;}
    .jc-open{
      font-size:0.65rem;font-weight:800;padding:2px 8px;border-radius:100px;
      background:var(--green-lt);color:var(--green);border:1px solid #bbf7d0;
      white-space:nowrap;flex-shrink:0;margin-left:auto;
    }
    .jc-desc{
      font-size:0.81rem;color:var(--muted);line-height:1.5;margin-bottom:9px;
      display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
    }
    .jc-tags{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px;}
    .jc-tag{
      padding:2px 8px;border-radius:5px;
      font-size:0.68rem;font-weight:700;
      background:var(--teal-lt);color:var(--teal);
    }
    .jc-foot{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:7px;}
    .jc-meta{display:flex;gap:12px;flex-wrap:wrap;}
    .jc-mi{display:flex;align-items:center;gap:3px;font-size:0.73rem;color:var(--muted);}
    .jc-mi strong{color:var(--ink2);font-weight:700;}
    .jc-acts{display:flex;gap:6px;align-items:center;}
    .btn-apply{
      padding:6px 14px;border-radius:100px;
      background:var(--orange);color:#fff;
      font-family:var(--font-b);font-size:0.77rem;font-weight:700;
      text-decoration:none;border:none;cursor:pointer;
      transition:background 0.18s,transform 0.12s;
      box-shadow:0 3px 10px rgba(232,96,10,0.22);
    }
    .btn-apply:hover{background:#d4540a;transform:translateY(-1px);}
    .btn-apply.applied{
      background:var(--green-lt);color:var(--green);border:1.5px solid #bbf7d0;
      box-shadow:none;cursor:default;pointer-events:none;
    }
    .btn-save{
      width:30px;height:30px;border-radius:100px;
      background:var(--cream2);border:1px solid var(--border);
      display:flex;align-items:center;justify-content:center;
      font-size:0.85rem;cursor:pointer;text-decoration:none;transition:background 0.18s;
    }
    .btn-save:hover{background:var(--border);}
    .btn-save.saved{background:#fef3c7;border-color:#fde68a;}
    .no-jobs{text-align:center;padding:44px 20px;color:var(--muted);}
    .no-jobs-icon{font-size:2.2rem;margin-bottom:10px;}
    .no-jobs h3{font-family:var(--font-h);font-size:1rem;color:var(--ink2);margin-bottom:5px;}

    /* ── RIGHT COLUMN ── */
    .right-col{display:flex;flex-direction:column;gap:16px;}

    /* profile card */
    .profile-card{
      background:var(--white);border:1.5px solid var(--border);
      border-radius:var(--radius);padding:20px;text-align:center;
    }
    .pc-ava{
      width:58px;height:58px;border-radius:14px;
      background:var(--orange);color:#fff;
      display:flex;align-items:center;justify-content:center;
      font-family:var(--font-h);font-size:1.45rem;font-weight:900;
      margin:0 auto 10px;overflow:hidden;
    }
    .pc-ava img{width:58px;height:58px;object-fit:cover;}
    .pc-name{font-family:var(--font-h);font-size:0.97rem;font-weight:400;color:var(--ink);margin-bottom:2px;}
    .pc-loc{font-size:0.75rem;color:var(--muted);margin-bottom:10px;}
    .pc-skills{display:flex;flex-wrap:wrap;gap:4px;justify-content:center;margin-bottom:11px;}
    .pc-skill{
      padding:2px 8px;border-radius:5px;font-size:0.68rem;font-weight:700;
      background:rgba(232,96,10,0.08);color:var(--orange);border:1px solid rgba(232,96,10,0.16);
    }
    .pc-stars{color:#d97706;font-size:0.88rem;}
    .pc-rating{display:flex;align-items:center;justify-content:center;gap:5px;font-size:0.78rem;color:var(--muted);margin-bottom:13px;}
    .btn-edit{
      display:block;width:100%;padding:8px;border-radius:100px;
      border:1.5px solid var(--border);background:transparent;color:var(--ink2);
      font-family:var(--font-b);font-size:0.8rem;font-weight:600;
      cursor:pointer;text-decoration:none;transition:border-color 0.2s,background 0.2s;
    }
    .btn-edit:hover{border-color:var(--orange);background:rgba(232,96,10,0.04);}

    /* profile complete bar */
    .complete-bar{
      background:var(--white);border:1.5px solid var(--border);
      border-radius:var(--radius);padding:14px 18px;
    }
    .cb-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;}
    .cb-lbl{font-size:0.8rem;font-weight:400;color:var(--ink2);}
    .cb-pct{font-family:var(--font-h);font-size:0.95rem;font-weight:900;color:var(--orange);}
    .cb-bg{height:7px;border-radius:100px;background:var(--cream2);overflow:hidden;margin-bottom:5px;}
    .cb-fill{height:100%;border-radius:100px;background:var(--orange);transition:width 1s ease;}
    .cb-hint{font-size:0.7rem;color:var(--muted);}
    .cb-hint a{color:var(--orange);font-weight:700;text-decoration:none;}

    /* app status */
    .app-status{font-size:0.65rem;font-weight:800;padding:2px 7px;border-radius:100px;white-space:nowrap;}
    .s-pending     {background:var(--yellow-lt);color:var(--yellow);}
    .s-accepted    {background:var(--green-lt);color:var(--green);}
    .s-rejected    {background:var(--red-lt);color:var(--red);}
    .s-shortlisted {background:var(--blue-lt);color:var(--blue);}
    .s-interview   {background:#f3e8ff;color:#7c3aed;}

    .app-item{
      display:flex;align-items:center;justify-content:space-between;
      padding:9px 0;border-bottom:1px solid var(--border);gap:7px;
    }
    .app-item:last-child{border-bottom:none;padding-bottom:0;}
    .app-ttl{
      font-size:0.81rem;font-weight:700;color:var(--ink);margin-bottom:1px;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:145px;
    }
    .app-dt{font-size:0.68rem;color:var(--muted);}

    /* notif item */
    .notif-item{
      display:flex;gap:9px;align-items:flex-start;
      padding:9px 0;border-bottom:1px solid var(--border);
    }
    .notif-item:last-child{border-bottom:none;padding-bottom:0;}
    .n-dot{width:7px;height:7px;border-radius:50%;background:var(--orange);flex-shrink:0;margin-top:5px;}
    .n-dot.read{background:var(--border);}
    .n-msg{font-size:0.79rem;color:var(--ink2);line-height:1.4;}
    .n-time{font-size:0.68rem;color:var(--muted);margin-top:2px;}

    /* skill bars */
    .sk-row{margin-bottom:10px;}
    .sk-row:last-child{margin-bottom:0;}
    .sk-top{display:flex;justify-content:space-between;margin-bottom:4px;}
    .sk-name{font-size:0.8rem;font-weight:700;color:var(--ink2);}
    .sk-pct{font-size:0.72rem;font-weight:800;color:var(--orange);}
    .sk-bg{height:6px;border-radius:100px;background:var(--cream2);overflow:hidden;}
    .sk-fill{height:100%;border-radius:100px;width:0;transition:width 1.2s ease;}

    /* ═══════════════════════════════
       RESPONSIVE
    ═══════════════════════════════ */
    @media(max-width:1060px){
      .two-col{grid-template-columns:1fr;}
      .right-col{display:none;}
      .stats{grid-template-columns:repeat(3,1fr);}
    }
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main{margin-left:0;}
      .content{padding:14px;}
      .stats{grid-template-columns:repeat(2,1fr);}
      .welcome{flex-direction:column;gap:12px;padding:18px;}
      .wr{display:none;}
      .hamburger{display:flex;color:var(--ink);}
      .two-col{grid-template-columns:1fr;}
    }
    @media(max-width:480px){
      .stats{grid-template-columns:1fr 1fr;}
      .wl-btns{flex-direction:column;}
    }

    /* overlay for mobile */
    .sb-overlay{
      display:none;position:fixed;inset:0;
      background:rgba(0,0,0,0.45);z-index:190;
    }
    .sb-overlay.show{display:block;}

    /* ── Compact Browse Jobs card for mobile ── */
@media(max-width:768px){
  .card{padding:14px 14px;}
  .card-head{margin-bottom:10px;}
  .search-bar{padding:3px 3px 3px 10px;margin-bottom:9px;}
  .search-bar input{font-size:0.8rem;}
  .search-bar button{padding:7px 12px;font-size:0.74rem;}
  .chips{gap:6px;margin-bottom:10px;}
  .chip{padding:5px 10px;font-size:0.7rem;min-height:30px;}
  .jobs-list{gap:8px;}
  .no-jobs{padding:26px 14px;}
  .no-jobs-icon{font-size:1.7rem;margin-bottom:6px;}
  .no-jobs h3{font-size:0.92rem;}
}

  </style>
</head>
<body>

<!-- ═══════════════════════════════
     SIDEBAR
═══════════════════════════════ -->
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
    <a href="dashboard.php" class="active">
      <span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard
    </a>
    <a href="browse_jobs.php">
      <span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Browse Jobs
    </a>
    <a href="applications.php">
      <span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Applications
      <?php if ($pending > 0): ?>
        <span class="nav-bdg"><?= $pending ?></span>
      <?php endif; ?>
    </a>
    <a href="saved_jobs.php">
      <span class="nav-ic"><img src="../assets/icons/saved jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Saved Jobs
      <?php if ($saved_count > 0): ?>
        <span class="nav-bdg"><?= $saved_count ?></span>
      <?php endif; ?>
    </a>
    <a href="recommended.php">
      <span class="nav-ic"><img src="../assets/icons/recommend jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Recommended
    </a>

    <div class="nav-section">Connect</div>
    <a href="network.php">
      <span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network
    </a>
    <a href="portfolio.php">
      <span class="nav-ic"><img src="../assets/icons/total count.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Portfolio
    </a>
    <a href="chat.php">
      <span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat
      <?php if ($unread_msgs > 0): ?>
        <span class="nav-bdg"><?= $unread_msgs ?></span>
      <?php endif; ?>
    </a>
    <a href="notifications.php">
      <span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications
      <?php if ($unread_notif > 0): ?>
        <span class="nav-bdg"><?= $unread_notif ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-section">Profile</div>
    <a href="profile.php">
      <span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Profile
    </a>
    <a href="skill_progress.php">
      <span class="nav-ic"><img src="../assets/icons/skill program.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Skill Progress
    </a>
    <a href="ratings.php">
      <span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings
    </a>
    <a href="settings.php">
      <span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings
    </a>

  </nav>

  <div class="sb-logout">
    <a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a>
  </div>
</div>

<!-- mobile overlay -->
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ═══════════════════════════════
     MAIN
═══════════════════════════════ -->
<div class="main">

  <!-- topbar -->
  <div class="topbar">
    <div class="tb-left">
      <div class="hamburger" id="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/dashboard (3).png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb"><strong>Dashboard</strong></span>
    </div>
    <div class="tb-right">
      <a href="chat.php" class="tb-btn" title="Messages">
        <img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;">
        <?php if ($unread_msgs > 0): ?>
          <span class="tb-dot"><?= $unread_msgs ?></span>
        <?php endif; ?>
      </a>
      <a href="notifications.php" class="tb-btn" title="Notifications">
        <img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;">
        <?php if ($unread_notif > 0): ?>
          <span class="tb-dot"><?= $unread_notif ?></span>
        <?php endif; ?>
      </a>
      <a href="profile.php" class="tb-ava" title="My Profile">
        <?php if (!empty($profile['profile_pic'])): ?>
          <img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt="">
        <?php else: ?>
          <?= $avatar_letter ?>
        <?php endif; ?>
      </a>
    </div>
  </div>

  <!-- page content -->
  <div class="content">

    <!-- WELCOME BANNER -->
    <div class="welcome">
      <div class="wl">
        <div class="wl-name">Welcome back, <?= htmlspecialchars($first_name) ?>!</div>
        <div class="wl-sub">
          You have <strong><?= $pending ?> pending</strong> application<?= $pending != 1 ? 's' : '' ?>
          and <strong><?= $accepted ?> accepted</strong> job<?= $accepted != 1 ? 's' : '' ?>.
          <?php if ($shortlisted > 0): ?>
            🎉 <strong><?= $shortlisted ?> shortlisted!</strong>
          <?php endif; ?>
        </div>
        <div class="wl-btns">
          <a href="browse_jobs.php" class="wl-btn wl-btn-w">🔍 Find Jobs</a>
          <a href="applications.php" class="wl-btn wl-btn-o"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">My Applications</a>
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
        <div class="wr-rating">⭐ <?= $avg_rating ?> / 5.0
          <?php if ($total_ratings > 0): ?>
            · <?= $total_ratings ?> review<?= $total_ratings != 1 ? 's' : '' ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- STATS ROW -->
    <div class="stats">
      <a href="applications.php" class="sc" style="text-decoration:none;">
        <div class="sc-top">
          <div class="sc-icon" style="background:rgba(232,96,10,0.1);"><img src="../assets/icons/application.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div>
          <span class="sc-badge" style="background:rgba(232,96,10,0.1);color:var(--orange);">Total</span>
        </div>
        <div class="sc-num"><?= $total_apps ?></div>
        <div class="sc-lbl">Applications Sent</div>
      </a>
      <a href="applications.php?tab=accepted" class="sc" style="text-decoration:none;">
        <div class="sc-top">
          <div class="sc-icon" style="background:var(--green-lt);"><img src="../assets/icons/job accepted.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div>
          <span class="sc-badge" style="background:var(--green-lt);color:var(--green);">Won</span>
        </div>
        <div class="sc-num" style="color:var(--green);"><?= $accepted ?></div>
        <div class="sc-lbl">Jobs Accepted</div>
      </a>
      <a href="applications.php?tab=pending" class="sc" style="text-decoration:none;">
        <div class="sc-top">
          <div class="sc-icon" style="background:var(--yellow-lt);"><img src="../assets/icons/pending.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div>
          <span class="sc-badge" style="background:var(--yellow-lt);color:var(--yellow);">Wait</span>
        </div>
        <div class="sc-num" style="color:var(--yellow);"><?= $pending ?></div>
        <div class="sc-lbl">Pending Review</div>
      </a>
      <a href="saved_jobs.php" class="sc" style="text-decoration:none;">
        <div class="sc-top">
          <div class="sc-icon" style="background:var(--blue-lt);"><img src="../assets/icons/saved jobs.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div>
          <span class="sc-badge" style="background:var(--blue-lt);color:var(--blue);">Saved</span>
        </div>
        <div class="sc-num" style="color:var(--blue);"><?= $saved_count ?></div>
        <div class="sc-lbl">Saved Jobs</div>
      </a>
      <a href="ratings.php" class="sc" style="text-decoration:none;">
        <div class="sc-top">
          <div class="sc-icon" style="background:var(--teal-lt);"><img src="../assets/icons/rating.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div>
          <span class="sc-badge" style="background:var(--teal-lt);color:var(--teal);">Score</span>
        </div>
        <div class="sc-num" style="color:var(--teal);"><?= $avg_rating ?></div>
        <div class="sc-lbl">Avg. Rating</div>
      </a>
    </div>

    <!-- TWO COLUMN -->
    <div class="two-col">

      <!-- LEFT: JOBS -->
      <div>
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">Browse Jobs</div>
              <div class="card-sub">Search and apply for jobs matching your skills</div>
            </div>
            <a href="browse_jobs.php" class="view-all">View All →</a>
          </div>

          <!-- search -->
          <form method="GET" action="">
            <div class="search-bar">
              <span style="font-size:0.95rem;color:var(--muted);">🔎</span>
              <input type="text" name="search"
                     placeholder="Search jobs by title, skill..."
                     value="<?= htmlspecialchars($search) ?>">
              <button type="submit">Search</button>
            </div>

            <!-- category chips -->
            <div class="chips">
              <a href="dashboard.php"
                 class="chip <?= empty($category) && empty($search) ? 'active' : '' ?>">
                🌟 All
              </a>
              <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= $cat['id'] ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>"
                   class="chip <?= $category === $cat['id'] ? 'active' : '' ?>">
                  <?= $cat['icon'] ?> <?= $cat['label'] ?>
                </a>
              <?php endforeach; ?>
            </div>

            <?php if (!empty($category) || !empty($search)): ?>
              <div style="margin-bottom:12px;font-size:0.8rem;color:var(--muted);">
                Results for:
                <?php if (!empty($category)): ?>
                  <strong style="color:var(--orange);"><?= htmlspecialchars($category) ?></strong>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                  &ldquo;<strong><?= htmlspecialchars($search) ?></strong>&rdquo;
                <?php endif; ?>
                &nbsp;&middot;&nbsp;
                <a href="dashboard.php" style="color:var(--orange);font-weight:700;">Clear ✕</a>
              </div>
            <?php endif; ?>
          </form>

          <!-- jobs list -->
          <div class="jobs-list">
            <?php if ($jobs_query && $jobs_query->num_rows > 0):
              $ci = 0;
              while ($job = $jobs_query->fetch_assoc()):
                $is_applied = in_array($job['id'], $applied_ids);
                $is_saved   = in_array($job['id'], $saved_ids);
                $tags = array_filter(array_map('trim', explode(',', $job['skills_required'] ?? '')));
                $col  = $job_colors[$ci++ % count($job_colors)];
                $diff = time() - strtotime($job['created_at']);
                if ($diff < 3600)      $ago = round($diff/60).'m ago';
                elseif ($diff < 86400) $ago = round($diff/3600).'h ago';
                else                   $ago = round($diff/86400).'d ago';
            ?>
              <div class="jc">
                <div class="jc-top">
                  <div class="jc-logo" style="background:<?= $col ?>;">
                    <?= strtoupper(substr($job['client_name'],0,1)) ?>
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div class="jc-title"><?= htmlspecialchars($job['title']) ?></div>
                    <div class="jc-client">
                      By <strong><?= htmlspecialchars($job['client_name']) ?></strong>
                      &nbsp;·&nbsp; 📍 <?= htmlspecialchars($job['client_loc'] ?? 'N/A') ?>
                    </div>
                  </div>
                  <span class="jc-open">🟢 Open</span>
                </div>

                <?php if (!empty($job['description'])): ?>
                  <div class="jc-desc"><?= htmlspecialchars($job['description']) ?></div>
                <?php endif; ?>

                <?php if (!empty($tags)): ?>
                  <div class="jc-tags">
                    <?php foreach (array_slice($tags,0,4) as $t): ?>
                      <span class="jc-tag"><?= htmlspecialchars($t) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="jc-foot">
                  <div class="jc-meta">
                    <?php if (!empty($job['budget'])): ?>
                      <div class="jc-mi">💰 <strong>₹<?= number_format($job['budget']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($job['job_type'])): ?>
                      <div class="jc-mi">⏱ <strong><?= htmlspecialchars($job['job_type']) ?></strong></div>
                    <?php endif; ?>
                    <div class="jc-mi">🕐 <?= $ago ?></div>
                  </div>
                  <div class="jc-acts">
                    <a href="?save=<?= $job['id'] ?>&category=<?= urlencode($category) ?>&search=<?= urlencode($search) ?>"
                       class="btn-save <?= $is_saved ? 'saved' : '' ?>"
                       title="<?= $is_saved ? 'Remove' : 'Save' ?>">
                      <?= $is_saved ? '🔖' : '📌' ?>
                    </a>
                    <?php if ($is_applied): ?>
                      <span class="btn-apply applied">✅ Applied</span>
                    <?php else: ?>
                      <a href="job_detail.php?id=<?= $job['id'] ?>"
                         class="btn-apply">
                        Apply Now →
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>

            <?php else: ?>
              <div class="no-jobs">
                <div class="no-jobs-icon">💼</div>
                <h3>No jobs found</h3>
                <p style="font-size:0.83rem;">Try a different search or category filter.</p>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($jobs_query && $jobs_query->num_rows >= 10): ?>
            <div style="text-align:center;margin-top:16px;">
              <a href="browse_jobs.php"
                 style="color:var(--orange);font-weight:700;font-size:0.83rem;text-decoration:none;">
                View all open jobs →
              </a>
            </div>
          <?php endif; ?>

        </div><!-- /card -->
      </div><!-- /left -->

      <!-- RIGHT COLUMN -->
      <div class="right-col">

        <!-- Profile Completion -->
        <?php if ($complete_pct < 100): ?>
          <div class="complete-bar">
            <div class="cb-top">
              <div class="cb-lbl">🧩 Profile Complete</div>
              <div class="cb-pct"><?= $complete_pct ?>%</div>
            </div>
            <div class="cb-bg">
              <div class="cb-fill" data-w="<?= $complete_pct ?>%" style="width:0;"></div>
            </div>
            <div class="cb-hint">
              <?php
                if (empty($profile['bio']))        echo 'Add bio → ';
                elseif (empty($profile['experience'])) echo 'Add experience → ';
                elseif (empty($profile['phone']))  echo 'Add phone → ';
                else echo 'Almost done → ';
              ?>
              <a href="profile.php">Update now</a>
            </div>
          </div>
        <?php endif; ?>

        <!-- Profile Card -->
        <div class="profile-card">
          <div class="pc-ava">
            <?php if (!empty($profile['profile_pic'])): ?>
              <img src="../uploads/profiles/<?= htmlspecialchars($profile['profile_pic']) ?>" alt="">
            <?php else: ?>
              <?= $avatar_letter ?>
            <?php endif; ?>
          </div>
          <div class="pc-name"><?= htmlspecialchars($user_name) ?></div>
          <div class="pc-loc">📍 <?= htmlspecialchars($profile['location'] ?? 'Location not set') ?></div>
          <?php if (!empty($profile['skills'])): ?>
            <div class="pc-skills">
              <?php foreach (array_slice(array_filter(array_map('trim', explode(',', $profile['skills']))), 0, 4) as $sk): ?>
                <span class="pc-skill"><?= htmlspecialchars($sk) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="pc-rating">
            <span class="pc-stars">
              <?php
                $fs = $avg_rating !== '—' ? floor((float)$avg_rating) : 0;
                for ($i=1;$i<=5;$i++) echo $i<=$fs?'★':'☆';
              ?>
            </span>
            <?= $avg_rating ?> / 5.0
          </div>
          <a href="profile.php" class="btn-edit">✏️ Edit Profile</a>
        </div>

        <!-- My Applications -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-head">
            <div class="card-title"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">My Applications</div>
            <a href="applications.php" class="view-all">All →</a>
          </div>
          <?php if ($recent_apps && $recent_apps->num_rows > 0): ?>
            <?php while ($ap = $recent_apps->fetch_assoc()):
              $s  = strtolower($ap['status']);
              $bc = str_contains($s,'interview') ? 'interview' : $s;
            ?>
              <div class="app-item">
                <div>
                  <div class="app-ttl"><?= htmlspecialchars(mb_strimwidth($ap['title'],0,28,'…')) ?></div>
                  <div class="app-dt"><?= date('d M Y', strtotime($ap['applied_at'])) ?></div>
                </div>
                <span class="app-status s-<?= $bc ?>"><?= ucfirst($ap['status']) ?></span>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p style="text-align:center;color:var(--muted);font-size:0.82rem;padding:12px 0;">
              No applications yet.<br>
              <a href="browse_jobs.php" style="color:var(--orange);font-weight:700;">Browse jobs →</a>
            </p>
          <?php endif; ?>
        </div>

        <!-- Skill Progress -->
        <?php if ($skills_progress && $skills_progress->num_rows > 0): ?>
          <div class="card" style="margin-bottom:0;">
            <div class="card-head">
              <div class="card-title"><img src="../assets/icons/skill program.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">Skill Progress</div>
              <a href="skill_progress.php" class="view-all">Edit →</a>
            </div>
            <?php
              $bc = ['#7C3AED','#7C3AED','#1d4ed8','#7c3aed'];
              $bi = 0;
              while ($sk = $skills_progress->fetch_assoc()):
                $c = $bc[$bi++ % count($bc)];
            ?>
              <div class="sk-row">
                <div class="sk-top">
                  <div class="sk-name"><?= htmlspecialchars($sk['skill_name']) ?></div>
                  <div class="sk-pct" style="color:<?= $c ?>;"><?= $sk['progress'] ?>%</div>
                </div>
                <div class="sk-bg">
                  <div class="sk-fill" data-w="<?= $sk['progress'] ?>%" style="background:<?= $c ?>;"></div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php endif; ?>

        <!-- Notifications -->
        <?php if ($recent_notifs && $recent_notifs->num_rows > 0): ?>
          <div class="card" style="margin-bottom:0;">
            <div class="card-head">
              <div class="card-title"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;margin-right:6px;vertical-align:-4px;">Notifications</div>
              <a href="notifications.php" class="view-all">All →</a>
            </div>
            <?php while ($n = $recent_notifs->fetch_assoc()): ?>
              <div class="notif-item">
                <div class="n-dot <?= $n['is_read'] ? 'read' : '' ?>"></div>
                <div>
                  <div class="n-msg"><?= htmlspecialchars($n['message']) ?></div>
                  <div class="n-time"><?= date('d M, h:i A', strtotime($n['created_at'])) ?></div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php endif; ?>

      </div><!-- /right-col -->
    </div><!-- /two-col -->

  </div><!-- /content -->
</div><!-- /main -->

<script>
// ── Animate bars ──
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    document.querySelectorAll('[data-w]').forEach(el => {
      el.style.width = el.dataset.w;
    });
  }, 250);
});

// ── Mobile sidebar ──
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('overlay').classList.add('show');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('show');
}
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