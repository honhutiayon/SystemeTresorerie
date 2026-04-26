<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'contoller.php'; // contient $operationdb

header("Content-Type: application/json");

// lire JSON brut envoyé par Postman
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "status" => "error",
        "message" => "JSON invalide"
    ]);
    exit;
}

// génération référence
$reference_operation = $operationdb->generateReference();

// récupération des données JSON
$type_operation = trim($data['type_operation'] ?? '');
$montant = (float)($data['montant'] ?? 0);
$motif = trim($data['motif'] ?? '');
$id_portefeuille = (int)($data['id_portefeuille'] ?? 0);

$numcompte_source = trim($data['numcompte_source'] ?? '');
$numcompte_destination = trim($data['numcompte_destination'] ?? '');

// NULL handling
if ($numcompte_destination === "" || $numcompte_destination === "NULL") {
    $numcompte_destination = null;
}

// validation type
$types_valides = ['ENTREE', 'SORTIE', 'TRANSFERT'];

if (!in_array($type_operation, $types_valides)) {
    echo json_encode(["status" => "error", "message" => "type invalide"]);
    exit;
}

// validation simple
if (empty($type_operation) || empty($montant) || empty($id_portefeuille) || empty($numcompte_source)) {
    echo json_encode(["status" => "error", "message" => "champ manquant"]);
    exit;
}

if ($montant <= 0) {
    echo json_encode(["status" => "error", "message" => "montant invalide"]);
    exit;
}

// vérification compte
if (!$operationdb->compteExiste($numcompte_source)) {
    echo json_encode(["status" => "error", "message" => "compte source invalide"]);
    exit;
}

// TRANSFERT
if ($type_operation == 'TRANSFERT') {

    if (!$operationdb->compteExiste($numcompte_destination)) {
        echo json_encode(["status" => "error", "message" => "compte destination invalide"]);
        exit;
    }

    if ($numcompte_source == $numcompte_destination) {
        echo json_encode(["status" => "error", "message" => "comptes identiques"]);
        exit;
    }
}

// SOLDE CHECK
if ($type_operation != 'ENTREE') {

    $solde = $operationdb->getSoldeCompte($numcompte_source);

    if ($solde === null) {
        echo json_encode(["status" => "error", "message" => "compte introuvable"]);
        exit;
    }

    if ($montant > $solde) {
        echo json_encode(["status" => "error", "message" => "solde insuffisant"]);
        exit;
    }
}

try {

    $operationdb->beginTransaction();

    // CREATE OPERATION
    $result = $operationdb->create(
        $reference_operation,
        $type_operation,
        $montant,
        $motif,
        $id_portefeuille,
        $numcompte_source,
        $numcompte_destination
    );

    if (!$result) {
        throw new Exception("erreur insertion operation");
    }

    // UPDATE COMPTES
    if ($type_operation == 'ENTREE') {
        $operationdb->addEntree($numcompte_source, $montant);
    }

    if ($type_operation == 'SORTIE') {
        $operationdb->addSortie($numcompte_source, $montant);
    }

    if ($type_operation == 'TRANSFERT') {
        $operationdb->addSortie($numcompte_source, $montant);
        $operationdb->addEntree($numcompte_destination, $montant);
    }

    $operationdb->commit();

    echo json_encode([
        "status" => "success",
        "reference" => $reference_operation
    ]);

} catch (Exception $e) {

    $operationdb->rollBack();

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>