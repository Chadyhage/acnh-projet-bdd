<?php
/**
 * Fiche d'une créature : informations et calendrier de disponibilité sur l'année.
 * URL : creature.php?id=12&hemisphere=NH
 */
require 'config.php';
require 'includes/fonctions.php';

$id = getEntier('id', 1, 1000000, 0);
$hemisphere = getChoix('hemisphere', array('NH', 'SH'), 'NH');

$creature = requete($dbh,
    "SELECT c.*, l.nom_lieu, l.zone_lieu
     FROM creature c
     JOIN lieu l ON l.id_lieu = c.id_lieu
     WHERE c.id_creature = :id",
    array(':id' => $id))->fetch();

if (!$creature) {
    http_response_code(404);
    $titre = 'Créature introuvable';
    $pageActive = 'creatures';
    require 'includes/header.php';
    echo '<p class="vide">Cette créature n\'existe pas. <a href="creatures.php">Retour à la liste</a></p>';
    require 'includes/footer.php';
    exit;
}

// Créneaux de chaque mois pour l'hémisphère choisi
$lignes = requete($dbh,
    "SELECT mois, heure_debut, heure_fin
     FROM disponibilite
     WHERE id_creature = :id AND hemisphere = :hemisphere
     ORDER BY mois, heure_debut",
    array(':id' => $id, ':hemisphere' => $hemisphere))->fetchAll();
$calendrier = array_fill(1, 12, array());
foreach ($lignes as $l) {
    $calendrier[(int) $l['mois']][] = creneau($l['heure_debut'], $l['heure_fin']);
}
$moisActuel = (int) (new DateTime())->format('n');

$titre = $creature['nom_creature'];
$pageActive = 'creatures';
require 'includes/header.php';
?>
<p class="fil"><a href="<?= e(url('creatures.php', array('hemisphere' => $hemisphere))) ?>">Poissons et insectes</a> / <?= e($creature['nom_creature']) ?></p>

<div class="fiche">
    <div class="fiche-image"><?= img(imageCreature($creature['nom_fichier_creature']), $creature['nom_creature'], 128) ?></div>
    <div>
        <h1><?= e($creature['nom_creature']) ?></h1>
        <p class="intro"><?= $creature['type_creature'] === 'poisson' ? 'Poisson' : 'Insecte' ?> n°<?= (int) $creature['numero_encyclopedie'] ?> de l'encyclopédie</p>
        <dl class="caracteristiques">
            <div><dt>Prix de vente</dt><dd><?= prix($creature['prix_vente_creature']) ?></dd></div>
            <div><dt>Lieu</dt><dd><?= e($creature['nom_lieu']) ?> (<?= e($creature['zone_lieu']) ?>)</dd></div>
            <div><dt>Météo</dt><dd><?= e(meteoFr($creature['meteo'])) ?></dd></div>
            <?php if ($creature['ombre'] !== null): ?>
                <div><dt>Ombre</dt><dd><?= e($creature['ombre']) ?></dd></div>
                <div><dt>Difficulté</dt><dd><?= e($creature['difficulte']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Apparition</dt><dd><?= $creature['captures_requises'] > 0
                ? 'après ' . (int) $creature['captures_requises'] . ' captures au total'
                : 'dès le début du jeu' ?></dd></div>
        </dl>
        <blockquote class="citation"><?= e($creature['phrase_capture']) ?></blockquote>
    </div>
</div>

<section aria-labelledby="t-calendrier">
    <div class="titre-avec-choix">
        <h2 id="t-calendrier">Disponibilité dans l'année</h2>
        <p class="bascule">
            <a href="<?= e(url('creature.php', array('id' => $id, 'hemisphere' => 'NH'))) ?>" <?= $hemisphere === 'NH' ? 'aria-current="true"' : '' ?>>Nord</a>
            <a href="<?= e(url('creature.php', array('id' => $id, 'hemisphere' => 'SH'))) ?>" <?= $hemisphere === 'SH' ? 'aria-current="true"' : '' ?>>Sud</a>
        </p>
    </div>
    <ol class="calendrier">
        <?php for ($m = 1; $m <= 12; $m++): ?>
            <li class="<?= $calendrier[$m] ? 'present' : 'absent' ?><?= $m === $moisActuel ? ' actuel' : '' ?>">
                <span class="mois-nom"><?= ucfirst(nomMois($m)) ?><?= $m === $moisActuel ? ' (ce mois-ci)' : '' ?></span>
                <span><?= $calendrier[$m] ? e(implode(' et ', $calendrier[$m])) : 'Absent' ?></span>
            </li>
        <?php endfor; ?>
    </ol>
</section>

<section aria-labelledby="t-description">
    <h2 id="t-description">Description du musée</h2>
    <p class="texte-long"><?= e($creature['description']) ?></p>
</section>

<?php require 'includes/footer.php'; ?>
