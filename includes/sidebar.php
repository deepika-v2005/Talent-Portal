<?php
// ── SIDEBAR COMPONENT ──
$avatar_letter = strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1));
$user_name     = $_SESSION['user_name'] ?? 'Worker';
$first_name    = explode(' ', $user_name)[0];

$nav_items = [
    ['id' => 'home',         'icon' => '🏠', 'label' => 'Dashboard',       'href' => 'dashboard.php'],
    ['id' => 'feed',         'icon' => '📰', 'label' => 'Explore',             'href' => 'feed.php'],
    ['id' => 'job_seekers',  'icon' => '👥', 'label' => 'Job Seekers',      'href' => 'job_seekers.php'],
    ['id' => 'profile',      'icon' => '👤', 'label' => 'My Profile',       'href' => 'profile.php'],
    ['id' => 'browse',       'icon' => '💼', 'label' => 'Browse Jobs',      'href' => 'browse_jobs.php'],
    ['id' => 'applications', 'icon' => '📋', 'label' => 'My Applications',  'href' => 'applications.php'],
    ['id' => 'saved',        'icon' => '🔖', 'label' => 'Saved Jobs',       'href' => 'saved_jobs.php'],
    ['id' => 'recommended',  'icon' => '✨', 'label' => 'Recommended',      'href' => 'recommended.php'],
    ['id' => 'network',      'icon' => '🔗', 'label' => 'Network',          'href' => 'network.php'],
    ['id' => 'portfolio',    'icon' => '🖼️', 'label' => 'Portfolio',        'href' => 'portfolio.php'],
    ['id' => 'ratings',      'icon' => '⭐', 'label' => 'Ratings',          'href' => 'ratings.php'],
    ['id' => 'skills',       'icon' => '📊', 'label' => 'Skill Progress',   'href' => 'skill_progress.php'],
    ['id' => 'chat',         'icon' => '💬', 'label' => 'Chat',             'href' => 'chat.php'],
    ['id' => 'notifications','icon' => '🔔', 'label' => 'Notifications',    'href' => 'notifications.php'],
    ['id' => 'settings',     'icon' => '⚙️', 'label' => 'Settings',         'href' => 'settings.php'],
];

$active_page = $active_page ?? 'home';
?>

<aside class="sidebar">
  <div class="sb-logo">
    <a href="dashboard.php">Skill<span>Connect</span></a>
  </div>
  <div class="sb-user">
    <div class="sb-avatar"><?= $avatar_letter ?></div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars($first_name) ?></div>
      <div class="sb-urole">🎯 Job Seeker</div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="nav-section">Menu</div>
    <?php foreach ($nav_items as $item): ?>
      <a href="<?= $item['href'] ?>" class="<?= $active_page === $item['id'] ? 'active' : '' ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <?= $item['label'] ?>
        <?php if ($item['id'] === 'notifications'): ?>
          <?php if (isset($conn)) {
              $uid = (int)$_SESSION['user_id'];
              $nb  = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0");
              $nc  = $nb ? $nb->fetch_assoc()['c'] : 0;
              if ($nc > 0) echo "<span class='sb-badge'>$nc</span>";
          } ?>
        <?php endif; ?>
        <?php if ($item['id'] === 'chat'): ?>
          <?php if (isset($conn)) {
              $uid = (int)$_SESSION['user_id'];
              $mb  = $conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND is_read=0");
              $mc  = $mb ? $mb->fetch_assoc()['c'] : 0;
              if ($mc > 0) echo "<span class='sb-badge'>$mc</span>";
          } ?>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sb-logout">
    <a href="../logout.php"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;display:block;margin-right:4px;"><path d="M6 3h9v18H6z"/><path d="M15 3.5l3 1v15l-3 1"/><circle cx="11.5" cy="12" r="0.9" fill="currentColor" stroke="none"/></svg>Logout</a>
  </div>
</aside>
