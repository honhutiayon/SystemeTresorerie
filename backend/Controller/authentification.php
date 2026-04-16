<?php
    // Affichage des erreurs pour le développement
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    header('Content-Type: application/json');
    require_once '../connexion/connexion.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1. Récupération des données JSON (Postman Body > raw > JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        
        // On utilise les colonnes de TA table : 'email' et 'mot_de_passe'
        $email = $input['email'] ?? '';
        $pass  = $input['password'] ?? '';

        if (empty($email) || empty($pass)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis']);
            exit;
        }

        // 2. Préparation de la requête
        $sql = "SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role FROM utilisateur WHERE email = ?";
        $stmt = mysqli_prepare($connexion, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            
            $result = mysqli_stmt_get_result($stmt);
            $u = mysqli_fetch_assoc($result);

            // 3. Vérification du mot de passe
            if ($u && password_verify($pass, $u['mot_de_passe'])) {
                
                // Mise à jour de la date de connexion (optionnel)
                $id_user = $u['id_utilisateur'];
                mysqli_query($connexion, "UPDATE utilisateur SET derniere_connexion = NOW() WHERE id_utilisateur = $id_user");

                // 4. Génération d'un Token "volatil"
                // Puisque tu n'as pas de table token, on génère un identifiant unique 
                // que le client devra renvoyer. Dans un vrai projet, on utiliserait un JWT ici.
                $token = bin2hex(random_bytes(32));

                echo json_encode([
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'token'   => $token,
                    'user'    => [
                        'id'     => $u['id_utilisateur'],
                        'nom'    => $u['nom'],
                        'prenom' => $u['prenom'],
                        'role'   => $u['role']
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur technique sur le serveur']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }

    mysqli_close($connexion);
?>