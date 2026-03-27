<?php 
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');

// Récupérer infos complètes de l'utilisateur connecté
$currentUser = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();

$res = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Produits</title>
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
        padding: 20px;
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
        font-weight: 600;
    }

    .badge-stock-ok {
        background: #d1fae5;
        color: #059669;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="text-center px-3 pt-2 pb-1">
            <h4>📦 Stock</h4>
        </div>

        <!-- PROFIL -->
        <div class="sidebar-profile">
            <?php if(!empty($currentUser['photo'])): ?>
            <img src="../uploads/<?= htmlspecialchars($currentUser['photo']) ?>" alt="Photo">
            <?php else: ?>
            <div class="sidebar-avatar-placeholder">
                <?= strtoupper(substr($_SESSION['user'], 0, 1)) ?>
            </div>
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
            <!-- ✅ Visible pour tous -->
            <a href="add.php">
                <i class="bi bi-plus-circle"></i> Ajouter produit
            </a>

            <div class="nav-section">Ventes</div>
            <a href="../sales/sell.php"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
            <a href="../sales/list.php"><i class="bi bi-clock-history"></i> Historique</a>

            <!-- Administration : admin seulement -->
            <?php if(isAdmin()): ?>
            <div class="nav-section">Administration</div>
            <a href="../admin/create_employee.php">
                <i class="bi bi-people"></i> Employés
                <span class="admin-only-badge">Admin</span>
            </a>
            <a href="../admin/profile.php">
                <i class="bi bi-person-circle"></i> Mon profil
                <span class="admin-only-badge">Admin</span>
            </a>
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
        <div class="alert alert-danger">🗑️ Produit supprimé avec succès.</div>
        <?php elseif($_GET['msg'] == 'sold'): ?>
        <div class="alert alert-success">✅ Vente enregistrée avec succès !</div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>📦 Liste des produits</h3>
            <div class="d-flex gap-2">
                <input type="text" id="search" class="form-control search-box" placeholder="Rechercher...">
                <!-- ✅ Bouton Ajouter visible pour tous -->
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Ajouter
                </a>
            </div>
        </div>

        <!-- STATS RAPIDES -->
        <?php
    $statsRes = $conn->query("SELECT COUNT(*) AS total, SUM(quantity) AS total_qty, SUM(quantity * price) AS total_val FROM products");
    $s = $statsRes->fetch_assoc();
    ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card p-3 text-center border-0 shadow-sm">
                    <h4 class="text-primary"><?= $s['total'] ?? 0 ?></h4>
                    <small class="text-muted">Types de produits</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 text-center border-0 shadow-sm">
                    <h4 class="text-success"><?= $s['total_qty'] ?? 0 ?></h4>
                    <small class="text-muted">Quantité totale en stock</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 text-center border-0 shadow-sm">
                    <h4 class="text-warning"><?= number_format($s['total_val'] ?? 0, 0, '', ' ') ?> FCFA</h4>
                    <small class="text-muted">Valeur totale du stock</small>
                </div>
            </div>
        </div>

        <!-- TABLEAU -->
        <div class="card p-3 shadow-sm">
            <table class="table table-hover" id="productTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Quantité</th>
                        <th>Prix unitaire (FCFA)</th>
                        <th>Valeur stock (FCFA)</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                $res = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
                while($row = $res->fetch_assoc()):
                ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= $row['quantity'] ?></td>
                        <td><?= number_format($row['price'], 0, '', ' ') ?></td>
                        <td><?= number_format($row['quantity'] * $row['price'], 0, '', ' ') ?></td>
                        <td>
                            <?php if($row['quantity'] == 0): ?>
                            <span class="badge-stock-low">❌ Rupture</span>
                            <?php elseif($row['quantity'] <= 3): ?>
                            <span class="badge-stock-low">⚠️ Stock faible</span>
                            <?php else: ?>
                            <span class="badge-stock-ok">✅ En stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- VENDRE : tous -->
                            <?php if($row['quantity'] > 0): ?>
                            <a href="../sales/sell.php?product_id=<?= $row['id'] ?>" class="btn btn-success btn-sm"
                                title="Vendre">
                                <i class="bi bi-cart"></i>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled title="Rupture">
                                <i class="bi bi-cart-x"></i>
                            </button>
                            <?php endif; ?>

                            <!-- ✅ MODIFIER + SUPPRIMER : tous -->
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
        let value = this.value.toLowerCase();
        document.querySelectorAll("#productTable tbody tr").forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
        });
    });

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