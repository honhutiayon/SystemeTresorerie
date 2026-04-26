<?php






class Database {

    private $dsn = "mysql:host=localhost;dbname=tresorerie;port=3306";
    private $username = "root";
    private $password = "ton_mot_de_passe";

    private $pdo;

    // Connexion PDO (une seule fois)
    public function chaineConnexion() {
        if ($this->pdo === null) {
            $this->pdo = new PDO($this->dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return $this->pdo;
    }

    // Exécuter requête
    public function request($sql, $params = null) {
        $pdo = $this->chaineConnexion();
        $req = $pdo->prepare($sql);

        if ($params === null) {
            $req->execute();
        } else {
            $req->execute($params);
        }

        return $req;
    }

    // Récupérer données
    public function recover($req, $one = true) {
        $req->setFetchMode(PDO::FETCH_OBJ);

        if ($one) {
            return $req->fetch();
        }

        return $req->fetchAll();
    }

    // BEGIN TRANSACTION
    public function beginTransaction() {
        return $this->chaineConnexion()->beginTransaction();
    }

    // COMMIT
    public function commit() {
        return $this->chaineConnexion()->commit();
    }

    // ROLLBACK
    public function rollBack() {
        return $this->chaineConnexion()->rollBack();
    }
}

  /*class Database{
        private $dsn="mysql:host=localhost;dbname=tresorerie;port=3306";
        private $username="root"; 

        private $password="ton_mot_de_passe"; 
           private $pdo;

        public function chaineConnexion(){
            $pdo = new PDO($this->dsn,$this->username,$this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        }

        public function request($sql, $params=null){
            $pdo = $this->chaineConnexion();
            $req = $pdo->prepare($sql);
            if($params == null){
                $req->execute();
            }
            else{
                $req->execute($params);
            }

            return $req;
        }

        public function recover($req, $one=true){
            $data = null;
            $req->setFetchMode(PDO::FETCH_OBJ);

            if($one == true){
                $data = $req->fetch();
            }
            else{
                $data = $req->fetchAll();
            }

            return $data;
        }

       public function beginTransaction() {
        return $this->pdo->beginTransaction();
        }

        public function commit() {
        return $this->pdo->commit();
        }

    public function rollBack() {
        return $this->pdo->rollBack();
       }

    }*/





    



?>