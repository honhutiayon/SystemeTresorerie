<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../connexion/connexion.php';

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);

// Voir les types de compte
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'types') {
    $types = [
        ['valeur' => 'caisse', 'libelle' => 'Caisse'],
        ['valeur' => 'banque', 'libelle' => 'Banque']
    ];
    echo json_encode(['success' => true, 'types_compte' => $types]);

// Voir tous les comptes actifs
} elseif ($method === 'GET') {
    $sql    = "SELECT c.*, p.libelle as plan_libelle, p.code_comptable 
               FROM compte c 
               LEFT JOIN plan_comptable p ON c.id_compte_comptable = p.id_compte_comptable
               WHERE c.actif = TRUE";
    $result = mysqli_query($connexion, $sql);
    $comptes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $comptes[] = $row;
    }
    echo json_encode(['success' => true, 'comptes' => $comptes]);

// Ajouter un compte
} elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'ajouter') {
    $nom          = $input['nom_compte'] ?? '';
    $type         = $input['type_compte'] ?? '';
    $numero       = $input['numero_compte'] ?? '';
    $id_comptable = !empty($input['id_compte_comptable']) ? (int)$input['id_compte_comptable'] : null;

    if (empty($nom) || empty($type) || empty($numero)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
        exit;
    }

    if ($id_comptable) {
        $sql  = "INSERT INTO compte (nom_compte, type_compte, numero_compte, id_compte_comptable) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($connexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $nom, $type, $numero, $id_comptable);
    } else {
        $sql  = "INSERT INTO compte (nom_compte, type_compte, numero_compte) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($connexion, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $nom, $type, $numero);
    }

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Compte ajouté avec succès']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout']);
    }
    mysqli_stmt_close($stmt);

// Désactiver un compte
} elseif ($method === 'DELETE') {
    $id = $input['id_compte'] ?? '';

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID du compte manquant']);
        exit;
    }

    $sql  = "UPDATE compte SET actif = FALSE WHERE id_compte = ?";
    $stmt = mysqli_prepare($connexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Compte désactivé avec succès']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la désactivation']);
    }
    mysqli_stmt_close($stmt);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}

mysqli_close($connexion);
?>