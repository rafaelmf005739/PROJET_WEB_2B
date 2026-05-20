<?php
// ═══════════════════════════════════════════════════════
//  pages/login.php
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (getUser()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// ─── Gestion des tentatives ───────────────────────────
if (!isset($_SESSION['login_attempts']))  $_SESSION['login_attempts']  = 0;
if (!isset($_SESSION['login_last_fail'])) $_SESSION['login_last_fail'] = 0;

$waitSeconds   = 5;
$maxAttempts   = 3;
$error         = '';
$waitRemaining = 0;

if ($_SESSION['login_attempts'] >= $maxAttempts) {
    $elapsed = time() - $_SESSION['login_last_fail'];
    if ($elapsed < $waitSeconds) {
        $waitRemaining = $waitSeconds - $elapsed;
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($waitRemaining > 0) {
        $error = "Trop de tentatives. Veuillez attendre {$waitRemaining} seconde(s).";
    } else {
        $email = trim($_POST['email'] ?? '');
        $pwd   = trim($_POST['password'] ?? '');

        if ($email === '' || $pwd === '') {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND actif = 1 LIMIT 1");
            $stmt->execute([$email]);
            $u = $stmt->fetch();

            if ($u && password_verify($pwd, $u['mot_de_passe'])) {
                $_SESSION['login_attempts'] = 0;
                loginUser($u);
                header('Location: ' . BASE_URL . '/index.php');
                exit;
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['login_last_fail'] = time();
                $remaining = $maxAttempts - $_SESSION['login_attempts'];
                if ($_SESSION['login_attempts'] >= $maxAttempts) {
                    $error         = "Trop de tentatives. Attendez {$waitSeconds} secondes.";
                    $waitRemaining = $waitSeconds;
                } else {
                    $error = "Email ou mot de passe incorrect. Il vous reste $remaining tentative(s).";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connexion — OmnesEvent</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/styles.css" />
  <style>
    /* ── Corrections layout page login ── */
    .auth-card { width: 100%; max-width: 440px; }

    .demo-accounts { overflow: hidden; }

    .demo-btn-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .demo-btn {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding: 10px 14px;
      border-radius: var(--radius-sm);
      cursor: pointer;
      font-size: 0.85rem;
      font-weight: 500;
      border: 1.5px solid var(--border);
      background: white;
      transition: var(--transition);
      width: 100%;
      text-align: left;
      overflow: hidden;
    }

    .demo-btn:hover {
      border-color: var(--primary);
      background: #EFF6FF;
    }

    .demo-btn span:first-child {
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .demo-role {
      flex-shrink: 0;
    }
  </style>
</head>
<body>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-icon" style="width:48px;height:48px;border-radius:14px;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:24px">🎫</div>
      <span style="font-family:var(--font-display);font-size:1.6rem;font-weight:800">Omnes<span style="color:var(--primary)">Event</span></span>
    </div>

    <h2>Connexion</h2>
    <p class="subtitle">Accédez à la plateforme événementielle Omnes</p>

    <!-- Comptes de test -->
    <div class="demo-accounts">
      <p>Comptes de test — mot de passe : <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:0.82rem">password</code></p>
      <div class="demo-btn-group">
        <button class="demo-btn" onclick="fillLogin('admin@omnes.edu')">
          <span>admin@omnes.edu</span>
          <span class="demo-role role-pill admin">Admin</span>
        </button>
        <button class="demo-btn" onclick="fillLogin('orga@omnes.edu')">
          <span>orga@omnes.edu</span>
          <span class="demo-role role-pill organisateur">Organisateur</span>
        </button>
        <button class="demo-btn" onclick="fillLogin('emma@omnes.edu')">
          <span>emma@omnes.edu</span>
          <span class="demo-role role-pill participant">Participant</span>
        </button>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert-banner warning" style="margin-bottom:16px">
        ⚠️ <?= htmlspecialchars($error) ?>
        <?php if ($waitRemaining > 0): ?>
          <span id="countdown"> (<?= $waitRemaining ?>s)</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/pages/login.php" class="form-grid">
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="votreprenom@omnes.edu"
               autocomplete="email" required />
      </div>
      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password"
               placeholder="••••••••"
               autocomplete="current-password" required />
      </div>
      <button type="submit" class="btn" id="submitBtn"
              style="width:100%;margin-top:4px"
              <?= $waitRemaining > 0 ? 'disabled' : '' ?>>
        Se connecter →
      </button>
    </form>

    <p style="text-align:center;margin-top:16px;font-size:0.85rem;color:var(--gray-500)">
      Pas encore de compte ?
      <a href="<?= BASE_URL ?>/pages/register.php" style="color:var(--primary)">S'inscrire →</a>
    </p>
    <p style="text-align:center;margin-top:8px;font-size:0.85rem;color:var(--gray-500)">
      Vous souhaitez organiser des événements ?
      <a href="<?= BASE_URL ?>/pages/request-organizer.php" style="color:var(--primary)">Faire une demande →</a>
    </p>

  </div>
</div>

<script>
function fillLogin(email) {
    document.getElementById('email').value    = email;
    document.getElementById('password').value = 'password';
}

<?php if ($waitRemaining > 0): ?>
let remaining = <?= $waitRemaining ?>;
const btn       = document.getElementById('submitBtn');
const countdown = document.getElementById('countdown');
const timer = setInterval(() => {
    remaining--;
    if (countdown) countdown.textContent = ' (' + remaining + 's)';
    if (remaining <= 0) {
        clearInterval(timer);
        btn.disabled    = false;
        btn.textContent = 'Se connecter →';
        if (countdown) countdown.textContent = '';
    }
}, 1000);
<?php endif; ?>
</script>

</body>
</html>