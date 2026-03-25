<?php 
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');

$message = "";

if(isset($_POST['add'])){
    $n = trim($_POST['name']);
    $q = intval($_POST['quantity']);
    $p = floatval($_POST['price']);

    if(!empty($n) && $q > 0 && $p > 0){
        $conn->query("INSERT INTO products(name,quantity,price) VALUES('$n','$q','$p')");
        $message = "success";
    } else {
        $message = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    *,
    *::before,
    *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    :root {
        --sidebar-w: 260px;
        --dark: #0f172a;
        --dark2: #1e293b;
        --gray: #64748b;
        --light: #f8fafc;
        --border: #e2e8f0;
        --white: #ffffff;
        --primary: #6366f1;
        --green: #10b981;
        --red: #ef4444;
        --orange: #f59e0b;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f0f4ff;
        min-height: 100vh;
    }

    /* ===== SIDEBAR ===== */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: var(--sidebar-w);
        height: 100vh;
        background: var(--dark);
        display: flex;
        flex-direction: column;
        z-index: 100;
        overflow: hidden;
    }

    .sidebar::before {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.3), transparent);
        top: -60px;
        right: -60px;
        border-radius: 50%;
        pointer-events: none;
    }

    .sidebar-brand {
        padding: 28px 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .brand-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, var(--primary), #818cf8);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 10px;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    }

    .sidebar-brand h4 {
        color: white;
        font-size: 16px;
        font-weight: 700;
        margin: 0;
    }

    .sidebar-brand span {
        color: rgba(255, 255, 255, 0.4);
        font-size: 11px;
    }

    .sidebar-nav {
        flex: 1;
        padding: 20px 12px;
        overflow-y: auto;
    }

    .nav-section {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.5px;
        color: rgba(255, 255, 255, 0.25);
        text-transform: uppercase;
        padding: 0 12px;
        margin: 16px 0 8px;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        border-radius: 10px;
        color: rgba(255, 255, 255, 0.55);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        margin-bottom: 2px;
    }

    .sidebar-nav a i {
        font-size: 17px;
        width: 20px;
        text-align: center;
    }

    .sidebar-nav a:hover {
        background: rgba(255, 255, 255, 0.07);
        color: white;
    }

    .sidebar-nav a.active {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(99, 102, 241, 0.1));
        color: #a5b4fc;
        border: 1px solid rgba(99, 102, 241, 0.3);
    }

    .sidebar-nav a.active i {
        color: var(--primary);
    }

    .sidebar-footer {
        padding: 16px 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
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

    /* ===== CONTENT ===== */
    .content {
        margin-left: var(--sidebar-w);
        padding: 32px;
        min-height: 100vh;
    }

    /* ===== TOPBAR ===== */
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
    }

    .topbar h5 {
        font-size: 22px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .topbar p {
        font-size: 13px;
        color: var(--gray);
        margin: 3px 0 0;
    }

    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--gray);
    }

    .breadcrumb-nav a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }

    .breadcrumb-nav a:hover {
        text-decoration: underline;
    }

    /* ===== MAIN LAYOUT ===== */
    .add-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }

    /* ===== FORM CARD ===== */
    .form-card {
        background: white;
        border-radius: 20px;
        padding: 32px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .form-card-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f1f5f9;
    }

    .form-header-icon {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #ede9fe, #ddd6fe);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .form-card-header h4 {
        font-size: 20px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .form-card-header p {
        font-size: 13px;
        color: var(--gray);
        margin: 3px 0 0;
    }

    /* FORM GROUP */
    .form-group {
        margin-bottom: 22px;
    }

    .form-group label {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .form-group label i {
        width: 22px;
        height: 22px;
        background: #ede9fe;
        color: var(--primary);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .form-group label .required {
        color: var(--red);
        margin-left: 2px;
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap input {
        width: 100%;
        padding: 13px 16px 13px 44px;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        color: var(--dark);
        background: #fafbff;
        outline: none;
        transition: all 0.25s;
        font-weight: 500;
    }

    .input-wrap input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
    }

    .input-wrap input::placeholder {
        color: #cbd5e1;
        font-weight: 400;
    }

    .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 17px;
        color: #94a3b8;
        transition: color 0.2s;
        pointer-events: none;
    }

    .input-wrap:focus-within .input-icon {
        color: var(--primary);
    }

    .input-hint {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* PREVIEW LIVE */
    .price-preview {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #f0fdf4;
        color: var(--green);
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        margin-top: 6px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .price-preview.visible {
        opacity: 1;
    }

    /* DIVIDER */
    .form-divider {
        border: none;
        border-top: 2px dashed #e2e8f0;
        margin: 24px 0;
    }

    /* BOUTON */
    .btn-add {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        border: none;
        border-radius: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        position: relative;
        overflow: hidden;
    }

    .btn-add::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), transparent);
        opacity: 0;
        transition: opacity 0.2s;
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.45);
    }

    .btn-add:hover::before {
        opacity: 1;
    }

    .btn-add:active {
        transform: translateY(0);
    }

    .btn-reset {
        width: 100%;
        padding: 13px;
        background: white;
        color: var(--gray);
        border: 2px solid var(--border);
        border-radius: 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-reset:hover {
        border-color: var(--red);
        color: var(--red);
        background: #fef2f2;
    }

    /* ===== SIDE PANEL ===== */
    .side-panel {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* APERÇU PRODUIT */
    .preview-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .preview-card h6 {
        font-size: 13px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .preview-product {
        background: linear-gradient(135deg, #6366f1, #818cf8);
        border-radius: 16px;
        padding: 20px;
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 120px;
    }

    .preview-product::before {
        content: '';
        position: absolute;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        right: -20px;
        top: -20px;
    }

    .preview-product-icon {
        font-size: 28px;
        margin-bottom: 10px;
    }

    .preview-product-name {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 4px;
        color: white;
    }

    .preview-product-name.empty {
        color: rgba(255, 255, 255, 0.4);
        font-style: italic;
    }

    .preview-product-meta {
        display: flex;
        gap: 12px;
        margin-top: 10px;
    }

    .meta-pill {
        background: rgba(255, 255, 255, 0.2);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        backdrop-filter: blur(4px);
    }

    /* TIPS CARD */
    .tips-card {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .tips-card h6 {
        color: rgba(255, 255, 255, 0.5);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        margin-bottom: 16px;
    }

    .tip-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 14px;
    }

    .tip-item:last-child {
        margin-bottom: 0;
    }

    .tip-icon {
        width: 28px;
        height: 28px;
        background: rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        flex-shrink: 0;
        color: #a5b4fc;
    }

    .tip-text {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.6);
        line-height: 1.5;
    }

    .tip-text strong {
        color: rgba(255, 255, 255, 0.9);
        display: block;
        margin-bottom: 1px;
        font-size: 13px;
    }

    /* ALERT */
    .alert-msg {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 24px;
        animation: slideDown 0.3s ease;
    }

    .alert-msg i {
        font-size: 18px;
    }

    .alert-msg.success {
        background: #f0fdf4;
        color: #16a34a;
        border: 1.5px solid #bbf7d0;
    }

    .alert-msg.error {
        background: #fef2f2;
        color: #dc2626;
        border: 1.5px solid #fecaca;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media(max-width: 900px) {
        .add-layout {
            grid-template-columns: 1fr;
        }

        .side-panel {
            display: none;
        }
    }

    @media(max-width: 768px) {
        .sidebar {
            display: none;
        }

        .content {
            margin-left: 0;
            padding: 20px;
        }
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
            <a href="list.php"><i class="bi bi-box-seam"></i> Produits</a>
            <a href="add.php" class="active"><i class="bi bi-plus-circle"></i> Ajouter produit</a>
            <div class="nav-section">Ventes</div>
            <a href="../sales/sell.php"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
            <a href="../sales/list.php"><i class="bi bi-clock-history"></i> Historique</a>
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
                <h5>➕ Ajouter un produit</h5>
                <p>Renseignez les informations du nouveau produit</p>
            </div>
            <div class="breadcrumb-nav">
                <a href="../dashboard.php"><i class="bi bi-house"></i> Accueil</a>
                <i class="bi bi-chevron-right"></i>
                <a href="list.php">Produits</a>
                <i class="bi bi-chevron-right"></i>
                <span>Ajouter</span>
            </div>
        </div>

        <!-- ALERT -->
        <?php if($message === 'success'): ?>
        <div class="alert-msg success" id="alertMsg">
            <i class="bi bi-check-circle-fill"></i>
            Produit ajouté avec succès ! Il est maintenant disponible dans votre stock.
        </div>
        <?php elseif($message === 'error'): ?>
        <div class="alert-msg error" id="alertMsg">
            <i class="bi bi-x-circle-fill"></i>
            Veuillez remplir tous les champs correctement (quantité et prix &gt; 0).
        </div>
        <?php endif; ?>

        <!-- LAYOUT -->
        <div class="add-layout">

            <!-- FORMULAIRE -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-header-icon">📦</div>
                    <div>
                        <h4>Nouveau produit</h4>
                        <p>Remplissez les champs ci-dessous</p>
                    </div>
                </div>

                <form method="POST" id="addForm">

                    <!-- NOM -->
                    <div class="form-group">
                        <label>
                            <i class="bi bi-tag"></i>
                            Nom du produit <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-box-seam input-icon"></i>
                            <input type="text" name="name" id="inputName"
                                placeholder="Ex: Chaussure Nike, Parfum Rose..." autocomplete="off" required>
                        </div>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i>
                            Donnez un nom clair et descriptif
                        </div>
                    </div>

                    <hr class="form-divider">

                    <!-- QUANTITÉ -->
                    <div class="form-group">
                        <label>
                            <i class="bi bi-hash"></i>
                            Quantité en stock <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-stack input-icon"></i>
                            <input type="number" name="quantity" id="inputQty" placeholder="Ex: 50" min="1" required>
                        </div>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i>
                            Nombre d'unités disponibles en stock
                        </div>
                    </div>

                    <!-- PRIX -->
                    <div class="form-group">
                        <label>
                            <i class="bi bi-currency-exchange"></i>
                            Prix unitaire <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-cash input-icon"></i>
                            <input type="number" name="price" id="inputPrice" placeholder="Ex: 5000" min="1" required>
                        </div>
                        <div class="price-preview" id="pricePreview">
                            <i class="bi bi-check-circle-fill"></i>
                            <span id="pricePreviewText"></span>
                        </div>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i>
                            Prix en Francs CFA (FCFA)
                        </div>
                    </div>

                    <hr class="form-divider">

                    <button type="submit" name="add" class="btn-add">
                        <i class="bi bi-plus-circle-fill"></i>
                        Ajouter le produit
                    </button>

                    <button type="reset" class="btn-reset" onclick="resetPreview()">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Réinitialiser
                    </button>

                </form>
            </div>

            <!-- PANNEAU LATÉRAL -->
            <div class="side-panel">

                <!-- APERÇU EN DIRECT -->
                <div class="preview-card">
                    <h6><i class="bi bi-eye"></i> Aperçu en direct</h6>
                    <div class="preview-product">
                        <div class="preview-product-icon">📦</div>
                        <div class="preview-product-name empty" id="previewName">
                            Nom du produit...
                        </div>
                        <div class="preview-product-meta">
                            <div class="meta-pill" id="previewQty">Qté: —</div>
                            <div class="meta-pill" id="previewPrice">Prix: —</div>
                        </div>
                    </div>
                </div>

                <!-- CONSEILS -->
                <div class="tips-card">
                    <h6>💡 Conseils</h6>
                    <div class="tip-item">
                        <div class="tip-icon"><i class="bi bi-tag"></i></div>
                        <div class="tip-text">
                            <strong>Nom du produit</strong>
                            Soyez précis : marque, type, taille si nécessaire.
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon"><i class="bi bi-stack"></i></div>
                        <div class="tip-text">
                            <strong>Quantité</strong>
                            Entrez le stock initial disponible à la vente.
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon"><i class="bi bi-cash"></i></div>
                        <div class="tip-text">
                            <strong>Prix unitaire</strong>
                            Prix de vente en FCFA par unité.
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script>
    const inputName = document.getElementById('inputName');
    const inputQty = document.getElementById('inputQty');
    const inputPrice = document.getElementById('inputPrice');

    const previewName = document.getElementById('previewName');
    const previewQty = document.getElementById('previewQty');
    const previewPrice = document.getElementById('previewPrice');
    const pricePreview = document.getElementById('pricePreview');
    const pricePreviewText = document.getElementById('pricePreviewText');

    // Mise à jour aperçu nom
    inputName.addEventListener('input', function() {
        if (this.value.trim()) {
            previewName.textContent = this.value;
            previewName.classList.remove('empty');
        } else {
            previewName.textContent = 'Nom du produit...';
            previewName.classList.add('empty');
        }
    });

    // Mise à jour aperçu quantité
    inputQty.addEventListener('input', function() {
        previewQty.textContent = this.value ? 'Qté: ' + this.value : 'Qté: —';
    });

    // Mise à jour aperçu prix
    inputPrice.addEventListener('input', function() {
        if (this.value > 0) {
            const formatted = parseInt(this.value).toLocaleString('fr-FR');
            previewPrice.textContent = 'Prix: ' + formatted + ' F';
            pricePreviewText.textContent = formatted + ' FCFA';
            pricePreview.classList.add('visible');
        } else {
            previewPrice.textContent = 'Prix: —';
            pricePreview.classList.remove('visible');
        }
    });

    function resetPreview() {
        previewName.textContent = 'Nom du produit...';
        previewName.classList.add('empty');
        previewQty.textContent = 'Qté: —';
        previewPrice.textContent = 'Prix: —';
        pricePreview.classList.remove('visible');
    }

    // Disparition alertes après 10s
    setTimeout(function() {
        const alert = document.getElementById('alertMsg');
        if (alert) {
            alert.style.transition = 'opacity 0.8s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 800);
        }
    }, 10000);
    </script>

</body>

</html>