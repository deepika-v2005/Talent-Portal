<?php
session_start();

if (!isset($_SESSION["reset_email"])) {
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["verify_otp"])) {
    $entered_otp = trim($_POST["otp"]);
    $now         = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("SELECT reset_otp, otp_expiry FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if ($now > $row['otp_expiry']) {
            $message  = "OTP has expired. Please request a new one.";
            $msg_type = "error";
        } elseif ($entered_otp != $row['reset_otp']) {
            $message  = "Invalid OTP. Please check and try again.";
            $msg_type = "error";
        } else {
            $_SESSION["otp_verified"] = true;
            header("Location: reset_password.php");
            exit();
        }
    }
    $stmt->close();
}

// Resend OTP
if (isset($_POST["resend_otp"])) {
    header("Location: forgot_password.php");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Verify OTP – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;--ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;--orange:#7C3AED;--teal:#7C3AED;--teal-lt:#ede9fe;--border:#DDD6FE;--red:#dc2626;--red-lt:#fef2f2;--green:#16a34a;--green-lt:#f0fdf4;--font-h:'Poppins', sans-serif;--font-b:'Poppins', sans-serif;}
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);min-height:100vh;display:flex;flex-direction:column;}
    nav{display:flex;align-items:center;justify-content:space-between;padding:0 6%;height:64px;background:rgba(250,247,242,0.95);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
    .logo{font-family:var(--font-h);font-size:1.35rem;font-weight:900;color:var(--ink);text-decoration:none;}
    .logo span{color:var(--orange);}
    .back-link{font-size:0.87rem;font-weight:600;color:var(--muted);text-decoration:none;}
    .back-link:hover{color:var(--orange);}
    main{flex:1;display:grid;grid-template-columns:1fr 1fr;min-height:calc(100vh - 64px);}
    .left-panel{background:linear-gradient(145deg,var(--orange) 0%,#9F67FA 100%);display:flex;flex-direction:column;justify-content:center;padding:60px 56px;position:relative;overflow:hidden;}
    .left-panel::before{content:'';position:absolute;top:-80px;right:-80px;width:300px;height:300px;background:rgba(255,255,255,0.08);border-radius:50%;}
    .panel-content{position:relative;z-index:1;}
    .panel-icon{width:72px;height:72px;background:rgba(255,255,255,0.2);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:28px;border:1px solid rgba(255,255,255,0.3);}
    .left-panel h2{font-family:var(--font-h);font-size:2.2rem;font-weight:900;color:#fff;line-height:1.15;margin-bottom:16px;}
    .left-panel p{color:rgba(255,255,255,0.82);font-size:0.97rem;line-height:1.7;max-width:320px;margin-bottom:28px;}
    .email-badge{background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);border-radius:10px;padding:14px 18px;color:#fff;font-size:0.9rem;backdrop-filter:blur(8px);}
    .email-badge strong{display:block;font-size:0.75rem;opacity:0.7;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;}
    .right-panel{display:flex;align-items:center;justify-content:center;padding:40px 48px;background:var(--white);}
    .form-box{width:100%;max-width:420px;}
    .form-tag{display:inline-flex;align-items:center;gap:7px;background:var(--teal-lt);border:1px solid rgba(124,58,237,0.2);border-radius:100px;padding:5px 14px;font-size:0.75rem;font-weight:700;color:var(--teal);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:22px;}
    .form-box h1{font-family:var(--font-h);font-size:2rem;font-weight:900;line-height:1.1;color:var(--ink);margin-bottom:10px;}
    .form-box .sub{font-size:0.93rem;color:var(--muted);line-height:1.65;margin-bottom:32px;}
    .form-box .sub strong{color:var(--orange);}
    .msg-box{border-radius:10px;padding:14px 16px;font-size:0.88rem;line-height:1.5;margin-bottom:24px;display:flex;align-items:flex-start;gap:10px;}
    .msg-box.error{background:var(--red-lt);border:1px solid #fecaca;color:var(--red);}
    .msg-box.success{background:var(--green-lt);border:1px solid #bbf7d0;color:var(--green);}
    /* OTP Boxes */
    .otp-label{font-size:0.85rem;font-weight:700;color:var(--ink2);margin-bottom:14px;display:block;}
    .otp-wrap{display:flex;gap:10px;justify-content:center;margin-bottom:8px;}
    .otp-box{width:52px;height:60px;border:2px solid var(--border);border-radius:12px;text-align:center;font-size:22px;font-weight:700;color:var(--orange);outline:none;transition:border-color 0.2s,background 0.2s;background:var(--cream);font-family:var(--font-b);}
    .otp-box:focus{border-color:var(--orange);background:#fff3ec;box-shadow:0 0 0 3px rgba(232,96,10,0.12);}
    .otp-box.filled{border-color:var(--orange);background:#fff3ec;}
    #timer-txt{font-size:13px;color:var(--muted);text-align:center;margin:12px 0 20px;}
    #timer-txt span{font-weight:700;color:var(--orange);}
    #timer-txt.expired span{color:var(--red);}
    input[type="hidden"]{}
    .btn-verify{width:100%;padding:14px;border-radius:100px;background:var(--orange);color:#fff;font-family:var(--font-b);font-size:1rem;font-weight:700;border:none;cursor:pointer;transition:background 0.2s,transform 0.15s;box-shadow:0 6px 20px rgba(232,96,10,0.3);display:flex;align-items:center;justify-content:center;gap:8px;}
    .btn-verify:hover{background:#d4540a;transform:translateY(-1px);}
    .btn-resend{width:100%;padding:13px;border-radius:100px;border:1.5px solid var(--border);background:transparent;color:var(--ink2);font-family:var(--font-b);font-size:0.93rem;font-weight:600;cursor:pointer;transition:border-color 0.2s,background 0.2s;margin-top:12px;}
    .btn-resend:hover{border-color:var(--orange);background:rgba(232,96,10,0.04);}
    .btn-resend:disabled{opacity:0.4;cursor:not-allowed;}
    .or-divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--muted);font-size:0.82rem;}
    .or-divider::before,.or-divider::after{content:'';flex:1;height:1px;background:var(--border);}
    .bottom-links{text-align:center;font-size:0.87rem;color:var(--muted);}
    .bottom-links a{color:var(--orange);font-weight:700;text-decoration:none;}
    .spinner{width:18px;height:18px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;display:none;}
    @keyframes spin{to{transform:rotate(360deg);}}
    @media(max-width:860px){main{grid-template-columns:1fr;}.left-panel{display:none;}.right-panel{padding:40px 24px;}}
  </style>
</head>
<body>

<nav>
  <a href="index.html" class="logo">Skill<span>Connect</span></a>
  <a href="forgot_password.php" class="back-link">← Back</a>
</nav>

<main>
  <div class="left-panel">
    <div class="panel-content">
      <div class="panel-icon">📧</div>
      <h2>Check Your<br/>Email</h2>
      <p>We sent a 6-digit OTP to your registered email address. Enter it to verify your identity.</p>
      <div class="email-badge">
        <strong>OTP sent to</strong>
        <?= htmlspecialchars($email) ?>
      </div>
    </div>
  </div>

  <div class="right-panel">
    <div class="form-box">

      <div class="form-tag">📩 OTP Verification</div>
      <h1>Enter OTP</h1>
      <p class="sub">A 6-digit OTP was sent to <strong><?= htmlspecialchars($email) ?></strong>. Valid for 15 minutes.</p>

      <?php if (!empty($message)): ?>
        <div class="msg-box <?= $msg_type ?>">
          <span><?= $msg_type === 'error' ? '⚠️' : '✅' ?></span>
          <span><?= $message ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="otpForm">
        <span class="otp-label">Enter 6-Digit OTP</span>
        <div class="otp-wrap">
          <?php for($i=1;$i<=6;$i++): ?>
          <input type="text" class="otp-box" maxlength="1" id="box<?=$i?>" inputmode="numeric" autocomplete="off">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="otp" id="otp_real">
        <input type="hidden" name="verify_otp" value="1">

        <div id="timer-txt">OTP expires in <span id="countdown">15:00</span></div>

        <button type="submit" class="btn-verify" id="verifyBtn">
          <span id="btnText">Verify OTP</span>
          <span class="spinner" id="spinner"></span>
          <span id="btnArrow">→</span>
        </button>
      </form>

      <form method="POST" action="">
        <input type="hidden" name="resend_otp" value="1">
        <button type="submit" class="btn-resend" id="resendBtn" disabled>
          🔄 Resend OTP <span id="resendTimer">(wait 60s)</span>
        </button>
      </form>

      <div class="or-divider">or</div>
      <div class="bottom-links">
        <a href="forgot_password.php">← Change Email</a> &nbsp;·&nbsp;
        <a href="login.php">Back to Login</a>
      </div>

    </div>
  </div>
</main>

<script>
// ── OTP boxes ──────────────────────────────────
const boxes    = document.querySelectorAll('.otp-box');
const otpReal  = document.getElementById('otp_real');

boxes.forEach((box, idx) => {
    box.addEventListener('input', () => {
        box.value = box.value.replace(/[^0-9]/g, '');
        if (box.value) { box.classList.add('filled'); if (idx < 5) boxes[idx+1].focus(); }
        else box.classList.remove('filled');
        combineOtp();
    });
    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && idx > 0) { boxes[idx-1].focus(); boxes[idx-1].classList.remove('filled'); }
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
        [...text].slice(0, 6).forEach((ch, i) => { if (boxes[i]) { boxes[i].value = ch; boxes[i].classList.add('filled'); } });
        boxes[Math.min(text.length, 5)].focus();
        combineOtp();
    });
});

function combineOtp() {
    otpReal.value = [...boxes].map(b => b.value).join('');
}

// ── OTP Countdown (15 min) ────────────────────
(function() {
    const el = document.getElementById('countdown');
    const timerTxt = document.getElementById('timer-txt');
    let seconds = 15 * 60;
    const t = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            clearInterval(t);
            el.textContent = '00:00';
            timerTxt.classList.add('expired');
            timerTxt.innerHTML = '⚠️ OTP has <span>expired</span>. Please resend.';
            return;
        }
        const m = String(Math.floor(seconds / 60)).padStart(2, '0');
        const s = String(seconds % 60).padStart(2, '0');
        el.textContent = m + ':' + s;
        if (seconds < 60) el.style.color = '#dc2626';
    }, 1000);
})();

// ── Resend button countdown (60s) ─────────────
(function() {
    const btn = document.getElementById('resendBtn');
    const lbl = document.getElementById('resendTimer');
    let sec = 60;
    const t = setInterval(() => {
        sec--;
        lbl.textContent = sec > 0 ? '(wait ' + sec + 's)' : '';
        if (sec <= 0) {
            clearInterval(t);
            btn.disabled = false;
            lbl.textContent = '';
        }
    }, 1000);
})();

// ── Submit spinner ─────────────────────────────
document.getElementById('otpForm').addEventListener('submit', function() {
    document.getElementById('btnText').textContent = 'Verifying...';
    document.getElementById('btnArrow').style.display = 'none';
    document.getElementById('spinner').style.display = 'inline-block';
    document.getElementById('verifyBtn').disabled = true;
});
</script>
</body>
</html>