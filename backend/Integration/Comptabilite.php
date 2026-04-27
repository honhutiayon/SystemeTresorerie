
<?php
/**
 * Script de génération d'écritures comptables
 * Projet : Système de Trésorerie
 * Style : Procédural sans abréviations
 */

// -------------------------------------------------------------------------
// 1. CONFIGURATION ET CONNEXION
// -------------------------------------------------------------------------

  
// Autorise l'origine de ton frontend (localhost)
    header("Access-Control-Allow-Origin: *"); 

    // Autorise les méthodes HTTP utilisées (GET, POST, etc.)
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

    // Autorise les headers spécifiques (très important pour l'AJAX)
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    // Si c'est une requête de type OPTIONS (preflight), on arrête ici
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit;
    }
    
// Définition du format de réponse
header('Content-Type: application/json; charset=UTF-8');

/**
 * RÉCUPÉRATION DES PARAMÈTRES
 * On cherche les identifiants dans l'URL. 
 * Si absents, on utilise des valeurs par défaut pour faciliter tes tests.
 */
$identifiant_operation = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$identifiant_compte_charge = isset($_GET['charge']) ? (int)$_GET['charge'] : 1; 

$chemin_connexion = "../connexion/connexion.php";

if (!file_exists($chemin_connexion)) {
    die(json_encode([
        'succes' => false,
        'message' => "Erreur critique : Le fichier de connexion est introuvable."
    ]));
}

require_once $chemin_connexion;

$reponse = [
    'succes' => false, 
    'message' => '', 
    'ecritures' => []
];

// -------------------------------------------------------------------------
// 2. RÉCUPÉRATION DE L'OPÉRATION
// -------------------------------------------------------------------------
try {
    if (!isset($connexion)) {
        throw new Exception("La variable de connexion \$connexion n'est pas définie dans le fichier inclus.");
    }

    $instruction_operation = $connexion->prepare("SELECT * FROM operation WHERE id_operation = ?");
    $instruction_operation->bind_param('i', $identifiant_operation);
    $instruction_operation->execute();
    $resultat_operation = $instruction_operation->get_result();
    $donnees_operation = $resultat_operation->fetch_assoc();
    $instruction_operation->close();

    if (!$donnees_operation) {
        throw new Exception("Aucune opération trouvée avec l'identifiant : $identifiant_operation");
    }

    $tableau_ecritures = [];
    $type_operation = $donnees_operation['type_operation'];
    $montant_operation = (float)$donnees_operation['montant'];
    $reference_operation = $donnees_operation['reference_operation'];
    $motif_operation = $donnees_operation['motif'];

    // -------------------------------------------------------------------------
    // 3. LOGIQUE MÉTIER : GÉNÉRATION DES LIGNES
    // -------------------------------------------------------------------------
    
    if ($type_operation === 'ENTREE') {
        /**
         * ENTREE : Débit Trésorerie (Destination) / Crédit Produit (Classe 7)
         */
        $requete_destination = $connexion->prepare("
            SELECT plan_comptable.* FROM compte 
            JOIN plan_comptable ON compte.id_compte_comptable = plan_comptable.id_compte_comptable 
            WHERE compte.id_compte = ?
        ");
        $requete_destination->bind_param('i', $donnees_operation['numcompte_destination']);
        $requete_destination->execute();
        $compte_comptable_destination = $requete_destination->get_result()->fetch_assoc();
        
        $resultat_produit = $connexion->query("SELECT * FROM plan_comptable WHERE classe = 7 ORDER BY code_comptable ASC LIMIT 1");
        $compte_comptable_produit = $resultat_produit->fetch_assoc();

        if (!$compte_comptable_destination || !$compte_comptable_produit) {
            throw new Exception("Paramètres comptables manquants pour enregistrer cette entrée.");
        }

        $tableau_ecritures[] = creerLigneEcriture('DEBIT', $compte_comptable_destination, $montant_operation, "Encaissement : $motif_operation", $reference_operation);
        $tableau_ecritures[] = creerLigneEcriture('CREDIT', $compte_comptable_produit, $montant_operation, "Produit : $motif_operation", $reference_operation);

    } elseif ($type_operation === 'SORTIE') {
        /**
         * SORTIE : Débit Charge (Classe 6) / Crédit Trésorerie (Source)
         */
        $requete_charge = $connexion->prepare("SELECT * FROM plan_comptable WHERE id_compte_comptable = ? AND classe = 6");
        $requete_charge->bind_param('i', $identifiant_compte_charge);
        $requete_charge->execute();
        $compte_comptable_charge = $requete_charge->get_result()->fetch_assoc();

        $requete_source = $connexion->prepare("
            SELECT plan_comptable.* FROM compte 
            JOIN plan_comptable ON compte.id_compte_comptable = plan_comptable.id_compte_comptable 
            WHERE compte.id_compte = ?
        ");
        $requete_source->bind_param('i', $donnees_operation['numcompte_source']);
        $requete_source->execute();
        $compte_comptable_source = $requete_source->get_result()->fetch_assoc();

        if (!$compte_comptable_charge || !$compte_comptable_source) {
            throw new Exception("Le compte de charge (ID: $identifiant_compte_charge) ou le compte source est invalide.");
        }

        $tableau_ecritures[] = creerLigneEcriture('DEBIT', $compte_comptable_charge, $montant_operation, "Charge : $motif_operation", $reference_operation);
        $tableau_ecritures[] = creerLigneEcriture('CREDIT', $compte_comptable_source, $montant_operation, "Paiement : $motif_operation", $reference_operation);

    } elseif ($type_operation === 'TRANSFERT') {
        /**
         * TRANSFERT : Débit Destination / Crédit Source
         */
        $requete_src = $connexion->prepare("SELECT plan_comptable.* FROM compte JOIN plan_comptable ON compte.id_compte_comptable = plan_comptable.id_compte_comptable WHERE compte.id_compte = ?");
        $requete_src->bind_param('i', $donnees_operation['numcompte_source']);
        $requete_src->execute();
        $donnees_source = $requete_src->get_result()->fetch_assoc();

        $requete_dest = $connexion->prepare("SELECT plan_comptable.* FROM compte JOIN plan_comptable ON compte.id_compte_comptable = plan_comptable.id_compte_comptable WHERE compte.id_compte = ?");
        $requete_dest->bind_param('i', $donnees_operation['numcompte_destination']);
        $requete_dest->execute();
        $donnees_dest = $requete_dest->get_result()->fetch_assoc();

        if (!$donnees_source || !$donnees_dest) {
            throw new Exception("Comptes de trésorerie introuvables pour le transfert.");
        }

        $tableau_ecritures[] = creerLigneEcriture('DEBIT', $donnees_dest, $montant_operation, "Réception : $motif_operation", $reference_operation);
        $tableau_ecritures[] = creerLigneEcriture('CREDIT', $donnees_source, $montant_operation, "Émission : $motif_operation", $reference_operation);
    }

    $reponse['succes'] = true;
    $reponse['message'] = "Génération réussie.";
    $reponse['ecritures'] = $tableau_ecritures;

} catch (Exception $erreur_detectee) {
    $reponse['message'] = "Erreur : " . $erreur_detectee->getMessage();
}

/**
 * Fonction pour créer une ligne d'écriture formatée
 */
function creerLigneEcriture($sens, $donnees, $montant, $libelle, $reference) {
    return [
        'sens' => $sens,
        'id_compte_comptable' => $donnees['id_compte_comptable'],
        'code_comptable' => $donnees['code_comptable'],
        'libelle_compte' => $donnees['libelle'],
        'montant' => $montant,
        'libelle_ecriture' => $libelle,
        'reference' => $reference
    ];
}

echo json_encode($reponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);