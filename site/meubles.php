<?php
/**
 * Meubles de saison disponibles sur une période.
 * Exemple d'URL : meubles.php?du=2026-12-01&au=2026-12-31&hemisphere=NH
 * Sans paramètre : le mois en cours, hémisphère Nord.
 */
require 'config.php';
require 'includes/fonctions.php';

// 1. Lire les paramètres de l'URL, en les vérifiant
$du         = getDateParam('du', date('Y-m-01'));         // premier jour du mois en cours
$au         = getDateParam('au', date('Y-m-t'));          // dernier jour du mois en cours
$hemisphere = getChoix('hemisphere', array('NH', 'SH'), 'NH');
if ($au < $du) {                                         // dates inversées : on cherche sur le seul jour « du »
    $au = $du;
}

// 2. Requête : les meubles dont une saison a une période qui chevauche les dates choisies.
//    Une période chevauche [du ; au] si elle commence avant « au » et finit après « du ».
//    Un meuble peut appartenir à plusieurs saisons : DISTINCT l'affiche une seule fois.
$meubles = requete($dbh,
"SELECT DISTINCT m.nom_meuble, cat.nom_categorie, m.prix_achat
FROM meuble m
JOIN categorie cat ON cat.id_categorie = m.id_categorie
JOIN proposer pr   ON pr.id_meuble = m.id_meuble
JOIN periode p     ON p.id_evenement = pr.id_evenement
WHERE p.hemisphere_periode = :hemisphere
  AND p.date_debut <= :au
  AND p.date_fin >= :du
ORDER BY m.nom_meuble",
    array(':hemisphere' => $hemisphere, ':du' => $du, ':au' => $au))->fetchAll();

// 3. Affichage
$titre = 'Meubles de saison';
$pageActive = 'meubles';
require 'includes/header.php';
?>
<h1>Meubles de saison</h1>

<form method="get" action="meubles.php">
    <label>Du <input type="date" name="du" value="<?= e($du) ?>" min="2000-01-01" max="2060-12-31"></label>
    <label>Au <input type="date" name="au" value="<?= e($au) ?>" min="2000-01-01" max="2060-12-31"></label>
    <label>Hémisphère
        <select name="hemisphere">
            <option value="NH">Nord</option>
            <option value="SH" <?= $hemisphere === 'SH' ? 'selected' : '' ?>>Sud</option>
        </select>
    </label>
    <button type="submit">Rechercher</button>
</form>

<p class="resume"><?= count($meubles) ?> meuble<?= count($meubles) > 1 ? 's' : '' ?> disponible<?= count($meubles) > 1 ? 's' : '' ?>
   du <?= dateFr($du) ?> au <?= dateFr($au) ?>, hémisphère <?= $hemisphere === 'NH' ? 'Nord' : 'Sud' ?>.</p>

<table class="liste">
    <tr><th>Nom</th><th>Catégorie</th><th>Prix d'achat</th></tr>
    <?php foreach ($meubles as $m): ?>
        <tr><td class="nom"><?= e($m['nom_meuble']) ?></td><td><?= e($m['nom_categorie']) ?></td>
            <td><?= $m['prix_achat'] === null ? 'Non vendu' : nombre($m['prix_achat']) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php require 'includes/footer.php'; ?>
