<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../connexion/connexion.php';

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);

// =============================================
// AJOUTER un compte (POST)
// =============================================
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'ajouter') {

    $nom            = $input['nom_compte'] ?? '';
    $type           = $input['type_compte'] ?? '';
    $numero         = $input['numero_compte'] ?? '';
    $solde_initial  = $input['solde_initial'] ?? 0;
    $id_comptable   = $input['id_compte_comptable'] ?? null;

    if (empty($nom) || empty($type) || empty($numero)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
        exit;
    }

    $sql  = "INSERT INTO compte (nom_compte, type_compte, numero_compte, solde_initial, solde_actuel, id_compte_comptable) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connexion, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssddi", $nom, $type, $numero, $solde_initial, $solde_initial, $id_comptable);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Compte ajouté avec succès']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout']);
        }
        mysqli_stmt_close($stmt);
    }

// =============================================
// VOIR tous les comptes (GET)
// =============================================
} elseif ($method === 'GET') {

    $sql    = "SELECT * FROM compte";
    $result = mysqli_query($connexion, $sql);

    if ($result) {
        $comptes = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $comptes[] = $row;
        }
        echo json_encode(['success' => true, 'comptes' => $comptes]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération']);
    }

// =============================================
// MODIFIER un compte (PUT)
// =============================================
} elseif ($method === 'PUT') {

    $id    = $input['id_compte'] ?? '';
    $nom   = $input['nom_compte'] ?? '';
    $type  = $input['type_compte'] ?? '';
    $solde = $input['solde_actuel'] ?? '';

    if (empty($id) || empty($nom) || empty($type)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
        exit;
    }

    $sql  = "UPDATE compte SET nom_compte = ?, type_compte = ?, solde_actuel = ? WHERE id_compte = ?";
    $stmt = mysqli_prepare($connexion, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssdi", $nom, $type, $solde, $id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Compte modifié avec succès']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
        }
        mysqli_stmt_close($stmt);
    }

// =============================================
// SUPPRIMER un compte (DELETE)
// =============================================
} elseif ($method === 'DELETE') {

    $id = $input['id_compte'] ?? '';

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID du compte manquant']);
        exit;
    }

    $sql  = "DELETE FROM compte WHERE id_compte = ?";
    $stmt = mysqli_prepare($connexion, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Compte supprimé avec succès']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        }
        mysqli_stmt_close($stmt);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}

mysqli_close($connexion);
?>