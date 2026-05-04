<?php
// JOURNALISATION
//Fichier  : journalisation.php
//Rôle     : Journal des opérations financières et journal comptable déduit depuis les tables existantes
//tables utilisées : operation, portefeuille, utilisateur,compte, plan_comptable
/** @var mysqli $connexion */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Headers CORS — permettre au frontend d'accéder à l'API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Gérer les requêtes OPTIONS (preflight)
// Le navigateur envoie d'abord une requête OPTIONS
// avant la vraie requête GET ou POST
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../connexion/connexion.php';

$methode = $_SERVER['REQUEST_METHOD'];
$input   = json_decode(file_get_contents('php://input'), true);
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

    // GET ?action=getJournalComptable
    // Journal comptable déduit automatiquement
    // depuis operation + compte + plan_comptable
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

    // Action inconnue
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
        
    // transaction sql     
    // POST ?action=enregistrerOperation
    case 'enregistrerOperation':
        if ($methode !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $champs = [
            'id_portefeuille',
            'type_operation',
            'montant',
            'motif',
            'numcompte_source',
            'statut'
        ];
        foreach ($champs as $champ) {
            if (empty($input[$champ])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Champ manquant : $champ"]);
                exit;
            }
        }

        $type      = $input['type_operation'];
        $montant   = $input['montant'];
        $motif     = $input['motif'];
        $id_pf     = $input['id_portefeuille'];
        $num_src   = $input['numcompte_source'];
        $statut    = $input['statut'];
        $num_dest  = !empty($input['numcompte_destination'])
            ? $input['numcompte_destination']
            : null;

        // Générer référence unique
        $annee     = date('Y');
        $res_count = mysqli_query(
            $connexion,
            "SELECT COUNT(*) as total FROM operation 
                     WHERE YEAR(date_operation) = '$annee'"
        );
        $count     = mysqli_fetch_assoc($res_count)['total'];
        $reference = 'OP-' . $annee . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        // DEBUT TRANSACTION
        mysqli_begin_transaction($connexion);

        try {
            // ETAPE 1 : Insérer l'opération
            $stmt1 = mysqli_prepare(
                $connexion,
                "INSERT INTO operation
                (reference_operation, type_operation, montant, motif,
                 id_portefeuille, numcompte_source, numcompte_destination, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt1,
                'ssdsiiss',
                $reference,
                $type,
                $montant,
                $motif,
                $id_pf,
                $num_src,
                $num_dest,
                $statut
            );

            if (!mysqli_stmt_execute($stmt1)) {
                throw new Exception(mysqli_error($connexion));
            }
            mysqli_stmt_close($stmt1);

            // ETAPE 2 : Mettre à jour le solde du compte
            // ENTREE → augmente total_entree
            // SORTIE → augmente total_sortie
            if ($type === 'ENTREE') {
                $stmt2 = mysqli_prepare(
                    $connexion,
                    "UPDATE compte 
                 SET total_entree = total_entree + ? 
                 WHERE id_compte = ?"
                );
                mysqli_stmt_bind_param($stmt2, 'di', $montant, $num_src);
            } elseif ($type === 'SORTIE') {
                $stmt2 = mysqli_prepare(
                    $connexion,
                    "UPDATE compte 
                 SET total_sortie = total_sortie + ? 
                 WHERE id_compte = ?"
                );
                mysqli_stmt_bind_param($stmt2, 'di', $montant, $num_src);
            } elseif ($type === 'TRANSFERT') {
                // Débiter le compte source
                $stmt2 = mysqli_prepare(
                    $connexion,
                    "UPDATE compte 
                 SET total_sortie = total_sortie + ? 
                 WHERE id_compte = ?"
                );
                mysqli_stmt_bind_param($stmt2, 'di', $montant, $num_src);

                if (!mysqli_stmt_execute($stmt2)) {
                    throw new Exception(mysqli_error($connexion));
                }
                mysqli_stmt_close($stmt2);

                // Créditer le compte destination
                $stmt2 = mysqli_prepare(
                    $connexion,
                    "UPDATE compte 
                 SET total_entree = total_entree + ? 
                 WHERE id_compte = ?"
                );
                mysqli_stmt_bind_param($stmt2, 'di', $montant, $num_dest);
            }

            if (!mysqli_stmt_execute($stmt2)) {
                throw new Exception(mysqli_error($connexion));
            }
            mysqli_stmt_close($stmt2);

            // TOUT REUSSI → COMMIT
            mysqli_commit($connexion);

            echo json_encode([
                'success'   => true,
                'reference' => $reference,
                'message'   => 'Opération enregistrée et solde mis à jour'
            ]);
        } catch (Exception $e) {
            // ERREUR → ROLLBACK
            mysqli_rollback($connexion);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Transaction annulée : ' . $e->getMessage()
            ]);
        }
        break;
}

mysqli_close($connexion);
?>
