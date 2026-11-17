<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'worker') {
    header("Location: ../login.php"); exit();
}

require_once '../includes/db.php';
$user_id = (int)$_SESSION['user_id'];

// ── SIDEBAR VARIABLES (needed by embedded sidebar) ──
$conn2 = new mysqli("localhost", "root", "", "skillconnect");
$profile       = $conn2->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pending       = (int)$conn2->query("SELECT COUNT(*) as c FROM applications WHERE worker_id=$user_id AND status='pending'")->fetch_assoc()['c'];
$saved_count   = (int)$conn2->query("SELECT COUNT(*) as c FROM saved_jobs WHERE worker_id=$user_id")->fetch_assoc()['c'];
$unread_notif  = (int)$conn2->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$unread_msgs   = (int)$conn2->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$conn2->close();
$avatar_letter = strtoupper(substr($profile['name'] ?? $_SESSION['user_name'], 0, 1));
$first_name    = explode(' ', $profile['name'] ?? $_SESSION['user_name'])[0];
// ── END SIDEBAR VARIABLES ──


// ── SUBMIT REVIEW (worker reviews a company/client) ──
$success = '';
$error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $client_id = (int)$_POST['client_id'];
    $stars     = min(5, max(1, (int)$_POST['stars']));
    $review    = trim($conn->real_escape_string($_POST['review_text']));

    // Only allow if they have a completed application
    $check = $conn->query("
        SELECT a.id FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.worker_id=$user_id AND j.client_id=$client_id AND a.status='accepted'
        LIMIT 1
    ");
    if ($check->num_rows === 0) {
        $error = 'You can only review clients after completing a job with them.';
    } else {
        $existing = $conn->query("SELECT id FROM ratings WHERE worker_id=$user_id AND client_id=$client_id");
        if ($existing->num_rows > 0) {
            $conn->query("UPDATE ratings SET stars=$stars, review='$review', created_at=NOW()
                          WHERE worker_id=$user_id AND client_id=$client_id");
        } else {
            $conn->query("INSERT INTO ratings (worker_id, client_id, stars, review, created_at)
                          VALUES ($user_id, $client_id, $stars, '$review', NOW())");
        }
        $success = 'Review submitted successfully!';
    }
}

// ── FETCH REVIEWS ON ME (from clients) ──
$my_reviews = $conn->query("
    SELECT r.*, u.name AS reviewer_name, u.location AS reviewer_loc
    FROM ratings r
    JOIN users u ON u.id = r.client_id
    WHERE r.worker_id = $user_id
    ORDER BY r.created_at DESC
");

// ── MY RATING SUMMARY ──
$summary = $conn->query("SELECT AVG(stars) as avg, COUNT(*) as total FROM ratings WHERE worker_id=$user_id")->fetch_assoc();
$avg_rating = $summary['avg'] ? round($summary['avg'], 1) : 0;
$total_reviews = (int)$summary['total'];

// Star distribution
$star_dist = [];
for ($i = 5; $i >= 1; $i--) {
    $cnt = $conn->query("SELECT COUNT(*) as c FROM ratings WHERE worker_id=$user_id AND stars=$i")->fetch_assoc()['c'];
    $star_dist[$i] = (int)$cnt;
}

// ── CLIENTS I CAN REVIEW ──
$reviewable = $conn->query("
    SELECT DISTINCT u.id, u.name, u.location,
           (SELECT stars FROM ratings WHERE worker_id=$user_id AND client_id=u.id LIMIT 1) AS my_rating
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON u.id = j.client_id
    WHERE a.worker_id=$user_id AND a.status='accepted'
    ORDER BY u.name ASC
");

$conn->close();
$active_page = 'ratings';

function stars_html($n, $max=5) {
    $out = '';
    for ($i = 1; $i <= $max; $i++) {
        $out .= $i <= $n ? '★' : '☆';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Ratings & Reviews – SkillConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --cream:#F5F3FF;--cream2:#EDE9FE;--white:#fff;
      --ink:#0B1F3A;--ink2:#1E3A5F;--muted:#6B7DB3;
      --orange:#7C3AED;--orange2:#9F67FA;
      --teal:#7C3AED;--teal-lt:#ede9fe;
      --border:#DDD6FE;
      --green:#16a34a;--green-lt:#f0fdf4;
      --yellow:#d97706;
      --sidebar:240px;
      --shadow:0 2px 12px rgba(11,31,58,0.07);
      --font-h:'Poppins', sans-serif;
      --font-b:'Poppins', sans-serif;
      --radius:14px;
    }
    body{background:var(--cream);font-family:var(--font-b);color:var(--ink);min-height:100vh;display:flex;}
    /* ── Dark mode (syncs with Settings > Dark Mode) ── */
    body.dark-mode{
      --cream:#0B1220;--cream2:#0B1220;--white:#111827;
      --ink:#E2E8F0;--ink2:#CBD5E1;--muted:#94A3B8;
      --border:#1E293B;--teal-lt:rgba(124,58,237,.18);--green-lt:rgba(22,163,74,.14);
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

    body.dark-mode [style*="background:#fef2f2"]{background:rgba(220,38,38,.14) !important;border-color:#7f1d1d !important;}
.stars{color:#f59e0b;font-size:1.1rem;letter-spacing:1px;}
    .stars-sm{color:#f59e0b;font-size:0.9rem;}
    .star-select{display:flex;gap:6px;flex-direction:row-reverse;justify-content:flex-end;}
    .star-select input{display:none;}
    .star-select label{font-size:1.8rem;cursor:pointer;color:#e4ddd1;transition:color 0.15s;}
    .star-select input:checked ~ label,
    .star-select label:hover,
    .star-select label:hover ~ label{color:#f59e0b;}

    .review-card{
      background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
      padding:20px;margin-bottom:14px;box-shadow:var(--shadow);
    }
    .reviewer-name{font-weight:700;color:var(--ink2);font-size:0.9rem;}
    .reviewer-loc{font-size:0.78rem;color:var(--muted);}
    .review-text{font-size:0.88rem;color:var(--ink2);line-height:1.6;margin-top:8px;}

    .bar-row{display:flex;align-items:center;gap:10px;margin-bottom:6px;}
    .bar-bg{flex:1;height:8px;border-radius:100px;background:var(--cream2);overflow:hidden;}
    .bar-fill{height:100%;border-radius:100px;background:#f59e0b;transition:width 0.8s ease;}

    .field-label{font-size:0.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:block;}
    .field-input{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:var(--font-b);font-size:0.9rem;background:var(--cream);color:var(--ink);outline:none;transition:border-color 0.2s;}
    .field-input:focus{border-color:var(--orange);}
  
    /* ═══ SIDEBAR CSS ═══ */
    .sidebar{width:240px;height:100vh;background:#0B1F3A;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:200;overflow-y:auto;transition:transform .28s ease;}
    .sb-logo{padding:20px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logo a{font-family:var(--font-h);font-size:1.2rem;font-weight:900;color:#fff;text-decoration:none;}
    .sb-logo span{color:#9F67FA;}
    .sb-user{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:11px;flex-shrink:0;}
    .sb-ava{width:40px;height:40px;border-radius:11px;flex-shrink:0;background:#7C3AED;color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:1rem;font-weight:900;overflow:hidden;}
    .sb-ava img{width:40px;height:40px;object-fit:cover;}
    .sb-uname{font-size:.86rem;font-weight:400;color:#fff;line-height:1.3;}
    .sb-urole{font-size:.7rem;color:rgba(255,255,255,.42);margin-top:1px;}
    .sb-nav{flex:1;padding:10px 0;overflow-y:auto;}
    .nav-section{font-size:.62rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.2);padding:12px 20px 5px;}
    .sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 20px;font-size:.84rem;font-weight:400;color:rgba(255,255,255,.52);text-decoration:none;border-left:3px solid transparent;transition:all .18s;position:relative;}
    .sb-nav a:hover{color:#fff;background:rgba(255,255,255,.05);border-left-color:rgba(255,255,255,.18);}
    .sb-nav a.active{color:#fff;background:rgba(232,96,10,.15);border-left-color:#7C3AED;}
    .nav-ic{font-size:1rem;width:18px;text-align:center;flex-shrink:0;}.nav-ic img{filter:brightness(0) invert(1);}
    .nav-bdg{margin-left:auto;background:#7C3AED;color:#fff;font-size:.6rem;font-weight:800;padding:2px 6px;border-radius:100px;min-width:17px;text-align:center;flex-shrink:0;}
    .sb-logout{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0;}
    .sb-logout a{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:9px;background:rgba(220,38,38,.11);border:1px solid rgba(220,38,38,.18);color:#f87171;font-size:.83rem;font-weight:400;text-decoration:none;transition:background .2s;}.sb-logout a img{filter:brightness(0) invert(1);}
    .sb-logout a:hover{background:rgba(220,38,38,.2);}
    .main-wrap{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh;}
    .topbar{height:60px;padding:0 26px;background:var(--white);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .tb-left{display:flex;align-items:center;gap:9px;}
    .hamburger{display:none;width:33px;height:33px;border-radius:8px;background:var(--cream2);border:1px solid var(--border);align-items:center;justify-content:center;font-size:1rem;cursor:pointer;flex-shrink:0;color:var(--ink);}
    .tb-crumb{font-size:.85rem;color:var(--muted);}
    .tb-crumb strong{color:var(--ink);font-weight:400;}
    .tb-right{display:flex;align-items:center;gap:9px;}
    .tb-btn{position:relative;width:35px;height:35px;border-radius:9px;background:var(--cream2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;text-decoration:none;transition:background .18s;cursor:pointer;}
    .tb-btn:hover{background:var(--border);}
    .tb-dot{position:absolute;top:-3px;right:-3px;background:#7C3AED;color:#fff;font-size:.56rem;font-weight:800;min-width:15px;height:15px;border-radius:100px;display:flex;align-items:center;justify-content:center;border:2px solid var(--white);padding:0 2px;}
    .tb-ava{width:35px;height:35px;border-radius:9px;background:#7C3AED;color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-size:.9rem;font-weight:900;text-decoration:none;overflow:hidden;}
    .tb-ava img{width:35px;height:35px;object-fit:cover;}
    .page-content{padding:24px 26px;flex:1;}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;}
    .sb-overlay.show{display:block;}
    .rating-layout{display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;}
    @media(max-width:768px){
      .sidebar{transform:translateX(-240px);}
      .sidebar.open{transform:translateX(0);}
      .main-wrap{margin-left:0;}
      .hamburger{display:flex;color:var(--ink);}
      .page-content{padding:14px;}
      .rating-layout{grid-template-columns:1fr;}
    }

  </style>
</head>
<body>
<!-- SIDEBAR -->
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
    <a href="dashboard.php" ><span class="nav-ic"><img src="../assets/icons/dashboard (3).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Dashboard</a>
    <a href="browse_jobs.php" ><span class="nav-ic"><img src="../assets/icons/browse_jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Browse Jobs</a>
    <a href="applications.php" >
      <span class="nav-ic"><img src="../assets/icons/application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Applications
      <?php if ($pending > 0): ?><span class="nav-bdg"><?= $pending ?></span><?php endif; ?>
    </a>
    <a href="saved_jobs.php" >
      <span class="nav-ic"><img src="../assets/icons/saved jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Saved Jobs
      <?php if ($saved_count > 0): ?><span class="nav-bdg"><?= $saved_count ?></span><?php endif; ?>
    </a>
    <a href="recommended.php" ><span class="nav-ic"><img src="../assets/icons/recommend jobs.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Recommended</a>
    <div class="nav-section">Connect</div>
    <a href="network.php" ><span class="nav-ic"><img src="../assets/icons/network.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Network</a>
    <a href="portfolio.php" ><span class="nav-ic"><img src="../assets/icons/total count.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Portfolio</a>
    <a href="chat.php" >
      <span class="nav-ic"><img src="../assets/icons/chat.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Chat
      <?php if ($unread_msgs > 0): ?><span class="nav-bdg"><?= $unread_msgs ?></span><?php endif; ?>
    </a>
    <a href="notifications.php" >
      <span class="nav-ic"><img src="../assets/icons/notifications.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Notifications
      <?php if ($unread_notif > 0): ?><span class="nav-bdg"><?= $unread_notif ?></span><?php endif; ?>
    </a>
    <div class="nav-section">Profile</div>
    <a href="profile.php" ><span class="nav-ic"><img src="../assets/icons/my application.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> My Profile</a>
    <a href="skill_progress.php" ><span class="nav-ic"><img src="../assets/icons/skill program.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Skill Progress</a>
    <a href="ratings.php" class="active"><span class="nav-ic"><img src="../assets/icons/rating.png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Ratings</a>
    <a href="settings.php" ><span class="nav-ic"><img src="../assets/icons/settings (2).png" alt="" style="width:20px;height:20px;object-fit:cover;border-radius:5px;display:block;"></span> Settings</a>
  </nav>
  <div class="sb-logout">
    <a href="../logout.php"><img src="../assets/icons/logout.png" alt="" style="width:18px;height:18px;object-fit:cover;border-radius:4px;margin-right:4px;">Logout</a>
  </div>
</div>
<div class="sb-overlay" id="overlay" onclick="closeSidebar()"></div>




<div class="main-wrap">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px;">
      <div class="hamburger" onclick="openSidebar()">☰</div> <img src="../assets/icons/rating.png" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:6px;">
      <div class="topbar-title"><span class="tb-crumb"><a href="dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a> / <strong>Ratings & Reviews</strong></span></div>
    </div>
  </div>

  <div class="page-content">

    <?php if ($success): ?>
      <div style="background:var(--green-lt);border:1px solid #86efac;color:var(--green);
                  padding:14px 20px;border-radius:var(--radius);margin-bottom:20px;font-weight:600;">
        ✅ <?= $success ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;
                  padding:14px 20px;border-radius:var(--radius);margin-bottom:20px;font-weight:600;">
        ❌ <?= $error ?>
      </div>
    <?php endif; ?>

    <div class="rating-layout">

      <!-- ── LEFT: Rating Summary ── -->
      <div>
        <!-- Overall Rating Card -->
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                    padding:24px;text-align:center;margin-bottom:20px;box-shadow:var(--shadow);">
          <div style="font-family:var(--font-h);font-size:3.5rem;font-weight:900;color:var(--ink);line-height:1;">
            <?= $avg_rating ?: '—' ?>
          </div>
          <div class="stars" style="font-size:1.4rem;margin:8px 0;">
            <?php
              $full  = floor($avg_rating);
              $half  = ($avg_rating - $full) >= 0.5;
              for ($i=1;$i<=5;$i++) {
                if ($i<=$full) echo '★';
                elseif ($i==$full+1 && $half) echo '⭐';
                else echo '☆';
              }
            ?>
          </div>
          <div style="font-size:0.85rem;color:var(--muted);font-weight:600;">
            <?= $total_reviews ?> review<?= $total_reviews!=1?'s':'' ?>
          </div>
        </div>

        <!-- Star Distribution -->
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                    padding:20px;margin-bottom:20px;box-shadow:var(--shadow);">
          <div style="font-family:var(--font-h);font-size:0.95rem;font-weight:400;margin-bottom:14px;">
            Rating Breakdown
          </div>
          <?php for ($s=5;$s>=1;$s--):
            $cnt = $star_dist[$s];
            $pct = $total_reviews > 0 ? round(($cnt/$total_reviews)*100) : 0;
          ?>
            <div class="bar-row">
              <span style="font-size:0.8rem;font-weight:700;color:var(--muted);width:14px;text-align:right;">
                <?= $s ?>
              </span>
              <span style="color:#f59e0b;font-size:0.9rem;">★</span>
              <div class="bar-bg">
                <div class="bar-fill" data-w="<?= $pct ?>%" style="width:0;"></div>
              </div>
              <span style="font-size:0.78rem;font-weight:700;color:var(--muted);width:28px;text-align:right;">
                <?= $cnt ?>
              </span>
            </div>
          <?php endfor; ?>
        </div>

        <!-- Write a Review (for completed jobs) -->
        <?php if ($reviewable && $reviewable->num_rows > 0): ?>
          <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                      padding:20px;box-shadow:var(--shadow);">
            <div style="font-family:var(--font-h);font-size:0.95rem;font-weight:900;margin-bottom:16px;">
              ✍️ Review a Client
            </div>
            <form method="POST">
              <div style="margin-bottom:12px;">
                <label class="field-label">Select Client</label>
                <select name="client_id" class="field-input">
                  <?php while ($c = $reviewable->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>">
                      <?= htmlspecialchars($c['name']) ?>
                      <?= $c['my_rating'] ? ' (Reviewed ★'.$c['my_rating'].')' : '' ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div style="margin-bottom:12px;">
                <label class="field-label">Your Rating</label>
                <div class="star-select">
                  <?php for ($s=5;$s>=1;$s--): ?>
                    <input type="radio" name="stars" id="star<?= $s ?>" value="<?= $s ?>"
                           <?= $s===5?'checked':'' ?>>
                    <label for="star<?= $s ?>">★</label>
                  <?php endfor; ?>
                </div>
              </div>

              <div style="margin-bottom:14px;">
                <label class="field-label">Your Review</label>
                <textarea name="review_text" rows="3" class="field-input"
                          placeholder="Describe your experience working with this client..."></textarea>
              </div>

              <button type="submit" name="submit_review"
                      style="width:100%;padding:10px;background:var(--orange);color:#fff;border:none;
                             border-radius:100px;font-family:var(--font-b);font-size:0.88rem;font-weight:700;
                             cursor:pointer;transition:background 0.2s;"
                      onmouseenter="this.style.background='#d4540a'"
                      onmouseleave="this.style.background='var(--orange)'">
                Submit Review
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- ── RIGHT: Reviews Received ── -->
      <div>
        <div style="font-family:var(--font-h);font-size:1.1rem;font-weight:400;margin-bottom:18px;">
          Reviews I Received
        </div>

        <?php if ($my_reviews && $my_reviews->num_rows > 0): ?>
          <?php while ($r = $my_reviews->fetch_assoc()): ?>
            <div class="review-card">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                <div>
                  <div class="reviewer-name">
                    🏢 <?= htmlspecialchars($r['reviewer_name']) ?>
                  </div>
                  <div class="reviewer-loc">
                    📍 <?= htmlspecialchars($r['reviewer_loc'] ?? '') ?>
                  </div>
                </div>
                <div>
                  <div class="stars-sm"><?= stars_html($r['stars']) ?></div>
                  <div style="font-size:0.72rem;color:var(--muted);font-weight:600;margin-top:2px;text-align:right;">
                    <?= date('d M Y', strtotime($r['created_at'])) ?>
                  </div>
                </div>
              </div>
              <?php if (!empty($r['review'])): ?>
                <div class="review-text">"<?= htmlspecialchars($r['review']) ?>"</div>
              <?php endif; ?>
            </div>
          <?php endwhile; ?>

        <?php else: ?>
          <div style="text-align:center;padding:50px 20px;color:var(--muted);
                      background:var(--white);border:1px solid var(--border);border-radius:var(--radius);">
            <div style="margin-bottom:12px;"><img src="../assets/icons/rating.png" alt="" style="width:45px;height:45px;object-fit:contain;"></div>
            <h3 style="font-family:var(--font-h);color:var(--ink2);margin-bottom:8px;">No reviews yet</h3>
            <p>Complete jobs to start receiving ratings from clients.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
// Animate rating bars on load
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    document.querySelectorAll('.bar-fill').forEach(b => {
      b.style.width = b.dataset.w;
    });
  }, 300);
});
</script>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
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
