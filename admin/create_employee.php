<?php
session_start();
require_once('../config/db.php');
requireLogin();
requireAdmin();

$message = '';
$msgType = '';

// Upload photo
function uploadPhoto($file) {
    if($file['error'] !== 0) return null;
    $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','webp'];
    if(!in_array(strtolower($ext), $allowed)) return null;
    $filename = 'user_' . time() . '.' . $ext;
    $dest     = '../uploads/' . $filename;
    if(!is_dir('../uploads')) mkdir('../uploads', 0755, true);
    move_uploaded_file($file['tmp_name'], $dest);
    return $filename;
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $u = trim($_POST['username']);
    $e = trim($_POST['email']);
    $p = $_POST['password'];

    if(!empty($u) && !empty($e) && !empty($p)){
        $check = $conn->query("SELECT id FROM users WHERE email='$e'");
        if($check->num_rows > 0){
            $message = "Cet email est déjà utilisé.";
            $msgType = "error";
        } else {
            $hash  = password_hash($p, PASSWORD_DEFAULT);
            $photo = uploadPhoto($_FILES['photo'] ?? ['error'=>4]);
            $photoVal = $photo ? "'$photo'" : "NULL";
            $conn->query("INSERT INTO users(username,email,password,role,photo) VALUES('$u','$e','$hash','employee',$photoVal)");
            $message = "Compte employé créé pour $u !";
            $msgType = "success";
        }
    } else {
        $message = "Remplissez tous les champs.";
        $msgType = "error";
    }
}

// Liste des employés
$employees = $conn->query("SELECT * FROM users WHERE role='employee' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Gestion Employés</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
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
        --light: #f8fafc;
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

    /* ADMIN BADGE dans sidebar */
    .admin-badge {
        margin: 0 12px 16px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.05));
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 12px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .admin-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .admin-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary), #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .admin-info-name {
        color: white;
        font-size: 13px;
        font-weight: 700;
    }

    .admin-info-role {
        color: #a5b4fc;
        font-size: 11px;
        font-weight: 600;
        background: rgba(99, 102, 241, 0.2);
        padding: 1px 7px;
        border-radius: 10px;
        display: inline-block;
        margin-top: 2px;
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

    /* CONTENT */
    .content {
        margin-left: var(--sidebar-w);
        padding: 32px;
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

    /* LAYOUT */
    .page-layout {
        display: grid;
        grid-template-columns: 420px 1fr;
        gap: 24px;
        align-items: start;
    }

    /* FORM CARD */
    .form-card {
        background: white;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .form-card-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 24px;
        padding-bottom: 18px;
        border-bottom: 2px solid #f1f5f9;
    }

    .form-header-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #ede9fe, #ddd6fe);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .form-card-header h4 {
        font-size: 18px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .form-card-header p {
        font-size: 13px;
        color: var(--gray);
        margin: 3px 0 0;
    }

    /* PHOTO UPLOAD */
    .photo-upload {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: #fafbff;
        border: 2px dashed var(--border);
        border-radius: 12px;
        margin-bottom: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .photo-upload:hover {
        border-color: var(--primary);
        background: #eef2ff;
    }

    .photo-preview {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        object-fit: cover;
        border: 2px solid var(--border);
    }

    .photo-placeholder {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: #ede9fe;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .photo-upload-text strong {
        font-size: 13px;
        font-weight: 700;
        color: var(--dark);
        display: block;
    }

    .photo-upload-text span {
        font-size: 12px;
        color: var(--gray);
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
        margin-bottom: 7px;
    }

    .lbl-icon {
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

    .input-wrap {
        position: relative;
    }

    .input-wrap input {
        width: 100%;
        padding: 12px 16px 12px 42px;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        color: var(--dark);
        background: #fafbff;
        outline: none;
        transition: all 0.25s;
    }

    .input-wrap input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
    }

    .input-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        color: #94a3b8;
        pointer-events: none;
    }

    .btn-create {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        border: none;
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    }

    /* ALERT */
    .alert-msg {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 20px;
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

    /* TABLE CARD */
    .table-card {
        background: white;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .table-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .table-card-header h5 {
        font-size: 16px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .count-badge {
        background: #ede9fe;
        color: var(--primary);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table th {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: var(--gray);
        padding: 8px 14px;
        border-bottom: 2px solid var(--border);
    }

    table td {
        padding: 12px 14px;
        font-size: 13px;
        color: var(--dark);
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    table tbody tr:last-child td {
        border-bottom: none;
    }

    table tbody tr:hover td {
        background: #f8faff;
    }

    .emp-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: cover;
    }

    .emp-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        color: white;
    }

    .role-badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .role-badge.admin {
        background: #ede9fe;
        color: var(--primary);
    }

    .role-badge.employee {
        background: #d1fae5;
        color: var(--green);
    }

    .action-btn {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }

    .action-btn.delete {
        background: #fef2f2;
        color: var(--red);
    }

    .action-btn.delete:hover {
        transform: scale(1.15);
        background: var(--red);
        color: white;
    }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">📦</div>
            <h4>Stock App</h4>
            <span>Administration</span>
        </div>
        <nav class="sidebar-nav">
            <!-- PROFIL ADMIN -->
            <div class="admin-badge">
                <?php if($_SESSION['photo']): ?>
                <img src="../uploads/<?= $_SESSION['photo'] ?>" class="admin-avatar" alt="Admin">
                <?php else: ?>
                <div class="admin-avatar-placeholder"><?= strtoupper(substr($_SESSION['user'],0,1)) ?></div>
                <?php endif; ?>
                <div>
                    <div class="admin-info-name"><?= htmlspecialchars($_SESSION['user']) ?></div>
                    <div class="admin-info-role">👑 Administrateur</div>
                </div>
            </div>

            <div class="nav-section">Principal</div>
            <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <div class="nav-section">Inventaire</div>
            <a href="../products/list.php"><i class="bi bi-box-seam"></i> Produits</a>
            <a href="../products/add.php"><i class="bi bi-plus-circle"></i> Ajouter produit</a>
            <div class="nav-section">Ventes</div>
            <a href="../sales/sell.php"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
            <a href="../sales/list.php"><i class="bi bi-clock-history"></i> Historique</a>
            <div class="nav-section">Administration</div>
            <a href="create_employee.php" class="active"><i class="bi bi-people"></i> Employés</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Mon profil</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">
        <div class="topbar">
            <div>
                <h5>👥 Gestion des employés</h5>
                <p>Créez et gérez les comptes de vos employés</p>
            </div>
        </div>

        <div class="page-layout">

            <!-- FORMULAIRE CRÉATION -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-header-icon">👤</div>
                    <div>
                        <h4>Nouvel employé</h4>
                        <p>Créer un compte employé</p>
                    </div>
                </div>

                <?php if($message): ?>
                <div class="alert-msg <?= $msgType ?>">
                    <i class="bi bi-<?= $msgType==='success' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                    <?= $message ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <!-- PHOTO -->
                    <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
                        <div class="photo-placeholder" id="photoPlaceholder">📷</div>
                        <img id="photoPreview" class="photo-preview" style="display:none;" alt="Preview">
                        <div class="photo-upload-text">
                            <strong>Photo de profil</strong>
                            <span>Cliquez pour choisir une image (JPG, PNG)</span>
                        </div>
                    </div>
                    <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none;">

                    <div class="form-group">
                        <label><span class="lbl-icon"><i class="bi bi-person"></i></span> Nom complet</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="username" placeholder="Ex: Mamadou Diallo" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><span class="lbl-icon"><i class="bi bi-envelope"></i></span> Email</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="employe@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><span class="lbl-icon"><i class="bi bi-lock"></i></span> Mot de passe</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-create">
                        <i class="bi bi-person-plus-fill"></i> Créer le compte
                    </button>
                </form>
            </div>

            <!-- LISTE EMPLOYÉS -->
            <div class="table-card">
                <div class="table-card-header">
                    <h5>👥 Liste des employés</h5>
                    <span class="count-badge"><?= $employees->num_rows ?> employé(s)</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Créé le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                $colors = ['#6366f1','#10b981','#f59e0b','#ec4899','#3b82f6'];
                $i = 0;
                while($emp = $employees->fetch_assoc()):
                    $color = $colors[$i % count($colors)]; $i++;
                ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php if($emp['photo']): ?>
                                    <img src="../uploads/<?= $emp['photo'] ?>" class="emp-avatar" alt="">
                                    <?php else: ?>
                                    <div class="emp-avatar-placeholder" style="background:<?= $color ?>">
                                        <?= strtoupper(substr($emp['username'],0,1)) ?>
                                    </div>
                                    <?php endif; ?>
                                    <strong><?= htmlspecialchars($emp['username']) ?></strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($emp['email']) ?></td>
                            <td><span class="role-badge employee">Employé</span></td>
                            <td style="color:#94a3b8;"><?= date('d/m/Y', strtotime($emp['created_at'] ?? 'now')) ?></td>
                            <td>
                                <a href="delete_employee.php?id=<?= $emp['id'] ?>" class="action-btn delete"
                                    onclick="return confirm('Supprimer cet employé ?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // Preview photo
    document.getElementById('photoInput').addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('photoPreview').src = e.target.result;
                document.getElementById('photoPreview').style.display = 'block';
                document.getElementById('photoPlaceholder').style.display = 'none';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    setTimeout(function() {
        document.querySelectorAll('.alert-msg').forEach(el => {
            el.style.transition = 'opacity 0.8s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 800);
        });
    }, 10000);
    </script>
</body>

</html>