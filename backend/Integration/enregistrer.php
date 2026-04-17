<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../connexion/connexion.php'; 

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Récupération sécurisée des données
    $nom          = $_POST['nom_compte'] ?? null;
    $type         = $_POST['type_compte'] ?? null;
    $numero       = $_POST['numero_compte'] ?? null;
    $solde_init   = floatval($_POST['solde_initial'] ?? 0);
    $solde_actuel = floatval($_POST['solde_actuel'] ?? 0);
    $id_plan      = isset($_POST['id_compte_comptable']) ? intval($_POST['id_compte_comptable']) : null;

    if (!$nom || !$type || !$id_plan) {
        echo json_encode(["status" => "error", "message" => "Données manquantes"]);
        exit;
    }

    // 2. Correction de la requête SQL (Utilisation des marqueurs '?' uniquement)
    $sql = "INSERT INTO compte (nom_compte, type_compte, numero_compte, solde_actuel, solde_initial, id_compte_comptable) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    if (isset($connexion) && $connexion instanceof mysqli) {
        $stmt = $connexion->prepare($sql);
        if ($stmt) {
            // L'ordre ici doit correspondre exactement aux parenthèses du INSERT ci-dessus
            $stmt->bind_param("sssddi", $nom, $type, $numero, $solde_actuel, $solde_init, $id_plan);

            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Compte financier créé avec succès"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Erreur exécution : " . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "Erreur préparation : " . $connexion->error]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Connexion à la base de données introuvable"]);
    }
}
?>


