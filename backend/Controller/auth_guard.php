<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Ajuste le chemin vers vendor
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function verifierAcces() {
    $cle_secrete = "kZ8!pQ92#mL9vX81*kP02_qZ73@nB64$"; // DOIT être la même que dans login.php
    
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Accès refusé. Token manquant."]);
        exit;
    }

    try {
        $jwt = $matches[1];
        // Décodage et vérification de la signature + expiration
        $decoded = JWT::decode($jwt, new Key($cle_secrete, 'HS256'));
        
        return $decoded->data; // Retourne les infos de l'utilisateur (id, role, etc.)
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Session expirée ou token invalide."]);
        exit;
    }
}