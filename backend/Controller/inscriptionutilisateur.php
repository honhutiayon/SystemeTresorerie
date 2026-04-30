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
    // 2. IMPORTATION DU GARDE (C'est ce qui manquait !)
    require_once 'auth_guard.php';

    // 3. VÉRIFICATION DU TOKEN
    // Le script s'arrête ici si le token est absent ou invalide
    $userData = verifierAcces(); 

    // Tu peux maintenant utiliser l'ID de l'utilisateur connecté
    $id_connecte = $userData->id;

    header('Content-Type: application/json');
    require_once '../connexion/connexion.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nom      = $input['nom'] ?? '';
        $prenom   = $input['prenom'] ?? '';
        $email    = $input['email'] ?? '';
        $password = $input['mot_de_passe'] ?? '';
        $role     = $input['role'] ?? '';

        if (!empty($nom) && !empty($email) && !empty($password) && !empty($role)) {

            //--- ÉTAPE 1 : VÉRIFICATION ---
            $checkEmailSql = "SELECT id_utilisateur FROM utilisateur WHERE email = ?";
            $checkStmt = mysqli_prepare($connexion, $checkEmailSql);
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, "s", $email);
                mysqli_stmt_execute($checkStmt);
                
                // On récupère le résultat pour pouvoir compter les lignes
                $result = mysqli_stmt_get_result($checkStmt);
                
                if (mysqli_num_rows($result) > 0) {
                    echo json_encode(["success" => false, "message" => "Cet email est déjà utilisé."]);
                    mysqli_stmt_close($checkStmt);
                    exit; 
                }
                mysqli_stmt_close($checkStmt);
            } 

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($connexion, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssss", $nom, $prenom, $email, $hash, $role);
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(["success" => true, "message" => "Utilisateur cree !"]);
                } else {
                    echo json_encode(["success" => false, "message" => mysqli_error($connexion)]);
                }
                mysqli_stmt_close($stmt);
            } else {
                echo json_encode(["success" => false, "message" => "Erreur preparation SQL"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Donnees JSON manquantes"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Utilisez POST"]);
    }
    
?>