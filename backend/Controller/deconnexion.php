<?php

    session_start();

    // 2. Vider toutes les valeurs de la session (dont 'id_utilisateur')
    $_SESSION = array();

    // 4. Détruire la session sur le serveur
    session_destroy();

    // 5. Réponse JSON pour confirmer la sortie
    header('Content-Type: application/json');
    echo json_encode([
        "success" => true,
        "message" => "Utilisateur deconnecte avec succes."
    ]);

?>