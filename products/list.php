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

// Stats globales avec bénéfice
$statsRes = $conn->query("
    SELECT 
        COUNT(*)                                    AS total,
        SUM(quantity)                               AS total_qty,
        SUM(quantity * price)                       AS total_val_vente,
        SUM(quantity * purchase_price)              AS total_val_achat,
        SUM(quantity * (price - purchase_price))    AS total_benefice
    FROM products
");
$s = $statsRes->fetch_assoc();

// Produits
$res = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits — Stock App</title>
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

    /* STATS GRID */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 16px 18px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .stat-card-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-bottom: 10px;
    }

    .stat-card-icon.blue {
        background: #dbeafe;
        color: #3b82f6;
    }

    .stat-card-icon.green {
        background: #d1fae5;
        color: #10b981;
    }

    .stat-card-icon.purple {
        background: #ede9fe;
        color: #6366f1;
    }

    .stat-card-icon.orange {
        background: #fef3c7;
        color: #f59e0b;
    }

    .stat-card-icon.emerald {
        background: #d1fae5;
        color: #059669;
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
        font-weight: 500;
    }

    /* BÉNÉFICE TOTAL BANNER */
    .benefice-banner {
        border-radius: 16px;
        padding: 18px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .benefice-banner.positive {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border: 1.5px solid #bbf7d0;
    }

    .benefice-banner.negative {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border: 1.5px solid #fecaca;
    }

    .benefice-banner h5 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
    }

    .benefice-banner.positive h5 {
        color: #065f46;
    }

    .benefice-banner.negative h5 {
        color: #991b1b;
    }

    .benefice-banner p {
        margin: 3px 0 0;
        font-size: 12px;
    }

    .benefice-banner.positive p {
        color: #16a34a;
    }

    .benefice-banner.negative p {
        color: #dc2626;
    }

    .benefice-big {
        font-size: 28px;
        font-weight: 800;
    }

    .benefice-banner.positive .benefice-big {
        color: #10b981;
    }

    .benefice-banner.negative .benefice-big {
        color: #ef4444;
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

    .search-box {
        max-width: 280px;
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
        padding: 12px 14px;
        font-size: 13px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .table tr:hover td {
        background: #f8faff;
    }

    /* BADGES */
    .badge-stock-low {
        background: #fee2e2;
        color: #dc2626;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .badge-stock-ok {
        background: #d1fae5;
        color: #059669;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .badge-stock-zero {
        background: #fee2e2;
        color: #dc2626;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    /* PRIX ACHAT / VENTE / BÉNÉFICE */
    .price-col {
        font-weight: 600;
        color: #0f172a;
    }

    .purchase-col {
        color: #3b82f6;
        font-weight: 600;
    }

    .profit-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
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

    /* MARGE % */
    .margin-bar {
        height: 5px;
        background: #e2e8f0;
        border-radius: 4px;
        margin-top: 4px;
        overflow: hidden;
    }

    .margin-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s;
    }

    .btn {
        border-radius: 8px;
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
            <a href="list.php" class="active"><i class="bi bi-box-seam"></i> Produits</a>
            <a href="add.php"><i class="bi bi-plus-circle"></i> Ajouter produit</a>
            <div class="nav-section">Achats</div>
            <a href="../purchases/add.php"><i class="bi bi-bag-plus"></i> Nouvel achat</a>
            <a href="../purchases/list.php"><i class="bi bi-clock-history"></i> Historique achats</a>
            <a href="../purchases/suppliers.php"><i class="bi bi-building"></i> Fournisseurs</a>
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

        <!-- MESSAGES -->
        <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-danger alert-dismissible">🗑️ Produit supprimé avec succès.</div>
        <?php elseif($_GET['msg'] == 'sold'): ?>
        <div class="alert alert-success alert-dismissible">✅ Vente enregistrée avec succès !</div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h3>📦 Liste des produits</h3>
                <p style="font-size:13px;color:#64748b;margin:3px 0 0;">
                    Inventaire avec prix d'achat, vente et bénéfices
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="search" class="form-control search-box" placeholder="🔍 Rechercher...">
                <a href="add.php" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-plus-circle"></i> Ajouter
                </a>
            </div>
        </div>

        <!-- STATS GRID (5 cartes) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-grid-3x3-gap"></i></div>
                <div class="stat-card-value"><?= $s['total'] ?? 0 ?></div>
                <div class="stat-card-label">Types produits</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon purple"><i class="bi bi-stack"></i></div>
                <div class="stat-card-value"><?= number_format($s['total_qty'] ?? 0, 0, '', ' ') ?></div>
                <div class="stat-card-label">Unités en stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-bag"></i></div>
                <div class="stat-card-value" style="font-size:13px;">
                    <?= number_format($s['total_val_achat'] ?? 0, 0, '', ' ') ?> F</div>
                <div class="stat-card-label">Valeur d'achat</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-tag"></i></div>
                <div class="stat-card-value" style="font-size:13px;">
                    <?= number_format($s['total_val_vente'] ?? 0, 0, '', ' ') ?> F</div>
                <div class="stat-card-label">Valeur de vente</div>
            </div>
            <div class="stat-card">
                <?php $totalBenef = floatval($s['total_benefice'] ?? 0); ?>
                <div class="stat-card-icon <?= $totalBenef >= 0 ? 'emerald' : 'red' ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="stat-card-value"
                    style="font-size:13px;color:<?= $totalBenef >= 0 ? '#10b981' : '#ef4444' ?>;">
                    <?= ($totalBenef >= 0 ? '+' : '−') . number_format(abs($totalBenef), 0, '', ' ') ?> F
                </div>
                <div class="stat-card-label">Bénéfice potentiel</div>
            </div>
        </div>

        <!-- BANNIÈRE BÉNÉFICE TOTAL -->
        <?php
    $benefTotal = floatval($s['total_benefice'] ?? 0);
    $isPos = $benefTotal >= 0;
    ?>
        <div class="benefice-banner <?= $isPos ? 'positive' : 'negative' ?>">
            <div>
                <h5>
                    <?= $isPos ? '📈 Bénéfice potentiel total' : '📉 Perte potentielle totale' ?>
                </h5>
                <p>
                    Si vous vendez tout le stock aux prix actuels
                    (valeur vente − valeur achat)
                </p>
            </div>
            <div class="benefice-big">
                <?= ($isPos ? '+' : '−') . number_format(abs($benefTotal), 0, '', ' ') ?> FCFA
            </div>
        </div>

        <!-- TABLEAU -->
        <div class="table-card">
            <table class="table table-hover mb-0" id="productTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom du produit</th>
                        <th>Qté</th>
                        <th>Prix achat</th>
                        <th>Prix vente</th>
                        <th>Bénéfice/unité</th>
                        <th>Bénéfice total</th>
                        <th>Marge %</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
            $res = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
            while($row = $res->fetch_assoc()):
                $pp     = floatval($row['purchase_price'] ?? 0);
                $pv     = floatval($row['price']);
                $qty    = intval($row['quantity']);
                $profit = $pv - $pp;
                $profitTotal = $profit * $qty;
                $margin = $pp > 0 ? (($profit / $pp) * 100) : 0;
                $marginWidth = min(abs($margin), 100);
                $profitClass = $profit > 0 ? 'pos' : ($profit < 0 ? 'neg' : 'zero');
                $marginColor = $profit > 0 ? '#10b981' : ($profit < 0 ? '#ef4444' : '#94a3b8');
            ?>
                    <tr>
                        <td style="color:#94a3b8;font-size:12px;"><?= $row['id'] ?></td>
                        <td>
                            <strong style="color:#0f172a;"><?= htmlspecialchars($row['name']) ?></strong>
                        </td>
                        <td>
                            <span style="font-weight:700;color:#0f172a;"><?= $qty ?></span>
                        </td>
                        <td class="purchase-col">
                            <?= $pp > 0 ? number_format($pp, 0, '', ' ') . ' F' : '<span style="color:#94a3b8;">—</span>' ?>
                        </td>
                        <td class="price-col">
                            <?= number_format($pv, 0, '', ' ') ?> F
                        </td>
                        <td>
                            <span class="profit-badge <?= $profitClass ?>">
                                <?php if($pp > 0): ?>
                                <?= ($profit >= 0 ? '+' : '−') . number_format(abs($profit), 0, '', ' ') ?> F
                                <?php else: ?>
                                <span style="color:#94a3b8;">—</span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <?php if($pp > 0 && $qty > 0): ?>
                            <span style="font-weight:700;color:<?= $profit >= 0 ? '#10b981' : '#ef4444' ?>;">
                                <?= ($profit >= 0 ? '+' : '−') . number_format(abs($profitTotal), 0, '', ' ') ?> F
                            </span>
                            <?php else: ?>
                            <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="min-width:90px;">
                            <?php if($pp > 0): ?>
                            <div style="font-size:12px;font-weight:700;color:<?= $marginColor ?>;">
                                <?= number_format($margin, 1) ?> %
                            </div>
                            <div class="margin-bar">
                                <div class="margin-fill"
                                    style="width:<?= $marginWidth ?>%;background:<?= $marginColor ?>;"></div>
                            </div>
                            <?php else: ?>
                            <span style="color:#94a3b8;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($qty == 0): ?>
                            <span class="badge-stock-zero">❌ Rupture</span>
                            <?php elseif($qty <= 5): ?>
                            <span class="badge-stock-low">⚠️ Faible</span>
                            <?php else: ?>
                            <span class="badge-stock-ok">✅ En stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($qty > 0): ?>
                            <a href="../sales/sell.php?product_id=<?= $row['id'] ?>" class="btn btn-success btn-sm"
                                title="Vendre">
                                <i class="bi bi-cart"></i>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled title="Rupture">
                                <i class="bi bi-cart-x"></i>
                            </button>
                            <?php endif; ?>
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm" title="Modifier">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                                onclick="return confirm('Supprimer ce produit ?')" title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
    document.getElementById("search").addEventListener("keyup", function() {
        const val = this.value.toLowerCase();
        document.querySelectorAll("#productTable tbody tr").forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? "" : "none";
        });
    });

    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(el => {
            el.style.transition = 'opacity 0.8s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 800);
        });
    }, 8000);
    </script>
</body>

</html>