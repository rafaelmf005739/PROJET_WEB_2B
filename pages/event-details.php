<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireAuth();
$pdo  = getPDO();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$stmt = $pdo->prepare("SELECT e.*, u.nom AS organisateur_nom
                        FROM evenements e
                        JOIN utilisateurs u ON e.organisateur_id = u.id
                        WHERE e.id = ?");
$stmt->execute([$id]);
$ev = $stmt->fetch();

if (!$ev) { header('Location: ' . BASE_URL . '/index.php?error=notfound'); exit; }

$stmtR = $pdo->prepare("SELECT id, code FROM reservations WHERE utilisateur_id = ? AND evenement_id = ? AND statut = 'confirme'");
$stmtR->execute([$user['id'], $id]);
$reservation = $stmtR->fetch();
$alreadyIn   = (bool)$reservation;

$flash     = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reserve' && $user['role'] === 'participant') {
        if ($alreadyIn) {
            $flash     = 'Vous êtes déjà inscrit à cet événement.';
            $flashType = 'warning';
        } elseif ($ev['inscrits'] >= $ev['capacite']) {
            $flash     = 'Cet événement est complet.';
            $flashType = 'error';
        } else {
// Vérifier s'il existe un billet annulé pour cet utilisateur
$chkAnnule = $pdo->prepare("SELECT id FROM reservations WHERE utilisateur_id = ? AND evenement_id = ? AND statut = 'annule'");
$chkAnnule->execute([$user['id'], $id]);
$billetAnnule = $chkAnnule->fetch();

if ($billetAnnule) {
    $pdo->prepare("UPDATE reservations SET statut = 'confirme' WHERE utilisateur_id = ? AND evenement_id = ?")
        ->execute([$user['id'], $id]);
    $code = null; // on récupère le code existant
} else {
    // Créer un nouveau billet
    $code = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $pdo->prepare("INSERT INTO reservations (code, utilisateur_id, evenement_id) VALUES (?, ?, ?)")
        ->execute([$code, $user['id'], $id]);
}
            $pdo->prepare("UPDATE evenements SET inscrits = inscrits + 1,
                            statut = IF(inscrits + 1 >= capacite, 'complet', 'ouvert')
                            WHERE id = ?")
                ->execute([$id]);
            $flash     = "Réservation confirmée ! 🎟️ Code : $code";
            $flashType = 'success';
            $alreadyIn = true;
            $stmtR->execute([$user['id'], $id]);
            $reservation = $stmtR->fetch();
        }
    }

    if ($action === 'cancel' && $user['role'] === 'participant') {
        $pdo->prepare("UPDATE reservations SET statut = 'annule' WHERE utilisateur_id = ? AND evenement_id = ?")
            ->execute([$user['id'], $id]);
        $pdo->prepare("UPDATE evenements SET inscrits = GREATEST(inscrits - 1, 0),
                        statut = IF(statut = 'complet', 'ouvert', statut) WHERE id = ?")
            ->execute([$id]);
        $flash       = 'Votre réservation a été annulée.';
        $flashType   = 'info';
        $alreadyIn   = false;
        $reservation = null;
    }

    if ($action === 'delete' && ($user['role'] === 'admin' || ($user['role'] === 'organisateur' && $ev['organisateur_id'] == $user['id']))) {
        $pdo->prepare("DELETE FROM evenements WHERE id = ?")->execute([$id]);
        header('Location: ' . BASE_URL . '/index.php?success=deleted');
        exit;
    }

    $stmt->execute([$id]);
    $ev = $stmt->fetch();
}

$left = $ev['capacite'] - $ev['inscrits'];
$pct  = $ev['capacite'] > 0 ? round(($ev['inscrits'] / $ev['capacite']) * 100) : 0;

function badgeClass(string $cat): string {
    return match($cat) { 'Soirée'=>'badge-soiree', 'Sport'=>'badge-sport', 'Culture'=>'badge-culture', default=>'badge-default' };
}
function barColor(int $pct): string {
    return $pct >= 100 ? '#DC2626' : ($pct >= 80 ? '#D97706' : '#16A34A');
}
function fmtDate(string $d): string {
    return (new DateTime($d))->format('j F Y');
}

$pageTitle  = htmlspecialchars($ev['titre']);
$activePage = '';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container details-page">

  <div class="breadcrumb">
    <a href="<?= BASE_URL ?>/index.php">Accueil</a>
    <span>›</span>
    <span><?= htmlspecialchars($ev['titre']) ?></span>
  </div>

  <?php if ($flash): ?>
    <div class="alert-banner <?= $flashType === 'error' ? 'warning' : 'info' ?>" style="margin-bottom:20px">
      <?= htmlspecialchars($flash) ?>
    </div>
  <?php endif; ?>

  <div class="details-grid">

    <div class="details-img-wrap">
      <?php if ($ev['affiche']): ?>
        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($ev['affiche']) ?>" alt="<?= htmlspecialchars($ev['titre']) ?>" />
      <?php else: ?>
        <div class="details-img-placeholder"><?= $ev['emoji'] ?: '📅' ?></div>
      <?php endif; ?>
    </div>

    <div class="details-card">
      <span class="badge <?= badgeClass($ev['categorie']) ?>"><?= htmlspecialchars($ev['categorie']) ?></span>
      <h2><?= htmlspecialchars($ev['titre']) ?></h2>

      <div class="details-meta-list">
        <div class="details-meta-item"><span class="icon">📅</span><strong>Date</strong><span><?= fmtDate($ev['date_event']) ?></span></div>
        <div class="details-meta-item"><span class="icon">📍</span><strong>Lieu</strong><span><?= htmlspecialchars($ev['lieu']) ?></span></div>
        <div class="details-meta-item"><span class="icon">🏷️</span><strong>Association</strong><span><?= htmlspecialchars($ev['association']) ?></span></div>
        <div class="details-meta-item"><span class="icon">👤</span><strong>Organisateur</strong><span><?= htmlspecialchars($ev['organisateur_nom']) ?></span></div>
        <div class="details-meta-item"><span class="icon">👥</span><strong>Capacité</strong><span><?= $ev['capacite'] ?> places</span></div>
      </div>

      <div class="capacity-info">
        <div class="capacity-header">
          <span>Inscriptions</span>
          <span><strong><?= $ev['inscrits'] ?></strong> / <?= $ev['capacite'] ?> (<?= $pct ?>%)</span>
        </div>
        <div class="capacity-bar">
          <div class="capacity-bar-fill" style="width:<?= $pct ?>%;background:<?= barColor($pct) ?>"></div>
        </div>
      </div>

      <div class="description-block"><?= nl2br(htmlspecialchars($ev['description'])) ?></div>

      <?php if ($user['role'] === 'participant'): ?>
        <?php if ($alreadyIn): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="cancel" />
              <button type="submit" class="btn btn-ghost">Annuler ma réservation</button>
            </form>
            <?php if ($reservation): ?>
              <a href="<?= BASE_URL ?>/pages/ticket-qr.php?code=<?= urlencode($reservation['code']) ?>"
                 class="btn btn-sm" target="_blank">🎟️ Voir mon billet / QR</a>
            <?php endif; ?>
          </div>
        <?php elseif ($ev['inscrits'] < $ev['capacite']): ?>
          <form method="POST">
            <input type="hidden" name="action" value="reserve" />
            <button type="submit" class="btn btn-success">🎟️ Réserver ma place</button>
          </form>
        <?php else: ?>
          <button class="btn" disabled style="background:var(--gray-300);color:var(--gray-500);cursor:not-allowed">
            Événement complet
          </button>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($user['role'] === 'admin' || ($user['role'] === 'organisateur' && $ev['organisateur_id'] == $user['id'])): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
          <a href="<?= BASE_URL ?>/pages/create-event.php?edit=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm">✏️ Modifier</a>
          <form method="POST" onsubmit="return confirm('Supprimer définitivement cet événement ?')" style="display:inline">
            <input type="hidden" name="action" value="delete" />
            <button type="submit" class="btn btn-danger btn-sm">🗑️ Supprimer</button>
          </form>
          <a href="<?= BASE_URL ?>/pages/scan-qr.php?event_id=<?= $ev['id'] ?>" class="btn btn-sm">📷 Scanner billets</a>
          <a href="<?= BASE_URL ?>/pages/organizer-dashboard.php" class="btn btn-ghost btn-sm">📊 Tableau de bord</a>
        </div>
      <?php endif; ?>

    </div>
  </div>

</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>