<?php
    // 1. Headers CORS
    header("Access-Control-Allow-Origin: *"); 
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    header("Content-Type: application/json; charset=UTF-8");
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

    // 2. IMPORTATION DU GARDE (C'est ce qui manquait !)
    require_once 'auth_guard.php';

    // 3. VÉRIFICATION DU TOKEN
    // Le script s'arrête ici si le token est absent ou invalide
    $userData = verifierAcces(); 

    // Tu peux maintenant utiliser l'ID de l'utilisateur connecté
    $id_connecte = $userData->id;

    // 4. CONNEXION À LA BASE ET LOGIQUE
    require_once '../connexion/connexion.php';
    $method = $_SERVER['REQUEST_METHOD'];

    // --- RÉCUPÉRATION (GET) ---
    if ($method === 'GET') {
        // On récupère les colonnes qui existent REELLEMENT dans ta table
        $sql = "SELECT id_portefeuille, id_compte, id_utilisateur, date_creation, statut FROM portefeuille ORDER BY id_portefeuille DESC";
        $result = mysqli_query($connexion, $sql);

        if ($result) {
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            echo json_encode(["success" => true, "data" => $data]);
        } else {
            echo json_encode(["success" => false, "message" => mysqli_error($connexion)]);
        }
    } 

    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);

        // ATTENTION : Ta table exige un id_compte !
        $id_compte = $data['id_compte'] ?? null; 

        if (!$id_compte) {
            echo json_encode(["success" => false, "message" => "L'ID du compte est obligatoire"]);
            exit;
        }

        // Préparation de l'insertion selon TA structure actuelle
        $sql = "INSERT INTO portefeuille (id_compte, id_utilisateur, statut) VALUES (?, ?, 'GENERE')";
        $stmt = mysqli_prepare($connexion, $sql);
        
        if ($stmt) {
            // "ii" car ce sont deux entiers (Integers)
            mysqli_stmt_bind_param($stmt, "ii", $id_compte, $id_connecte);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode([
                    "success" => true, 
                    "message" => "Portefeuille lié à l'utilisateur $id_connecte enregistré !",
                    "id" => mysqli_insert_id($connexion)
                ]);
            } else {
                echo json_encode(["success" => false, "message" => mysqli_stmt_error($stmt)]);
            }
            mysqli_stmt_close($stmt);
        }
    }
?>