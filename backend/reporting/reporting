<?php
    // 1. Headers CORS en tout premier (AVANT le require)
    header("Access-Control-Allow-Origin: *"); 
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header('Content-Type: application/json');

    // 2. Gestion du Preflight (indispensable pour AJAX)
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // 3. Inclusion de la connexion
    // On utilise un try/catch ou on vérifie si le fichier existe pour éviter un crash fatal
    if (!file_exists('../connexion/connexion.php')) {
        echo json_encode(["success" => false, "message" => "Fichier de connexion introuvable"]);
        exit;
    }
    require_once '../connexion/connexion.php';

    try {
        $response = [];

        // --- 1. SOLDE GLOBAL ---
        $sqlGlobal = "SELECT SUM(total_entree) as solde_total FROM compte";
        $resGlobal = mysqli_query($connexion, $sqlGlobal);
        if (!$resGlobal) throw new Exception(mysqli_error($connexion));
        
        $dataGlobal = mysqli_fetch_assoc($resGlobal);
        $response['solde_global'] = (float)($dataGlobal['solde_total'] ?? 0);

        // --- 2. SOLDE PAR COMPTE ---
        $sqlComptes = "SELECT id_compte, nom_compte, type_compte, total_entree FROM compte";
        $resComptes = mysqli_query($connexion, $sqlComptes);
        if (!$resComptes) throw new Exception(mysqli_error($connexion));

        $comptes = [];
        while ($row = mysqli_fetch_assoc($resComptes)) {
            $row['total_entree'] = (float)$row['total_entree'];
            $comptes[] = $row;
        }
        $response['solde_par_compte'] = $comptes;

        // --- 3. STATISTIQUES SIMPLES (MOIS EN COURS) ---
        $moisActuel = date('m');
        $anneeActuelle = date('Y');

        $sqlStats = "SELECT 
                        SUM(CASE WHEN type_operation = 'ENTREE' THEN montant ELSE 0 END) as total_entrees,
                        SUM(CASE WHEN type_operation = 'SORTIE' THEN montant ELSE 0 END) as total_sorties,
                        SUM(CASE WHEN type_operation = 'TRANSFERT' THEN montant ELSE 0 END) as total_transfert,
                        COUNT(id_operation) as nb_operations
                    FROM operation 
                    WHERE statut = 'VALIDE' 
                    AND MONTH(date_operation) = '$moisActuel' 
                    AND YEAR(date_operation) = '$anneeActuelle'";

        $resStats = mysqli_query($connexion, $sqlStats);
        if (!$resStats) throw new Exception(mysqli_error($connexion));
        
        $stats = mysqli_fetch_assoc($resStats);

        $response['stats_mensuelles'] = [
            'total_entrees' => (float)($stats['total_entrees'] ?? 0),
            'total_sorties' => (float)($stats['total_sorties'] ?? 0),
            'total_transfert' => (float)($stats['total_transfert'] ?? 0),
            'nombre_transactions' => (int)($stats['nb_operations'] ?? 0)
        ];

        // --- ENVOI DE LA RÉPONSE RÉUSSIE ---
        echo json_encode([
            "success" => true,
            "data" => $response
        ]);

    } catch (Exception $e) {
        // En cas d'erreur SQL ou PHP, on renvoie un JSON propre au lieu de "planter"
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Erreur serveur: " . $e->getMessage()
        ]);
    }
?>