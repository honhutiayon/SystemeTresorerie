<?php
// 1. Headers CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// 2. Gestion du Preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Authentification et Connexion
require_once 'auth_guard.php';
$userData = verifierAcces();
$id_connecte = $userData->id;

if (!file_exists('../connexion/connexion.php')) {
    echo json_encode(["success" => false, "message" => "Fichier de connexion introuvable"]);
    exit;
}
require_once '../connexion/connexion.php';

try {
    $response = [];

    // --- 1. SOLDE GLOBAL ---
    // Calculé uniquement sur les opérations validées
    $sqlGlobal = "SELECT 
        (SUM(CASE WHEN type_operation = 'ENTREE' THEN montant ELSE 0 END) + 
         SUM(CASE WHEN type_operation = 'TRANSFERT' THEN montant ELSE 0 END)) - 
        (SUM(CASE WHEN type_operation = 'SORTIE' THEN montant ELSE 0 END) + 
         SUM(CASE WHEN type_operation = 'TRANSFERT' THEN montant ELSE 0 END)) as solde_total 
        FROM operation WHERE statut = 'VALIDE'";
    
    // Note : Le transfert s'annule au niveau global (somme entrée = somme sortie), 
    // donc le solde global est techniquement SUM(ENTREE) - SUM(SORTIE).
    $resGlobal = mysqli_query($connexion, $sqlGlobal);
    if (!$resGlobal) throw new Exception(mysqli_error($connexion));
    
    $dataGlobal = mysqli_fetch_assoc($resGlobal);
    $response['solde_global'] = (float)($dataGlobal['solde_total'] ?? 0);

    // --- 2. SOLDE PAR COMPTE (Logique de flux) ---
    $sqlComptes = "SELECT 
        c.id_compte, 
        c.nom_compte, 
        c.type_compte,
        (
            -- Entrées : Opérations ENTREE sur ce compte + TRANSFERTS reçus
            COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_source = c.id_compte AND type_operation = 'ENTREE' AND statut = 'VALIDE'), 0) +
            COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_destination = c.id_compte AND type_operation = 'TRANSFERT' AND statut = 'VALIDE'), 0)
        ) - 
        (
            -- Sorties : Opérations SORTIE sur ce compte + TRANSFERTS envoyés
            COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_source = c.id_compte AND (type_operation = 'SORTIE' OR type_operation = 'TRANSFERT') AND statut = 'VALIDE'), 0)
        ) as solde_actuel
        FROM compte c";

    $resComptes = mysqli_query($connexion, $sqlComptes);
    if (!$resComptes) throw new Exception(mysqli_error($connexion));

    $comptes = [];
    while ($row = mysqli_fetch_assoc($resComptes)) {
        $row['solde_actuel'] = (float)$row['solde_actuel'];
        $comptes[] = $row;
    }
    $response['solde_par_compte'] = $comptes;

    // --- 3. STATISTIQUES MENSUELLES (MOIS EN COURS) ---
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

    // --- ENVOI DE LA RÉPONSE ---
    echo json_encode([
        "success" => true,
        "data" => $response
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erreur serveur : " . $e->getMessage()
    ]);
}

?>