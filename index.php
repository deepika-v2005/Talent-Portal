<?php
session_start();

// Check if user is already logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_name    = $_SESSION['user_name'] ?? '';
$user_role    = $_SESSION['user_role'] ?? '';

// Dashboard link based on role
function getDashLink($role) {
    if ($role === 'client') return 'client/dashboard.php';
    if ($role === 'admin')  return 'admin/dashboard.php';
    return 'worker/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SkillConnect – Find Real Talent, Not Just Degrees</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    :root {
      --cream:    #faf7f2;
      --cream2:   #f3ede3;
      --white:    #ffffff;
      --ink:      #0B1F3A;
      --ink2:     #1E3A5F;
      --muted:    #52525C;
      --navy:     #14335E;
      --navy2:    #1E4D8C;
      --purple:   #6D28D9;
      --purple2:  #8B5CF6;
      --teal:     #7C3AED;
      --teal-lt:  #ede9fe;
      --border:   #e4ddd1;
      --shadow:   0 4px 24px rgba(11,31,58,0.08);
      --shadow-lg:0 16px 48px rgba(11,31,58,0.13);
      --font-h:   'Poppins', sans-serif;
      --font-b:   'Poppins', sans-serif;
      --radius:   16px;
    }

    html { scroll-behavior: smooth; }
    body {
      background: var(--cream);
      color: var(--ink);
      font-family: var(--font-b);
      font-size: 16px;
      line-height: 1.65;
      overflow-x: hidden;
    }

    /* NAVBAR */
    nav {
      position: fixed; top:0; left:0; right:0; z-index:200;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 6%; height: 68px;
      background: rgba(250,247,242,0.92);
      backdrop-filter: blur(18px);
      border-bottom: 1px solid var(--border);
      transition: box-shadow 0.3s;
    }
    nav.scrolled { box-shadow: 0 2px 20px rgba(11,31,58,0.1); }

    .logo { font-family:var(--font-h); font-size:1.45rem; font-weight:800; color:var(--navy); text-decoration:none; letter-spacing:-0.5px; }
    .logo span { color:var(--purple); font-weight:800; }

    .nav-links { display:flex; gap:32px; list-style:none; }
    .nav-links a { font-size:0.88rem; font-weight:400; color:var(--muted); text-decoration:none; transition:color 0.2s; }
    .nav-links a:hover { color:var(--ink); }

    .nav-btns { display:flex; gap:10px; align-items:center; }

    .nav-toggle{
      display:none; width:38px; height:38px; border-radius:9px;
      background:var(--cream2); border:1.5px solid var(--border);
      align-items:center; justify-content:center; cursor:pointer;
      font-size:1.2rem; flex-shrink:0;
    }

    .btn-outline {
      padding:8px 20px; border-radius:100px;
      border:2px solid var(--navy); background:transparent;
      color:var(--navy); font-family:var(--font-b); font-size:0.87rem; font-weight:500;
      cursor:pointer; text-decoration:none; transition: border-color 0.2s, color 0.2s, transform 0.15s;
    }
    .btn-outline:hover { border-color:var(--purple); color:var(--purple); background:transparent; transform:translateY(-1px); }

    .btn-fill {
      padding:8px 22px; border-radius:100px;
      background:var(--ink); color:#fff;
      font-family:var(--font-b); font-size:0.87rem; font-weight:400;
      cursor:pointer; text-decoration:none; border:none;
      transition:background 0.2s, transform 0.15s;
      box-shadow:0 4px 14px rgba(20,51,94,0.3);
    }
    .btn-fill:hover { background:var(--navy); }

    /* Logged in user pill */
    .user-pill {
      display:flex; align-items:center; gap:8px;
      background:var(--cream2); border:1.5px solid var(--border);
      border-radius:100px; padding:5px 14px 5px 8px;
      font-size:0.85rem; font-weight:400; color:var(--ink2);
      text-decoration:none; transition:border-color 0.2s;
    }
    .user-pill:hover { border-color:var(--navy); }
    .user-avatar {
      width:28px; height:28px; border-radius:50%;
      background:var(--navy); color:#fff;
      display:flex; align-items:center; justify-content:center;
      font-size:0.8rem; font-weight:400; flex-shrink:0;
    }
    .btn-logout {
      padding:8px 18px; border-radius:100px;
      border:1.5px solid #fecaca; background:transparent;
      color:var(--red,#dc2626); font-family:var(--font-b); font-size:0.85rem; font-weight:400;
      cursor:pointer; text-decoration:none; transition:background 0.2s;
    }
    .btn-logout:hover { background:#fef2f2; }

    /* HERO */
    .hero {
      padding:120px 6% 0;
      min-height:100vh;
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:60px;
      align-items:center;
      position:relative;
      overflow:hidden;
    }
    .hero::before {
      content:'';
      position:absolute; top:-10%; right:-5%;
      width:55%; height:130%;
      background:var(--cream2);
      clip-path:polygon(12% 0%, 100% 0%, 100% 100%, 0% 100%);
      z-index:0;
    }

    .hero-text { position:relative; z-index:1; }

    .hero-pill {
      display:inline-flex; align-items:center; gap:8px;
      background:var(--teal-lt); border:1px solid rgba(124,58,237,0.2);
      border-radius:100px; padding:5px 14px 5px 8px;
      font-size:0.78rem; font-weight:400; color:var(--teal);
      text-transform:uppercase; letter-spacing:0.8px;
      margin-bottom:28px; animation:popIn 0.5s ease both;
    }
    .pill-dot { width:22px; height:22px; border-radius:50%; background:var(--teal); display:flex; align-items:center; justify-content:center; font-size:0.7rem; color:#fff; }

    h1 {
      font-family:var(--font-h);
      font-size:clamp(2.6rem,4.5vw,3.9rem);
      font-weight:400; line-height:1.06;
      letter-spacing:-1px; color:var(--ink);
      margin-bottom:22px;
      animation:slideUp 0.7s 0.1s ease both;
    }
    h1 .hl { color:var(--navy); font-weight:400; position:relative; display:inline-block; }
    h1 .hl::after { content:''; position:absolute; left:0; bottom:2px; width:100%; height:6px; background:rgba(20,51,94,0.18); border-radius:3px; z-index:-1; }

    .hero-desc { font-size:1.02rem; color:var(--muted); max-width:460px; line-height:1.75; margin-bottom:38px; animation:slideUp 0.7s 0.2s ease both; }

    .hero-actions { display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:52px; animation:slideUp 0.7s 0.3s ease both; }
    .btn-hero { padding:14px 32px; border-radius:100px; font-size:0.97rem; font-weight:400; }
    .btn-hero.sec { background:var(--white); border:1.5px solid var(--border); color:var(--ink2); box-shadow:var(--shadow); text-decoration:none; }
    .btn-hero.sec:hover { border-color:var(--navy); }

    .hero-meta { display:flex; gap:36px; animation:slideUp 0.7s 0.4s ease both; }
    .meta-num { font-family:var(--font-h); font-size:1.8rem; font-weight:400; color:var(--ink); line-height:1; }
    .meta-txt { font-size:0.8rem; color:var(--muted); margin-top:3px; }

    .hero-cards { position:relative; z-index:1; display:flex; flex-direction:column; gap:16px; padding:20px 0; animation:slideLeft 0.8s 0.2s ease both; }

    .hcard { background:var(--white); border-radius:var(--radius); padding:20px 22px; box-shadow:var(--shadow-lg); border:1px solid var(--border); transition:transform 0.3s; }
    .hcard:hover { transform:translateX(-4px); }
    .hcard:nth-child(2) { margin-left:30px; }

    .hcard-top { display:flex; gap:14px; align-items:center; margin-bottom:12px; }
    .hcard-ava { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
    .hcard-name { font-weight:400; font-size:0.95rem; color:var(--ink); }
    .hcard-sub { font-size:0.78rem; color:var(--muted); margin-top:2px; }

    .hcard-tags { display:flex; flex-wrap:wrap; gap:6px; }
    .tag { border-radius:6px; padding:3px 10px; font-size:0.74rem; font-weight:400; }
    .tag-o { background:#eef2f9; color:var(--navy); }
    .tag-t { background:var(--teal-lt); color:var(--teal); }
    .tag-g { background:#fef3e2; color:#b45309; }

    .hcard-footer { display:flex; justify-content:space-between; align-items:center; margin-top:14px; padding-top:12px; border-top:1px solid var(--border); }
    .hcard-rate { font-family:var(--font-h); font-weight:400; font-size:1.05rem; color:var(--navy); }
    .hcard-rate span { font-family:var(--font-b); font-size:0.75rem; color:var(--muted); font-weight:400; }
    .hcard-stars { font-size:0.82rem; color:#d97706; }
    .match-badge { display:inline-flex; align-items:center; gap:6px; background:#e6f9f0; border:1px solid #a7f3d0; border-radius:100px; padding:4px 12px; font-size:0.75rem; font-weight:400; color:#065f46; margin-top:10px; }

    section { padding:96px 6%; }
    .sec-label { font-size:0.74rem; font-weight:400; letter-spacing:2.5px; text-transform:uppercase; color:var(--navy); margin-bottom:10px; }
    .sec-title { font-family:var(--font-h); font-size:clamp(1.8rem,3vw,2.5rem); font-weight:400; line-height:1.15; letter-spacing:-0.5px; color:var(--ink); margin-bottom:12px; }
    .sec-sub { font-size:0.97rem; color:var(--muted); max-width:480px; line-height:1.7; }
    .divider { width:36px; height:3px; background:var(--navy); border-radius:2px; margin:18px 0 44px; }

    .reveal { opacity:0; transform:translateY(20px); transition:opacity 0.55s ease,transform 0.55s ease; }
    .reveal.on { opacity:1; transform:none; }

    .how-bg { background:var(--cream2); }
    .steps { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:2px; background:var(--border); border-radius:20px; overflow:hidden; margin-top:48px; border:1px solid var(--border); box-shadow:var(--shadow); }
    .step { background:var(--white); padding:36px 28px; transition:background 0.25s; }
    .step:hover { background:#fff9f5; }
    .step-no { font-family:var(--font-h); font-size:2.8rem; font-weight:400; color:var(--cream2); -webkit-text-stroke:2px var(--navy); line-height:1; margin-bottom:18px; }
    .step-icon { font-size:1.6rem; margin-bottom:12px; }
    .step h3 { font-family:var(--font-h); font-size:1.05rem; font-weight:400; margin-bottom:8px; }
    .step p { font-size:0.86rem; color:var(--muted); line-height:1.65; }

    .cat-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:14px; margin-top:48px; }
    .cat-card { background:var(--white); border:1.5px solid var(--border); border-radius:14px; padding:22px 16px; text-align:center; text-decoration:none; color:var(--ink); cursor:pointer; transition:all 0.2s; display:block; }
    .cat-card:hover { border-color:var(--navy); box-shadow:0 8px 24px rgba(20,51,94,0.12); transform:translateY(-3px); }
    .cat-emoji { font-size:2rem; display:block; margin-bottom:10px; }
    .cat-name { font-weight:400; font-size:0.88rem; }
    .cat-n { font-size:0.76rem; color:var(--muted); margin-top:3px; }

    .workers-bg { background:var(--cream2); }
    .workers-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:18px; margin-top:48px; }
    .wcard { background:var(--white); border:1.5px solid var(--border); border-radius:var(--radius); padding:24px; transition:all 0.25s; }
    .wcard:hover { border-color:var(--navy); box-shadow:var(--shadow-lg); transform:translateY(-3px); }
    .wcard-top { display:flex; gap:14px; margin-bottom:14px; }
    .wcard-ava { width:50px; height:50px; border-radius:12px; font-size:1.5rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .wcard-name { font-family:var(--font-h); font-weight:400; font-size:1rem; }
    .wcard-job { font-size:0.8rem; color:var(--muted); }
    .wcard-loc { font-size:0.76rem; color:var(--muted); margin-top:2px; }
    .wcard-tags { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
    .wcard-bot { display:flex; align-items:center; justify-content:space-between; padding-top:14px; border-top:1px solid var(--border); }
    .wcard-price { font-family:var(--font-h); font-weight:400; font-size:1.05rem; color:var(--navy); }
    .wcard-price span { font-family:var(--font-b); font-size:0.74rem; color:var(--muted); font-weight:400; }
    .btn-hire { padding:7px 16px; border-radius:100px; background:var(--navy); color:#fff; font-size:0.8rem; font-weight:400; text-decoration:none; border:none; cursor:pointer; transition:background 0.2s; box-shadow:0 3px 10px rgba(20,51,94,0.25); }
    .btn-hire:hover { background:var(--navy); }

    .feat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(290px,1fr)); gap:20px; margin-top:48px; }
    .feat-card { background:var(--white); border:1.5px solid var(--border); border-radius:var(--radius); padding:28px; display:flex; gap:18px; align-items:flex-start; transition:border-color 0.25s,box-shadow 0.25s; }
    .feat-card:hover { border-color:var(--teal); box-shadow:0 8px 24px rgba(124,58,237,0.1); }
    .feat-icon { width:46px; height:46px; border-radius:12px; background:var(--teal-lt); display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
    .feat-card h3 { font-family:var(--font-h); font-weight:400; font-size:1rem; margin-bottom:6px; }
    .feat-card p { font-size:0.86rem; color:var(--muted); line-height:1.65; }

    .testi-bg { background:var(--cream2); }
    .testi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(270px,1fr)); gap:18px; margin-top:48px; }
    .tcard { background:var(--white); border:1.5px solid var(--border); border-radius:var(--radius); padding:26px; }
    .tcard-stars { color:#d97706; font-size:0.95rem; letter-spacing:2px; margin-bottom:12px; }
    .tcard-text { font-size:0.9rem; color:var(--ink2); line-height:1.72; font-style:italic; margin-bottom:18px; }
    .tcard-auth { display:flex; gap:12px; align-items:center; }
    .tcard-ava { font-size:1.6rem; }
    .tcard-name { font-weight:400; font-size:0.9rem; }
    .tcard-role { font-size:0.77rem; color:var(--muted); }

    .cta-wrap { background:linear-gradient(135deg,var(--navy) 0%,#9F67FA 100%); border-radius:24px; padding:72px 60px; text-align:center; position:relative; overflow:hidden; }
    .cta-wrap::before { content:''; position:absolute; top:-40px; right:-40px; width:200px; height:200px; background:rgba(255,255,255,0.08); border-radius:50%; }
    .cta-wrap::after  { content:''; position:absolute; bottom:-60px; left:-30px; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
    .cta-wrap h2 { font-family:var(--font-h); font-size:clamp(1.9rem,3.5vw,2.8rem); font-weight:400; color:#fff; line-height:1.1; margin-bottom:14px; }
    .cta-wrap p { color:rgba(255,255,255,0.82); font-size:1rem; margin-bottom:36px; }
    .cta-btns { display:flex; justify-content:center; align-items:center; gap:16px; flex-wrap:wrap; }
    .btn-white { padding:13px 30px; border-radius:100px; background:transparent; color:#fff; border:2px solid #fff; font-family:var(--font-b); font-size:0.95rem; font-weight:500; text-decoration:none; cursor:pointer; transition:background 0.2s, transform 0.15s; }
    .btn-white:hover { background:rgba(255,255,255,0.12); transform:translateY(-1px); }
    .btn-wghost { padding:13px 30px; border-radius:100px; background:transparent; color:#fff; border:2px solid rgba(255,255,255,0.6); font-family:var(--font-b); font-size:0.95rem; font-weight:400; text-decoration:none; cursor:pointer; transition:background 0.2s, border-color 0.2s; }
    .btn-wghost:hover { background:transparent; border-color:#fff; }

    footer { background:var(--ink); color:rgba(255,255,255,0.75); padding:64px 6% 32px; }
    .footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:40px; margin-bottom:48px; }
    .footer-logo { font-family:var(--font-h); font-size:1.4rem; font-weight:800; color:#fff; text-decoration:none; }
    .footer-logo span { color:var(--purple2); font-weight:800; }
    .footer-desc { font-size:0.86rem; line-height:1.7; margin-top:12px; max-width:240px; }
    .footer-col h4 { font-family:var(--font-h); font-size:0.95rem; font-weight:400; color:#fff; margin-bottom:16px; }
    .footer-col ul { list-style:none; }
    .footer-col li { margin-bottom:10px; }
    .footer-col a { color:rgba(255,255,255,0.55); font-size:0.86rem; text-decoration:none; transition:color 0.2s; }
    .footer-col a:hover { color:var(--purple); }
    .footer-bot { display:flex; align-items:center; justify-content:space-between; padding-top:24px; border-top:1px solid rgba(255,255,255,0.1); font-size:0.8rem; color:rgba(255,255,255,0.4); }

    @keyframes popIn  { from{opacity:0;transform:scale(0.85)} to{opacity:1;transform:scale(1)} }
    @keyframes slideUp{ from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }
    @keyframes slideLeft{ from{opacity:0;transform:translateX(30px)} to{opacity:1;transform:translateX(0)} }

    @media(max-width:960px){
      .hero{grid-template-columns:1fr;padding-bottom:60px;}
      .hero::before{display:none;}
      .hero-cards{display:none;}
      .footer-grid{grid-template-columns:1fr 1fr;}
      nav{padding:0 4%;}
      .nav-toggle{display:flex;}
      .nav-links{
        display:none; position:absolute; top:68px; left:0; right:0;
        flex-direction:column; gap:0; background:var(--white);
        border-bottom:1px solid var(--border); box-shadow:var(--shadow-lg);
        padding:8px 6% 16px; z-index:199;
      }
      .nav-links.open{display:flex;}
      .nav-links li{width:100%;}
      .nav-links a{display:block; padding:12px 0; border-bottom:1px solid var(--border);}
      section{padding:64px 5%;}
      .cta-wrap{padding:56px 40px;}
    }
    @media(max-width:768px){
      .steps{grid-template-columns:1fr 1fr;}
      .cat-grid{grid-template-columns:repeat(3,1fr);}
    }
    @media(max-width:600px){
      .hero{padding:100px 5% 0;}
      .hero-meta{gap:20px; flex-wrap:wrap;}
      .hero-actions{flex-direction:column; align-items:stretch;}
      .btn-hero{text-align:center;}
      .cta-wrap{padding:40px 22px;}
      .cta-btns{flex-direction:column;}
      .footer-grid{grid-template-columns:1fr;}
      .footer-bot{flex-direction:column;gap:8px;text-align:center;}
      .steps{grid-template-columns:1fr;}
      .cat-grid{grid-template-columns:1fr 1fr;}
      .nav-btns{gap:6px;}
      .nav-btns .btn-outline,.nav-btns .btn-fill{padding:7px 14px;font-size:0.8rem;}
    }


  </style>
</head>
<body>

<!-- NAVBAR -->
<nav id="navbar">
  <a href="index.php" class="logo">Skill<span>Connect</span></a>

  <ul class="nav-links" id="navLinks">
    <li><a href="#how">How It Works</a></li>
    <li><a href="#categories">Categories</a></li>
    <li><a href="#workers">Find Talent</a></li>
    <li><a href="#features">Features</a></li>
  </ul>

  <div class="nav-btns">
    <?php if ($is_logged_in): ?>
      <!-- LOGGED IN: Show user name + dashboard + logout -->
      <a href="<?= getDashLink($user_role) ?>" class="user-pill">
        <span class="user-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></span>
        <?= htmlspecialchars($user_name) ?>
      </a>
      <a href="logout.php" class="btn-outline" style="border-color:#fecaca;color:#dc2626;">Logout</a>
    <?php else: ?>
      <!-- NOT LOGGED IN: Show Login + Get Started -->
      <a href="login.php" class="btn-outline">Login</a>
      <a href="register.php" class="btn-outline">Registered Here →</a>
    <?php endif; ?>
    <button class="nav-toggle" id="navToggle" aria-label="Menu">☰</button>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-text">
    <div class="hero-pill"><span class="pill-dot">✦</span> AI-Powered Matching</div>
    <h1>Where <span class="hl">Skills</span> Meet Success</h1>
    <p class="hero-desc">Connect with verified mehendi artists, photographers, makeup artists, tutors & more — or find your next job based on what you can truly do.</p>
    <div class="hero-actions">
      <?php if ($is_logged_in): ?>
        <a href="<?= getDashLink($user_role) ?>" class="btn-fill btn-hero">Go to Dashboard</a>
        <a href="<?= $user_role === 'client' ? 'post_job.php' : 'jobs.php' ?>" class="btn-hero sec">
          <?= $user_role === 'client' ? 'Post a Job' : 'Find Jobs' ?>
        </a>
      <?php else: ?>
        <a href="register.php?role=client" class="btn-outline btn-hero">Post a Job</a>
        <a href="register.php?role=worker" class="btn-outline btn-hero">Join as Worker</a>
      <?php endif; ?>
    </div>
    <div class="hero-meta">
      <div class="meta-item"><div class="meta-num">12K+</div><div class="meta-txt">Skilled Workers</div></div>
      <div class="meta-item"><div class="meta-num">8.9K</div><div class="meta-txt">Jobs Posted</div></div>
      <div class="meta-item"><div class="meta-num">98%</div><div class="meta-txt">Satisfaction</div></div>
    </div>
  </div>

  <div class="hero-cards">
    <div class="hcard">
      <div class="hcard-top">
        <div class="hcard-ava" style="background:#eef2f9">💇‍♀️</div>
        <div><div class="hcard-name">Kavitha R.</div><div class="hcard-sub">Mehendi Artist · Coimbatore</div></div>
      </div>
      <div class="hcard-tags"><span class="tag tag-o">Bridal Mehendi</span><span class="tag tag-o">Arabic Art</span><span class="tag tag-g">5 yrs exp</span></div>
      <div class="hcard-footer"><div class="hcard-rate">₹800 <span>/ session</span></div><div class="hcard-stars">★★★★★ 4.9</div></div>
      <div class="match-badge">✦ 97% AI Match Score</div>
    </div>
    <div class="hcard">
      <div class="hcard-top">
        <div class="hcard-ava" style="background:#ede9fe">📸</div>
        <div><div class="hcard-name">Sanjay K.</div><div class="hcard-sub">Photographer · Chennai</div></div>
      </div>
      <div class="hcard-tags"><span class="tag tag-t">Wedding</span><span class="tag tag-t">Portrait</span><span class="tag tag-t">Available Now</span></div>
      <div class="hcard-footer"><div class="hcard-rate">₹3,500 <span>/ day</span></div><div class="hcard-stars">★★★★★ 4.8</div></div>
      <div class="match-badge">✦ 93% AI Match Score</div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-bg" id="how">
  <div class="sec-label">Simple Process</div>
  <h2 class="sec-title reveal">How SkillConnect Works</h2>
  <div class="divider"></div>
  <div class="steps">
    <div class="step reveal"><div class="step-no">01</div><div class="step-icon">👤</div><h3>Create Profile</h3><p>Register and build your skill portfolio with photos, experience and availability status.</p></div>
    <div class="step reveal"><div class="step-no">02</div><div class="step-icon">📋</div><h3>Post or Browse Jobs</h3><p>Clients post jobs with budget & location. AI recommends the best matches instantly.</p></div>
    <div class="step reveal"><div class="step-no">03</div><div class="step-icon">💬</div><h3>Chat & Confirm</h3><p>Use built-in chat to discuss requirements and confirm the booking securely on platform.</p></div>
    <div class="step reveal"><div class="step-no">04</div><div class="step-icon">⭐</div><h3>Pay & Review</h3><p>Pay securely after job completion. Leave ratings to build a trusted reputation.</p></div>
  </div>
</section>

<!-- CATEGORIES -->
<section id="categories">
  <div class="sec-label">Explore Skills</div>
  <h2 class="sec-title reveal">Browse by Category</h2>
  <p class="sec-sub reveal">Every skill deserves a platform. Find or offer work across 50+ categories.</p>
  <div class="divider"></div>
  <div class="cat-grid">
    <a href="jobs.php?cat=mehendi"     class="cat-card reveal"><span class="cat-emoji">💇‍♀️</span><div class="cat-name">Mehendi Artist</div><div class="cat-n">340 workers</div></a>
    <a href="jobs.php?cat=photography" class="cat-card reveal"><span class="cat-emoji">📸</span><div class="cat-name">Photography</div><div class="cat-n">512 workers</div></a>
    <a href="jobs.php?cat=makeup"      class="cat-card reveal"><span class="cat-emoji">💄</span><div class="cat-name">Makeup Artist</div><div class="cat-n">280 workers</div></a>
    <a href="jobs.php?cat=videography" class="cat-card reveal"><span class="cat-emoji">🎬</span><div class="cat-name">Videography</div><div class="cat-n">460 workers</div></a>
    <a href="jobs.php?cat=design" class="cat-card reveal"><span class="cat-emoji">🎨</span><div class="cat-name">Graphic Design</div><div class="cat-n">320 workers</div></a>
    <a href="jobs.php?cat=catering"    class="cat-card reveal"><span class="cat-emoji">🍽️</span><div class="cat-name">Catering</div><div class="cat-n">190 workers</div></a>
    <a href="jobs.php?cat=tailoring"   class="cat-card reveal"><span class="cat-emoji">🧵</span><div class="cat-name">Tailoring</div><div class="cat-n">215 workers</div></a>
    <a href="jobs.php?cat=tutor"       class="cat-card reveal"><span class="cat-emoji">📚</span><div class="cat-name">Home Tutor</div><div class="cat-n">670 workers</div></a>
  </div>
</section>


<!-- FEATURES -->
<section id="features">
  <div class="sec-label">Why Choose Us</div>
  <h2 class="sec-title reveal">Everything You Need, One Platform</h2>
  <div class="divider"></div>
  <div class="feat-grid">
    <div class="feat-card reveal"><div class="feat-icon">🤖</div><div><h3>AI Job Matching</h3><p>Smart algorithm matches workers to jobs based on skills, location, budget and past performance automatically.</p></div></div>
    <div class="feat-card reveal"><div class="feat-icon">🗂️</div><div><h3>Portfolio Showcase</h3><p>Upload photos and videos of your best work. Let your portfolio speak louder than any resume.</p></div></div>
    <div class="feat-card reveal"><div class="feat-icon">💬</div><div><h3>Real-Time Chat</h3><p>Built-in secure messaging with notification alerts so you never miss a job opportunity.</p></div></div>
    <div class="feat-card reveal"><div class="feat-icon">🔒</div><div><h3>Secure Payments</h3><p>Encrypted payment gateway. Pay only when satisfied. Full transaction history available anytime.</p></div></div>
    <div class="feat-card reveal"><div class="feat-icon">⭐</div><div><h3>Ratings & Reviews</h3><p>Verified star ratings build trusted reputations that help clients hire with full confidence.</p></div></div>
    <div class="feat-card reveal"><div class="feat-icon">📍</div><div><h3>Location-Based Search</h3><p>Filter workers by city or district. Find skilled people near you for faster, more relevant hiring.</p></div></div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testi-bg">
  <div class="sec-label">Success Stories</div>
  <h2 class="sec-title reveal">What Our Users Say</h2>
  <div class="divider"></div>
  <div class="testi-grid">
    <div class="tcard reveal"><div class="tcard-stars">★★★★★</div><p class="tcard-text">"I got 5 bookings in my first week. SkillConnect helped me reach clients I never could through word of mouth alone. Truly life changing!"</p><div class="tcard-auth"><span class="tcard-ava">💇‍♀️</span><div><div class="tcard-name">Kowsalya P.</div><div class="tcard-role">Mehendi Artist, Coimbatore</div></div></div></div>
    <div class="tcard reveal"><div class="tcard-stars">★★★★★</div><p class="tcard-text">"Found a perfect photographer within 2 hours for my wedding event. The rating system gave me full confidence in my choice."</p><div class="tcard-auth"><span class="tcard-ava">🏠</span><div><div class="tcard-name">Ramesh T.</div><div class="tcard-role">Client, Salem</div></div></div></div>
    <div class="tcard reveal"><div class="tcard-stars">★★★★☆</div><p class="tcard-text">"The AI matching is genuinely impressive. It showed me exactly the right jobs. My bookings doubled within a month."</p><div class="tcard-auth"><span class="tcard-ava">📸</span><div><div class="tcard-name">Anand M.</div><div class="tcard-role">Photographer, Chennai</div></div></div></div>
  </div>
</section>

<!-- CTA -->
<section>
  <div class="cta-wrap reveal">
    <h2>Join 12,000+ Skilled Professionals Today</h2>
    <p>Whether you're hiring or looking for work — SkillConnect is built for real skills.</p>
    <div class="cta-btns">
      <?php if ($is_logged_in): ?>
        <a href="<?= getDashLink($user_role) ?>" class="btn-white">Go to Dashboard →</a>
      <?php else: ?>
        <a href="register.php?role=worker" class="btn-white">Join as Worker</a>
        <a href="register.php?role=client" class="btn-wghost">Post a Job</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-grid">
    <div>
      <a href="index.php" class="footer-logo">Skill<span>Connect</span></a>
      <p class="footer-desc">AI-powered skill-based job marketplace connecting talented workers with clients across India.</p>
    </div>
    <div class="footer-col"><h4>Platform</h4><ul><li><a href="#">Find Workers</a></li><li><a href="#">Post a Job</a></li><li><a href="#">How It Works</a></li><li><a href="#">Pricing</a></li></ul></div>
    <div class="footer-col"><h4>Categories</h4><ul><li><a href="#">Mehendi Artists</a></li><li><a href="#">Photographers</a></li><li><a href="#">Videographers</a></li><li><a href="#">Makeup Artists</a></li></ul></div>
    <div class="footer-col"><h4>Support</h4><ul><li><a href="#">Help Center</a></li><li><a href="#">Contact Us</a></li><li><a href="#">Privacy Policy</a></li><li><a href="#">Terms of Use</a></li></ul></div>
  </div>
  <div class="footer-bot">
    <span>© 2025 SkillConnect. All rights reserved.</span>
    <span>Made with ❤️ for skilled India</span>
  </div>
</footer>

<script>
  window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', scrollY > 30);
  });
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e, i) => {
      if (e.isIntersecting) { setTimeout(() => e.target.classList.add('on'), i * 70); io.unobserve(e.target); }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));

  // mobile nav toggle
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => navLinks.classList.toggle('open'));
    navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => navLinks.classList.remove('open')));
  }
</script>
</body>
</html>