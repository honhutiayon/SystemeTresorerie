<?php

    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    require_once 'auth_guard.php';
    require_once '../connexion/connexion.php';

    // 1. On récupère les infos de celui qui demande la suppression (via le Token)
    $userData = verifierAcces(); 
    $id_connecte = $userData->id; // L'ID extrait du JWT

    // 2. On récupère l'ID de l'utilisateur à supprimer (envoyé depuis le front)
    $data = json_decode(file_get_contents("php://input"), true);
    $id_a_supprimer = $data['id_utilisateur'] ?? null;

    if (!$id_a_supprimer) {
        echo json_encode(["success" => false, "message" => "ID utilisateur manquant."]);
        exit;
    }

    // 3. LA SÉCURITÉ CRITIQUE : Comparaison des ID
    // On vérifie si l'ID envoyé correspond à l'ID de la personne connectée
    if ($id_connecte != $id_a_supprimer) {
        http_response_code(403); // Interdit
        echo json_encode([
            "success" => false, 
            "message" => "Action non autorisée. Vous ne pouvez supprimer que votre propre compte."
        ]);
        exit;
    }

    // 4. Suppression dans la base de données
    $sql = "DELETE FROM utilisateurs WHERE id_utilisateur = ?";
    $stmt = mysqli_prepare($connexion, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_a_supprimer);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                "success" => true, 
                "message" => "Votre compte a été supprimé avec succès."
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Erreur lors de la suppression."]);
        }
        mysqli_stmt_close($stmt);
    }
    
?>