<?php
session_start();
include('../config/db.php');
include('../config/mailer.php');

$message = '';
$msgType = '';
$token   = $_GET['token'] ?? $_POST['token'] ?? '';
$valid   = false;
$user    = null;

if($token){
    $safeToken = $conn->real_escape_string($token);
    $res = $conn->query("
        SELECT pr.*, u.username, u.email AS user_email, u.role
        FROM password_resets pr
        JOIN users u ON u.email = pr.email
        WHERE pr.token = '$safeToken'
          AND pr.used  = 0
          AND pr.expires_at > NOW()
        LIMIT 1
    ");
    if($res->num_rows > 0){
        $valid = true;
        $user  = $res->fetch_assoc();
    } else {
        $message = "Ce lien est invalide ou a expiré. Faites une nouvelle demande.";
        $msgType = "error";
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])){
    $postToken   = $conn->real_escape_string($_POST['token']);
    $newPass     = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];

    // Re-vérifier le token
    $res2 = $conn->query("
        SELECT pr.*, u.username, u.email AS user_email
        FROM password_resets pr
        JOIN users u ON u.email = pr.email
        WHERE pr.token = '$postToken' AND pr.used=0 AND pr.expires_at>NOW()
        LIMIT 1
    ");
    if($res2->num_rows === 0){
        $message = "Lien invalide ou expiré.";
        $msgType = "error";
        $valid   = false;
    } elseif(strlen($newPass) < 6){
        $message = "Le mot de passe doit contenir au moins 6 caractères.";
        $msgType = "error";
        $valid   = true;
        $user    = $res2->fetch_assoc();
    } elseif($newPass !== $confirmPass){
        $message = "Les deux mots de passe ne correspondent pas.";
        $msgType = "error";
        $valid   = true;
        $user    = $res2->fetch_assoc();
    } else {
        $user  = $res2->fetch_assoc();
        $hash  = password_hash($newPass, PASSWORD_DEFAULT);
        $email = $conn->real_escape_string($user['user_email']);

        $conn->query("UPDATE users SET password='$hash' WHERE email='$email'");
        $conn->query("UPDATE password_resets SET used=1 WHERE token='$postToken'");

        // Email de confirmation
        $username = htmlspecialchars($user['username']);
        $content  = "
        <p style='color:#374151;font-size:15px;line-height:1.7;margin:0 0 16px;'>
            Bonjour <strong>{$username}</strong> 👋
        </p>
        <p style='color:#374151;font-size:15px;line-height:1.7;margin:0 0 16px;'>
            Votre mot de passe <strong>" . APP_NAME . "</strong> a été
            <strong>réinitialisé avec succès</strong>.
        </p>
        <table width='100%' cellpadding='0' cellspacing='0'
               style='background:#f0fdf4;border-radius:0 8px 8px 0;
                      border-left:4px solid #10b981;margin:20px 0;'>
            <tr><td style='padding:14px 18px;'>
                <p style='color:#065f46;font-size:13px;margin:0;font-weight:600;'>
                    ✅ Votre compte est maintenant sécurisé avec votre nouveau mot de passe.
                </p>
            </td></tr>
        </table>
        <table width='100%' cellpadding='0' cellspacing='0'
               style='background:#fef2f2;border-radius:0 8px 8px 0;
                      border-left:4px solid #ef4444;margin:16px 0 0;'>
            <tr><td style='padding:14px 18px;'>
                <p style='color:#991b1b;font-size:13px;margin:0;'>
                    🔒 Si vous n'êtes pas à l'origine de cette modification,
                    contactez immédiatement l'administrateur.
                </p>
            </td></tr>
        </table>";

        $html = emailTemplate(
            '✅ Mot de passe modifié avec succès',
            $content,
            'Se connecter maintenant',
            APP_URL . '/auth/login.php',
            'success'
        );

        sendMail($email, $user['username'],
            '✅ Mot de passe modifié — ' . APP_NAME,
            $html
        );

        $message = "✅ Mot de passe réinitialisé avec succès ! Vous pouvez maintenant vous connecter.";
        $msgType = "success";
        $valid   = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
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
        --primary: #4f46e5;
        --dark: #0f172a;
        --gray: #64748b;
        --border: #e2e8f0;
        --light: #f8fafc;
        --green: #10b981;
        --red: #ef4444;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #0f172a;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .bg-orbs {
        position: fixed;
        inset: 0;
        z-index: 0;
        overflow: hidden;
    }

    .orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.3;
        animation: drift 10s ease-in-out infinite alternate;
    }

    .orb-1 {
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, #10b981, #059669);
        top: -150px;
        left: -150px;
    }

    .orb-2 {
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, #4f46e5, #7c3aed);
        bottom: -100px;
        right: -100px;
        animation-delay: 3s;
    }

    @keyframes drift {
        from {
            transform: translate(0, 0) scale(1);
        }

        to {
            transform: translate(30px, 20px) scale(1.05);
        }
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        background-size: 30px 30px;
        z-index: 0;
    }

    .wrapper {
        position: relative;
        z-index: 1;
        width: 480px;
        max-width: 95vw;
        animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(40px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .brand {
        text-align: center;
        margin-bottom: 20px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .card {
        background: white;
        border-radius: 24px;
        padding: 40px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
    }

    .card-icon {
        width: 64px;
        height: 64px;
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin: 0 auto 20px;
    }

    .card h2 {
        font-size: 22px;
        font-weight: 800;
        color: var(--dark);
        text-align: center;
        margin-bottom: 8px;
    }

    .subtitle {
        font-size: 13.5px;
        color: var(--gray);
        text-align: center;
        margin-bottom: 28px;
        line-height: 1.6;
    }

    .alert-msg {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 14px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 500;
        margin-bottom: 22px;
        line-height: 1.6;
        animation: fadeIn 0.3s ease;
    }

    .alert-msg i {
        font-size: 18px;
        flex-shrink: 0;
        margin-top: 2px;
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

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 7px;
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap i.icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray);
        font-size: 16px;
        pointer-events: none;
        transition: color 0.2s;
    }

    .input-wrap input {
        width: 100%;
        padding: 12px 42px 12px 42px;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        color: var(--dark);
        background: var(--light);
        outline: none;
        transition: all 0.2s;
    }

    .input-wrap input:focus {
        border-color: var(--green);
        background: white;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }

    .input-wrap:focus-within i.icon {
        color: var(--green);
    }

    .toggle-pass {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--gray);
        cursor: pointer;
        font-size: 16px;
        padding: 0;
        transition: color 0.2s;
    }

    .toggle-pass:hover {
        color: var(--green);
    }

    .strength-wrap {
        margin-top: 8px;
    }

    .strength-bar {
        height: 4px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
    }

    .strength-fill {
        height: 100%;
        width: 0%;
        border-radius: 4px;
        transition: width 0.3s, background 0.3s;
    }

    .strength-label {
        font-size: 11px;
        color: var(--gray);
        margin-top: 4px;
    }

    .match-hint {
        font-size: 12px;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .btn-submit {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #10b981, #059669);
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
        margin-top: 6px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .back-link {
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
        color: var(--gray);
    }

    .back-link a {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
    }

    .back-link a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <div class="bg-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>
    <div class="wrapper">
        <div class="brand">📦 Stock App</div>
        <div class="card">
            <div class="card-icon">🔑</div>
            <h2>Nouveau mot de passe</h2>
            <p class="subtitle">Choisissez un mot de passe fort pour sécuriser votre compte.</p>

            <?php if($message): ?>
            <div class="alert-msg <?= $msgType ?>">
                <i class="bi bi-<?= $msgType==='success' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                <span><?= $message ?></span>
            </div>
            <?php endif; ?>

            <?php if($valid && $user): ?>
            <!-- Info utilisateur -->
            <div style="background:#f8fafc;border-radius:12px;padding:14px 16px;
                    margin-bottom:22px;display:flex;align-items:center;gap:12px;
                    border:1px solid #e2e8f0;">
                <div style="width:38px;height:38px;border-radius:10px;
                        background:linear-gradient(135deg,#6366f1,#818cf8);
                        display:flex;align-items:center;justify-content:center;
                        color:white;font-weight:700;font-size:16px;flex-shrink:0;">
                    <?= strtoupper(substr($user['username'],0,1)) ?>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:700;color:#0f172a;">
                        <?= htmlspecialchars($user['username']) ?>
                    </div>
                    <div style="font-size:12px;color:#64748b;"><?= htmlspecialchars($user['user_email']) ?></div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock icon"></i>
                        <input type="password" name="new_password" id="newPass" placeholder="••••••••" required
                            oninput="checkStrength(this.value)">
                        <button type="button" class="toggle-pass" onclick="togglePass('newPass','eye1')">
                            <i class="bi bi-eye" id="eye1"></i>
                        </button>
                    </div>
                    <div class="strength-wrap">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirmer le nouveau mot de passe</label>
                    <div class="input-wrap">
                        <i class="bi bi-shield-check icon"></i>
                        <input type="password" name="confirm_password" id="confirmPass" placeholder="••••••••" required
                            oninput="checkMatch()">
                        <button type="button" class="toggle-pass" onclick="togglePass('confirmPass','eye2')">
                            <i class="bi bi-eye" id="eye2"></i>
                        </button>
                    </div>
                    <div class="match-hint" id="matchHint"></div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-shield-lock-fill"></i>
                    Réinitialiser le mot de passe
                </button>
            </form>
            <?php endif; ?>

            <div class="back-link">
                <?php if($msgType === 'success'): ?>
                <a href="login.php">
                    <i class="bi bi-box-arrow-in-right"></i> Se connecter maintenant
                </a>
                <?php else: ?>
                <a href="forgot_password.php">
                    <i class="bi bi-arrow-left"></i> Nouvelle demande
                </a>
                &nbsp;·&nbsp;
                <a href="login.php">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function togglePass(id, iconId) {
        const input = document.getElementById(id);
        const icon = document.getElementById(iconId);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    }

    function checkStrength(val) {
        const fill = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        let score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const levels = [{
                pct: '0%',
                color: '',
                text: ''
            },
            {
                pct: '25%',
                color: '#ef4444',
                text: '🔴 Très faible'
            },
            {
                pct: '50%',
                color: '#f59e0b',
                text: '🟡 Faible'
            },
            {
                pct: '70%',
                color: '#3b82f6',
                text: '🔵 Moyen'
            },
            {
                pct: '88%',
                color: '#10b981',
                text: '🟢 Fort'
            },
            {
                pct: '100%',
                color: '#059669',
                text: '✅ Très fort'
            },
        ];
        fill.style.width = levels[score].pct;
        fill.style.background = levels[score].color;
        label.textContent = levels[score].text;
        checkMatch();
    }

    function checkMatch() {
        const newP = document.getElementById('newPass')?.value || '';
        const conf = document.getElementById('confirmPass')?.value || '';
        const hint = document.getElementById('matchHint');
        if (!hint || !conf) return;
        if (newP === conf) {
            hint.innerHTML = '<i class="bi bi-check-circle" style="color:#10b981"></i> Les mots de passe correspondent';
            hint.style.color = '#10b981';
        } else {
            hint.innerHTML =
                '<i class="bi bi-x-circle" style="color:#ef4444"></i> Les mots de passe ne correspondent pas';
            hint.style.color = '#ef4444';
        }
    }
    </script>
</body>

</html>