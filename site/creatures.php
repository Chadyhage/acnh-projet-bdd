<?php
/**
 * Poissons et insectes disponibles à un instant T.
 * Exemple d'URL : creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18
 * Sans paramètre : le mois et l'heure actuels (heure de Paris), hémisphère Nord.
 */
require 'config.php';
require 'includes/fonctions.php';

// 1. Lire les paramètres de l'URL, en les vérifiant
$type       = getChoix('type', array('poisson', 'insecte'), '');      // vide = poissons et insectes
$hemisphere = getChoix('hemisphere', array('NH', 'SH'), 'NH');
$mois       = getEntier('mois', 1, 12, (int) date('n'));
$heure      = getEntier('heure', 0, 23, (int) date('G'));

// 2. Conditions communes aux deux requêtes : la créature a un créneau ce mois-ci,
//    dans cet hémisphère, qui contient l'heure choisie.
//    Un créneau qui passe minuit (ex. 19 h -> 4 h) a une heure de début plus grande que l'heure de fin :
//    l'heure doit alors être après le début OU avant la fin.
$where = "WHERE d.hemisphere = :hemisphere
  AND d.mois = :mois
  AND (   (d.heure_debut < d.heure_fin AND :heure >= d.heure_debut AND :heure < d.heure_fin)
       OR (d.heure_debut > d.heure_fin AND (:heure >= d.heure_debut OR :heure < d.heure_fin)))";
$params = array(':hemisphere' => $hemisphere, ':mois' => $mois, ':heure' => $heure);
if ($type !== '') {                      // le filtre « Type » est rempli : une condition de plus
    $where .= "\n  AND c.type_creature = :type";
    $params[':type'] = $type;
}

// 3. Requête 1 : la liste des créatures disponibles, de la plus chère à la moins chère
$creatures = requete($dbh,
"SELECT c.nom_creature, c.type_creature, l.nom_lieu, c.prix_vente_creature
FROM creature c
JOIN lieu l          ON l.id_lieu = c.id_lieu
JOIN disponibilite d ON d.id_creature = c.id_creature
$where
ORDER BY c.prix_vente_creature DESC, c.nom_creature", $params)->fetchAll();

// 4. Requête 2 (requête complexe du dossier) : où chercher ? Nombre de créatures et meilleur prix par zone
$zones = requete($dbh,
"SELECT l.zone_lieu, COUNT(*) AS nb, MAX(c.prix_vente_creature) AS meilleur_prix
FROM creature c
JOIN lieu l          ON l.id_lieu = c.id_lieu
JOIN disponibilite d ON d.id_creature = c.id_creature
$where
GROUP BY l.zone_lieu
ORDER BY nb DESC, meilleur_prix DESC, l.zone_lieu", $params)->fetchAll();

// 5. Affichage
$titre = 'Poissons et insectes';
$pageActive = 'creatures';
require 'includes/header.php';
?>
<h1>Poissons et insectes disponibles</h1>

<form method="get" action="creatures.php">
    <label>Type
        <select name="type">
            <option value="">Poissons et insectes</option>
            <option value="poisson" <?= $type === 'poisson' ? 'selected' : '' ?>>Poissons</option>
            <option value="insecte" <?= $type === 'insecte' ? 'selected' : '' ?>>Insectes</option>
        </select>
    </label>
    <label>Hémisphère
        <select name="hemisphere">
            <option value="NH">Nord</option>
            <option value="SH" <?= $hemisphere === 'SH' ? 'selected' : '' ?>>Sud</option>
        </select>
    </label>
    <label>Mois
        <select name="mois">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $mois ? 'selected' : '' ?>><?= nomMois($m) ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <label>Heure
        <select name="heure">
            <?php for ($h = 0; $h <= 23; $h++): ?>
                <option value="<?= $h ?>" <?= $h === $heure ? 'selected' : '' ?>><?= $h ?> h</option>
            <?php endfor; ?>
        </select>
    </label>
    <button type="submit">Rechercher</button>
</form>

<p class="resume"><?= count($creatures) ?> créature<?= count($creatures) > 1 ? 's' : '' ?> disponible<?= count($creatures) > 1 ? 's' : '' ?> en <?= nomMois($mois) ?> à <?= $heure ?> h,
   hémisphère <?= $hemisphere === 'NH' ? 'Nord' : 'Sud' ?>.</p>

<h2>Où chercher</h2>
<table>
    <tr><th>Zone</th><th>Nombre de créatures</th><th>Meilleur prix</th></tr>
    <?php foreach ($zones as $z): ?>
        <tr><td><?= e($z['zone_lieu']) ?></td><td><?= $z['nb'] ?></td><td><?= nombre($z['meilleur_prix']) ?></td></tr>
    <?php endforeach; ?>
</table>

<h2>Liste des créatures</h2>
<table class="liste">
    <tr><th>Nom</th><th>Type</th><th>Lieu</th><th>Prix de vente</th></tr>
    <?php foreach ($creatures as $c): ?>
        <tr><td class="nom"><?= e($c['nom_creature']) ?></td><td><?= e($c['type_creature']) ?></td>
            <td><?= e($c['nom_lieu']) ?></td><td><?= nombre($c['prix_vente_creature']) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php require 'includes/footer.php'; ?>
