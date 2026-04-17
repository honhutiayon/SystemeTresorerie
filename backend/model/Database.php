<?php

    class Database{
        private $dsn="mysql:host=localhost;dbname=tresorerie;port=3306";
        private $username="root"; // chacun met son nom utilisateur
        private $password="ton_mot_de_passe"; // chacun modifier et met son mot de passe

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
    }

?>