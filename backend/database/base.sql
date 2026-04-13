CREATE DATABASE IF NOT EXISTS tresorerie;
USE tresorerie;

-- ============================================================
-- 1. utilisateur
-- ============================================================
CREATE TABLE utilisateur (
    id_utilisateur INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin','comptable','caissiere','controleur') NOT NULL,
    token VARCHAR(255) NULL,
    token_expiration DATETIME NULL,
    tentatives_echec TINYINT DEFAULT 0,
    compte_bloque BOOLEAN DEFAULT FALSE,
    actif BOOLEAN DEFAULT TRUE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion TIMESTAMP NULL
);

-- ============================================================
-- 2. plan_comptable
-- ============================================================
CREATE TABLE plan_comptable (
    id_compte_comptable INT PRIMARY KEY AUTO_INCREMENT,
    code_comptable VARCHAR(10) UNIQUE NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    classe INT NOT NULL CHECK (classe BETWEEN 1 AND 7),
    type_mouvement ENUM('actif','passif','charge','produit') NOT NULL,
    solde_normal ENUM('debit','credit') DEFAULT 'debit',
    actif BOOLEAN DEFAULT TRUE
);

-- ============================================================
-- 3. compte
-- ============================================================
CREATE TABLE compte (
    id_compte INT PRIMARY KEY AUTO_INCREMENT,
    nom_compte VARCHAR(100) NOT NULL,
    type_compte ENUM('caisse','banque') NOT NULL,
    numero_compte VARCHAR(20) UNIQUE NOT NULL,
    solde_actuel DECIMAL(15,2) DEFAULT 0.00,
    solde_initial DECIMAL(15,2) DEFAULT 0.00,
    id_compte_comptable INT NOT NULL,
    actif BOOLEAN DEFAULT TRUE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_compte_comptable) REFERENCES plan_comptable(id_compte_comptable)
);

-- ============================================================
-- 4. portefeuille
-- (créé AVANT operation car operation en a besoin)
-- ============================================================
CREATE TABLE portefeuille (
    id_portefeuille INT PRIMARY KEY AUTO_INCREMENT,
    id_utilisateur INT NOT NULL,
    id_compte INT NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('ACTIF','INACTIF') DEFAULT 'ACTIF',
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur),
    FOREIGN KEY (id_compte) REFERENCES compte(id_compte)
);

-- ============================================================
-- 5. operation
-- (créé APRÈS portefeuille)
-- ============================================================
CREATE TABLE operation (
    id_operation INT PRIMARY KEY AUTO_INCREMENT,
    reference_operation VARCHAR(20) UNIQUE NOT NULL,
    type_operation ENUM('ENTREE','SORTIE','TRANSFERT') NOT NULL,
    montant DECIMAL(15,2) NOT NULL CHECK (montant > 0),
    motif VARCHAR(255),
    beneficiaire VARCHAR(150),
    date_operation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_portefeuille INT NOT NULL,
    id_compte_source INT NULL,
    id_compte_destination INT NULL,
    id_utilisateur INT NOT NULL,
    statut ENUM('EN_COURS','VALIDE','ANNULEE') DEFAULT 'EN_COURS',
    FOREIGN KEY (id_portefeuille) REFERENCES portefeuille(id_portefeuille),
    FOREIGN KEY (id_compte_source) REFERENCES compte(id_compte),
    FOREIGN KEY (id_compte_destination) REFERENCES compte(id_compte),
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

-- ============================================================
-- 6. ecriture_comptable
-- (générée automatiquement à chaque opération - OHADA)
-- ============================================================
CREATE TABLE ecriture_comptable (
    id_ecriture INT PRIMARY KEY AUTO_INCREMENT,
    id_operation INT NOT NULL,
    id_compte_comptable INT NOT NULL,
    sens ENUM('debit','credit') NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    libelle VARCHAR(255),
    date_ecriture DATE NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_operation) REFERENCES operation(id_operation),
    FOREIGN KEY (id_compte_comptable) REFERENCES plan_comptable(id_compte_comptable)
);

-- ============================================================
-- 7. recu
-- ============================================================
CREATE TABLE recu (
    id_recu INT PRIMARY KEY AUTO_INCREMENT,
    numero_recu VARCHAR(30) UNIQUE NOT NULL,
    id_operation INT NOT NULL,
    contenu_html LONGTEXT,
    date_generation DATETIME DEFAULT CURRENT_TIMESTAMP,
    genere_par INT NOT NULL,
    FOREIGN KEY (id_operation) REFERENCES operation(id_operation),
    FOREIGN KEY (genere_par) REFERENCES utilisateur(id_utilisateur)
);

-- ============================================================
-- 8. journal_audit
-- ============================================================
CREATE TABLE journal_audit (
    id_audit INT PRIMARY KEY AUTO_INCREMENT,
    id_utilisateur INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    adresse_ip VARCHAR(45),
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);