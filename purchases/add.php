<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');
requireLogin();

$currentUser  = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();
$message      = '';
$msgType      = '';
$existing_qty = 0;

if(isset($_POST['add'])){
    $product_name   = trim($_POST['product_name']);
    $qty            = intval($_POST['quantity']);
    $unit_price     = floatval($_POST['unit_price']);
    $sell_price     = floatval($_POST['sell_price']);
    $supplier_name  = trim($_POST['supplier_name']);
    $supplier_phone = trim($_POST['supplier_phone']);

    if(!empty($product_name) && $qty > 0 && $unit_price > 0 && $sell_price > 0 && !empty($supplier_name)){

        // ── Gestion fournisseur ─────────────────────────────
        $stmtSup = $conn->prepare("SELECT * FROM suppliers WHERE LOWER(name) = LOWER(?)");
        $stmtSup->bind_param("s", $supplier_name);
        $stmtSup->execute();
        $supRes = $stmtSup->get_result();
        $stmtSup->close();

        if($supRes->num_rows > 0){
            $supplier    = $supRes->fetch_assoc();
            $supplier_id = $supplier['id'];
            if(!empty($supplier_phone)){
                $stmtUpSup = $conn->prepare("UPDATE suppliers SET phone=? WHERE id=?");
                $stmtUpSup->bind_param("si", $supplier_phone, $supplier_id);
                $stmtUpSup->execute();
                $stmtUpSup->close();
            }
        } else {
            $stmtInsSup = $conn->prepare("INSERT INTO suppliers(name, phone) VALUES(?, ?)");
            $stmtInsSup->bind_param("ss", $supplier_name, $supplier_phone);
            $stmtInsSup->execute();
            $supplier_id = $conn->insert_id;
            $stmtInsSup->close();
        }

        // ── Gestion produit ─────────────────────────────────
        $stmtProd = $conn->prepare("SELECT * FROM products WHERE LOWER(name) = LOWER(?)");
        $stmtProd->bind_param("s", $product_name);
        $stmtProd->execute();
        $prodRes = $stmtProd->get_result();
        $stmtProd->close();

        if($prodRes->num_rows > 0){
            // Produit existe → incrémenter + mettre à jour les prix
            $product      = $prodRes->fetch_assoc();
            $product_id   = $product['id'];
            $existing_qty = $product['quantity'] + $qty;

            $stmtUpProd = $conn->prepare("
                UPDATE products
                SET quantity       = quantity + ?,
                    purchase_price = ?,
                    price          = ?
                WHERE id = ?
            ");
            $stmtUpProd->bind_param("iddi", $qty, $unit_price, $sell_price, $product_id);
            $stmtUpProd->execute();
            $stmtUpProd->close();
            $msgType = "incremented";

        } else {
            // Nouveau produit → créer avec les deux prix
            $stmtInsProd = $conn->prepare("
                INSERT INTO products(name, quantity, purchase_price, price)
                VALUES(?, ?, ?, ?)
            ");
            $stmtInsProd->bind_param("sidd", $product_name, $qty, $unit_price, $sell_price);
            $stmtInsProd->execute();
            $product_id   = $conn->insert_id;
            $existing_qty = $qty;
            $stmtInsProd->close();
            $msgType = "new_product";
        }

        // ── Enregistrer l'achat ──────────────────────────────
        $stmtIns = $conn->prepare("
            INSERT INTO purchases(product_id, supplier_id, quantity, unit_price)
            VALUES(?, ?, ?, ?)
        ");
        $stmtIns->bind_param("iiid", $product_id, $supplier_id, $qty, $unit_price);
        $stmtIns->execute();
        $stmtIns->close();

        $message = $msgType;

    } else {
        $message = "error";
        $msgType = "error";
    }
}

// Stats rapides
$totalAchats       = $conn->query("SELECT COUNT(*) AS c FROM purchases")->fetch_assoc()['c'];
$totalDepense      = $conn->query("SELECT SUM(quantity * unit_price) AS t FROM purchases")->fetch_assoc()['t'] ?? 0;
$totalFournisseurs = $conn->query("SELECT COUNT(*) AS c FROM suppliers")->fetch_assoc()['c'];

// Compteur notifications
$bellCount = 0;
$bellRes   = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='stock_alert' AND is_read=0");
if($bellRes) $bellCount = $bellRes->fetch_assoc()['c'];

// Derniers achats
$lastPurchases = $conn->query("
    SELECT p.*, pr.name AS product_name, s.name AS supplier_name, s.phone AS supplier_phone
    FROM purchases p
    JOIN products  pr ON p.product_id  = pr.id
    JOIN suppliers s  ON p.supplier_id = s.id
    ORDER BY p.purchased_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achats — Stock App</title>
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
        margin-bottom: 28px;
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

    /* MINI STATS */
    .mini-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 28px;
    }

    .mini-stat {
        background: white;
        border-radius: 16px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        transition: transform 0.2s;
    }

    .mini-stat:hover {
        transform: translateY(-3px);
    }

    .mini-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .mini-stat-icon.blue {
        background: #dbeafe;
        color: var(--blue);
    }

    .mini-stat-icon.green {
        background: #d1fae5;
        color: var(--green);
    }

    .mini-stat-icon.orange {
        background: #fef3c7;
        color: var(--orange);
    }

    .mini-stat-value {
        font-size: 20px;
        font-weight: 800;
        color: var(--dark);
        line-height: 1;
    }

    .mini-stat-label {
        font-size: 12px;
        color: var(--gray);
        margin-top: 3px;
        font-weight: 500;
    }

    /* LAYOUT */
    .purchase-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }

    /* FORM */
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
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
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

    .section-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 24px 0 20px;
    }

    .section-divider span {
        font-size: 12px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 1px;
        white-space: nowrap;
    }

    .section-divider::before,
    .section-divider::after {
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
        margin-bottom: 18px;
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

    .label-blue {
        background: #dbeafe;
        color: var(--blue);
    }

    .label-orange {
        background: #fef3c7;
        color: var(--orange);
    }

    .label-green {
        background: #d1fae5;
        color: var(--green);
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

    /* BÉNÉFICE LIVE */
    .profit-live {
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border: 1.5px solid #bbf7d0;
        transition: all 0.3s;
    }

    .profit-live.neg {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-color: #fecaca;
    }

    .profit-live-left {}

    .profit-live-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .profit-live-margin {
        font-size: 11px;
        color: var(--green);
        margin-top: 2px;
        font-weight: 600;
    }

    .profit-live-margin.neg {
        color: var(--red);
    }

    .profit-live-value {
        font-size: 22px;
        font-weight: 800;
        color: var(--green);
    }

    .profit-live-value.neg {
        color: var(--red);
    }

    .total-preview {
        background: linear-gradient(135deg, #dbeafe, #ede9fe);
        border-radius: 12px;
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
    }

    .total-preview-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--gray);
    }

    .total-preview-value {
        font-size: 20px;
        font-weight: 800;
        color: var(--primary);
    }

    .btn-purchase {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
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
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .btn-purchase:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.45);
    }

    .btn-purchase:active {
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

    /* SIDE PANEL */
    .side-panel {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .recap-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .recap-card h6 {
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

    .recap-box {
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        border-radius: 16px;
        padding: 20px;
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 130px;
    }

    .recap-box::before {
        content: '';
        position: absolute;
        width: 120px;
        height: 120px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        right: -30px;
        top: -30px;
    }

    .recap-box-icon {
        font-size: 26px;
        margin-bottom: 10px;
    }

    .recap-box-product {
        font-size: 15px;
        font-weight: 700;
        color: white;
        margin-bottom: 4px;
    }

    .recap-box-product.empty {
        color: rgba(255, 255, 255, 0.4);
        font-style: italic;
    }

    .recap-box-supplier {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 8px;
    }

    .recap-pills {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .recap-pill {
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .recap-total-box {
        margin-top: 14px;
        padding: 14px;
        background: #eff6ff;
        border-radius: 12px;
        border: 1.5px solid #bfdbfe;
        text-align: center;
    }

    .recap-total-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .recap-total-value {
        font-size: 24px;
        font-weight: 800;
        color: var(--blue);
        margin-top: 4px;
    }

    /* ANALYSE */
    .analyse-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .analyse-card h6 {
        font-size: 13px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .analyse-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 9px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .analyse-row:last-child {
        border-bottom: none;
    }

    .analyse-label {
        color: var(--gray);
        font-weight: 500;
    }

    .analyse-val {
        font-weight: 700;
        color: var(--dark);
    }

    .analyse-val.green {
        color: var(--green);
    }

    .analyse-val.red {
        color: var(--red);
    }

    .big-profit {
        text-align: center;
        padding: 14px;
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border-radius: 12px;
        margin-top: 12px;
        border: 1.5px solid #bbf7d0;
    }

    .big-profit.loss {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-color: #fecaca;
    }

    .big-profit-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .big-profit-value {
        font-size: 22px;
        font-weight: 800;
        color: var(--green);
        margin-top: 4px;
    }

    .big-profit-value.loss {
        color: var(--red);
    }

    /* DERNIERS ACHATS */
    .last-card {
        background: var(--dark);
        border-radius: 20px;
        padding: 24px;
    }

    .last-card h6 {
        color: rgba(255, 255, 255, 0.5);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        margin-bottom: 16px;
    }

    .purchase-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .purchase-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .purchase-item-name {
        font-size: 13px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.85);
    }

    .purchase-item-supplier {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.35);
        margin-top: 2px;
    }

    .purchase-item-amount {
        font-size: 13px;
        font-weight: 700;
        color: #93c5fd;
        white-space: nowrap;
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

    .alert-msg.incremented {
        background: #eff6ff;
        color: #2563eb;
        border: 1.5px solid #bfdbfe;
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
        .purchase-layout {
            grid-template-columns: 1fr;
        }

        .side-panel {
            display: none;
        }

        .mini-stats {
            grid-template-columns: 1fr;
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
            <a href="../products/list.php"><i class="bi bi-box-seam"></i> Produits</a>
            <a href="../products/add.php"><i class="bi bi-plus-circle"></i> Ajouter produit</a>
            <div class="nav-section">Achats</div>
            <a href="add.php" class="active"><i class="bi bi-bag-plus"></i> Nouvel achat</a>
            <a href="list.php"><i class="bi bi-clock-history"></i> Historique achats</a>
            <a href="suppliers.php"><i class="bi bi-building"></i> Fournisseurs</a>
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
                <h5>🛍️ Nouvel Achat</h5>
                <p>Enregistrez un achat — stock, prix d'achat et de vente mis à jour automatiquement</p>
            </div>
            <div class="breadcrumb-nav">
                <a href="../dashboard.php"><i class="bi bi-house"></i> Accueil</a>
                <i class="bi bi-chevron-right"></i>
                <a href="list.php">Achats</a>
                <i class="bi bi-chevron-right"></i>
                <span>Nouvel achat</span>
            </div>
        </div>

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
                    <div class="mini-stat-value" style="font-size:15px;"><?= number_format($totalDepense,0,'',' ') ?> F
                    </div>
                    <div class="mini-stat-label">Total dépensé (FCFA)</div>
                </div>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if($message === 'incremented'): ?>
        <div class="alert-msg incremented" id="alertMsg">
            <i class="bi bi-arrow-up-circle-fill"></i>
            <span>
                Achat enregistré ! Stock incrémenté, prix d'achat et de vente mis à jour.<br>
                <strong>Nouveau stock : <?= $existing_qty ?> unité(s)</strong>
            </span>
        </div>
        <?php elseif($message === 'new_product'): ?>
        <div class="alert-msg success" id="alertMsg">
            <i class="bi bi-check-circle-fill"></i>
            <span>
                Achat enregistré ! Nouveau produit créé avec <strong><?= $existing_qty ?> unité(s)</strong>,
                prix d'achat et de vente enregistrés.
            </span>
        </div>
        <?php elseif($message === 'error'): ?>
        <div class="alert-msg error" id="alertMsg">
            <i class="bi bi-x-circle-fill"></i>
            <span>Veuillez remplir tous les champs obligatoires correctement.</span>
        </div>
        <?php endif; ?>

        <!-- LAYOUT -->
        <div class="purchase-layout">

            <!-- FORMULAIRE -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-header-icon">🛍️</div>
                    <div>
                        <h4>Enregistrer un achat</h4>
                        <p>Produit, quantité, prix d'achat, prix de vente et fournisseur</p>
                    </div>
                </div>

                <form method="POST" id="purchaseForm">

                    <!-- SECTION PRODUIT -->
                    <div class="section-divider"><span>📦 Informations produit</span></div>

                    <div class="form-group">
                        <label>
                            <i class="bi bi-box-seam label-purple"></i>
                            Nom du produit <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-box-seam input-icon"></i>
                            <input type="text" name="product_name" id="inputProduct"
                                placeholder="Ex: Parfum Rose, Chaussure Nike..." autocomplete="off" required>
                        </div>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i>
                            Si le produit existe, son stock et ses prix seront mis à jour
                        </div>
                    </div>

                    <!-- QUANTITÉ -->
                    <div class="form-group">
                        <label>
                            <i class="bi bi-hash label-purple"></i>
                            Quantité achetée <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-stack input-icon"></i>
                            <input type="number" name="quantity" id="inputQty" placeholder="Ex: 50" min="1" required>
                        </div>
                    </div>

                    <!-- SECTION PRIX -->
                    <div class="section-divider"><span>💰 Prix d'achat & de vente</span></div>

                    <div class="form-row">
                        <!-- PRIX D'ACHAT -->
                        <div class="form-group">
                            <label>
                                <i class="bi bi-bag label-blue"></i>
                                Prix d'achat (FCFA) <span class="required">*</span>
                            </label>
                            <div class="input-wrap">
                                <i class="bi bi-bag input-icon"></i>
                                <input type="number" name="unit_price" id="inputPrice" placeholder="Ex: 3000" min="1"
                                    required>
                            </div>
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i>
                                Ce que vous payez au fournisseur
                            </div>
                        </div>

                        <!-- PRIX DE VENTE -->
                        <div class="form-group">
                            <label>
                                <i class="bi bi-tag-fill label-orange"></i>
                                Prix de vente (FCFA) <span class="required">*</span>
                            </label>
                            <div class="input-wrap">
                                <i class="bi bi-tag input-icon"></i>
                                <input type="number" name="sell_price" id="inputSellPrice" placeholder="Ex: 5000"
                                    min="1" required>
                            </div>
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i>
                                Ce que le client va payer
                            </div>
                        </div>
                    </div>

                    <!-- BÉNÉFICE EN DIRECT -->
                    <div class="profit-live" id="profitLive">
                        <div class="profit-live-left">
                            <div class="profit-live-label">💹 Bénéfice par unité</div>
                            <div class="profit-live-margin" id="profitMargin">Entrez les deux prix</div>
                        </div>
                        <div class="profit-live-value" id="profitPerUnit">— FCFA</div>
                    </div>

                    <!-- TOTAL EN DIRECT -->
                    <div class="total-preview" style="margin-top:12px;">
                        <span class="total-preview-label">
                            <i class="bi bi-calculator"></i> Total achat estimé
                        </span>
                        <span class="total-preview-value" id="totalPreview">— FCFA</span>
                    </div>

                    <!-- SECTION FOURNISSEUR -->
                    <div class="section-divider"><span>🏢 Informations fournisseur</span></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="bi bi-building label-blue"></i>
                                Nom fournisseur <span class="required">*</span>
                            </label>
                            <div class="input-wrap">
                                <i class="bi bi-building input-icon"></i>
                                <input type="text" name="supplier_name" id="inputSupplier"
                                    placeholder="Ex: Diallo Commerce" autocomplete="off" required>
                            </div>
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i>
                                Créé automatiquement si nouveau
                            </div>
                        </div>
                        <div class="form-group">
                            <label>
                                <i class="bi bi-telephone label-blue"></i>
                                Numéro fournisseur
                            </label>
                            <div class="input-wrap">
                                <i class="bi bi-telephone input-icon"></i>
                                <input type="text" name="supplier_phone" id="inputPhone" placeholder="Ex: 77 123 45 67">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="add" class="btn-purchase">
                        <i class="bi bi-bag-plus-fill"></i>
                        Enregistrer l'achat
                    </button>
                    <button type="reset" class="btn-reset" onclick="resetForm()">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Réinitialiser
                    </button>
                </form>
            </div>

            <!-- PANNEAU LATÉRAL -->
            <div class="side-panel">

                <!-- RÉCAP EN DIRECT -->
                <div class="recap-card">
                    <h6><i class="bi bi-eye"></i> Récapitulatif</h6>
                    <div class="recap-box">
                        <div class="recap-box-icon">🛍️</div>
                        <div class="recap-box-product empty" id="recapProduct">Aucun produit saisi</div>
                        <div class="recap-box-supplier" id="recapSupplier">Fournisseur: —</div>
                        <div class="recap-pills">
                            <div class="recap-pill" id="recapQty">Qté: —</div>
                            <div class="recap-pill" id="recapBuy">Achat: —</div>
                            <div class="recap-pill" id="recapSell">Vente: —</div>
                            <div class="recap-pill" id="recapPhone">📞 —</div>
                        </div>
                    </div>
                    <div class="recap-total-box">
                        <div class="recap-total-label">Total achat</div>
                        <div class="recap-total-value" id="recapTotal">— FCFA</div>
                    </div>
                </div>

                <!-- ANALYSE RENTABILITÉ -->
                <div class="analyse-card">
                    <h6><i class="bi bi-graph-up-arrow"></i> Analyse rentabilité</h6>
                    <div class="analyse-row">
                        <span class="analyse-label">Prix d'achat/unité</span>
                        <span class="analyse-val" id="aBuy">— FCFA</span>
                    </div>
                    <div class="analyse-row">
                        <span class="analyse-label">Prix de vente/unité</span>
                        <span class="analyse-val" id="aSell">— FCFA</span>
                    </div>
                    <div class="analyse-row">
                        <span class="analyse-label">Marge %</span>
                        <span class="analyse-val" id="aMargin">— %</span>
                    </div>
                    <div class="analyse-row">
                        <span class="analyse-label">Bénéfice/unité</span>
                        <span class="analyse-val green" id="aProfit">— FCFA</span>
                    </div>
                    <div class="analyse-row">
                        <span class="analyse-label">Bénéfice total</span>
                        <span class="analyse-val green" id="aTotalProfit">— FCFA</span>
                    </div>
                    <div class="big-profit" id="bigProfit">
                        <div class="big-profit-label">Potentiel si tout vendu</div>
                        <div class="big-profit-value" id="bigProfitVal">— FCFA</div>
                    </div>
                </div>

                <!-- DERNIERS ACHATS -->
                <div class="last-card">
                    <h6>⚡ Derniers achats</h6>
                    <?php
                if($lastPurchases->num_rows === 0){
                    echo "<p style='color:rgba(255,255,255,0.3);font-size:13px;text-align:center;padding:10px 0;'>Aucun achat enregistré</p>";
                }
                while($lp = $lastPurchases->fetch_assoc()):
                    $total = $lp['quantity'] * $lp['unit_price'];
                ?>
                    <div class="purchase-item">
                        <div>
                            <div class="purchase-item-name"><?= htmlspecialchars($lp['product_name']) ?></div>
                            <div class="purchase-item-supplier">
                                <?= htmlspecialchars($lp['supplier_name']) ?>
                                <?php if($lp['supplier_phone']): ?> ·
                                <?= htmlspecialchars($lp['supplier_phone']) ?><?php endif; ?>
                                · <?= date('d/m H:i', strtotime($lp['purchased_at'])) ?>
                            </div>
                        </div>
                        <div class="purchase-item-amount"><?= number_format($total,0,'',' ') ?> F</div>
                    </div>
                    <?php endwhile; ?>
                </div>

            </div>
        </div>
    </div>

    <script>
    const inputProduct = document.getElementById('inputProduct');
    const inputQty = document.getElementById('inputQty');
    const inputPrice = document.getElementById('inputPrice');
    const inputSellPrice = document.getElementById('inputSellPrice');
    const inputSupplier = document.getElementById('inputSupplier');
    const inputPhone = document.getElementById('inputPhone');

    const recapProduct = document.getElementById('recapProduct');
    const recapSupplier = document.getElementById('recapSupplier');
    const recapQty = document.getElementById('recapQty');
    const recapBuy = document.getElementById('recapBuy');
    const recapSell = document.getElementById('recapSell');
    const recapPhone = document.getElementById('recapPhone');
    const recapTotal = document.getElementById('recapTotal');
    const totalPreview = document.getElementById('totalPreview');

    const profitLive = document.getElementById('profitLive');
    const profitPerUnit = document.getElementById('profitPerUnit');
    const profitMargin = document.getElementById('profitMargin');

    const aBuy = document.getElementById('aBuy');
    const aSell = document.getElementById('aSell');
    const aMargin = document.getElementById('aMargin');
    const aProfit = document.getElementById('aProfit');
    const aTotalProfit = document.getElementById('aTotalProfit');
    const bigProfit = document.getElementById('bigProfit');
    const bigProfitVal = document.getElementById('bigProfitVal');

    function fmt(n) {
        return Math.round(n).toLocaleString('fr-FR');
    }

    function updateAll() {
        const product = inputProduct.value.trim();
        const qty = parseInt(inputQty.value) || 0;
        const buy = parseFloat(inputPrice.value) || 0;
        const sell = parseFloat(inputSellPrice.value) || 0;
        const supplier = inputSupplier.value.trim();
        const phone = inputPhone.value.trim();

        const total = qty * buy;
        const profit = sell - buy;
        const totalProfit = profit * qty;
        const margin = buy > 0 ? ((profit / buy) * 100) : 0;
        const isLoss = profit < 0;

        // ── Récap ──
        recapProduct.textContent = product || 'Aucun produit saisi';
        recapProduct.className = 'recap-box-product' + (product ? '' : ' empty');
        recapSupplier.textContent = 'Fournisseur: ' + (supplier || '—');
        recapQty.textContent = 'Qté: ' + (qty > 0 ? qty : '—');
        recapBuy.textContent = 'Achat: ' + (buy > 0 ? fmt(buy) + ' F' : '—');
        recapSell.textContent = 'Vente: ' + (sell > 0 ? fmt(sell) + ' F' : '—');
        recapPhone.textContent = '📞 ' + (phone || '—');
        recapTotal.textContent = total > 0 ? fmt(total) + ' FCFA' : '— FCFA';
        totalPreview.textContent = total > 0 ? fmt(total) + ' FCFA' : '— FCFA';

        // ── Bénéfice live ──
        if (buy > 0 && sell > 0) {
            profitLive.className = 'profit-live' + (isLoss ? ' neg' : '');
            profitPerUnit.className = 'profit-live-value' + (isLoss ? ' neg' : '');
            profitMargin.className = 'profit-live-margin' + (isLoss ? ' neg' : '');
            profitPerUnit.textContent = (isLoss ? '−' : '+') + fmt(Math.abs(profit)) + ' FCFA';
            profitMargin.textContent = 'Marge : ' + margin.toFixed(1) + '%';
        } else {
            profitLive.className = 'profit-live';
            profitPerUnit.className = 'profit-live-value';
            profitMargin.className = 'profit-live-margin';
            profitPerUnit.textContent = '— FCFA';
            profitMargin.textContent = 'Entrez les deux prix';
        }

        // ── Panneau analyse ──
        aBuy.textContent = buy > 0 ? fmt(buy) + ' FCFA' : '— FCFA';
        aSell.textContent = sell > 0 ? fmt(sell) + ' FCFA' : '— FCFA';
        aMargin.textContent = (buy > 0 && sell > 0) ? margin.toFixed(1) + ' %' : '— %';

        const profitColor = !isLoss ? 'green' : 'red';
        aProfit.className = 'analyse-val ' + (buy > 0 && sell > 0 ? profitColor : '');
        aTotalProfit.className = 'analyse-val ' + (buy > 0 && sell > 0 ? profitColor : '');
        aProfit.textContent = (buy > 0 && sell > 0) ? (isLoss ? '−' : '+') + fmt(Math.abs(profit)) + ' FCFA' : '— FCFA';
        aTotalProfit.textContent = (buy > 0 && sell > 0) ? (isLoss ? '−' : '+') + fmt(Math.abs(totalProfit)) + ' FCFA' :
            '— FCFA';

        bigProfit.className = 'big-profit' + (isLoss ? ' loss' : '');
        bigProfitVal.className = 'big-profit-value' + (isLoss ? ' loss' : '');
        bigProfitVal.textContent = (buy > 0 && sell > 0) ? (isLoss ? '−' : '+') + fmt(Math.abs(totalProfit)) + ' FCFA' :
            '— FCFA';
    }

    [inputProduct, inputQty, inputPrice, inputSellPrice, inputSupplier, inputPhone].forEach(el => {
        if (el) el.addEventListener('input', updateAll);
    });

    function resetForm() {
        setTimeout(updateAll, 10);
    }

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