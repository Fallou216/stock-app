<?php 
session_start();
if(!isset($_SESSION['user'])){
    header("Location: auth/login.php");
    exit();
}

// ✅ config/db.php chargé UNE SEULE FOIS
include_once('config/db.php');

// Stats produits
$productRes = $conn->query("SELECT COUNT(*) AS total_products, SUM(quantity * price) AS total_value FROM products");
$stats = $productRes->fetch_assoc();

// Stats utilisateurs
$userRes = $conn->query("SELECT COUNT(*) AS total_users FROM users");
$userCount = $userRes->fetch_assoc()['total_users'];

// Stats ventes
$salesRes = $conn->query("SELECT COUNT(*) AS total_sales, SUM(quantity_sold * sale_price) AS total_revenue FROM sales");
$salesStats = $salesRes->fetch_assoc();

// Stats achats
$achatsRes         = $conn->query("SELECT COUNT(*) AS total_achats, SUM(quantity * unit_price) AS total_depense FROM purchases");
$achatsStats       = $achatsRes->fetch_assoc();
$totalFournisseurs = $conn->query("SELECT COUNT(*) AS c FROM suppliers")->fetch_assoc()['c'];

// Produits récents
$recentProducts = $conn->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5");

// Ventes récentes
$recentSales = $conn->query("
    SELECT s.*, p.name AS product_name 
    FROM sales s 
    JOIN products p ON s.product_id = p.id 
    ORDER BY s.sold_at DESC 
    LIMIT 5
");

// Achats récents
$recentPurchases = $conn->query("
    SELECT p.*, pr.name AS product_name, s.name AS supplier_name, s.phone AS supplier_phone
    FROM purchases p
    JOIN products  pr ON p.product_id  = pr.id
    JOIN suppliers s  ON p.supplier_id = s.id
    ORDER BY p.purchased_at DESC
    LIMIT 5
");

// Diagramme circulaire
$pieData = $conn->query("
    SELECT p.name, SUM(s.quantity_sold) AS total_vendu
    FROM sales s JOIN products p ON s.product_id = p.id
    GROUP BY p.id ORDER BY total_vendu DESC LIMIT 5
");
$pieLabels = []; $pieValues = [];
while($r = $pieData->fetch_assoc()){
    $pieLabels[] = $r['name'];
    $pieValues[] = (int)$r['total_vendu'];
}

// Diagramme barres
$barData = $conn->query("
    SELECT DATE(sold_at) AS jour, SUM(quantity_sold * sale_price) AS revenu
    FROM sales WHERE sold_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(sold_at) ORDER BY jour ASC
");
$barLabels = []; $barValues = [];
while($r = $barData->fetch_assoc()){
    $barLabels[] = date('d/m', strtotime($r['jour']));
    $barValues[] = (float)$r['revenu'];
}

// Infos utilisateur connecté
$currentUser = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

// ✅ Compteur notifications non lues
$bellCount = 0;
$bellRes = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='stock_alert' AND is_read=0");
if($bellRes) $bellCount = $bellRes->fetch_assoc()['c'];

// ✅ Vérification automatique du stock (génère notifications)
define('SKIP_STOCK_CHECK', false);
if(file_exists('notifications/check.php')){
    include_once 'notifications/check.php';
    // Re-compter après vérification
    $bellRes2 = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='stock_alert' AND is_read=0");
    if($bellRes2) $bellCount = $bellRes2->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f4f6f9;
    }

    /* SIDEBAR */
    .sidebar {
        position: fixed;
        width: 240px;
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
        margin: 0 12px 16px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.05));
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 14px;
        padding: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        z-index: 1;
    }

    .sidebar-profile img {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.2);
        flex-shrink: 0;
    }

    .sidebar-avatar-placeholder {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .sidebar-profile-info {
        overflow: hidden;
    }

    .sidebar-profile-name {
        color: white;
        font-size: 13px;
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
        padding: 2px 8px;
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
        padding: 0 16px;
        margin: 14px 0 6px;
    }

    .sidebar-nav {
        flex: 1;
        padding: 0 8px;
        overflow-y: auto;
    }

    .sidebar h4 {
        color: white;
        margin-bottom: 8px;
        padding: 0 16px;
        font-size: 16px;
    }

    .sidebar a {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #9ca3af;
        padding: 11px 14px;
        text-decoration: none;
        transition: 0.3s;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 2px;
    }

    .sidebar a i {
        font-size: 17px;
        width: 20px;
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

    /* BADGE NOTIFICATION SIDEBAR */
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

    .sidebar-notif-link {
        background: rgba(239, 68, 68, 0.08) !important;
        border: 1px solid rgba(239, 68, 68, 0.2) !important;
    }

    .sidebar-notif-link i {
        color: #ef4444 !important;
    }

    .sidebar-notif-link:hover {
        background: rgba(239, 68, 68, 0.15) !important;
        color: #fca5a5 !important;
    }

    .sidebar-footer {
        padding: 12px 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 10px;
        color: rgba(255, 255, 255, 0.4);
        text-decoration: none;
        font-size: 13px;
        transition: all 0.2s;
    }

    .sidebar-footer a:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #fca5a5;
    }

    /* CONTENT */
    .content {
        margin-left: 250px;
        padding: 20px;
    }

    /* TOPBAR */
    .topbar {
        background: white;
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    }

    .topbar-left h5 {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        color: #0f172a;
    }

    .topbar-left p {
        margin: 2px 0 0;
        font-size: 12px;
        color: #64748b;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .topbar-date {
        background: #f8fafc;
        padding: 7px 14px;
        border-radius: 10px;
        font-size: 12px;
        color: #64748b;
        border: 1px solid #e2e8f0;
        font-weight: 500;
    }

    /* CLOCHE TOPBAR */
    .topbar-bell {
        position: relative;
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #64748b;
        transition: all 0.2s;
        cursor: pointer;
    }

    .topbar-bell:hover {
        background: #fee2e2;
        color: #ef4444;
        border-color: #fecaca;
    }

    .topbar-bell i {
        font-size: 18px;
    }

    .topbar-bell .bell-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        animation: pulse-badge 2s infinite;
    }

    .topbar-bell.has-notif {
        background: #fff0f0;
        border-color: #fecaca;
        color: #ef4444;
    }

    .topbar-avatar img {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        object-fit: cover;
        border: 3px solid #ede9fe;
        cursor: pointer;
    }

    .topbar-avatar-placeholder {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
        border: 3px solid #ede9fe;
        cursor: pointer;
    }

    .topbar-role-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    .topbar-role-badge.admin {
        background: #ede9fe;
        color: #6366f1;
    }

    .topbar-role-badge.employee {
        background: #d1fae5;
        color: #059669;
    }

    /* CARDS */
    .card-box {
        border-radius: 15px;
        padding: 20px;
        color: white;
        transition: 0.3s;
    }

    .card-box:hover {
        transform: translateY(-5px);
    }

    .bg-blue {
        background: linear-gradient(135deg, #3b82f6, #6366f1);
    }

    .bg-green {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .bg-orange {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .bg-pink {
        background: linear-gradient(135deg, #ec4899, #db2777);
    }

    .bg-indigo {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
    }

    .bg-teal {
        background: linear-gradient(135deg, #14b8a6, #0d9488);
    }

    .bg-violet {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .bg-red {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }

    /* ALERTE STOCK BANNER */
    .stock-alert-banner {
        background: linear-gradient(135deg, #fee2e2, #fef2f2);
        border: 1.5px solid #fecaca;
        border-radius: 14px;
        padding: 14px 20px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stock-alert-banner-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .stock-alert-banner-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: white;
        flex-shrink: 0;
    }

    .stock-alert-banner h6 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: #991b1b;
    }

    .stock-alert-banner p {
        margin: 2px 0 0;
        font-size: 12px;
        color: #dc2626;
    }

    .stock-alert-banner a {
        background: #ef4444;
        color: white;
        padding: 8px 16px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .stock-alert-banner a:hover {
        background: #dc2626;
        color: white;
    }

    .table th {
        background: #4f46e5;
        color: white;
    }

    .table tr:hover {
        background: #f1f1f1;
    }

    .btn {
        border-radius: 8px;
    }

    .search-box {
        max-width: 300px;
    }

    .badge-stock-low {
        background: #fee2e2;
        color: #dc2626;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 12px;
    }

    .section-title {
        font-size: 13px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 4px 0;
        border-bottom: 2px solid #e2e8f0;
        margin: 24px 0 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .purchase-row td {
        font-size: 13px;
    }

    .supplier-badge {
        background: #dbeafe;
        color: #2563eb;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .chart-card {
        background: white;
        border-radius: 15px;
        padding: 20px 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        height: 100%;
    }

    .chart-title {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
        border-bottom: 2px solid #f3f4f6;
        padding-bottom: 8px;
    }

    .chart-wrapper {
        position: relative;
        height: 220px;
    }

    .admin-banner {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        border-radius: 14px;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        color: white;
    }

    .admin-banner h6 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
    }

    .admin-banner p {
        margin: 3px 0 0;
        font-size: 12px;
        opacity: 0.8;
    }

    .admin-banner a {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 16px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid rgba(255, 255, 255, 0.3);
        transition: all 0.2s;
        white-space: nowrap;
    }

    .admin-banner a:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    </style>
</head>

<body>

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <div class="text-center px-3 pt-2 pb-2">
            <h4>📦 Stock App</h4>
        </div>

        <!-- PROFIL -->
        <div class="sidebar-profile">
            <?php if(!empty($currentUser['photo'])): ?>
            <img src="uploads/<?= htmlspecialchars($currentUser['photo']) ?>" alt="Photo">
            <?php else: ?>
            <div class="sidebar-avatar-placeholder">
                <?= strtoupper(substr($_SESSION['user'], 0, 1)) ?>
            </div>
            <?php endif; ?>
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name"><?= htmlspecialchars($_SESSION['user']) ?></div>
                <?php if(isAdmin()): ?>
                <div class="sidebar-profile-role role-admin">👑 Administrateur</div>
                <?php else: ?>
                <div class="sidebar-profile-role role-employee">👤 Employé</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- NAV -->
        <div class="sidebar-nav">
            <div class="nav-section">Principal</div>
            <a href="dashboard.php" class="active">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <div class="nav-section">Inventaire</div>
            <a href="products/list.php">
                <i class="bi bi-box-seam"></i> Produits
            </a>
            <a href="products/add.php">
                <i class="bi bi-plus-circle"></i> Ajouter produit
            </a>

            <div class="nav-section">Achats</div>
            <a href="purchases/add.php">
                <i class="bi bi-bag-plus"></i> Nouvel achat
            </a>
            <a href="purchases/list.php">
                <i class="bi bi-clock-history"></i> Historique achats
            </a>
            <a href="purchases/suppliers.php">
                <i class="bi bi-building"></i> Fournisseurs
            </a>

            <div class="nav-section">Ventes</div>
            <a href="sales/sell.php">
                <i class="bi bi-cart-plus"></i> Nouvelle vente
            </a>
            <a href="sales/list.php">
                <i class="bi bi-clock-history"></i> Historique ventes
            </a>

            <!-- ✅ NOTIFICATIONS avec cloche animée -->
            <div class="nav-section">Alertes</div>
            <a href="notifications/index.php" class="<?= $bellCount > 0 ? 'sidebar-notif-link' : '' ?>">
                <i class="bi bi-bell<?= $bellCount > 0 ? '-fill' : '' ?>"></i>
                Notifications
                <?php if($bellCount > 0): ?>
                <span class="notif-badge"><?= $bellCount > 9 ? '9+' : $bellCount ?></span>
                <?php endif; ?>
            </a>

            <?php if(isAdmin()): ?>
            <div class="nav-section">Administration</div>
            <a href="admin/create_employee.php">
                <i class="bi bi-people"></i> Employés
                <span class="admin-only-badge">Admin</span>
            </a>
            <a href="admin/profile.php">
                <i class="bi bi-person-circle"></i> Mon profil
                <span class="admin-only-badge">Admin</span>
            </a>
            <?php endif; ?>
        </div>

        <div class="sidebar-footer">
            <a href="auth/logout.php">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <!-- TOPBAR -->
        <div class="topbar">
            <div class="topbar-left">
                <h5>Bonjour, <?= htmlspecialchars($_SESSION['user']) ?> 👋</h5>
                <p>Voici un aperçu de votre activité</p>
            </div>
            <div class="topbar-right">

                <?php if(isAdmin()): ?>
                <span class="topbar-role-badge admin">👑 Admin</span>
                <?php else: ?>
                <span class="topbar-role-badge employee">👤 Employé</span>
                <?php endif; ?>

                <div class="topbar-date">
                    <i class="bi bi-calendar3"></i> <?= date('d M Y') ?>
                </div>

                <!-- ✅ CLOCHE NOTIFICATIONS TOPBAR -->
                <a href="notifications/index.php" class="topbar-bell <?= $bellCount > 0 ? 'has-notif' : '' ?>"
                    title="<?= $bellCount > 0 ? "$bellCount notification(s) non lue(s)" : 'Notifications' ?>">
                    <i class="bi bi-bell<?= $bellCount > 0 ? '-fill' : '' ?>"></i>
                    <?php if($bellCount > 0): ?>
                    <span class="bell-badge"><?= $bellCount > 9 ? '9+' : $bellCount ?></span>
                    <?php endif; ?>
                </a>

                <!-- AVATAR -->
                <?php if(isAdmin()): ?>
                <a href="admin/profile.php" class="topbar-avatar" title="Mon profil">
                    <?php else: ?>
                    <div class="topbar-avatar">
                        <?php endif; ?>
                        <?php if(!empty($currentUser['photo'])): ?>
                        <img src="uploads/<?= htmlspecialchars($currentUser['photo']) ?>" alt="Photo">
                        <?php else: ?>
                        <div class="topbar-avatar-placeholder">
                            <?= strtoupper(substr($_SESSION['user'], 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <?php if(isAdmin()): ?>
                </a>
                <?php else: ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ✅ BANNIÈRE ALERTE STOCK (si notifications non lues) -->
    <?php if($bellCount > 0): ?>
    <?php
    $alertProds = $conn->query("
        SELECT p.name, p.quantity
        FROM products p
        WHERE p.quantity <= 5
        ORDER BY p.quantity ASC
        LIMIT 3
    ");
    $alertNames = [];
    while($ap = $alertProds->fetch_assoc()){
        $alertNames[] = ($ap['quantity'] == 0 ? '❌' : '⚠️') . ' ' . $ap['name'];
    }
    ?>
    <div class="stock-alert-banner">
        <div class="stock-alert-banner-left">
            <div class="stock-alert-banner-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div>
                <h6>🚨 Alerte Stock — <?= $bellCount ?> notification(s) non lue(s)</h6>
                <p><?= implode(' · ', $alertNames) ?><?= count($alertNames) < $bellCount ? ' · et plus...' : '' ?></p>
            </div>
        </div>
        <a href="notifications/index.php">
            <i class="bi bi-bell-fill"></i> Voir les alertes
        </a>
    </div>
    <?php endif; ?>

    <!-- BANNIÈRE ADMIN -->
    <?php if(isAdmin()): ?>
    <div class="admin-banner">
        <div>
            <h6><i class="bi bi-shield-fill"></i> Espace Administrateur</h6>
            <p>Vous avez un accès complet à toutes les fonctionnalités de l'application.</p>
        </div>
        <a href="admin/create_employee.php">
            <i class="bi bi-person-plus"></i> Gérer les employés
        </a>
    </div>
    <?php endif; ?>

    <!-- STATS VENTES -->
    <div class="section-title">
        <i class="bi bi-graph-up" style="color:#6366f1;"></i> Statistiques Ventes & Stock
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card-box bg-blue">
                <h3><i class="bi bi-box"></i> <?= $stats['total_products'] ?? 0 ?></h3>
                <p>Produits en stock</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-box bg-green">
                <h3><i class="bi bi-cash"></i> <?= number_format($stats['total_value'] ?? 0, 0, '', ' ') ?> FCFA</h3>
                <p>Valeur du stock</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-box bg-orange">
                <?php if(isAdmin()): ?>
                <h3><i class="bi bi-people"></i> <?= $userCount ?? 0 ?></h3>
                <p>Utilisateurs</p>
                <?php else: ?>
                <h3><i class="bi bi-graph-up"></i> <?= $salesStats['total_sales'] ?? 0 ?></h3>
                <p>Total ventes</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-box bg-pink">
                <h3><i class="bi bi-cart-check"></i> <?= $salesStats['total_sales'] ?? 0 ?></h3>
                <p>Ventes effectuées</p>
                <small><?= number_format($salesStats['total_revenue'] ?? 0, 0, '', ' ') ?> FCFA générés</small>
            </div>
        </div>
    </div>

    <!-- STATS ACHATS -->
    <div class="section-title">
        <i class="bi bi-bag-check" style="color:#3b82f6;"></i> Statistiques Achats & Fournisseurs
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card-box bg-indigo">
                <h3><i class="bi bi-bag-check"></i> <?= $achatsStats['total_achats'] ?? 0 ?></h3>
                <p>Produits achetés</p>
                <small>Commandes enregistrées</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-box bg-teal">
                <h3><i class="bi bi-building"></i> <?= $totalFournisseurs ?? 0 ?></h3>
                <p>Fournisseurs</p>
                <small>Partenaires actifs</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-box bg-violet">
                <h3 style="font-size:18px;">
                    <i class="bi bi-cash-stack"></i>
                    <?= number_format($achatsStats['total_depense'] ?? 0, 0, '', ' ') ?> FCFA
                </h3>
                <p>Total acheté</p>
                <small>Dépenses cumulées</small>
            </div>
        </div>
    </div>

    <!-- STAT NOTIFICATIONS si alertes -->
    <?php if($bellCount > 0): ?>
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card-box bg-red" style="cursor:pointer;" onclick="window.location='notifications/index.php'">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 style="margin:0;font-size:20px;">
                            <i class="bi bi-bell-fill"></i> <?= $bellCount ?>
                            alerte<?= $bellCount > 1 ? 's' : '' ?> stock non lue<?= $bellCount > 1 ? 's' : '' ?>
                        </h4>
                        <p style="margin:4px 0 0;opacity:0.85;font-size:13px;">
                            Cliquez pour consulter et gérer les alertes
                        </p>
                    </div>
                    <i class="bi bi-arrow-right-circle-fill" style="font-size:32px;opacity:0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TABLEAUX -->
    <div class="row mt-2">
        <!-- PRODUITS RÉCENTS -->
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5>📦 Produits récents</h5>
                    <a href="products/list.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <input type="text" id="searchProducts" class="form-control search-box my-2" placeholder="Rechercher...">
                <table class="table table-hover" id="productTable">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Quantité</th>
                            <th>Prix (FCFA)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $recentProducts->fetch_assoc()){ ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td>
                                <?= $row['quantity'] ?>
                                <?php if($row['quantity'] == 0): ?>
                                <span
                                    style="background:#fee2e2;color:#dc2626;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:700;">❌
                                    Rupture</span>
                                <?php elseif($row['quantity'] <= 5): ?>
                                <span class="badge-stock-low">⚠️ Faible</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($row['price'], 0, '', ' ') ?></td>
                            <td>
                                <a href="sales/sell.php?product_id=<?= $row['id'] ?>" class="btn btn-success btn-sm"
                                    title="Vendre">
                                    <i class="bi bi-cart"></i>
                                </a>
                                <a href="products/edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm"
                                    title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="products/delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Supprimer ce produit ?')" title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- VENTES RÉCENTES -->
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5>🛒 Ventes récentes</h5>
                    <a href="sales/list.php" class="btn btn-sm btn-outline-success">Voir tout</a>
                </div>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Qté</th>
                            <th>Total (FCFA)</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if($recentSales->num_rows === 0){
                            echo "<tr><td colspan='4' class='text-center text-muted py-3'>Aucune vente pour l'instant</td></tr>";
                        }
                        while($s = $recentSales->fetch_assoc()){ ?>
                        <tr>
                            <td><?= htmlspecialchars($s['product_name']) ?></td>
                            <td><?= $s['quantity_sold'] ?></td>
                            <td><?= number_format($s['quantity_sold'] * $s['sale_price'], 0, '', ' ') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($s['sold_at'])) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ACHATS RÉCENTS -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5>🛍️ Achats récents</h5>
                    <div class="d-flex gap-2">
                        <a href="purchases/add.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-bag-plus"></i> Nouvel achat
                        </a>
                        <a href="purchases/list.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                </div>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="background:#3b82f6;color:white;">Produit</th>
                            <th style="background:#3b82f6;color:white;">Fournisseur</th>
                            <th style="background:#3b82f6;color:white;">Téléphone</th>
                            <th style="background:#3b82f6;color:white;">Qté</th>
                            <th style="background:#3b82f6;color:white;">Prix unit.</th>
                            <th style="background:#3b82f6;color:white;">Total (FCFA)</th>
                            <th style="background:#3b82f6;color:white;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if($recentPurchases->num_rows === 0){
                            echo "<tr><td colspan='7' class='text-center text-muted py-3'>
                                    <i class='bi bi-bag-x'></i> Aucun achat enregistré
                                  </td></tr>";
                        }
                        while($p = $recentPurchases->fetch_assoc()): ?>
                        <tr class="purchase-row">
                            <td><strong><?= htmlspecialchars($p['product_name']) ?></strong></td>
                            <td>
                                <span class="supplier-badge">
                                    <i class="bi bi-building"></i>
                                    <?= htmlspecialchars($p['supplier_name']) ?>
                                </span>
                            </td>
                            <td style="color:#64748b;">
                                <?= $p['supplier_phone'] ? htmlspecialchars($p['supplier_phone']) : '—' ?>
                            </td>
                            <td><?= $p['quantity'] ?></td>
                            <td><?= number_format($p['unit_price'], 0, '', ' ') ?></td>
                            <td><strong
                                    style="color:#3b82f6;"><?= number_format($p['quantity'] * $p['unit_price'], 0, '', ' ') ?></strong>
                            </td>
                            <td style="color:#64748b;"><?= date('d/m/Y H:i', strtotime($p['purchased_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DIAGRAMMES -->
    <div class="row g-3 mt-3 mb-4">
        <div class="col-md-4">
            <div class="chart-card">
                <div class="chart-title">🥧 Top produits vendus</div>
                <?php if(empty($pieLabels)): ?>
                <p class="text-muted text-center py-4" style="font-size:13px;">
                    <i class="bi bi-info-circle"></i> Aucune vente enregistrée
                </p>
                <?php else: ?>
                <div class="chart-wrapper"><canvas id="pieChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-8">
            <div class="chart-card">
                <div class="chart-title">📊 Revenus des 7 derniers jours (FCFA)</div>
                <?php if(empty($barLabels)): ?>
                <p class="text-muted text-center py-4" style="font-size:13px;">
                    <i class="bi bi-info-circle"></i> Aucune vente ces 7 derniers jours
                </p>
                <?php else: ?>
                <div class="chart-wrapper"><canvas id="barChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    </div>

    <script>
    document.getElementById("searchProducts").addEventListener("keyup", function() {
        let value = this.value.toLowerCase();
        document.querySelectorAll("#productTable tbody tr").forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
        });
    });

    <?php if(!empty($pieLabels)): ?>
    new Chart(document.getElementById('pieChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pieLabels) ?>,
            datasets: [{
                data: <?= json_encode($pieValues) ?>,
                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#3b82f6'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            family: 'Poppins',
                            size: 11
                        },
                        boxWidth: 12,
                        padding: 8
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.label + ' : ' + ctx.parsed + ' vendus'
                    }
                }
            }
        }
    });
    <?php endif; ?>

    <?php if(!empty($barLabels)): ?>
    new Chart(document.getElementById('barChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($barLabels) ?>,
            datasets: [{
                label: 'Revenus',
                data: <?= json_encode($barValues) ?>,
                backgroundColor: 'rgba(99,102,241,0.75)',
                borderColor: '#4f46e5',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.parsed.y.toLocaleString('fr-FR') + ' FCFA'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f3f4f6'
                    },
                    ticks: {
                        callback: v => v.toLocaleString('fr-FR') + ' F',
                        font: {
                            family: 'Poppins',
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            family: 'Poppins',
                            size: 11
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(el) {
            el.style.transition = 'opacity 0.8s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 800);
        });
    }, 10000);
    </script>

</body>

</html>