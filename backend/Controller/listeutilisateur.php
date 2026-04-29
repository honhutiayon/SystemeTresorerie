<?php
    // FORCE L'AFFICHAGE DES ERREURS
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

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
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($connexion, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssss", $nom, $prenom, $email, $hash, $role);
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(["success" => true, "message" => "Utilisateur créé !"]);
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