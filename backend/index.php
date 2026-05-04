<?php

/*header("Content-Type: application/json");

require_once __DIR__ . "/Controller/controlleroperation.php";

$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$uri = str_replace("/SystemeTresorerie/backend", "", $uri);
$uri = trim($uri, "/");

$segments = explode("/", $uri);

$route = $segments[0] ?? null;
$action = $segments[1] ?? null;
$id = $segments[2] ?? null;

switch ($route) {

    case "operations":

        $_GET['action'] = $action;

        if ($id !== null) {
            $_GET['id_compte'] = $id;
        }

        require __DIR__ . "/Controller/controlleroperation.php";
        break;

    default:
        echo json_encode([
            "status" => "error",
            "message" => "Route introuvable"
        ]);
        break;
}*/