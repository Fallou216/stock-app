<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');
requireLogin();

$currentUser = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();
$id          = intval($_GET['id']);
$message     = '';
$msgType     = '';

// Récupérer le produit
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$data){
    header("Location: list.php");
    exit();
}

// Mise à jour
if(isset($_POST['update'])){
    $name           = trim($_POST['name']);
    $quantity       = intval($_POST['quantity']);
    $price          = floatval($_POST['price']);
    $purchase_price = floatval($_POST['purchase_price']);

    if(!empty($name) && $quantity >= 0 && $price > 0){
        $upd = $conn->prepare("
            UPDATE products
            SET name=?, quantity=?, purchase_price=?, price=?
            WHERE id=?
        ");
        $upd->bind_param("siddi", $name, $quantity, $purchase_price, $price, $id);

        if($upd->execute()){
            $message = "Produit mis à jour avec succès !";
            $msgType = "success";
            $data['name']           = $name;
            $data['quantity']       = $quantity;
            $data['purchase_price'] = $purchase_price;
            $data['price']          = $price;
        } else {
            $message = "Erreur lors de la mise à jour.";
            $msgType = "error";
        }
        $upd->close();
    } else {
        $message = "Veuillez remplir tous les champs correctement.";
        $msgType = "error";
    }
}

// Compteur notifications
$bellCount = 0;
$bellRes   = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='stock_alert' AND is_read=0");
if($bellRes) $bellCount = $bellRes->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier — <?= htmlspecialchars($data['name']) ?></title>
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
        --gray: #64748b;
        --border: #e2e8f0;
        --primary: #6366f1;
        --green: #10b981;
        --red: #ef4444;
        --orange: #f59e0b;
        --blue: #3b82f6;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f0f4ff;
        min-height: 100vh;
    }

    /* SIDEBAR */
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
        padding: 20px 24px 14px;
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

    .sidebar-profile {
        margin: 0 12px 14px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.05));
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 14px;
        padding: 12px 14px;
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

    .sidebar-nav {
        flex: 1;
        padding: 8px 12px;
        overflow-y: auto;
    }

    .nav-section {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.5px;
        color: rgba(255, 255, 255, 0.25);
        text-transform: uppercase;
        padding: 0 12px;
        margin: 14px 0 6px;
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
        padding: 12px;
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

    /* CONTENT */
    .content {
        margin-left: var(--sidebar-w);
        padding: 32px;
        min-height: 100vh;
    }

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

    /* LAYOUT */
    .edit-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }

    /* FORM CARD */
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
        background: linear-gradient(135deg, #fef3c7, #fde68a);
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

    .product-id-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #f1f5f9;
        color: var(--gray);
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        margin-top: 5px;
    }

    /* SECTION LABEL */
    .section-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0 0 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-group {
        margin-bottom: 20px;
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
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .label-purple {
        background: #ede9fe;
        color: var(--primary);
    }

    .label-green {
        background: #d1fae5;
        color: var(--green);
    }

    .label-blue {
        background: #dbeafe;
        color: var(--blue);
    }

    .label-orange {
        background: #fef3c7;
        color: var(--orange);
    }

    .required {
        color: var(--red);
        margin-left: 2px;
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap input {
        width: 100%;
        padding: 12px 16px 12px 44px;
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
        font-size: 16px;
        color: #94a3b8;
        pointer-events: none;
        transition: color 0.2s;
    }

    .input-wrap:focus-within .input-icon {
        color: var(--primary);
    }

    .input-hint {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* BÉNÉFICE BOX */
    .benefice-box {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border: 1.5px solid #bbf7d0;
        border-radius: 12px;
        padding: 14px 16px;
        margin: 16px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .benefice-box.negative {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-color: #fecaca;
    }

    .benefice-label {
        font-size: 12px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .benefice-pct {
        font-size: 12px;
        color: var(--green);
        font-weight: 600;
    }

    .benefice-pct.negative {
        color: var(--red);
    }

    .benefice-value {
        font-size: 20px;
        font-weight: 800;
        color: var(--green);
    }

    .benefice-value.negative {
        color: var(--red);
    }

    .form-divider {
        border: none;
        border-top: 2px dashed #e2e8f0;
        margin: 24px 0;
    }

    .btn-update {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
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
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-update:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.45);
    }

    .btn-update:active {
        transform: translateY(0);
    }

    .btn-cancel {
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
        text-decoration: none;
    }

    .btn-cancel:hover {
        border-color: var(--red);
        color: var(--red);
        background: #fef2f2;
    }

    /* SIDE PANEL */
    .side-panel {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* APERÇU */
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
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border-radius: 16px;
        padding: 20px;
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 130px;
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

    .preview-product-meta {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }

    .meta-pill {
        background: rgba(255, 255, 255, 0.25);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    /* ANALYSE */
    .benefice-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .benefice-card h6 {
        font-size: 13px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .b-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .b-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .b-label {
        color: var(--gray);
        font-weight: 500;
    }

    .b-val {
        font-weight: 700;
        color: var(--dark);
    }

    .b-val.profit {
        color: var(--green);
    }

    .b-val.loss {
        color: var(--red);
    }

    .big-box {
        text-align: center;
        padding: 16px;
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border-radius: 12px;
        margin-top: 14px;
        border: 1.5px solid #bbf7d0;
    }

    .big-box.loss {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-color: #fecaca;
    }

    .big-box-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .big-box-val {
        font-size: 26px;
        font-weight: 800;
        color: var(--green);
        margin-top: 4px;
    }

    .big-box-val.loss {
        color: var(--red);
    }

    /* HISTORIQUE PRIX */
    .history-card {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        border-radius: 20px;
        padding: 24px;
    }

    .history-card h6 {
        color: rgba(255, 255, 255, 0.5);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        margin-bottom: 16px;
    }

    .history-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 12px;
    }

    .history-row:last-child {
        border-bottom: none;
    }

    .history-label {
        color: rgba(255, 255, 255, 0.5);
    }

    .history-val {
        color: rgba(255, 255, 255, 0.85);
        font-weight: 600;
    }

    /* ALERTS */
    .alert-msg {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-radius: 14px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 24px;
        animation: slideDown 0.3s ease;
        line-height: 1.5;
    }

    .alert-msg i {
        font-size: 22px;
        flex-shrink: 0;
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

    @media(max-width:900px) {
        .edit-layout {
            grid-template-columns: 1fr;
        }

        .side-panel {
            display: none;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width:768px) {
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
        <div class="sidebar-profile">
            <?php if(!empty($currentUser['photo'])): ?>
            <img src="../uploads/<?= htmlspecialchars($currentUser['photo']) ?>" alt="">
            <?php else: ?>
            <div class="sidebar-avatar-placeholder"><?= strtoupper(substr($_SESSION['user'],0,1)) ?></div>
            <?php endif; ?>
            <div style="overflow:hidden;">
                <div class="sidebar-profile-name"><?= htmlspecialchars($_SESSION['user']) ?></div>
                <?php if(isAdmin()): ?>
                <div class="sidebar-profile-role role-admin">👑 Administrateur</div>
                <?php else: ?>
                <div class="sidebar-profile-role role-employee">👤 Employé</div>
                <?php endif; ?>
            </div>
        </div>
        <nav class="sidebar-nav">
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
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">
        <div class="topbar">
            <div>
                <h5>✏️ Modifier le produit</h5>
                <p>Mettez à jour les informations du produit</p>
            </div>
            <div class="breadcrumb-nav">
                <a href="../dashboard.php"><i class="bi bi-house"></i> Accueil</a>
                <i class="bi bi-chevron-right"></i>
                <a href="list.php">Produits</a>
                <i class="bi bi-chevron-right"></i>
                <span>Modifier</span>
            </div>
        </div>

        <!-- ALERT -->
        <?php if($message): ?>
        <div class="alert-msg <?= $msgType ?>" id="alertMsg">
            <i class="bi bi-<?= $msgType==='success' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
            <span><?= $message ?></span>
        </div>
        <?php endif; ?>

        <!-- LAYOUT -->
        <div class="edit-layout">

            <!-- FORMULAIRE -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-header-icon">✏️</div>
                    <div>
                        <h4><?= htmlspecialchars($data['name']) ?></h4>
                        <p>Modifiez les informations ci-dessous</p>
                        <span class="product-id-badge"><i class="bi bi-hash"></i> Produit #<?= $id ?></span>
                    </div>
                </div>

                <form method="POST">

                    <!-- INFOS PRODUIT -->
                    <div class="section-label">📦 Informations produit</div>

                    <div class="form-group">
                        <label>
                            <i class="bi bi-tag label-purple"></i>
                            Nom du produit <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-box-seam input-icon"></i>
                            <input type="text" name="name" id="inputName" value="<?= htmlspecialchars($data['name']) ?>"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="bi bi-hash label-purple"></i>
                            Quantité en stock <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-stack input-icon"></i>
                            <input type="number" name="quantity" id="inputQty" value="<?= intval($data['quantity']) ?>"
                                min="0" required>
                        </div>
                        <div class="input-hint"><i class="bi bi-info-circle"></i> Quantité actuelle en stock</div>
                    </div>

                    <hr class="form-divider">

                    <!-- PRIX -->
                    <div class="section-label">💰 Prix & Bénéfice</div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="bi bi-bag label-blue"></i>
                                Prix d'achat (FCFA)
                            </label>
                            <div class="input-wrap">
                                <i class="bi bi-bag input-icon"></i>
                                <input type="number" name="purchase_price" id="inputPurchasePrice"
                                    value="<?= floatval($data['purchase_price'] ?? 0) ?>" min="0" step="1"
                                    placeholder="Ex: 3000">
                            </div>
                            <div class="input-hint"><i class="bi bi-info-circle"></i> Prix fournisseur</div>
                        </div>
                        <div class="form-group">
                            <label>
                                <i class="bi bi-tag-fill label-orange"></i>
                                Prix de vente (FCFA) <span class="required">*</span>
                            </label>
                            <div class="input-wrap">
                                <i class="bi bi-cash input-icon"></i>
                                <input type="number" name="price" id="inputPrice"
                                    value="<?= floatval($data['price']) ?>" min="1" step="1" required>
                            </div>
                            <div class="input-hint"><i class="bi bi-info-circle"></i> Prix client</div>
                        </div>
                    </div>

                    <!-- BÉNÉFICE LIVE -->
                    <div class="benefice-box" id="beneficeBox">
                        <div>
                            <div class="benefice-label">💹 Bénéfice par unité</div>
                            <div class="benefice-pct" id="beneficePct">—</div>
                        </div>
                        <div class="benefice-value" id="beneficeVal">— FCFA</div>
                    </div>

                    <hr class="form-divider">

                    <button type="submit" name="update" class="btn-update">
                        <i class="bi bi-check-circle-fill"></i>
                        Enregistrer les modifications
                    </button>
                    <a href="list.php" class="btn-cancel">
                        <i class="bi bi-arrow-left"></i>
                        Annuler
                    </a>
                </form>
            </div>

            <!-- PANNEAU LATÉRAL -->
            <div class="side-panel">

                <!-- APERÇU -->
                <div class="preview-card">
                    <h6><i class="bi bi-eye"></i> Aperçu en direct</h6>
                    <div class="preview-product">
                        <div class="preview-product-icon">📦</div>
                        <div class="preview-product-name" id="previewName">
                            <?= htmlspecialchars($data['name']) ?>
                        </div>
                        <div class="preview-product-meta">
                            <div class="meta-pill" id="previewQty">Qté: <?= intval($data['quantity']) ?></div>
                            <div class="meta-pill" id="previewBuy">Achat:
                                <?= number_format($data['purchase_price'] ?? 0, 0, '', ' ') ?> F</div>
                            <div class="meta-pill" id="previewSell">Vente:
                                <?= number_format($data['price'], 0, '', ' ') ?> F</div>
                        </div>
                    </div>
                </div>

                <!-- ANALYSE RENTABILITÉ -->
                <div class="benefice-card">
                    <h6><i class="bi bi-graph-up-arrow"></i> Analyse rentabilité</h6>
                    <div class="b-row">
                        <span class="b-label">Prix d'achat</span>
                        <span class="b-val" id="sideBuy"><?= number_format($data['purchase_price'] ?? 0, 0, '', ' ') ?>
                            FCFA</span>
                    </div>
                    <div class="b-row">
                        <span class="b-label">Prix de vente</span>
                        <span class="b-val" id="sideSell"><?= number_format($data['price'], 0, '', ' ') ?> FCFA</span>
                    </div>
                    <div class="b-row">
                        <span class="b-label">Marge %</span>
                        <span class="b-val" id="sideMargin">
                            <?php
                        $pp = floatval($data['purchase_price'] ?? 0);
                        $pv = floatval($data['price']);
                        echo $pp > 0 ? number_format((($pv - $pp) / $pp) * 100, 1) . ' %' : '— %';
                        ?>
                        </span>
                    </div>
                    <div class="b-row">
                        <span class="b-label">Bénéfice / unité</span>
                        <span class="b-val <?= ($pv - $pp) >= 0 ? 'profit' : 'loss' ?>" id="sideProfit">
                            <?= number_format($pv - $pp, 0, '', ' ') ?> FCFA
                        </span>
                    </div>
                    <div class="big-box <?= ($pv - $pp) < 0 ? 'loss' : '' ?>" id="bigBox">
                        <div class="big-box-label">Bénéfice total stock</div>
                        <div class="big-box-val <?= ($pv - $pp) < 0 ? 'loss' : '' ?>" id="bigBoxVal">
                            <?= number_format(($pv - $pp) * intval($data['quantity']), 0, '', ' ') ?> FCFA
                        </div>
                    </div>
                </div>

                <!-- VALEURS INITIALES -->
                <div class="history-card">
                    <h6>📋 Valeurs actuelles en BDD</h6>
                    <div class="history-row">
                        <span class="history-label">Prix d'achat</span>
                        <span class="history-val"><?= number_format($data['purchase_price'] ?? 0, 0, '', ' ') ?>
                            FCFA</span>
                    </div>
                    <div class="history-row">
                        <span class="history-label">Prix de vente</span>
                        <span class="history-val"><?= number_format($data['price'], 0, '', ' ') ?> FCFA</span>
                    </div>
                    <div class="history-row">
                        <span class="history-label">Quantité</span>
                        <span class="history-val"><?= intval($data['quantity']) ?> unité(s)</span>
                    </div>
                    <div class="history-row">
                        <span class="history-label">Stock valorisé</span>
                        <span
                            class="history-val"><?= number_format(floatval($data['price']) * intval($data['quantity']), 0, '', ' ') ?>
                            FCFA</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
    const inputName = document.getElementById('inputName');
    const inputQty = document.getElementById('inputQty');
    const inputPrice = document.getElementById('inputPrice');
    const inputPurchasePrice = document.getElementById('inputPurchasePrice');
    const previewName = document.getElementById('previewName');
    const previewQty = document.getElementById('previewQty');
    const previewBuy = document.getElementById('previewBuy');
    const previewSell = document.getElementById('previewSell');
    const beneficeBox = document.getElementById('beneficeBox');
    const beneficeVal = document.getElementById('beneficeVal');
    const beneficePct = document.getElementById('beneficePct');
    const sideBuy = document.getElementById('sideBuy');
    const sideSell = document.getElementById('sideSell');
    const sideMargin = document.getElementById('sideMargin');
    const sideProfit = document.getElementById('sideProfit');
    const bigBox = document.getElementById('bigBox');
    const bigBoxVal = document.getElementById('bigBoxVal');

    function fmt(n) {
        return Math.round(n).toLocaleString('fr-FR');
    }

    function updateAll() {
        const name = inputName.value.trim();
        const qty = parseInt(inputQty.value) || 0;
        const buy = parseFloat(inputPurchasePrice.value) || 0;
        const sell = parseFloat(inputPrice.value) || 0;
        const profit = sell - buy;
        const total = profit * qty;
        const margin = buy > 0 ? ((profit / buy) * 100) : 0;
        const isLoss = profit < 0;

        // Aperçu
        previewName.textContent = name || '—';
        previewQty.textContent = 'Qté: ' + qty;
        previewBuy.textContent = buy > 0 ? 'Achat: ' + fmt(buy) + ' F' : 'Achat: —';
        previewSell.textContent = sell > 0 ? 'Vente: ' + fmt(sell) + ' F' : 'Vente: —';

        // Bénéfice inline
        beneficeBox.className = 'benefice-box' + (isLoss ? ' negative' : '');
        beneficeVal.className = 'benefice-value' + (isLoss ? ' negative' : '');
        beneficePct.className = 'benefice-pct' + (isLoss ? ' negative' : '');
        beneficeVal.textContent = sell > 0 ? (isLoss ? '−' : '+') + fmt(Math.abs(profit)) + ' FCFA' : '— FCFA';
        beneficePct.textContent = sell > 0 && buy > 0 ? 'Marge : ' + margin.toFixed(1) + '%' : (sell > 0 ?
            'Prix achat non renseigné' : '—');

        // Panneau
        sideBuy.textContent = buy > 0 ? fmt(buy) + ' FCFA' : '— FCFA';
        sideSell.textContent = sell > 0 ? fmt(sell) + ' FCFA' : '— FCFA';
        sideMargin.textContent = (buy > 0 && sell > 0) ? margin.toFixed(1) + ' %' : '— %';
        sideProfit.className = 'b-val ' + (isLoss ? 'loss' : 'profit');
        sideProfit.textContent = sell > 0 ? (isLoss ? '−' : '+') + fmt(Math.abs(profit)) + ' FCFA' : '— FCFA';
        bigBox.className = 'big-box' + (isLoss ? ' loss' : '');
        bigBoxVal.className = 'big-box-val' + (isLoss ? ' loss' : '');
        bigBoxVal.textContent = sell > 0 ? (isLoss ? '−' : '+') + fmt(Math.abs(total)) + ' FCFA' : '— FCFA';
    }

    [inputName, inputQty, inputPrice, inputPurchasePrice].forEach(el => {
        el.addEventListener('input', updateAll);
    });

    // Alerte auto-disparaît
    setTimeout(function() {
        const a = document.getElementById('alertMsg');
        if (a) {
            a.style.transition = 'opacity 0.8s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 800);
        }
    }, 8000);

    // Init
    updateAll();
    </script>
</body>

</html>