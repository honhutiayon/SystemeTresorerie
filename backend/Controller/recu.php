<?php
// Désactiver l'affichage des erreurs pour ne pas corrompre le PDF
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Heure exacte du Cameroun
date_default_timezone_set('Africa/Douala');

require_once __DIR__ . '/../../fpdf186/fpdf.php';
require_once '../connexion/connexion.php';
require_once 'auth_guard.php';

// Nettoyage du tampon pour éviter l'erreur 500
if (ob_get_length()) ob_end_clean();

$id_op = $_GET['id_operation'] ?? null;
if (!$id_op) { 
    header('Content-Type: text/plain');
    die("ID operation manquant"); 
}

try {
    // 2. Requête adaptée à TA table (id_compte_source, date_operation, motif)
    $query = "SELECT o.*, c.nom_compte, u.nom as nom_caissier 
              FROM operation o
              LEFT JOIN compte c ON o.numcompte_source = c.id_compte
              LEFT JOIN utilisateur u ON o.id_portefeuille = u.id_utilisateur 
              WHERE o.id_operation = ?";

    $stmt = mysqli_prepare($connexion, $query);
    if (!$stmt) throw new Exception(mysqli_error($connexion));
    
    mysqli_stmt_bind_param($stmt, "i", $id_op);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $op = mysqli_fetch_assoc($result);

    if (!$op) die("Operation introuvable dans la base");

    // 3. Génération du PDF
    class RecuTresorerie extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, utf8_decode("SYSTÈME SIGERIS - REÇU DE CAISSE"), 0, 1, 'C');
            $this->SetDrawColor(33, 94, 56);
            $this->Line(10, 22, 140, 22);
            $this->Ln(10);
        }
    }

    $pdf = new RecuTresorerie();
    $pdf->AddPage('P', array(150, 180)); 
    $pdf->SetFont('Arial', '', 11);

    // Utilisation de TA colonne reference_operation pour l'unicité
    $ref = $op['reference_operation']; 

    // Encadré Référence
    $pdf->SetFillColor(230, 240, 230);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, utf8_decode(" RÉFÉRENCE PIÈCE : " . $ref), 1, 1, 'L', true);
    $pdf->Ln(5);

    // Infos de l'opération
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, "Date & Heure :", 0, 0);
    $pdf->Cell(0, 8, date('d/m/Y H:i', strtotime($op['date_operation'])), 0, 1);

    $pdf->Cell(50, 8, "Type :", 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, $op['type_operation'], 0, 1);
    $pdf->SetFont('Arial', '', 11);

    $pdf->Cell(50, 8, "Compte Source :", 0, 0);
    $pdf->Cell(0, 8, utf8_decode($op['nom_compte'] ?? 'Non spécifié'), 0, 1);

    $pdf->Ln(5);
    $pdf->SetFillColor(255, 253, 230);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(50, 12, " MONTANT :", 0, 0);
    $pdf->Cell(80, 12, number_format($op['montant'], 0, ',', ' ') . " FCFA", 0, 1, 'R', true);

    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, "Motif :", 0, 0);
    $pdf->MultiCell(0, 8, utf8_decode($op['motif'] ?? 'N/A'), 0, 'L');

    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(65, 5, "Signature Caissier", 0, 0, 'C');
    $pdf->Cell(65, 5, "Signature Client", 0, 1, 'C');
    
    // Bas de page
    $pdf->SetY(-20);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, "Document genere le " . date('d/m/Y H:i:s') . " - Statut: " . $op['statut'], 0, 1, 'C');

    $pdf->Output('I', "Recu_" . $ref . ".pdf");

} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo "Erreur SQL : " . $e->getMessage();
}

?>