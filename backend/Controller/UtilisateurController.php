<?php
require_once 'controller.php';

$action = $_GET['action'];

if ($action == 'create') {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $statut = $_POST['statut'];

    
    if ($role == 'Admin') {
        $role_finale = "admin";
    } elseif ($role == 'Comptable') {
        $role_finale = "comptable";
    } elseif ($role == 'Caissiere') {
        $role_finale = "caissiere";
    } else {
        $role_finale = "controleur";
    }

    
    $statut_finale = "en cours"; 

    if (!empty($nom) && !empty($prenom) && !empty($email) && !empty($password)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            
            $sql = $utilisateurdb->readEmail($email);
            if (!empty($sql)) {
                echo "Cet email existe déjà";
            } else {
                
                $sql1 = $utilisateurdb->readPassword($password);
                if (!empty($sql1)) {
                    echo "Ce mot de passe existe déjà";
                } else {
                    
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    
                    $query = $utilisateurdb->create($nom, $prenom, $email, '', $hashed_password, $role_finale, $statut_finale);
                    echo "success";
                }
            }
        } else {
            echo "Votre email n'est pas valide";
        }
    } else {
        echo "Tous les champs sont à remplir";
    }
}

if ($action == 'update') {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $statut = $_POST['statut'];
    $id_utilisateur = $_POST['id_user'];

    if ($role == 'Admin') {
        $role_finale = "admin";
    } elseif ($role == 'Comptable') {
        $role_finale = "comptable";
    } elseif ($role == 'Caissiere') {
        $role_finale = "caissiere";
    } else {
        $role_finale = "controleur";
    }

    
    if ($statut == 'en cour') {
        $statut_finale = "en cour";
    } elseif ($statut == 'Valide') {
        $statut_finale = "validé";
    } else {
        $statut_finale = "admis";
    }

    if (!empty($nom) && !empty($prenom) && !empty($email) && !empty($password)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        
            
            $sql = $utilisateurdb->readEmail($email, $id_utilisateur); 
            if (!empty($sql)) {
                echo "Cet email existe déjà";
            } else {
                
                
                $sql1 = $utilisateurdb->readPassword($password);
                if (!empty($sql1)) {
                    echo "Ce mot de passe existe déjà";
                } else {
                    
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    
                    $query = $utilisateurdb->update($nom, $prenom, $email, $hashed_password, $role_finale, $statut_finale, $id_utilisateur);
                    echo "success";
                }
            }
        } else {
            echo "Votre email n'est pas valide";
        }
    } else {
        echo "Tous les champs sont à remplir";
    }
}

if ($action == 'delete') {
    $id_user = $_GET['id'];
    $utilisateurdb->delete($id_user);
    $_SESSION['error'] = array(
        'type' => 'information',
        'message' => 'Utilisateur supprimé avec succès'
    );
    
}
?>