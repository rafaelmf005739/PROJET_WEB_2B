<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo    = getPDO();
$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom   = trim($_POST['nom']         ?? '');
    $email = trim($_POST['email']       ?? '');
    $asso  = trim($_POST['association'] ?? '');

    if (!$nom)   $errors[] = 'Le nom est obligatoire.';
    if (!$email) $errors[] = 'L\'email est obligatoire.';
    if (!$asso)  $errors[] = 'L\'association est obligatoire.';

    // Vérifier qu'il n'y a pas déjà une demande en attente
    if ($email) {
        $chk = $pdo->prepare("SELECT id FROM demandes_organisateur WHERE email = ? AND statut = 'pending'");
        $chk->execute([$email]);
        if ($chk->fetch()) $errors[] = 'Une demande est déjà en attente pour cet email.';
    }

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO demandes_organisateur (nom, email, association) VALUES (?, ?, ?)")
            ->execute([$nom, $email, $asso]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Devenir organisateur — OmnesEvent</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/styles.css" />
</head>
<body>

<div class="auth-page" style="background:linear-gradient(135deg,#DCFCE7,#F0FDF4)">
  <div class="auth-card">

    <div style="text-align:center;margin-bottom:24px">
      <div style="font-size:3rem;margin-bottom:8px">🏷️</div>
      <h2>Devenir organisateur</h2>
      <p class="subtitle">Votre demande sera examinée par l'administrateur.</p>
    </div>

    <?php if ($done): ?>
      <div class="alert-banner info" style="margin-bottom:20px">
        ✅ Demande envoyée ! Vous serez contacté(e) par email une fois votre compte validé.
      </div>
      <a href="<?= BASE_URL ?>/pages/login.php" class="btn" style="width:100%;text-align:center">Retour à la connexion</a>
    <?php else: ?>

      <?php if (!empty($errors)): ?>
        <div class="alert-banner warning" style="margin-bottom:16px">
          <?php foreach ($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="form-grid">
        <div class="form-group">
          <label for="nom">Nom complet *</label>
          <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required />
        </div>
        <div class="form-group">
          <label for="email">Email Omnes *</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="prenom.nom@omnes.edu" required />
        </div>
        <div class="form-group">
          <label for="association">Association / Club *</label>
          <input type="text" id="association" name="association"
                 value="<?= htmlspecialchars($_POST['association'] ?? '') ?>"
                 placeholder="Ex : BDE, Club Photo, Junior Entreprise…" required />
        </div>
        <button type="submit" class="btn" style="width:100%">Envoyer la demande</button>
      </form>

      <p style="text-align:center;margin-top:16px;font-size:0.85rem;color:var(--gray-500)">
        <a href="<?= BASE_URL ?>/pages/login.php" style="color:var(--primary)">← Retour à la connexion</a>
      </p>

    <?php endif; ?>
  </div>
</div>

</body>
</html>
