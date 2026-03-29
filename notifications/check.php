<?php
/**
 * check.php — Vérifie les stocks faibles et envoie les alertes
 * Appelé via include_once depuis dashboard.php
 * NE PAS appeler session_start() ni include db.php ici
 */

if(!defined('STOCK_SEUIL')){
    define('STOCK_SEUIL', 5);
}

function checkStockAlerts($conn){

    // Produits en dessous du seuil
    $produits = $conn->query("
        SELECT * FROM products
        WHERE quantity <= " . STOCK_SEUIL . "
        ORDER BY quantity ASC
    ");

    if(!$produits || $produits->num_rows === 0) return;

    $alertList = [];

    while($p = $produits->fetch_assoc()){
        $pid  = intval($p['id']);
        $qty  = intval($p['quantity']);
        $name = $p['name']; // ✅ PAS d'escape ici, on utilise les requêtes préparées

        // Vérifier si une notification existe déjà AUJOURD'HUI pour ce produit
        $stmtCheck = $conn->prepare("
            SELECT id FROM notifications
            WHERE product_id = ?
              AND type = 'stock_alert'
              AND DATE(created_at) = CURDATE()
        ");
        $stmtCheck->bind_param("i", $pid);
        $stmtCheck->execute();
        $existing = $stmtCheck->get_result();
        $stmtCheck->close();

        if($existing && $existing->num_rows === 0){
            // ✅ Message avec apostrophes gérées proprement
            $msg = $qty == 0
                ? "Rupture de stock : le produit \"{$name}\" n'a plus aucune unité disponible."
                : "Stock faible : le produit \"{$name}\" n'a plus que {$qty} unité(s) en stock.";

            // ✅ Requête préparée → les apostrophes dans $msg ne cassent plus rien
            $stmtInsert = $conn->prepare("
                INSERT INTO notifications(type, message, product_id)
                VALUES('stock_alert', ?, ?)
            ");
            $stmtInsert->bind_param("si", $msg, $pid);
            $stmtInsert->execute();
            $stmtInsert->close();
        }

        $alertList[] = $p;
    }

    // Envoyer email aux admins UNE SEULE FOIS par jour
    if(!empty($alertList)){
        $emailCheck = $conn->query("
            SELECT id FROM notifications
            WHERE type = 'email_alert_sent'
              AND DATE(created_at) = CURDATE()
        ");

        if($emailCheck && $emailCheck->num_rows === 0){
            // Marquer comme envoyé
            $conn->query("
                INSERT INTO notifications(type, message)
                VALUES('email_alert_sent', 'Alerte email stock envoyée')
            ");

            // Charger mailer seulement si pas déjà chargé
            if(!function_exists('sendMail')){
                $mailerPath = __DIR__ . '/../config/mailer.php';
                if(file_exists($mailerPath)){
                    include_once $mailerPath;
                }
            }

            if(function_exists('sendMail') && function_exists('emailStockAlert')){
                $admins = $conn->query("SELECT * FROM users WHERE role='admin' AND email != ''");
                if($admins){
                    while($admin = $admins->fetch_assoc()){
                        if(!empty($admin['email'])){
                            $html = emailStockAlert($alertList, $admin['username']);
                            sendMail(
                                $admin['email'],
                                $admin['username'],
                                '🚨 Alerte Stock Critique — ' . (defined('APP_NAME') ? APP_NAME : 'Stock App'),
                                $html
                            );
                        }
                    }
                }
            }
        }
    }
}

// Exécuter seulement si $conn est disponible
if(isset($conn)){
    checkStockAlerts($conn);
}
?>