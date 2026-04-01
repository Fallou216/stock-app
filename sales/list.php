<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');
requireLogin();

$currentUser = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

// Compteur notifications
$bellCount = 0;
$bellRes   = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='stock_alert' AND is_read=0");
if($bellRes) $bellCount = $bellRes->fetch_assoc()['c'];

// ══════════════════════════════════════════
// FILTRE PÉRIODE
// ══════════════════════════════════════════
$filtre   = $_GET['filtre']   ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

// Construire la condition WHERE selon le filtre
switch($filtre){
    case 'today':
        $whereDate = "AND DATE(s.sold_at) = CURDATE()";
        $filtreLabel = "Aujourd'hui";
        break;
    case 'week':
        $whereDate = "AND YEARWEEK(s.sold_at, 1) = YEARWEEK(NOW(), 1)";
        $filtreLabel = "Cette semaine";
        break;
    case 'month':
        $whereDate = "AND MONTH(s.sold_at) = MONTH(NOW()) AND YEAR(s.sold_at) = YEAR(NOW())";
        $filtreLabel = "Ce mois";
        break;
    case 'year':
        $whereDate = "AND YEAR(s.sold_at) = YEAR(NOW())";
        $filtreLabel = "Cette année";
        break;
    case 'custom':
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);
        if($from && $to){
            $whereDate = "AND DATE(s.sold_at) BETWEEN '$from' AND '$to'";
            $filtreLabel = "Du $from au $to";
        } elseif($from){
            $whereDate = "AND DATE(s.sold_at) >= '$from'";
            $filtreLabel = "À partir du $from";
        } else {
            $whereDate = "";
            $filtreLabel = "Toutes les ventes";
        }
        break;
    default:
        $whereDate = "";
        $filtreLabel = "Toutes les ventes";
        break;
}

// ══════════════════════════════════════════
// EXPORT EXCEL — déclenché avant tout HTML
// ══════════════════════════════════════════
if(isset($_GET['export']) && $_GET['export'] === 'excel' && isAdmin()){

    $exportQuery = $conn->query("
        SELECT s.id, p.name AS product_name,
               p.purchase_price,
               s.quantity_sold, s.sale_price,
               (s.quantity_sold * s.sale_price) AS total_vente,
               (s.quantity_sold * p.purchase_price) AS total_achat,
               (s.quantity_sold * (s.sale_price - p.purchase_price)) AS benefice,
               s.sold_at
        FROM sales s
        JOIN products p ON s.product_id = p.id
        WHERE 1=1 $whereDate
        ORDER BY s.sold_at DESC
    ");

    // ── En-têtes HTTP pour téléchargement Excel ──
    $filename = 'ventes_' . $filtre . '_' . date('Y-m-d_His') . '.xls';
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // ── BOM UTF-8 pour Excel ──
    echo "\xEF\xBB\xBF";

    // ── Titre du rapport ──
    echo "RAPPORT DES VENTES — " . strtoupper($filtreLabel) . "\t\t\t\t\t\t\t\n";
    echo "Généré le : " . date('d/m/Y H:i') . "\t\t\t\t\t\t\t\n";
    echo "\t\t\t\t\t\t\t\n";

    // ── En-têtes colonnes ──
    $headers = [
        '#',
        'Produit',
        'Qté vendue',
        'Prix achat (FCFA)',
        'Prix vente (FCFA)',
        'Total vente (FCFA)',
        'Total achat (FCFA)',
        'Bénéfice (FCFA)',
        'Date & Heure',
    ];
    echo implode("\t", $headers) . "\n";

    // ── Lignes de données ──
    $grandTotalVente  = 0;
    $grandTotalAchat  = 0;
    $grandTotalBenef  = 0;
    $rows = [];

    while($r = $exportQuery->fetch_assoc()){
        $grandTotalVente += $r['total_vente'];
        $grandTotalAchat += $r['total_achat'];
        $grandTotalBenef += $r['benefice'];
        echo implode("\t", [
            $r['id'],
            $r['product_name'],
            $r['quantity_sold'],
            number_format($r['purchase_price'], 0, '.', ''),
            number_format($r['sale_price'], 0, '.', ''),
            number_format($r['total_vente'], 0, '.', ''),
            number_format($r['total_achat'], 0, '.', ''),
            number_format($r['benefice'], 0, '.', ''),
            date('d/m/Y H:i', strtotime($r['sold_at'])),
        ]) . "\n";
    }

    // ── Ligne vide séparatrice ──
    echo "\t\t\t\t\t\t\t\t\n";

    // ── Totaux ──
    echo implode("\t", [
        '',
        'TOTAL GÉNÉRAL',
        '',
        '',
        '',
        number_format($grandTotalVente, 0, '.', ''),
        number_format($grandTotalAchat, 0, '.', ''),
        number_format($grandTotalBenef, 0, '.', ''),
        '',
    ]) . "\n";

    exit();
}

// ══════════════════════════════════════════
// DONNÉES PAGE
// ══════════════════════════════════════════
$salesData = $conn->query("
    SELECT s.*,
           p.name           AS product_name,
           p.purchase_price AS purchase_price,
           (s.quantity_sold * s.sale_price)                          AS total_vente,
           (s.quantity_sold * p.purchase_price)                      AS total_achat,
           (s.quantity_sold * (s.sale_price - p.purchase_price))     AS benefice
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE 1=1 $whereDate
    ORDER BY s.sold_at DESC
");

// Stats filtrées
$statsQ = $conn->query("
    SELECT
        COUNT(*)                                                          AS nb_ventes,
        SUM(s.quantity_sold * s.sale_price)                              AS total_vente,
        SUM(s.quantity_sold * p.purchase_price)                          AS total_achat,
        SUM(s.quantity_sold * (s.sale_price - p.purchase_price))         AS total_benefice,
        SUM(s.quantity_sold)                                             AS total_qte
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE 1=1 $whereDate
");
$stats = $statsQ->fetch_assoc();

// Stats "toutes périodes" pour référence
$statsAllQ = $conn->query("
    SELECT COUNT(*) AS nb FROM sales
");
$totalAllVentes = $statsAllQ->fetch_assoc()['nb'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des ventes — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f4f6f9;
    }

    /* SIDEBAR */
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
        border: 2px solid rgba(255, 255, 255, 0.2);
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

    /* PAGE HEADER */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
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

    /* FILTRES */
    .filter-bar {
        background: white;
        border-radius: 16px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .filter-label {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
        white-space: nowrap;
    }

    .filter-btn {
        padding: 7px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        border: 1.5px solid #e2e8f0;
        background: white;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .filter-btn:hover {
        border-color: #6366f1;
        color: #6366f1;
        background: #f5f3ff;
    }

    .filter-btn.active {
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        border-color: #6366f1;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .filter-divider {
        width: 1px;
        height: 30px;
        background: #e2e8f0;
    }

    .date-range {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .date-range input {
        padding: 7px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        font-family: 'Poppins', sans-serif;
        color: #0f172a;
        outline: none;
        transition: all 0.2s;
    }

    .date-range input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .date-range button {
        padding: 7px 14px;
        background: #6366f1;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .date-range button:hover {
        background: #4f46e5;
    }

    /* STATS GRID */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .stat-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        margin-bottom: 10px;
    }

    .stat-card-icon.purple {
        background: #ede9fe;
        color: #6366f1;
    }

    .stat-card-icon.green {
        background: #d1fae5;
        color: #10b981;
    }

    .stat-card-icon.blue {
        background: #dbeafe;
        color: #3b82f6;
    }

    .stat-card-icon.orange {
        background: #fef3c7;
        color: #f59e0b;
    }

    .stat-card-icon.emerald {
        background: #d1fae5;
        color: #059669;
    }

    .stat-card-icon.red {
        background: #fee2e2;
        color: #ef4444;
    }

    .stat-card-value {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-card-label {
        font-size: 11px;
        color: #64748b;
    }

    /* BENEFICE BANNER */
    .benefice-banner {
        border-radius: 14px;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .benefice-banner.pos {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border: 1.5px solid #bbf7d0;
    }

    .benefice-banner.neg {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border: 1.5px solid #fecaca;
    }

    .benefice-banner h5 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
    }

    .benefice-banner.pos h5 {
        color: #065f46;
    }

    .benefice-banner.neg h5 {
        color: #991b1b;
    }

    .benefice-banner p {
        margin: 3px 0 0;
        font-size: 12px;
    }

    .benefice-banner.pos p {
        color: #16a34a;
    }

    .benefice-banner.neg p {
        color: #dc2626;
    }

    .benefice-big {
        font-size: 26px;
        font-weight: 800;
    }

    .benefice-banner.pos .benefice-big {
        color: #10b981;
    }

    .benefice-banner.neg .benefice-big {
        color: #ef4444;
    }

    /* PERIODE TAG */
    .periode-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #ede9fe;
        color: #6366f1;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        margin-left: 10px;
    }

    /* TABLE */
    .table-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .table th {
        background: #4f46e5;
        color: white;
        font-size: 12px;
        font-weight: 700;
        padding: 12px 14px;
    }

    .table th:first-child {
        border-radius: 8px 0 0 8px;
    }

    .table th:last-child {
        border-radius: 0 8px 8px 0;
    }

    .table td {
        padding: 11px 14px;
        font-size: 13px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .table tr:hover td {
        background: #f8faff;
    }

    .total-row td {
        font-weight: 700;
        background: #f0f4ff !important;
        color: #4f46e5;
    }

    /* PROFIT BADGE */
    .profit-badge {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .profit-badge.pos {
        background: #d1fae5;
        color: #059669;
    }

    .profit-badge.neg {
        background: #fee2e2;
        color: #dc2626;
    }

    .profit-badge.zero {
        background: #f1f5f9;
        color: #64748b;
    }

    /* EXPORT BTN */
    .btn-export {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border: none;
        border-radius: 10px;
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(16, 185, 129, 0.4);
        color: white;
    }

    .btn-new-sale {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        border: none;
        border-radius: 10px;
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .btn-new-sale:hover {
        transform: translateY(-2px);
        color: white;
    }

    .search-box {
        max-width: 280px;
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
            <a href="sell.php"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
            <a href="list.php" class="active"><i class="bi bi-clock-history"></i> Historique ventes</a>
            <div class="nav-section">Alertes</div>
            <a href="../notifications/index.php">
                <i class="bi bi-bell<?= $bellCount > 0 ? '-fill' : '' ?>"></i> Notifications
                <?php if($bellCount > 0): ?>
                <span class="notif-badge"><?= $bellCount > 9 ? '9+' : $bellCount ?></span>
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

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h3>
                    <i class="bi bi-clock-history"></i> Historique des ventes
                    <span class="periode-tag">
                        <i class="bi bi-funnel-fill"></i> <?= htmlspecialchars($filtreLabel) ?>
                    </span>
                </h3>
                <p><?= number_format($stats['nb_ventes'] ?? 0, 0, '', ' ') ?> vente(s) sur <?= $totalAllVentes ?> au
                    total</p>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <?php if(isAdmin()): ?>
                <!-- Export Excel avec le filtre actif -->
                <a href="list.php?filtre=<?= urlencode($filtre) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&export=excel"
                    class="btn-export">
                    <i class="bi bi-file-earmark-excel-fill"></i> Excel
                    <?php if($filtre !== 'all'): ?>
                    <span style="background:rgba(255,255,255,0.25);padding:1px 6px;border-radius:10px;font-size:11px;">
                        <?= htmlspecialchars($filtreLabel) ?>
                    </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <a href="sell.php" class="btn-new-sale">
                    <i class="bi bi-cart-plus"></i> Nouvelle vente
                </a>
            </div>
        </div>

        <!-- BARRE DE FILTRES -->
        <div class="filter-bar">
            <span class="filter-label"><i class="bi bi-calendar3"></i> Période :</span>

            <a href="list.php?filtre=all" class="filter-btn <?= $filtre==='all'    ? 'active' : '' ?>">
                <i class="bi bi-infinity"></i> Tout
            </a>
            <a href="list.php?filtre=today" class="filter-btn <?= $filtre==='today'  ? 'active' : '' ?>">
                <i class="bi bi-sun"></i> Aujourd'hui
            </a>
            <a href="list.php?filtre=week" class="filter-btn <?= $filtre==='week'   ? 'active' : '' ?>">
                <i class="bi bi-calendar-week"></i> Semaine
            </a>
            <a href="list.php?filtre=month" class="filter-btn <?= $filtre==='month'  ? 'active' : '' ?>">
                <i class="bi bi-calendar-month"></i> Mois
            </a>
            <a href="list.php?filtre=year" class="filter-btn <?= $filtre==='year'   ? 'active' : '' ?>">
                <i class="bi bi-calendar-range"></i> Année
            </a>

            <div class="filter-divider"></div>

            <!-- FILTRE PERSONNALISÉ -->
            <form method="GET" action="list.php" class="date-range">
                <input type="hidden" name="filtre" value="custom">
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" placeholder="Du">
                <span style="font-size:12px;color:#94a3b8;font-weight:600;">→</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" placeholder="Au">
                <button type="submit">
                    <i class="bi bi-search"></i> Filtrer
                </button>
            </form>
        </div>

        <!-- STATS 5 CARTES -->
        <?php
    $nbVentes    = intval($stats['nb_ventes']    ?? 0);
    $totalVente  = floatval($stats['total_vente']  ?? 0);
    $totalAchat  = floatval($stats['total_achat']  ?? 0);
    $totalBenef  = floatval($stats['total_benefice'] ?? 0);
    $totalQte    = intval($stats['total_qte']    ?? 0);
    $isPos       = $totalBenef >= 0;
    ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon purple"><i class="bi bi-cart-check"></i></div>
                <div class="stat-card-value"><?= $nbVentes ?></div>
                <div class="stat-card-label">Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-stack"></i></div>
                <div class="stat-card-value"><?= $totalQte ?></div>
                <div class="stat-card-label">Unités vendues</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-bag"></i></div>
                <div class="stat-card-value" style="font-size:13px;"><?= number_format($totalAchat, 0, '', ' ') ?> F
                </div>
                <div class="stat-card-label">Coût d'achat</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon green"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-card-value" style="font-size:13px;"><?= number_format($totalVente, 0, '', ' ') ?> F
                </div>
                <div class="stat-card-label">Chiffre d'affaires</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon <?= $isPos ? 'emerald' : 'red' ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="stat-card-value" style="font-size:13px;color:<?= $isPos ? '#10b981' : '#ef4444' ?>;">
                    <?= ($isPos ? '+' : '−') . number_format(abs($totalBenef), 0, '', ' ') ?> F
                </div>
                <div class="stat-card-label">Bénéfice net</div>
            </div>
        </div>

        <!-- BANNIÈRE BÉNÉFICE -->
        <div class="benefice-banner <?= $isPos ? 'pos' : 'neg' ?>">
            <div>
                <h5>
                    <?= $isPos ? '📈 Bénéfice net' : '📉 Perte nette' ?> — <?= htmlspecialchars($filtreLabel) ?>
                </h5>
                <p>
                    CA : <?= number_format($totalVente, 0, '', ' ') ?> FCFA —
                    Coût : <?= number_format($totalAchat, 0, '', ' ') ?> FCFA —
                    <?= $nbVentes ?> transaction(s)
                </p>
            </div>
            <div class="benefice-big">
                <?= ($isPos ? '+' : '−') . number_format(abs($totalBenef), 0, '', ' ') ?> FCFA
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <input type="text" id="search" class="form-control search-box"
                    placeholder="🔍 Rechercher produit, date...">
                <span class="text-muted ms-3" id="countResult" style="font-size:13px;white-space:nowrap;"></span>
            </div>

            <table class="table table-hover mb-0" id="salesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produit</th>
                        <th>Qté vendue</th>
                        <th>Prix achat</th>
                        <th>Prix vente</th>
                        <th>Total vente</th>
                        <th>Bénéfice</th>
                        <th>Date & Heure</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
            $grandTotalVente = 0;
            $grandTotalBenef = 0;
            $rowCount        = 0;

            if($salesData->num_rows === 0){
                echo "<tr><td colspan='8' class='text-center text-muted py-4'>
                        <i class='bi bi-inbox' style='font-size:32px;opacity:0.3;'></i><br>
                        Aucune vente pour cette période
                      </td></tr>";
            }

            while($row = $salesData->fetch_assoc()):
                $tv = floatval($row['total_vente']);
                $tb = floatval($row['benefice']);
                $pp = floatval($row['purchase_price']);
                $grandTotalVente += $tv;
                $grandTotalBenef += $tb;
                $rowCount++;
                $profitClass = $tb > 0 ? 'pos' : ($tb < 0 ? 'neg' : 'zero');
            ?>
                    <tr>
                        <td style="color:#94a3b8;font-size:12px;"><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['product_name']) ?></strong></td>
                        <td><span style="font-weight:700;"><?= $row['quantity_sold'] ?></span></td>
                        <td style="color:#3b82f6;font-weight:600;">
                            <?= $pp > 0 ? number_format($pp, 0, '', ' ') . ' F' : '<span style="color:#94a3b8;">—</span>' ?>
                        </td>
                        <td style="font-weight:600;"><?= number_format($row['sale_price'], 0, '', ' ') ?> F</td>
                        <td><strong><?= number_format($tv, 0, '', ' ') ?> F</strong></td>
                        <td>
                            <?php if($pp > 0): ?>
                            <span class="profit-badge <?= $profitClass ?>">
                                <?= ($tb >= 0 ? '+' : '−') . number_format(abs($tb), 0, '', ' ') ?> F
                            </span>
                            <?php else: ?>
                            <span style="color:#94a3b8;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#64748b;"><?= date('d/m/Y H:i', strtotime($row['sold_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>

                <!-- LIGNE TOTAUX -->
                <?php if($rowCount > 0 && isAdmin()): ?>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" style="text-align:right;">
                            <i class="bi bi-calculator"></i>
                            Total — <?= htmlspecialchars($filtreLabel) ?> :
                        </td>
                        <td>
                            <strong><?= number_format($grandTotalVente, 0, '', ' ') ?> FCFA</strong>
                        </td>
                        <td>
                            <span style="font-weight:800;color:<?= $grandTotalBenef >= 0 ? '#10b981' : '#ef4444' ?>;">
                                <?= ($grandTotalBenef >= 0 ? '+' : '−') . number_format(abs($grandTotalBenef), 0, '', ' ') ?>
                                FCFA
                            </span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

    </div>

    <script>
    const searchInput = document.getElementById('search');
    const countResult = document.getElementById('countResult');
    const allRows = document.querySelectorAll('#salesTable tbody tr');

    countResult.textContent = allRows.length + ' vente(s)';

    searchInput.addEventListener('keyup', function() {
        const val = this.value.toLowerCase();
        let visible = 0;
        allRows.forEach(row => {
            const match = row.innerText.toLowerCase().includes(val);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        countResult.textContent = visible + ' vente(s) trouvée(s)';
    });
    </script>
</body>

</html>