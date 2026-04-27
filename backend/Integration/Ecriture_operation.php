<?php
// ============================================================
//  GET /Integration/ecritures_operation.php?id_operation=12
//  Retourne le détail comptable d'une opération précise :
//  les deux faces débit/crédit avec vérification de l'équilibre.
// ============================================================

  
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
    
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../connexion/connexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["succes" => false, "message" => "Méthode non autorisée. Utilisez GET."]);
    exit();
}

// ---------- 1. Validation du paramètre ----------
if (!isset($_GET['id_operation']) || !is_numeric($_GET['id_operation'])) {
    http_response_code(400);
    echo json_encode(["succes" => false, "message" => "Le paramètre 'id_operation' est requis et doit être un entier."]);
    exit();
}

$id_operation = (int) $_GET['id_operation'];

// ---------- 2. Récupération complète de l'opération ----------
$sql = "
    SELECT 
        o.id_operation,
        o.reference_operation,
        o.type_operation,
        o.montant,
        o.motif,
        o.date_operation,
        o.statut,
        u.nom                  AS nom_utilisateur,
        u.prenom               AS prenom_utilisateur,
        cs.nom_compte          AS nom_compte_source,
        cs.type_compte         AS type_compte_source,
        pc_s.code_comptable    AS code_source,
        pc_s.libelle           AS libelle_source,
        pc_s.classe            AS classe_source,
        pc_s.type_mouvement    AS type_mouvement_source,
        pc_s.solde_normal      AS solde_normal_source,
        cd.nom_compte          AS nom_compte_dest,
        cd.type_compte         AS type_compte_dest,
        pc_d.code_comptable    AS code_dest,
        pc_d.libelle           AS libelle_dest,
        pc_d.classe            AS classe_dest,
        pc_d.type_mouvement    AS type_mouvement_dest,
        pc_d.solde_normal      AS solde_normal_dest
    FROM operation o
    JOIN portefeuille p           ON o.id_portefeuille       = p.id_portefeuille
    JOIN utilisateur u            ON p.id_utilisateur        = u.id_utilisateur
    JOIN compte cs                ON o.numcompte_source      = cs.id_compte
    LEFT JOIN plan_comptable pc_s ON cs.id_compte_comptable  = pc_s.id_compte_comptable
    LEFT JOIN compte cd           ON o.numcompte_destination = cd.id_compte
    LEFT JOIN plan_comptable pc_d ON cd.id_compte_comptable  = pc_d.id_compte_comptable
    WHERE o.id_operation = ?
";

$stmt = mysqli_prepare($connexion, $sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["succes" => false, "message" => "Erreur préparation requête : " . mysqli_error($connexion)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $id_operation);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$op     = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$op) {
    http_response_code(404);
    echo json_encode(["succes" => false, "message" => "Opération introuvable."]);
    exit();
}

// ---------- 3. Construction des écritures débit/crédit ----------
$montant   = (float) $op['montant'];
$ref       = $op['motif'] ?? $op['reference_operation'];
$ecritures = [];

switch ($op['type_operation']) {

    case 'ENTREE':
        $ecritures[] = [
            "sens"           => "DEBIT",
            "code_compte"    => $op['code_source'],
            "libelle_compte" => $op['libelle_source'],
            "classe"         => $op['classe_source'],
            "libelle"        => "Entrée de fonds - " . $ref,
            "montant"        => $montant
        ];
        $ecritures[] = [
            "sens"           => "CREDIT",
            "code_compte"    => "7xx",
            "libelle_compte" => "Produit (classe 7)",
            "classe"         => 7,
            "libelle"        => "Produit constaté - " . $ref,
            "montant"        => $montant
        ];
        break;

    case 'SORTIE':
        $ecritures[] = [
            "sens"           => "DEBIT",
            "code_compte"    => "6xx",
            "libelle_compte" => "Charge (classe 6)",
            "classe"         => 6,
            "libelle"        => "Charge enregistrée - " . $ref,
            "montant"        => $montant
        ];
        $ecritures[] = [
            "sens"           => "CREDIT",
            "code_compte"    => $op['code_source'],
            "libelle_compte" => $op['libelle_source'],
            "classe"         => $op['classe_source'],
            "libelle"        => "Sortie de fonds - " . $ref,
            "montant"        => $montant
        ];
        break;

    case 'TRANSFERT':
        $ecritures[] = [
            "sens"           => "DEBIT",
            "code_compte"    => $op['code_dest']    ?? "N/A",
            "libelle_compte" => $op['libelle_dest'] ?? "Compte destination",
            "classe"         => $op['classe_dest']  ?? null,
            "libelle"        => "Transfert reçu - " . $ref,
            "montant"        => $montant
        ];
        $ecritures[] = [
            "sens"           => "CREDIT",
            "code_compte"    => $op['code_source'],
            "libelle_compte" => $op['libelle_source'],
            "classe"         => $op['classe_source'],
            "libelle"        => "Transfert émis - " . $ref,
            "montant"        => $montant
        ];
        break;
}

// ---------- 4. Vérification de l'équilibre ----------
$total_debit  = 0;
$total_credit = 0;
foreach ($ecritures as $e) {
    if ($e['sens'] === 'DEBIT')  $total_debit  += $e['montant'];
    if ($e['sens'] === 'CREDIT') $total_credit += $e['montant'];
}
$equilibre = abs($total_debit - $total_credit) <= 0.01;

// ---------- 5. Réponse ----------
http_response_code(200);
echo json_encode([
    "succes"    => true,
    "operation" => [
        "id_operation"      => $op['id_operation'],
        "reference"         => $op['reference_operation'],
        "type"              => $op['type_operation'],
        "montant"           => $montant,
        "motif"             => $op['motif'],
        "date"              => $op['date_operation'],
        "statut"            => $op['statut'],
        "utilisateur"       => $op['prenom_utilisateur'] . ' ' . $op['nom_utilisateur'],
        "compte_source"     => $op['nom_compte_source'],
        "compte_destination"=> $op['nom_compte_dest'] ?? null
    ],
    "ecritures" => $ecritures,
    "bilan"     => [
        "total_debit"  => $total_debit,
        "total_credit" => $total_credit,
        "equilibre"    => $equilibre
    ]
]);