<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireAuth(['organisateur', 'admin']);
$pdo  = getPDO();

// Selon le rôle : tous les events ou seulement les siens
if ($user['role'] === 'admin') {
    $stmt = $pdo->query("SELECT e.*, u.nom AS organisateur_nom FROM evenements e JOIN utilisateurs u ON e.organisateur_id = u.id ORDER BY e.date_event ASC");
} else {
    $stmt = $pdo->prepare("SELECT e.*, u.nom AS organisateur_nom FROM evenements e JOIN utilisateurs u ON e.organisateur_id = u.id WHERE e.organisateur_id = ? ORDER BY e.date_event ASC");
    $stmt->execute([$user['id']]);
}
$evenements = $stmt->fetchAll();

// Stats
$totalInscrits  = array_sum(array_column($evenements, 'inscrits'));
$totalCapacite  = array_sum(array_column($evenements, 'capacite'));
$totalComplets  = count(array_filter($evenements, fn($e) => $e['statut'] === 'complet'));
$tauxRemplissage = $totalCapacite > 0 ? round(($totalInscrits / $totalCapacite) * 100) : 0;

// Action POST : suppression depuis le dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int)($_POST['event_id'] ?? 0);
    if ($delId) {
        if ($user['role'] === 'admin') {
            $pdo->prepare("DELETE FROM evenements WHERE id = ?")->execute([$delId]);
        } else {
            $pdo->prepare("DELETE FROM evenements WHERE id = ? AND organisateur_id = ?")->execute([$delId, $user['id']]);
        }
        header('Location: ' . BASE_URL . '/pages/organizer-dashboard.php?success=deleted');
        exit;
    }
}

function fmtDate(string $d): string { return (new DateTime($d))->format('j F Y'); }
function barColor(int $pct): string { return $pct>=100?'#DC2626':($pct>=80?'#D97706':'#16A34A'); }

$pageTitle  = 'Tableau de bord organisateur';
$activePage = 'organizer';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container dashboard-page">

  <div class="dashboard-header">
    <h2>📊 Tableau de bord organisateur</h2>
    <p style="color:var(--gray-500);margin-top:4px">
      <?= $user['role'] === 'admin' ? 'Vue globale de tous les événements.' : "Événements de " . htmlspecialchars($user['association'] ?? $user['nom']) . "." ?>
    </p>
  </div>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert-banner info" style="margin-bottom:20px">✅ Opération effectuée avec succès.</div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">📅</div>
      <div class="stat-value"><?= count($evenements) ?></div>
      <div class="stat-label">Événements</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-value"><?= $totalInscrits ?></div>
      <div class="stat-label">Inscrits total</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📈</div>
      <div class="stat-value"><?= $tauxRemplissage ?>%</div>
      <div class="stat-label">Taux de remplissage</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🔴</div>
      <div class="stat-value"><?= $totalComplets ?></div>
      <div class="stat-label">Événements complets</div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
    <a href="<?= BASE_URL ?>/pages/create-event.php" class="btn btn-sm">➕ Créer un événement</a>
  </div>

  <!-- Tableau -->
  <div class="events-table-wrap">
    <table class="events-table">
      <thead>
        <tr>
          <th>Événement</th>
          <th>Date</th>
          <th>Inscriptions</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($evenements)): ?>
          <tr>
            <td colspan="5" style="text-align:center;color:var(--gray-500);padding:40px">
              Aucun événement. <a href="<?= BASE_URL ?>/pages/create-event.php" style="color:var(--primary)">Créer le premier →</a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($evenements as $ev):
            $pct = $ev['capacite'] > 0 ? round(($ev['inscrits'] / $ev['capacite']) * 100) : 0;
            $statusClass = match($ev['statut']) { 'ouvert'=>'ouvert', 'complet'=>'complet', default=>'annule' };
          ?>
          <tr>
            <td class="event-name"><?= $ev['emoji'] ?> <?= htmlspecialchars($ev['titre']) ?></td>
            <td><?= fmtDate($ev['date_event']) ?></td>
            <td>
              <div class="mini-bar">
                <span><?= $ev['inscrits'] ?>/<?= $ev['capacite'] ?></span>
                <div class="mini-bar-track">
                  <div class="mini-bar-fill" style="width:<?= $pct ?>%;background:<?= barColor($pct) ?>"></div>
                </div>
              </div>
            </td>
            <td><span class="status-badge <?= $statusClass ?>"><?= $ev['statut'] ?></span></td>
            <td>
              <div class="td-actions">
                <a href="<?= BASE_URL ?>/pages/event-details.php?id=<?= $ev['id'] ?>&tab=inscrits" class="btn btn-sm btn-ghost">👥 Inscrits</a>
                <a href="<?= BASE_URL ?>/pages/create-event.php?edit=<?= $ev['id'] ?>" class="btn btn-sm btn-ghost">✏️</a>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Supprimer cet événement et toutes ses réservations ?')">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="event_id" value="<?= $ev['id'] ?>" />
                  <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
