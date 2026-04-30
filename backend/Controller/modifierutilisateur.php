<?php
    // 1. Headers CORS (Doivent être AVANT tout le reste)
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST, OPTIONS"); // Ajout de OPTIONS ici
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

    // 2. GESTION DU PREFLIGHT (INDISPENSABLE pour corriger l'erreur de ton image)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Activer l'affichage des erreurs pour le debug (à retirer en production)
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // 3. Importations
    require_once 'auth_guard.php';
    require_once '../connexion/connexion.php';

    // Vérification JWT
    $userData = verifierAcces();

    // Lecture des données JSON
    $data = json_decode(file_get_contents("php://input"), true);

    // Extraction des champs
    $id_a_modifier = $data['id_utilisateur'] ?? null;
    $nom           = $data['nom'] ?? null;
    $prenom        = $data['prenom'] ?? null;
    $email         = $data['email'] ?? null;
    $role          = $data['role'] ?? null;

    // Validation de base
    if (!$id_a_modifier || !$nom || !$email) {
        echo json_encode(["success" => false, "message" => "Champs obligatoires manquants"]);
        exit;
    }

    // Requête SQL sécurisée (Utilise MySQLi comme tu préfères)
    $sql = "UPDATE utilisateur 
            SET nom = ?, prenom = ?, email = ?, role = ? 
            WHERE id_utilisateur = ? 
            AND role != 'admin'"; 

    $stmt = mysqli_prepare($connexion, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssi", $nom, $prenom, $email, $role, $id_a_modifier);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                echo json_encode(["success" => true, "message" => "Utilisateur mis à jour !"]);
            } else {
                echo json_encode(["success" => false, "message" => "Aucune modification (Admin ou données identiques)"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Erreur SQL : " . mysqli_stmt_error($stmt)]);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur de préparation"]);
    }
?>