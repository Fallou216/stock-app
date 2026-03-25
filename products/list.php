<?php 
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');

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
    }

    .sidebar h4 {
        color: white;
    }

    .sidebar a {
        display: block;
        color: #9ca3af;
        padding: 15px;
        text-decoration: none;
        transition: 0.3s;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background: #4f46e5;
        color: white;
        border-radius: 8px;
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
    <div class="sidebar text-center">
        <h4>📦 Stock</h4>
        <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="list.php" class="active"><i class="bi bi-box"></i> Produits</a>
        <a href="add.php"><i class="bi bi-plus-circle"></i> Ajouter</a>
        <a href="../sales/sell.php"><i class="bi bi-cart-plus"></i> Vente</a>
        <a href="../sales/list.php"><i class="bi bi-clock-history"></i> Historique</a>
        <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
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
                // Relancer la requête car on a utilisé $res plus haut
                $res = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
                while($row = $res->fetch_assoc()){ 
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
                            <!-- Bouton Vendre (désactivé si rupture) -->
                            <?php if($row['quantity'] > 0): ?>
                            <a href="../sales/sell.php?product_id=<?= $row['id'] ?>" class="btn btn-success btn-sm"
                                title="Vendre">
                                <i class="bi bi-cart"></i>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled title="Rupture de stock">
                                <i class="bi bi-cart-x"></i>
                            </button>
                            <?php endif; ?>

                            <!-- Modifier -->
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm" title="Modifier">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <!-- Supprimer -->
                            <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
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

    <!-- SEARCH SCRIPT -->
    <script>
    document.getElementById("search").addEventListener("keyup", function() {
        let value = this.value.toLowerCase();
        document.querySelectorAll("#productTable tbody tr").forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
        });
    });
    </script>

    <script>
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.transition = 'opacity 0.8s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 800);
        });
    }, 10000);
    </script>

</body>

</html>