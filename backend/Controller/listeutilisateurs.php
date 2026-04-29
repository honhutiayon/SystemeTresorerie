<?php

    // Autorise l'origine de ton frontend (localhost)
    header("Access-Control-Allow-Origin: *"); 

    // Autorise les méthodes HTTP utilisées (GET, POST, etc.)
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

    // Autorise les headers spécifiques (très important pour l'AJAX)
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    // Si c'est une requête de type OPTIONS (preflight), on arrête ici
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit;
    }
    require_once '../connexion/connexion.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // On sélectionne les colonnes nécessaires (ne JAMAIS renvoyer le hash du mot de passe)
        $sql = "SELECT id_utilisateur, nom, prenom, email, role FROM utilisateur ORDER BY id_utilisateur DESC";
        
        $result = mysqli_query($connexion, $sql);

        if ($result) {
            $utilisateurs = [];

            // On parcourt les résultats
            while ($row = mysqli_fetch_assoc($result)) {
                $utilisateurs[] = $row;
            }

            // On renvoie la liste
            echo json_encode([
                "success" => true, 
                "data" => $utilisateurs
            ]);
        } else {
            echo json_encode([
                "success" => false, 
                "message" => "Erreur lors de la récupération : " . mysqli_error($connexion)
            ]);
        }

    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Méthode non autorisée. Utilisez GET."
        ]);
    }
    
?>