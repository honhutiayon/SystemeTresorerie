<?php
// On s'assure que l'autoload est bien chargé
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Fichier autoload introuvable."]));
}
require_once $autoloadPath;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function verifierAcces() {
    $cle_secrete = "kZ8!pQ92#mL9vX81*kP02_qZ73@nB64$"; 
    $authHeader = null;

    // Méthode ultra-robuste pour récupérer le Header Authorization
    if (isset($_SERVER['Authorization'])) {
        $authHeader = $_SERVER['Authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }

    // Vérification du format Bearer
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Accès refusé. Token manquant."]);
        exit;
    }

    try {
        $jwt = $matches[1];
        // Décodage
        $decoded = JWT::decode($jwt, new Key($cle_secrete, 'HS256'));
        
        // Retourne les données (id, role, etc.)
        return $decoded->data; 
        
    } catch (Exception $e) {
        http_response_code(401);
        // On affiche l'erreur exacte pour le debug (à retirer en production)
        echo json_encode([
            "success" => false, 
            "message" => "Token invalide : " . $e->getMessage()
        ]);
        exit;
    }
}