<?php
session_start();

// ✅ PHPMAILER — TOP-ல வரணும்
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── DB CONNECTION ──
$host     = "localhost";
$dbname   = "skillconnect";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_otp VARCHAR(6) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_expiry DATETIME DEFAULT NULL");

$message  = "";
$msg_type = "";

// ── FORM SUBMIT ──
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $email = $conn->real_escape_string($email);

    if (empty($email)) {
        $message  = "Please enter your email address.";
        $msg_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message  = "Please enter a valid email address.";
        $msg_type = "error";
    } else {
        $sql    = "SELECT id, name FROM users WHERE email = '$email'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $user       = $result->fetch_assoc();
            $otp        = rand(100000, 999999);
            $otp_expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

            $conn->query("UPDATE users SET reset_otp='$otp', otp_expiry='$otp_expiry' WHERE email='$email'");
            $_SESSION["reset_email"] = $email;

            // ── SEND OTP EMAIL ──
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USER') ?: 'your_email@gmail.com';
                $mail->Password   = getenv('SMTP_PASS') ?: 'YOUR_APP_PASSWORD';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('kowsalyaparthipan.m@gmail.com', 'SkillConnect');
                $mail->addAddress($email, $user['name']);

                $mail->isHTML(true);
                $mail->Subject = 'SkillConnect – Your Password Reset OTP';
                $mail->Body    = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body{font-family:"Segoe UI",sans-serif;background:#faf7f2;margin:0;padding:0;}
    .container{max-width:520px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1);}
    .header{background:linear-gradient(135deg,#7C3AED,#9F67FA);padding:36px 40px;text-align:center;}
    .header h1{color:#fff;font-size:24px;margin:0;}
    .header p{color:rgba(255,255,255,0.85);font-size:14px;margin:6px 0 0;}
    .body{padding:36px 40px;}
    .greeting{font-size:16px;color:#0B1F3A;margin-bottom:16px;}
    .info{font-size:14px;color:#6B7DB3;line-height:1.7;margin-bottom:28px;}
    .otp-box{background:#fff3ec;border:2px dashed #7C3AED;border-radius:12px;padding:24px;text-align:center;margin-bottom:28px;}
    .otp-label{font-size:12px;color:#6B7DB3;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;}
    .otp-code{font-size:42px;font-weight:900;color:#7C3AED;letter-spacing:12px;font-family:monospace;}
    .otp-valid{font-size:12px;color:#6B7DB3;margin-top:10px;}
    .warning{background:#fef2f2;border-left:4px solid #dc2626;border-radius:8px;padding:14px 16px;font-size:13px;color:#dc2626;margin-bottom:24px;}
    .footer{background:#f3ede3;padding:20px 40px;text-align:center;font-size:12px;color:#6B7DB3;}
    .footer a{color:#7C3AED;text-decoration:none;font-weight:600;}
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🔑 SkillConnect</h1>
      <p>Password Reset Request</p>
    </div>
    <div class="body">
      <p class="greeting">Hello, <strong>' . htmlspecialchars($user['name']) . '</strong> 👋</p>
      <p class="info">We received a request to reset your SkillConnect account password. Use the OTP below to proceed. If you did not request this, please ignore this email.</p>
      <div class="otp-box">
        <div class="otp-label">Your One-Time Password (OTP)</div>
        <div class="otp-code">' . $otp . '</div>
        <div class="otp-valid">⏱ Valid for <strong>15 minutes</strong> only</div>
      </div>
      <div class="warning">⚠️ Never share this OTP with anyone. SkillConnect will never ask for your OTP.</div>
      <p style="font-size:13px;color:#6B7DB3;">If you did not request a password reset, your account is safe.</p>
    </div>
    <div class="footer">
      &copy; 2025 <a href="#">SkillConnect</a> · AI-Based Skill Job Marketplace<br>
      This is an automated email. Please do not reply.
    </div>
  </div>
</body>
</html>';

                $mail->AltBody = "Hello " . $user['name'] . ",\n\nYour OTP is: $otp\nValid for 15 minutes.\n\nDo not share this OTP.";
                $mail->send();

                $message  = "OTP sent successfully to <strong>$email</strong>. Please check your inbox.";
                $msg_type = "success";

                header("refresh:3;url=verify_otp.php");

            } catch (Exception $e) {
                $message  = "Failed to send OTP. Error: " . $mail->ErrorInfo;
                $msg_type = "error";
            }

        } else {
            $message  = "No account found with this email address.";
            $msg_type = "error";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#ffffff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--border:#DDD6FE;--red:#dc2626;--red-lt:#fef2f2;--green:#16a34a;--green-lt:#f0fdf4;--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);min-height:100vh;display:flex;flex-direction:column;}
    nav{display:flex;align-items:center;justify-content:space-between;padding:0 6%;height:64px;background:rgba(250,247,242,0.95);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
    .logo{font-family:var(--font-h);font-size:1.35rem;font-weight:900;color:var(--ink);text-decoration:none;}
    .logo span{color:var(--orange);}
    .back-link{font-size:0.87rem;font-weight:600;color:var(--muted);text-decoration:none;display:flex;align-items:center;gap:6px;transition:color 0.2s;}
    .back-link:hover{color:var(--orange);}
    main{flex:1;display:grid;grid-template-columns:1fr 1fr;min-height:calc(100vh - 64px);}
    .left-panel{background:linear-gradient(145deg,var(--orange) 0%,#9F67FA 100%);display:flex;flex-direction:column;justify-content:center;align-items:flex-start;padding:60px 56px;position:relative;overflow:hidden;}
    .left-panel::before{content:'';position:absolute;top:-80px;right:-80px;width:300px;height:300px;background:rgba(255,255,255,0.08);border-radius:50%;}
    .left-panel::after{content:'';position:absolute;bottom:-60px;left:-40px;width:250px;height:250px;background:rgba(255,255,255,0.06);border-radius:50%;}
    .panel-content{position:relative;z-index:1;}
    .panel-icon{width:72px;height:72px;background:rgba(255,255,255,0.2);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:28px;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.3);}
    .left-panel h2{font-family:var(--font-h);font-size:2.2rem;font-weight:900;color:#fff;line-height:1.15;letter-spacing:-0.5px;margin-bottom:16px;}
    .left-panel p{color:rgba(255,255,255,0.82);font-size:0.97rem;line-height:1.7;max-width:320px;margin-bottom:36px;}
    .steps-list{list-style:none;display:flex;flex-direction:column;gap:16px;}
    .steps-list li{display:flex;align-items:flex-start;gap:14px;color:rgba(255,255,255,0.9);font-size:0.9rem;line-height:1.5;}
    .step-num-circle{width:28px;height:28px;flex-shrink:0;background:rgba(255,255,255,0.25);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:700;color:#fff;border:1px solid rgba(255,255,255,0.4);}
    .right-panel{display:flex;align-items:center;justify-content:center;padding:40px 48px;background:var(--white);}
    .form-box{width:100%;max-width:420px;}
    .form-tag{display:inline-flex;align-items:center;gap:7px;background:var(--teal-lt);border:1px solid rgba(124,58,237,0.2);border-radius:100px;padding:5px 14px;font-size:0.75rem;font-weight:700;color:var(--teal);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:22px;}
    .form-box h1{font-family:var(--font-h);font-size:2rem;font-weight:900;line-height:1.1;letter-spacing:-0.5px;color:var(--ink);margin-bottom:10px;}
    .form-box .sub{font-size:0.93rem;color:var(--muted);line-height:1.65;margin-bottom:32px;}
    .msg-box{border-radius:10px;padding:14px 16px;font-size:0.88rem;line-height:1.5;margin-bottom:24px;display:flex;align-items:flex-start;gap:10px;}
    .msg-box.error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
    .msg-box.success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
    .msg-icon{font-size:1.1rem;flex-shrink:0;margin-top:1px;}
    .form-group{margin-bottom:22px;}
    label{display:block;font-size:0.85rem;font-weight:700;color:var(--ink2);margin-bottom:8px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:1rem;color:var(--muted);pointer-events:none;}
    input[type="email"]{width:100%;padding:13px 14px 13px 42px;border:1.5px solid var(--border);border-radius:10px;background:var(--cream);color:var(--ink);font-family:var(--font-b);font-size:0.95rem;font-weight:500;outline:none;transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;}
    input[type="email"]:focus{border-color:var(--orange);background:var(--white);box-shadow:0 0 0 3px rgba(232,96,10,0.12);}
    input[type="email"]::placeholder{color:#b5a89a;font-weight:400;}
    .btn-submit{width:100%;padding:14px;border-radius:100px;background:var(--orange);color:#fff;font-family:var(--font-b);font-size:1rem;font-weight:700;border:none;cursor:pointer;transition:background 0.2s,transform 0.15s;box-shadow:0 6px 20px rgba(232,96,10,0.3);margin-top:4px;display:flex;align-items:center;justify-content:center;gap:8px;}
    .btn-submit:hover{background:#d4540a;transform:translateY(-1px);}
    .or-divider{display:flex;align-items:center;gap:12px;margin:24px 0;color:var(--muted);font-size:0.82rem;}
    .or-divider::before,.or-divider::after{content:'';flex:1;height:1px;background:var(--border);}
    .bottom-links{text-align:center;font-size:0.87rem;color:var(--muted);}
    .bottom-links a{color:var(--orange);font-weight:700;text-decoration:none;}
    .btn-back-login{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;border-radius:100px;border:1.5px solid var(--border);background:transparent;color:var(--ink2);font-family:var(--font-b);font-size:0.93rem;font-weight:600;text-decoration:none;transition:border-color 0.2s,background 0.2s;}
    .btn-back-login:hover{border-color:var(--orange);background:rgba(232,96,10,0.04);}
    .success-state{text-align:center;padding:20px 0;display:none;}
    .success-state.show{display:block;}
    .success-icon-big{width:80px;height:80px;background:var(--green-lt);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.2rem;margin:0 auto 20px;border:2px solid #bbf7d0;}
    .success-state h3{font-family:var(--font-h);font-size:1.5rem;font-weight:900;margin-bottom:10px;}
    .success-state p{color:var(--muted);font-size:0.92rem;line-height:1.65;}
    .redirect-bar{margin-top:20px;background:var(--cream2);border-radius:8px;padding:10px 16px;font-size:0.82rem;color:var(--muted);}
    .spinner{width:18px;height:18px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;display:none;}
    @keyframes spin{to{transform:rotate(360deg);}}
    @media(max-width:860px){main{grid-template-columns:1fr;}.left-panel{display:none;}.right-panel{padding:40px 24px;}}
  </style>
</head>
<body>

<nav>
  <a href="index.html" class="logo">Skill<span>Connect</span></a>
  <a href="login.php" class="back-link">← Back to Login</a>
</nav>

<main>
  <div class="left-panel">
    <div class="panel-content">
      <div class="panel-icon">🔑</div>
      <h2>Reset Your<br/>Password</h2>
      <p>No worries! Follow these simple steps to get back into your account safely.</p>
      <ul class="steps-list">
        <li><span class="step-num-circle">1</span><span>Enter your registered email address</span></li>
        <li><span class="step-num-circle">2</span><span>OTP will be sent directly to your inbox</span></li>
        <li><span class="step-num-circle">3</span><span>Enter the OTP to verify your identity</span></li>
        <li><span class="step-num-circle">4</span><span>Set a new strong password and login</span></li>
      </ul>
    </div>
  </div>

  <div class="right-panel">
    <div class="form-box">

      <div class="form-tag">🔒 Account Recovery</div>
      <h1>Forgot Password?</h1>
      <p class="sub">Enter the email linked to your SkillConnect account. OTP will be sent directly to your email.</p>

      <?php if (!empty($message)): ?>
        <div class="msg-box <?= $msg_type ?>">
          <span class="msg-icon"><?= $msg_type === 'success' ? '✅' : '⚠️' ?></span>
          <span><?= $message ?></span>
        </div>
      <?php endif; ?>

      <div class="success-state" id="successState">
        <div class="success-icon-big">📧</div>
        <h3>OTP Sent!</h3>
        <p>A 6-digit OTP has been sent to your email. Please check your inbox and spam folder.</p>
        <div class="redirect-bar">⏳ Redirecting to OTP verification page in 3 seconds...</div>
      </div>

      <form method="POST" action="" id="forgotForm">
        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-wrap">
            <span class="input-icon">📧</span>
            <input type="email" id="email" name="email"
              placeholder="Enter your registered email"
              value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
              required autocomplete="email"/>
          </div>
        </div>
        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnText">Send OTP to Email</span>
          <span class="spinner" id="spinner"></span>
          <span id="btnArrow">→</span>
        </button>
      </form>

      <div class="or-divider">or</div>
      <a href="login.php" class="btn-back-login">← Back to Login</a>
      <div class="bottom-links" style="margin-top:20px;">
        Don't have an account? <a href="register.php">Sign up here</a>
      </div>

    </div>
  </div>
</main>

<script>
  const form       = document.getElementById('forgotForm');
  const submitBtn  = document.getElementById('submitBtn');
  const btnText    = document.getElementById('btnText');
  const btnArrow   = document.getElementById('btnArrow');
  const spinner    = document.getElementById('spinner');
  const emailInput = document.getElementById('email');

  form.addEventListener('submit', function(e) {
    const email = emailInput.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRegex.test(email)) {
      e.preventDefault();
      emailInput.style.borderColor = '#dc2626';
      emailInput.focus();
      return;
    }
    btnText.textContent    = 'Sending OTP...';
    btnArrow.style.display = 'none';
    spinner.style.display  = 'inline-block';
    submitBtn.disabled     = true;
  });

  emailInput.addEventListener('input', function() {
    this.style.borderColor = '';
  });

  <?php if ($msg_type === 'success'): ?>
    document.getElementById('forgotForm').style.display = 'none';
    document.getElementById('successState').classList.add('show');
  <?php endif; ?>
</script>

</body>
</html>
