<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization ");
header("Content-Type: Application/json; charset=UTF-8");


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);


require_once 'contoller.php'; // contient $operationdb
/*

header('Content-Type: application/json');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization ");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'contoller.php'; // contient $operationdb 

header('Content-Type: application/json'); */

$action = $_GET['action'] ?? null;

switch ($action) {

    // =========================
    // CREATE OPERATION
    // =========================
    case 'create':

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            echo json_encode([
                "status" => "error",
                "message" => "JSON invalide"
            ]);
            exit;
        }

        $type_operation = strtoupper(trim($input['type_operation'] ?? ''));
        $montant = (float) ($input['montant'] ?? 0);
        $motif = trim($input['motif'] ?? '');
        $id_portefeuille = (int) ($input['id_portefeuille'] ?? 0);
        $numcompte_source = (int) ($input['numcompte_source'] ?? 0);
        $numcompte_destination = !empty($input['numcompte_destination']) ? (int) $input['numcompte_destination'] : null;

        $types_valides = ['ENTREE', 'SORTIE', 'TRANSFERT'];

        if (!in_array($type_operation, $types_valides)) {
            echo json_encode([
                "status" => "error",
                "message" => "Type d'opération invalide"
            ]);
            exit;
        }

        if ($montant <= 0 || $id_portefeuille <= 0 || $numcompte_source <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Champs invalides"
            ]);
            exit;
        }

        if (!$operationdb->compteExiste($numcompte_source)) {
            echo json_encode([
                "status" => "error",
                "message" => "Compte source invalide"
            ]);
            exit;
        }

        if ($type_operation === 'TRANSFERT') {

            if (!$numcompte_destination || !$operationdb->compteExiste($numcompte_destination)) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Compte destination invalide"
                ]);
                exit;
            }

            if ($numcompte_source === $numcompte_destination) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Comptes identiques interdits"
                ]);
                exit;
            }
        }

        try {

            $operationdb->beginTransaction();

            $reference_operation = $operationdb->generateReference();

            $result = $operationdb->create(
                $reference_operation,
                $type_operation,
                $montant,
                $motif,
                $id_portefeuille,
                $numcompte_source,
                $numcompte_destination,
                'EN_COURS'
            );

            if (!$result) {
                throw new Exception("Erreur insertion opération");
            }

            $operationdb->commit();

            echo json_encode([
                "status" => "success",
                "message" => "Opération créée (en attente de validation)",
                "reference" => $reference_operation
            ]);

        } catch (Exception $e) {
            $operationdb->rollBack();

            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }

        break;

    // =========================
    // LIST
    // =========================
    case 'list':

        $data = $operationdb->readAll();

        echo json_encode([
            "status" => "success",
            "data" => $data
        ]);

        break;

    // =========================
    // GET ONE
    // =========================
    case 'get':

        $id = (int) ($_GET['id'] ?? 0);
        $data = $operationdb->read($id);

        if (!$data) {
            echo json_encode([
                "status" => "error",
                "message" => "Opération introuvable"
            ]);
        } else {
            echo json_encode([
                "status" => "success",
                "data" => $data
            ]);
        }

        break;

    // =========================
    // SOLDE
    // =========================
    case 'solde':

        $id_compte = (int) ($_GET['id_compte'] ?? 0);

        if ($id_compte <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Compte invalide"
            ]);
            exit;
        }

        if (!$operationdb->compteExiste($id_compte)) {
            echo json_encode([
                "status" => "error",
                "message" => "Compte introuvable"
            ]);
            exit;
        }

        $solde = $operationdb->getSoldeCompte($id_compte);

        echo json_encode([
            "status" => "success",
            "id_compte" => $id_compte,
            "solde" => $solde
        ]);

        break;

    // =========================
    // STATUS (VALIDER / ANNULER)
    // =========================
    case 'status':

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            echo json_encode([
                "status" => "error",
                "message" => "JSON invalide"
            ]);
            exit;
        }

        $id_operation = (int) ($input['id_operation'] ?? 0);
        $statut = strtoupper(trim($input['statut'] ?? ''));

        $statuts_valides = ['EN_COURS', 'VALIDE', 'ANNULEE'];

        if ($id_operation <= 0) {
            echo json_encode([
                "status" => "error",
                "message" => "ID opération invalide"
            ]);
            exit;
        }

        if (!in_array($statut, $statuts_valides)) {
            echo json_encode([
                "status" => "error",
                "message" => "Statut invalide"
            ]);
            exit;
        }

        try {

            $operation = $operationdb->read($id_operation);

            if (!$operation) {
                throw new Exception("Opération introuvable");
            }

            if ($operation->statut === $statut) {
                throw new Exception("Statut déjà appliqué");
            }

            $operationdb->beginTransaction();

            // =========================
            // VALIDATION
            // =========================
            if ($statut === 'VALIDE' && $operation->statut !== 'VALIDE') {

                if ($operation->type_operation === 'ENTREE') {
                    $operationdb->addEntree($operation->numcompte_source, $operation->montant);
                }

                if ($operation->type_operation === 'SORTIE') {
                    $operationdb->addSortie($operation->numcompte_source, $operation->montant);
                }

                if ($operation->type_operation === 'TRANSFERT') {
                    $operationdb->addSortie($operation->numcompte_source, $operation->montant);
                    $operationdb->addEntree($operation->numcompte_destination, $operation->montant);
                }
            }

            // =========================
            // ANNULATION (INVERSION SI DÉJÀ VALIDÉ)
            // =========================
            if ($statut === 'ANNULEE' && $operation->statut === 'VALIDE') {

                if ($operation->type_operation === 'ENTREE') {
                    $operationdb->addSortie($operation->numcompte_source, $operation->montant);
                }

                if ($operation->type_operation === 'SORTIE') {
                    $operationdb->addEntree($operation->numcompte_source, $operation->montant);
                }

                if ($operation->type_operation === 'TRANSFERT') {
                    $operationdb->addEntree($operation->numcompte_source, $operation->montant);
                    $operationdb->addSortie($operation->numcompte_destination, $operation->montant);
                }
            }

            $result = $operationdb->updateStatus($id_operation, $statut);

            if (!$result) {
                throw new Exception("Erreur mise à jour statut");
            }

            $operationdb->commit();

            echo json_encode([
                "status" => "success",
                "message" => "Statut mis à jour"
            ]);

        } catch (Exception $e) {
            $operationdb->rollBack();

            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }

        break;

    // =========================
    // DEFAULT
    // =========================
    default:

       /*echo json_encode([
            "status" => "error",
            "message" => "Action invalide"
        ]);*/

        break;
}
?>