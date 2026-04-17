<?php 

    require_once 'Contoller.php';

    $email = $_POST['email'];
    $password = $_POST['password'];

    if(!empty($email) && !empty($passwword)){
        if(strpos($email,"@")){
           $sql = $utilisateurdb->chaineConnexion($email,$password);
           if(!empty($sql)){
                $_SESSION['profil'] = $sql;
                $_SESSION['error'] = array(
                    'type' => 'information',
                    'message' => 'Bienvenue ' .$sql->nom .' vous êtes connecté en mode ' .$sql->role
                );

                echo "success";
           }
           else{
            echo "email et mot de passe invalide";
           }
        }
        else{
            echo "email invalide";
        }
    }
    else{
        echo "champ demandé svp!!";
    }


?>