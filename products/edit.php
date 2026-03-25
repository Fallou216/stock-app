<?php 
include('../config/db.php');

$id = intval($_GET['id']); // sécurité: forcer l'id en entier

// Récupération des données du produit
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Gestion de la mise à jour
$message = '';
if(isset($_POST['update'])){
    $name = $_POST['name'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];

    $update = $conn->prepare("UPDATE products SET name=?, quantity=?, price=? WHERE id=?");
    $update->bind_param("sidi", $name, $quantity, $price, $id);
    
    if($update->execute()){
        $message = '<div class="alert alert-success">Produit mis à jour avec succès !</div>';
        $data['name'] = $name;
        $data['quantity'] = $quantity;
        $data['price'] = $price;
    } else {
        $message = '<div class="alert alert-danger">Erreur lors de la mise à jour.</div>';
    }
    $update->close();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Produit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background: #f2f7fb;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .form-container {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        max-width: 500px;
        margin: 50px auto;
    }

    h2 {
        text-align: center;
        color: #333;
        margin-bottom: 25px;
    }

    .btn-success {
        background: linear-gradient(90deg, #4CAF50, #45a049);
        border: none;
    }

    .btn-success:hover {
        background: linear-gradient(90deg, #45a049, #3e8e41);
    }

    .btn-secondary {
        background: #ddd;
        color: #333;
    }

    .btn-secondary:hover {
        background: #ccc;
    }

    .form-label {
        font-weight: 500;
        color: #555;
    }
    </style>
</head>

<body>

    <div class="form-container">
        <h2>Modifier le produit</h2>
        <?= $message ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nom du produit</label>
                <input name="name" value="<?= htmlspecialchars($data['name']) ?>" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Quantité</label>
                <input name="quantity" type="number" value="<?= htmlspecialchars($data['quantity']) ?>"
                    class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Prix</label>
                <input name="price" type="number" step="0.01" value="<?= htmlspecialchars($data['price']) ?>"
                    class="form-control" required>
            </div>
            <button name="update" class="btn btn-success w-100">Modifier</button>
            <a href="list.php" class="btn btn-secondary w-100 mt-2">Annuler</a>
        </form>
    </div>

</body>

</html>