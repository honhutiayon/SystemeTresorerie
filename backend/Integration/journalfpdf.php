<?php
// ============================================================
//  GET /Integration/journal_pdf.php
//  Génère un PDF du journal comptable complet
//
//  Paramètres GET (tous optionnels) :
//    date_debut  : YYYY-MM-DD
//    date_fin    : YYYY-MM-DD
//    type        : ENTREE | SORTIE | TRANSFERT
// ============================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '/var/www/html/SystemeTresorerie/fpdf186/fpdf.php';
require_once __DIR__ . '/../connexion/connexion.php';

// ---------- 1. Lecture des filtres ----------
$date_debut = $_GET['date_debut'] ?? null;
$date_fin   = $_GET['date_fin']   ?? null;
$type       = isset($_GET['type']) && in_array(strtoupper($_GET['type']), ['ENTREE','SORTIE','TRANSFERT'])
                ? strtoupper($_GET['type']) : null;

// ---------- 2. Construction de la requête ----------
$conditions = ["1=1"];
$params     = [];
$types      = "";

if ($date_debut) {
    $conditions[] = "o.date_operation >= ?";
    $params[]     = $date_debut . ' 00:00:00';
    $types       .= "s";
}
if ($date_fin) {
    $conditions[] = "o.date_operation <= ?";
    $params[]     = $date_fin . ' 23:59:59';
    $types       .= "s";
}
if ($type) {
    $conditions[] = "o.type_operation = ?";
    $params[]     = $type;
    $types       .= "s";
}

$where = "WHERE " . implode(" AND ", $conditions);

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
        cs.nom_compte          AS compte_source,
        pc_s.code_comptable    AS code_source,
        pc_s.libelle           AS libelle_source,
        cd.nom_compte          AS compte_destination,
        pc_d.code_comptable    AS code_dest,
        pc_d.libelle           AS libelle_dest
    FROM operation o
    JOIN portefeuille p           ON o.id_portefeuille       = p.id_portefeuille
    JOIN utilisateur u            ON p.id_utilisateur        = u.id_utilisateur
    JOIN compte cs                ON o.numcompte_source      = cs.id_compte
    LEFT JOIN plan_comptable pc_s ON cs.id_compte_comptable  = pc_s.id_compte_comptable
    LEFT JOIN compte cd           ON o.numcompte_destination = cd.id_compte
    LEFT JOIN plan_comptable pc_d ON cd.id_compte_comptable  = pc_d.id_compte_comptable
    $where
    ORDER BY o.date_operation DESC
";

$stmt = mysqli_prepare($connexion, $sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result     = mysqli_stmt_get_result($stmt);
$operations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $operations[] = $row;
}
mysqli_stmt_close($stmt);

// ---------- 3. Calcul des totaux ----------
$total_debit  = 0;
$total_credit = 0;
foreach ($operations as $op) {
    $total_debit  += (float) $op['montant'];
    $total_credit += (float) $op['montant'];
}

// ---------- 4. Classe FPDF personnalisée ----------
class JournalPDF extends FPDF {

    function Header() {
        // Logo / En-tête
        $this->SetFillColor(26, 95, 63); // Vert foncé
        $this->Rect(0, 0, 297, 28, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 16);
        $this->SetY(6);
        $this->Cell(0, 8, 'JOURNAL D\'INTEGRATION COMPTABLE', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, 'Systeme SIGERIS - Plan Comptable OHADA', 0, 1, 'C');

        $this->SetTextColor(0, 0, 0);
        $this->SetY(32);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' - Genere le ' . date('d/m/Y a H:i'), 0, 0, 'C');
    }

    function SectionTitle($titre) {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(26, 95, 63);
        $this->Cell(0, 7, $titre, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    function TableHeader() {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(26, 95, 63);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->Cell(25,  7, 'Date',           1, 0, 'C', true);
        $this->Cell(35,  7, 'Reference',      1, 0, 'C', true);
        $this->Cell(25,  7, 'Type',           1, 0, 'C', true);
        $this->Cell(80,  7, 'Compte Debit',   1, 0, 'C', true);
        $this->Cell(80,  7, 'Compte Credit',  1, 0, 'C', true);
        $this->Cell(32,  7, 'Montant (FCFA)', 1, 1, 'C', true);
        // Total : 25+35+25+80+80+32 = 277mm = largeur utile paysage A4
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
    }
}

// ---------- 5. Génération du PDF ----------
$pdf = new JournalPDF('L', 'mm', 'A4'); // Paysage pour avoir plus de place
$pdf->SetMargins(10, 35, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Infos du document
$pdf->SetFont('Arial', '', 9);
$pdf->SetFillColor(255, 255, 220);
$pdf->Cell(100, 6, 'Periode : ' . ($date_debut ? date('d/m/Y', strtotime($date_debut)) : 'Debut') . ' au ' . ($date_fin ? date('d/m/Y', strtotime($date_fin)) : 'Aujourd\'hui'), 0, 0, 'L', true);
$pdf->Cell(90,  6, 'Type filtre : ' . ($type ?? 'Tous'), 0, 0, 'C', true);
$pdf->Cell(87,  6, 'Heure : ' . date('H:i:s'), 0, 1, 'R', true);
$pdf->Ln(4);

if (empty($operations)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 10, 'Aucune operation trouvee pour les criteres selectionnes.', 0, 1, 'C');
} else {

    // ---------- BLOC SOLDE — design distinctif avant le tableau ----------
    $pdf->Ln(2);

    // Fond gris clair derrière tout le bloc
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Rect(10, $pdf->GetY(), 277, 22, 'F');

    // Barre gauche verte décorative
    $pdf->SetFillColor(26, 95, 63);
    $pdf->Rect(10, $pdf->GetY(), 4, 22, 'F');

    // Titre SOLDE GLOBAL
    $pdf->SetFont('Arial', 'B', 15);
    $pdf->SetTextColor(26, 95, 63);
    $pdf->SetX(17);
    $pdf->Cell(80, 7, 'SOLDE GLOBAL', 0, 0, 'L');

    // Montant en grand
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(26, 95, 63);
    $pdf->Cell(100, 7, number_format($total_debit, 0, ',', ' ') . ' FCFA', 0, 1, 'C');

    // Ligne 2 — détails
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(5);

    // ---------- Tableau ----------
    $pdf->TableHeader();

    $fill   = false;
    $ligne  = 0;

    foreach ($operations as $op) {
        $montant = (float) $op['montant'];
        $ref     = $op['motif'] ?? $op['reference_operation'];
        $date    = date('d/m/Y', strtotime($op['date_operation']));

        // Détermination débit / crédit selon type
        switch ($op['type_operation']) {
            case 'ENTREE':
                $compte_debit  = $op['code_source'] . ' - ' . substr($op['libelle_source'] ?? 'Caisse/Banque', 0, 18);
                $compte_credit = '7xx - Produit';
                break;
            case 'SORTIE':
                $compte_debit  = '6xx - Charge';
                $compte_credit = $op['code_source'] . ' - ' . substr($op['libelle_source'] ?? 'Caisse/Banque', 0, 18);
                break;
            case 'TRANSFERT':
                $compte_debit  = ($op['code_dest'] ?? 'N/A') . ' - ' . substr($op['libelle_dest'] ?? 'Destination', 0, 15);
                $compte_credit = $op['code_source'] . ' - ' . substr($op['libelle_source'] ?? 'Source', 0, 15);
                break;
            default:
                $compte_debit  = '-';
                $compte_credit = '-';
        }

        // Couleur alternée des lignes
        if ($fill) {
            $pdf->SetFillColor(245, 250, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell(25, 6, $date,                            1, 0, 'C', true);
        $pdf->Cell(35, 6, $op['reference_operation'],       1, 0, 'C', true);

        // Couleur selon type
        switch ($op['type_operation']) {
            case 'ENTREE':
                $pdf->SetTextColor(0, 128, 0);
                break;
            case 'SORTIE':
                $pdf->SetTextColor(200, 0, 0);
                break;
            case 'TRANSFERT':
                $pdf->SetTextColor(0, 0, 200);
                break;
        }
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell(25, 6, $op['type_operation'],            1, 0, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 7);

        $pdf->Cell(80, 6, $compte_debit,                    1, 0, 'L', true);
        $pdf->Cell(80, 6, $compte_credit,                   1, 0, 'L', true);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell(32, 6, number_format($montant, 0, ',', ' '), 1, 1, 'R', true);
        $pdf->SetFont('Arial', '', 7);

        $fill = !$fill;
        $ligne++;
    }
}

// ---------- 7. Sortie du PDF ----------
$nom_fichier = 'Journal_Comptable_' . date('Ymd_His') . '.pdf';
$pdf->Output('I', $nom_fichier); // 'I' = affiche dans le navigateur, 'D' = télécharge