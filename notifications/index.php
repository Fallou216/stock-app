<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');
requireLogin();

// ✅ Définir STOCK_SEUIL ici directement (sans dépendre de check.php)
if(!defined('STOCK_SEUIL')){
    define('STOCK_SEUIL', 5);
}

$currentUser = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

// Marquer toutes comme lues
if(isset($_GET['mark_all_read'])){
    $conn->query("UPDATE notifications SET is_read=1 WHERE type='stock_alert'");
    header("Location: index.php");
    exit();
}

// Marquer une seule comme lue
if(isset($_GET['mark_read'])){
    $id = intval($_GET['mark_read']);
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$id");
    header("Location: index.php");
    exit();
}

// Récupérer toutes les notifications stock
$notifications = $conn->query("
    SELECT n.*, p.name AS product_name, p.quantity AS current_qty
    FROM notifications n
    LEFT JOIN products p ON n.product_id = p.id
    WHERE n.type = 'stock_alert'
    ORDER BY n.created_at DESC
");

// Compter non lues
$unread = $conn->query("
    SELECT COUNT(*) AS c FROM notifications
    WHERE type='stock_alert' AND is_read=0
")->fetch_assoc()['c'];

// Stats
$ruptures    = $conn->query("SELECT COUNT(*) AS c FROM products WHERE quantity=0")->fetch_assoc()['c'];
$stockFaible = $conn->query("SELECT COUNT(*) AS c FROM products WHERE quantity>0 AND quantity<=" . STOCK_SEUIL)->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f4f6f9;
    }

    .sidebar {
        position: fixed;
        width: 230px;
        height: 100%;
        background: #111827;
        padding-top: 20px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        z-index: 100;
    }

    .sidebar::before {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.25), transparent);
        top: -60px;
        right: -60px;
        border-radius: 50%;
        pointer-events: none;
    }

    .sidebar-profile {
        margin: 0 10px 14px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.05));
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 14px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        z-index: 1;
    }

    .sidebar-profile img {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.2);
        flex-shrink: 0;
    }

    .sidebar-avatar-placeholder {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 15px;
        flex-shrink: 0;
    }

    .sidebar-profile-name {
        color: white;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar-profile-role {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 20px;
        margin-top: 3px;
    }

    .role-admin {
        background: rgba(99, 102, 241, 0.25);
        color: #a5b4fc;
    }

    .role-employee {
        background: rgba(16, 185, 129, 0.2);
        color: #6ee7b7;
    }

    .nav-section {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.5px;
        color: rgba(255, 255, 255, 0.25);
        text-transform: uppercase;
        padding: 0 14px;
        margin: 12px 0 5px;
    }

    .sidebar-nav {
        flex: 1;
        padding: 0 8px;
        overflow-y: auto;
    }

    .sidebar h4 {
        color: white;
        padding: 0 14px;
        margin-bottom: 8px;
        font-size: 15px;
    }

    .sidebar a {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #9ca3af;
        padding: 10px 12px;
        text-decoration: none;
        transition: 0.3s;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 2px;
    }

    .sidebar a i {
        font-size: 16px;
        width: 18px;
        text-align: center;
    }

    .sidebar a:hover {
        background: rgba(255, 255, 255, 0.07);
        color: white;
    }

    .sidebar a.active {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(99, 102, 241, 0.1));
        color: #a5b4fc;
        border: 1px solid rgba(99, 102, 241, 0.3);
    }

    .sidebar a.active i {
        color: #6366f1;
    }

    .admin-only-badge {
        font-size: 9px;
        font-weight: 700;
        background: rgba(99, 102, 241, 0.25);
        color: #a5b4fc;
        padding: 1px 6px;
        border-radius: 10px;
        margin-left: auto;
    }

    .notif-badge {
        background: #ef4444;
        color: white;
        padding: 1px 7px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        margin-left: auto;
        animation: pulse-badge 2s infinite;
    }

    @keyframes pulse-badge {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }

        50% {
            box-shadow: 0 0 0 5px rgba(239, 68, 68, 0);
        }
    }

    .sidebar-footer {
        padding: 10px 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        color: rgba(255, 255, 255, 0.4);
        text-decoration: none;
        font-size: 13px;
    }

    .sidebar-footer a:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #fca5a5;
    }

    /* CONTENT */
    .content {
        margin-left: 240px;
        padding: 24px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-header h3 {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .page-header p {
        font-size: 13px;
        color: #64748b;
        margin: 3px 0 0;
    }

    /* NOTIFICATION CARD */
    .notif-card {
        background: white;
        border-radius: 16px;
        padding: 18px 20px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
        position: relative;
    }

    .notif-card:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    }

    .notif-card.unread {
        border-left: 4px solid #ef4444;
        background: #fff8f8;
    }

    .notif-card.read {
        border-left: 4px solid #e2e8f0;
        opacity: 0.7;
    }

    .notif-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .notif-icon.rupture {
        background: #fee2e2;
    }

    .notif-icon.faible {
        background: #fef3c7;
    }

    .notif-body {
        flex: 1;
    }

    .notif-msg {
        font-size: 14px;
        font-weight: 500;
        color: #0f172a;
        margin-bottom: 4px;
        line-height: 1.5;
    }

    .notif-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 12px;
        color: #94a3b8;
    }

    .notif-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .qty-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    .qty-badge.zero {
        background: #fee2e2;
        color: #dc2626;
    }

    .qty-badge.low {
        background: #fef3c7;
        color: #d97706;
    }

    .btn-read {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        font-size: 14px;
    }

    .btn-read:hover {
        background: #10b981;
        color: white;
    }

    .unread-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #ef4444;
        flex-shrink: 0;
        box-shadow: 0 0 6px rgba(239, 68, 68, 0.5);
    }

    /* EMPTY */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 56px;
        opacity: 0.3;
        margin-bottom: 16px;
        display: block;
    }

    .empty-state h5 {
        color: #64748b;
        font-size: 16px;
        margin-bottom: 6px;
    }

    /* STATS */
    .notif-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }

    .notif-stat {
        background: white;
        border-radius: 14px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        border: 1px solid #e2e8f0;
    }

    .notif-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        flex-shrink: 0;
    }

    .notif-stat-icon.red {
        background: #fee2e2;
        color: #ef4444;
    }

    .notif-stat-icon.orange {
        background: #fef3c7;
        color: #f59e0b;
    }

    .notif-stat-icon.green {
        background: #d1fae5;
        color: #10b981;
    }

    .notif-stat-value {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
    }

    .notif-stat-label {
        font-size: 12px;
        color: #64748b;
        margin-top: 3px;
    }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="text-center px-3 pt-2 pb-1">
            <h4>📦 Stock App</h4>
        </div>
        <div class="sidebar-profile">
            <?php if(!empty($currentUser['photo'])): ?>
            <img src="../uploads/<?= htmlspecialchars($currentUser['photo']) ?>" alt="">
            <?php else: ?>
            <div class="sidebar-avatar-placeholder"><?= strtoupper(substr($_SESSION['user'],0,1)) ?></div>
            <?php endif; ?>
            <div style="overflow:hidden;">
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
            <a href="../purchases/add.php"><i class="bi bi-bag-plus"></i> Nouvel achat</a>
            <a href="../purchases/list.php"><i class="bi bi-clock-history"></i> Historique achats</a>
            <a href="../purchases/suppliers.php"><i class="bi bi-building"></i> Fournisseurs</a>
            <div class="nav-section">Ventes</div>
            <a href="../sales/sell.php"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
            <a href="../sales/list.php"><i class="bi bi-clock-history"></i> Historique ventes</a>
            <div class="nav-section">Alertes</div>
            <a href="index.php" class="active">
                <i class="bi bi-bell-fill"></i> Notifications
                <?php if($unread > 0): ?>
                <span class="notif-badge"><?= $unread > 9 ? '9+' : $unread ?></span>
                <?php endif; ?>
            </a>
            <?php if(isAdmin()): ?>
            <div class="nav-section">Administration</div>
            <a href="../admin/create_employee.php"><i class="bi bi-people"></i> Employés <span
                    class="admin-only-badge">Admin</span></a>
            <a href="../admin/profile.php"><i class="bi bi-person-circle"></i> Mon profil <span
                    class="admin-only-badge">Admin</span></a>
            <?php endif; ?>
        </div>
        <div class="sidebar-footer">
            <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <div class="page-header">
            <div>
                <h3>🔔 Notifications</h3>
                <p>Alertes de stock faible et ruptures détectées</p>
            </div>
            <?php if($unread > 0): ?>
            <a href="index.php?mark_all_read=1" class="btn btn-sm btn-outline-success">
                <i class="bi bi-check-all"></i> Tout marquer comme lu
            </a>
            <?php endif; ?>
        </div>

        <!-- STATS -->
        <div class="notif-stats">
            <div class="notif-stat">
                <div class="notif-stat-icon red"><i class="bi bi-bell-fill"></i></div>
                <div>
                    <div class="notif-stat-value"><?= $unread ?></div>
                    <div class="notif-stat-label">Non lues</div>
                </div>
            </div>
            <div class="notif-stat">
                <div class="notif-stat-icon orange"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <div class="notif-stat-value"><?= $stockFaible ?></div>
                    <div class="notif-stat-label">Stock faible</div>
                </div>
            </div>
            <div class="notif-stat">
                <div class="notif-stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
                <div>
                    <div class="notif-stat-value"><?= $ruptures ?></div>
                    <div class="notif-stat-label">Ruptures</div>
                </div>
            </div>
        </div>

        <!-- LISTE NOTIFICATIONS -->
        <?php if($notifications->num_rows === 0): ?>
        <div class="empty-state">
            <i class="bi bi-bell-slash"></i>
            <h5>Aucune notification</h5>
            <p>Tous vos produits ont un stock suffisant. 🎉</p>
            <a href="../products/list.php" class="btn btn-primary btn-sm mt-2">
                <i class="bi bi-box-seam"></i> Voir les produits
            </a>
        </div>

        <?php else:
        $notifList = $conn->query("
            SELECT n.*, p.name AS product_name, p.quantity AS current_qty
            FROM notifications n
            LEFT JOIN products p ON n.product_id = p.id
            WHERE n.type = 'stock_alert'
            ORDER BY n.is_read ASC, n.created_at DESC
        ");
        while($n = $notifList->fetch_assoc()):
            $isRupture = (intval($n['current_qty']) === 0);
            $cardClass = $n['is_read'] ? 'read' : 'unread';
            $iconClass = $isRupture ? 'rupture' : 'faible';
            $icon      = $isRupture ? '❌' : '⚠️';
            $qtyClass  = $isRupture ? 'zero' : 'low';
    ?>
        <div class="notif-card <?= $cardClass ?>">
            <?php if(!$n['is_read']): ?>
            <div class="unread-dot"></div>
            <?php endif; ?>

            <div class="notif-icon <?= $iconClass ?>"><?= $icon ?></div>

            <div class="notif-body">
                <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                <div class="notif-meta">
                    <span><i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></span>
                    <?php if($n['product_name']): ?>
                    <span><i class="bi bi-box-seam"></i> <?= htmlspecialchars($n['product_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="notif-actions">
                <?php if($n['current_qty'] !== null): ?>
                <span class="qty-badge <?= $qtyClass ?>">
                    <?= intval($n['current_qty']) ?> unité(s)
                </span>
                <?php endif; ?>

                <?php if($n['product_id']): ?>
                <a href="../products/list.php" class="btn-read" title="Voir les produits">
                    <i class="bi bi-eye"></i>
                </a>
                <?php endif; ?>

                <?php if(!$n['is_read']): ?>
                <a href="index.php?mark_read=<?= $n['id'] ?>" class="btn-read" title="Marquer comme lu">
                    <i class="bi bi-check"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; endif; ?>

    </div>
</body>

</html>