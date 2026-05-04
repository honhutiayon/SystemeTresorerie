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

require_once 'contoller.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;

switch ($action) {
    // =========================
    // CREATE OPERATION
    // =========================
    case 'create':
        // LECTURE JSON (IMPORTANT)
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            exit(json_encode([
                "status" => "error",
                "message" => "JSON invalide"
            ]));
        }

        // Récupération des données
        $type_operation = strtoupper(trim($input['type_operation'] ?? ''));
        $montant = (float) ($input['montant'] ?? 0);
        $motif = trim($input['motif'] ?? '');
        $id_portefeuille = (int) ($input['id_portefeuille'] ?? 0);
        $numcompte_source = (int) ($input['numcompte_source'] ?? 0);
        $numcompte_destination = !empty($input['numcompte_destination']) ? (int) $input['numcompte_destination'] : null;

        // TYPES VALIDES
        $types_valides = ['ENTREE', 'SORTIE', 'TRANSFERT'];
        if (!in_array($type_operation, $types_valides)) {
            exit(json_encode([
                "status" => "error",
                "message" => "Type d'opération invalide"
            ]));
        }

        // Validation des champs
        if ($montant <= 0 || $id_portefeuille <= 0 || $numcompte_source <= 0) {
            exit(json_encode([
                "status" => "error",
                "message" => "Champs invalides"
            ]));
        }

        // Vérification du compte source
        if (!$operationdb->compteExiste($numcompte_source)) {
            exit(json_encode([
                "status" => "error",
                "message" => "Compte source invalide"
            ]));
        }

        // Vérification pour les transferts
        if ($type_operation === 'TRANSFERT') {
            if (!$numcompte_destination || !$operationdb->compteExiste($numcompte_destination)) {
                exit(json_encode([
                    "status" => "error",
                    "message" => "Compte destination invalide"
                ]));
            }
            if ($numcompte_source === $numcompte_destination) {
                exit(json_encode([
                    "status" => "error",
                    "message" => "Comptes identiques interdits"
                ]));
            }
        }

        // Vérification du solde
        if (in_array($type_operation, ['SORTIE', 'TRANSFERT'])) {
            $solde = $operationdb->getSoldeCompte($numcompte_source);
            if ($solde === null) {
                exit(json_encode([
                    "status" => "error",
                    "message" => "Compte introuvable"
                ]));
            }
            if ($montant > $solde) {
                exit(json_encode([
                    "status" => "error",
                    "message" => "Solde insuffisant"
                ]));
            }
        }

        // Traitement de l'opération
        try {
            $operationdb->beginTransaction();
            $reference_operation = $operationdb->generateReference();

            // 1. INSERT OPERATION
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
                throw new Exception("Erreur insertion operation");
            }

            // 2. Mise à jour des comptes
            if ($type_operation === 'ENTREE') {
                $operationdb->addEntree($numcompte_source, $montant);
            }
            if ($type_operation === 'SORTIE') {
                $operationdb->addSortie($numcompte_source, $montant);
            }
            if ($type_operation === 'TRANSFERT') {
                $operationdb->addSortie($numcompte_source, $montant);
                $operationdb->addEntree($numcompte_destination, $montant);
            }

            $operationdb->commit();

            echo json_encode([
                "status" => "success",
                "message" => "Opération effectuée avec succès",
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
    // LIST OPERATIONS
    // =========================
    case 'list':
        $data = $operationdb->readAll();
        echo json_encode([
            "status" => "success",
            "data" => $data
        ]);
        break;

    // =========================
    // GET ONE OPERATION
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
    // ACTION INVALID
    // =========================
    
        echo json_encode([
            "status" => "error",
            "message" => "Action invalide"
        ]);
        break;



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

    default:
}




?>
