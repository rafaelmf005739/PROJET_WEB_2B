<?php
// ═══════════════════════════════════════════════════════
//  pages/my-tickets.php
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireAuth(['participant']);
$pdo  = getPDO();

// ─── Annulation depuis cette page ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($eventId) {
        $pdo->prepare("UPDATE reservations SET statut = 'annule' WHERE utilisateur_id = ? AND evenement_id = ? AND statut = 'confirme'")
            ->execute([$user['id'], $eventId]);
        $pdo->prepare("UPDATE evenements SET inscrits = GREATEST(inscrits - 1, 0), statut = IF(statut = 'complet', 'ouvert', statut) WHERE id = ?")
            ->execute([$eventId]);
    }
    header('Location: ' . BASE_URL . '/pages/my-tickets.php?success=cancelled');
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.*, e.titre, e.date_event, e.lieu, e.association, e.categorie, e.emoji, e.id AS event_id
    FROM reservations r
    JOIN evenements e ON r.evenement_id = e.id
    WHERE r.utilisateur_id = ?
    ORDER BY e.date_event ASC
");
$stmt->execute([$user['id']]);
$tickets = $stmt->fetchAll();

function badgeClass(string $cat): string {
    return match($cat) { 'Soirée'=>'badge-soiree', 'Sport'=>'badge-sport', 'Culture'=>'badge-culture', default=>'badge-default' };
}
function fmtDate(string $d): string {
    return (new DateTime($d))->format('j F Y');
}

$pageTitle  = 'Mes billets';
$activePage = 'tickets';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container tickets-section">
  <div class="tickets-header">
    <h2>🎟️ Mes billets</h2>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm">+ Trouver un événement</a>
  </div>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert-banner info" style="margin-bottom:20px">✅ Réservation annulée avec succès.</div>
  <?php endif; ?>

  <?php if (empty($tickets)): ?>
    <div class="empty-state">
      <div class="empty-icon">🎟️</div>
      <h3>Aucun billet pour l'instant</h3>
      <p>Inscrivez-vous à un événement pour retrouver vos billets ici.</p>
      <a href="<?= BASE_URL ?>/index.php" class="btn" style="margin-top:16px">Découvrir les événements</a>
    </div>
  <?php else: ?>
    <?php foreach ($tickets as $t):
      $now    = new DateTime();
      $evDate = new DateTime($t['date_event']);
      $isPast = $evDate < $now;
      $statusClass = $isPast ? 'passe' : ($t['statut'] === 'confirme' ? 'confirme' : 'annule');
      $statusLabel = $isPast ? 'Passé' : ($t['statut'] === 'confirme' ? 'Confirmé' : 'Annulé');
    ?>
    <div class="ticket-card">
      <div class="ticket-header">
        <div>
          <div class="ticket-event-name"><?= $t['emoji'] ?> <?= htmlspecialchars($t['titre']) ?></div>
          <span class="badge <?= badgeClass($t['categorie']) ?>" style="margin-top:6px"><?= htmlspecialchars($t['categorie']) ?></span>
        </div>
        <span class="ticket-status <?= $statusClass ?>"><?= $statusLabel ?></span>
      </div>
      <div class="ticket-details">
        <div class="ticket-detail-row">📅 <?= fmtDate($t['date_event']) ?></div>
        <div class="ticket-detail-row">📍 <?= htmlspecialchars($t['lieu']) ?></div>
        <div class="ticket-detail-row">🏷️ <?= htmlspecialchars($t['association']) ?></div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <span class="ticket-code">🎟️ <?= htmlspecialchars($t['code']) ?></span>
        <div style="display:flex;gap:8px">
          <a href="<?= BASE_URL ?>/pages/event-details.php?id=<?= $t['event_id'] ?>" class="btn btn-ghost btn-sm">Voir</a>
          <?php if (!$isPast && $t['statut'] === 'confirme'): ?>
            <form method="POST" action="<?= BASE_URL ?>/pages/my-tickets.php" style="display:inline">
              <input type="hidden" name="action"   value="cancel" />
              <input type="hidden" name="event_id" value="<?= $t['event_id'] ?>" />
              <button type="submit" class="btn btn-danger btn-sm"
                      onclick="return confirm('Annuler cette réservation ?')">Annuler</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>