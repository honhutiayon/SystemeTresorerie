<?php

    $connexion = mysqli_connect("localhost", "root", "Faraday08", "tresorerie");
    if (!$connexion) {
        die("Échec de la connexion : " . mysqli_connect_error());
    }
    //echo "Connexion réussie !";

?>