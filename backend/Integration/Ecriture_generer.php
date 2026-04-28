<?php
// ============================================================
//  POST /Integration/ecritures_generer.php
//  Génère les écritures comptables (débit/crédit) pour une
//  opération donnée, en se basant sur les tables existantes.
//  Body JSON : { "id_operation": 12 }
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
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once __DIR__ . '/../../connexion/connexion.php';
// $connexion = connexion mysqli disponible via connexion.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["succes" => false, "message" => "Méthode non autorisée. Utilisez POST."]);
    exit();
}

// ---------- 1. Lecture du corps JSON ----------
$body = json_decode(file_get_contents("php://input"), true);

if (!isset($body['id_operation']) || !is_numeric($body['id_operation'])) {
    http_response_code(400);
    echo json_encode(["succes" => false, "message" => "Le champ 'id_operation' est requis et doit être un entier."]);
    exit();
}

$id_operation = (int) $body['id_operation'];

// ---------- 2. Récupération de l'opération avec ses comptes ----------
$sql = "
    SELECT 
        o.id_operation,
        o.reference_operation,
        o.type_operation,
        o.montant,
        o.motif,
        o.statut,
        cs.nom_compte          AS nom_compte_source,
        pc_s.code_comptable    AS code_source,
        pc_s.libelle           AS libelle_source,
        cd.nom_compte          AS nom_compte_dest,
        pc_d.code_comptable    AS code_dest,
        pc_d.libelle           AS libelle_dest
    FROM operation o
    JOIN portefeuille p            ON o.id_portefeuille       = p.id_portefeuille
    JOIN compte cs                 ON o.numcompte_source      = cs.id_compte
    LEFT JOIN plan_comptable pc_s  ON cs.id_compte_comptable  = pc_s.id_compte_comptable
    LEFT JOIN compte cd            ON o.numcompte_destination = cd.id_compte
    LEFT JOIN plan_comptable pc_d  ON cd.id_compte_comptable  = pc_d.id_compte_comptable
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

if ($op['statut'] !== 'VALIDE') {
    http_response_code(422);
    echo json_encode([
        "succes"  => false,
        "message" => "Seules les opérations avec statut VALIDE peuvent générer des écritures. Statut actuel : " . $op['statut']
    ]);
    exit();
}

// ---------- 3. Construction des écritures selon le type ----------
// Règle OHADA simplifiée :
//   ENTREE    → Débit  compte source (caisse/banque)  | Crédit compte produit classe 7
//   SORTIE    → Débit  compte charge classe 6          | Crédit compte source
//   TRANSFERT → Débit  compte destination              | Crédit compte source

$montant   = (float) $op['montant'];
$ecritures = [];
$ref       = $op['motif'] ?? $op['reference_operation'];

switch ($op['type_operation']) {

    case 'ENTREE':
        $ecritures[] = [
            "sens"           => "DEBIT",
            "code_compte"    => $op['code_source'],
            "libelle_compte" => $op['libelle_source'],
            "libelle"        => "Entrée de fonds - " . $ref,
            "montant"        => $montant
        ];
        $cp = obtenirCompteParClasse($connexion, 7);
        $ecritures[] = [
            "sens"           => "CREDIT",
            "code_compte"    => $cp['code_comptable'],
            "libelle_compte" => $cp['libelle'],
            "libelle"        => "Produit constaté - " . $ref,
            "montant"        => $montant
        ];
        break;

    case 'SORTIE':
        $cc = obtenirCompteParClasse($connexion, 6);
        $ecritures[] = [
            "sens"           => "DEBIT",
            "code_compte"    => $cc['code_comptable'],
            "libelle_compte" => $cc['libelle'],
            "libelle"        => "Charge enregistrée - " . $ref,
            "montant"        => $montant
        ];
        $ecritures[] = [
            "sens"           => "CREDIT",
            "code_compte"    => $op['code_source'],
            "libelle_compte" => $op['libelle_source'],
            "libelle"        => "Sortie de fonds - " . $ref,
            "montant"        => $montant
        ];
        break;

    case 'TRANSFERT':
        if (empty($op['code_dest'])) {
            http_response_code(422);
            echo json_encode(["succes" => false, "message" => "Compte destination manquant ou sans compte comptable associé."]);
            exit();
        }
        $ecritures[] = [
            "sens"           => "DEBIT",
            "code_compte"    => $op['code_dest'],
            "libelle_compte" => $op['libelle_dest'],
            "libelle"        => "Transfert reçu - " . $ref,
            "montant"        => $montant
        ];
        $ecritures[] = [
            "sens"           => "CREDIT",
            "code_compte"    => $op['code_source'],
            "libelle_compte" => $op['libelle_source'],
            "libelle"        => "Transfert émis - " . $ref,
            "montant"        => $montant
        ];
        break;

    default:
        http_response_code(422);
        echo json_encode(["succes" => false, "message" => "Type d'opération inconnu : " . $op['type_operation']]);
        exit();
}

// ---------- 4. Vérification de l'équilibre comptable ----------
$total_debit  = 0;
$total_credit = 0;
foreach ($ecritures as $e) {
    if ($e['sens'] === 'DEBIT')  $total_debit  += $e['montant'];
    if ($e['sens'] === 'CREDIT') $total_credit += $e['montant'];
}

if (abs($total_debit - $total_credit) > 0.01) {
    http_response_code(500);
    echo json_encode(["succes" => false, "message" => "Déséquilibre comptable détecté. Opération annulée."]);
    exit();
}

// ---------- 5. Réponse ----------
http_response_code(200);
echo json_encode([
    "succes"         => true,
    "message"        => "Écritures comptables générées avec succès.",
    "id_operation"   => $id_operation,
    "reference"      => $op['reference_operation'],
    "type_operation" => $op['type_operation'],
    "montant"        => $montant,
    "total_debit"    => $total_debit,
    "total_credit"   => $total_credit,
    "equilibre"      => true,
    "ecritures"      => $ecritures
]);

// ============================================================
//  Utilitaire : premier compte d'une classe OHADA
// ============================================================
function obtenirCompteParClasse($connexion, int $classe): array {
    $stmt = mysqli_prepare($connexion, "
        SELECT code_comptable, libelle 
        FROM plan_comptable 
        WHERE classe = ? 
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $classe);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        http_response_code(500);
        echo json_encode(["succes" => false, "message" => "Aucun compte de classe $classe dans le plan comptable."]);
        exit();
    }
    return $row;
}