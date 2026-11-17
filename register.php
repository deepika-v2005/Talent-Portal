<?php
session_start();

// ── DB CONNECTION ──
$host    = "localhost";
$dbname  = "skillconnect";
$db_user = "root";
$db_pass = "";

$conn = new mysqli($host, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message  = "";
$msg_type = "";

// Pre-fill role from URL (?role=worker or ?role=client)
$selected_role = isset($_GET["role"]) ? $_GET["role"] : "worker";
if (!in_array($selected_role, ["worker", "client"])) {
    $selected_role = "worker";
}

// ── FORM SUBMIT ──
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ── COLLECT RAW (password must NOT be escaped) ──
    $name     = trim($_POST["name"]     ?? "");
    $email    = trim($_POST["email"]    ?? "");
    $phone    = trim($_POST["phone"]    ?? "");
    $location = trim($_POST["location"] ?? "");
    $skills   = trim($_POST["skills"]   ?? "");
    $bio      = trim($_POST["bio"]      ?? "");
    $password = $_POST["password"]         ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";

    // Sanitize role
    $role = in_array($_POST["role"] ?? "", ["worker", "client"])
          ? $_POST["role"]
          : "worker";

    // ── VALIDATIONS ──
    if (empty($name) || empty($email) || empty($password) || empty($location)) {
        $message  = "Please fill in all required fields.";
        $msg_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message  = "Please enter a valid email address.";
        $msg_type = "error";

    } elseif (!empty($phone) && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $message  = "Please enter a valid phone number.";
        $msg_type = "error";

    } elseif (strlen($password) < 8) {
        $message  = "Password must be at least 8 characters.";
        $msg_type = "error";

    } elseif ($password !== $confirm) {
        $message  = "Passwords do not match.";
        $msg_type = "error";

    } else {

        // ── CHECK EMAIL EXISTS (Prepared Statement) ──
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message  = "This email is already registered. <a href='login.php'>Login here</a>.";
            $msg_type = "error";
            $stmt->close();

        } else {
            $stmt->close();

            // ── HASH PASSWORD ──
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Workers only get skills; clients get empty string
            $insert_skills = ($role === "worker") ? $skills : "";

            // ── INSERT using Prepared Statement (SQL Injection safe) ──
            //
            // users table columns matched exactly:
            //   name, email, password, phone, role, location,
            //   bio, skills, education, experience, profile_pic,
            //   show_phone, show_email, show_location, profile_public, created_at
            //
            // education    → NULL  (user fills in profile.php later)
            // experience   → NULL  (user fills in profile.php later)
            // profile_pic  → NULL  (user uploads in profile.php later)
            // show_phone   → 0     (private by default)
            // show_email   → 0     (private by default)
            // show_location  → 1   (visible by default)
            // profile_public → 1   (visible by default)

            $stmt = $conn->prepare("
                INSERT INTO users (
                    name,
                    email,
                    password,
                    phone,
                    role,
                    location,
                    bio,
                    skills,
                    education,
                    experience,
                    profile_pic,
                    show_phone,
                    show_email,
                    show_location,
                    profile_public,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    NULL, NULL, NULL,
                    0, 0, 1, 1,
                    NOW()
                )
            ");

            // 8 strings: name, email, hashed_password, phone, role, location, bio, skills
            $stmt->bind_param(
                "ssssssss",
                $name,
                $email,
                $hashed,
                $phone,
                $role,
                $location,
                $bio,
                $insert_skills
            );

            if ($stmt->execute()) {
                $stmt->close();

                // ── AUTO LOGIN: Fetch new user ──
                $stmt2 = $conn->prepare("SELECT id, name, role FROM users WHERE email = ?");
                $stmt2->bind_param("s", $email);
                $stmt2->execute();
                $result   = $stmt2->get_result();
                $new_user = $result->fetch_assoc();
                $stmt2->close();
                $conn->close();

                // Set session
                $_SESSION["user_id"]   = $new_user["id"];
                $_SESSION["user_name"] = $new_user["name"];
                $_SESSION["user_role"] = $new_user["role"];

                // ── REDIRECT BASED ON ROLE ──
                if ($role === "client") {
                    header("Location: client/dashboard.php");
                } else {
                    header("Location: worker/dashboard.php");
                }
                exit();

            } else {
                $stmt->close();
                $message  = "Registration failed. Please try again.";
                $msg_type = "error";
            }
        }
    }

    // Keep selected role on error redisplay
    $selected_role = $_POST["role"] ?? "worker";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register – SkillConnect</title>
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
      --teal:     #7C3AED;
      --teal-lt:  #ede9fe;
      --border:   #e4ddd1;
      --red:      #dc2626;
      --red-lt:   #fef2f2;
      --green:    #16a34a;
      --green-lt: #f0fdf4;
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

    /* ── NAVBAR ── */
    nav {
      display:flex; align-items:center; justify-content:space-between;
      padding:0 6%; height:64px;
      background:rgba(250,247,242,0.97);
      border-bottom:1px solid var(--border);
      position:sticky; top:0; z-index:100;
    }
    .logo { font-family:var(--font-h); font-size:1.35rem; font-weight:400; color:var(--ink); text-decoration:none; }
    .logo span { color:var(--orange); }
    .nav-right { display:flex; align-items:center; gap:10px; font-size:0.87rem; color:var(--muted); }
    .nav-right a { color:var(--orange); font-weight:400; text-decoration:none; }
    .nav-right a:hover { opacity:0.8; }

    /* ── MAIN LAYOUT ── */
    main {
      flex:1;
      display:grid;
      grid-template-columns:1fr 1.1fr;
      min-height:calc(100vh - 64px);
    }

    /* ── LEFT PANEL ── */
    .left-panel {
      background:linear-gradient(150deg, var(--orange) 0%, #9F67FA 60%, #ffb347 100%);
      display:flex; flex-direction:column; justify-content:center;
      padding:60px 52px; position:relative; overflow:hidden;
    }
    .left-panel::before {
      content:''; position:absolute; top:-100px; right:-100px;
      width:350px; height:350px; background:rgba(255,255,255,0.09); border-radius:50%;
    }
    .left-panel::after {
      content:''; position:absolute; bottom:-80px; left:-60px;
      width:300px; height:300px; background:rgba(255,255,255,0.06); border-radius:50%;
    }
    .panel-inner { position:relative; z-index:1; }
    .panel-logo {
      font-family:var(--font-h); font-size:1.5rem; font-weight:400;
      color:#fff; text-decoration:none; margin-bottom:40px; display:block;
    }
    .panel-logo span { opacity:0.75; }
    .left-panel h2 {
      font-family:var(--font-h); font-size:2.3rem; font-weight:400;
      color:#fff; line-height:1.12; letter-spacing:-0.5px; margin-bottom:14px;
    }
    .left-panel > .panel-inner > p {
      color:rgba(255,255,255,0.82); font-size:0.97rem; line-height:1.7;
      max-width:330px; margin-bottom:40px;
    }
    .benefit-list { display:flex; flex-direction:column; gap:14px; margin-bottom:44px; }
    .benefit-item { display:flex; align-items:center; gap:14px; }
    .b-check {
      width:30px; height:30px; flex-shrink:0;
      background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.35);
      border-radius:50%; display:flex; align-items:center; justify-content:center;
      font-size:0.85rem; color:#fff;
    }
    .b-text { font-size:0.9rem; color:rgba(255,255,255,0.92); line-height:1.4; }
    .panel-quote {
      background:rgba(255,255,255,0.13); border:1px solid rgba(255,255,255,0.22);
      border-radius:14px; padding:18px 20px; backdrop-filter:blur(6px);
    }
    .pq-text   { font-size:0.88rem; color:#fff; line-height:1.6; font-style:italic; margin-bottom:12px; }
    .pq-author { display:flex; align-items:center; gap:10px; }
    .pq-ava    { font-size:1.4rem; }
    .pq-name   { font-size:0.82rem; font-weight:400; color:#fff; }
    .pq-role   { font-size:0.74rem; color:rgba(255,255,255,0.65); }

    /* ── RIGHT PANEL ── */
    .right-panel {
      background:var(--white);
      display:flex; align-items:flex-start; justify-content:center;
      padding:44px 52px; overflow-y:auto;
    }
    .form-box { width:100%; max-width:460px; }
    .form-tag {
      display:inline-flex; align-items:center; gap:7px;
      background:var(--teal-lt); border:1px solid rgba(124,58,237,0.2);
      border-radius:100px; padding:5px 14px;
      font-size:0.75rem; font-weight:400; color:var(--teal);
      text-transform:uppercase; letter-spacing:0.8px; margin-bottom:18px;
    }
    .form-box h1 {
      font-family:var(--font-h); font-size:1.85rem; font-weight:400;
      line-height:1.1; letter-spacing:-0.5px; color:var(--ink); margin-bottom:6px;
    }
    .form-box .sub { font-size:0.9rem; color:var(--muted); margin-bottom:26px; line-height:1.6; }

    /* ── ROLE TOGGLE ── */
    .role-toggle { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:26px; }
    .role-opt { display:none; }
    .role-label {
      display:flex; flex-direction:column; align-items:center; gap:8px;
      padding:16px 12px; border:2px solid var(--border); border-radius:14px;
      cursor:pointer; transition:all 0.2s; background:var(--cream); text-align:center;
    }
    .role-label:hover { border-color:var(--orange); background:#fff9f5; }
    .role-emoji { font-size:1.8rem; }
    .role-title { font-weight:400; font-size:0.9rem; color:var(--ink); }
    .role-desc  { font-size:0.74rem; color:var(--muted); line-height:1.4; }
    .role-opt:checked + .role-label {
      border-color:var(--orange); background:rgba(232,96,10,0.06);
      box-shadow:0 0 0 3px rgba(232,96,10,0.1);
    }
    .role-opt:checked + .role-label .role-title { color:var(--orange); }

    /* ── MESSAGE BOX ── */
    .msg-box {
      border-radius:10px; padding:13px 16px; font-size:0.87rem; line-height:1.5;
      margin-bottom:20px; display:flex; align-items:flex-start; gap:10px;
    }
    .msg-box.error   { background:var(--red-lt);   border:1px solid #fecaca; color:var(--red); }
    .msg-box.success { background:var(--green-lt); border:1px solid #bbf7d0; color:var(--green); }
    .msg-box a { color:inherit; font-weight:400; }

    /* ── FORM FIELDS ── */
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-group { margin-bottom:16px; }
    .form-group label {
      display:block; font-size:0.83rem; font-weight:400;
      color:var(--ink2); margin-bottom:7px; letter-spacing:0.2px;
    }
    .req { color:var(--orange); }
    .opt-hint { font-size:0.74rem; color:var(--muted); font-weight:400; margin-left:4px; }

    .input-wrap { position:relative; }
    .i-icon {
      position:absolute; left:13px; top:50%; transform:translateY(-50%);
      font-size:0.95rem; color:var(--muted); pointer-events:none;
    }
    .i-icon.top { top:14px; transform:none; }

    .form-group input,
    .form-group textarea {
      width:100%; padding:12px 13px 12px 38px;
      border:1.5px solid var(--border); border-radius:10px;
      background:var(--cream); color:var(--ink);
      font-family:var(--font-b); font-size:0.92rem; font-weight:400;
      outline:none; transition:border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .form-group textarea { padding-left:38px; resize:vertical; min-height:80px; }
    .form-group input:focus,
    .form-group textarea:focus {
      border-color:var(--orange); background:var(--white);
      box-shadow:0 0 0 3px rgba(232,96,10,0.1);
    }
    input::placeholder, textarea::placeholder { color:#b5a89a; font-weight:400; }

    .eye-btn {
      position:absolute; right:12px; top:50%; transform:translateY(-50%);
      cursor:pointer; font-size:0.95rem; color:var(--muted);
      user-select:none; transition:color 0.2s;
    }
    .eye-btn:hover { color:var(--orange); }

    /* Worker-only fields */
    .worker-only { display:none; }
    .worker-only.show { display:block; }

    /* Strength bar */
    .strength-bar  { height:3px; background:var(--border); border-radius:2px; margin-top:6px; overflow:hidden; }
    .strength-fill { height:100%; width:0; border-radius:2px; transition:width 0.35s, background 0.35s; }
    .strength-txt  { font-size:0.72rem; color:var(--muted); margin-top:4px; }

    /* Terms */
    .terms-row {
      display:flex; align-items:flex-start; gap:10px;
      margin-bottom:20px; margin-top:4px;
    }
    .terms-row input[type="checkbox"] {
      width:17px; height:17px; min-width:17px; padding:0; margin-top:2px;
      accent-color:var(--orange); cursor:pointer;
    }
    .terms-row label {
      font-size:0.82rem; color:var(--muted); line-height:1.5;
      margin-bottom:0; font-weight:400; cursor:pointer;
    }
    .terms-row a { color:var(--orange); font-weight:400; text-decoration:none; }

    /* Submit */
    .btn-submit {
      width:100%; padding:14px; border-radius:100px;
      background:var(--orange); color:#fff;
      font-family:var(--font-b); font-size:1rem; font-weight:400;
      border:none; cursor:pointer;
      transition:background 0.2s, transform 0.15s;
      box-shadow:0 6px 20px rgba(232,96,10,0.3);
      display:flex; align-items:center; justify-content:center; gap:8px;
      margin-bottom:18px;
    }
    .btn-submit:hover   { background:#d4540a; transform:translateY(-1px); }
    .btn-submit:active  { transform:translateY(0); }
    .btn-submit:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

    .divider-or {
      display:flex; align-items:center; gap:12px;
      font-size:0.8rem; color:var(--muted); margin-bottom:18px;
    }
    .divider-or::before, .divider-or::after { content:''; flex:1; height:1px; background:var(--border); }

    .login-link { text-align:center; font-size:0.87rem; color:var(--muted); }
    .login-link a { color:var(--orange); font-weight:400; text-decoration:none; }
    .login-link a:hover { opacity:0.8; }

    .spinner {
      width:17px; height:17px;
      border:2px solid rgba(255,255,255,0.4); border-top-color:#fff;
      border-radius:50%; animation:spin 0.7s linear infinite; display:none;
    }
    @keyframes spin { to { transform:rotate(360deg); } }

    @media(max-width:960px) {
      main { grid-template-columns:1fr; }
      .left-panel { display:none; }
      .right-panel { padding:36px 24px; }
    }
    @media(max-width:480px) {
      .form-row { grid-template-columns:1fr; }
      .right-panel { padding:24px 16px; }
      .form-box h1 { font-size:1.6rem; }
    }
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="logo">Skill<span>Connect</span></a>
  <div class="nav-right">
    Already have an account? <a href="login.php">Login →</a>
  </div>
</nav>

<main>

  <!-- ── LEFT PANEL ── -->
  <div class="left-panel">
    <div class="panel-inner">
      <a href="index.php" class="panel-logo">Skill<span>Connect</span></a>
      <h2>Your Skills<br/>Are Your Resume</h2>
      <p>Join thousands of workers and clients building real connections based on what matters — skills, not paper.</p>
      <div class="benefit-list">
        <div class="benefit-item"><div class="b-check">✓</div><div class="b-text">Free to register — no hidden charges</div></div>
        <div class="benefit-item"><div class="b-check">✓</div><div class="b-text">AI-powered job matching instantly</div></div>
        <div class="benefit-item"><div class="b-check">✓</div><div class="b-text">Build your portfolio &amp; get verified ratings</div></div>
        <div class="benefit-item"><div class="b-check">✓</div><div class="b-text">Secure payments &amp; real-time chat</div></div>
        <div class="benefit-item"><div class="b-check">✓</div><div class="b-text">Find jobs near your city or district</div></div>
      </div>
      <div class="panel-quote">
        <div class="pq-text">"I registered on Monday and got my first booking by Wednesday. SkillConnect actually works!"</div>
        <div class="pq-author">
          <span class="pq-ava">💇‍♀️</span>
          <div>
            <div class="pq-name">Kavitha R.</div>
            <div class="pq-role">Mehendi Artist, Coimbatore</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── RIGHT PANEL ── -->
  <div class="right-panel">
    <div class="form-box">

      <div class="form-tag">🚀 Create Account</div>
      <h1>Get Started Free</h1>
      <p class="sub">Join as a worker to find jobs, or as a client to hire skilled people.</p>

      <?php if (!empty($message)): ?>
        <div class="msg-box <?= $msg_type ?>">
          <span><?= $msg_type === 'error' ? '⚠️' : '✅' ?></span>
          <span><?= $message ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="regForm" novalidate>

        <!-- ROLE -->
        <div class="role-toggle">
          <input type="radio" name="role" id="roleWorker" value="worker" class="role-opt"
            <?= $selected_role === 'worker' ? 'checked' : '' ?>>
          <label for="roleWorker" class="role-label">
            <span class="role-emoji">👷</span>
            <span class="role-title">I'm a Service Provider</span>
            <span class="role-desc">I want to offer my skills &amp; find jobs</span>
          </label>
          <input type="radio" name="role" id="roleClient" value="client" class="role-opt"
            <?= $selected_role === 'client' ? 'checked' : '' ?>>
          <label for="roleClient" class="role-label">
            <span class="role-emoji">🏢</span>
            <span class="role-title">I'm a Client</span>
            <span class="role-desc">I want to hire skilled workers</span>
          </label>
        </div>

        <!-- name + location -->
        <div class="form-row">
          <div class="form-group">
            <label id="nameLabel">Full Name <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="i-icon">👤</span>
              <input type="text" name="name" id="nameInput" placeholder="Your full name"
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>City / Location <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="i-icon">📍</span>
              <input type="text" name="location" placeholder="e.g. Coimbatore"
                     value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>
            </div>
          </div>
        </div>

        <!-- email + phone -->
        <div class="form-row">
          <div class="form-group">
            <label>Email Address <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="i-icon">📧</span>
              <input type="email" name="email" placeholder="your@email.com"
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Phone Number <span class="opt-hint">(optional)</span></label>
            <div class="input-wrap">
              <span class="i-icon">📱</span>
              <input type="tel" name="phone" placeholder="+91 98765 43210"
                     value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
          </div>
        </div>

        <!-- skills — worker only -->
        <div class="form-group worker-only" id="skillsGroup">
          <label>Your Skills <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon top">🎯</span>
            <textarea name="skills"
                      placeholder="e.g. Bridal Mehendi, Photography, Graphic Design, Music..."><?= htmlspecialchars($_POST['skills'] ?? '') ?></textarea>
          </div>
          <div id="skill-tags-preview" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
        </div>

        <!-- bio — worker only -->
        <div class="form-group worker-only" id="bioGroup">
          <label>Short Bio <span class="opt-hint">(optional)</span></label>
          <div class="input-wrap">
            <span class="i-icon top">✍️</span>
            <textarea name="bio" rows="2"
                      placeholder="Tell clients a little about yourself..."><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- password -->
        <div class="form-group">
          <label>Password <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon">🔒</span>
            <input type="password" name="password" id="pw1" placeholder="Min 8 characters" required>
            <span class="eye-btn" onclick="toggleEye('pw1',this)">👁️</span>
          </div>
          <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
          <div class="strength-txt" id="strengthTxt">Enter password to check strength</div>
        </div>

        <!-- confirm password -->
        <div class="form-group">
          <label>Confirm Password <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="i-icon">🔑</span>
            <input type="password" name="confirm_password" id="pw2" placeholder="Re-enter password" required>
            <span class="eye-btn" onclick="toggleEye('pw2',this)">👁️</span>
          </div>
          <div class="strength-txt" id="matchTxt"></div>
        </div>

        <!-- terms -->
        <div class="terms-row">
          <input type="checkbox" id="terms" required>
          <label for="terms">
            I agree to SkillConnect's <a href="javascript:void(0)" onclick="openTerms()">Terms of Service</a> and <a href="javascript:void(0)" onclick="openTerms()">Privacy Policy</a>
          </label>
        </div>

        <!-- submit -->
        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnTxt">Create My Account</span>
          <span class="spinner" id="spinner"></span>
          <span id="btnArrow">→</span>
        </button>

      </form>

      <div class="divider-or">or</div>
      <div class="login-link">Already have an account? <a href="login.php">Login here →</a></div>

    </div>
  </div>

</main>

<script>
  // ── ROLE TOGGLE ──
  function updateRole() {
    const isWorker = document.querySelector('input[name="role"]:checked')?.value === 'worker';
    document.querySelectorAll('.worker-only').forEach(el => el.classList.toggle('show', isWorker));

    // BUG-009 FIX — name label/placeholder should reflect the selected role
    const nameLabel = document.getElementById('nameLabel');
    const nameInput = document.getElementById('nameInput');
    if (nameLabel && nameInput) {
      if (isWorker) {
        nameLabel.innerHTML = 'Full Name <span class="req">*</span>';
        nameInput.placeholder = 'Your full name';
      } else {
        nameLabel.innerHTML = 'Company / Full Name <span class="req">*</span>';
        nameInput.placeholder = 'Your company or full name';
      }
    }
  }
  document.querySelectorAll('input[name="role"]').forEach(r => r.addEventListener('change', updateRole));
  updateRole();

  // ── LIVE SKILL TAGS ──
  const skillsTA    = document.querySelector('textarea[name="skills"]');
  const tagsPreview = document.getElementById('skill-tags-preview');
  function renderSkillTags(val) {
    tagsPreview.innerHTML = val.split(',').map(s => s.trim()).filter(Boolean).map(s =>
      `<span style="background:#ede9fe;color:#7C3AED;font-size:0.75rem;font-weight:400;padding:3px 10px;border-radius:100px;">${s}</span>`
    ).join('');
  }
  if (skillsTA) {
    renderSkillTags(skillsTA.value);
    skillsTA.addEventListener('input', () => renderSkillTags(skillsTA.value));
  }

  // ── PASSWORD TOGGLE ──
  function toggleEye(id, el) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    el.textContent = inp.type === 'password' ? '👁️' : '🙈';
  }

  // ── PASSWORD STRENGTH ──
  const pw1  = document.getElementById('pw1');
  const pw2  = document.getElementById('pw2');
  const fill = document.getElementById('strengthFill');
  const sTxt = document.getElementById('strengthTxt');
  const mTxt = document.getElementById('matchTxt');

  pw1.addEventListener('input', () => {
    const v = pw1.value;
    let score = 0;
    if (v.length >= 8)           score++;
    if (/[A-Z]/.test(v))         score++;
    if (/[0-9]/.test(v))         score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    if (v.length >= 12)          score++;
    const pct    = (score / 5) * 100;
    const colors = ['','#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
    const labels = ['','😟 Very Weak','😐 Weak','🙂 Fair','😊 Strong','💪 Very Strong'];
    fill.style.width      = pct + '%';
    fill.style.background = colors[score] || '#e4ddd1';
    sTxt.textContent      = v.length ? labels[score] : 'Enter password to check strength';
    sTxt.style.color      = colors[score] || '#6B7DB3';
    checkMatch();
  });
  pw2.addEventListener('input', checkMatch);
  function checkMatch() {
    if (!pw2.value) { mTxt.textContent = ''; return; }
    mTxt.textContent = pw1.value === pw2.value ? '✅ Passwords match' : '❌ Passwords do not match';
    mTxt.style.color = pw1.value === pw2.value ? '#16a34a' : '#dc2626';
  }

  // ── SUBMIT VALIDATION + SPINNER ──
  document.getElementById('regForm').addEventListener('submit', function(e) {
    if (pw1.value !== pw2.value) {
      e.preventDefault();
      pw2.style.borderColor = '#dc2626';
      pw2.focus();
      mTxt.textContent = '❌ Passwords do not match';
      mTxt.style.color = '#dc2626';
      return;
    }
    const role = document.querySelector('input[name="role"]:checked')?.value;
    if (role === 'worker' && !skillsTA?.value.trim()) {
      e.preventDefault();
      skillsTA.style.borderColor = '#dc2626';
      skillsTA.focus();
      return;
    }
    if (!document.getElementById('terms').checked) {
      e.preventDefault();
      return;
    }
    document.getElementById('btnTxt').textContent    = 'Creating Account...';
    document.getElementById('btnArrow').style.display = 'none';
    document.getElementById('spinner').style.display  = 'inline-block';
    document.getElementById('submitBtn').disabled     = true;
  });

  pw2.addEventListener('input', () => pw2.style.borderColor = '');
  if (skillsTA) skillsTA.addEventListener('input', () => skillsTA.style.borderColor = '');
</script>

<script>
function openTerms(){
  document.getElementById('termsModal').style.display='flex';
  document.body.style.overflow='hidden';
}
function closeTerms(){
  document.getElementById('termsModal').style.display='none';
  document.body.style.overflow='';
}
window.onclick=function(e){
  let m=document.getElementById('termsModal');
  if(m && e.target===m) closeTerms();
}
document.addEventListener('keydown', function(e){
  if(e.key === 'Escape') closeTerms();
});
</script>

<div id="termsModal" class="terms-modal-overlay">
  <div class="terms-modal-box" onclick="event.stopPropagation()">

    <div class="terms-modal-header">
      <div>
        <h2>Terms &amp; Conditions</h2>
        <span class="terms-modal-updated">Last updated: July 2026</span>
      </div>
      <button type="button" class="terms-modal-close" onclick="closeTerms()" aria-label="Close">&times;</button>
    </div>

    <div class="terms-modal-body">

      <section>
        <h3>1. Acceptance of Terms</h3>
        <p>By creating an account, logging in, or otherwise using SkillConnect ("the Platform"), you agree to be bound by these Terms &amp; Conditions and our Privacy Policy. If you do not agree with any part of these terms, please do not use the Platform.</p>
      </section>

      <section>
        <h3>2. Eligibility</h3>
        <p>You must be at least 18 years old and capable of entering into a legally binding agreement to register on SkillConnect. By registering, you confirm that all information you provide is accurate, current, and complete.</p>
      </section>

      <section>
        <h3>3. User Accounts</h3>
        <p>You are responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account. Please notify us immediately of any unauthorized use of your account.</p>
      </section>

      <section>
        <h3>4. Conduct on the Platform</h3>
        <p>Workers and clients agree to interact respectfully and honestly. Misrepresentation of skills, qualifications, job listings, or payments; harassment; discrimination; and fraudulent activity are strictly prohibited and may result in suspension or termination of your account.</p>
      </section>

      <section>
        <h3>5. Jobs, Payments &amp; Disputes</h3>
        <p>SkillConnect facilitates connections between workers and clients but is not a party to any agreement, job, or payment made between them. We encourage users to agree on scope, pricing, and timelines in writing before starting work. SkillConnect is not liable for disputes, losses, or damages arising from arrangements made outside the scope of the Platform.</p>
      </section>

      <section>
        <h3>6. Privacy</h3>
        <p>We collect and process personal information as described in our Privacy Policy, including profile details, contact information, and usage data, in order to provide and improve our services. We do not sell your personal data to third parties.</p>
      </section>

      <section>
        <h3>7. Content &amp; Intellectual Property</h3>
        <p>Any content you upload — including profile details, portfolio images, and job posts — remains your property, but you grant SkillConnect a limited license to display it on the Platform for the purpose of connecting workers and clients.</p>
      </section>

      <section>
        <h3>8. Limitation of Liability</h3>
        <p>SkillConnect is provided on an "as is" and "as available" basis. To the maximum extent permitted by law, we are not liable for any indirect, incidental, or consequential damages resulting from your use of the Platform.</p>
      </section>

      <section>
        <h3>9. Changes to These Terms</h3>
        <p>We may update these Terms &amp; Conditions from time to time to reflect changes in our services or legal requirements. Continued use of the Platform after changes are posted constitutes acceptance of the revised terms.</p>
      </section>

      <section>
        <h3>10. Contact Us</h3>
        <p>If you have any questions about these Terms &amp; Conditions, please reach out to our support team through the Contact page.</p>
      </section>

    </div>

    <div class="terms-modal-footer">
      <button type="button" class="terms-modal-agree" onclick="closeTerms()">I Understand</button>
    </div>

  </div>
</div>

<style>
.terms-modal-overlay{
  display:none;
  position:fixed; inset:0;
  background:rgba(11,31,58,0.55);
  backdrop-filter:blur(2px);
  z-index:9999;
  align-items:center;
  justify-content:center;
  padding:24px;
}
.terms-modal-box{
  background:#fff;
  width:100%;
  max-width:640px;
  max-height:82vh;
  border-radius:16px;
  box-shadow:0 24px 64px rgba(11,31,58,0.25);
  display:flex;
  flex-direction:column;
  overflow:hidden;
  font-family:'Poppins', sans-serif;
}
.terms-modal-header{
  display:flex; align-items:flex-start; justify-content:space-between;
  padding:22px 26px 16px;
  border-bottom:1px solid #e4ddd1;
}
.terms-modal-header h2{
  font-family:'Poppins', sans-serif;
  font-size:1.4rem; font-weight:400; color:#0B1F3A; margin:0 0 4px;
}
.terms-modal-updated{ font-size:0.78rem; color:#6B7DB3; }
.terms-modal-close{
  background:none; border:none; font-size:1.6rem; line-height:1;
  color:#6B7DB3; cursor:pointer; padding:2px 6px; border-radius:8px;
  transition:background 0.2s, color 0.2s;
}
.terms-modal-close:hover{ background:#f3ede3; color:#0B1F3A; }
.terms-modal-body{
  padding:20px 26px; overflow-y:auto; flex:1;
}
.terms-modal-body section{ margin-bottom:18px; }
.terms-modal-body section:last-child{ margin-bottom:0; }
.terms-modal-body h3{
  font-size:0.92rem; font-weight:400; color:#1E3A5F; margin-bottom:6px;
}
.terms-modal-body p{
  font-size:0.87rem; line-height:1.7; color:#3f4a5e;
}
.terms-modal-footer{
  padding:16px 26px 22px;
  border-top:1px solid #e4ddd1;
  display:flex; justify-content:flex-end;
}
.terms-modal-agree{
  background:#7C3AED; color:#fff; border:none;
  font-family:'Poppins', sans-serif;
  font-weight:400; font-size:0.9rem;
  padding:10px 24px; border-radius:100px;
  cursor:pointer; transition:background 0.2s, transform 0.15s;
}
.terms-modal-agree:hover{ background:#6b2fd6; transform:translateY(-1px); }
</style>

</body>
</html>