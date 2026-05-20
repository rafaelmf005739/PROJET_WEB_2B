<?php
// ═══════════════════════════════════════════════════════
//  pages/profile.php
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user    = requireAuth();
$pdo     = getPDO();
$errors  = [];
$success = false;

// Nombre de billets actifs
$stmtT = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE utilisateur_id = ? AND statut = 'confirme'");
$stmtT->execute([$user['id']]);
$nbTickets = $stmtT->fetchColumn();

// ─── Traitement POST ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom   = trim($_POST['nom']       ?? '');
    $email = trim($_POST['email']     ?? '');
    $pwd   = trim($_POST['password']  ?? '');
    $pwd2  = trim($_POST['password2'] ?? '');

    if (!$nom)   $errors[] = 'Le nom est obligatoire.';
    if (!$email) $errors[] = 'L\'email est obligatoire.';
    if ($pwd !== '' && strlen($pwd) < 6) $errors[] = 'Le mot de passe doit faire au moins 6 caractères.';
    if ($pwd !== $pwd2) $errors[] = 'Les deux mots de passe ne correspondent pas.';

    // Email déjà pris par un autre
    if ($email) {
        $chk = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
        $chk->execute([$email, $user['id']]);
        if ($chk->fetch()) $errors[] = 'Cet email est déjà utilisé.';
    }

    // Upload photo de profil
    $photo = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Format d\'image non supporté (JPG, PNG, WEBP).';
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'La photo ne doit pas dépasser 2 Mo.';
        } else {
            $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/' . $filename);
            $photo = $filename;
        }
    }

    if (empty($errors)) {
        $parts  = explode(' ', $nom);
        $avatar = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

        $sql    = "UPDATE utilisateurs SET nom = ?, email = ?, avatar = ?";
        $params = [$nom, $email, $avatar];

        if ($pwd !== '') {
            $sql     .= ", mot_de_passe = ?";
            $params[] = password_hash($pwd, PASSWORD_BCRYPT);
        }

        if ($photo) {
            $sql     .= ", photo = ?";
            $params[] = $photo;
        }

        $sql     .= " WHERE id = ?";
        $params[] = $user['id'];

        $pdo->prepare($sql)->execute($params);

        // Rafraîchir la session
        $_SESSION['user']['nom']    = $nom;
        $_SESSION['user']['email']  = $email;
        $_SESSION['user']['avatar'] = $avatar;
        if ($photo) $_SESSION['user']['photo'] = $photo;
        $user    = getUser();
        $success = true;
    }
}

// Recharger depuis la BDD pour avoir la photo à jour
$stmtU = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmtU->execute([$user['id']]);
$userDb = $stmtU->fetch();

$pageTitle  = 'Mon profil';
$activePage = 'profile';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container profile-page">
  <div class="page-title" style="margin-bottom:24px">
    <h2>Mon profil</h2>
  </div>

  <div class="profile-grid">

    <!-- Sidebar -->
    <div class="profile-sidebar">
      <?php if (!empty($userDb['photo'])): ?>
        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($userDb['photo']) ?>"
             alt="Photo de profil"
             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--primary)" />
      <?php else: ?>
        <div class="profile-avatar"><?= htmlspecialchars($user['avatar'] ?? '?') ?></div>
      <?php endif; ?>

      <div>
        <div class="profile-name"><?= htmlspecialchars($user['nom']) ?></div>
        <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
      </div>
      <span class="role-pill <?= $user['role'] ?>"><?= $user['role'] ?></span>

      <div style="width:100%;border-top:1px solid var(--border);padding-top:14px;display:flex;flex-direction:column;gap:8px">
        <?php if ($user['role'] === 'participant'): ?>
          <p style="font-size:0.82rem;color:var(--gray-500);text-align:center">
            <strong><?= $nbTickets ?></strong> billet(s) actif(s)
          </p>
          <a href="<?= BASE_URL ?>/pages/my-tickets.php" class="btn btn-ghost btn-sm" style="width:100%;text-align:center">
            Voir mes billets
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/pages/logout.php" class="btn btn-ghost btn-sm" style="width:100%;text-align:center">
          🚪 Se déconnecter
        </a>
      </div>
    </div>

    <!-- Formulaire -->
    <div class="profile-main">
      <h3>Modifier mes informations</h3>

      <?php if ($success): ?>
        <div class="alert-banner info" style="margin-bottom:20px">✅ Profil mis à jour avec succès !</div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert-banner warning" style="margin-bottom:20px">
          <?php foreach ($errors as $e): ?>
            <div>⚠️ <?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>/pages/profile.php" enctype="multipart/form-data" class="form-grid">

        <div class="form-group">
          <label for="photo">Photo de profil</label>
          <input type="file" id="photo" name="photo" accept="image/*" />
          <span class="hint">JPG, PNG, WEBP — max 2 Mo</span>
        </div>

        <div class="form-group">
          <label for="nom">Nom complet</label>
          <input type="text" id="nom" name="nom"
                 value="<?= htmlspecialchars($user['nom']) ?>" required />
        </div>

        <div class="form-group">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email"
                 value="<?= htmlspecialchars($user['email']) ?>" required />
        </div>

        <div class="form-group">
          <label>Rôle</label>
          <input type="text" value="<?= $user['role'] ?>" disabled
                 style="background:var(--gray-100);color:var(--gray-500)" />
          <span class="hint">Le rôle est attribué par l'administrateur.</span>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="password">Nouveau mot de passe</label>
            <input type="password" id="password" name="password"
                   placeholder="Laisser vide pour ne pas changer" />
            <span class="hint">Minimum 6 caractères</span>
          </div>
          <div class="form-group">
            <label for="password2">Confirmer le mot de passe</label>
            <input type="password" id="password2" name="password2"
                   placeholder="••••••••" />
          </div>
        </div>

        <button type="submit" class="btn">Enregistrer les modifications</button>

      </form>
    </div>

  </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>