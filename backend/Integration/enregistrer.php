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
        
        // On remplace solde_initial/actuel par total_entree/sortie selon ta nouvelle BD
        $total_entree = floatval($_POST['total_entree'] ?? 0);
        $total_sortie = floatval($_POST['total_sortie'] ?? 0);
        
        $id_plan      = isset($_POST['id_compte_comptable']) ? intval($_POST['id_compte_comptable']) : null;

        if (!$nom || !$type || !$id_plan || !$numero) {
            echo json_encode(["status" => "error", "message" => "Données manquantes"]);
            exit;
        }

        // 2. Requête SQL mise à jour avec les nouveaux noms de colonnes
        $sql = "INSERT INTO compte (nom_compte, type_compte, numero_compte, total_entree, total_sortie, id_compte_comptable) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        if (isset($connexion) && $connexion instanceof mysqli) {
            $stmt = $connexion->prepare($sql);
            if ($stmt) {
                // "ss s d d i" -> string, string, string, double, double, integer
                $stmt->bind_param("sssddi", $nom, $type, $numero, $total_entree, $total_sortie, $id_plan);

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

    // Pas besoin de ob_clean() si aucun contenu n'a été envoyé avant le JSON
?>