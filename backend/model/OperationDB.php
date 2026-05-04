<?php 

require_once 'Database.php';

class OperationDB {

    private $db;

    public function __construct(){
        $this->db = new Database();
    }

    // CREATE
   /* public function create($reference_operation, $type_operation, $montant, $motif, $id_portefeuille, $numcompte_source, $numcompte_destination, $statut = 'EN_COURS') {
    $sql = "INSERT INTO operation SET  reference_operation = ?, type_operation = ?, montant = ?,  motif = ?,  id_portefeuille = ?,  numcompte_source = ?, numcompte_destination = ?, statut = ?";
    $params = array($reference_operation, $type_operation, $montant, $motif, $id_portefeuille, $numcompte_source, $numcompte_destination, $statut);
     $this->db->request($sql, $params);

   }*/
   public function create($reference_operation, $type_operation, $montant, $motif, $id_portefeuille, $numcompte_source, $numcompte_destination, $statut = 'EN_COURS') {

    $sql = "INSERT INTO operation 
            SET reference_operation = ?, type_operation = ?, montant = ?, motif = ?, id_portefeuille = ?, numcompte_source = ?, numcompte_destination = ?, statut = ?";

    $params = array($reference_operation, $type_operation, $montant, $motif, $id_portefeuille, $numcompte_source, $numcompte_destination, $statut);

    $result = $this->db->request($sql, $params);

    return $result; // ⭐ IMPORTANT
}


    
    public function read($id_operation){
        $sql = "SELECT * FROM operation WHERE id_operation = ?";
        $params = array($id_operation);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, true);
        return $data;
    }

    
    public function readAll(){
        $sql = "SELECT * FROM operation ORDER BY date_operation DESC";
        $req = $this->db->request($sql);
        $data = $this->db->recover($req, false);
        return $data;
    }

    
    public function readPortefeuille($id_portefeuille){
        $sql = "SELECT * FROM operation WHERE id_portefeuille = ? ORDER BY date_operation DESC";
        $params = array($id_portefeuille);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, false);
        return $data;
    }

    
    /*public function update($id_operation,$type_operation,$montant,$motif,$id_portefeuille,$numcompte_source,$numcompte_destination,$statut){
        $sql = "UPDATE operation SET type_operation = ?, montant = ?, motif = ?, id_portefeuille = ?,   numcompte_source = ?, numcompte_destination = ?, statut = ?  WHERE id_operation = ?";
        $params = array( $type_operation, $montant, $motif, $id_portefeuille, $numcompte_source, $numcompte_destination, $statut,  $id_operation);
         $this->db->request($sql, $params);

        
    }*/

    public function update($id_operation,$type_operation,$montant,$motif,$id_portefeuille,$numcompte_source,$numcompte_destination,$statut){

    $sql = "UPDATE operation 
            SET type_operation = ?, montant = ?, motif = ?, id_portefeuille = ?, numcompte_source = ?, numcompte_destination = ?, statut = ?  
            WHERE id_operation = ?";

    $params = array(
        $type_operation,
        $montant,
        $motif,
        $id_portefeuille,
        $numcompte_source,
        $numcompte_destination,
        $statut,
        $id_operation
    );

    $req = $this->db->request($sql, $params);

    // ✅ IMPORTANT : retourner un résultat réel
    return $req;
}

    
    public function readReference($reference) {
        $sql = "SELECT * FROM operation WHERE reference_operation = ?";
        $params = array($reference);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, true);
        return $data;
    }

    // SOLDE COMPTE (corrigé avec id_compte)
    public function getSoldeCompte($id_compte) {
        $sql = "SELECT total_entree, total_sortie FROM compte WHERE id_compte = ?";
        $params = array($id_compte);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, true);
        if ($data !== false && $data !== null) {
            return (float)$data->total_entree - (float)$data->total_sortie;
        }

        return null;
    }

    // COMPTE EXISTE (corrigé avec id_compte)
   /*public function compteExiste($id_compte) {
        $sql = "SELECT COUNT(*) as total FROM compte WHERE id_compte = ?";
        $params = array($id_compte);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, true);
        if (!$data || !isset($data->total)) {
            return false;
        }
        return (int)$data->total > 0;
    }*/

    public function compteExiste($numcompte) {
    // Requête pour vérifier l'existence du compte par numéro de compte
    $sql = "SELECT COUNT(*) as total FROM compte WHERE numero_compte = ?";
    
    // Paramètre à passer : le numéro de compte
    $params = array($numcompte);
    
    // Exécution de la requête
    $req = $this->db->request($sql, $params);
    
    // Récupération des résultats
    $data = $this->db->recover($req, true);
    
    // Vérification que la donnée existe
    if (!$data || !isset($data->total)) {
        return false;
    }
    
    // Retourner vrai si le compte existe, sinon faux
    return (int)$data->total > 0;
}
    

    // ENTREE
    public function addEntree($id_compte, $montant) {
    $sql = "UPDATE compte  SET total_entree = total_entree + ?   WHERE id_compte = ?";
    $params = array($montant, $id_compte);
    $req = $this->db->request($sql, $params);
    if (!$req) {
        return false;
    }

    return true;
}

    // SORTIE (corrigé avec id_compte)
    public function addSortie($id_compte, $montant) {
    $sql = "UPDATE compte SET total_sortie = total_sortie + ?  WHERE id_compte = ?";
    $params = array($montant, $id_compte);
    $req = $this->db->request($sql, $params);
    if (!$req) {
        return false;
    }

    return true;
}

    
    public function generateReference() {
        $reference = 'OP' . time() . rand(100, 999);
        while ($this->readReference($reference) != false) {
            $reference = 'OP' . time() . rand(100, 999);
        }

        return $reference;
    }

    // TRANSACTIONS
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }

    public function commit() {
        return $this->db->commit();
    }

    public function rollBack() {
        return $this->db->rollBack();
    }

    public function updateStatus($id_operation, $statut){
    $sql = "UPDATE operation SET statut = ? WHERE id_operation = ?";
    $params = array($statut, $id_operation);

    return $this->db->request($sql, $params);

}

/*public function deleteByReference($reference) {
    // Requête SQL pour supprimer une opération en fonction de la référence
    $sql = "DELETE FROM operation WHERE reference_operation = ?";
    $params = array($reference);

    // Exécution de la requête
    return $this->db->request($sql, $params);
}*/
public function deleteByReference($reference) {

    $sql = "DELETE FROM operation WHERE reference_operation = ?";
    $params = [$reference];

    return $this->db->request($sql, $params);
}

// Dans ta classe operationdb
public function getCompteByNumero($numero_compte) {
    $sql = "SELECT * FROM compte WHERE numero_compte = ?";
    $params = array($numero_compte);
    $req = $this->db->request($sql, $params);
    $data = $this->db->recover($req, true);
    return $data;  // Retourne les données du compte, y compris l'ID
}

}
?>