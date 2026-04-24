<?php
/**
 * Script procédural : Génération d'écritures comptables
 * Version sans abréviation (sauf $conn) pour une clarté maximale.
 */

// Simulation des paramètres d'entrée
$identifiant_operation = 123; 
$identifiant_compte_charge = 45; 

// Inclusion du fichier de connexion (doit fournir la variable $conn)
require_once "../connexion/connexion.php";

// Initialisation de la réponse finale
$reponse = [
    'succes' => false, 
    'message' => '', 
    'ecritures' => []
];

// -------------------------------------------------------------------------
// 1. RÉCUPÉRATION DE L'OPÉRATION DANS LA BASE DE DONNÉES
// -------------------------------------------------------------------------
$instruction_operation = $conn->prepare("SELECT * FROM operation WHERE id_operation = ?");
$instruction_operation->bind_param('i', $identifiant_operation);
$instruction_operation->execute();
$resultat_operation = $instruction_operation->get_result();
$donnees_operation = $resultat_operation->fetch_assoc();
$instruction_operation->close();

if (!$donnees_operation) {
    $reponse['message'] = "Erreur : L'opération demandée est introuvable dans la base de données.";
} else {
    try {
        $tableau_ecritures = [];
        $type_operation = $donnees_operation['type_operation'];
        $montant_operation = $donnees_operation['montant'];
        $reference_operation = $donnees_operation['reference_operation'];
        $motif_operation = $donnees_operation['motif'];

        // -------------------------------------------------------------------------
        // 2. ANALYSE DU TYPE D'OPÉRATION ET GÉNÉRATION DES LIGNES
        // -------------------------------------------------------------------------
        
        if ($type_operation === 'ENTREE') {
            /**
             * SCÉNARIO ENTREE : 
             * DEBIT  -> Compte de trésorerie (Banque ou Caisse destination)
             * CREDIT -> Compte de produit (Classe 7)
             */
            
            // Recherche du compte comptable lié à la caisse de destination
            $requete_destination = $conn->prepare("
                SELECT plan_comptable.* FROM compte 
                JOIN plan_comptable ON compte.id_compte_comptable = plan_comptable.id_compte_comptable 
                WHERE compte.id_compte = ?
            ");
            $requete_destination->bind_param('i', $donnees_operation['numcompte_destination']);
            $requete_destination->execute();
            $compte_comptable_destination = $requete_destination->get_result()->fetch_assoc();
            
            // Recherche du premier compte de produit disponible (Classe 7)
            $resultat_produit = $conn->query("SELECT * FROM plan_comptable WHERE classe = 7 ORDER BY code_comptable ASC LIMIT 1");
            $compte_comptable_produit = $resultat_produit->fetch_assoc();

            if (!$compte_comptable_destination || !$compte_comptable_produit) {
                throw new Exception("Configuration manquante : Le compte destination ou le compte de produit (Classe 7) n'est pas défini.");
            }

            $tableau_ecritures[] = [
                'sens' => 'DEBIT', 
                'id_compte_comptable' => $compte_comptable_destination['id_compte_comptable'], 
                'code_comptable' => $compte_comptable_destination['code_comptable'], 
                'libelle_compte' => $compte_comptable_destination['libelle'], 
                'montant' => $montant_operation, 
                'libelle_ecriture' => "Encaissement : " . $motif_operation, 
                'reference' => $reference_operation
            ];

            $tableau_ecritures[] = [
                'sens' => 'CREDIT', 
                'id_compte_comptable' => $compte_comptable_produit['id_compte_comptable'], 
                'code_comptable' => $compte_comptable_produit['code_comptable'], 
                'libelle_compte' => $compte_comptable_produit['libelle'], 
                'montant' => $montant_operation, 
                'libelle_ecriture' => "Origine du produit : " . $motif_operation, 
                'reference' => $reference_operation
            ];

        } elseif ($type_operation === 'SORTIE') {
            /**
             * SCÉNARIO SORTIE : 
             * DEBIT  -> Compte de charge choisi (Classe 6)
             * CREDIT -> Compte de trésorerie (Banque ou Caisse source)
             */
            if (!$identifiant_compte_charge) {
                throw new Exception("Erreur : Un compte de charge est obligatoire pour enregistrer une sortie.");
            }

            // Vérification du compte de charge
            $requete_charge = $conn->prepare("SELECT * FROM plan_comptable WHERE id_compte_comptable = ? AND classe = 6");
            $requete_charge->bind_param('i', $identifiant_compte_charge);
            $requete_charge->execute();
            $compte_comptable_charge = $requete_charge->get_result()->fetch_assoc();

            // Recherche du compte comptable lié à la caisse source
            $requete_source = $conn->prepare("
                SELECT plan_comptable.* FROM compte 
                JOIN plan_comptable ON compte.id_compte_comptable = plan_comptable.id_compte_comptable 
                WHERE compte.id_compte = ?
            ");
            $requete_source->bind_param('i', $donnees_operation['numcompte_source']);
            $requete_source->execute();
            $compte_comptable_source = $requete_source->get_result()->fetch_assoc();

            if (!$compte_comptable_charge || !$compte_comptable_source) {
                throw new Exception("Erreur : Le compte de charge ou le compte de trésorerie source est invalide.");
            }

            $tableau_ecritures[] = [
                'sens' => 'DEBIT', 
                'id_compte_comptable' => $compte_comptable_charge['id_compte_comptable'], 
                'code_comptable' => $compte_comptable_charge['code_comptable'], 
                'libelle_compte' => $compte_comptable_charge['libelle'], 
                'montant' => $montant_operation, 
                'libelle_ecriture' => "Charge enregistrée : " . $motif_operation, 
                'reference' => $reference_operation
            ];

            $tableau_ecritures[] = [
                'sens' => 'CREDIT', 
                'id_compte_comptable' => $compte_comptable_source['id_compte_comptable'], 
                'code_comptable' => $compte_comptable_source['code_comptable'], 
                'libelle_compte' => $compte_comptable_source['libelle'], 
                'montant' => $montant_operation, 
                'libelle_ecriture' => "Décaissement trésorerie : " . $motif_operation, 
                'reference' => $reference_operation
            ];

        } elseif ($type_operation === 'TRANSFERT') {
            /**
             * SCÉNARIO TRANSFERT : 
             * DEBIT  -> Compte de trésorerie (Destination)
             * CREDIT -> Compte de trésorerie (Source)
             */
            
            // Infos compte Source
            $requete_transfert_source = $conn->prepare("SELECT plan_comptable.* FROM compte JOIN plan_comptable ON compte.id_compte_comptable = plan_comptable.id_compte_comptable WHERE compte.id_compte = ?");
            $requete_transfert_source->bind_param('i', $donnees_operation['numcompte_source']);
            $requete_transfert_source->execute();
            $donnees_source = $requete_transfert_source->get_result()->fetch_assoc();

            // Infos compte Destination
            $requete_transfert_destination = $conn->prepare("SELECT plan_comptable.* FROM compte JOIN plan_comptable ON compte.id_compte_comptable = plan_comptable.id_compte_comptable WHERE compte.id_compte = ?");
            $requete_transfert_destination->bind_param('i', $donnees_operation['numcompte_destination']);
            $requete_transfert_destination->execute();
            $donnees_destination = $requete_transfert_destination->get_result()->fetch_assoc();

            if (!$donnees_source || !$donnees_destination) {
                throw new Exception("Erreur : Impossible d'identifier les comptes source ou destination pour le transfert.");
            }

            $tableau_ecritures[] = [
                'sens' => 'DEBIT', 
                'id_compte_comptable' => $donnees_destination['id_compte_comptable'], 
                'code_comptable' => $donnees_destination['code_comptable'], 
                'libelle_compte' => $donnees_destination['libelle'], 
                'montant' => $montant_operation, 
                'libelle_ecriture' => "Réception transfert : " . $motif_operation, 
                'reference' => $reference_operation
            ];

            $tableau_ecritures[] = [
                'sens' => 'CREDIT', 
                'id_compte_comptable' => $donnees_source['id_compte_comptable'], 
                'code_comptable' => $donnees_source['code_comptable'], 
                'libelle_compte' => $donnees_source['libelle'], 
                'montant' => $montant_operation, 
                'libelle_ecriture' => "Émission transfert : " . $motif_operation, 
                'reference' => $reference_operation
            ];

        } else {
            throw new Exception("Erreur : Le type d'opération spécifié est inconnu.");
        }

        // -------------------------------------------------------------------------
        // 3. VÉRIFICATION DE L'ÉQUILIBRE COMPTABLE (DÉBIT = CRÉDIT)
        // -------------------------------------------------------------------------
        $somme_debit = 0;
        $somme_credit = 0;
        foreach ($tableau_ecritures as $ligne_ecriture) {
            if ($ligne_ecriture['sens'] === 'DEBIT') {
                $somme_debit += $ligne_ecriture['montant'];
            } else {
                $somme_credit += $ligne_ecriture['montant'];
            }
        }

        // Comparaison avec une marge de tolérance pour les arrondis
        if (abs($somme_debit - $somme_credit) > 0.001) {
            throw new Exception("Déséquilibre détecté : Débit ($somme_debit) différent du Crédit ($somme_credit).");
        }

        // Finalisation de la réponse en cas de succès
        $reponse['succes'] = true;
        $reponse['message'] = "Les écritures comptables ont été générées avec succès.";
        $reponse['ecritures'] = $tableau_ecritures;

    } catch (Exception $exception_detectee) {
        $reponse['message'] = $exception_detectee->getMessage();
    }
}

// Envoi du résultat au format JSON
header('Content-Type: application/json');
echo json_encode($reponse);