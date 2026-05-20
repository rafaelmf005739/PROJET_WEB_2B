<?php
// ═══════════════════════════════════════════════════════
//  pages/ticket-qr.php — Billet avec QR code unique
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireAuth(['participant']);
$pdo  = getPDO();

$code = trim($_GET['code'] ?? '');
if (!$code) { header('Location: ' . BASE_URL . '/pages/my-tickets.php'); exit; }

// Vérifier que ce billet appartient bien à l'utilisateur connecté
$stmt = $pdo->prepare("
    SELECT r.*, e.titre, e.date_event, e.lieu, e.association, e.categorie, e.emoji,
           u.nom AS participant_nom, u.email AS participant_email
    FROM reservations r
    JOIN evenements e ON r.evenement_id = e.id
    JOIN utilisateurs u ON r.utilisateur_id = u.id
    WHERE r.code = ? AND r.utilisateur_id = ? AND r.statut = 'confirme'
");
$stmt->execute([$code, $user['id']]);
$ticket = $stmt->fetch();

if (!$ticket) { header('Location: ' . BASE_URL . '/pages/my-tickets.php'); exit; }

// URL de vérification encodée dans le QR code
$verifyUrl = 'http://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/pages/scan-qr.php?verify=' . urlencode($code);

function fmtDate(string $d): string {
    return (new DateTime($d))->format('j F Y');
}

$pageTitle  = 'Mon billet — ' . $ticket['titre'];
$activePage = 'tickets';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width:560px;padding:40px 20px">

  <div class="breadcrumb">
    <a href="<?= BASE_URL ?>/pages/my-tickets.php">Mes billets</a>
    <span>›</span>
    <span>Billet QR</span>
  </div>

  <!-- Billet -->
  <div style="background:white;border-radius:20px;box-shadow:var(--shadow-xl);overflow:hidden;margin-top:24px;border:1px solid var(--border)">

    <!-- En-tête coloré -->
    <div style="background:var(--primary);padding:28px;color:white;text-align:center">
      <div style="font-size:3rem;margin-bottom:8px"><?= $ticket['emoji'] ?: '🎫' ?></div>
      <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin-bottom:4px">
        <?= htmlspecialchars($ticket['titre']) ?>
      </h2>
      <span style="background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:999px;font-size:0.82rem;font-weight:600">
        <?= htmlspecialchars($ticket['categorie']) ?>
      </span>
    </div>

    <!-- Infos billet -->
    <div style="padding:24px;display:flex;flex-direction:column;gap:12px">
      <div style="display:flex;gap:10px;align-items:center;font-size:0.9rem">
        <span style="font-size:1.1rem">📅</span>
        <div>
          <div style="font-weight:700;color:var(--dark)"><?= fmtDate($ticket['date_event']) ?></div>
        </div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;font-size:0.9rem">
        <span style="font-size:1.1rem">📍</span>
        <div>
          <div style="font-weight:700;color:var(--dark)"><?= htmlspecialchars($ticket['lieu']) ?></div>
        </div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;font-size:0.9rem">
        <span style="font-size:1.1rem">🏷️</span>
        <div>
          <div style="font-weight:700;color:var(--dark)"><?= htmlspecialchars($ticket['association']) ?></div>
        </div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;font-size:0.9rem">
        <span style="font-size:1.1rem">👤</span>
        <div>
          <div style="font-weight:700;color:var(--dark)"><?= htmlspecialchars($ticket['participant_nom']) ?></div>
          <div style="color:var(--gray-500);font-size:0.82rem"><?= htmlspecialchars($ticket['participant_email']) ?></div>
        </div>
      </div>
    </div>

    <!-- Séparateur pointillé -->
    <div style="border-top:2px dashed var(--border);margin:0 24px"></div>

    <!-- QR Code -->
    <div style="padding:24px;text-align:center">
      <p style="font-size:0.82rem;color:var(--gray-500);margin-bottom:16px">
        Présentez ce QR code à l'entrée de l'événement
      </p>

      <!-- QR généré via API qrserver.com (pas de lib PHP nécessaire) -->
      <div style="display:inline-block;padding:12px;background:white;border-radius:12px;border:2px solid var(--border)">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($verifyUrl) ?>"
             alt="QR Code billet <?= htmlspecialchars($code) ?>"
             width="200" height="200"
             style="display:block;border-radius:4px" />
      </div>

      <div style="margin-top:16px">
        <span style="font-family:monospace;font-size:1rem;font-weight:700;background:var(--gray-100);padding:8px 16px;border-radius:8px;letter-spacing:0.1em;color:var(--dark)">
          <?= htmlspecialchars($code) ?>
        </span>
      </div>

      <p style="font-size:0.75rem;color:var(--gray-300);margin-top:12px">
        Billet valide · OmnesEvent
      </p>
    </div>

  </div>

  <div style="text-align:center;margin-top:20px">
    <a href="<?= BASE_URL ?>/pages/my-tickets.php" class="btn btn-ghost btn-sm">← Retour à mes billets</a>
  </div>

</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
