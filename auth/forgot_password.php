<?php
session_start();
include('../config/db.php');
include('../config/mailer.php');

$message = '';
$msgType = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($conn->real_escape_string($_POST['email']));

    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
        $message = "Veuillez entrer une adresse email valide.";
        $msgType = "error";
    } else {
        $res  = $conn->query("SELECT * FROM users WHERE email = '$email'");
        $user = $res->fetch_assoc();

        if(!$user){
            $message = "Si cet email est enregistré, vous recevrez un lien de réinitialisation.";
            $msgType = "success";
        } else {
            // Générer token sécurisé
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Invalider anciens tokens
            $conn->query("UPDATE password_resets SET used=1 WHERE email='$email'");

            // Insérer nouveau token
            $conn->query("INSERT INTO password_resets(email,token,expires_at)
                          VALUES('$email','$token','$expires')");

            $resetLink = APP_URL . "/auth/reset_password.php?token=$token";
            $username  = htmlspecialchars($user['username']);

            $content = "
            <p style='color:#374151;font-size:15px;line-height:1.7;margin:0 0 16px;'>
                Bonjour <strong>{$username}</strong> 👋
            </p>
            <p style='color:#374151;font-size:15px;line-height:1.7;margin:0 0 16px;'>
                Vous avez demandé à réinitialiser votre mot de passe
                pour votre compte <strong>" . APP_NAME . "</strong>.
            </p>
            <table width='100%' cellpadding='0' cellspacing='0'
                   style='background:#fef3c7;border-radius:0 8px 8px 0;
                          border-left:4px solid #f59e0b;margin:20px 0;'>
                <tr><td style='padding:14px 18px;'>
                    <p style='color:#92400e;font-size:13px;margin:0;font-weight:600;'>
                        ⚠️ Ce lien est valable pendant <strong>1 heure</strong> seulement.
                        Ne le partagez avec personne.
                    </p>
                </td></tr>
            </table>
            <p style='color:#374151;font-size:14px;line-height:1.7;margin:0;'>
                Cliquez sur le bouton ci-dessous pour créer votre nouveau mot de passe :
            </p>";

            $html = emailTemplate(
                '🔐 Réinitialisation de votre mot de passe',
                $content,
                'Réinitialiser mon mot de passe',
                $resetLink,
                'primary'
            );

            $sent = sendMail($email, $user['username'],
                '🔐 Réinitialisation de votre mot de passe — ' . APP_NAME,
                $html
            );

            if($sent){
                $message = "Un lien de réinitialisation a été envoyé à <strong>$email</strong>.<br>
                            Vérifiez votre boîte mail (et les spams). Valable 1 heure.";
                $msgType = "success";
            } else {
                $message = "Erreur lors de l'envoi de l'email. Vérifiez la configuration SMTP.";
                $msgType = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — Stock App</title>
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
        background: radial-gradient(circle, #4f46e5, #7c3aed);
        top: -150px;
        left: -150px;
    }

    .orb-2 {
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, #06b6d4, #3b82f6);
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
        width: 460px;
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
        background: linear-gradient(135deg, #ede9fe, #ddd6fe);
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

    .card .subtitle {
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
        padding: 12px 14px 12px 42px;
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
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }

    .input-wrap:focus-within i.icon {
        color: var(--primary);
    }

    .btn-submit {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
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
        box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
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
            <div class="card-icon">🔐</div>
            <h2>Mot de passe oublié ?</h2>
            <p class="subtitle">
                Entrez votre adresse email et nous vous enverrons
                un lien pour réinitialiser votre mot de passe.
            </p>

            <?php if($message): ?>
            <div class="alert-msg <?= $msgType ?>">
                <i class="bi bi-<?= $msgType==='success' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                <span><?= $message ?></span>
            </div>
            <?php endif; ?>

            <?php if($msgType !== 'success'): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Adresse email de votre compte</label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope icon"></i>
                        <input type="email" name="email" placeholder="ton_email@exemple.com"
                            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required
                            autofocus>
                    </div>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="bi bi-send-fill"></i>
                    Envoyer le lien de réinitialisation
                </button>
            </form>
            <?php endif; ?>

            <div class="back-link">
                <a href="login.php">
                    <i class="bi bi-arrow-left"></i> Retour à la connexion
                </a>
            </div>
        </div>
    </div>
</body>

</html>