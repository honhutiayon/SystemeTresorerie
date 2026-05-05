<?php
    // 1. Headers CORS
    header("Access-Control-Allow-Origin: *"); 
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    header("Content-Type: application/json; charset=UTF-8");
<<<<<<< HEAD
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

    // 2. IMPORTATION DU GARDE
    require_once 'auth_guard.php';

    // 3. VÉRIFICATION DU TOKEN
    $userData = verifierAcces(); 
    $id_connecte = $userData->id;

    // 4. CONNEXION
=======

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

    // 2. IMPORTATION DU GARDE (C'est ce qui manquait !)
    require_once 'auth_guard.php';

    // 3. VÉRIFICATION DU TOKEN
    // Le script s'arrête ici si le token est absent ou invalide
    $userData = verifierAcces(); 

    // Tu peux maintenant utiliser l'ID de l'utilisateur connecté
    $id_connecte = $userData->id;

    // 4. CONNEXION À LA BASE ET LOGIQUE
>>>>>>> main
    require_once '../connexion/connexion.php';
    $method = $_SERVER['REQUEST_METHOD'];

    // --- RÉCUPÉRATION (GET) ---
<<<<<<< HEAD
    if ($method === 'GET') {
        // MODIFICATION : Jointure avec la table compte pour avoir le nom
        $sql = "SELECT p.id_portefeuille, p.id_compte, p.id_utilisateur, p.date_creation, p.statut, c.nom_compte 
                FROM portefeuille p
                LEFT JOIN compte c ON p.id_compte = c.id_compte
                ORDER BY p.id_portefeuille DESC LIMIT 50";
        
=======
   if ($method === 'GET') {
        // On récupère les colonnes qui existent REELLEMENT dans ta table
        $sql = "SELECT id_portefeuille, id_compte, id_utilisateur, date_creation, statut FROM portefeuille ORDER BY id_portefeuille DESC";
>>>>>>> main
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

<<<<<<< HEAD
=======
        // ATTENTION : Ta table exige un id_compte !
>>>>>>> main
        $id_compte = $data['id_compte'] ?? null; 

        if (!$id_compte) {
            echo json_encode(["success" => false, "message" => "L'ID du compte est obligatoire"]);
            exit;
        }

<<<<<<< HEAD
=======
        // Préparation de l'insertion selon TA structure actuelle
>>>>>>> main
        $sql = "INSERT INTO portefeuille (id_compte, id_utilisateur, statut) VALUES (?, ?, 'GENERE')";
        $stmt = mysqli_prepare($connexion, $sql);
        
        if ($stmt) {
<<<<<<< HEAD
=======
            // "ii" car ce sont deux entiers (Integers)
>>>>>>> main
            mysqli_stmt_bind_param($stmt, "ii", $id_compte, $id_connecte);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode([
                    "success" => true, 
<<<<<<< HEAD
                    "message" => "Portefeuille enregistré !",
=======
                    "message" => "Portefeuille lié à l'utilisateur $id_connecte enregistré !",
>>>>>>> main
                    "id" => mysqli_insert_id($connexion)
                ]);
            } else {
                echo json_encode(["success" => false, "message" => mysqli_stmt_error($stmt)]);
            }
            mysqli_stmt_close($stmt);
        }
    }
?>