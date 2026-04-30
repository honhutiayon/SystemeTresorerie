
create database tresorerie;
use tresorerie;

-- 1. utilisateur
CREATE TABLE utilisateur (
    id_utilisateur INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin','comptable','caissiere','controleur') NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion TIMESTAMP NULL
);

-- 2. plan_comptable
CREATE TABLE plan_comptable (
    id_compte_comptable INT PRIMARY KEY AUTO_INCREMENT,
    code_comptable VARCHAR(10) UNIQUE NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    classe INT NOT NULL CHECK (classe BETWEEN 1 AND 7),
    type_mouvement ENUM('actif','passif','charge','produit') NOT NULL,
    solde_normal ENUM('debit','credit') DEFAULT 'debit'
);

-- 3. compte
CREATE TABLE compte (
    id_compte INT PRIMARY KEY AUTO_INCREMENT,
    nom_compte VARCHAR(100) NOT NULL,
    type_compte ENUM('caisse','banque') NOT NULL,
    numero_compte VARCHAR(20) UNIQUE NOT NULL,
    solde_actuel DECIMAL(15,2) DEFAULT 0.00,
    solde_initial DECIMAL(15,2) DEFAULT 0.00,
    id_compte_comptable INT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_compte_comptable) REFERENCES plan_comptable(id_compte_comptable)
);



-- 5. portefeuille
CREATE TABLE portefeuille (
    id_portefeuille INT PRIMARY KEY AUTO_INCREMENT,
    id_compte INT NOT NULL,
    id_utilisateur INT NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('GENERE','IMPRIME','ANNULE') DEFAULT 'GENERE',
    FOREIGN KEY (id_compte) REFERENCES compte(id_compte),
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

-- 4. operation
CREATE TABLE operation (
    id_operation INT PRIMARY KEY AUTO_INCREMENT,
    reference_operation VARCHAR(20) UNIQUE NOT NULL,
    type_operation ENUM('ENTREE','SORTIE','TRANSFERT') NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    motif VARCHAR(100),
    date_operation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_portefeuille INT NOT NULL,
    id_compte_source INT NOT NULL,
    id_compte_destination INT NULL,
    statut ENUM('EN_COURS','VALIDE','ANNULEE') DEFAULT 'EN_COURS',
    FOREIGN KEY (id_portefeuille) REFERENCES portefeuille(id_portefeuille)
);
