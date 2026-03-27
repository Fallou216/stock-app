<?php
session_start();
if(!isset($_SESSION['user'])){ header("Location: ../auth/login.php"); exit(); }
include('../config/db.php');
requireLogin();

$currentUser = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

$purchases = $conn->query("
    SELECT p.*, pr.name AS product_name, s.name AS supplier_name, s.phone AS supplier_phone
    FROM purchases p
    JOIN products  pr ON p.product_id  = pr.id
    JOIN suppliers s  ON p.supplier_id = s.id
    ORDER BY p.purchased_at DESC
");

$totalAchats       = $conn->query("SELECT COUNT(*) AS c FROM purchases")->fetch_assoc()['c'];
$totalDepense      = $conn->query("SELECT SUM(quantity * unit_price) AS t FROM purchases")->fetch_assoc()['t'] ?? 0;
$totalFournisseurs = $conn->query("SELECT COUNT(*) AS c FROM suppliers")->fetch_assoc()['c'];
$achatsAujourdhui  = $conn->query("SELECT COUNT(*) AS c FROM purchases WHERE DATE(purchased_at) = CURDATE()")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Historique Achats — Stock App</title>
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
        transition: all 0.2s;
    }

    .sidebar-footer a:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #fca5a5;
    }

    .content {
        margin-left: 240px;
        padding: 24px;
    }

    .mini-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }

    .mini-stat {
        background: white;
        border-radius: 14px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s;
    }

    .mini-stat:hover {
        transform: translateY(-3px);
    }

    .mini-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        flex-shrink: 0;
    }

    .mini-stat-icon.blue {
        background: #dbeafe;
        color: #3b82f6;
    }

    .mini-stat-icon.green {
        background: #d1fae5;
        color: #10b981;
    }

    .mini-stat-icon.orange {
        background: #fef3c7;
        color: #f59e0b;
    }

    .mini-stat-icon.purple {
        background: #ede9fe;
        color: #6366f1;
    }

    .mini-stat-value {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1;
    }

    .mini-stat-label {
        font-size: 12px;
        color: #64748b;
        margin-top: 3px;
    }

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

    .btn-new {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        color: white;
        border: none;
        border-radius: 10px;
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-new:hover {
        transform: translateY(-2px);
        color: white;
    }

    .table th {
        background: #3b82f6;
        color: white;
    }

    .table tr:hover {
        background: #f8faff;
    }

    .search-box {
        max-width: 300px;
    }

    .supplier-badge {
        background: #dbeafe;
        color: #2563eb;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .total-row td {
        font-weight: 700;
        background: #eff6ff;
        color: #2563eb;
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
            <a href="add.php"><i class="bi bi-bag-plus"></i> Nouvel achat</a>
            <a href="list.php" class="active"><i class="bi bi-clock-history"></i> Historique achats</a>
            <a href="suppliers.php"><i class="bi bi-building"></i> Fournisseurs</a>
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
        <!-- MINI STATS -->
        <div class="mini-stats">
            <div class="mini-stat">
                <div class="mini-stat-icon blue"><i class="bi bi-bag-check"></i></div>
                <div>
                    <div class="mini-stat-value"><?= $totalAchats ?></div>
                    <div class="mini-stat-label">Total achats</div>
                </div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-icon orange"><i class="bi bi-building"></i></div>
                <div>
                    <div class="mini-stat-value"><?= $totalFournisseurs ?></div>
                    <div class="mini-stat-label">Fournisseurs</div>
                </div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-icon green"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="mini-stat-value" style="font-size:14px;"><?= number_format($totalDepense,0,'',' ') ?> F
                    </div>
                    <div class="mini-stat-label">Total dépensé</div>
                </div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-icon purple"><i class="bi bi-calendar-check"></i></div>
                <div>
                    <div class="mini-stat-value"><?= $achatsAujourdhui ?></div>
                    <div class="mini-stat-label">Achats aujourd'hui</div>
                </div>
            </div>
        </div>

        <div class="page-header">
            <div>
                <h3><i class="bi bi-bag-check"></i> Historique des achats</h3>
                <p>Tous les achats enregistrés avec fournisseurs</p>
            </div>
            <a href="add.php" class="btn-new"><i class="bi bi-bag-plus"></i> Nouvel achat</a>
        </div>

        <div class="card p-3 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <input type="text" id="search" class="form-control search-box" placeholder="🔍 Rechercher...">
                <span class="text-muted ms-3" id="countResult" style="font-size:13px;white-space:nowrap;"></span>
            </div>
            <table class="table table-hover" id="purchaseTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produit</th>
                        <th>Fournisseur</th>
                        <th>Téléphone</th>
                        <th>Quantité</th>
                        <th>Prix unitaire</th>
                        <th>Total (FCFA)</th>
                        <th>Date & Heure</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                $grandTotal = 0;
                if($purchases->num_rows === 0){
                    echo "<tr><td colspan='8' class='text-center text-muted py-4'>
                            <i class='bi bi-inbox' style='font-size:32px;opacity:0.3;'></i><br>
                            Aucun achat enregistré
                          </td></tr>";
                }
                while($p = $purchases->fetch_assoc()):
                    $lineTotal   = $p['quantity'] * $p['unit_price'];
                    $grandTotal += $lineTotal;
                ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['product_name']) ?></strong></td>
                        <td>
                            <span class="supplier-badge">
                                <i class="bi bi-building"></i> <?= htmlspecialchars($p['supplier_name']) ?>
                            </span>
                        </td>
                        <td><?= $p['supplier_phone'] ? htmlspecialchars($p['supplier_phone']) : '<span style="color:#94a3b8;">—</span>' ?>
                        </td>
                        <td><?= $p['quantity'] ?></td>
                        <td><?= number_format($p['unit_price'],0,'',' ') ?></td>
                        <td><strong><?= number_format($lineTotal,0,'',' ') ?></strong></td>
                        <td style="color:#64748b;"><?= date('d/m/Y H:i', strtotime($p['purchased_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <?php if($grandTotal > 0): ?>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="6" style="text-align:right;">
                            <i class="bi bi-calculator"></i> Total général :
                        </td>
                        <td colspan="2"><strong><?= number_format($grandTotal,0,'',' ') ?> FCFA</strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <script>
    const searchInput = document.getElementById('search');
    const countResult = document.getElementById('countResult');
    const allRows = document.querySelectorAll('#purchaseTable tbody tr');
    countResult.textContent = allRows.length + ' achat(s)';
    searchInput.addEventListener('keyup', function() {
        const val = this.value.toLowerCase();
        let visible = 0;
        allRows.forEach(row => {
            const match = row.innerText.toLowerCase().includes(val);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        countResult.textContent = visible + ' achat(s) trouvé(s)';
    });
    </script>
</body>

</html>