<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');

$message     = '';
$msgType     = '';
$preselected_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $product_id    = intval($_POST['product_id']);
    $quantity_sold = intval($_POST['quantity_sold']);

    $res     = $conn->query("SELECT * FROM products WHERE id = $product_id");
    $product = $res->fetch_assoc();

    if(!$product){
        $message = "Produit introuvable.";
        $msgType = "error";
    } elseif($quantity_sold <= 0){
        $message = "Quantité invalide. Entrez un nombre supérieur à 0.";
        $msgType = "error";
    } elseif($quantity_sold > $product['quantity']){
        $message = "Stock insuffisant ! Stock disponible : <strong>{$product['quantity']}</strong> unité(s).";
        $msgType = "warning";
    } else {
        $sale_price = $product['price'];
        $conn->query("INSERT INTO sales (product_id, quantity_sold, sale_price) VALUES ($product_id, $quantity_sold, $sale_price)");
        $conn->query("UPDATE products SET quantity = quantity - $quantity_sold WHERE id = $product_id");
        $newStock = $conn->query("SELECT quantity FROM products WHERE id = $product_id")->fetch_assoc();
        $total    = $quantity_sold * $sale_price;
        $message  = "Vente enregistrée ! <strong>{$product['name']}</strong> — {$quantity_sold} unité(s) — <strong>" . number_format($total,0,'',' ') . " FCFA</strong> — Stock restant : <strong>{$newStock['quantity']}</strong>";
        $msgType  = "success";
        $preselected_id = 0;
    }
}

$products = $conn->query("SELECT * FROM products ORDER BY name ASC");

// Stats rapides
$totalVentes  = $conn->query("SELECT COUNT(*) AS c FROM sales")->fetch_assoc()['c'];
$totalRevenu  = $conn->query("SELECT SUM(quantity_sold * sale_price) AS r FROM sales")->fetch_assoc()['r'] ?? 0;
$ventesAujourdhui = $conn->query("SELECT COUNT(*) AS c FROM sales WHERE DATE(sold_at) = CURDATE()")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Vente — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --sidebar-w: 260px;
        --dark:    #0f172a;
        --gray:    #64748b;
        --light:   #f8fafc;
        --border:  #e2e8f0;
        --white:   #ffffff;
        --primary: #6366f1;
        --green:   #10b981;
        --red:     #ef4444;
        --orange:  #f59e0b;
    }
    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f0f4ff;
        min-height: 100vh;
    }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 10px; }

    /* SIDEBAR */
    .sidebar {
        position: fixed; left: 0; top: 0;
        width: var(--sidebar-w); height: 100vh;
        background: var(--dark);
        display: flex; flex-direction: column;
        z-index: 100; overflow: hidden;
    }
    .sidebar::before {
        content: ''; position: absolute;
        width: 200px; height: 200px;
        background: radial-gradient(circle, rgba(99,102,241,0.3), transparent);
        top: -60px; right: -60px; border-radius: 50%;
        pointer-events: none;
    }
    .sidebar-brand {
        padding: 28px 24px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .brand-icon {
        width: 44px; height: 44px;
        background: linear-gradient(135deg, var(--primary), #818cf8);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px; margin-bottom: 10px;
        box-shadow: 0 4px 15px rgba(99,102,241,0.4);
    }
    .sidebar-brand h4 { color: white; font-size: 16px; font-weight: 700; margin: 0; }
    .sidebar-brand span { color: rgba(255,255,255,0.4); font-size: 11px; }
    .sidebar-nav { flex: 1; padding: 20px 12px; overflow-y: auto; }
    .nav-section {
        font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
        color: rgba(255,255,255,0.25); text-transform: uppercase;
        padding: 0 12px; margin: 16px 0 8px;
    }
    .sidebar-nav a {
        display: flex; align-items: center; gap: 12px;
        padding: 11px 14px; border-radius: 10px;
        color: rgba(255,255,255,0.55); text-decoration: none;
        font-size: 14px; font-weight: 500; transition: all 0.2s;
        margin-bottom: 2px;
    }
    .sidebar-nav a i { font-size: 17px; width: 20px; text-align: center; }
    .sidebar-nav a:hover { background: rgba(255,255,255,0.07); color: white; }
    .sidebar-nav a.active {
        background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(99,102,241,0.1));
        color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3);
    }
    .sidebar-nav a.active i { color: var(--primary); }
    .sidebar-footer {
        padding: 16px 12px;
        border-top: 1px solid rgba(255,255,255,0.06);
    }
    .sidebar-footer a {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; border-radius: 10px;
        color: rgba(255,255,255,0.4); text-decoration: none;
        font-size: 13px; transition: all 0.2s;
    }
    .sidebar-footer a:hover { background: rgba(239,68,68,0.1); color: #fca5a5; }

    /* CONTENT */
    .content { margin-left: var(--sidebar-w); padding: 32px; min-height: 100vh; }

    /* TOPBAR */
    .topbar {
        display: flex; justify-content: space-between;
        align-items: center; margin-bottom: 28px;
    }
    .topbar h5 { font-size: 22px; font-weight: 800; color: var(--dark); margin: 0; }
    .topbar p  { font-size: 13px; color: var(--gray); margin: 3px 0 0; }
    .breadcrumb-nav {
        display: flex; align-items: center; gap: 8px;
        font-size: 13px; color: var(--gray);
    }
    .breadcrumb-nav a { color: var(--primary); text-decoration: none; font-weight: 600; }
    .breadcrumb-nav a:hover { text-decoration: underline; }

    /* MINI STATS */
    .mini-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
    .mini-stat {
        background: white; border-radius: 16px; padding: 18px 20px;
        display: flex; align-items: center; gap: 14px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        border: 1px solid var(--border); transition: transform 0.2s;
    }
    .mini-stat:hover { transform: translateY(-3px); }
    .mini-stat-icon {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px; flex-shrink: 0;
    }
    .mini-stat-icon.purple { background: #ede9fe; color: var(--primary); }
    .mini-stat-icon.green  { background: #d1fae5; color: var(--green); }
    .mini-stat-icon.orange { background: #fef3c7; color: var(--orange); }
    .mini-stat-value { font-size: 20px; font-weight: 800; color: var(--dark); line-height: 1; }
    .mini-stat-label { font-size: 12px; color: var(--gray); margin-top: 3px; font-weight: 500; }

    /* LAYOUT */
    .sell-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 24px; align-items: start;
    }

    /* FORM CARD */
    .form-card {
        background: white; border-radius: 20px; padding: 32px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        border: 1px solid var(--border);
    }
    .form-card-header {
        display: flex; align-items: center; gap: 14px;
        margin-bottom: 28px; padding-bottom: 20px;
        border-bottom: 2px solid #f1f5f9;
    }
    .form-header-icon {
        width: 52px; height: 52px;
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px;
    }
    .form-card-header h4 { font-size: 20px; font-weight: 800; color: var(--dark); margin: 0; }
    .form-card-header p  { font-size: 13px; color: var(--gray); margin: 3px 0 0; }

    /* ALERT */
    .alert-msg {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 18px; border-radius: 12px;
        font-size: 14px; font-weight: 500;
        margin-bottom: 24px; animation: slideDown 0.3s ease;
        line-height: 1.6;
    }
    .alert-msg i { font-size: 20px; flex-shrink: 0; margin-top: 1px; }
    .alert-msg.success { background: #f0fdf4; color: #15803d; border: 1.5px solid #bbf7d0; }
    .alert-msg.error   { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca; }
    .alert-msg.warning { background: #fffbeb; color: #d97706; border: 1.5px solid #fde68a; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* FORM ELEMENTS */
    .form-group { margin-bottom: 22px; }
    .form-group label {
        display: flex; align-items: center; gap: 7px;
        font-size: 13px; font-weight: 700;
        color: var(--dark); margin-bottom: 8px;
    }
    .form-group label .lbl-icon {
        width: 22px; height: 22px;
        background: #ede9fe; color: var(--primary);
        border-radius: 6px; display: flex;
        align-items: center; justify-content: center;
        font-size: 12px;
    }
    .form-group label .lbl-icon.green { background: #d1fae5; color: var(--green); }
    .required { color: var(--red); margin-left: 2px; }

    .input-wrap { position: relative; }
    .input-wrap select,
    .input-wrap input {
        width: 100%; padding: 13px 16px 13px 44px;
        border: 2px solid var(--border); border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px; color: var(--dark);
        background: #fafbff; outline: none;
        transition: all 0.25s; font-weight: 500;
        appearance: none;
    }
    .input-wrap select:focus,
    .input-wrap input:focus {
        border-color: var(--primary); background: white;
        box-shadow: 0 0 0 4px rgba(99,102,241,0.08);
    }
    .input-wrap select.is-invalid,
    .input-wrap input.is-invalid {
        border-color: var(--red);
        box-shadow: 0 0 0 4px rgba(239,68,68,0.08);
    }
    .input-icon {
        position: absolute; left: 14px; top: 50%;
        transform: translateY(-50%);
        font-size: 17px; color: #94a3b8;
        transition: color 0.2s; pointer-events: none;
    }
    .input-wrap:focus-within .input-icon { color: var(--primary); }
    .input-hint {
        font-size: 12px; color: #94a3b8;
        margin-top: 6px; display: flex; align-items: center; gap: 4px;
    }
    .input-hint.ok    { color: var(--green); }
    .input-hint.error { color: var(--red); }

    /* PRODUCT INFO BOX */
    .product-info-box {
        background: #f0fdf4; border: 1.5px solid #bbf7d0;
        border-radius: 14px; padding: 16px 18px;
        margin-bottom: 20px; display: none;
        animation: fadeIn 0.3s ease;
    }
    .product-info-box.rupture { background: #fef2f2; border-color: #fecaca; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .info-row {
        display: flex; justify-content: space-between;
        align-items: center; font-size: 13px; font-weight: 600;
        color: var(--dark);
    }
    .info-row + .info-row { margin-top: 8px; padding-top: 8px; border-top: 1px dashed #d1fae5; }
    .info-badge {
        background: rgba(16,185,129,0.12); color: var(--green);
        padding: 3px 10px; border-radius: 20px;
        font-size: 12px; font-weight: 700;
    }
    .info-total {
        font-size: 18px; font-weight: 800; color: var(--green);
    }

    /* BOUTON */
    .btn-sell {
        width: 100%; padding: 15px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white; border: none; border-radius: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 15px; font-weight: 700;
        cursor: pointer; transition: all 0.3s;
        display: flex; align-items: center;
        justify-content: center; gap: 10px;
        box-shadow: 0 4px 15px rgba(16,185,129,0.3);
    }
    .btn-sell:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(16,185,129,0.45);
    }
    .btn-sell:disabled {
        background: #e2e8f0; color: #94a3b8;
        cursor: not-allowed; box-shadow: none;
    }
    .btn-sell:active:not(:disabled) { transform: translateY(0); }

    /* QUICK LINKS */
    .quick-links {
        display: flex; justify-content: space-between;
        margin-top: 16px;
    }
    .quick-link {
        display: flex; align-items: center; gap: 6px;
        font-size: 13px; color: var(--gray);
        text-decoration: none; font-weight: 600;
        transition: color 0.2s;
    }
    .quick-link:hover { color: var(--primary); }

    /* SIDE PANEL */
    .side-panel { display: flex; flex-direction: column; gap: 16px; }

    /* RECAP CARD */
    .recap-card {
        background: white; border-radius: 20px; padding: 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        border: 1px solid var(--border);
    }
    .recap-card h6 {
        font-size: 13px; font-weight: 700; color: var(--gray);
        text-transform: uppercase; letter-spacing: 1px;
        margin-bottom: 18px; display: flex; align-items: center; gap: 6px;
    }
    .recap-product {
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 16px; padding: 20px;
        color: white; position: relative; overflow: hidden;
        min-height: 130px;
    }
    .recap-product::before {
        content: '';
        position: absolute; width: 120px; height: 120px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%; right: -30px; top: -30px;
    }
    .recap-icon { font-size: 28px; margin-bottom: 10px; }
    .recap-name {
        font-size: 16px; font-weight: 700;
        color: white; margin-bottom: 4px;
    }
    .recap-name.empty { color: rgba(255,255,255,0.4); font-style: italic; }
    .recap-pills { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
    .recap-pill {
        background: rgba(255,255,255,0.2);
        padding: 4px 10px; border-radius: 20px;
        font-size: 11px; font-weight: 600;
        backdrop-filter: blur(4px);
    }
    .recap-total-box {
        margin-top: 16px; padding: 16px;
        background: #f0fdf4; border-radius: 12px;
        border: 1.5px solid #bbf7d0; text-align: center;
    }
    .recap-total-label { font-size: 11px; font-weight: 700; color: var(--gray); text-transform: uppercase; letter-spacing: 1px; }
    .recap-total-value { font-size: 26px; font-weight: 800; color: var(--green); margin-top: 4px; }

    /* LAST SALES */
    .last-sales-card {
        background: var(--dark); border-radius: 20px; padding: 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .last-sales-card h6 {
        color: rgba(255,255,255,0.5); font-size: 11px; font-weight: 700;
        letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 16px;
    }
    .sale-item {
        display: flex; justify-content: space-between;
        align-items: center; padding: 10px 0;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .sale-item:last-child { border-bottom: none; padding-bottom: 0; }
    .sale-item-name { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.85); }
    .sale-item-date { font-size: 11px; color: rgba(255,255,255,0.35); margin-top: 2px; }
    .sale-item-amount {
        font-size: 13px; font-weight: 700; color: #6ee7b7;
        white-space: nowrap;
    }

    @media(max-width: 900px){
        .sell-layout { grid-template-columns: 1fr; }
        .side-panel  { display: none; }
        .mini-stats  { grid-template-columns: 1fr; }
    }
    @media(max-width: 768px){
        .sidebar { display: none; }
        .content { margin-left: 0; padding: 20px; }
    }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">📦</div>
        <h4>Stock App</h4>
        <span>Gestion de stock</span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="nav-section">Inventaire</div>
        <a href="../products/list.php"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="../products/add.php"><i class="bi bi-plus-circle"></i> Ajouter produit</a>
        <div class="nav-section">Ventes</div>
        <a href="sell.php" class="active"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
        <a href="list.php"><i class="bi bi-clock-history"></i> Historique</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </div>
</div>

<!-- CONTENT -->
<div class="content">

    <!-- TOPBAR -->
    <div class="topbar">
        <div>
            <h5>🛒 Nouvelle Vente</h5>
            <p>Enregistrez une vente et mettez le stock à jour automatiquement</p>
        </div>
        <div class="breadcrumb-nav">
            <a href="../dashboard.php"><i class="bi bi-house"></i> Accueil</a>
            <i class="bi bi-chevron-right"></i>
            <a href="list.php">Ventes</a>
            <i class="bi bi-chevron-right"></i>
            <span>Nouvelle vente</span>
        </div>
    </div>

    <!-- MINI STATS -->
    <div class="mini-stats">
        <div class="mini-stat">
            <div class="mini-stat-icon purple"><i class="bi bi-cart-check"></i></div>
            <div>
                <div class="mini-stat-value"><?= $totalVentes ?></div>
                <div class="mini-stat-label">Total ventes</div>
            </div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon green"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="mini-stat-value" style="font-size:15px;"><?= number_format($totalRevenu,0,'',' ') ?> F</div>
                <div class="mini-stat-label">Revenus totaux</div>
            </div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon orange"><i class="bi bi-calendar-check"></i></div>
            <div>
                <div class="mini-stat-value"><?= $ventesAujourdhui ?></div>
                <div class="mini-stat-label">Ventes aujourd'hui</div>
            </div>
        </div>
    </div>

    <!-- LAYOUT -->
    <div class="sell-layout">

        <!-- FORMULAIRE -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-header-icon">🛒</div>
                <div>
                    <h4>Enregistrer une vente</h4>
                    <p>Sélectionnez le produit et la quantité vendue</p>
                </div>
            </div>

            <!-- ALERT -->
            <?php if($message): ?>
            <div class="alert-msg <?= $msgType ?>" id="alertMsg">
                <?php if($msgType==='success'): ?>
                    <i class="bi bi-check-circle-fill"></i>
                <?php elseif($msgType==='warning'): ?>
                    <i class="bi bi-exclamation-triangle-fill"></i>
                <?php else: ?>
                    <i class="bi bi-x-circle-fill"></i>
                <?php endif; ?>
                <span><?= $message ?></span>
            </div>
            <?php endif; ?>

            <!-- INFO PRODUIT DYNAMIQUE -->
            <div class="product-info-box" id="productInfo">
                <div class="info-row">
                    <span><i class="bi bi-box-seam" style="color:#10b981;margin-right:6px;"></i><strong id="infoNom"></strong></span>
                    <span class="info-badge" id="infoStock"></span>
                </div>
                <div class="info-row">
                    <span style="color:#64748b;">Prix unitaire</span>
                    <span><strong id="infoPrix"></strong> FCFA</span>
                </div>
                <div class="info-row">
                    <span style="color:#64748b;">Total estimé</span>
                    <span class="info-total" id="infoTotal">—</span>
                </div>
            </div>

            <form method="POST">

                <!-- PRODUIT -->
                <div class="form-group">
                    <label>
                        <span class="lbl-icon"><i class="bi bi-box-seam"></i></span>
                        Produit <span class="required">*</span>
                    </label>
                    <div class="input-wrap">
                        <i class="bi bi-search input-icon"></i>
                        <select name="product_id" id="productSelect" required>
                            <option value="">— Sélectionner un produit —</option>
                            <?php while($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"
                                data-nom="<?= htmlspecialchars($p['name']) ?>"
                                data-stock="<?= $p['quantity'] ?>"
                                data-prix="<?= $p['price'] ?>"
                                <?= ($p['id'] == $preselected_id) ? 'selected' : '' ?>
                                <?= ($p['quantity'] == 0) ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                                (<?= $p['quantity'] ?> en stock)
                                <?= ($p['quantity'] == 0) ? '— Rupture' : '' ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="input-hint"><i class="bi bi-info-circle"></i> Les produits en rupture sont désactivés</div>
                </div>

                <!-- QUANTITÉ -->
                <div class="form-group">
                    <label>
                        <span class="lbl-icon green"><i class="bi bi-hash"></i></span>
                        Quantité vendue <span class="required">*</span>
                    </label>
                    <div class="input-wrap">
                        <i class="bi bi-stack input-icon"></i>
                        <input type="number" name="quantity_sold" id="quantityInput"
                               placeholder="Ex: 2" min="1" required>
                    </div>
                    <div class="input-hint" id="stockDispo">
                        <i class="bi bi-info-circle"></i> Sélectionnez d'abord un produit
                    </div>
                </div>

                <button type="submit" class="btn-sell" id="btnVendre">
                    <i class="bi bi-check-circle-fill"></i>
                    Enregistrer la vente
                </button>

                <div class="quick-links">
                    <a href="../dashboard.php" class="quick-link">
                        <i class="bi bi-arrow-left"></i> Dashboard
                    </a>
                    <a href="list.php" class="quick-link">
                        Historique <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

            </form>
        </div>

        <!-- PANNEAU LATÉRAL -->
        <div class="side-panel">

            <!-- RÉCAP EN DIRECT -->
            <div class="recap-card">
                <h6><i class="bi bi-eye"></i> Récapitulatif</h6>
                <div class="recap-product">
                    <div class="recap-icon">🛒</div>
                    <div class="recap-name empty" id="recapName">Aucun produit sélectionné</div>
                    <div class="recap-pills">
                        <div class="recap-pill" id="recapStock">Stock: —</div>
                        <div class="recap-pill" id="recapPrix">Prix: —</div>
                    </div>
                </div>
                <div class="recap-total-box">
                    <div class="recap-total-label">Total estimé</div>
                    <div class="recap-total-value" id="recapTotal">— FCFA</div>
                </div>
            </div>

            <!-- DERNIÈRES VENTES -->
            <div class="last-sales-card">
                <h6>⚡ Dernières ventes</h6>
                <?php
                $lastSales = $conn->query("
                    SELECT s.quantity_sold, s.sale_price, s.sold_at, p.name
                    FROM sales s JOIN products p ON s.product_id = p.id
                    ORDER BY s.sold_at DESC LIMIT 5
                ");
                if($lastSales->num_rows === 0){
                    echo "<p style='color:rgba(255,255,255,0.3);font-size:13px;text-align:center;padding:10px 0;'>Aucune vente récente</p>";
                }
                while($ls = $lastSales->fetch_assoc()):
                    $total = $ls['quantity_sold'] * $ls['sale_price'];
                ?>
                <div class="sale-item">
                    <div>
                        <div class="sale-item-name"><?= htmlspecialchars($ls['name']) ?></div>
                        <div class="sale-item-date"><?= date('d/m/Y H:i', strtotime($ls['sold_at'])) ?></div>
                    </div>
                    <div class="sale-item-amount">+<?= number_format($total,0,'',' ') ?> F</div>
                </div>
                <?php endwhile; ?>
            </div>

        </div>
    </div>
</div>

<script>
const select    = document.getElementById('productSelect');
const qtyInput  = document.getElementById('quantityInput');
const infoBox   = document.getElementById('productInfo');
const infoNom   = document.getElementById('infoNom');
const infoStock = document.getElementById('infoStock');
const infoPrix  = document.getElementById('infoPrix');
const infoTotal = document.getElementById('infoTotal');
const stockDispo = document.getElementById('stockDispo');
const btnVendre  = document.getElementById('btnVendre');
const recapName  = document.getElementById('recapName');
const recapStock = document.getElementById('recapStock');
const recapPrix  = document.getElementById('recapPrix');
const recapTotal = document.getElementById('recapTotal');

function updateInfo() {
    const opt   = select.options[select.selectedIndex];
    const stock = parseInt(opt.dataset.stock) || 0;
    const prix  = parseFloat(opt.dataset.prix) || 0;
    const nom   = opt.dataset.nom || '';
    const qty   = parseInt(qtyInput.value) || 0;

    if(select.value){
        // Info box
        infoBox.style.display = 'block';
        infoNom.textContent   = nom;
        infoStock.textContent = stock + ' en stock';
        infoPrix.textContent  = prix.toLocaleString('fr-FR');
        infoTotal.textContent = qty > 0 ? (qty * prix).toLocaleString('fr-FR') + ' FCFA' : '—';

        // Récap latéral
        recapName.textContent  = nom;
        recapName.classList.remove('empty');
        recapStock.textContent = 'Stock: ' + stock;
        recapPrix.textContent  = 'Prix: ' + prix.toLocaleString('fr-FR') + ' F';
        recapTotal.textContent = qty > 0 ? (qty * prix).toLocaleString('fr-FR') + ' FCFA' : '— FCFA';

        // Validation quantité
        if(qty > stock && qty > 0){
            qtyInput.classList.add('is-invalid');
            stockDispo.innerHTML = '<i class="bi bi-x-circle"></i> Stock insuffisant ! Max: ' + stock;
            stockDispo.className = 'input-hint error';
            btnVendre.disabled   = true;
        } else {
            qtyInput.classList.remove('is-invalid');
            stockDispo.innerHTML = '<i class="bi bi-check-circle"></i> Stock disponible : ' + stock;
            stockDispo.className = 'input-hint ok';
            btnVendre.disabled   = false;
        }
    } else {
        infoBox.style.display  = 'none';
        recapName.textContent  = 'Aucun produit sélectionné';
        recapName.classList.add('empty');
        recapStock.textContent = 'Stock: —';
        recapPrix.textContent  = 'Prix: —';
        recapTotal.textContent = '— FCFA';
        stockDispo.innerHTML   = '<i class="bi bi-info-circle"></i> Sélectionnez d\'abord un produit';
        stockDispo.className   = 'input-hint';
    }
}

select.addEventListener('change', updateInfo);
qtyInput.addEventListener('input', updateInfo);
window.addEventListener('load', () => { if(select.value) updateInfo(); });

// Disparition alertes
setTimeout(function(){
    const alert = document.getElementById('alertMsg');
    if(alert){
        alert.style.transition = 'opacity 0.8s ease';
        alert.style.opacity    = '0';
        setTimeout(() => alert.remove(), 800);
    }
}, 10000);
</script>

</body>
</html>