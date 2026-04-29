<?php
    // 1. Configuration des en-têtes (CORS et JSON)
    header("Access-Control-Allow-Origin: *"); 
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    header('Content-Type: application/json; charset=UTF-8');

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit;
    }

    // 2. Importation des dépendances Composer et de la connexion
    // Vérifie bien que le chemin vers vendor est correct par rapport à ce fichier
    require_once __DIR__ . '/../vendor/autoload.php'; 
    require_once '../connexion/connexion.php';

    use Firebase\JWT\JWT;

    // CLE SECRETE : Garde cette chaîne très bien cachée (ne pas la changer après production)
    $cle_secrete = "zR4!pQ92#mL9vX81*kP02_qZ73@nB64$";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $pass  = $input['password'] ?? '';

        if (empty($email) || empty($pass)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis']);
            exit;
        }

        // Requête pour trouver l'utilisateur
        $sql = "SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role FROM utilisateur WHERE email = ?";
        $stmt = mysqli_prepare($connexion, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $u = mysqli_fetch_assoc($result);

            // Vérification du mot de passe haché
            if ($u && password_verify($pass, $u['mot_de_passe'])) {
                
                // --- GENERATION DU JWT ---
                $temps_actuel = time();
                $expiration = $temps_actuel + (3600 * 24); // Valide pour 24 heures

                $payload = [
                    "iat" => $temps_actuel, // Date de création
                    "exp" => $expiration,   // Date d'expiration
                    "data" => [             // Données utiles de l'utilisateur
                        "id" => $u['id_utilisateur'],
                        "email" => $u['email'],
                        "role" => $u['role']
                    ]
                ];

                // Signature du jeton avec l'algorithme HS256
                $jwt = JWT::encode($payload, $cle_secrete, 'HS256');

                // Mise à jour de la connexion en DB (optionnel)
                $id_user = $u['id_utilisateur'];
                mysqli_query($connexion, "UPDATE utilisateur SET derniere_connexion = NOW() WHERE id_utilisateur = $id_user");

                echo json_encode([
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'token'   => $jwt, // C'est ce token que le frontend devra stocker
                    'user'    => [
                        'id'     => $u['id_utilisateur'],
                        'nom'    => $u['nom'],
                        'prenom' => $u['prenom'],
                        'role'   => $u['role']
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }

    mysqli_close($connexion);
?>
