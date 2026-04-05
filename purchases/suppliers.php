<?php
session_start();
if(!isset($_SESSION['user'])){ header("Location: ../auth/login.php"); exit(); }
include('../config/db.php');
requireLogin();

$currentUser = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

$message = '';
$msgType = '';

// ── SUPPRIMER un fournisseur (admin seulement) ──────
if(isset($_GET['delete']) && isAdmin()){
    $did = intval($_GET['delete']);
    // Vérifier s'il a des achats
    $checkAchats = $conn->query("SELECT COUNT(*) AS c FROM purchases WHERE supplier_id=$did")->fetch_assoc()['c'];
    if($checkAchats > 0){
        $message = "Impossible de supprimer ce fournisseur — il a $checkAchats achat(s) enregistré(s).";
        $msgType = "error";
    } else {
        $conn->query("DELETE FROM suppliers WHERE id=$did");
        $message = "Fournisseur supprimé avec succès.";
        $msgType = "success";
    }
}

// ── MODIFIER un fournisseur (admin seulement) ───────
if(isset($_POST['action']) && $_POST['action'] === 'edit' && isAdmin()){
    $eid   = intval($_POST['supplier_id']);
    $name  = trim($conn->real_escape_string($_POST['name']));
    $phone = trim($conn->real_escape_string($_POST['phone']));
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));

    if(!empty($name)){
        // Vérifier doublon nom (sauf lui-même)
        $chk = $conn->query("SELECT id FROM suppliers WHERE LOWER(name)=LOWER('$name') AND id != $eid");
        if($chk->num_rows > 0){
            $message = "Un fournisseur avec ce nom existe déjà.";
            $msgType = "error";
        } else {
            $conn->query("UPDATE suppliers SET name='$name', phone='$phone', email='$email' WHERE id=$eid");
            $message = "✅ Fournisseur \"$name\" mis à jour avec succès !";
            $msgType = "success";
        }
    } else {
        $message = "Le nom du fournisseur est obligatoire.";
        $msgType = "error";
    }
}

// ── Récupérer fournisseur à modifier ────────────────
$editSupplier = null;
$editMode     = false;
if(isset($_GET['edit']) && isAdmin()){
    $editId = intval($_GET['edit']);
    $res    = $conn->prepare("SELECT * FROM suppliers WHERE id=?");
    $res->bind_param("i", $editId);
    $res->execute();
    $editSupplier = $res->get_result()->fetch_assoc();
    $res->close();
    if($editSupplier) $editMode = true;
}

// ── Liste fournisseurs ───────────────────────────────
$suppliers = $conn->query("
    SELECT s.*, COUNT(p.id) AS total_achats, SUM(p.quantity * p.unit_price) AS total_depense
    FROM suppliers s
    LEFT JOIN purchases p ON p.supplier_id = s.id
    GROUP BY s.id ORDER BY s.created_at DESC
");

// Compteur notifications
$bellCount = 0;
$bellRes   = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='stock_alert' AND is_read=0");
if($bellRes) $bellCount = $bellRes->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fournisseurs — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
    body { font-family: 'Poppins', sans-serif; background: #f4f6f9; }

    /* SIDEBAR */
    .sidebar { position:fixed; width:230px; height:100%; background:#111827; padding-top:20px; display:flex; flex-direction:column; overflow:hidden; z-index:100; }
    .sidebar::before { content:''; position:absolute; width:200px; height:200px; background:radial-gradient(circle,rgba(99,102,241,0.25),transparent); top:-60px; right:-60px; border-radius:50%; pointer-events:none; }
    .sidebar-profile { margin:0 10px 14px; background:linear-gradient(135deg,rgba(99,102,241,0.2),rgba(99,102,241,0.05)); border:1px solid rgba(99,102,241,0.3); border-radius:14px; padding:12px; display:flex; align-items:center; gap:10px; position:relative; z-index:1; }
    .sidebar-profile img { width:38px; height:38px; border-radius:10px; object-fit:cover; flex-shrink:0; }
    .sidebar-avatar-placeholder { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#6366f1,#818cf8); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:15px; flex-shrink:0; }
    .sidebar-profile-name { color:white; font-size:12px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-profile-role { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; margin-top:3px; }
    .role-admin    { background:rgba(99,102,241,0.25); color:#a5b4fc; }
    .role-employee { background:rgba(16,185,129,0.2);  color:#6ee7b7; }
    .nav-section { font-size:10px; font-weight:700; letter-spacing:1.5px; color:rgba(255,255,255,0.25); text-transform:uppercase; padding:0 14px; margin:12px 0 5px; }
    .sidebar-nav { flex:1; padding:0 8px; overflow-y:auto; }
    .sidebar h4 { color:white; padding:0 14px; margin-bottom:8px; font-size:15px; }
    .sidebar a { display:flex; align-items:center; gap:10px; color:#9ca3af; padding:10px 12px; text-decoration:none; transition:0.3s; border-radius:10px; font-size:13px; font-weight:500; margin-bottom:2px; }
    .sidebar a i { font-size:16px; width:18px; text-align:center; }
    .sidebar a:hover { background:rgba(255,255,255,0.07); color:white; }
    .sidebar a.active { background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(99,102,241,0.1)); color:#a5b4fc; border:1px solid rgba(99,102,241,0.3); }
    .sidebar a.active i { color:#6366f1; }
    .admin-only-badge { font-size:9px; font-weight:700; background:rgba(99,102,241,0.25); color:#a5b4fc; padding:1px 6px; border-radius:10px; margin-left:auto; }
    .notif-badge { background:#ef4444; color:white; padding:1px 7px; border-radius:20px; font-size:10px; font-weight:700; margin-left:auto; }
    .sidebar-footer { padding:10px 8px; border-top:1px solid rgba(255,255,255,0.06); }
    .sidebar-footer a { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:rgba(255,255,255,0.4); text-decoration:none; font-size:13px; }
    .sidebar-footer a:hover { background:rgba(239,68,68,0.1); color:#fca5a5; }

    /* CONTENT */
    .content { margin-left:240px; padding:24px; }
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
    .page-header h3 { font-size:20px; font-weight:700; color:#0f172a; margin:0; }

    /* LAYOUT avec formulaire */
    .page-layout { display:grid; grid-template-columns:1fr; gap:20px; }
    .page-layout.with-form { grid-template-columns:360px 1fr; align-items:start; }

    /* FORMULAIRE MODIFICATION */
    .edit-card {
        background:white; border-radius:16px; padding:24px;
        box-shadow:0 4px 20px rgba(245,158,11,0.15);
        border:2px solid #fde68a; position:sticky; top:20px;
    }
    .edit-card-header { display:flex; align-items:center; gap:12px; margin-bottom:20px; padding-bottom:16px; border-bottom:2px solid #fef3c7; }
    .edit-card-icon { width:44px; height:44px; background:linear-gradient(135deg,#fef3c7,#fde68a); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; }
    .edit-card-header h5 { font-size:16px; font-weight:800; color:#0f172a; margin:0; }
    .edit-card-header p  { font-size:12px; color:#64748b; margin:2px 0 0; }
    .edit-mode-tag { display:inline-flex; align-items:center; gap:4px; background:#fef3c7; color:#d97706; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; margin-top:4px; }

    .form-group-edit { margin-bottom:16px; }
    .form-group-edit label { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#0f172a; margin-bottom:6px; }
    .lbl-icon-sm { width:20px; height:20px; background:#fef3c7; color:#d97706; border-radius:5px; display:flex; align-items:center; justify-content:center; font-size:11px; }
    .input-edit { width:100%; padding:10px 14px 10px 38px; border:1.5px solid #e2e8f0; border-radius:10px; font-family:'Poppins',sans-serif; font-size:13px; color:#0f172a; background:#fafbff; outline:none; transition:all 0.2s; }
    .input-edit:focus { border-color:#f59e0b; background:white; box-shadow:0 0 0 3px rgba(245,158,11,0.1); }
    .input-edit::placeholder { color:#cbd5e1; }
    .input-wrap-sm { position:relative; }
    .input-icon-sm { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:14px; color:#94a3b8; pointer-events:none; }
    .input-wrap-sm:focus-within .input-icon-sm { color:#f59e0b; }

    .btn-save { width:100%; padding:12px; background:linear-gradient(135deg,#f59e0b,#d97706); color:white; border:none; border-radius:10px; font-family:'Poppins',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; justify-content:center; gap:7px; box-shadow:0 4px 12px rgba(245,158,11,0.3); }
    .btn-save:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(245,158,11,0.4); }
    .btn-cancel-edit { width:100%; padding:11px; background:white; color:#64748b; border:1.5px solid #e2e8f0; border-radius:10px; font-family:'Poppins',sans-serif; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; justify-content:center; gap:7px; margin-top:8px; text-decoration:none; }
    .btn-cancel-edit:hover { border-color:#ef4444; color:#ef4444; background:#fef2f2; }

    /* ALERT */
    .alert-msg { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:12px; font-size:13px; font-weight:600; margin-bottom:20px; animation:fadeIn 0.3s ease; }
    .alert-msg i { font-size:18px; flex-shrink:0; }
    .alert-msg.success { background:#f0fdf4; color:#16a34a; border:1.5px solid #bbf7d0; }
    .alert-msg.error   { background:#fef2f2; color:#dc2626; border:1.5px solid #fecaca; }
    @keyframes fadeIn { from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:translateY(0);} }

    /* SUPPLIER CARDS */
    .supplier-card {
        background:white; border-radius:16px; padding:18px 22px;
        box-shadow:0 2px 12px rgba(0,0,0,0.05); border:1px solid #e2e8f0;
        display:flex; justify-content:space-between; align-items:center;
        margin-bottom:12px; transition:all 0.2s;
    }
    .supplier-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.08); }
    .supplier-card.editing { border-color:#f59e0b; background:#fffbeb; box-shadow:0 4px 16px rgba(245,158,11,0.15); }

    .supplier-avatar { width:48px; height:48px; border-radius:14px; background:linear-gradient(135deg,#3b82f6,#6366f1); display:flex; align-items:center; justify-content:center; color:white; font-size:20px; font-weight:800; flex-shrink:0; }
    .supplier-name   { font-size:15px; font-weight:700; color:#0f172a; margin:0; }
    .supplier-phone  { font-size:12px; color:#64748b; margin-top:2px; }
    .supplier-email  { font-size:12px; color:#94a3b8; margin-top:1px; }
    .supplier-date   { font-size:11px; color:#94a3b8; margin-top:3px; }

    .supplier-stats { display:flex; gap:20px; align-items:center; }
    .stat-pill { text-align:center; }
    .stat-pill-value { font-size:18px; font-weight:800; color:#0f172a; }
    .stat-pill-label { font-size:11px; color:#94a3b8; }
    .stat-pill-value.blue  { color:#3b82f6; }
    .stat-pill-value.green { color:#10b981; font-size:13px; }

    /* ACTION BUTTONS */
    .actions { display:flex; gap:6px; align-items:center; margin-left:16px; }
    .action-btn { width:32px; height:32px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; font-size:14px; text-decoration:none; transition:all 0.2s; border:none; cursor:pointer; flex-shrink:0; }
    .action-btn.edit-btn   { background:#fef3c7; color:#d97706; }
    .action-btn.edit-btn:hover   { background:#f59e0b; color:white; transform:scale(1.1); }
    .action-btn.delete-btn { background:#fef2f2; color:#ef4444; }
    .action-btn.delete-btn:hover { background:#ef4444; color:white; transform:scale(1.1); }

    /* EMPTY */
    .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-state i { font-size:48px; opacity:0.3; margin-bottom:12px; display:block; }

    /* STATS TOP */
    .top-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
    .top-stat { background:white; border-radius:14px; padding:16px 18px; display:flex; align-items:center; gap:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04); border:1px solid #e2e8f0; }
    .top-stat-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
    .top-stat-icon.blue   { background:#dbeafe; color:#3b82f6; }
    .top-stat-icon.green  { background:#d1fae5; color:#10b981; }
    .top-stat-icon.orange { background:#fef3c7; color:#f59e0b; }
    .top-stat-value { font-size:20px; font-weight:800; color:#0f172a; line-height:1; }
    .top-stat-label { font-size:12px; color:#64748b; margin-top:3px; }

    /* EDITING BADGE */
    .editing-badge { background:#fef3c7; color:#d97706; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; margin-left:8px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="text-center px-3 pt-2 pb-1"><h4>📦 Stock App</h4></div>
    <div class="sidebar-profile">
        <?php if(!empty($currentUser['photo'])): ?>
            <img src="../uploads/<?= htmlspecialchars($currentUser['photo']) ?>" alt="">
        <?php else: ?>
            <div class="sidebar-avatar-placeholder"><?= strtoupper(substr($_SESSION['user'],0,1)) ?></div>
        <?php endif; ?>
        <div>
            <div class="sidebar-profile-name"><?= htmlspecialchars($_SESSION['user']) ?></div>
            <?php if(isAdmin()): ?>
                <div class="sidebar-profile-role role-admin">👑 Admin</div>
            <?php else: ?>
                <div class="sidebar-profile-role role-employee">👤 Employé</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="nav-section">Inventaire</div>
        <a href="../products/list.php"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="../products/add.php"><i class="bi bi-plus-circle"></i> Ajouter produit</a>
        <div class="nav-section">Achats</div>
        <a href="add.php"><i class="bi bi-bag-plus"></i> Nouvel achat</a>
        <a href="list.php"><i class="bi bi-clock-history"></i> Historique achats</a>
        <a href="suppliers.php" class="active"><i class="bi bi-building"></i> Fournisseurs</a>
        <div class="nav-section">Ventes</div>
        <a href="../sales/sell.php"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
        <a href="../sales/list.php"><i class="bi bi-clock-history"></i> Historique ventes</a>
        <div class="nav-section">Alertes</div>
        <a href="../notifications/index.php">
            <i class="bi bi-bell<?= $bellCount > 0 ? '-fill' : '' ?>"></i> Notifications
            <?php if($bellCount > 0): ?>
            <span class="notif-badge"><?= $bellCount > 9 ? '9+' : $bellCount ?></span>
            <?php endif; ?>
        </a>
        <?php if(isAdmin()): ?>
        <div class="nav-section">Administration</div>
        <a href="../admin/create_employee.php"><i class="bi bi-people"></i> Employés <span class="admin-only-badge">Admin</span></a>
        <a href="../admin/profile.php"><i class="bi bi-person-circle"></i> Mon profil <span class="admin-only-badge">Admin</span></a>
        <?php endif; ?>
    </div>
    <div class="sidebar-footer">
        <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </div>
</div>

<!-- CONTENT -->
<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h3>🏢 Fournisseurs</h3>
            <p style="font-size:13px;color:#64748b;margin:3px 0 0;">
                <?= $editMode ? '✏️ Modification de : ' . htmlspecialchars($editSupplier['name']) : 'Créés automatiquement lors des achats' ?>
            </p>
        </div>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="bi bi-bag-plus"></i> Nouvel achat
        </a>
    </div>

    <!-- ALERT -->
    <?php if($message): ?>
    <div class="alert-msg <?= $msgType ?>" id="alertMsg">
        <i class="bi bi-<?= $msgType==='success' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
        <?= $message ?>
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <?php
    $statsQ   = $conn->query("SELECT COUNT(*) AS nb, SUM(p2.quantity*p2.unit_price) AS total FROM suppliers s LEFT JOIN purchases p2 ON p2.supplier_id=s.id");
    $statsTop = $statsQ->fetch_assoc();
    $nbAchats = $conn->query("SELECT COUNT(*) AS c FROM purchases")->fetch_assoc()['c'];
    ?>
    <div class="top-stats">
        <div class="top-stat">
            <div class="top-stat-icon blue"><i class="bi bi-building"></i></div>
            <div>
                <div class="top-stat-value"><?= $suppliers->num_rows ?></div>
                <div class="top-stat-label">Fournisseurs</div>
            </div>
        </div>
        <div class="top-stat">
            <div class="top-stat-icon orange"><i class="bi bi-bag-check"></i></div>
            <div>
                <div class="top-stat-value"><?= $nbAchats ?></div>
                <div class="top-stat-label">Total achats</div>
            </div>
        </div>
        <div class="top-stat">
            <div class="top-stat-icon green"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="top-stat-value" style="font-size:15px;"><?= number_format($statsTop['total'] ?? 0, 0, '', ' ') ?> F</div>
                <div class="top-stat-label">Total dépensé</div>
            </div>
        </div>
    </div>

    <!-- LAYOUT -->
    <div class="page-layout <?= $editMode ? 'with-form' : '' ?>">

        <!-- ══ FORMULAIRE MODIFICATION (visible si edit mode) ══ -->
        <?php if($editMode && isAdmin()): ?>
        <div>
            <div class="edit-card">
                <div class="edit-card-header">
                    <div class="edit-card-icon">✏️</div>
                    <div>
                        <h5>Modifier le fournisseur</h5>
                        <p><?= htmlspecialchars($editSupplier['name']) ?></p>
                        <div class="edit-mode-tag">✏️ Mode modification</div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action"      value="edit">
                    <input type="hidden" name="supplier_id" value="<?= $editSupplier['id'] ?>">

                    <!-- NOM -->
                    <div class="form-group-edit">
                        <label>
                            <span class="lbl-icon-sm"><i class="bi bi-building"></i></span>
                            Nom du fournisseur *
                        </label>
                        <div class="input-wrap-sm">
                            <i class="bi bi-building input-icon-sm"></i>
                            <input type="text" name="name" class="input-edit"
                                   value="<?= htmlspecialchars($editSupplier['name']) ?>"
                                   placeholder="Ex: Diallo Commerce" required>
                        </div>
                    </div>

                    <!-- TÉLÉPHONE -->
                    <div class="form-group-edit">
                        <label>
                            <span class="lbl-icon-sm"><i class="bi bi-telephone"></i></span>
                            Numéro de téléphone
                        </label>
                        <div class="input-wrap-sm">
                            <i class="bi bi-telephone input-icon-sm"></i>
                            <input type="text" name="phone" class="input-edit"
                                   value="<?= htmlspecialchars($editSupplier['phone'] ?? '') ?>"
                                   placeholder="Ex: 77 123 45 67">
                        </div>
                    </div>

                    <!-- EMAIL -->
                    <div class="form-group-edit">
                        <label>
                            <span class="lbl-icon-sm"><i class="bi bi-envelope"></i></span>
                            Email du fournisseur
                        </label>
                        <div class="input-wrap-sm">
                            <i class="bi bi-envelope input-icon-sm"></i>
                            <input type="email" name="email" class="input-edit"
                                   value="<?= htmlspecialchars($editSupplier['email'] ?? '') ?>"
                                   placeholder="fournisseur@email.com">
                        </div>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="bi bi-check-circle-fill"></i>
                        Enregistrer les modifications
                    </button>
                    <a href="suppliers.php" class="btn-cancel-edit">
                        <i class="bi bi-arrow-left"></i> Annuler
                    </a>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ LISTE FOURNISSEURS ══ -->
        <div>
            <?php
            // Re-fetch après modifications éventuelles
            $suppliersList = $conn->query("
                SELECT s.*, COUNT(p.id) AS total_achats, SUM(p.quantity * p.unit_price) AS total_depense
                FROM suppliers s
                LEFT JOIN purchases p ON p.supplier_id = s.id
                GROUP BY s.id ORDER BY s.created_at DESC
            ");

            if($suppliersList->num_rows === 0):
            ?>
            <div class="empty-state">
                <i class="bi bi-building"></i>
                <p>Aucun fournisseur enregistré</p>
                <a href="add.php" class="btn btn-primary btn-sm mt-2">
                    Enregistrer un premier achat
                </a>
            </div>

            <?php else: while($s = $suppliersList->fetch_assoc()):
                $isEditing = ($editMode && $editSupplier['id'] == $s['id']);
            ?>
            <div class="supplier-card <?= $isEditing ? 'editing' : '' ?>">

                <!-- INFOS GAUCHE -->
                <div style="display:flex;align-items:center;gap:16px;flex:1;">
                    <div class="supplier-avatar"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                    <div>
                        <div class="supplier-name">
                            <?= htmlspecialchars($s['name']) ?>
                            <?php if($isEditing): ?>
                            <span class="editing-badge">✏️ En cours</span>
                            <?php endif; ?>
                        </div>
                        <div class="supplier-phone">
                            <?php if($s['phone']): ?>
                                <i class="bi bi-telephone"></i> <?= htmlspecialchars($s['phone']) ?>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">Pas de numéro</span>
                            <?php endif; ?>
                        </div>
                        <?php if(!empty($s['email'])): ?>
                        <div class="supplier-email">
                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($s['email']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="supplier-date">
                            <i class="bi bi-calendar3"></i> Depuis le <?= date('d/m/Y', strtotime($s['created_at'])) ?>
                        </div>
                    </div>
                </div>

                <!-- STATS DROITE -->
                <div style="display:flex;align-items:center;gap:0;">
                    <div class="supplier-stats">
                        <div class="stat-pill">
                            <div class="stat-pill-value blue"><?= $s['total_achats'] ?></div>
                            <div class="stat-pill-label">Achats</div>
                        </div>
                        <div class="stat-pill">
                            <div class="stat-pill-value green">
                                <?= number_format($s['total_depense'] ?? 0, 0, '', ' ') ?> F
                            </div>
                            <div class="stat-pill-label">Dépensé</div>
                        </div>
                    </div>

                    <!-- ACTIONS (admin seulement) -->
                    <?php if(isAdmin()): ?>
                    <div class="actions">
                        <!-- MODIFIER -->
                        <a href="suppliers.php?edit=<?= $s['id'] ?>"
                           class="action-btn edit-btn" title="Modifier ce fournisseur">
                            <i class="bi bi-pencil-fill"></i>
                        </a>

                        <!-- SUPPRIMER -->
                        <?php if($s['total_achats'] == 0): ?>
                        <a href="suppliers.php?delete=<?= $s['id'] ?>"
                           class="action-btn delete-btn"
                           onclick="return confirm('Supprimer le fournisseur \"<?= htmlspecialchars($s['name']) ?>\" ?')"
                           title="Supprimer ce fournisseur">
                            <i class="bi bi-trash-fill"></i>
                        </a>
                        <?php else: ?>
                        <button class="action-btn delete-btn"
                                style="opacity:0.35;cursor:not-allowed;"
                                title="Impossible — ce fournisseur a <?= $s['total_achats'] ?> achat(s)"
                                disabled>
                            <i class="bi bi-trash-fill"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
            <?php endwhile; endif; ?>
        </div>

    </div><!-- /page-layout -->
</div>

<script>
setTimeout(function(){
    const alert = document.getElementById('alertMsg');
    if(alert){
        alert.style.transition = 'opacity 0.8s';
        alert.style.opacity    = '0';
        setTimeout(() => alert.remove(), 800);
    }
}, 8000);
</script>
</body>
</html>