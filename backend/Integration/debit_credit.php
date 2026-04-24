<?php
/**
 * Fonction de DEBIT (Entrée de fonds)
 * @param mysqli $connexion La variable de connexion
 * @param int $id_compte L'ID du compte financier
 * @param float $montant Le montant à ajouter
 */
function debiter($connexion, $id_compte, $montant) {
    $sql = "UPDATE compte SET solde_actuel = solde_actuel + ? WHERE id_compte = ?";
    $stmt = $connexion->prepare($sql);
    $stmt->bind_param("di", $montant, $id_compte);
    return $stmt->execute();
}

/**
 * Fonction de CREDIT (Sortie de fonds)
 * @param mysqli $connexion La variable de connexion
 * @param int $id_compte L'ID du compte financier
 * @param float $montant Le montant à retirer
 */
function crediter($connexion, $id_compte, $montant) {
    // Note : On pourrait ajouter une vérification ici pour empêcher 
    // un solde négatif si nécessaire.
    $sql = "UPDATE compte SET solde_actuel = solde_actuel - ? WHERE id_compte = ?";
    $stmt = $connexion->prepare($sql);
    $stmt->bind_param("di", $montant, $id_compte);
    return $stmt->execute();
}
?>