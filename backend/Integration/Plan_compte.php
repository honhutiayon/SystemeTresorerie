<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// ============================================================
//  GET /Integration/plan_comptes.php
//  Retourne le plan de comptes OHADA.
//
//  Paramètres GET (tous optionnels) :
//    classe  : 1 à 7
//    type    : actif | passif | charge | produit
//    q       : recherche sur code ou libellé
// ============================================================

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

// ---------- 1. Filtres ----------
$classe = isset($_GET['classe']) && is_numeric($_GET['classe']) ? (int) $_GET['classe'] : null;
$type   = isset($_GET['type'])   && in_array($_GET['type'], ['actif','passif','charge','produit'])
            ? $_GET['type'] : null;
$q      = isset($_GET['q']) ? trim($_GET['q']) : null;

if ($classe !== null && ($classe < 1 || $classe > 7)) {
    http_response_code(400);
    echo json_encode(["succes" => false, "message" => "La classe doit être entre 1 et 7."]);
    exit();
}

// ---------- 2. Construction de la requête ----------
$conditions = ["1=1"];
$params     = [];
$types      = "";

if ($classe !== null) {
    $conditions[] = "classe = ?";
    $params[]     = $classe;
    $types       .= "i";
}
if ($type) {
    $conditions[] = "type_mouvement = ?";
    $params[]     = $type;
    $types       .= "s";
}
if ($q) {
    $conditions[] = "(code_comptable LIKE ? OR libelle LIKE ?)";
    $like         = '%' . $q . '%';
    $params[]     = $like;
    $params[]     = $like;
    $types       .= "ss";
}

$where = "WHERE " . implode(" AND ", $conditions);

$sql  = "
    SELECT 
        id_compte_comptable,
        code_comptable,
        libelle,
        classe,
        type_mouvement,
        solde_normal
    FROM plan_comptable
    $where
    ORDER BY code_comptable ASC
";

$stmt = mysqli_prepare($connexion, $sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$comptes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $comptes[] = $row;
}
mysqli_stmt_close($stmt);

// ---------- 3. Regroupement par classe pour le front ----------
$groupes = [];
foreach ($comptes as $c) {
    $groupes[(int)$c['classe']][] = $c;
}
ksort($groupes);

// ---------- 4. Réponse ----------
http_response_code(200);
echo json_encode([
    "succes"  => true,
    "total"   => count($comptes),
    "groupes" => $groupes,
    "comptes" => $comptes
]);