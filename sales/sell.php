<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');

$message = '';

// Pré-sélection du produit via URL (?product_id=X)
$preselected_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Traitement du formulaire
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $product_id    = intval($_POST['product_id']);
    $quantity_sold = intval($_POST['quantity_sold']);

    // Vérifier stock disponible
    $res     = $conn->query("SELECT * FROM products WHERE id = $product_id");
    $product = $res->fetch_assoc();

    if(!$product){
        $message = "<div class='alert alert-danger'>❌ Produit introuvable.</div>";
    } elseif($quantity_sold <= 0){
        $message = "<div class='alert alert-danger'>❌ Quantité invalide.</div>";
    } elseif($quantity_sold > $product['quantity']){
        $message = "<div class='alert alert-danger'>⚠️ Stock insuffisant ! Stock disponible : <strong>{$product['quantity']}</strong></div>";
    } else {
        $sale_price = $product['price'];

        // Enregistrer la vente
        $conn->query("INSERT INTO sales (product_id, quantity_sold, sale_price) 
                      VALUES ($product_id, $quantity_sold, $sale_price)");

        // Diminuer le stock
        $conn->query("UPDATE products SET quantity = quantity - $quantity_sold WHERE id = $product_id");

        // Récupérer le nouveau stock
        $newStock = $conn->query("SELECT quantity FROM products WHERE id = $product_id")->fetch_assoc();
        $total    = $quantity_sold * $sale_price;

        $message = "
        <div class='alert alert-success'>
            ✅ Vente enregistrée avec succès !<br>
            <small>
                <strong>{$product['name']}</strong> — 
                Quantité vendue : <strong>$quantity_sold</strong> — 
                Total : <strong>" . number_format($total, 0, '', ' ') . " FCFA</strong><br>
                Stock restant : <strong>{$newStock['quantity']}</strong>
            </small>
        </div>";

        // Réinitialiser la présélection après vente
        $preselected_id = 0;
    }
}

// Récupérer tous les produits (même rupture pour info)
$products = $conn->query("SELECT * FROM products ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Nouvelle Vente</title>
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
        padding: 30px;
    }

    .sell-card {
        max-width: 550px;
        margin: auto;
        border-radius: 15px;
        border: none;
    }

    .product-info-box {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        display: none;
    }

    .product-info-box.rupture {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .form-select:focus,
    .form-control:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }

    .btn-sell {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
        color: white;
        font-weight: 600;
        padding: 12px;
        font-size: 16px;
    }

    .btn-sell:hover {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }

    .btn-sell:disabled {
        background: #9ca3af;
        cursor: not-allowed;
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
        <a href="sell.php" class="active"><i class="bi bi-cart-plus"></i> Vente</a>
        <a href="list.php"><i class="bi bi-clock-history"></i> Historique</a>
        <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </div>

    <!-- CONTENT -->
    <div class="content">
        <div class="card sell-card p-4 shadow">

            <h4 class="mb-4"><i class="bi bi-cart-plus text-success"></i> Nouvelle Vente</h4>

            <?= $message ?>

            <!-- INFO PRODUIT SÉLECTIONNÉ (dynamique) -->
            <div class="product-info-box" id="productInfo">
                <div class="d-flex justify-content-between">
                    <span><strong id="infoNom"></strong></span>
                    <span class="text-muted" id="infoStock"></span>
                </div>
                <div class="mt-1">
                    Prix unitaire : <strong id="infoPrix"></strong> FCFA
                </div>
                <div class="mt-1">
                    Total estimé : <strong id="infoTotal" class="text-success"></strong> FCFA
                </div>
            </div>

            <form method="POST">

                <!-- SELECT PRODUIT -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Produit</label>
                    <select name="product_id" id="productSelect" class="form-select" required>
                        <option value="">-- Choisir un produit --</option>
                        <?php while($p = $products->fetch_assoc()){ ?>
                        <option value="<?= $p['id'] ?>" data-nom="<?= htmlspecialchars($p['name']) ?>"
                            data-stock="<?= $p['quantity'] ?>" data-prix="<?= $p['price'] ?>"
                            <?= ($p['id'] == $preselected_id) ? 'selected' : '' ?>
                            <?= ($p['quantity'] == 0) ? 'disabled' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                            (Stock: <?= $p['quantity'] ?>)
                            <?= ($p['quantity'] == 0) ? '— Rupture' : '' ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- QUANTITÉ -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Quantité vendue</label>
                    <input type="number" name="quantity_sold" id="quantityInput" class="form-control" min="1"
                        placeholder="Ex: 2" required>
                    <div class="form-text" id="stockDispo"></div>
                </div>

                <!-- BOUTON -->
                <button type="submit" class="btn btn-sell w-100" id="btnVendre">
                    <i class="bi bi-check-circle"></i> Enregistrer la vente
                </button>

                <!-- LIENS RAPIDES -->
                <div class="d-flex justify-content-between mt-3">
                    <a href="../dashboard.php" class="text-muted text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Retour Dashboard
                    </a>
                    <a href="list.php" class="text-muted text-decoration-none">
                        Voir l'historique <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

            </form>
        </div>
    </div>

    <script>
    // Données produits en JS
    const select = document.getElementById('productSelect');
    const qtyInput = document.getElementById('quantityInput');
    const infoBox = document.getElementById('productInfo');
    const infoNom = document.getElementById('infoNom');
    const infoStock = document.getElementById('infoStock');
    const infoPrix = document.getElementById('infoPrix');
    const infoTotal = document.getElementById('infoTotal');
    const stockDispo = document.getElementById('stockDispo');
    const btnVendre = document.getElementById('btnVendre');

    function updateInfo() {
        const opt = select.options[select.selectedIndex];
        const stock = parseInt(opt.dataset.stock) || 0;
        const prix = parseFloat(opt.dataset.prix) || 0;
        const nom = opt.dataset.nom || '';
        const qty = parseInt(qtyInput.value) || 0;

        if (select.value) {
            infoBox.style.display = 'block';
            infoNom.textContent = nom;
            infoStock.textContent = 'Stock : ' + stock;
            infoPrix.textContent = prix.toLocaleString('fr-FR');
            infoTotal.textContent = qty > 0 ? (qty * prix).toLocaleString('fr-FR') : '—';
            stockDispo.textContent = 'Stock disponible : ' + stock;

            // Bloquer si quantité dépasse le stock
            if (qty > stock) {
                qtyInput.classList.add('is-invalid');
                btnVendre.disabled = true;
            } else {
                qtyInput.classList.remove('is-invalid');
                btnVendre.disabled = false;
            }
        } else {
            infoBox.style.display = 'none';
            stockDispo.textContent = '';
        }
    }

    select.addEventListener('change', updateInfo);
    qtyInput.addEventListener('input', updateInfo);

    // Déclencher si produit pré-sélectionné
    window.addEventListener('load', () => {
        if (select.value) updateInfo();
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