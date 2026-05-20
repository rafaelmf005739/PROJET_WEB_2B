<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireAuth(['organisateur', 'admin']);
$pdo  = getPDO();

$editId = (int)($_GET['edit'] ?? 0);
$ev     = null;
$errors = [];
$isEdit = false;

if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([$editId]);
    $ev = $stmt->fetch();

    if (!$ev) { header('Location: ' . BASE_URL . '/index.php'); exit; }

    // Vérifier les droits
    if ($user['role'] !== 'admin' && $ev['organisateur_id'] != $user['id']) {
        header('Location: ' . BASE_URL . '/index.php?error=unauthorized'); exit;
    }
    $isEdit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie   = trim($_POST['categorie']   ?? '');
    $date_event  = trim($_POST['date_event']  ?? '');
    $lieu        = trim($_POST['lieu']        ?? '');
    $association = trim($_POST['association'] ?? '');
    $capacite    = (int)($_POST['capacite']   ?? 0);

    // Validation
    if (!$titre)       $errors[] = 'Le titre est obligatoire.';
    if (!$description) $errors[] = 'La description est obligatoire.';
    if (!in_array($categorie, ['Soirée','Sport','Culture'])) $errors[] = 'Catégorie invalide.';
    if (!$date_event)  $errors[] = 'La date est obligatoire.';
    if (!$lieu)        $errors[] = 'Le lieu est obligatoire.';
    if (!$association) $errors[] = 'L\'association est obligatoire.';
    if ($capacite < 1) $errors[] = 'La capacité doit être supérieure à 0.';

    // Upload affiche
    $affiche = $ev['affiche'] ?? null;
    if (isset($_FILES['affiche']) && $_FILES['affiche']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['affiche']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Format d\'image non supporté (JPG, PNG, WEBP).';
        } elseif ($_FILES['affiche']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'L\'image ne doit pas dépasser 5 Mo.';
        } else {
            $filename = uniqid('event_') . '.' . $ext;
            move_uploaded_file($_FILES['affiche']['tmp_name'], __DIR__ . '/../uploads/' . $filename);
            $affiche = $filename;
        }
    }

    $emojiMap = ['Soirée'=>'🎉','Sport'=>'⚽','Culture'=>'💡'];
    $emoji    = $emojiMap[$categorie] ?? '📅';

    if (empty($errors)) {
        if ($isEdit) {
            $pdo->prepare("UPDATE evenements SET
                titre = ?, description = ?, categorie = ?, date_event = ?,
                lieu = ?, association = ?, capacite = ?, affiche = ?, emoji = ?
                WHERE id = ?")
                ->execute([$titre, $description, $categorie, $date_event, $lieu, $association, $capacite, $affiche, $emoji, $editId]);
            header("Location: " . BASE_URL . "/pages/event-details.php?id=$editId&success=updated");
            exit;
        } else {
            $pdo->prepare("INSERT INTO evenements
                (titre, description, categorie, date_event, lieu, association, capacite, affiche, emoji, organisateur_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$titre, $description, $categorie, $date_event, $lieu, $association, $capacite, $affiche, $emoji, $user['id']]);
            $newId = $pdo->lastInsertId();
            header("Location: " . BASE_URL . "/pages/event-details.php?id=$newId&success=created");
            exit;
        }
    }

    // Conserver les valeurs saisies en cas d'erreur
    $ev = compact('titre','description','categorie','date_event','lieu','association','capacite');
}

$pageTitle  = $isEdit ? 'Modifier l\'événement' : 'Créer un événement';
$activePage = 'create';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container form-page">
  <div class="page-title">
    <h2><?= $isEdit ? 'Modifier l\'événement' : 'Créer un événement' ?></h2>
    <p style="color:var(--gray-500);margin-top:4px">
      <?= $isEdit ? 'Mettez à jour les informations de votre événement.' : 'Publiez un nouvel événement pour vos membres.' ?>
    </p>
  </div>

  <div class="form-card" style="margin-top:24px">

    <?php if (!empty($errors)): ?>
      <div class="alert-banner warning" style="margin-bottom:20px">
        <div>
          <?php foreach ($errors as $e): ?>
            <div>⚠️ <?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-grid">

      <div class="form-group">
        <label for="titre">Titre *</label>
        <input type="text" id="titre" name="titre"
               value="<?= htmlspecialchars($ev['titre'] ?? '') ?>"
               placeholder="Ex : Soirée de rentrée BDE" required />
      </div>

      <div class="form-group">
        <label for="description">Description *</label>
        <textarea id="description" name="description" rows="4"
                  placeholder="Programme, infos pratiques…" required><?= htmlspecialchars($ev['description'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="categorie">Catégorie *</label>
          <select id="categorie" name="categorie" required>
            <option value="">Choisir…</option>
            <?php foreach (['Soirée'=>'🎉','Sport'=>'⚽','Culture'=>'💡'] as $c => $e): ?>
              <option value="<?= $c ?>" <?= ($ev['categorie'] ?? '') === $c ? 'selected' : '' ?>>
                <?= "$e $c" ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="date_event">Date *</label>
          <input type="date" id="date_event" name="date_event"
                 value="<?= htmlspecialchars($ev['date_event'] ?? '') ?>" required />
        </div>
      </div>

      <div class="form-group">
        <label for="lieu">Lieu *</label>
        <input type="text" id="lieu" name="lieu"
               value="<?= htmlspecialchars($ev['lieu'] ?? '') ?>"
               placeholder="Ex : Amphithéâtre A – Campus Omnes" required />
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="association">Association *</label>
          <input type="text" id="association" name="association"
                 value="<?= htmlspecialchars($ev['association'] ?? '') ?>"
                 placeholder="Ex : BDE, BDS…" required />
        </div>
        <div class="form-group">
          <label for="capacite">Capacité maximale *</label>
          <input type="number" id="capacite" name="capacite" min="1"
                 value="<?= (int)($ev['capacite'] ?? '') ?>"
                 placeholder="Ex : 150" required />
          <span class="hint">Nombre de places disponibles</span>
        </div>
      </div>

      <div class="form-group">
        <label for="affiche">Affiche (optionnel)</label>
        <input type="file" id="affiche" name="affiche" accept="image/*" />
        <span class="hint">JPG, PNG, WEBP — max 5 Mo</span>
        <?php if (!empty($ev['affiche'])): ?>
          <div style="margin-top:8px">
            <img src="/uploads/<?= htmlspecialchars($ev['affiche']) ?>" alt="Affiche actuelle"
                 style="height:80px;width:auto;border-radius:8px;border:1px solid var(--border)" />
            <p style="font-size:0.78rem;color:var(--gray-500);margin-top:4px">Affiche actuelle — en télécharger une nouvelle remplacera celle-ci.</p>
          </div>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">
        <button type="submit" class="btn">
          <?= $isEdit ? 'Enregistrer les modifications' : 'Publier l\'événement' ?>
        </button>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost">Annuler</a>
      </div>

    </form>
  </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
