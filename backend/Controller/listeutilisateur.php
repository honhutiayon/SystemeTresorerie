<?php
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
        $sql = "SELECT id_utilisateur, nom, prenom, email, role, date_creation FROM utilisateur ORDER BY nom ASC";
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
