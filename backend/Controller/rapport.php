<?php
// 1. Synchronisation avec l'heure du Cameroun (GMT+1)
date_default_timezone_set('Africa/Douala');

ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once __DIR__ . '/../../fpdf186/fpdf.php';
require_once '../connexion/connexion.php';
require_once 'auth_guard.php';

function toIso($string) {
    return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
}

class RapportTresorerie extends FPDF {
    
    function Header() {
        // --- BANDEAU VERT (Style SIGERIS) ---
        $this->SetFillColor(33, 94, 56); 
        $this->Rect(10, 10, 190, 25, 'F');
        
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 16);
        $this->SetY(15);
        $this->Cell(0, 7, toIso("JOURNAL D'ÉTAT DE TRÉSORERIE"), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, toIso("Système SIGERIS - Plan Comptable OHADA"), 0, 1, 'C');
        
        // --- BARRE D'INFOS AVEC L'HEURE EXACTE ---
        $this->Ln(5);
        $this->SetFillColor(255, 253, 230); // Jaune clair
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 8);
        
        $this->Cell(65, 7, toIso(" Période : Début au Aujourd'hui"), 0, 0, 'L', true);
        $this->Cell(60, 7, toIso("Type filtre : Tous"), 0, 0, 'C', true);
        // Affichage de la date et l'heure précise
        $this->Cell(65, 7, toIso("Édité le : " . date('d/m/Y à H:i:s')), 0, 1, 'R', true);
        $this->Ln(3);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(100);
        $this->Cell(0, 10, toIso('Page ') . $this->PageNo() . '/{nb} - Généré à ' . date('H:i:s'), 0, 0, 'C');
    }

    // Bloc pour le solde global
    function AfficherSoldeGlobal($montant) {
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(240, 240, 240);
        $this->SetDrawColor(33, 94, 56);
        $this->Cell(130, 10, toIso(" SOLDE TOTAL DISPONIBLE (GLOBAL)"), 'LBT', 0, 'L', true);
        $this->SetTextColor(33, 94, 56);
        $this->Cell(60, 10, number_format($montant, 0, ',', ' ') . " FCFA ", 'RBT', 1, 'R', true);
        $this->SetTextColor(0);
        $this->Ln(5);
    }

    function TableauSoldes($header, $data) {
        $this->SetFillColor(33, 94, 56);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        
        $w = array(60, 40, 50, 40); 
        for($i=0;$i<count($header);$i++)
            $this->Cell($w[$i], 8, toIso($header[$i]), 1, 0, 'C', true);
        $this->Ln();
        
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(0);
        
        foreach($data as $row) {
            $this->Cell($w[0], 7, toIso($row['nom_compte']), 1, 0, 'L');
            $this->Cell($w[1], 7, toIso($row['type_compte']), 1, 0, 'C');
            
            $this->SetFont('Arial', 'B', 8);
            $this->Cell($w[2], 7, number_format($row['solde_actuel'], 0, ',', ' ') . ' FCFA', 1, 0, 'R');
            $this->SetFont('Arial', '', 8);

            if ($row['solde_actuel'] < 0) {
                $this->SetTextColor(200, 0, 0);
                $status = 'DÉCOUVERT';
            } else {
                $this->SetTextColor(0, 100, 0);
                $status = 'OK';
            }
            $this->Cell($w[3], 7, $status, 1, 0, 'C');
            $this->SetTextColor(0);
            $this->Ln();
        }
    }
}

try {
    // Calcul du solde global (Entrées - Sorties)
    $sqlGlobal = "SELECT 
        SUM(CASE WHEN type_operation = 'ENTREE' THEN montant ELSE 0 END) - 
        SUM(CASE WHEN type_operation = 'SORTIE' THEN montant ELSE 0 END) as total 
        FROM operation WHERE statut = 'VALIDE'";
    $resGlobal = mysqli_query($connexion, $sqlGlobal);
    $dataGlobal = mysqli_fetch_assoc($resGlobal);
    $soldeTotalGlobal = (float)($dataGlobal['total'] ?? 0);

    // Détail par compte
    $sqlComptes = "SELECT c.nom_compte, c.type_compte,
        (COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_source = c.id_compte AND type_operation = 'ENTREE' AND statut = 'VALIDE'), 0) +
         COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_destination = c.id_compte AND type_operation = 'TRANSFERT' AND statut = 'VALIDE'), 0)) - 
        (COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_source = c.id_compte AND (type_operation = 'SORTIE' OR type_operation = 'TRANSFERT') AND statut = 'VALIDE'), 0)) as solde_actuel
        FROM compte c";
    $resComptes = mysqli_query($connexion, $sqlComptes);
    $comptes = [];
    while($r = mysqli_fetch_assoc($resComptes)) $comptes[] = $r;

    if (ob_get_length()) ob_end_clean();

    $pdf = new RapportTresorerie();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Affichage du solde global
    $pdf->AfficherSoldeGlobal($soldeTotalGlobal);

    // Tableau
    $header = array('Compte', 'Type', 'Montant (FCFA)', 'Statut');
    $pdf->TableauSoldes($header, $comptes);

    $pdf->Output('I', 'Journal_Tresorerie.pdf');

} catch (Exception $e) {
    ini_set('display_errors', 1);
    die("Erreur : " . $e->getMessage());
}

?>