<?php
// ═══════════════════════════════════════════════════════
//  pages/register.php — Inscription participant
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Déjà connecté → accueil
if (getUser()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom    = trim($_POST['nom']       ?? '');
    $email  = trim($_POST['email']     ?? '');
    $pwd    = trim($_POST['password']  ?? '');
    $pwd2   = trim($_POST['password2'] ?? '');

    // Validation
    if (!$nom)              $errors[] = 'Le nom est obligatoire.';
    if (!$email)            $errors[] = 'L\'email est obligatoire.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format d\'email invalide.';
    if (strlen($pwd) < 6)   $errors[] = 'Le mot de passe doit faire au moins 6 caractères.';
    if ($pwd !== $pwd2)     $errors[] = 'Les deux mots de passe ne correspondent pas.';

    // Email déjà utilisé
    if (empty($errors)) {
        $pdo = getPDO();
        $chk = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) $errors[] = 'Cet email est déjà utilisé.';
    }

    if (empty($errors)) {
        $pdo    = getPDO();
        $parts  = explode(' ', $nom);
        $avatar = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
        $hash   = password_hash($pwd, PASSWORD_BCRYPT);

        $pdo->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role, avatar) VALUES (?, ?, ?, 'participant', ?)")
            ->execute([$nom, $email, $hash, $avatar]);

        // Connexion automatique
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        loginUser($u);

        header('Location: ' . BASE_URL . '/index.php?success=registered');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inscription — OmnesEvent</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/styles.css" />
</head>
<body>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-icon" style="width:48px;height:48px;border-radius:14px;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:24px">🎫</div>
      <span style="font-family:var(--font-display);font-size:1.6rem;font-weight:800">Omnes<span style="color:var(--primary)">Event</span></span>
    </div>

    <h2>Créer un compte</h2>
    <p class="subtitle">Rejoignez la plateforme événementielle Omnes</p>

    <?php if (!empty($errors)): ?>
      <div class="alert-banner warning" style="margin-bottom:16px">
        <?php foreach ($errors as $e): ?>
          <div>⚠️ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/pages/register.php" class="form-grid">
      <div class="form-group">
        <label for="nom">Nom complet *</label>
        <input type="text" id="nom" name="nom"
               value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
               placeholder="Prénom Nom" required />
      </div>
      <div class="form-group">
        <label for="email">Email Omnes *</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="prenom.nom@omnes.edu" required />
      </div>
      <div class="form-group">
        <label for="password">Mot de passe *</label>
        <input type="password" id="password" name="password"
               placeholder="Minimum 6 caractères" required />
      </div>
      <div class="form-group">
        <label for="password2">Confirmer le mot de passe *</label>
        <input type="password" id="password2" name="password2"
               placeholder="••••••••" required />
      </div>
      <button type="submit" class="btn" style="width:100%;margin-top:4px">Créer mon compte →</button>
    </form>

    <p style="text-align:center;margin-top:16px;font-size:0.85rem;color:var(--gray-500)">
      Déjà un compte ?
      <a href="<?= BASE_URL ?>/pages/login.php" style="color:var(--primary)">Se connecter →</a>
    </p>

  </div>
</div>

</body>
</html>