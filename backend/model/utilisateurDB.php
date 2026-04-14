<?php

require_once 'Database.php';

class UtilisateurDB {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    
    public function create($id_quartier, $id_specialite, $id_niveau, $nom, $prenom, $email, $telephone, $photo, $password, $statut, $role) {
        $sql = "INSERT INTO utilisateur SET id_quartier = ?, id_specialite = ?, id_niveau = ?, nom = ?, prenom = ?, email = ?, telephone = ?, photo = ?, password = ?, statut = ?, role = ?";
        $params = array($id_quartier, $id_specialite, $id_niveau, $nom, $prenom, $email, $telephone, $photo, $password, $statut, $role);
        $this->db->request($sql, $params);
    }

   
    public function readEmail($email) {
        $sql = "SELECT email FROM utilisateur WHERE email = ?";
        $params = array($email);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, true);
        return $data;
    }

    
    public function readRole($role) {
        $sql = "SELECT * FROM utilisateur WHERE role = ? ORDER BY id_utilisateur DESC";
        $params = array($role);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, false);
        return $data;
    }

    
    public function read($id_utilisateur) {
        $sql = "SELECT * FROM utilisateur WHERE id_utilisateur = ?";
        $params = array($id_utilisateur);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, true);
        return $data;
    }

    
    public function chaineConnexion($email, $password) {
        
        $sql = "SELECT * FROM utilisateur WHERE email = ?";
        $params = array($email);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, true);
         return $data;
    }

    
    public function update($id_utilisateur, $id_quartier, $id_specialite, $id_niveau, $nom, $prenom, $email, $telephone, $password, $statut, $role) {
        
        $sql = "UPDATE utilisateur SET id_quartier = ?, id_specialite = ?, id_niveau = ?, nom = ?, prenom = ?, email = ?, telephone = ?, password = ?, statut = ?, role = ? WHERE id_utilisateur = ?";
        $params = array($id_quartier, $id_specialite, $id_niveau, $nom, $prenom, $email, $telephone, $password, $statut, $role, $id_utilisateur);
        $this->db->request($sql, $params);
    }

    
    public function updateStatus($statut, $id_utilisateur) {
        $sql = "UPDATE utilisateur SET statut = ? WHERE id_utilisateur = ?";
        $params = array($statut, $id_utilisateur);
        $this->db->request($sql, $params);
    }

   
    public function delete($id_utilisateur) {
        $sql = "DELETE FROM utilisateur WHERE id_utilisateur = ?";
        $params = array($id_utilisateur);
        $this->db->request($sql, $params);
    }

    
    public function readAll() {
        $sql = "SELECT * FROM utilisateur ORDER BY id_utilisateur DESC";
        $req = $this->db->request($sql);
        $data = $this->db->recover($req, false);
        return $data;
    }

    
    public function isAdmin($id_utilisateur) {
        $sql = "SELECT role FROM utilisateur WHERE id_utilisateur = ?";
        $params = array($id_utilisateur);
        $req = $this->db->request($sql, $params);
        $data = $this->db->recover($req, true);

        return ($data && $data['role'] === 'admin');
    }
}

?>