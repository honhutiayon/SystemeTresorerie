<?php

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header('Content-Type: application/json');

    // On ne fait RIEN sur le serveur, on envoie juste un signal de succès
    echo json_encode([
        "success" => true,
        "message" => "Déconnexion réussie. Pensez à supprimer le token côté client."
    ]);

?>
