<?php
session_start();

// Already logged in → redirect
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    if ($role === 'client')     { header("Location: client/dashboard.php"); exit(); }
    elseif ($role === 'admin')  { header("Location: admin/dashboard.php");  exit(); }
    else                        { header("Location: worker/dashboard.php"); exit(); }
}

// DB CONFIG
$conn = new mysqli("localhost", "root", "", "skillconnect");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

$error   = "";
$success = "";

// Success msg from register page
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Account created! Please login to continue.";
}

// ── FORM SUBMIT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];

                // Redirect based on role
                if ($user['role'] === 'client') {
                    header("Location: client/dashboard.php");
                } elseif ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: worker/dashboard.php");
                }
                exit();
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "No account found with this email.";
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    :root {
      --cream:    #faf7f2;
      --cream2:   #f3ede3;
      --white:    #ffffff;
      --ink:      #0B1F3A;
      --ink2:     #1E3A5F;
      --muted:    #6B7DB3;
      --orange:   #7C3AED;
      --orange2:  #9F67FA;
      --teal:     #7C3AED;
      --teal-lt:  #ede9fe;
      --border:   #e4ddd1;
      --red:      #dc2626;
      --red-lt:   #fef2f2;
      --green:    #16a34a;
      --green-lt: #f0fdf4;
      --shadow:   0 4px 24px rgba(11,31,58,0.08);
      --shadow-lg:0 24px 64px rgba(11,31,58,0.14);
      --font-h:   'Poppins', sans-serif;
      --font-b:   'Poppins', sans-serif;
      --radius:   16px;
    }

    html { scroll-behavior:smooth; }
    body {
      background:var(--cream);
      font-family:var(--font-b);
      color:var(--ink);
      min-height:100vh;
      display:flex;
      flex-direction:column;
    }

    /* NAVBAR */
    nav {
      display:flex; align-items:center; justify-content:space-between;
      padding:0 6%; height:64px;
      background:rgba(250,247,242,0.97);
      border-bottom:1px solid var(--border);
      position:sticky; top:0; z-index:100;
    }
    .logo { font-family:var(--font-h); font-size:1.35rem; font-weight:400; color:var(--ink); text-decoration:none; }
    .logo span { color:var(--orange); }
    .nav-right { font-size:0.87rem; color:var(--muted); }
    .nav-right a { color:var(--orange); font-weight:400; text-decoration:none; }
    .nav-right a:hover { opacity:0.8; }

    /* MAIN LAYOUT */
    main {
      flex:1;
      display:grid;
      grid-template-columns:1fr 1fr;
      min-height:calc(100vh - 64px);
    }

    /* LEFT PANEL */
    .left-panel {
      background:linear-gradient(150deg, #0B1F3A 0%, #1E3A5F 60%, #5a4a30 100%);
      display:flex; flex-direction:column;
      justify-content:center;
      padding:60px 52px;
      position:relative; overflow:hidden;
    }
    .left-panel::before {
      content:''; position:absolute; top:-100px; right:-100px;
      width:350px; height:350px; background:rgba(232,96,10,0.08); border-radius:50%;
    }
    .left-panel::after {
      content:''; position:absolute; bottom:-80px; left:-60px;
      width:300px; height:300px; background:rgba(232,96,10,0.05); border-radius:50%;
    }
    .panel-inner { position:relative; z-index:1; }

    .panel-logo {
      font-family:var(--font-h); font-size:1.5rem; font-weight:400;
      color:#fff; text-decoration:none; margin-bottom:44px; display:block;
    }
    .panel-logo span { color:var(--orange2); }

    .left-panel h2 {
      font-family:var(--font-h); font-size:2.3rem; font-weight:400;
      color:#fff; line-height:1.12; letter-spacing:-0.5px; margin-bottom:14px;
    }
    .left-desc {
      color:rgba(255,255,255,0.65); font-size:0.97rem; line-height:1.7;
      max-width:330px; margin-bottom:40px;
    }

    /* Stats */
    .stats-row {
      display:flex; gap:0;
      background:rgba(255,255,255,0.06);
      border:1px solid rgba(255,255,255,0.1);
      border-radius:14px; overflow:hidden; margin-bottom:36px;
    }
    .stat-item { flex:1; padding:18px 16px; text-align:center; border-right:1px solid rgba(255,255,255,0.1); }
    .stat-item:last-child { border-right:none; }
    .stat-num { font-family:var(--font-h); font-size:1.5rem; font-weight:400; color:var(--orange2); line-height:1; }
    .stat-lbl { font-size:0.72rem; color:rgba(255,255,255,0.5); margin-top:4px; }

    /* Testimonial */
    .testi-card {
      background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12);
      border-radius:14px; padding:20px 22px; backdrop-filter:blur(6px);
    }
    .testi-stars { color:var(--orange2); font-size:0.85rem; letter-spacing:2px; margin-bottom:10px; }
    .testi-txt { font-size:0.88rem; color:rgba(255,255,255,0.82); line-height:1.65; font-style:italic; margin-bottom:14px; }
    .testi-auth { display:flex; align-items:center; gap:10px; }
    .testi-ava { font-size:1.5rem; }
    .testi-name { font-size:0.85rem; font-weight:400; color:#fff; }
    .testi-role { font-size:0.73rem; color:rgba(255,255,255,0.5); }

    /* RIGHT PANEL */
    .right-panel {
      background:var(--white);
      display:flex; align-items:center; justify-content:center;
      padding:48px 52px;
    }
    .form-box { width:100%; max-width:400px; }

    .form-tag {
      display:inline-flex; align-items:center; gap:7px;
      background:var(--teal-lt); border:1px solid rgba(124,58,237,0.2);
      border-radius:100px; padding:5px 14px;
      font-size:0.75rem; font-weight:400; color:var(--teal);
      text-transform:uppercase; letter-spacing:0.8px; margin-bottom:20px;
    }

    .form-box h1 {
      font-family:var(--font-h); font-size:2rem; font-weight:400;
      line-height:1.1; letter-spacing:-0.5px; color:var(--ink); margin-bottom:8px;
    }
    .form-box .sub { font-size:0.92rem; color:var(--muted); margin-bottom:28px; line-height:1.6; }

    /* Messages */
    .msg-box {
      border-radius:10px; padding:13px 16px;
      font-size:0.87rem; line-height:1.5; margin-bottom:22px;
      display:flex; align-items:flex-start; gap:10px;
    }
    .msg-box.error   { background:var(--red-lt);   border:1px solid #fecaca; color:var(--red); }
    .msg-box.success { background:var(--green-lt); border:1px solid #bbf7d0; color:var(--green); }

    /* Form fields */
    .form-group { margin-bottom:18px; }
    .form-group label {
      display:block; font-size:0.83rem; font-weight:400;
      color:var(--ink2); margin-bottom:7px;
    }
    .input-wrap { position:relative; }
    .i-icon {
      position:absolute; left:13px; top:50%;
      transform:translateY(-50%); font-size:0.95rem;
      color:var(--muted); pointer-events:none;
    }
    .eye-btn {
      position:absolute; right:12px; top:50%;
      transform:translateY(-50%); cursor:pointer;
      font-size:0.95rem; color:var(--muted); user-select:none; transition:color 0.2s;
    }
    .eye-btn:hover { color:var(--orange); }

    .form-group input {
      width:100%; padding:13px 42px 13px 38px;
      border:1.5px solid var(--border); border-radius:10px;
      background:var(--cream); color:var(--ink);
      font-family:var(--font-b); font-size:0.93rem; font-weight:400;
      outline:none; transition:border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .form-group input:focus {
      border-color:var(--orange); background:var(--white);
      box-shadow:0 0 0 3px rgba(232,96,10,0.1);
    }
    input::placeholder { color:#b5a89a; font-weight:400; }

    /* Remember + Forgot */
    .form-meta {
      display:flex; align-items:center; justify-content:space-between;
      margin-bottom:22px; font-size:0.83rem;
    }
    .remember { display:flex; align-items:center; gap:8px; color:var(--muted); cursor:pointer; }
    .remember input[type="checkbox"] { width:16px; height:16px; accent-color:var(--orange); cursor:pointer; }
    .forgot-link { color:var(--orange); font-weight:400; text-decoration:none; font-size:0.83rem; }
    .forgot-link:hover { opacity:0.75; }

    /* Submit button */
    .btn-submit {
      width:100%; padding:14px; border-radius:100px;
      background:var(--orange); color:#fff;
      font-family:var(--font-b); font-size:1rem; font-weight:400;
      border:none; cursor:pointer;
      transition:background 0.2s, transform 0.15s;
      box-shadow:0 6px 20px rgba(232,96,10,0.3);
      display:flex; align-items:center; justify-content:center; gap:8px;
      margin-bottom:20px;
    }
    .btn-submit:hover   { background:#d4540a; transform:translateY(-1px); }
    .btn-submit:active  { transform:translateY(0); }
    .btn-submit:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

    /* Divider */
    .divider-or {
      display:flex; align-items:center; gap:12px;
      font-size:0.8rem; color:var(--muted); margin-bottom:16px;
    }
    .divider-or::before, .divider-or::after { content:''; flex:1; height:1px; background:var(--border); }

    /* Quick role links */
    .quick-roles { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px; }
    .qrole {
      display:flex; align-items:center; gap:8px;
      padding:11px 14px; background:var(--cream);
      border:1.5px solid var(--border); border-radius:10px;
      font-size:0.83rem; font-weight:400; color:var(--ink2);
      text-decoration:none; transition:all 0.2s;
    }
    .qrole:hover { border-color:var(--orange); background:#fff9f5; color:var(--orange); }

    /* Register link */
    .register-link { text-align:center; font-size:0.87rem; color:var(--muted); }
    .register-link a { color:var(--orange); font-weight:400; text-decoration:none; }
    .register-link a:hover { opacity:0.8; }

    /* Spinner */
    .spinner {
      width:17px; height:17px; border:2px solid rgba(255,255,255,0.4);
      border-top-color:#fff; border-radius:50%;
      animation:spin 0.7s linear infinite; display:none;
    }
    @keyframes spin { to { transform:rotate(360deg); } }

    /* Responsive */
    @media(max-width:900px) {
      main { grid-template-columns:1fr; }
      .left-panel { display:none; }
      .right-panel { padding:40px 24px; }
    }
    @media(max-width:420px) {
      .right-panel { padding:28px 16px; }
      .form-box { max-width:100%; }
      .form-box h1 { font-size:1.6rem; }
      .quick-roles { grid-template-columns:1fr; }
      .form-meta { flex-direction:column; align-items:flex-start; gap:10px; }
      nav { padding:0 4%; }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
  <a href="index.html" class="logo">Skill<span>Connect</span></a>
  <div class="nav-right">
    New here? <a href="register.php">Create an account →</a>
  </div>
</nav>

<main>

  <!-- LEFT PANEL -->
  <div class="left-panel">
    <div class="panel-inner">
      <a href="index.html" class="panel-logo">Skill<span>Connect</span></a>
      <h2>Welcome<br/>Back!</h2>
      <p class="left-desc">Log in to access your jobs, manage your profile, and connect with skilled workers or clients across India.</p>

      <div class="stats-row">
        <div class="stat-item"><div class="stat-num">12K+</div><div class="stat-lbl">Workers</div></div>
        <div class="stat-item"><div class="stat-num">8.9K</div><div class="stat-lbl">Jobs Posted</div></div>
        <div class="stat-item"><div class="stat-num">98%</div><div class="stat-lbl">Satisfaction</div></div>
      </div>

      <div class="testi-card">
        <div class="testi-stars">★★★★★</div>
        <p class="testi-txt">"Found the perfect photographer for my wedding in just 2 hours. The platform is so easy to use!"</p>
        <div class="testi-auth">
          <span class="testi-ava">🏠</span>
          <div>
            <div class="testi-name">Ramesh T.</div>
            <div class="testi-role">Client, Salem, TN</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">
    <div class="form-box">

      <div class="form-tag">🔐 Secure Login</div>
      <h1>Login to Your<br/>Account</h1>
      <p class="sub">Enter your credentials to access your SkillConnect dashboard.</p>

      <!-- ERROR -->
      <?php if (!empty($error)): ?>
        <div class="msg-box error"><span>⚠️</span><span><?= htmlspecialchars($error) ?></span></div>
      <?php endif; ?>

      <!-- SUCCESS -->
      <?php if (!empty($success)): ?>
        <div class="msg-box success"><span>✅</span><span><?= htmlspecialchars($success) ?></span></div>
      <?php endif; ?>

      <!-- FORM -->
      <form method="POST" action="login.php" id="loginForm" novalidate>

        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-wrap">
            <span class="i-icon">📧</span>
            <input type="email" id="email" name="email"
              placeholder="Enter your email"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required autocomplete="email"/>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <span class="i-icon">🔒</span>
            <input type="password" id="password" name="password"
              placeholder="Enter your password"
              required autocomplete="current-password"/>
            <span class="eye-btn" id="eyeBtn" onclick="toggleEye()">👁️</span>
          </div>
        </div>

        <div class="form-meta">
          <label class="remember">
            <input type="checkbox" name="remember"/> Remember me
          </label>
          <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnTxt">Login to Account</span>
          <span class="spinner" id="spinner"></span>
          <span id="btnArrow">→</span>
        </button>

      </form>

      <div class="divider-or">New to SkillConnect?</div>

      <div class="quick-roles">
        <a href="register.php?role=worker" class="qrole">👷 Join as Worker</a>
        <a href="register.php?role=client" class="qrole">🏢 Hire Talent</a>
      </div>

      <div class="register-link">
        Already registered? <a href="register.php">Create account →</a>
      </div>

    </div>
  </div>

</main>

<script>
  // Show/hide password
  function toggleEye() {
    const inp = document.getElementById('password');
    const btn = document.getElementById('eyeBtn');
    inp.type        = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁️' : '🙈';
  }

  // Submit spinner
  document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value.trim();
    const pw    = document.getElementById('password').value.trim();
    if (!email || !pw) { e.preventDefault(); return; }
    document.getElementById('btnTxt').textContent     = 'Logging in...';
    document.getElementById('btnArrow').style.display = 'none';
    document.getElementById('spinner').style.display  = 'inline-block';
    document.getElementById('submitBtn').disabled     = true;
  });

  // Auto focus
  window.onload = () => {
    const e = document.getElementById('email');
    if (!e.value) e.focus();
  };
</script>

<script>
function openTerms(){document.getElementById('termsModal').style.display='block';}
function closeTerms(){document.getElementById('termsModal').style.display='none';}
window.onclick=function(e){let m=document.getElementById('termsModal');if(m&&e.target==m)m.style.display='none';}
</script>
<div id="termsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999"><div style="background:#fff;max-width:700px;margin:5% auto;padding:20px;border-radius:12px;max-height:80vh;overflow:auto;position:relative"><span onclick="closeTerms()" style="position:absolute;right:15px;cursor:pointer;font-size:28px">&times;</span><h2>Terms & Conditions</h2><p>Use SkillConnect responsibly.</p></div></div></body>
</html>