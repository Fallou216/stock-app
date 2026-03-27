<?php
session_start();
if(!isset($_SESSION['user'])){ header("Location: ../auth/login.php"); exit(); }
include('../config/db.php');
requireLogin();

$currentUser = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();
$suppliers   = $conn->query("
    SELECT s.*, COUNT(p.id) AS total_achats, SUM(p.quantity * p.unit_price) AS total_depense
    FROM suppliers s
    LEFT JOIN purchases p ON p.supplier_id = s.id
    GROUP BY s.id ORDER BY s.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Fournisseurs — Stock App</title>
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

    .supplier-card {
        background: white;
        border-radius: 16px;
        padding: 20px 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        transition: transform 0.2s;
    }

    .supplier-card:hover {
        transform: translateY(-2px);
    }

    .supplier-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        font-weight: 800;
        flex-shrink: 0;
    }

    .supplier-name {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .supplier-phone {
        font-size: 13px;
        color: #64748b;
        margin-top: 3px;
    }

    .supplier-stats {
        display: flex;
        gap: 20px;
    }

    .stat-pill {
        text-align: center;
    }

    .stat-pill-value {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
    }

    .stat-pill-label {
        font-size: 11px;
        color: #94a3b8;
    }

    .stat-pill-value.blue {
        color: #3b82f6;
    }

    .stat-pill-value.green {
        color: #10b981;
    }

    .supplier-date {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 4px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 48px;
        opacity: 0.3;
        margin-bottom: 12px;
    }
    </style>
</head>

<body>

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

    <div class="content">
        <div class="page-header">
            <div>
                <h3>🏢 Fournisseurs</h3>
                <p style="font-size:13px;color:#64748b;margin:3px 0 0;">Créés automatiquement lors des achats</p>
            </div>
            <a href="add.php" class="btn btn-primary btn-sm">
                <i class="bi bi-bag-plus"></i> Nouvel achat
            </a>
        </div>

        <?php if($suppliers->num_rows === 0): ?>
        <div class="empty-state">
            <i class="bi bi-building d-block"></i>
            <p>Aucun fournisseur enregistré</p>
            <a href="add.php" class="btn btn-primary btn-sm mt-2">Enregistrer un premier achat</a>
        </div>
        <?php else: ?>
        <?php while($s = $suppliers->fetch_assoc()): ?>
        <div class="supplier-card">
            <div style="display:flex;align-items:center;gap:16px;">
                <div class="supplier-avatar"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                <div>
                    <div class="supplier-name"><?= htmlspecialchars($s['name']) ?></div>
                    <div class="supplier-phone">
                        <?php if($s['phone']): ?>
                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($s['phone']) ?>
                        <?php else: ?>
                        <span style="color:#cbd5e1;">Pas de numéro</span>
                        <?php endif; ?>
                    </div>
                    <div class="supplier-date">
                        <i class="bi bi-calendar3"></i> Depuis le <?= date('d/m/Y', strtotime($s['created_at'])) ?>
                    </div>
                </div>
            </div>
            <div class="supplier-stats">
                <div class="stat-pill">
                    <div class="stat-pill-value blue"><?= $s['total_achats'] ?></div>
                    <div class="stat-pill-label">Achats</div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-value green" style="font-size:14px;">
                        <?= number_format($s['total_depense'] ?? 0, 0, '', ' ') ?> F
                    </div>
                    <div class="stat-pill-label">Dépensé</div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>
</body>

</html>