<?php
/**
 * JOURNALISATION
 * Fichier  : journalisation.php
 * Rôle     : Journal des opérations financières et journal comptable (écritures débit/crédit)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Connexion partagée avec toute l'équipe
require_once '../connexion/connexion.php';

// GENERATION DE REFERENCES UNIQUES

/**
 * Génère une référence unique pour le journal des opérations
 * Exemple : JOP-2026-00001
 */
function genererReferenceJournal($connexion) {
    $annee  = date('Y');
    $query  = "SELECT COUNT(*) as total FROM journal_operations 
               WHERE YEAR(date_journal) = '$annee'";
    $result = mysqli_query($connexion, $query);
    $row    = mysqli_fetch_assoc($result);
    $numero = str_pad($row['total'] + 1, 5, '0', STR_PAD_LEFT);
    return "JOP-" . $annee . "-" . $numero;
}

/**
 * Génère une référence unique pour le journal comptable
 * Exemple : ECR-2026-00001
 */
function genererReferenceEcriture($connexion) {
    $annee  = date('Y');
    $query  = "SELECT COUNT(*) as total FROM journal_comptable 
               WHERE YEAR(date_ecriture) = '$annee'";
    $result = mysqli_query($connexion, $query);
    $row    = mysqli_fetch_assoc($result);
    $numero = str_pad($row['total'] + 1, 5, '0', STR_PAD_LEFT);
    return "ECR-" . $annee . "-" . $numero;
}

// RECUPERATION DE L'ACTION DEMANDEE

/**
 * On récupère la méthode HTTP et les données envoyées
 * GET  → lecture  (getJournalOperations, getJournalComptable)
 * POST → écriture (logOperation, logEcritureComptable, logOperationComplete)
 */
$methode = $_SERVER['REQUEST_METHOD'];
$input   = json_decode(file_get_contents('php://input'), true);
$action  = $_GET['action'] ?? '';

// ROUTEUR PRINCIPAL
switch ($action) {

    // Enregistrer une opération dans le journal
    // POST ?action=logOperation
    case 'logOperation':
        if ($methode !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        // Vérification des champs obligatoires
        $champs = ['id_operation','id_utilisateur','type_operation',
                   'montant','id_compte_source','solde_avant','solde_apres','statut'];
        foreach ($champs as $champ) {
            if (empty($input[$champ])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Champ manquant : $champ"]);
                exit;
            }
        }

        $reference   = genererReferenceJournal($connexion);
        $adresse_ip  = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';

        // Gestion du compte destination (NULL si ENTREE ou SORTIE)
        $id_destination = !empty($input['id_compte_destination']) 
                          ? $input['id_compte_destination'] 
                          : null;

        // On utilise mysqli_prepare comme les collègues
        $stmt = mysqli_prepare($connexion,
            "INSERT INTO journal_operations 
                (reference_journal, id_operation, id_utilisateur,
                 type_operation, montant, motif,
                 id_compte_source, id_compte_destination,
                 solde_avant, solde_apres, statut, adresse_ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param($stmt, 'siisdsidddss',
            $reference,
            $input['id_operation'],
            $input['id_utilisateur'],
            $input['type_operation'],
            $input['montant'],
            $input['motif'],
            $input['id_compte_source'],
            $id_destination,
            $input['solde_avant'],
            $input['solde_apres'],
            $input['statut'],
            $adresse_ip
        );

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'reference' => $reference]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => mysqli_error($connexion)]);
        }
        mysqli_stmt_close($stmt);
        break;

    // Récupérer le journal des opérations
    // GET ?action=getJournalOperations
    // GET ?action=getJournalOperations&type_operation=ENTREE
    // GET ?action=getJournalOperations&date_debut=2026-01-01&date_fin=2026-12-31
    case 'getJournalOperations':
        if ($methode !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $query = "SELECT
                    jo.*,
                    u.nom, u.prenom, u.role,
                    c.nom_compte, c.type_compte,
                    o.reference_operation
                  FROM journal_operations jo
                  INNER JOIN utilisateur u ON jo.id_utilisateur  = u.id_utilisateur
                  INNER JOIN compte c      ON jo.id_compte_source = c.id_compte
                  INNER JOIN operation o   ON jo.id_operation     = o.id_operation
                  WHERE 1=1";

        // Filtres optionnels
        if (!empty($_GET['date_debut'])) {
            $date_debut = mysqli_real_escape_string($connexion, $_GET['date_debut']);
            $query .= " AND DATE(jo.date_journal) >= '$date_debut'";
        }
        if (!empty($_GET['date_fin'])) {
            $date_fin = mysqli_real_escape_string($connexion, $_GET['date_fin']);
            $query .= " AND DATE(jo.date_journal) <= '$date_fin'";
        }
        if (!empty($_GET['type_operation'])) {
            $type   = mysqli_real_escape_string($connexion, $_GET['type_operation']);
            $query .= " AND jo.type_operation = '$type'";
        }
        if (!empty($_GET['id_utilisateur'])) {
            $id_u   = mysqli_real_escape_string($connexion, $_GET['id_utilisateur']);
            $query .= " AND jo.id_utilisateur = '$id_u'";
        }

        $query .= " ORDER BY jo.date_journal DESC";

        $result = mysqli_query($connexion, $query);
        $data   = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'total' => count($data), 'data' => $data]);
        break;

    // Enregistrer une écriture comptable débit/crédit
    // POST ?action=logEcritureComptable
    case 'logEcritureComptable':
        if ($methode !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $champs = ['id_operation','id_utilisateur',
                   'id_compte_comptable_debit','id_compte_comptable_credit',
                   'libelle_ecriture','montant'];
        foreach ($champs as $champ) {
            if (empty($input[$champ])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Champ manquant : $champ"]);
                exit;
            }
        }

        // Vérification : débit ≠ crédit
        if ($input['id_compte_comptable_debit'] === $input['id_compte_comptable_credit']) {
            http_response_code(400);
            echo json_encode(['success' => false, 
                              'message' => 'Le compte débité et crédité ne peuvent pas être identiques']);
            exit;
        }

        $reference = genererReferenceEcriture($connexion);

        $stmt = mysqli_prepare($connexion,
            "INSERT INTO journal_comptable
                (reference_ecriture, id_operation, id_utilisateur,
                 id_compte_comptable_debit, id_compte_comptable_credit,
                 libelle_ecriture, montant)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param($stmt, 'siiissd',
            $reference,
            $input['id_operation'],
            $input['id_utilisateur'],
            $input['id_compte_comptable_debit'],
            $input['id_compte_comptable_credit'],
            $input['libelle_ecriture'],
            $input['montant']
        );

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'reference' => $reference]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => mysqli_error($connexion)]);
        }
        mysqli_stmt_close($stmt);
        break;

    // Récupérer le journal comptable
    // GET ?action=getJournalComptable
    // GET ?action=getJournalComptable&date_debut=2026-01-01&date_fin=2026-12-31
    case 'getJournalComptable':
        if ($methode !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $query = "SELECT
                    jc.*,
                    pd.code_comptable AS code_debit,
                    pd.libelle        AS libelle_debit,
                    pc.code_comptable AS code_credit,
                    pc.libelle        AS libelle_credit,
                    o.reference_operation
                  FROM journal_comptable jc
                  INNER JOIN plan_comptable pd ON jc.id_compte_comptable_debit  = pd.id_compte_comptable
                  INNER JOIN plan_comptable pc ON jc.id_compte_comptable_credit = pc.id_compte_comptable
                  INNER JOIN operation o       ON jc.id_operation               = o.id_operation
                  WHERE 1=1";

        if (!empty($_GET['date_debut'])) {
            $date_debut = mysqli_real_escape_string($connexion, $_GET['date_debut']);
            $query .= " AND DATE(jc.date_ecriture) >= '$date_debut'";
        }
        if (!empty($_GET['date_fin'])) {
            $date_fin = mysqli_real_escape_string($connexion, $_GET['date_fin']);
            $query .= " AND DATE(jc.date_ecriture) <= '$date_fin'";
        }

        $query .= " ORDER BY jc.date_ecriture DESC";

        $result = mysqli_query($connexion, $query);
        $data   = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'total' => count($data), 'data' => $data]);
        break;

    // Opération complète avec transaction SQL
    // POST ?action=logOperationComplete
    case 'logOperationComplete':
        if ($methode !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        // Vérification des deux blocs de données
        if (empty($input['operation']) || empty($input['ecriture'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 
                              'message' => 'Les blocs operation et ecriture sont obligatoires']);
            exit;
        }

        $op  = $input['operation'];
        $ecr = $input['ecriture'];

        // Démarrage de la transaction
        mysqli_begin_transaction($connexion);

        try {
            // ETAPE 1 : Insérer dans journal_operations
            $ref_op     = genererReferenceJournal($connexion);
            $adresse_ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
            $id_dest    = !empty($op['id_compte_destination']) 
                          ? $op['id_compte_destination'] 
                          : null;

            $stmt1 = mysqli_prepare($connexion,
                "INSERT INTO journal_operations
                    (reference_journal, id_operation, id_utilisateur,
                     type_operation, montant, motif,
                     id_compte_source, id_compte_destination,
                     solde_avant, solde_apres, statut, adresse_ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param($stmt1, 'siisdsidddss',
                $ref_op,
                $op['id_operation'],
                $op['id_utilisateur'],
                $op['type_operation'],
                $op['montant'],
                $op['motif'],
                $op['id_compte_source'],
                $id_dest,
                $op['solde_avant'],
                $op['solde_apres'],
                $op['statut'],
                $adresse_ip
            );

            if (!mysqli_stmt_execute($stmt1)) {
                throw new Exception(mysqli_error($connexion));
            }
            mysqli_stmt_close($stmt1);

            // ETAPE 2 : Insérer dans journal_comptable
            $ref_ecr = genererReferenceEcriture($connexion);

            $stmt2 = mysqli_prepare($connexion,
                "INSERT INTO journal_comptable
                    (reference_ecriture, id_operation, id_utilisateur,
                     id_compte_comptable_debit, id_compte_comptable_credit,
                     libelle_ecriture, montant)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param($stmt2, 'siiissd',
                $ref_ecr,
                $ecr['id_operation'],
                $ecr['id_utilisateur'],
                $ecr['id_compte_comptable_debit'],
                $ecr['id_compte_comptable_credit'],
                $ecr['libelle_ecriture'],
                $ecr['montant']
            );

            if (!mysqli_stmt_execute($stmt2)) {
                throw new Exception(mysqli_error($connexion));
            }
            mysqli_stmt_close($stmt2);

            // TOUT A REUSSI → on valide
            mysqli_commit($connexion);

            echo json_encode([
                'success'              => true,
                'reference_operation'  => $ref_op,
                'reference_ecriture'   => $ref_ecr
            ]);

        } catch (Exception $e) {
            // UNE ERREUR → on annule tout
            mysqli_rollback($connexion);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Transaction annulée : ' . $e->getMessage()
            ]);
        }
        break;

    // Action inconnue
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => "Action inconnue : '$action'",
            'actions_disponibles' => [
                'POST logOperation',
                'GET  getJournalOperations',
                'POST logEcritureComptable',
                'GET  getJournalComptable',
                'POST logOperationComplete'
            ]
        ]);
        break;
}

mysqli_close($connexion);
?>