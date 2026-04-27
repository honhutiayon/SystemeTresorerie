<?php
// JOURNALISATION
//Fichier  : journalisation.php
 //Rôle     : Journal des opérations financières et journal comptable déduit depuis les tables existantes
//tables utilisées : operation, portefeuille, utilisateur,compte, plan_comptable

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../connexion/connexion.php';

$methode = $_SERVER['REQUEST_METHOD'];
$action  = $_GET['action'] ?? '';

switch ($action) {

    // GET ?action=getJournalOperations
    // Journal des opérations financières
    // Lit directement depuis la table operation
    case 'getJournalOperations':
        if ($methode !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $query = "SELECT
                    o.id_operation,
                    o.reference_operation,
                    o.type_operation,
                    o.montant,
                    o.motif,
                    o.date_operation,
                    o.statut,
                    o.numcompte_source,
                    o.numcompte_destination,
                    u.nom, u.prenom, u.role,
                    c.nom_compte, c.type_compte,
                    c.total_entree, c.total_sortie
                  FROM operation o
                  INNER JOIN portefeuille p ON o.id_portefeuille  = p.id_portefeuille
                  INNER JOIN utilisateur u  ON p.id_utilisateur   = u.id_utilisateur
                  INNER JOIN compte c       ON o.numcompte_source = c.id_compte
                  WHERE 1=1";

        if (!empty($_GET['date_debut'])) {
            $date_debut = mysqli_real_escape_string($connexion, $_GET['date_debut']);
            $query .= " AND DATE(o.date_operation) >= '$date_debut'";
        }
        if (!empty($_GET['date_fin'])) {
            $date_fin = mysqli_real_escape_string($connexion, $_GET['date_fin']);
            $query .= " AND DATE(o.date_operation) <= '$date_fin'";
        }
        if (!empty($_GET['type_operation'])) {
            $type   = mysqli_real_escape_string($connexion, $_GET['type_operation']);
            $query .= " AND o.type_operation = '$type'";
        }
        if (!empty($_GET['id_utilisateur'])) {
            $id_u   = mysqli_real_escape_string($connexion, $_GET['id_utilisateur']);
            $query .= " AND p.id_utilisateur = '$id_u'";
        }
        if (!empty($_GET['statut'])) {
            $statut = mysqli_real_escape_string($connexion, $_GET['statut']);
            $query .= " AND o.statut = '$statut'";
        }

        $query .= " ORDER BY o.date_operation DESC";

        $result = mysqli_query($connexion, $query);
        $data   = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        echo json_encode([
            'success' => true,
            'total'   => count($data),
            'data'    => $data
        ]);
        break;

    // --------------------------------------------------
    // GET ?action=getJournalComptable
    // Journal comptable déduit automatiquement
    // depuis operation + compte + plan_comptable
    // --------------------------------------------------
    case 'getJournalComptable':
        if ($methode !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $query = "SELECT
                    o.reference_operation,
                    o.type_operation,
                    o.montant,
                    o.motif,
                    o.date_operation,
                    o.statut,
                    c.nom_compte,
                    c.type_compte,
                    pc.code_comptable,
                    pc.libelle        AS libelle_compte,
                    pc.type_mouvement,
                    pc.solde_normal,
                    u.nom, u.prenom, u.role,
                    -- Déduction du sens de l'écriture
                    -- ENTREE → le compte est débité (argent qui entre)
                    -- SORTIE → le compte est crédité (argent qui sort)
                    CASE
                        WHEN o.type_operation = 'ENTREE'    THEN 'DEBIT'
                        WHEN o.type_operation = 'SORTIE'    THEN 'CREDIT'
                        WHEN o.type_operation = 'TRANSFERT' THEN 'DEBIT/CREDIT'
                    END AS sens_ecriture,
                    -- Montant au débit
                    CASE
                        WHEN o.type_operation = 'ENTREE' THEN o.montant
                        ELSE 0
                    END AS montant_debit,
                    -- Montant au crédit
                    CASE
                        WHEN o.type_operation = 'SORTIE' THEN o.montant
                        ELSE 0
                    END AS montant_credit
                  FROM operation o
                  INNER JOIN portefeuille p    ON o.id_portefeuille       = p.id_portefeuille
                  INNER JOIN utilisateur u     ON p.id_utilisateur        = u.id_utilisateur
                  INNER JOIN compte c          ON o.numcompte_source      = c.id_compte
                  INNER JOIN plan_comptable pc ON c.id_compte_comptable   = pc.id_compte_comptable
                  WHERE 1=1";

        if (!empty($_GET['date_debut'])) {
            $date_debut = mysqli_real_escape_string($connexion, $_GET['date_debut']);
            $query .= " AND DATE(o.date_operation) >= '$date_debut'";
        }
        if (!empty($_GET['date_fin'])) {
            $date_fin = mysqli_real_escape_string($connexion, $_GET['date_fin']);
            $query .= " AND DATE(o.date_operation) <= '$date_fin'";
        }

        $query .= " ORDER BY o.date_operation DESC";

        $result = mysqli_query($connexion, $query);
        $data   = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        echo json_encode([
            'success' => true,
            'total'   => count($data),
            'data'    => $data
        ]);
        break;

    // --------------------------------------------------
    // Action inconnue
    // --------------------------------------------------
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => "Action inconnue : '$action'",
            'actions_disponibles' => [
                'GET getJournalOperations',
                'GET getJournalComptable'
            ]
        ]);
        break;
}

mysqli_close($connexion);
?>