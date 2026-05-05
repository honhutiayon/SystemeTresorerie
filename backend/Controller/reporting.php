<?php
    // 1. Headers CORS & JSON
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Content-Type: application/json");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // 2. Sécurité et Connexion
    require_once 'auth_guard.php';
   

    if (!file_exists('../connexion/connexion.php')) {
        echo json_encode(["success" => false, "message" => "Base de données introuvable"]);
        exit;
    }
    require_once '../connexion/connexion.php';

    try {
        $response = [];

        // --- SECTION 1 : SOLDE GLOBAL ---
        $sqlGlobal = "SELECT 
            SUM(CASE WHEN type_operation = 'ENTREE' THEN montant ELSE 0 END) - 
            SUM(CASE WHEN type_operation = 'SORTIE' THEN montant ELSE 0 END) as solde_total 
            FROM operation WHERE statut = 'VALIDE'";
        
        $resGlobal = mysqli_query($connexion, $sqlGlobal);
        $dataGlobal = mysqli_fetch_assoc($resGlobal);
        $response['solde_global'] = (float)($dataGlobal['solde_total'] ?? 0);


        // --- SECTION 2 : SOLDE PAR COMPTE ---
        $sqlComptes = "SELECT 
            c.id_compte, 
            c.nom_compte, 
            c.type_compte,
            (
                COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_source = c.id_compte AND type_operation = 'ENTREE' AND statut = 'VALIDE'), 0) +
                COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_destination = c.id_compte AND type_operation = 'TRANSFERT' AND statut = 'VALIDE'), 0)
            ) - 
            (
                COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_source = c.id_compte AND (type_operation = 'SORTIE' OR type_operation = 'TRANSFERT') AND statut = 'VALIDE'), 0)
            ) as solde_actuel
            FROM compte c";

        $resComptes = mysqli_query($connexion, $sqlComptes);
        $comptes = [];
        while ($row = mysqli_fetch_assoc($resComptes)) {
            $row['solde_actuel'] = (float)$row['solde_actuel'];
            $comptes[] = $row;
        }
        $response['solde_par_compte'] = $comptes;


        // --- SECTION 3 : STATISTIQUES SIMPLES (GLOBALES POUR LE TEST) ---
        // J'ai retiré le filtre "AND MONTH..." pour que tes données s'affichent enfin sur Postman
        $sqlStats = "SELECT 
            SUM(CASE WHEN type_operation = 'ENTREE' THEN montant ELSE 0 END) as total_entrees,
            SUM(CASE WHEN type_operation = 'SORTIE' THEN montant ELSE 0 END) as total_sorties,
            SUM(CASE WHEN type_operation = 'TRANSFERT' THEN montant ELSE 0 END) as total_transfert,
            COUNT(id_operation) as nb_operations
            FROM operation 
            WHERE statut = 'VALIDE'";

        $resStats = mysqli_query($connexion, $sqlStats);
        $stats = mysqli_fetch_assoc($resStats);

        $stats_brutes = [
            'Total Entrées' => (float)($stats['total_entrees'] ?? 0),
            'Total Sorties' => (float)($stats['total_sorties'] ?? 0),
            'Total Transferts' => (float)($stats['total_transfert'] ?? 0),
            'Nombre Transactions' => (int)($stats['nb_operations'] ?? 0)
        ];

        // Filtrage des zéros : on ne garde que ce qui est > 0
        $stats_finales = array_filter($stats_brutes, function($v) {
            return $v > 0;
        });

        $response['statistiques_simples'] = !empty($stats_finales) ? $stats_finales : "Aucune opération valide en base";


        // --- SORTIE FINALE ---
        echo json_encode([
            "success" => true,
            "data" => $response
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
?>
