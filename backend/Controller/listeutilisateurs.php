<?php
<<<<<<< HEAD
    // 1. Headers de sécurité et CORS
    header("Access-Control-Allow-Origin: *"); 
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

    // 2. Importation du garde (Vérifie si la personne est connectée)
    require_once 'auth_guard.php';

    // 3. Vérification du token
    // On récupère les infos de l'utilisateur connecté (id, role, etc.)
    $userData = verifierAcces(); 

    // Optionnel : Tu pourrais vérifier si l'utilisateur est un 'ADMIN'
    /*
    if ($userData->role !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Accès réservé aux administrateurs."]);
        exit;
    }
    */

    // 4. Connexion à la base de données
    require_once '../connexion/connexion.php';

    // 5. Logique pour le GET
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // On ne sélectionne SURTOUT PAS le mot de passe (sécurité)
        $sql = "SELECT id_utilisateur, nom, prenom, email, role, date_creation FROM utilisateur ORDER BY nom ASC LIMIT 50";
        $result = mysqli_query($connexion, $sql);

        if ($result) {
            $utilisateurs = mysqli_fetch_all($result, MYSQLI_ASSOC);
            
            echo json_encode([
                "success" => true, 
                "data" => $utilisateurs,
                "requete_par" => $userData->email // Juste pour info
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Erreur : " . mysqli_error($connexion)]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Méthode non autorisée. Utilisez GET."]);
    }

    mysqli_close($connexion);
?>
=======

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
>>>>>>> main
