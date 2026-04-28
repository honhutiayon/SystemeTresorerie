<?php
// ============================================================
//  POST /creation_compte.php
//  Crée un nouveau compte (caisse ou banque)
//  Accepte aussi bien du JSON que du form-data
// ============================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ⚠️ CORS en tout premier — avant tout require, echo ou espace
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../connexion/connexion.php';

$reponse = ['status' => 'error', 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $reponse['message'] = "Méthode non autorisée. Utilisez POST.";
    echo json_encode($reponse);
    exit();
}

// ---------- Lecture des données (JSON ou form-data) ----------
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'application/json')) {
    // Le front-end envoie du JSON → on lit le body brut
    $body = json_decode(file_get_contents("php://input"), true);
    if (!$body) {
        http_response_code(400);
        $reponse['message'] = "Corps JSON invalide ou vide.";
        echo json_encode($reponse);
        exit();
    }
} else {
    // form-data ou x-www-form-urlencoded
    $body = $_POST;
}

// ---------- Extraction des champs ----------
$nom_compte                 = isset($body['nom_compte'])          ? trim($body['nom_compte'])      : null;
$type_compte                = isset($body['type_compte'])         ? trim($body['type_compte'])     : null;
$numero_compte              = isset($body['numero_compte'])       ? trim($body['numero_compte'])   : null;
$total_entree               = isset($body['total_entree'])        ? floatval($body['total_entree']): 0;
$total_sortie               = isset($body['total_sortie'])        ? floatval($body['total_sortie']): 0;
$identifiant_plan_comptable = isset($body['id_compte_comptable']) ? intval($body['id_compte_comptable']) : null;

// ---------- Validation ----------
$manquants = [];
if (empty($nom_compte))                 $manquants[] = "nom_compte";
if (empty($type_compte))                $manquants[] = "type_compte";
if (empty($numero_compte))              $manquants[] = "numero_compte";
if (empty($identifiant_plan_comptable)) $manquants[] = "id_compte_comptable";

if (!empty($manquants)) {
    http_response_code(400);
    $reponse['message'] = "Données manquantes : " . implode(', ', $manquants);
    echo json_encode($reponse);
    exit();
}

// Validation du type_compte
if (!in_array($type_compte, ['caisse', 'banque'])) {
    http_response_code(400);
    $reponse['message'] = "type_compte invalide. Valeurs acceptées : 'caisse' ou 'banque'.";
    echo json_encode($reponse);
    exit();
}

// ---------- Vérification que le numero_compte n'existe pas déjà ----------
$stmtCheck = $connexion->prepare("SELECT id_compte FROM compte WHERE numero_compte = ?");
$stmtCheck->bind_param("s", $numero_compte);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    http_response_code(409);
    $reponse['message'] = "Ce numéro de compte existe déjà.";
    echo json_encode($reponse);
    $stmtCheck->close();
    exit();
}
$stmtCheck->close();

// ---------- Vérification que le plan comptable existe ----------
$stmtPlan = $connexion->prepare("SELECT id_compte_comptable FROM plan_comptable WHERE id_compte_comptable = ?");
$stmtPlan->bind_param("i", $identifiant_plan_comptable);
$stmtPlan->execute();
$stmtPlan->store_result();

if ($stmtPlan->num_rows === 0) {
    http_response_code(404);
    $reponse['message'] = "Le compte comptable id=$identifiant_plan_comptable n'existe pas dans le plan comptable.";
    echo json_encode($reponse);
    $stmtPlan->close();
    exit();
}
$stmtPlan->close();

// ---------- Insertion ----------
$requete_insertion = "INSERT INTO compte (nom_compte, type_compte, numero_compte, total_entree, total_sortie, id_compte_comptable) 
                      VALUES (?, ?, ?, ?, ?, ?)";

$instruction = $connexion->prepare($requete_insertion);

if (!$instruction) {
    http_response_code(500);
    $reponse['message'] = "Erreur préparation requête : " . $connexion->error;
    echo json_encode($reponse);
    exit();
}

$instruction->bind_param("sssddi", $nom_compte, $type_compte, $numero_compte, $total_entree, $total_sortie, $identifiant_plan_comptable);

if ($instruction->execute()) {
    $id_nouveau_compte = $connexion->insert_id;
    http_response_code(201);
    $reponse['status']   = 'success';
    $reponse['message']  = "Compte '$nom_compte' créé avec succès.";
    $reponse['id_compte'] = $id_nouveau_compte;
} else {
    http_response_code(500);
    $reponse['message'] = "Erreur SQL : " . $instruction->error;
}

$instruction->close();
echo json_encode($reponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);