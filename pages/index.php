<?php
// ═══════════════════════════════════════════════════════
//  index.php — Accueil + recherche d'événements
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireAuth();
$pdo  = getPDO();

// ─── Filtres de recherche ────────────────────────────────
$text = trim($_GET['q']         ?? '');
$cat  = trim($_GET['categorie'] ?? '');
$date = trim($_GET['date']      ?? '');
$asso = trim($_GET['asso']      ?? '');

$sql    = "SELECT e.*, u.nom AS organisateur_nom
           FROM evenements e
           JOIN utilisateurs u ON e.organisateur_id = u.id
           WHERE 1=1";
$params = [];

if ($text !== '') {
    $sql .= " AND (e.titre LIKE :text OR e.description LIKE :text2)";
    $params['text']  = "%$text%";
    $params['text2'] = "%$text%";
}
if ($cat !== '') {
    $sql .= " AND e.categorie = :cat";
    $params['cat'] = $cat;
}
if ($date !== '') {
    $sql .= " AND e.date_event = :date";
    $params['date'] = $date;
}
if ($asso !== '') {
    $sql .= " AND e.association = :asso";
    $params['asso'] = $asso;
}
$sql .= " ORDER BY e.date_event ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evenements = $stmt->fetchAll();

// Stats hero
$totalInscrits   = $pdo->query("SELECT COALESCE(SUM(inscrits),0) FROM evenements")->fetchColumn();
$totalEvenements = $pdo->query("SELECT COUNT(*) FROM evenements WHERE statut != 'annule'")->fetchColumn();

// Associations distinctes pour le filtre
$associations = $pdo->query("SELECT DISTINCT association FROM evenements ORDER BY association")->fetchAll(PDO::FETCH_COLUMN);

// Tickets de l'utilisateur connecté (badge "Inscrit")
$ticketsUser = [];
$st = $pdo->prepare("SELECT evenement_id FROM reservations WHERE utilisateur_id = ? AND statut = 'confirme'");
$st->execute([$user['id']]);
$ticketsUser = array_column($st->fetchAll(), 'evenement_id');

function badgeClass(string $cat): string {
    return match($cat) { 'Soirée'=>'badge-soiree', 'Sport'=>'badge-sport', 'Culture'=>'badge-culture', default=>'badge-default' };
}
function formatDate(string $d): string {
    return (new DateTime($d))->format('j F Y');
}

$pageTitle  = 'Accueil';
$activePage = 'home';
include_once __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-content">
      <div class="hero-tag">✨ Plateforme officielle Omnes</div>
      <h2>Les événements du <em>campus</em>, au même endroit</h2>
      <p>Soirées, tournois sportifs, conférences — toute la vie associative Omnes centralisée.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <strong><?= (int)$totalEvenements ?></strong>
          <span>Événements à venir</span>
        </div>
        <div class="hero-stat">
          <strong><?= count($associations) ?></strong>
          <span>Associations</span>
        </div>
        <div class="hero-stat">
          <strong><?= (int)$totalInscrits ?></strong>
          <span>Participants inscrits</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- RECHERCHE -->
<section class="search-section">
  <div class="container">
    <div class="search-box">
      <h3>🔍 Rechercher un événement</h3>
      <form method="GET" action="<?= BASE_URL ?>/index.php" class="search-form" accept-charset="UTF-8">
        <div class="search-row">
          <div class="field-group" style="flex:2;min-width:180px">
            <label for="q">Mots-clés</label>
            <input type="text" id="q" name="q" value="<?= htmlspecialchars($text) ?>" placeholder="Nom, description…" />
          </div>
          <div class="field-group">
            <label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie">
              <option value="">Toutes</option>
              <option value="Soirée"  <?= $cat === 'Soirée'  ? 'selected' : '' ?>>🎉 Soirée</option>
              <option value="Sport"   <?= $cat === 'Sport'   ? 'selected' : '' ?>>⚽ Sport</option>
              <option value="Culture" <?= $cat === 'Culture' ? 'selected' : '' ?>>💡 Culture</option>
            </select>
          </div>
          <div class="field-group">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($date) ?>" />
          </div>
          <div class="field-group">
            <label for="asso">Association</label>
            <select id="asso" name="asso">
              <option value="">Toutes</option>
              <?php foreach ($associations as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $asso === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button type="submit" class="btn">Filtrer</button>
          <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost">Réinitialiser</a>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- ÉVÉNEMENTS -->
<section class="events-section">
  <div class="container">
    <div class="section-header">
      <h2>Événements à venir</h2>
      <span class="events-count"><?= count($evenements) ?> événement<?= count($evenements) > 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($evenements)): ?>
      <div class="no-result">
        <div class="icon">🔍</div>
        <p>Aucun événement ne correspond à vos critères.</p>
      </div>
    <?php else: ?>
    <div class="events-grid">
      <?php foreach ($evenements as $ev):
        $left = $ev['capacite'] - $ev['inscrits'];
        $pct  = $ev['capacite'] > 0 ? round(($ev['inscrits'] / $ev['capacite']) * 100) : 0;
        $leftClass = $pct >= 100 ? 'full' : ($pct >= 80 ? 'warning' : 'ok');
        $alreadyIn = in_array($ev['id'], $ticketsUser);
      ?>
      <article class="event-card">
        <div class="event-img-wrap">
          <?php if ($ev['affiche']): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($ev['affiche']) ?>" alt="<?= htmlspecialchars($ev['titre']) ?>" />
          <?php else: ?>
            <div class="event-img-placeholder"><?= $ev['emoji'] ?: '📅' ?></div>
          <?php endif; ?>
          <div class="event-capacity-bar">
            <div class="event-capacity-fill <?= $leftClass ?>" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <div class="event-body">
          <div class="event-meta">
            <span class="badge <?= badgeClass($ev['categorie']) ?>"><?= htmlspecialchars($ev['categorie']) ?></span>
            <?php if ($pct >= 100): ?>
              <span class="badge badge-full">Complet</span>
            <?php endif; ?>
            <?php if ($alreadyIn): ?>
              <span class="badge" style="background:#DCFCE7;color:#166534">✓ Inscrit</span>
            <?php endif; ?>
          </div>
          <h3><?= htmlspecialchars($ev['titre']) ?></h3>
          <div class="event-info">
            <div class="event-info-row"><span class="icon">📅</span><?= formatDate($ev['date_event']) ?></div>
            <div class="event-info-row"><span class="icon">📍</span><?= htmlspecialchars($ev['lieu']) ?></div>
            <div class="event-info-row"><span class="icon">🏷️</span><?= htmlspecialchars($ev['association']) ?></div>
          </div>
        </div>
        <div class="event-footer">
          <span class="places-left <?= $leftClass ?>">
            <?= $pct >= 100 ? 'Complet' : "$left place" . ($left > 1 ? 's' : '') . " restante" . ($left > 1 ? 's' : '') ?>
          </span>
          <a href="<?= BASE_URL ?>/pages/event-details.php?id=<?= $ev['id'] ?>" class="btn btn-sm">Voir →</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include_once __DIR__ . '/includes/footer.php'; ?>