<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');

$sales = $conn->query("
    SELECT s.*, p.name AS product_name 
    FROM sales s 
    JOIN products p ON s.product_id = p.id 
    ORDER BY s.sold_at DESC
");
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Historique des ventes</title>
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

    .search-box {
        max-width: 300px;
    }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar text-center">
        <h4>📦 Stock App</h4>
        <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="../products/list.php"><i class="bi bi-box"></i> Produits</a>
        <a href="../products/add.php"><i class="bi bi-plus-circle"></i> Ajouter</a>
        <a href="sell.php"><i class="bi bi-cart-plus"></i> Vente</a>
        <a href="list.php" class="active"><i class="bi bi-clock-history"></i> Historique</a>
        <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </div>

    <div class="content">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-clock-history"></i> Historique des ventes</h3>
            <a href="sell.php" class="btn btn-success btn-sm">
                <i class="bi bi-cart-plus"></i> Nouvelle vente
            </a>
        </div>

        <div class="card p-3 shadow-sm">

            <!-- BARRE DE RECHERCHE -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <input type="text" id="search" class="form-control search-box"
                    placeholder="🔍 Rechercher un produit...">
                <span class="text-muted ms-3" id="countResult" style="font-size: 13px; white-space: nowrap;"></span>
            </div>

            <table class="table table-hover" id="salesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produit</th>
                        <th>Quantité vendue</th>
                        <th>Prix unitaire (FCFA)</th>
                        <th>Total (FCFA)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                if($sales->num_rows === 0){
                    echo "<tr><td colspan='6' class='text-center text-muted py-4'>Aucune vente enregistrée</td></tr>";
                }
                while($s = $sales->fetch_assoc()){ ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><?= htmlspecialchars($s['product_name']) ?></td>
                        <td><?= $s['quantity_sold'] ?></td>
                        <td><?= number_format($s['sale_price'], 0, '', ' ') ?></td>
                        <td><strong><?= number_format($s['quantity_sold'] * $s['sale_price'], 0, '', ' ') ?></strong>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($s['sold_at'])) ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>

        </div>
    </div>

    <script>
    const searchInput = document.getElementById('search');
    const countResult = document.getElementById('countResult');
    const allRows = document.querySelectorAll('#salesTable tbody tr');

    // Afficher le total au chargement
    countResult.textContent = allRows.length + ' vente(s)';

    searchInput.addEventListener('keyup', function() {
        const value = this.value.toLowerCase();
        let visible = 0;

        allRows.forEach(row => {
            const match = row.innerText.toLowerCase().includes(value);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        countResult.textContent = visible + ' vente(s) trouvée(s)';
    });
    </script>

</body>

</html>