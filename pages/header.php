<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

$user       = getUser();
$pageTitle  = $pageTitle  ?? 'OmnesEvent';
$activePage = $activePage ?? '';
$B          = BASE_URL;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?> — OmnesEvent</title>
  <link rel="stylesheet" href="<?= $B ?>/css/styles.css" />
</head>
<body>

<header class="header">
  <div class="header-inner">
    <a class="logo" href="<?= $B ?>/index.php">
      <div class="logo-icon">🎫</div>
      <span class="logo-text">Omnes<span>Event</span></span>
    </a>

    <nav class="navbar">
      <a href="<?= $B ?>/index.php" <?= $activePage === 'home' ? 'class="active"' : '' ?>>Accueil</a>

      <?php if ($user && in_array($user['role'], ['organisateur','admin'])): ?>
        <a href="<?= $B ?>/pages/create-event.php" <?= $activePage === 'create' ? 'class="active"' : '' ?>>Créer</a>
      <?php endif; ?>

      <?php if ($user && $user['role'] === 'participant'): ?>
        <a href="<?= $B ?>/pages/my-tickets.php" <?= $activePage === 'tickets' ? 'class="active"' : '' ?>>Mes billets</a>
      <?php endif; ?>

      <?php if ($user && in_array($user['role'], ['organisateur','admin'])): ?>
        <a href="<?= $B ?>/pages/organizer-dashboard.php" <?= $activePage === 'organizer' ? 'class="active"' : '' ?>>Organisateur</a>
      <?php endif; ?>

      <?php if ($user && $user['role'] === 'admin'): ?>
        <a href="<?= $B ?>/pages/admin-dashboard.php" <?= $activePage === 'admin' ? 'class="active"' : '' ?>>Admin</a>
      <?php endif; ?>

      <?php if ($user): ?>
        <a href="<?= $B ?>/pages/profile.php" <?= $activePage === 'profile' ? 'class="active"' : '' ?>>Profil</a>
      <?php endif; ?>
    </nav>

    <div class="header-right">
      <?php if ($user): ?>
        <a class="user-badge" href="<?= $B ?>/pages/profile.php">
          <div class="user-avatar"><?= htmlspecialchars($user['avatar'] ?? '?') ?></div>
          <span><?= htmlspecialchars(explode(' ', $user['nom'])[0]) ?></span>
          <span class="role-pill <?= $user['role'] ?>"><?= $user['role'] ?></span>
        </a>
        <a href="<?= $B ?>/pages/logout.php" class="btn btn-ghost btn-sm">Déco</a>
      <?php else: ?>
        <a href="<?= $B ?>/pages/login.php" class="btn btn-sm">Connexion</a>
      <?php endif; ?>

      <button class="menu-toggle"
              onclick="this.closest('.header').querySelector('.mobile-nav').classList.toggle('open')"
              aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <nav class="mobile-nav">
    <a href="<?= $B ?>/index.php">🏠 Accueil</a>
    <?php if ($user && in_array($user['role'], ['organisateur','admin'])): ?>
      <a href="<?= $B ?>/pages/create-event.php">➕ Créer un événement</a>
    <?php endif; ?>
    <?php if ($user && $user['role'] === 'participant'): ?>
      <a href="<?= $B ?>/pages/my-tickets.php">🎟️ Mes billets</a>
    <?php endif; ?>
    <?php if ($user && in_array($user['role'], ['organisateur','admin'])): ?>
      <a href="<?= $B ?>/pages/organizer-dashboard.php">📊 Organisateur</a>
    <?php endif; ?>
    <?php if ($user && $user['role'] === 'admin'): ?>
      <a href="<?= $B ?>/pages/admin-dashboard.php">⚙️ Admin</a>
    <?php endif; ?>
    <?php if ($user): ?>
      <a href="<?= $B ?>/pages/profile.php">👤 Profil</a>
      <a href="<?= $B ?>/pages/logout.php">🚪 Déconnexion</a>
    <?php else: ?>
      <a href="<?= $B ?>/pages/login.php">🔑 Connexion</a>
    <?php endif; ?>
  </nav>
</header>

<div class="toast-container" id="toastContainer"></div>
<script src="<?= $B ?>/js/app.js"></script>