<?php
// ============================================================
//  GET /Integration/ecritures_journal.php
//  Retourne le journal comptable à partir des opérations
//  et de leurs comptes comptables associés.
//
//  Paramètres GET (tous optionnels) :
//    date_debut  : YYYY-MM-DD
//    date_fin    : YYYY-MM-DD
//    type        : ENTREE | SORTIE | TRANSFERT
//    classe      : 1 à 7  (classe du plan comptable)
//    page        : numéro de page (défaut 1)
//    limite      : résultats par page (défaut 20, max 100)
// ============================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ⚠️ CORS en tout premier — avant tout require, echo ou espace
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../connexion/connexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["succes" => false, "message" => "Méthode non autorisée. Utilisez GET."]);
    exit();
}

// ---------- 1. Lecture et validation des filtres ----------
$date_debut = $_GET['date_debut'] ?? null;
$date_fin   = $_GET['date_fin']   ?? null;
$type       = isset($_GET['type']) && in_array(strtoupper($_GET['type']), ['ENTREE','SORTIE','TRANSFERT'])
                ? strtoupper($_GET['type']) : null;
$classe     = isset($_GET['classe']) && is_numeric($_GET['classe']) ? (int) $_GET['classe'] : null;
$page       = isset($_GET['page'])   && is_numeric($_GET['page'])   ? max(1, (int) $_GET['page'])    : 1;
$limite     = isset($_GET['limite']) && is_numeric($_GET['limite']) ? min(100, (int) $_GET['limite']) : 20;
$offset     = ($page - 1) * $limite;

foreach (['date_debut' => $date_debut, 'date_fin' => $date_fin] as $nom => $val) {
    if ($val !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        http_response_code(400);
        echo json_encode(["succes" => false, "message" => "Format invalide pour '$nom'. Attendu : YYYY-MM-DD."]);
        exit();
    }
}
if ($classe !== null && ($classe < 1 || $classe > 7)) {
    http_response_code(400);
    echo json_encode(["succes" => false, "message" => "La classe doit être entre 1 et 7."]);
    exit();
}

// ---------- 2. Construction dynamique de la requête ----------
// Le journal comptable affiche chaque opération avec ses deux faces
// (débit et crédit) déduites de la logique OHADA, sans table intermédiaire.

$conditions = ["1=1"];
$params     = [];
$types      = "";

if ($date_debut) {
    $conditions[] = "o.date_operation >= ?";
    $params[]     = $date_debut . ' 00:00:00';
    $types       .= "s";
}
if ($date_fin) {
    $conditions[] = "o.date_operation <= ?";
    $params[]     = $date_fin . ' 23:59:59';
    $types       .= "s";
}
if ($type) {
    $conditions[] = "o.type_operation = ?";
    $params[]     = $type;
    $types       .= "s";
}
if ($classe !== null) {
    $conditions[] = "(pc_s.classe = ? OR pc_d.classe = ?)";
    $params[]     = $classe;
    $params[]     = $classe;
    $types       .= "ii";
}

$where = "WHERE " . implode(" AND ", $conditions);

// ---------- 3. Compte total ----------
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM operation o
    JOIN portefeuille p           ON o.id_portefeuille       = p.id_portefeuille
    JOIN utilisateur u            ON p.id_utilisateur        = u.id_utilisateur
    JOIN compte cs                ON o.numcompte_source      = cs.id_compte
    LEFT JOIN plan_comptable pc_s ON cs.id_compte_comptable  = pc_s.id_compte_comptable
    LEFT JOIN compte cd           ON o.numcompte_destination = cd.id_compte
    LEFT JOIN plan_comptable pc_d ON cd.id_compte_comptable  = pc_d.id_compte_comptable
    $where
";

$stmtCount = mysqli_prepare($connexion, $sqlCount);
if ($types) mysqli_stmt_bind_param($stmtCount, $types, ...$params);
mysqli_stmt_execute($stmtCount);
$resCount = mysqli_stmt_get_result($stmtCount);
$total    = (int) mysqli_fetch_assoc($resCount)['total'];
mysqli_stmt_close($stmtCount);

// ---------- 4. Récupération des opérations avec infos comptables ----------
$sqlData = "
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
        cs.nom_compte          AS compte_source,
        pc_s.code_comptable    AS code_source,
        pc_s.libelle           AS libelle_source,
        pc_s.classe            AS classe_source,
        pc_s.type_mouvement    AS type_source,
        cd.nom_compte          AS compte_destination,
        pc_d.code_comptable    AS code_dest,
        pc_d.libelle           AS libelle_dest,
        pc_d.classe            AS classe_dest,
        pc_d.type_mouvement    AS type_dest
    FROM operation o
    JOIN portefeuille p           ON o.id_portefeuille       = p.id_portefeuille
    JOIN utilisateur u            ON p.id_utilisateur        = u.id_utilisateur
    JOIN compte cs                ON o.numcompte_source      = cs.id_compte
    LEFT JOIN plan_comptable pc_s ON cs.id_compte_comptable  = pc_s.id_compte_comptable
    LEFT JOIN compte cd           ON o.numcompte_destination = cd.id_compte
    LEFT JOIN plan_comptable pc_d ON cd.id_compte_comptable  = pc_d.id_compte_comptable
    $where
    ORDER BY o.date_operation DESC
    LIMIT ? OFFSET ?
";

$allParams = array_merge($params, [$limite, $offset]);
$allTypes  = $types . "ii";

$stmtData = mysqli_prepare($connexion, $sqlData);
mysqli_stmt_bind_param($stmtData, $allTypes, ...$allParams);
mysqli_stmt_execute($stmtData);
$resData    = mysqli_stmt_get_result($stmtData);
$operations = [];

while ($row = mysqli_fetch_assoc($resData)) {
    // Reconstruction de l'écriture débit/crédit par type
    $montant = (float) $row['montant'];
    $ref     = $row['motif'] ?? $row['reference_operation'];

    switch ($row['type_operation']) {
        case 'ENTREE':
            $row['ecriture_debit']  = ["compte" => $row['code_source'], "libelle" => $row['libelle_source'], "montant" => $montant, "detail" => "Entrée - $ref"];
            $row['ecriture_credit'] = ["compte" => "Classe 7", "libelle" => "Produit", "montant" => $montant, "detail" => "Produit constaté - $ref"];
            break;
        case 'SORTIE':
            $row['ecriture_debit']  = ["compte" => "Classe 6", "libelle" => "Charge", "montant" => $montant, "detail" => "Charge - $ref"];
            $row['ecriture_credit'] = ["compte" => $row['code_source'], "libelle" => $row['libelle_source'], "montant" => $montant, "detail" => "Sortie - $ref"];
            break;
        case 'TRANSFERT':
            $row['ecriture_debit']  = ["compte" => $row['code_dest'],   "libelle" => $row['libelle_dest'],   "montant" => $montant, "detail" => "Transfert reçu - $ref"];
            $row['ecriture_credit'] = ["compte" => $row['code_source'], "libelle" => $row['libelle_source'], "montant" => $montant, "detail" => "Transfert émis - $ref"];
            break;
    }
    $operations[] = $row;
}
mysqli_stmt_close($stmtData);

// ---------- 5. Totaux ----------
$sqlTotaux = "
    SELECT SUM(o.montant) AS total_mouvements, COUNT(*) AS nb_operations
    FROM operation o
    JOIN portefeuille p           ON o.id_portefeuille       = p.id_portefeuille
    JOIN compte cs                ON o.numcompte_source      = cs.id_compte
    LEFT JOIN plan_comptable pc_s ON cs.id_compte_comptable  = pc_s.id_compte_comptable
    LEFT JOIN compte cd           ON o.numcompte_destination = cd.id_compte
    LEFT JOIN plan_comptable pc_d ON cd.id_compte_comptable  = pc_d.id_compte_comptable
    $where
";
$stmtTotaux = mysqli_prepare($connexion, $sqlTotaux);
if ($types) mysqli_stmt_bind_param($stmtTotaux, $types, ...$params);
mysqli_stmt_execute($stmtTotaux);
$resTotaux = mysqli_stmt_get_result($stmtTotaux);
$totaux    = mysqli_fetch_assoc($resTotaux);
mysqli_stmt_close($stmtTotaux);

// ---------- 6. Réponse ----------
http_response_code(200);
echo json_encode([
    "succes"     => true,
    "pagination" => [
        "page"        => $page,
        "limite"      => $limite,
        "total"       => $total,
        "total_pages" => (int) ceil($total / $limite)
    ],
    "totaux"     => [
        "total_mouvements" => (float) ($totaux['total_mouvements'] ?? 0),
        "nb_operations"    => (int)   ($totaux['nb_operations']    ?? 0)
    ],
    "journal"    => $operations
]);