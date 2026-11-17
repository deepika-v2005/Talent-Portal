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

// ── SEND CONNECTION REQUEST (client initiates to worker) ──
if (isset($_GET['connect'])) {
    $wid = (int)$_GET['connect'];
    $check = $conn->query("SELECT id FROM connections WHERE worker_id=$wid AND client_id=$user_id");
    if ($check->num_rows === 0) {
        $conn->query("INSERT INTO connections (worker_id, client_id, status, created_at) VALUES ($wid, $user_id, 'pending', NOW())");
        $cn = $conn->real_escape_string($user_name);
        $msg = "$cn wants to connect with you!";
        $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at) VALUES ($wid,'connection','$msg',0,NOW())");
    }
    header("Location: network.php?tab=discover&connected=1"); exit();
}

// ── ACCEPT / REJECT ──
if (isset($_GET['accept'])) {
    $cid = (int)$_GET['accept'];
    $conn->query("UPDATE connections SET status='accepted' WHERE id=$cid AND client_id=$user_id");
    $c = $conn->query("SELECT worker_id FROM connections WHERE id=$cid")->fetch_assoc();
    if ($c) {
        $msg=$conn->real_escape_string("Your connection request to ".htmlspecialchars($user_name)." was accepted! You can now receive job offers.");
        $conn->query("INSERT INTO notifications (user_id,type,message,is_read,created_at) VALUES ({$c['worker_id']},'connection','$msg',0,NOW())");
    }
    header("Location: network.php?tab=connected"); exit();
}
if (isset($_GET['reject'])) {
    $cid = (int)$_GET['reject'];
    $conn->query("UPDATE connections SET status='rejected' WHERE id=$cid AND client_id=$user_id");
    header("Location: network.php"); exit();
}

// ── SEND JOB OFFER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_offer'])) {
    $worker_id_offer = (int)$_POST['worker_id'];
    $job_id_offer    = (int)$_POST['job_id'];
    $is_connected = $conn->query("SELECT id FROM connections WHERE worker_id=$worker_id_offer AND client_id=$user_id AND status='accepted'")->num_rows;
    if ($is_connected && $job_id_offer) {
        $already = $conn->query("SELECT id FROM applications WHERE worker_id=$worker_id_offer AND job_id=$job_id_offer")->num_rows;
        if (!$already) {
            $conn->query("INSERT INTO applications (worker_id, job_id, status, cover_note, applied_at) VALUES ($worker_id_offer, $job_id_offer, 'pending', 'Direct offer from client', NOW())");
        }
        $job_title = $conn->query("SELECT title FROM jobs WHERE id=$job_id_offer AND client_id=$user_id")->fetch_assoc()['title'] ?? 'a job';
        $jt = $conn->real_escape_string($job_title);
        $cn = $conn->real_escape_string($user_name);
        $msg = "Job Offer from $cn: \"$jt\" – Check your applications!";
        $conn->query("INSERT INTO notifications (user_id,type,message,is_read,link,created_at) VALUES ($worker_id_offer,'job_alert','$msg',0,'../worker/applications.php',NOW())");
    }
    header("Location: network.php?tab=connected&offered=1"); exit();
}

$tab = $_GET['tab'] ?? 'discover';

// ── ALL REGISTERED USERS (DISCOVER) ──
$all_users = $conn->query("
    SELECT u.id, u.name, u.location, u.skills, u.profile_pic, u.bio, u.role,
           (SELECT status FROM connections WHERE worker_id=u.id AND client_id=$user_id) AS conn_status,
           (SELECT id FROM connections WHERE worker_id=u.id AND client_id=$user_id) AS conn_id
    FROM users u
    WHERE u.id != $user_id AND u.role IN ('worker','client')
    ORDER BY u.name ASC
");
$all_count = $all_users ? $all_users->num_rows : 0;

// ── PENDING REQUESTS ──
$requests = $conn->query("SELECT c.*,u.name,u.location,u.skills,u.profile_pic,u.bio,u.id as worker_id FROM connections c JOIN users u ON u.id=c.worker_id WHERE c.client_id=$user_id AND c.status='pending' ORDER BY c.created_at DESC");
$req_count = $requests ? $requests->num_rows : 0;

// ── CONNECTED ──
$connected = $conn->query("SELECT c.*,u.name,u.location,u.skills,u.profile_pic,u.bio,u.id as worker_id FROM connections c JOIN users u ON u.id=c.worker_id WHERE c.client_id=$user_id AND c.status='accepted' ORDER BY c.created_at DESC");
$con_count = $connected ? $connected->num_rows : 0;

// ── MY OPEN JOBS (for offer modal) ──
$my_jobs = $conn->query("SELECT id, title FROM jobs WHERE client_id=$user_id AND status='open' ORDER BY created_at DESC");

$conn->close();
$colors=['#7C3AED','#1d4ed8','#db2777','#d97706','#16a34a','#0891b2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Network – SkillConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--purple:#7C3AED;--purple-lt:#ede9fe;--blue:#1d4ed8;--blue-lt:#eff6ff;--border:#DDD6FE;--green:#16a34a;--green-lt:#f0fdf4;--red:#dc2626;--red-lt:#fef2f2;--sidebar:240px;--shadow:0 2px 12px rgba(11,31,58,.07);--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;--radius:14px;}
body{background:var(--cream);font-family:var(--font-b);color:var(--ink);font-weight:400;min-height:100vh;display:flex;overflow-x:hidden;}
.sidebar{width:var(--sidebar);height:100vh;background:#0B1F3A;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
.sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);}.sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:400;color:#fff;text-decoration:none;}.sb-logo span{color:#9F67FA;}
.sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;}
.sb-ava{width:40px;height:40px;border-radius:11px;background:var(--purple);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:400;overflow:hidden;flex-shrink:0;}.sb-ava img{width:40px;height:40px;object-fit:cover;border-radius:11px;}
.sb-uname{font-size:.86rem;font-weight:400;color:#fff;}.sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);}
.sb-nav{flex:1;padding:10px 0;}.nav-section{font-size:.62rem;font-weight:400;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
.sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;}
.sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}.sb-nav a.active{color:#fff;background:rgba(124,58,237,.2);border-left-color:var(--purple);}
.nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}.nav-bdg{margin-left:auto;background:var(--purple);color:#fff;font-size:.6rem;font-weight:400;padding:2px 6px;border-radius:100px;}
.sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);}.sb-logout a{display:flex;align-items:center;justify-content:center;gap:9px;padding:10px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;}.sb-logout a img{filter:brightness(0) invert(1);}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-width:0;}
.topbar{height:60px;padding:0 26px;background:rgba(245,243,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;gap:10px;}
.tb-left{display:flex;align-items:center;gap:9px;min-width:0;}.hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;}
.tb-crumb{font-size:.85rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}.tb-crumb strong{color:var(--ink);font-weight:400;}.tb-right{display:flex;align-items:center;gap:9px;flex-shrink:0;}
.tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;flex-shrink:0;}.tb-btn:hover{background:var(--cream2);}
.tb-dot{position:absolute;top:-3px;right:-3px;background:var(--purple);color:#fff;font-size:.56rem;font-weight:400;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--cream);}
.tb-ava{width:35px;height:35px;border-radius:9px;background:var(--purple);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:400;text-decoration:none;overflow:hidden;flex-shrink:0;}.tb-ava img{width:35px;height:35px;object-fit:cover;}
.content{padding:24px 26px;flex:1;min-width:0;}.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}.sb-overlay.show{display:block;}
.page-title{font-family:var(--font-h);font-size:1.5rem;font-weight:400;margin-bottom:6px;}
.page-sub{font-size:.84rem;color:var(--muted);margin-bottom:22px;}
.tabs{display:flex;gap:5px;background:var(--white);border:1px solid var(--border);border-radius:100px;padding:4px;width:fit-content;max-width:100%;margin-bottom:24px;flex-wrap:wrap;}
.tab-btn{padding:8px 18px;border-radius:100px;border:none;background:transparent;font-family:var(--font-b);font-size:.82rem;font-weight:400;color:var(--muted);cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;}
.tab-btn.active{background:var(--purple);color:#fff;}.tab-btn:hover:not(.active){background:var(--cream2);color:var(--ink);}
.tab-btn img{width:16px;height:16px;object-fit:contain;margin-right:5px;border-radius:3px;flex-shrink:0;}
/* Search bar */
.search-wrap{margin-bottom:20px;position:relative;}.search-input{width:100%;max-width:420px;padding:10px 16px 10px 40px;border:1.5px solid var(--border);border-radius:100px;font-family:var(--font-b);font-size:.87rem;background:var(--white);outline:none;color:var(--ink);}.search-input:focus{border-color:var(--purple);}.search-ic{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:.95rem;pointer-events:none;}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;}
.card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);transition:transform .18s;min-width:0;height:100%;display:flex;flex-direction:column;}.card:hover{transform:translateY(-2px);}
.card-accent{height:4px;flex-shrink:0;}
.card-body{padding:18px;min-width:0;flex:1;display:flex;flex-direction:column;}
.c-top{display:flex;gap:12px;align-items:center;margin-bottom:10px;flex-wrap:wrap;}
.c-top div[style*="flex:1"]{min-width:0;}
.c-ava{width:48px;height:48px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1.1rem;font-weight:400;color:#fff;overflow:hidden;}.c-ava img{width:48px;height:48px;object-fit:cover;border-radius:50%;}
.c-name{font-family:var(--font-h);font-size:.95rem;font-weight:400;overflow-wrap:break-word;word-break:break-word;}.c-name a{color:var(--ink);text-decoration:none;}.c-name a:hover{color:var(--purple);}
.c-loc{font-size:.74rem;color:var(--muted);overflow-wrap:break-word;}
.c-role-badge{display:inline-flex;align-items:center;gap:3px;font-size:.62rem;font-weight:400;padding:3px 10px;border-radius:100px;margin-top:4px;transition:transform .15s;}
.c-role-badge:hover{transform:scale(1.05);}
.role-worker{background:linear-gradient(135deg,#059669,#10b981);color:#fff;box-shadow:0 2px 8px rgba(5,150,105,.35);letter-spacing:.4px;}
.role-client{background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;box-shadow:0 2px 8px rgba(217,119,6,.35);letter-spacing:.4px;}
.c-bio{font-size:.78rem;color:var(--muted);line-height:1.5;margin-bottom:10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.c-skills{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;}
.c-skill{font-size:.68rem;font-weight:400;padding:2px 8px;border-radius:5px;background:var(--purple-lt);color:var(--purple);}
.c-actions{display:flex;gap:7px;flex-wrap:wrap;padding-top:12px;border-top:1px solid var(--border);margin-top:auto;}
.btn{padding:7px 15px;border-radius:100px;font-family:var(--font-b);font-size:.75rem;font-weight:400;text-decoration:none;border:none;cursor:pointer;transition:all .18s;display:inline-flex;align-items:center;justify-content:center;gap:4px;}
.btn-connect{background:var(--purple);color:#fff;border:1.5px solid var(--purple);}.btn-connect:hover{background:#6D28D9;}
.btn-accept{background:var(--green-lt);color:var(--green);border:1.5px solid var(--green);}.btn-accept:hover{background:rgba(22,163,74,.2);}
.btn-reject{background:var(--red-lt);color:var(--red);border:1.5px solid var(--red);}.btn-reject:hover{background:rgba(220,38,38,.2);}
.btn-chat{background:var(--purple-lt);color:var(--purple);border:1.5px solid var(--purple);}.btn-chat:hover{background:var(--purple);color:#fff;}
.btn-view{background:var(--blue-lt);color:var(--blue);border:1.5px solid var(--blue);}.btn-view:hover{background:var(--blue);color:#fff;}
.btn-offer{background:var(--purple);color:#fff;border:1.5px solid var(--purple);}.btn-offer:hover{background:#6D28D9;}
.btn-pending{background:#fef3c7;color:#d97706;border:1.5px solid #d97706;cursor:default;}
.btn-connected{background:var(--green-lt);color:var(--green);border:1.5px solid var(--green);cursor:default;}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted);background:var(--white);border:1px solid var(--border);border-radius:var(--radius);}
.empty-state .ei{font-size:3rem;margin-bottom:12px;}.empty-state h3{font-family:var(--font-h);font-size:1.1rem;color:var(--ink2);margin-bottom:6px;}
.stats-bar{display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
.stat-chip{background:var(--white);border:1px solid var(--border);border-radius:100px;padding:7px 16px;font-size:.8rem;font-weight:400;color:var(--ink2);display:flex;align-items:center;gap:6px;white-space:nowrap;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px;}
.modal-backdrop.open{display:flex;}
.modal{background:var(--white);border-radius:var(--radius);padding:28px;width:90%;max-width:480px;box-shadow:0 8px 40px rgba(0,0,0,.18);max-height:90vh;overflow-y:auto;}
.modal-title{font-family:var(--font-h);font-size:1.2rem;font-weight:400;margin-bottom:18px;}
.field-label{display:block;font-size:.78rem;font-weight:400;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;}
.field-select{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-family:var(--font-b);font-size:.88rem;background:var(--white);outline:none;margin-bottom:16px;}
.field-select:focus{border-color:var(--purple);}
.toast{position:fixed;bottom:24px;right:24px;left:24px;max-width:340px;margin-left:auto;background:var(--purple);color:#fff;padding:12px 22px;border-radius:100px;font-weight:400;font-size:.86rem;box-shadow:var(--shadow);z-index:9999;animation:fadeUp .3s ease;text-align:center;pointer-events:none;}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}

/* ============ RESPONSIVE ============ */
@media(max-width:768px){
  .sidebar{transform:translateX(-240px);}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0;}
  .content{padding:14px;}
  .hamburger{display:flex;}
  .topbar{padding:0 14px;}
  .tb-crumb{max-width:130px;}
  .tabs{gap:3px;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;flex-wrap:nowrap;scrollbar-width:none;}
  .tabs::-webkit-scrollbar{display:none;}
  .tab-btn{padding:7px 12px;font-size:.77rem;white-space:nowrap;}
  .grid{grid-template-columns:1fr;}
  .search-input{max-width:100%;}
  .stats-bar{gap:8px;}
  .stat-chip{padding:6px 12px;font-size:.75rem;flex:1 1 auto;justify-content:center;}
}

@media(max-width:480px){
  .content{padding:10px;}
  .card-body{padding:14px;}
  .c-ava{width:40px;height:40px;font-size:.95rem;}
  .c-ava img{width:40px;height:40px;}
  .c-actions{gap:6px;}
  .c-actions .btn{flex:1 1 calc(50% - 6px);text-align:center;}
  .page-title{font-size:1.25rem;}
  .page-title img{width:34px !important;height:34px !important;}
  .stats-bar{gap:6px;}
  .stat-chip{padding:6px 10px;font-size:.7rem;}
  .modal{padding:18px;width:94%;}
  .modal-title{font-size:1.05rem;}
  /* prevent iOS auto-zoom on focus by keeping inputs >=16px */
  .search-input,.field-select{font-size:16px;}
  .toast{left:12px;right:12px;bottom:12px;max-width:none;font-size:.8rem;padding:10px 16px;}
  .c-top{gap:9px;}
  .c-name{font-size:.88rem;}
  .c-loc{font-size:.7rem;}
  .modal form div[style*="display:flex"]{flex-direction:column;}
  .modal form div[style*="display:flex"] button{width:100%;}
}

@media(max-width:360px){
  .tb-crumb{display:none;}
  .c-actions .btn{flex:1 1 100%;}
  .stat-chip{flex:1 1 calc(50% - 6px);}
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
body.dark-mode .page-sub{color:#94A3B8;}
body.dark-mode .tabs{background:#111827;border:1px solid #1E293B;}
body.dark-mode .tab-btn{color:#94A3B8;}
body.dark-mode .tab-btn:hover:not(.active){background:#1E293B;color:#E2E8F0;}
body.dark-mode .search-input{border:1.5px solid #1E293B;background:#111827;color:#E2E8F0;}
body.dark-mode .card{background:#111827;border:1.5px solid #1E293B;}
body.dark-mode .c-name a{color:#E2E8F0;}
body.dark-mode .c-loc{color:#94A3B8;}
body.dark-mode .c-bio{color:#94A3B8;}
body.dark-mode .c-skill{background:rgba(124,58,237,.18);}
body.dark-mode .c-actions{border-top:1px solid #1E293B;}
body.dark-mode .btn-accept{background:rgba(22,163,74,.14);}
body.dark-mode .btn-reject{background:rgba(220,38,38,.14);}
body.dark-mode .btn-chat{background:rgba(124,58,237,.18);}
body.dark-mode .btn-view{background:rgba(29,78,216,.16);}
body.dark-mode .btn-pending{background:rgba(217,119,6,.16);}
body.dark-mode .btn-connected{background:rgba(22,163,74,.14);}
body.dark-mode .empty-state{color:#94A3B8;background:#111827;border:1px solid #1E293B;}
body.dark-mode .empty-state h3{color:#CBD5E1;}
body.dark-mode .stat-chip{background:#111827;border:1px solid #1E293B;color:#CBD5E1;}
body.dark-mode .modal{background:#111827;}
body.dark-mode .field-label{color:#94A3B8;}
body.dark-mode .field-select{border:1.5px solid #1E293B;background:#111827;}
</style>
</head>
<body>
<script>(function(){try{var m=document.cookie.match(/(?:^|; )dark_mode=([^;]*)/);if(m&&m[1]==="1"){document.body.classList.add("dark-mode");}}catch(e){}})();</script>
<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}

// ── TAB SWITCHING (previously missing — this is why tab clicks did nothing) ──
function switchTab(tab){
  document.querySelectorAll('.tab-btn[data-tab]').forEach(function(b){
    b.classList.toggle('active', b.getAttribute('data-tab') === tab);
  });
  ['discover','requests','connected'].forEach(function(t){
    var el = document.getElementById('tab-'+t);
    if(el) el.style.display = (t===tab) ? 'block' : 'none';
  });
  var url = new URL(window.location);
  url.searchParams.set('tab', tab);
  window.history.replaceState({}, '', url);
}

// ── SEARCH FILTER (previously missing) ──
function filterMembers(){
  var input = document.getElementById('memberSearch');
  if(!input) return;
  var q = input.value.trim().toLowerCase();
  document.querySelectorAll('.member-card').forEach(function(card){
    var name = card.dataset.name || '';
    var loc = card.dataset.loc || '';
    var skills = card.dataset.skills || '';
    var match = !q || name.includes(q) || loc.includes(q) || skills.includes(q);
    card.style.display = match ? '' : 'none';
  });
}

// ── JOB OFFER MODAL (previously missing) ──
function openOfferModal(workerId, workerName){
  document.getElementById('offer-worker-id').value = workerId;
  document.getElementById('offer-worker-name').textContent = workerName;
  document.getElementById('offer-modal').classList.add('open');
}
function closeOfferModal(){
  document.getElementById('offer-modal').classList.remove('open');
}
// Close modal on backdrop click
document.addEventListener('DOMContentLoaded', function(){
  var backdrop = document.getElementById('offer-modal');
  if(backdrop){
    backdrop.addEventListener('click', function(e){
      if(e.target === backdrop) closeOfferModal();
    });
  }
});
</script>

<?php if(isset($_GET['offered'])): ?>
<div class="toast" id="toast">Job offer sent!</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},3500);</script>
<?php endif;?>
<?php if(isset($_GET['connected'])): ?>
<div class="toast" id="toast">Connection request sent!</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},3500);</script>
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
    <a href="feed.php"><span class="nav-ic"><img src="../assets/icons/explore.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Explore</a>
    <a href="post_job.php"><span class="nav-ic"><img src="../assets/icons/request.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Service Request</a>
    <a href="manage_jobs.php"><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Jobs</a>
    <a href="applicants.php"><span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Applicants<?php if($pending_apps>0): ?><span class="nav-bdg"><?=$pending_apps?></span><?php endif;?></a>
    
    <a href="hired.php"><span class="nav-ic"><img src="../assets/icons/job accepted.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Hired</a>
    <div class="nav-section">Connect</div>
    <a href="network.php" class="active"><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network<?php if($req_count>0): ?><span class="nav-bdg"><?=$req_count?></span><?php endif;?></a>
    <a href="chat.php"><span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat<?php if($unread_msgs>0): ?><span class="nav-bdg"><?=$unread_msgs?></span><?php endif;?></a>
    <a href="notifications.php"><span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications<?php if($unread_notif>0): ?><span class="nav-bdg"><?=$unread_notif?></span><?php endif;?></a>
    <div class="nav-section">Account</div>
    <a href="profile.php"><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Company Profile</a>
    <a href="ratings.php"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php"><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout"><a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a></div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <div class="tb-left">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/network.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <span class="tb-crumb">Dashboard / <strong>Network</strong></span>
    </div>
    <div class="tb-right">
      <a href="chat.php" class="tb-btn"><img src="../assets/icons/chat.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_msgs>0): ?><span class="tb-dot"><?=$unread_msgs?></span><?php endif;?></a>
      <a href="notifications.php" class="tb-btn"><img src="../assets/icons/notifications.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;"><?php if($unread_notif>0): ?><span class="tb-dot"><?=$unread_notif?></span><?php endif;?></a>
      <a href="profile.php" class="tb-ava"><?php if(!empty($profile['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($profile['profile_pic'])?>" alt=""><?php else: ?><?=$avatar_letter?><?php endif;?></a>
    </div>
  </div>

  <div class="content">
    <div class="page-title" style="display:flex;align-items:center;gap:8px;"><img src="../assets/icons/connect.png" alt="" style="width:45px;height:45px;object-fit:contain;"> Network</div>
    <div class="page-sub">Browse everyone on SkillConnect — workers, clients, and job seekers.</div>

    <div class="stats-bar">
      <div class="stat-chip"><?=$all_count?> Members</div>
      <div class="stat-chip"><?=$con_count?> Connected</div>
      <div class="stat-chip"><?=$req_count?> Requests</div>
    </div>

    <div class="tabs">
      <button class="tab-btn <?=$tab==='discover'?'active':''?>" data-tab="discover" onclick="switchTab('discover')"><img src="../assets/icons/all members.png" alt="">All Members (<?=$all_count?>)</button>
      <button class="tab-btn <?=$tab==='requests'?'active':''?>" data-tab="requests" onclick="switchTab('requests')">
        <img src="../assets/icons/request.png" alt="">Requests <?php if($req_count>0): ?><span style="background:#d97706;color:#fff;font-size:.6rem;padding:1px 6px;border-radius:100px;margin-left:2px;"><?=$req_count?></span><?php endif;?>
      </button>
      <button class="tab-btn <?=$tab==='connected'?'active':''?>" data-tab="connected" onclick="switchTab('connected')"><img src="../assets/icons/connect.png" alt="">Connected (<?=$con_count?>)</button>
    </div>

    <!-- ── DISCOVER ALL USERS TAB ── -->
    <div id="tab-discover" style="display:<?=$tab==='discover'?'block':'none'?>;">
      <div class="search-wrap">
        <span class="search-ic">🔍</span>
        <input type="text" class="search-input" id="memberSearch" placeholder="Search by name, location, or skills..." oninput="filterMembers()">
      </div>
      <?php if($all_users && $all_users->num_rows > 0):
        $ai = 0; $rows = [];
        while($r = $all_users->fetch_assoc()) $rows[] = $r;
      ?>
      <div class="grid" id="memberGrid">
        <?php foreach($rows as $r):
          $c = $colors[$ai++ % count($colors)];
          $skills = array_filter(array_map('trim', explode(',', $r['skills'] ?? '')));
          $conn_st = $r['conn_status'];
          $is_worker = ($r['role'] === 'worker');
        ?>
        <div class="card member-card" data-name="<?=strtolower(htmlspecialchars($r['name']))?>" data-loc="<?=strtolower(htmlspecialchars($r['location'] ?? ''))?>" data-skills="<?=strtolower(htmlspecialchars($r['skills'] ?? ''))?>">
          <div class="card-accent" style="background:linear-gradient(90deg,<?=$c?>,#7C3AED);"></div>
          <div class="card-body">
            <div class="c-top">
              <div class="c-ava" style="background:<?=$c?>;">
                <?php if(!empty($r['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($r['profile_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($r['name'],0,1))?><?php endif;?>
              </div>
              <div style="flex:1;">
                <div class="c-name"><a href="<?=$is_worker?'applicant_profile.php':'#'?>?id=<?=$r['id']?>"><?=htmlspecialchars($r['name'])?></a></div>
                <div class="c-loc">📍 <?=htmlspecialchars($r['location'] ?? 'N/A')?></div>
                <span class="c-role-badge <?=$is_worker?'role-worker':'role-client'?>"><?=$is_worker?'👷 Service Provider':'🏢 Client'?></span>
              </div>
            </div>
            <?php if(!empty($r['bio'])): ?><div class="c-bio"><?=htmlspecialchars($r['bio'])?></div><?php endif;?>
            <?php if(!empty($skills)): ?>
              <div class="c-skills"><?php foreach(array_slice($skills,0,3) as $sk): ?><span class="c-skill"><?=htmlspecialchars($sk)?></span><?php endforeach;?></div>
            <?php endif;?>
            <div class="c-actions">
              <?php if($is_worker): ?>
                <a href="applicant_profile.php?id=<?=$r['id']?>" class="btn btn-view">👤 Profile</a>
                <?php if($conn_st === 'accepted'): ?>
                  <span class="btn btn-connected">✅ Connected</span>
                  <a href="chat.php?with=<?=$r['id']?>" class="btn btn-chat">💬 Chat</a>
                <?php elseif($conn_st === 'pending'): ?>
                  <span class="btn btn-pending">⏳ Pending</span>
                <?php else: ?>
                  <a href="?connect=<?=$r['id']?>&tab=discover" class="btn btn-connect">➕ Connect</a>
                <?php endif;?>
              <?php else: ?>
                <span class="btn btn-view" style="cursor:default;">🏢 Client</span>
              <?php endif;?>
            </div>
          </div>
        </div>
        <?php endforeach;?>
      </div>
      <?php else: ?>
        <div class="empty-state"><div class="ei">👥</div><h3>No members yet</h3><p>No other users registered on the platform yet.</p></div>
      <?php endif;?>
    </div>

    <!-- ── REQUESTS TAB ── -->
    <div id="tab-requests" style="display:<?=$tab==='requests'?'block':'none'?>;">
      <?php if($requests && $requests->num_rows > 0):
        $ai = 0;
        while($r = $requests->fetch_assoc()):
          $c = $colors[$ai++ % count($colors)];
          $skills = array_filter(array_map('trim', explode(',', $r['skills'] ?? '')));
      ?>
        <div class="card" style="margin-bottom:14px;">
          <div class="card-accent" style="background:<?=$c?>;"></div>
          <div class="card-body">
            <div class="c-top">
              <div class="c-ava" style="background:<?=$c?>;">
                <?php if(!empty($r['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($r['profile_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($r['name'],0,1))?><?php endif;?>
              </div>
              <div style="flex:1;">
                <div class="c-name"><a href="applicant_profile.php?id=<?=$r['worker_id']?>"><?=htmlspecialchars($r['name'])?></a></div>
                <div class="c-loc">📍 <?=htmlspecialchars($r['location'] ?? 'N/A')?> · <?=date('d M Y', strtotime($r['created_at']))?></div>
              </div>
              <span style="font-size:.65rem;font-weight:400;padding:2px 9px;border-radius:100px;background:#fef3c7;color:#d97706;">⏳ Pending</span>
            </div>
            <?php if(!empty($r['bio'])): ?><div class="c-bio"><?=htmlspecialchars($r['bio'])?></div><?php endif;?>
            <?php if(!empty($skills)): ?>
              <div class="c-skills"><?php foreach(array_slice($skills,0,4) as $sk): ?><span class="c-skill"><?=htmlspecialchars($sk)?></span><?php endforeach;?></div>
            <?php endif;?>
            <div class="c-actions">
              <a href="?accept=<?=$r['id']?>" class="btn btn-accept">✅ Accept</a>
              <a href="?reject=<?=$r['id']?>" class="btn btn-reject" onclick="return confirm('Reject this request?')">❌ Reject</a>
              <a href="applicant_profile.php?id=<?=$r['worker_id']?>" class="btn btn-view">👤 Profile</a>
            </div>
          </div>
        </div>
      <?php endwhile;
      else: ?>
        <div class="empty-state"><div class="ei">📥</div><h3>No pending requests</h3><p>Workers will send you connection requests. Accept them to message and send job offers.</p></div>
      <?php endif;?>
    </div>

    <!-- ── CONNECTED TAB ── -->
    <div id="tab-connected" style="display:<?=$tab==='connected'?'block':'none'?>;">
      <?php if($connected && $connected->num_rows > 0): ?>
        <div style="background:var(--green-lt);border:1px solid var(--green);border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;font-size:.84rem;color:#166534;font-weight:400;">
          💡 Send direct job offers to your connected workers!
        </div>
        <div class="grid">
        <?php $ai = 0;
          while($r = $connected->fetch_assoc()):
            $c = $colors[$ai++ % count($colors)];
            $skills = array_filter(array_map('trim', explode(',', $r['skills'] ?? '')));
        ?>
          <div class="card">
            <div class="card-accent" style="background:linear-gradient(90deg,<?=$c?>,#7C3AED);"></div>
            <div class="card-body">
              <div class="c-top">
                <div class="c-ava" style="background:<?=$c?>;">
                  <?php if(!empty($r['profile_pic'])): ?><img src="../uploads/profiles/<?=htmlspecialchars($r['profile_pic'])?>" alt=""><?php else: ?><?=strtoupper(substr($r['name'],0,1))?><?php endif;?>
                </div>
                <div style="flex:1;">
                  <div class="c-name"><a href="applicant_profile.php?id=<?=$r['worker_id']?>"><?=htmlspecialchars($r['name'])?></a></div>
                  <div class="c-loc">📍 <?=htmlspecialchars($r['location'] ?? 'N/A')?></div>
                </div>
                <span style="font-size:.65rem;font-weight:400;padding:2px 9px;border-radius:100px;background:var(--green-lt);color:var(--green);">✅ Connected</span>
              </div>
              <?php if(!empty($r['bio'])): ?><div class="c-bio"><?=htmlspecialchars($r['bio'])?></div><?php endif;?>
              <?php if(!empty($skills)): ?>
                <div class="c-skills"><?php foreach(array_slice($skills,0,4) as $sk): ?><span class="c-skill"><?=htmlspecialchars($sk)?></span><?php endforeach;?></div>
              <?php endif;?>
              <div class="c-actions">
                <a href="chat.php?with=<?=$r['worker_id']?>" class="btn btn-chat">💬 Message</a>
                <a href="applicant_profile.php?id=<?=$r['worker_id']?>" class="btn btn-view">👤 Profile</a>
                <button class="btn btn-offer" onclick="openOfferModal(<?=$r['worker_id']?>, '<?=addslashes(htmlspecialchars($r['name']))?>')">🎯 Send Offer</button>
              </div>
            </div>
          </div>
        <?php endwhile;?>
        </div>
      <?php else: ?>
        <div class="empty-state"><div class="ei">🔗</div><h3>No connections yet</h3><p>Accept requests from workers, or explore <strong>All Members</strong> tab to find and connect.</p></div>
      <?php endif;?>
    </div>
  </div>
</div>

<!-- ── OFFER MODAL ── -->
<div class="modal-backdrop" id="offer-modal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
      <div class="modal-title">🎯 Send Job Offer</div>
      <button onclick="closeOfferModal()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--muted);">✕</button>
    </div>
    <div style="background:var(--green-lt);border:1px solid var(--green);border-radius:10px;padding:12px 14px;font-size:.82rem;color:#166534;margin-bottom:16px;font-weight:400;">Sending offer to <strong id="offer-worker-name"></strong></div>
    <form method="POST">
      <input type="hidden" name="worker_id" id="offer-worker-id">
      <label class="field-label">Select Job</label>
      <select name="job_id" class="field-select" required>
        <option value="">-- Choose an open job --</option>
        <?php if($my_jobs && $my_jobs->num_rows > 0):
          while($j = $my_jobs->fetch_assoc()): ?>
          <option value="<?=$j['id']?>"><?=htmlspecialchars($j['title'])?></option>
        <?php endwhile; endif; ?>
      </select>
      <div style="display:flex;gap:10px;">
        <button type="submit" name="send_offer" style="flex:1;padding:11px;background:var(--purple);color:#fff;border:none;border-radius:100px;font-family:var(--font-b);font-size:.9rem;font-weight:400;cursor:pointer;">🎯 Send Offer</button>
        <button type="button" onclick="closeOfferModal()" style="padding:11px 20px;background:var(--cream2);color:var(--ink2);border:1px solid var(--border);border-radius:100px;font-family:var(--font-b);font-size:.9rem;font-weight:400;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>


</body>
</html>