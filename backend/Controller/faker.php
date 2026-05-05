<?php
// 1. Correction du chemin : on remonte d'un dossier pour trouver /vendor
require_once __DIR__ . '/../vendor/autoload.php';

use Faker\Factory;

// Configuration PDO
$host = 'localhost';
$dbname = 'tresorerie';
$username = 'root';
$password = 'Faraday08'; // Vérifie bien que c'est ton mot de passe MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "🚀 Début du seeding de la base de données...\n\n";

    $faker = Factory::create('fr_FR');

    // Désactiver les contraintes de clés étrangères pour vider les tables proprement
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Nettoyer les tables avant insertion
    echo "🧹 Nettoyage des tables...\n";
    $pdo->exec('TRUNCATE TABLE operation');
    $pdo->exec('TRUNCATE TABLE portefeuille');
    $pdo->exec('TRUNCATE TABLE compte');
    $pdo->exec('TRUNCATE TABLE plan_comptable');
    $pdo->exec('TRUNCATE TABLE utilisateur');

    // 1. UTILISATEURS (10 utilisateurs)
    echo "👥 Création des utilisateurs...\n";
    $users = [];
    $roles = ['admin', 'comptable', 'caissiere', 'controleur'];

    for ($i = 0; $i < 5000; $i++) {
        $nom = $faker->lastName();
        $prenom = $faker->firstName();
        $email = strtolower($faker->unique()->safeEmail());
        $mot_de_passe = password_hash('password123', PASSWORD_DEFAULT);
        $role_choisi = $roles[array_rand($roles)];

        $stmt = $pdo->prepare("
            INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nom, $prenom, $email, $mot_de_passe, $role_choisi]);

        $users[] = [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'role' => $role_choisi
        ];
    }
    echo "✅ " . count($users) . " utilisateurs créés\n";

    // Récupérer IDs utilisateurs
    $userIds = $pdo->query("SELECT id_utilisateur FROM utilisateur")->fetchAll(PDO::FETCH_COLUMN);

    // 2. PLAN COMPTABLE
    echo "📊 Création du plan comptable...\n";
    $planComptable = [
        ['101', 'CAPITAL SOCIAL', 1, 'passif', 'credit'],
        ['102', 'RESERVES', 1, 'passif', 'credit'],
        ['109', 'RESULTAT NET', 1, 'passif', 'credit'],
        ['201', 'IMMOBILISATIONS INCORPORELLES', 2, 'actif', 'debit'],
        ['202', 'IMMOBILISATIONS CORPORELLES', 2, 'actif', 'debit'],
        ['301', 'MATIERES PREMIERES', 3, 'actif', 'debit'],
        ['401', 'CLIENTS', 4, 'actif', 'debit'],
        ['411', 'FOURNISSEURS', 4, 'passif', 'credit'],
        ['501', 'CAISSE', 5, 'actif', 'debit'],
        ['512', 'BANQUE CCP', 5, 'actif', 'debit'],
        ['601', 'ACHATS DE MARCHANDISES', 6, 'charge', 'debit'],
        ['701', 'VENTES DE MARCHANDISES', 7, 'produit', 'credit']
    ];

    foreach ($planComptable as $compte) {
        $stmt = $pdo->prepare("
            INSERT INTO plan_comptable (code_comptable, libelle, classe, type_mouvement, solde_normal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute($compte);
    }

    // Récupérer IDs plan comptable pour la trésorerie (501 et 512)
    $plan501 = $pdo->query("SELECT id_compte_comptable FROM plan_comptable WHERE code_comptable = '501'")->fetchColumn();
    $plan512 = $pdo->query("SELECT id_compte_comptable FROM plan_comptable WHERE code_comptable = '512'")->fetchColumn();

    // 3. COMPTES TRÉSORERIE
    echo "💰 Création des comptes trésorerie...\n";
    $comptes = [
        ['Caisse Principale', 'caisse', 'CAISSE-001', 50000, 50000, $plan501],
        ['Banque Centrale', 'banque', 'BK-001', 1000000, 1000000, $plan512]
    ];

    foreach ($comptes as $compte) {
        $stmt = $pdo->prepare("
            INSERT INTO compte (nom_compte, type_compte, numero_compte, solde_initial, solde_actuel, id_compte_comptable)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($compte);
    }

    // Récupérer IDs comptes
    $compteIds = $pdo->query("SELECT id_compte FROM compte")->fetchAll(PDO::FETCH_COLUMN);

    // 4. PORTEFEUILLES
    echo "📋 Création des portefeuilles...\n";
    $statuts = ['GENERE', 'IMPRIME', 'ANNULE'];
    for ($i = 0; $i < 15; $i++) {
        $pdo->prepare("INSERT INTO portefeuille (id_compte, id_utilisateur, statut) VALUES (?, ?, ?)")
            ->execute([$compteIds[array_rand($compteIds)], $userIds[array_rand($userIds)], $statuts[array_rand($statuts)]]);
    }

    // Récupérer IDs portefeuilles
    $portefeuilleIds = $pdo->query("SELECT id_portefeuille FROM portefeuille")->fetchAll(PDO::FETCH_COLUMN);

    // 5. OPÉRATIONS
    echo "💸 Création des opérations...\n";
    for ($i = 0; $i < 50; $i++) {
        $type = ['ENTREE', 'SORTIE', 'TRANSFERT'][array_rand(['ENTREE', 'SORTIE', 'TRANSFERT'])];
        $pdo->prepare("
            INSERT INTO operation (reference_operation, type_operation, montant, motif, id_portefeuille, id_compte_source, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            'OP-' . $faker->unique()->numerify('########'),
            $type,
            $faker->randomFloat(2, 100, 5000),
            $faker->sentence(3),
            $portefeuilleIds[array_rand($portefeuilleIds)],
            $compteIds[array_rand($compteIds)],
            'VALIDE'
        ]);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "\n🎉 SEEDING TERMINÉ AVEC SUCCÈS !\n";

    echo "\n🔑 EXEMPLES D'UTILISATEURS (MDP: password123) :\n";
    foreach (array_slice($users, 0, 3) as $u) {
        echo "   - {$u['email']} | Rôle: {$u['role']}\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
