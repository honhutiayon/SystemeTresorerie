<?php

    require_once __DIR__ . '/../../vendor/autoload.php';

    use Faker\Factory;

    // --- CONFIGURATION BDD ---
    $host = 'localhost';
    $db   = 'tresorerie'; 
    $user = 'root';           
    $pass = 'Faraday08';               
    $charset = 'utf8mb4';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (\PDOException $e) {
        die("Erreur connexion : " . $e->getMessage());
    }

    $faker = Factory::create('fr_FR');

    /**
     * Fonction pour obtenir le solde actuel d'un compte
     */
    function getSoldeCompte($pdo, $id_compte) {
        $sql = "SELECT 
            (COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_source = :id AND type_operation = 'ENTREE' AND statut = 'VALIDE'), 0) +
            COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_destination = :id AND type_operation = 'TRANSFERT' AND statut = 'VALIDE'), 0)) - 
            (COALESCE((SELECT SUM(montant) FROM operation WHERE numcompte_source = :id AND (type_operation = 'SORTIE' OR type_operation = 'TRANSFERT') AND statut = 'VALIDE'), 0)) as solde";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id_compte]);
        $res = $stmt->fetch();
        return (float)$res['solde'];
    }

    // Récupérer les IDs valides
    $portefeuilles = $pdo->query("SELECT id_portefeuille FROM portefeuille")->fetchAll(PDO::FETCH_COLUMN);
    $comptes = $pdo->query("SELECT id_compte FROM compte")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($portefeuilles) || empty($comptes)) {
        die("Erreur : Assurez-vous d'avoir des données dans les tables 'portefeuille' et 'compte'.");
    }

    $count = 10000; // On génère les opérations
    echo "Début de la génération intelligente...\n";

    $sqlInsert = "INSERT INTO operation (reference_operation, type_operation, montant, motif, date_operation, id_portefeuille, numcompte_source, numcompte_destination, statut) 
                VALUES (:ref, :type, :montant, :motif, :date, :id_p, :id_src, :id_dest, :statut)";
    $stmtInsert = $pdo->prepare($sqlInsert);

    for ($i = 0; $i < $count; $i++) {
        $id_src = $faker->randomElement($comptes);
        $soldeActuel = getSoldeCompte($pdo, $id_src);
        
        // LOGIQUE ANTI-NÉGATIF :
        // Si le solde est faible (moins de 1000), on force une ENTREE pour renflouer les caisses
        if ($soldeActuel < 1000) {
            $type_op = 'ENTREE';
            $montant = $faker->randomFloat(2, 2000, 10000); // Gros dépôt
            $id_dest = null;
        } else {
            // Sinon, on peut dépenser ou transférer
            $type_op = $faker->randomElement(['SORTIE', 'TRANSFERT']);
            $montant = $faker->randomFloat(2, 10, $soldeActuel * 0.5); // On ne dépense jamais plus de 50% du solde
            
            $id_dest = null;
            if ($type_op === 'TRANSFERT') {
                $id_dest = $faker->randomElement($comptes);
                while ($id_dest == $id_src) $id_dest = $faker->randomElement($comptes);
            }
        }

        try {
            $stmtInsert->execute([
                'ref'     => 'OP-' . strtoupper($faker->bothify('??#####')),
                'type'    => $type_op,
                'montant' => $montant,
                'motif'   => $faker->sentence(3),
                'date'    => $faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s'),
                'id_p'    => $faker->randomElement($portefeuilles),
                'id_src'  => $id_src,
                'id_dest' => $id_dest,
                'statut'  => 'VALIDE' // On valide direct pour que le prochain tour de boucle voit le nouveau solde
            ]);
            echo "Opération $type_op de $montant sur compte $id_src réussie.\n";
        } catch (Exception $e) {
            echo "Erreur : " . $e->getMessage() . "\n";
        }
    }

    echo "\nGénération terminée. Tous les soldes devraient être positifs !";
    
?>