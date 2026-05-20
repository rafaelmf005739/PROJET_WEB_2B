<?php
// ═══════════════════════════════════════════════════════
//  pages/scan-qr.php — Scanner et vérifier les billets
//  Accessible : organisateur + admin
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireAuth(['organisateur', 'admin']);
$pdo  = getPDO();

$eventId = (int)($_GET['event_id'] ?? 0);
$result  = null;
$verifyCode = trim($_GET['verify'] ?? '');

// ─── Vérification via URL (QR scanné) ────────────────
if ($verifyCode) {
    $stmt = $pdo->prepare("
        SELECT r.*, e.titre, e.date_event, u.nom AS participant_nom, u.email AS participant_email
        FROM reservations r
        JOIN evenements e ON r.evenement_id = e.id
        JOIN utilisateurs u ON r.utilisateur_id = u.id
        WHERE r.code = ?
    ");
    $stmt->execute([$verifyCode]);
    $result = $stmt->fetch();

    // Marquer comme utilisé si valide
    if ($result && $result['statut'] === 'confirme') {
        $pdo->prepare("UPDATE reservations SET statut = 'utilise' WHERE code = ?")
            ->execute([$verifyCode]);
        $result['check_status'] = 'valid';
    } elseif ($result && $result['statut'] === 'utilise') {
        $result['check_status'] = 'already_used';
    } else {
        $result = ['check_status' => 'invalid'];
    }
}

// ─── Vérification manuelle via formulaire ─────────────
$manualResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code_manuel'])) {
    $codeManuel = strtoupper(trim($_POST['code_manuel']));
    $stmt = $pdo->prepare("
        SELECT r.*, e.titre, e.date_event, e.lieu, u.nom AS participant_nom, u.email AS participant_email
        FROM reservations r
        JOIN evenements e ON r.evenement_id = e.id
        JOIN utilisateurs u ON r.utilisateur_id = u.id
        WHERE r.code = ?
    ");
    $stmt->execute([$codeManuel]);
    $manualResult = $stmt->fetch();

    if ($manualResult && $manualResult['statut'] === 'confirme') {
        $pdo->prepare("UPDATE reservations SET statut = 'utilise' WHERE code = ?")
            ->execute([$codeManuel]);
        $manualResult['check_status'] = 'valid';
    } elseif ($manualResult && $manualResult['statut'] === 'utilise') {
        $manualResult['check_status'] = 'already_used';
    } else {
        $manualResult = ['check_status' => 'invalid', 'code' => $codeManuel];
    }
}

// ─── Charger l'événement si event_id fourni ──────────
$event = null;
if ($eventId) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
}

// ─── Liste des inscrits pour cet événement ───────────
$inscrits = [];
if ($eventId) {
    $stmt = $pdo->prepare("
        SELECT r.code, r.statut, r.created_at, u.nom, u.email
        FROM reservations r
        JOIN utilisateurs u ON r.utilisateur_id = u.id
        WHERE r.evenement_id = ?
        ORDER BY u.nom ASC
    ");
    $stmt->execute([$eventId]);
    $inscrits = $stmt->fetchAll();
}

function fmtDate(string $d): string {
    return (new DateTime($d))->format('j F Y');
}

$pageTitle  = 'Scanner les billets';
$activePage = 'organizer';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container dashboard-page">

  <div class="dashboard-header">
    <h2>📷 Scanner les billets</h2>
    <?php if ($event): ?>
      <p style="color:var(--gray-500);margin-top:4px">
        <?= htmlspecialchars($event['titre']) ?> — <?= fmtDate($event['date_event']) ?>
      </p>
    <?php endif; ?>
  </div>

  <!-- ─── Résultat scan QR via URL ─── -->
  <?php if ($verifyCode && $result): ?>
    <div style="margin-bottom:28px">
      <?php if ($result['check_status'] === 'valid'): ?>
        <div style="background:#DCFCE7;border:2px solid #16A34A;border-radius:var(--radius-lg);padding:24px;text-align:center">
          <div style="font-size:3rem;margin-bottom:8px">✅</div>
          <h3 style="color:#166534;font-family:var(--font-display);font-size:1.3rem">Billet VALIDE</h3>
          <p style="color:#166534;margin-top:8px">
            <strong><?= htmlspecialchars($result['participant_nom']) ?></strong><br>
            <?= htmlspecialchars($result['titre']) ?><br>
            Code : <code><?= htmlspecialchars($verifyCode) ?></code>
          </p>
        </div>
      <?php elseif ($result['check_status'] === 'already_used'): ?>
        <div style="background:#FEF3C7;border:2px solid #D97706;border-radius:var(--radius-lg);padding:24px;text-align:center">
          <div style="font-size:3rem;margin-bottom:8px">⚠️</div>
          <h3 style="color:#92400E;font-family:var(--font-display);font-size:1.3rem">Billet déjà utilisé</h3>
          <p style="color:#92400E;margin-top:8px">Ce billet a déjà été scanné.</p>
        </div>
      <?php else: ?>
        <div style="background:#FEE2E2;border:2px solid #DC2626;border-radius:var(--radius-lg);padding:24px;text-align:center">
          <div style="font-size:3rem;margin-bottom:8px">❌</div>
          <h3 style="color:#991B1B;font-family:var(--font-display);font-size:1.3rem">Billet INVALIDE</h3>
          <p style="color:#991B1B;margin-top:8px">Ce code ne correspond à aucun billet valide.</p>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr;gap:24px">

    <!-- ─── Vérification manuelle ─── -->
    <div style="background:white;border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);box-shadow:var(--shadow-sm)">
      <h3 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;margin-bottom:16px">
        🔍 Vérifier un code manuellement
      </h3>

      <form method="POST" action="<?= BASE_URL ?>/pages/scan-qr.php<?= $eventId ? '?event_id='.$eventId : '' ?>" class="form-grid">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <input type="text" name="code_manuel"
                 placeholder="Ex : TKT-A1B2C3D4"
                 value="<?= htmlspecialchars($_POST['code_manuel'] ?? '') ?>"
                 style="flex:1;min-width:200px;font-family:monospace;text-transform:uppercase"
                 required />
          <button type="submit" class="btn">Vérifier</button>
        </div>
      </form>

      <!-- Résultat vérification manuelle -->
      <?php if ($manualResult): ?>
        <div style="margin-top:16px;padding:16px;border-radius:var(--radius);
          <?= $manualResult['check_status'] === 'valid' ? 'background:#DCFCE7;border:1px solid #16A34A' :
             ($manualResult['check_status'] === 'already_used' ? 'background:#FEF3C7;border:1px solid #D97706' :
              'background:#FEE2E2;border:1px solid #DC2626') ?>">
          <?php if ($manualResult['check_status'] === 'valid'): ?>
            <p style="color:#166534;font-weight:700">✅ Billet valide — <?= htmlspecialchars($manualResult['participant_nom']) ?></p>
            <p style="color:#166534;font-size:0.85rem"><?= htmlspecialchars($manualResult['titre']) ?> · <?= fmtDate($manualResult['date_event']) ?></p>
          <?php elseif ($manualResult['check_status'] === 'already_used'): ?>
            <p style="color:#92400E;font-weight:700">⚠️ Ce billet a déjà été utilisé.</p>
          <?php else: ?>
            <p style="color:#991B1B;font-weight:700">❌ Code invalide ou billet annulé.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ─── Liste des inscrits ─── -->
    <?php if ($eventId && !empty($inscrits)): ?>
    <div style="background:white;border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border);box-shadow:var(--shadow-sm)">
      <h3 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;margin-bottom:16px">
        👥 Liste des inscrits (<?= count($inscrits) ?>)
      </h3>

      <?php
      $nbValides  = count(array_filter($inscrits, fn($i) => $i['statut'] === 'confirme'));
      $nbUtilises = count(array_filter($inscrits, fn($i) => $i['statut'] === 'utilise'));
      ?>
      <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
        <span style="background:#DCFCE7;color:#166534;padding:6px 12px;border-radius:999px;font-size:0.82rem;font-weight:700">
          ✅ <?= $nbValides ?> présent(s)
        </span>
        <span style="background:#DBEAFE;color:#1E40AF;padding:6px 12px;border-radius:999px;font-size:0.82rem;font-weight:700">
          🎟️ <?= $nbUtilises ?> scanné(s)
        </span>
      </div>

      <div style="display:flex;flex-direction:column;gap:8px;max-height:400px;overflow-y:auto">
        <?php foreach ($inscrits as $inscrit): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:var(--radius-sm);
          background:<?= $inscrit['statut'] === 'utilise' ? '#F0FDF4' : ($inscrit['statut'] === 'annule' ? '#FEF2F2' : '#F8FAFC') ?>">
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:0.9rem"><?= htmlspecialchars($inscrit['nom']) ?></div>
            <div style="color:var(--gray-500);font-size:0.78rem"><?= htmlspecialchars($inscrit['email']) ?></div>
            <code style="font-size:0.72rem;color:var(--gray-400)"><?= htmlspecialchars($inscrit['code']) ?></code>
          </div>
          <span style="font-size:0.75rem;font-weight:700;padding:3px 8px;border-radius:999px;
            <?= $inscrit['statut'] === 'utilise' ? 'background:#DCFCE7;color:#166534' :
               ($inscrit['statut'] === 'annule' ? 'background:#FEE2E2;color:#991B1B' :
                'background:#DBEAFE;color:#1E40AF') ?>">
            <?= $inscrit['statut'] === 'utilise' ? '✅ Scanné' : ($inscrit['statut'] === 'annule' ? '❌ Annulé' : '🎟️ Valide') ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <div style="margin-top:20px">
    <a href="<?= BASE_URL ?>/pages/organizer-dashboard.php" class="btn btn-ghost btn-sm">← Retour au tableau de bord</a>
  </div>

</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
