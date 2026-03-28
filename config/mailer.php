<?php
// ============================================================
// CONFIGURATION PHPMAILER — MODIFIE ICI
// ============================================================
define('MAIL_FROM',     'falloudioum216@gmail.com');
define('MAIL_FROM_NAME','Stock App');
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USER',     'falloudioum216@gmail.com');
define('MAIL_PASS',     'fbui hial xkdj yjia
'); // Mot de passe app Gmail
define('APP_URL',       'http://localhost/stock-app');
define('APP_NAME',      'Stock App');

// ── Chargement PHPMailer ─────────────────────────────────────
// Si installé via Composer :
if(file_exists(__DIR__ . '/../vendor/autoload.php')){
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Si installé manuellement :
    require_once __DIR__ . '/../PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoie un email HTML via PHPMailer / Gmail SMTP
 */
function sendMail($to, $toName, $subject, $htmlBody){
    $mail = new PHPMailer(true);
    try {
        // Serveur SMTP
        $mail->isSMTP();
        $mail->Host        = MAIL_HOST;
        $mail->SMTPAuth    = true;
        $mail->Username    = MAIL_USER;
        $mail->Password    = MAIL_PASS;
        $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = MAIL_PORT;
        $mail->CharSet     = 'UTF-8';
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];

        // Expéditeur & Destinataire
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erreur envoi email : " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Template email universel bien stylé
 */
function emailTemplate($title, $content, $btnText = '', $btnUrl = '', $type = 'primary'){
    $colors = [
        'primary' => ['bg' => '#4f46e5', 'light' => '#ede9fe', 'text' => '#4f46e5'],
        'success' => ['bg' => '#10b981', 'light' => '#d1fae5', 'text' => '#065f46'],
        'warning' => ['bg' => '#f59e0b', 'light' => '#fef3c7', 'text' => '#92400e'],
        'danger'  => ['bg' => '#ef4444', 'light' => '#fee2e2', 'text' => '#991b1b'],
    ];
    $c = $colors[$type] ?? $colors['primary'];

    $btn = '';
    if($btnText && $btnUrl){
        $btn = "
        <table width='100%' cellpadding='0' cellspacing='0' style='margin:28px 0 16px;'>
            <tr><td align='center'>
                <a href='{$btnUrl}'
                   style='background:{$c['bg']};color:white;padding:14px 36px;
                          border-radius:12px;text-decoration:none;font-weight:700;
                          font-size:15px;display:inline-block;letter-spacing:0.3px;
                          font-family:Arial,sans-serif;'>
                    {$btnText}
                </a>
            </td></tr>
        </table>
        <p style='text-align:center;color:#94a3b8;font-size:12px;margin:0;'>
            Ou copiez ce lien dans votre navigateur :<br>
            <a href='{$btnUrl}' style='color:{$c['bg']};font-size:11px;word-break:break-all;'>{$btnUrl}</a>
        </p>";
    }

    $appName = APP_NAME;
    $year    = date('Y');

    return "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width,initial-scale=1'>
    <title>{$appName}</title>
</head>
<body style='margin:0;padding:0;background:#f0f4ff;font-family:Arial,Helvetica,sans-serif;'>

<table width='100%' cellpadding='0' cellspacing='0' border='0'
       style='background:#f0f4ff;padding:40px 20px;'>
<tr><td align='center'>

    <table width='600' cellpadding='0' cellspacing='0' border='0'
           style='max-width:600px;width:100%;'>

        <!-- ═══ HEADER ═══ -->
        <tr>
            <td style='background:linear-gradient(135deg,#0f172a,#1e293b);
                       border-radius:16px 16px 0 0;padding:32px 40px;
                       text-align:center;'>
                <div style='font-size:40px;margin-bottom:10px;line-height:1;'>📦</div>
                <h1 style='color:white;margin:0;font-size:24px;font-weight:800;
                           letter-spacing:-0.5px;font-family:Arial,sans-serif;'>
                    {$appName}
                </h1>
                <p style='color:rgba(255,255,255,0.5);margin:6px 0 0;font-size:12px;
                          letter-spacing:1px;text-transform:uppercase;'>
                    Système de Gestion de Stock
                </p>
            </td>
        </tr>

        <!-- ═══ ACCENT BAR ═══ -->
        <tr>
            <td style='background:{$c['bg']};height:4px;'></td>
        </tr>

        <!-- ═══ BODY ═══ -->
        <tr>
            <td style='background:white;padding:40px;'>
                <h2 style='color:#0f172a;font-size:20px;font-weight:800;
                           margin:0 0 20px;font-family:Arial,sans-serif;
                           border-bottom:2px solid #f1f5f9;padding-bottom:16px;'>
                    {$title}
                </h2>
                {$content}
                {$btn}
            </td>
        </tr>

        <!-- ═══ FOOTER ═══ -->
        <tr>
            <td style='background:#f8fafc;border-radius:0 0 16px 16px;
                       padding:24px 40px;border-top:1px solid #e2e8f0;'>
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td align='center'>
                            <p style='color:#94a3b8;font-size:12px;margin:0 0 8px;
                                      line-height:1.6;font-family:Arial,sans-serif;'>
                                Cet email a été envoyé automatiquement par
                                <strong style='color:#64748b;'>{$appName}</strong>.<br>
                                Si vous n'avez pas effectué cette action, ignorez cet email.
                            </p>
                            <p style='color:#cbd5e1;font-size:11px;margin:0;'>
                                &copy; {$year} {$appName} — Tous droits réservés.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>

</td></tr>
</table>

</body>
</html>";
}

/**
 * Template email d'alerte stock faible
 */
function emailStockAlert($products, $recipientName){
    $appName = APP_NAME;
    $appUrl  = APP_URL;
    $year    = date('Y');
    $date    = date('d/m/Y à H:i');

    // Générer les lignes du tableau produits
    $rows = '';
    foreach($products as $p){
        $qty    = intval($p['quantity']);
        $bgQty  = $qty == 0 ? '#fee2e2' : '#fef3c7';
        $clQty  = $qty == 0 ? '#dc2626' : '#d97706';
        $status = $qty == 0 ? '❌ Rupture' : '⚠️ Faible';

        $rows .= "
        <tr>
            <td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;
                       font-size:14px;color:#0f172a;font-weight:600;
                       font-family:Arial,sans-serif;'>
                📦 {$p['name']}
            </td>
            <td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;
                       text-align:center;'>
                <span style='background:{$bgQty};color:{$clQty};
                             padding:4px 12px;border-radius:20px;
                             font-size:13px;font-weight:700;
                             font-family:Arial,sans-serif;'>
                    {$qty} unité(s)
                </span>
            </td>
            <td style='padding:12px 16px;border-bottom:1px solid #f1f5f9;
                       text-align:center;font-size:13px;font-weight:700;
                       color:{$clQty};font-family:Arial,sans-serif;'>
                {$status}
            </td>
        </tr>";
    }

    $count = count($products);

    return "<!DOCTYPE html>
<html lang='fr'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f4ff;font-family:Arial,Helvetica,sans-serif;'>

<table width='100%' cellpadding='0' cellspacing='0' border='0'
       style='background:#f0f4ff;padding:40px 20px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' border='0'
       style='max-width:600px;width:100%;'>

    <!-- HEADER -->
    <tr>
        <td style='background:linear-gradient(135deg,#0f172a,#1e293b);
                   border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;'>
            <div style='font-size:40px;margin-bottom:10px;'>📦</div>
            <h1 style='color:white;margin:0;font-size:24px;font-weight:800;'>
                {$appName}
            </h1>
            <p style='color:rgba(255,255,255,0.5);margin:6px 0 0;font-size:12px;
                      letter-spacing:1px;text-transform:uppercase;'>
                Système de Gestion de Stock
            </p>
        </td>
    </tr>

    <!-- ALERT BAR -->
    <tr><td style='background:#ef4444;height:4px;'></td></tr>

    <!-- BODY -->
    <tr>
        <td style='background:white;padding:40px;'>

            <!-- TITRE ALERTE -->
            <table width='100%' cellpadding='0' cellspacing='0'
                   style='background:#fef2f2;border-radius:12px;
                          border-left:4px solid #ef4444;margin-bottom:24px;'>
                <tr>
                    <td style='padding:16px 20px;'>
                        <h2 style='color:#991b1b;font-size:18px;font-weight:800;
                                   margin:0 0 4px;'>
                            🚨 Alerte Stock Critique
                        </h2>
                        <p style='color:#dc2626;font-size:13px;margin:0;'>
                            {$count} produit(s) nécessitent votre attention immédiate.
                        </p>
                    </td>
                </tr>
            </table>

            <p style='color:#374151;font-size:15px;line-height:1.7;margin:0 0 20px;'>
                Bonjour <strong>{$recipientName}</strong> 👋,
            </p>
            <p style='color:#374151;font-size:15px;line-height:1.7;margin:0 0 24px;'>
                Le système <strong>{$appName}</strong> a détecté des produits dont le stock
                est critique. Voici le récapitulatif au <strong>{$date}</strong> :
            </p>

            <!-- TABLEAU PRODUITS -->
            <table width='100%' cellpadding='0' cellspacing='0'
                   style='border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;
                          margin-bottom:24px;'>
                <tr style='background:#ef4444;'>
                    <th style='padding:12px 16px;color:white;font-size:12px;
                               font-weight:700;text-align:left;letter-spacing:0.5px;
                               text-transform:uppercase;font-family:Arial,sans-serif;'>
                        Produit
                    </th>
                    <th style='padding:12px 16px;color:white;font-size:12px;
                               font-weight:700;text-align:center;letter-spacing:0.5px;
                               text-transform:uppercase;font-family:Arial,sans-serif;'>
                        Quantité
                    </th>
                    <th style='padding:12px 16px;color:white;font-size:12px;
                               font-weight:700;text-align:center;letter-spacing:0.5px;
                               text-transform:uppercase;font-family:Arial,sans-serif;'>
                        Statut
                    </th>
                </tr>
                {$rows}
            </table>

            <!-- RECOMMANDATION -->
            <table width='100%' cellpadding='0' cellspacing='0'
                   style='background:#fffbeb;border-radius:12px;
                          border-left:4px solid #f59e0b;margin-bottom:28px;'>
                <tr>
                    <td style='padding:16px 20px;'>
                        <p style='color:#92400e;font-size:13px;margin:0;font-weight:600;'>
                            💡 <strong>Recommandation :</strong> Passez une commande auprès
                            de vos fournisseurs pour réapprovisionner ces produits rapidement.
                        </p>
                    </td>
                </tr>
            </table>

            <!-- BOUTON -->
            <table width='100%' cellpadding='0' cellspacing='0'>
                <tr><td align='center'>
                    <a href='{$appUrl}/products/list.php'
                       style='background:linear-gradient(135deg,#ef4444,#dc2626);
                              color:white;padding:14px 36px;border-radius:12px;
                              text-decoration:none;font-weight:700;font-size:15px;
                              display:inline-block;font-family:Arial,sans-serif;'>
                        🔍 Voir les produits
                    </a>
                </td></tr>
            </table>

        </td>
    </tr>

    <!-- FOOTER -->
    <tr>
        <td style='background:#f8fafc;border-radius:0 0 16px 16px;
                   padding:24px 40px;border-top:1px solid #e2e8f0;
                   text-align:center;'>
            <p style='color:#94a3b8;font-size:12px;margin:0 0 6px;line-height:1.6;'>
                Alerte générée automatiquement par <strong style='color:#64748b;'>{$appName}</strong>.
            </p>
            <p style='color:#cbd5e1;font-size:11px;margin:0;'>
                &copy; {$year} {$appName} — Tous droits réservés.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>";
}
?>