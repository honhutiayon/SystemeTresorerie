<?php
// Fonction pour augmenter le solde (ENTREE / DEBIT pour un compte d'actif)
function debiterCompte($conn, $id_compte, $montant) {
    $sql = "UPDATE compte SET solde_actuel = solde_actuel + ? WHERE id_compte = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $montant, $id_compte);
    return $stmt->execute();
}

// Fonction pour diminuer le solde (SORTIE / CREDIT pour un compte d'actif)
function crediterCompte($conn, $id_compte, $montant) {
    // On peut ajouter une vérification de solde ici
    $sql = "UPDATE compte SET solde_actuel = solde_actuel - ? WHERE id_compte = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $montant, $id_compte);
    return $stmt->execute();
}
?>