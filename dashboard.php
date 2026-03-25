<?php 
session_start();
if(!isset($_SESSION['user'])){
    header("Location: auth/login.php");
    exit();
}

include('config/db.php');

// Stats produits
$productRes = $conn->query("SELECT COUNT(*) AS total_products, SUM(quantity * price) AS total_value FROM products");
$stats = $productRes->fetch_assoc();

// Stats utilisateurs
$userRes = $conn->query("SELECT COUNT(*) AS total_users FROM users");
$userCount = $userRes->fetch_assoc()['total_users'];

// Stats ventes
$salesRes = $conn->query("SELECT COUNT(*) AS total_sales, SUM(quantity_sold * sale_price) AS total_revenue FROM sales");
$salesStats = $salesRes->fetch_assoc();

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

// DONNÉES DIAGRAMME CIRCULAIRE : Top 5 produits les plus vendus
$pieData = $conn->query("
    SELECT p.name, SUM(s.quantity_sold) AS total_vendu
    FROM sales s
    JOIN products p ON s.product_id = p.id
    GROUP BY p.id
    ORDER BY total_vendu DESC
    LIMIT 5
");
$pieLabels = [];
$pieValues = [];
while($r = $pieData->fetch_assoc()){
    $pieLabels[] = $r['name'];
    $pieValues[] = (int)$r['total_vendu'];
}

// DONNÉES DIAGRAMME EN BARRE : Ventes des 7 derniers jours
$barData = $conn->query("
    SELECT DATE(sold_at) AS jour, SUM(quantity_sold * sale_price) AS revenu
    FROM sales
    WHERE sold_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(sold_at)
    ORDER BY jour ASC
");
$barLabels = [];
$barValues = [];
while($r = $barData->fetch_assoc()){
    $barLabels[] = date('d/m', strtotime($r['jour']));
    $barValues[] = (float)$r['revenu'];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f4f6f9;
    }

    .sidebar {
        position: fixed;
        width: 240px;
        height: 100%;
        background: #111827;
        padding-top: 20px;
    }

    .sidebar h4 {
        color: white;
        margin-bottom: 30px;
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
        margin-left: 250px;
        padding: 20px;
    }

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

    .topbar {
        background: white;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
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

    /* CHARTS */
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
        /* Taille réduite et fixe */
        height: 220px;
    }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar text-center">
        <h4>📦 Stock App</h4>
        <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="products/list.php"><i class="bi bi-box"></i> Produits</a>
        <a href="products/add.php"><i class="bi bi-plus-circle"></i> Ajouter</a>
        <a href="sales/sell.php"><i class="bi bi-cart-plus"></i> Vente</a>
        <a href="sales/list.php"><i class="bi bi-clock-history"></i> Historique</a>
        <a href="auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <!-- TOPBAR -->
        <div class="topbar d-flex justify-content-between align-items-center">
            <h5>Bienvenue, <?= htmlspecialchars($_SESSION['user']) ?> 👋</h5>
            <span class="text-muted">Dashboard</span>
        </div>

        <!-- STATS CARDS -->
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card-box bg-blue">
                    <h3><i class="bi bi-box"></i> <?= $stats['total_products'] ?? 0 ?></h3>
                    <p>Produits en stock</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-box bg-green">
                    <h3><i class="bi bi-cash"></i> <?= number_format($stats['total_value'] ?? 0, 0, '', ' ') ?> FCFA
                    </h3>
                    <p>Valeur du stock</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-box bg-orange">
                    <h3><i class="bi bi-people"></i> <?= $userCount ?? 0 ?></h3>
                    <p>Utilisateurs</p>
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

        <!-- TABLEAUX PRODUITS + VENTES -->
        <div class="row mt-4">

            <!-- PRODUITS RÉCENTS -->
            <div class="col-md-6">
                <div class="card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5>📦 Produits récents</h5>
                        <a href="products/list.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <input type="text" id="searchProducts" class="form-control search-box my-2"
                        placeholder="Rechercher...">
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
                                    <?php if($row['quantity'] <= 3): ?>
                                    <span class="badge-stock-low">⚠️ Faible</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($row['price'], 0, '', ' ') ?></td>
                                <td>
                                    <a href="sales/sell.php?product_id=<?= $row['id'] ?>" class="btn btn-success btn-sm"
                                        title="Vendre">
                                        <i class="bi bi-cart"></i>
                                    </a>
                                    <a href="products/edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="products/delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Supprimer ce produit ?')">
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

        <!-- ======= DIAGRAMMES EN BAS ======= -->
        <div class="row g-3 mt-3 mb-4">

            <!-- DIAGRAMME CIRCULAIRE -->
            <div class="col-md-4">
                <div class="chart-card">
                    <div class="chart-title">🥧 Top produits vendus</div>
                    <?php if(empty($pieLabels)): ?>
                    <p class="text-muted text-center py-4" style="font-size:13px;">
                        <i class="bi bi-info-circle"></i> Aucune vente enregistrée
                    </p>
                    <?php else: ?>
                    <div class="chart-wrapper">
                        <canvas id="pieChart"></canvas>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DIAGRAMME EN BARRE -->
            <div class="col-md-8">
                <div class="chart-card">
                    <div class="chart-title">📊 Revenus des 7 derniers jours (FCFA)</div>
                    <?php if(empty($barLabels)): ?>
                    <p class="text-muted text-center py-4" style="font-size:13px;">
                        <i class="bi bi-info-circle"></i> Aucune vente ces 7 derniers jours
                    </p>
                    <?php else: ?>
                    <div class="chart-wrapper">
                        <canvas id="barChart"></canvas>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

    <!-- SCRIPTS -->
    <script>
    // Recherche produits
    document.getElementById("searchProducts").addEventListener("keyup", function() {
        let value = this.value.toLowerCase();
        document.querySelectorAll("#productTable tbody tr").forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
        });
    });

    // ---- DIAGRAMME CIRCULAIRE ----
    <?php if(!empty($pieLabels)): ?>
    const pieCtx = document.getElementById('pieChart').getContext('2d');
    new Chart(pieCtx, {
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
                        label: function(context) {
                            return ' ' + context.label + ' : ' + context.parsed + ' vendus';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // ---- DIAGRAMME EN BARRE ----
    <?php if(!empty($barLabels)): ?>
    const barCtx = document.getElementById('barChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($barLabels) ?>,
            datasets: [{
                label: 'Revenus (FCFA)',
                data: <?= json_encode($barValues) ?>,
                backgroundColor: 'rgba(99,102,241,0.75)',
                borderColor: '#4f46e5',
                borderWidth: 2,
                borderRadius: 6,
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
                        label: function(context) {
                            return ' ' + context.parsed.y.toLocaleString('fr-FR') + ' FCFA';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('fr-FR') + ' F';
                        },
                        font: {
                            family: 'Poppins',
                            size: 11
                        }
                    },
                    grid: {
                        color: '#f3f4f6'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            family: 'Poppins',
                            size: 11
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    <?php endif; ?>
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