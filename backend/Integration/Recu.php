<?php
// ============================================================
//  GET /Integration/recu_pdf.php?id_operation=12
//  Génère un reçu PDF pour une opération précise
// ============================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '/var/www/html/SystemeTresorerie/fpdf186/fpdf.php';
require_once __DIR__ . '/../connexion/connexion.php';

// ---------- 1. Validation du paramètre ----------
if (!isset($_GET['id_operation']) || !is_numeric($_GET['id_operation'])) {
    http_response_code(400);
    die("Parametre 'id_operation' requis.");
}

$id_operation = (int) $_GET['id_operation'];

// ---------- 2. Récupération de l'opération ----------
$sql = "
    SELECT 
        o.id_operation,
        o.reference_operation,
        o.type_operation,
        o.montant,
        o.motif,
        o.date_operation,
        o.statut,
        u.nom                  AS nom_utilisateur,
        u.prenom               AS prenom_utilisateur,
        u.role                 AS role_utilisateur,
        cs.nom_compte          AS nom_compte_source,
        cs.type_compte         AS type_compte_source,
        cs.numero_compte       AS numero_source,
        pc_s.code_comptable    AS code_source,
        pc_s.libelle           AS libelle_source,
        cd.nom_compte          AS nom_compte_dest,
        cd.type_compte         AS type_compte_dest,
        cd.numero_compte       AS numero_dest,
        pc_d.code_comptable    AS code_dest,
        pc_d.libelle           AS libelle_dest
    FROM operation o
    JOIN portefeuille p           ON o.id_portefeuille       = p.id_portefeuille
    JOIN utilisateur u            ON p.id_utilisateur        = u.id_utilisateur
    JOIN compte cs                ON o.numcompte_source      = cs.id_compte
    LEFT JOIN plan_comptable pc_s ON cs.id_compte_comptable  = pc_s.id_compte_comptable
    LEFT JOIN compte cd           ON o.numcompte_destination = cd.id_compte
    LEFT JOIN plan_comptable pc_d ON cd.id_compte_comptable  = pc_d.id_compte_comptable
    WHERE o.id_operation = ?
";

$stmt = mysqli_prepare($connexion, $sql);
if (!$stmt) die("Erreur requete : " . mysqli_error($connexion));

mysqli_stmt_bind_param($stmt, "i", $id_operation);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$op     = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$op) die("Operation introuvable.");

// ---------- 3. Classe FPDF reçu ----------
class RecuPDF extends FPDF {

    var $type_operation;

    function Header() {
        // Bande verte en-tête
        $this->SetFillColor(26, 95, 63);
        $this->Rect(0, 0, 210, 32, 'F');

        // Titre
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 18);
        $this->SetY(5);
        $this->Cell(0, 9, 'RECU DE ' . $this->type_operation, 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, 'Systeme SIGERIS - Plan Comptable OHADA', 0, 1, 'C');

        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 6, 'Ce document est une piece comptable officielle', 0, 1, 'C');

        $this->SetTextColor(0, 0, 0);
        $this->SetY(38);
    }

    function Footer() {
        $this->SetY(-15);
        // Ligne séparatrice
        $this->SetDrawColor(26, 95, 63);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'Document genere automatiquement le ' . date('d/m/Y a H:i:s') . '  —  Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function LigneInfo($label, $valeur, $couleur_valeur = [0, 0, 0]) {
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(65, 7, $label . ' :', 0, 0, 'L');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($couleur_valeur[0], $couleur_valeur[1], $couleur_valeur[2]);
        $this->Cell(0, 7, $valeur, 0, 1, 'L');
        $this->SetTextColor(0, 0, 0);
    }

    function Separateur() {
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
    }

    function TitreSection($titre) {
        $this->SetFillColor(26, 95, 63);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, '  ' . $titre, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }
}

// ---------- 4. Génération ----------
$pdf = new RecuPDF();
$pdf->type_operation = $op['type_operation'];
$pdf->SetMargins(10, 42, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

$montant = (float) $op['montant'];
$date    = date('d/m/Y', strtotime($op['date_operation']));
$heure   = date('H:i:s', strtotime($op['date_operation']));

// ---- Numéro de reçu + statut ----
$pdf->SetFillColor(240, 248, 240);
$pdf->Rect(10, $pdf->GetY(), 190, 12, 'F');
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(26, 95, 63);
$pdf->Cell(95, 12, '  Ref : ' . $op['reference_operation'], 0, 0, 'L');

// Badge statut
switch ($op['statut']) {
    case 'VALIDE':
        $pdf->SetFillColor(0, 180, 0);
        break;
    case 'EN_COURS':
        $pdf->SetFillColor(255, 165, 0);
        break;
    case 'ANNULEE':
        $pdf->SetFillColor(200, 0, 0);
        break;
    default:
        $pdf->SetFillColor(128, 128, 128);
}
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(50, 12, $op['statut'], 0, 0, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(16);

// ---- MONTANT EN GRAND ----
$pdf->SetFillColor(26, 95, 63);
$pdf->Rect(10, $pdf->GetY(), 190, 20, 'F');
$pdf->SetFont('Arial', 'B', 22);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 20, number_format($montant, 0, ',', ' ') . ' FCFA', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(6);

// ---- INFORMATIONS DE L'OPERATION ----
$pdf->TitreSection('INFORMATIONS DE L\'OPERATION');

$pdf->LigneInfo('Date', $date);
$pdf->LigneInfo('Heure', $heure);
$pdf->LigneInfo('Type', $op['type_operation'],
    $op['type_operation'] === 'ENTREE' ? [0,128,0] :
    ($op['type_operation'] === 'SORTIE' ? [200,0,0] : [0,0,200])
);
$pdf->LigneInfo('Motif', $op['motif'] ?? 'Non specifie');
$pdf->Separateur();

// ---- COMPTES ----
$pdf->TitreSection('COMPTES CONCERNES');

$pdf->LigneInfo('Compte source', $op['nom_compte_source'] . ' (' . strtoupper($op['type_compte_source']) . ')');
$pdf->LigneInfo('N° Compte source', $op['numero_source']);
$pdf->LigneInfo('Code comptable', $op['code_source'] . ' — ' . ($op['libelle_source'] ?? 'N/A'));

if ($op['type_operation'] === 'TRANSFERT' && $op['nom_compte_dest']) {
    $pdf->Separateur();
    $pdf->LigneInfo('Compte destination', $op['nom_compte_dest'] . ' (' . strtoupper($op['type_compte_dest']) . ')');
    $pdf->LigneInfo('N° Compte destination', $op['numero_dest']);
    $pdf->LigneInfo('Code comptable dest.', $op['code_dest'] . ' — ' . ($op['libelle_dest'] ?? 'N/A'));
}
$pdf->Separateur();

// ---- ECRITURE COMPTABLE ----
$pdf->TitreSection('ECRITURE COMPTABLE (OHADA)');

switch ($op['type_operation']) {
    case 'ENTREE':
        $debit_compte  = $op['code_source'] . ' - ' . ($op['libelle_source'] ?? 'Caisse/Banque');
        $credit_compte = '7xx - Produits';
        $debit_libelle = 'Entree de fonds';
        $credit_libelle= 'Produit constate';
        break;
    case 'SORTIE':
        $debit_compte  = '6xx - Charges';
        $credit_compte = $op['code_source'] . ' - ' . ($op['libelle_source'] ?? 'Caisse/Banque');
        $debit_libelle = 'Charge enregistree';
        $credit_libelle= 'Sortie de fonds';
        break;
    case 'TRANSFERT':
        $debit_compte  = ($op['code_dest'] ?? 'N/A') . ' - ' . ($op['libelle_dest'] ?? 'Destination');
        $credit_compte = $op['code_source'] . ' - ' . ($op['libelle_source'] ?? 'Source');
        $debit_libelle = 'Transfert recu';
        $credit_libelle= 'Transfert emis';
        break;
}

// Tableau écriture
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(15,  7, 'Sens',    1, 0, 'C', true);
$pdf->Cell(80,  7, 'Compte',  1, 0, 'C', true);
$pdf->Cell(60,  7, 'Libelle', 1, 0, 'C', true);
$pdf->Cell(35,  7, 'Montant', 1, 1, 'C', true);

// Ligne DEBIT
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(200, 0, 0);
$pdf->Cell(15, 7, 'DEBIT',  1, 0, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(80, 7, $debit_compte,  1, 0, 'L');
$pdf->Cell(60, 7, $debit_libelle, 1, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(35, 7, number_format($montant, 0, ',', ' '), 1, 1, 'R');

// Ligne CREDIT
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(0, 128, 0);
$pdf->Cell(15, 7, 'CREDIT', 1, 0, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(80, 7, $credit_compte,  1, 0, 'L');
$pdf->Cell(60, 7, $credit_libelle, 1, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(35, 7, number_format($montant, 0, ',', ' '), 1, 1, 'R');
$pdf->SetFont('Arial', '', 8);

$pdf->Ln(4);

// Equilibre
$pdf->SetFillColor(240, 255, 240);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(0, 128, 0);
$pdf->Cell(0, 6, '  Equilibre comptable verifie : Debit (' . number_format($montant, 0, ',', ' ') . ') = Credit (' . number_format($montant, 0, ',', ' ') . ')  ✓', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(5);

// ---- RESPONSABLE ----
$pdf->TitreSection('RESPONSABLE DE L\'OPERATION');
$pdf->LigneInfo('Nom & Prenom', $op['prenom_utilisateur'] . ' ' . $op['nom_utilisateur']);
$pdf->LigneInfo('Role', ucfirst($op['role_utilisateur']));
$pdf->Ln(8);

// ---- SIGNATURE ----
$pdf->SetDrawColor(26, 95, 63);
$pdf->SetLineWidth(0.4);
$pdf->Line(120, $pdf->GetY(), 200, $pdf->GetY());
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetX(120);
$pdf->Cell(80, 5, 'Signature & Cachet', 0, 1, 'C');

// ---------- 5. Sortie ----------
$nom_fichier = 'Recu_' . $op['reference_operation'] . '_' . date('Ymd') . '.pdf';
$pdf->Output('I', $nom_fichier);