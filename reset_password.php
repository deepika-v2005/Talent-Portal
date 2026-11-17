<?php
session_start();

if (!isset($_SESSION["reset_email"]) || !isset($_SESSION["otp_verified"])) {
    header("Location: forgot_password.php");
    exit();
}

$host     = "localhost";
$dbname   = "skillconnect";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$email    = $_SESSION["reset_email"];
$message  = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_pass = trim($_POST["password"]);
    $confirm  = trim($_POST["confirm"]);

    if (strlen($new_pass) < 6) {
        $message  = "Password must be at least 6 characters.";
        $msg_type = "error";
    } elseif ($new_pass !== $confirm) {
        $message  = "Passwords do not match.";
        $msg_type = "error";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("UPDATE users SET password=?, reset_otp=NULL, otp_expiry=NULL WHERE email=?");
        $stmt->bind_param("ss", $hashed, $email);
        $stmt->execute();
        $stmt->close();

        unset($_SESSION["reset_email"], $_SESSION["otp_verified"]);
        header("Location: login.php?reset=1");
        exit();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--border:#DDD6FE;--red:#dc2626;--red-lt:#fef2f2;--green:#16a34a;--green-lt:#f0fdf4;--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);min-height:100vh;display:flex;flex-direction:column;}
    nav{display:flex;align-items:center;justify-content:space-between;padding:0 6%;height:64px;background:rgba(250,247,242,0.95);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
    .logo{font-family:var(--font-h);font-size:1.35rem;font-weight:900;color:var(--ink);text-decoration:none;}
    .logo span{color:var(--orange);}
    main{flex:1;display:grid;grid-template-columns:1fr 1fr;min-height:calc(100vh - 64px);}
    .left-panel{background:linear-gradient(145deg,var(--orange) 0%,#9F67FA 100%);display:flex;flex-direction:column;justify-content:center;padding:60px 56px;position:relative;overflow:hidden;}
    .left-panel::before{content:'';position:absolute;top:-80px;right:-80px;width:300px;height:300px;background:rgba(255,255,255,0.08);border-radius:50%;}
    .panel-content{position:relative;z-index:1;}
    .panel-icon{width:72px;height:72px;background:rgba(255,255,255,0.2);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:28px;border:1px solid rgba(255,255,255,0.3);}
    .left-panel h2{font-family:var(--font-h);font-size:2.2rem;font-weight:900;color:#fff;line-height:1.15;margin-bottom:16px;}
    .left-panel p{color:rgba(255,255,255,0.82);font-size:0.97rem;line-height:1.7;max-width:320px;}
    .tips{margin-top:28px;display:flex;flex-direction:column;gap:12px;}
    .tip{display:flex;align-items:center;gap:12px;color:rgba(255,255,255,0.9);font-size:0.88rem;}
    .tip-icon{font-size:1rem;}
    .right-panel{display:flex;align-items:center;justify-content:center;padding:40px 48px;background:var(--white);}
    .form-box{width:100%;max-width:420px;}
    .form-tag{display:inline-flex;align-items:center;gap:7px;background:var(--teal-lt);border:1px solid rgba(124,58,237,0.2);border-radius:100px;padding:5px 14px;font-size:0.75rem;font-weight:700;color:var(--teal);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:22px;}
    .form-box h1{font-family:var(--font-h);font-size:2rem;font-weight:900;line-height:1.1;color:var(--ink);margin-bottom:10px;}
    .form-box .sub{font-size:0.93rem;color:var(--muted);line-height:1.65;margin-bottom:32px;}
    .msg-box{border-radius:10px;padding:14px 16px;font-size:0.88rem;line-height:1.5;margin-bottom:24px;display:flex;gap:10px;}
    .msg-box.error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
    .form-group{margin-bottom:20px;}
    label{display:block;font-size:0.85rem;font-weight:700;color:var(--ink2);margin-bottom:8px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:1rem;color:var(--muted);pointer-events:none;}
    .toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--muted);}
    input[type="password"],input[type="text"]{width:100%;padding:13px 40px 13px 42px;border:1.5px solid var(--border);border-radius:10px;background:var(--cream);color:var(--ink);font-family:var(--font-b);font-size:0.95rem;font-weight:500;outline:none;transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;}
    input:focus{border-color:var(--orange);background:var(--white);box-shadow:0 0 0 3px rgba(232,96,10,0.12);}
    #strength-bar-wrap{height:5px;background:#eee;border-radius:4px;margin-top:8px;overflow:hidden;}
    #strength-bar{height:100%;width:0;border-radius:4px;transition:width 0.3s,background 0.3s;}
    #strength-text{font-size:11px;color:#888;margin-top:4px;}
    .hint{font-size:11px;color:#999;margin-top:4px;}
    .btn-reset{width:100%;padding:14px;border-radius:100px;background:var(--orange);color:#fff;font-family:var(--font-b);font-size:1rem;font-weight:700;border:none;cursor:pointer;transition:background 0.2s,transform 0.15s;box-shadow:0 6px 20px rgba(232,96,10,0.3);margin-top:4px;}
    .btn-reset:hover{background:#d4540a;transform:translateY(-1px);}
    .bottom-links{text-align:center;font-size:0.87rem;color:var(--muted);margin-top:20px;}
    .bottom-links a{color:var(--orange);font-weight:700;text-decoration:none;}
    @media(max-width:860px){main{grid-template-columns:1fr;}.left-panel{display:none;}.right-panel{padding:40px 24px;}}
  </style>
</head>
<body>
<nav>
  <a href="index.html" class="logo">Skill<span>Connect</span></a>
</nav>
<main>
  <div class="left-panel">
    <div class="panel-content">
      <div class="panel-icon">🔐</div>
      <h2>Create a Strong<br/>Password</h2>
      <p>Your new password protects your SkillConnect account. Make it strong and unique.</p>
      <div class="tips">
        <div class="tip"><span class="tip-icon">✅</span><span>At least 6 characters long</span></div>
        <div class="tip"><span class="tip-icon">✅</span><span>Mix uppercase & lowercase letters</span></div>
        <div class="tip"><span class="tip-icon">✅</span><span>Include numbers and symbols</span></div>
        <div class="tip"><span class="tip-icon">✅</span><span>Don't reuse old passwords</span></div>
      </div>
    </div>
  </div>

  <div class="right-panel">
    <div class="form-box">
      <div class="form-tag">🔐 New Password</div>
      <h1>Reset Password</h1>
      <p class="sub">Almost done! Set a new strong password for your account.</p>

      <?php if (!empty($message)): ?>
        <div class="msg-box <?= $msg_type ?>">
          <span>⚠️</span><span><?= htmlspecialchars($message) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label for="password">New Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password" placeholder="Minimum 6 characters" required oninput="checkStrength(this.value)">
            <button type="button" class="toggle-pw" onclick="togglePw('password',this)">👁️</button>
          </div>
          <div id="strength-bar-wrap"><div id="strength-bar"></div></div>
          <div id="strength-text">Enter a password</div>
        </div>
        <div class="form-group">
          <label for="confirm">Confirm New Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="confirm" name="confirm" placeholder="Re-enter new password" required>
            <button type="button" class="toggle-pw" onclick="togglePw('confirm',this)">👁️</button>
          </div>
          <p class="hint">Both passwords must match.</p>
        </div>
        <button type="submit" class="btn-reset">Reset Password ✓</button>
      </form>

      <div class="bottom-links">
        <a href="login.php">← Back to Login</a>
      </div>
    </div>
  </div>
</main>

<script>
function checkStrength(val) {
    const bar=document.getElementById('strength-bar'),text=document.getElementById('strength-text');
    let score=0;
    if(val.length>=6)score++;if(val.length>=10)score++;
    if(/[A-Z]/.test(val))score++;if(/[0-9]/.test(val))score++;if(/[^A-Za-z0-9]/.test(val))score++;
    const levels=[
        {pct:'0%',color:'#eee',label:'Enter a password'},
        {pct:'20%',color:'#e94560',label:'Very Weak'},
        {pct:'40%',color:'#ff7c43',label:'Weak'},
        {pct:'60%',color:'#f9c74f',label:'Fair'},
        {pct:'80%',color:'#43aa8b',label:'Strong'},
        {pct:'100%',color:'#0f3460',label:'Very Strong'},
    ];
    const l=val.length===0?levels[0]:levels[Math.min(score,5)];
    bar.style.width=l.pct;bar.style.background=l.color;
    text.textContent=l.label;text.style.color=l.color==='#eee'?'#888':l.color;
}
function togglePw(id,btn) {
    const inp=document.getElementById(id);
    inp.type=inp.type==='password'?'text':'password';
    btn.textContent=inp.type==='password'?'👁️':'🙈';
}
</script>
</body>
</html>