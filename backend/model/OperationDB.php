<?php 

require_once 'Database.php';

class OperationDB {
    private $db;

    public function __construct(){
        $this->db = new Database();
    }

    
    public function create($reference_operation, $type_operation, $montant, $motif, $id_portefeuille, $id_compte_source, $id_compte_destination, $id_utilisateur, $statut = 'EN_COURS') {
    $sql = "INSERT INTO operation SET reference_operation = ?, type_operation = ?, montant = ?, motif = ?, id_portefeuille = ?, id_compte_source = ?, id_compte_destination = ?, id_utilisateur = ?, statut = ?";
    $params = array($reference_operation, $type_operation, $montant, $motif, $id_portefeuille, $id_compte_source, $id_compte_destination, $id_utilisateur, $statut);
    $this->db->request($sql, $params);
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

    
    public function readUser($id_utilisateur){
        $sql = "SELECT * FROM operation WHERE id_utilisateur = ? ORDER BY date_operation DESC";
        $params = array($id_utilisateur);
        $req = $this->db->request($sql, $params);
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

    
    public function update($id_operation, $type_operation, $montant, $motif, $id_portefeuille, $id_compte_source, $id_compte_destination, $id_utilisateur, $statut){
        $sql = "UPDATE operation SET type_operation = ?, montant = ?, motif = ?, id_portefeuille = ?, id_compte_source = ?, id_compte_destination = ?, id_utilisateur = ?, statut = ? WHERE id_operation = ?";
        $params = array($type_operation, $montant, $motif, $id_portefeuille, $id_compte_source, $id_compte_destination, $id_utilisateur, $statut, $id_operation);
        $this->db->request($sql, $params);
    }

    
    public function delete($id_operation){
        $sql = "DELETE FROM operation WHERE id_operation = ?";
        $params = array($id_operation);
        $this->db->request($sql, $params);
    }
}
?>