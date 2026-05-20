<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireAuth(['admin']);
$pdo  = getPDO();

// ─── Actions POST ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Accepter une demande organisateur ──────────────
    if ($action === 'accept_request') {
        $reqId = (int)$_POST['req_id'];

        // Récupérer les infos de la demande
        $stmt = $pdo->prepare("SELECT * FROM demandes_organisateur WHERE id = ?");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();

        if ($req) {
            // Vérifier si l'email existe déjà dans utilisateurs
            $chk = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $chk->execute([$req['email']]);
            $existingUser = $chk->fetch();

            if ($existingUser) {
                // L'utilisateur existe → on lui change juste le rôle et l'association
                $pdo->prepare("UPDATE utilisateurs SET role = 'organisateur', association = ? WHERE email = ?")
                    ->execute([$req['association'], $req['email']]);
            } else {
                // L'utilisateur n'existe pas → on crée le compte avec un mot de passe temporaire
                $parts  = explode(' ', $req['nom']);
                $avatar = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                $tmpPwd = password_hash('password', PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role, association, avatar) VALUES (?, ?, ?, 'organisateur', ?, ?)")
                    ->execute([$req['nom'], $req['email'], $tmpPwd, $req['association'], $avatar]);
            }

            // Marquer la demande comme acceptée
            $pdo->prepare("UPDATE demandes_organisateur SET statut = 'accepted' WHERE id = ?")
                ->execute([$reqId]);
        }

        header('Location: ' . BASE_URL . '/pages/admin-dashboard.php?tab=requests&msg=accepted');
        exit;
    }

    // ── Refuser une demande ────────────────────────────
    if ($action === 'reject_request') {
        $pdo->prepare("UPDATE demandes_organisateur SET statut = 'rejected' WHERE id = ?")
            ->execute([(int)$_POST['req_id']]);
        header('Location: ' . BASE_URL . '/pages/admin-dashboard.php?tab=requests&msg=rejected');
        exit;
    }

    // ── Ignorer un signalement ─────────────────────────
    if ($action === 'dismiss_report') {
        $pdo->prepare("UPDATE signalements SET traite = 1 WHERE id = ?")
            ->execute([(int)$_POST['sig_id']]);
        header('Location: ' . BASE_URL . '/pages/admin-dashboard.php?tab=reports');
        exit;
    }

    // ── Supprimer un événement signalé ─────────────────
    if ($action === 'delete_event') {
        $pdo->prepare("DELETE FROM evenements WHERE id = ?")
            ->execute([(int)$_POST['event_id']]);
        header('Location: ' . BASE_URL . '/pages/admin-dashboard.php?tab=reports&msg=deleted');
        exit;
    }

    // ── Suspendre un compte ────────────────────────────
    if ($action === 'suspend_user') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== $user['id']) {
            $pdo->prepare("UPDATE utilisateurs SET actif = 0 WHERE id = ?")
                ->execute([$uid]);
        }
        header('Location: ' . BASE_URL . '/pages/admin-dashboard.php?tab=users');
        exit;
    }

    // ── Réactiver un compte ────────────────────────────
    if ($action === 'activate_user') {
        $pdo->prepare("UPDATE utilisateurs SET actif = 1 WHERE id = ?")
            ->execute([(int)$_POST['user_id']]);
        header('Location: ' . BASE_URL . '/pages/admin-dashboard.php?tab=users');
        exit;
    }

    // ── Changer le rôle d'un utilisateur ──────────────
    if ($action === 'change_role') {
        $uid     = (int)$_POST['user_id'];
        $newRole = $_POST['new_role'] ?? '';
        if (in_array($newRole, ['participant', 'organisateur', 'admin']) && $uid !== $user['id']) {
            $pdo->prepare("UPDATE utilisateurs SET role = ? WHERE id = ?")
                ->execute([$newRole, $uid]);
        }
        header('Location: ' . BASE_URL . '/pages/admin-dashboard.php?tab=users');
        exit;
    }
}

// ─── Données ──────────────────────────────────────────
$activeTab = $_GET['tab'] ?? 'requests';

$pendingRequests = $pdo->query("SELECT * FROM demandes_organisateur WHERE statut = 'pending' ORDER BY created_at DESC")->fetchAll();

$reports = $pdo->query("
    SELECT s.*, e.titre AS event_titre, e.id AS event_id, u.nom AS signale_par_nom
    FROM signalements s
    JOIN evenements e ON s.evenement_id = e.id
    JOIN utilisateurs u ON s.signale_par = u.id
    WHERE s.traite = 0
    ORDER BY s.created_at DESC")->fetchAll();

$users = $pdo->query("SELECT * FROM utilisateurs ORDER BY role, nom")->fetchAll();

$allEvents = $pdo->query("
    SELECT e.*, u.nom AS organisateur_nom
    FROM evenements e
    JOIN utilisateurs u ON e.organisateur_id = u.id
    ORDER BY e.date_event ASC")->fetchAll();

function fmtDate(string $d): string {
    return (new DateTime($d))->format('j F Y');
}

$pageTitle  = 'Administration';
$activePage = 'admin';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="container dashboard-page">

  <div class="dashboard-header">
    <h2>⚙️ Administration</h2>
    <p style="color:var(--gray-500);margin-top:4px">Modération, comptes et gestion globale de la plateforme.</p>
  </div>

  <?php if (isset($_GET['msg'])): ?>
    <div class="alert-banner info" style="margin-bottom:16px">
      ✅ <?= htmlspecialchars(match($_GET['msg']) {
        'accepted' => 'Demande acceptée — le compte organisateur est actif.',
        'rejected' => 'Demande refusée.',
        'deleted'  => 'Événement supprimé.',
        default    => 'Opération effectuée.'
      }) ?>
    </div>
  <?php endif; ?>

  <!-- Onglets -->
  <div class="admin-tabs">
    <?php
    $tabs = [
      'requests' => 'Demandes organisateurs',
      'reports'  => 'Signalements',
      'users'    => 'Comptes',
      'events'   => 'Événements'
    ];
    foreach ($tabs as $tab => $label):
      $count = match($tab) { 'requests' => count($pendingRequests), 'reports' => count($reports), default => 0 };
    ?>
      <a href="?tab=<?= $tab ?>" class="admin-tab btn <?= $activeTab === $tab ? 'active' : '' ?>">
        <?= $label ?>
        <?php if ($count > 0): ?>
          <span class="badge badge-default" style="margin-left:6px"><?= $count ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ─── Demandes organisateurs ─── -->
  <?php if ($activeTab === 'requests'): ?>
    <div class="alert-banner info" style="margin-bottom:20px">
      ℹ️ Accepter une demande attribue le rôle <strong>Organisateur</strong> à l'utilisateur (ou crée son compte avec le mot de passe <code>password</code> s'il n'existe pas encore).
    </div>
    <?php if (empty($pendingRequests)): ?>
      <div class="empty-state"><div class="empty-icon">✅</div><h3>Aucune demande en attente</h3></div>
    <?php else: ?>
      <div class="requests-list">
        <?php foreach ($pendingRequests as $r): ?>
        <div class="request-card">
          <div class="request-info">
            <div class="request-name"><?= htmlspecialchars($r['nom']) ?></div>
            <div class="request-meta">
              📧 <?= htmlspecialchars($r['email']) ?> &nbsp;·&nbsp;
              🏷️ <?= htmlspecialchars($r['association']) ?> &nbsp;·&nbsp;
              📅 <?= fmtDate($r['created_at']) ?>
            </div>
          </div>
          <div class="request-actions">
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"  value="accept_request" />
              <input type="hidden" name="req_id"  value="<?= $r['id'] ?>" />
              <button class="btn btn-success btn-sm">✓ Accepter</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="reject_request" />
              <input type="hidden" name="req_id" value="<?= $r['id'] ?>" />
              <button class="btn btn-danger btn-sm">✕ Refuser</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <!-- ─── Signalements ─── -->
  <?php elseif ($activeTab === 'reports'): ?>
    <?php if (empty($reports)): ?>
      <div class="empty-state"><div class="empty-icon">✅</div><h3>Aucun signalement non traité</h3></div>
    <?php else: ?>
      <div class="requests-list">
        <?php foreach ($reports as $rep): ?>
        <div class="request-card">
          <div class="request-info">
            <div class="request-name">📋 <?= htmlspecialchars($rep['event_titre']) ?></div>
            <div class="request-meta">⚠️ <?= htmlspecialchars($rep['raison']) ?> &nbsp;·&nbsp; Par <?= htmlspecialchars($rep['signale_par_nom']) ?></div>
          </div>
          <div class="request-actions">
            <a href="<?= BASE_URL ?>/pages/event-details.php?id=<?= $rep['event_id'] ?>" class="btn btn-ghost btn-sm">Voir</a>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"   value="delete_event" />
              <input type="hidden" name="event_id" value="<?= $rep['event_id'] ?>" />
              <button class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cet événement ?')">Supprimer</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="dismiss_report" />
              <input type="hidden" name="sig_id" value="<?= $rep['id'] ?>" />
              <button class="btn btn-sm">Ignorer</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <!-- ─── Comptes utilisateurs ─── -->
  <?php elseif ($activeTab === 'users'): ?>
    <div class="users-list">
      <?php foreach ($users as $u): ?>
      <div class="user-row">
        <div class="user-row-avatar"><?= htmlspecialchars($u['avatar'] ?? '?') ?></div>
        <div class="user-row-info">
          <div class="user-row-name"><?= htmlspecialchars($u['nom']) ?></div>
          <div class="user-row-email"><?= htmlspecialchars($u['email']) ?></div>
        </div>
        <span class="role-pill <?= $u['role'] ?>"><?= $u['role'] ?></span>
        <span class="status-badge <?= $u['actif'] ? 'ouvert' : 'annule' ?>"><?= $u['actif'] ? 'Actif' : 'Suspendu' ?></span>
        <div class="user-row-actions">
          <?php if ($u['id'] !== $user['id']): ?>
            <!-- Changer le rôle -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"  value="change_role" />
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
              <select name="new_role" onchange="this.form.submit()"
                      style="font-size:0.78rem;padding:4px 8px;border-radius:6px;border:1px solid var(--border)">
                <option value="participant"  <?= $u['role']==='participant'  ? 'selected':'' ?>>Participant</option>
                <option value="organisateur" <?= $u['role']==='organisateur' ? 'selected':'' ?>>Organisateur</option>
                <option value="admin"        <?= $u['role']==='admin'        ? 'selected':'' ?>>Admin</option>
              </select>
            </form>
            <!-- Suspendre / Réactiver -->
            <?php if ($u['actif']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"  value="suspend_user" />
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                <button class="btn btn-warning btn-sm">Suspendre</button>
              </form>
            <?php else: ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"  value="activate_user" />
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                <button class="btn btn-success btn-sm">Réactiver</button>
              </form>
            <?php endif; ?>
          <?php else: ?>
            <span style="font-size:0.78rem;color:var(--gray-500)">Votre compte</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  <!-- ─── Tous les événements ─── -->
  <?php elseif ($activeTab === 'events'): ?>
    <div class="requests-list">
      <?php foreach ($allEvents as $ev): ?>
      <div class="request-card">
        <div class="request-info">
          <div class="request-name"><?= $ev['emoji'] ?> <?= htmlspecialchars($ev['titre']) ?></div>
          <div class="request-meta">
            📅 <?= fmtDate($ev['date_event']) ?> &nbsp;·&nbsp;
            👥 <?= $ev['inscrits'] ?>/<?= $ev['capacite'] ?> &nbsp;·&nbsp;
            <?= htmlspecialchars($ev['association']) ?> &nbsp;·&nbsp;
            Organisateur : <?= htmlspecialchars($ev['organisateur_nom']) ?>
          </div>
        </div>
        <div class="request-actions">
          <a href="<?= BASE_URL ?>/pages/event-details.php?id=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm">Voir</a>
          <a href="<?= BASE_URL ?>/pages/create-event.php?edit=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm">Modifier</a>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action"   value="delete_event" />
            <input type="hidden" name="event_id" value="<?= $ev['id'] ?>" />
            <button class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ?')">Supprimer</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>