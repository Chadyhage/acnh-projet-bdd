<?php
/**
 * Poissons et insectes disponibles à un instant T.
 * URL : creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18&zone=Mer&meteo=pluie&depart=1&q=bass&tri=prix_desc
 * Sans paramètre : le mois et l'heure actuels (heure de Paris), hémisphère Nord.
 */
require 'config.php';
require 'includes/fonctions.php';

$maintenant = new DateTime();

// --- 1. Paramètres reçus (validés : liste blanche ou bornes)
$zones = $dbh->query("SELECT DISTINCT zone_lieu FROM lieu ORDER BY zone_lieu")->fetchAll(PDO::FETCH_COLUMN);
$tris = array(
    'prix_desc' => 'c.prix_vente_creature DESC, c.nom_creature',
    'prix_asc'  => 'c.prix_vente_creature ASC, c.nom_creature',
    'nom'       => 'c.nom_creature',
    'numero'    => 'c.type_creature, c.numero_encyclopedie',
);
$f = array(
    'type'       => getChoix('type', array('poisson', 'insecte'), ''),
    'hemisphere' => getChoix('hemisphere', array('NH', 'SH'), 'NH'),
    'mois'       => getEntier('mois', 1, 12, (int) $maintenant->format('n')),
    'heure'      => getEntier('heure', 0, 23, (int) $maintenant->format('G')),
    'zone'       => getChoix('zone', $zones, ''),
    'meteo'      => getChoix('meteo', array('beau', 'pluie'), ''),
    'depart'     => getChoix('depart', array('1'), ''),
    'q'          => getTexte('q'),
    'tri'        => getChoix('tri', array_keys($tris), 'prix_desc'),
);
$moisPrecedent = $f['mois'] === 1 ? 12 : $f['mois'] - 1;
$moisSuivant   = $f['mois'] === 12 ? 1 : $f['mois'] + 1;

// --- 2. Conditions communes : la créature a un créneau ce mois-ci qui contient l'heure choisie
$conditions = array(
    "EXISTS (SELECT 1 FROM disponibilite d
                 WHERE d.id_creature = c.id_creature
                   AND d.hemisphere = :hemisphere
                   AND d.mois = :mois
                   AND " . sqlCreneauContientHeure('d') . ")",
);
$params = array(':hemisphere' => $f['hemisphere'], ':mois' => $f['mois'], ':heure' => $f['heure']);

if ($f['type'] !== '') {
    $conditions[] = 'c.type_creature = :type';
    $params[':type'] = $f['type'];
}
if ($f['meteo'] === 'beau') {
    $conditions[] = "c.meteo <> 'Rain only'";          // le cœlacanthe n'apparaît que sous la pluie
} elseif ($f['meteo'] === 'pluie') {
    $conditions[] = "c.meteo <> 'Any except rain'";    // ces insectes disparaissent quand il pleut
}
if ($f['depart'] === '1') {                          // absente le mois suivant = dernier mois pour l'attraper
    $conditions[] = "NOT EXISTS (SELECT 1 FROM disponibilite dn
                     WHERE dn.id_creature = c.id_creature
                       AND dn.hemisphere = :hemisphere
                       AND dn.mois = :mois_suivant)";
    $params[':mois_suivant'] = $moisSuivant;
}
if ($f['q'] !== '') {
    $conditions[] = 'c.nom_creature LIKE :q';
    $params[':q'] = motifLike($f['q']);
}

// --- 3. Résumé par zone (jointure + filtres + GROUP BY + ORDER BY)
$paramsResume = $params;
$sqlResume = "SELECT l.zone_lieu, c.type_creature,
                     COUNT(*) AS nb,
                     MAX(c.prix_vente_creature) AS meilleur_prix
              FROM creature c
              JOIN lieu l ON l.id_lieu = c.id_lieu
              WHERE " . implode("\n                AND ", $conditions) . "
              GROUP BY l.zone_lieu, c.type_creature
              ORDER BY nb DESC, meilleur_prix DESC, l.zone_lieu, c.type_creature";
$resume = requete($dbh, $sqlResume, $paramsResume)->fetchAll();

// --- 4. Liste détaillée (filtre de zone en plus) avec les badges « nouveau » et « dernier mois »
if ($f['zone'] !== '') {
    $conditions[] = 'l.zone_lieu = :zone';
    $params[':zone'] = $f['zone'];
}
$params[':mois_precedent'] = $moisPrecedent;
$params[':mois_suivant'] = $moisSuivant;
$sql = "SELECT c.id_creature, c.type_creature, c.numero_encyclopedie, c.nom_creature, c.prix_vente_creature,
               c.ombre, c.meteo, c.captures_requises, c.nom_fichier_creature, l.nom_lieu, l.zone_lieu,
               NOT EXISTS (SELECT 1 FROM disponibilite dp
                           WHERE dp.id_creature = c.id_creature AND dp.hemisphere = :hemisphere
                             AND dp.mois = :mois_precedent) AS nouveau,
               NOT EXISTS (SELECT 1 FROM disponibilite ds
                           WHERE ds.id_creature = c.id_creature AND ds.hemisphere = :hemisphere
                             AND ds.mois = :mois_suivant) AS dernier_mois
        FROM creature c
        JOIN lieu l ON l.id_lieu = c.id_lieu
        WHERE " . implode("\n          AND ", $conditions) . "
        ORDER BY " . $tris[$f['tri']];
$creatures = requete($dbh, $sql, $params)->fetchAll();

$titre = 'Poissons et insectes';
$pageActive = 'creatures';
require 'includes/header.php';
?>
<div class="entete-page">
    <h1>Poissons et insectes disponibles</h1>
    <p class="intro">
        <?= ucfirst(nomMois($f['mois'])) ?> à <?= heure($f['heure']) ?>, <?= hemisphereTexte($f['hemisphere']) ?> :
        <strong><?= count($creatures) ?></strong> <?= $f['type'] === '' ? 'créature' : $f['type'] ?><?= count($creatures) > 1 ? 's' : '' ?> à attraper.
        <a href="<?= e(url('creatures.php', array('hemisphere' => $f['hemisphere']))) ?>">Revenir à maintenant</a>
    </p>
</div>

<section class="ruban" aria-label="Choisir l'heure">
    <p class="ruban-titre">Heure</p>
    <div class="heures">
        <?php for ($h = 0; $h < 24; $h++): ?>
            <a class="heure <?= ($h >= 6 && $h < 18) ? 'jour' : 'nuit' ?>" style="--ciel:<?= ciel($h) ?>"
               href="<?= e(url('creatures.php', array_merge($f, array('heure' => $h)))) ?>"
               <?= $h === $f['heure'] ? 'aria-current="true"' : '' ?> title="<?= heure($h) ?>"><?= $h ?></a>
        <?php endfor; ?>
    </div>
    <p class="ruban-titre">Mois</p>
    <div class="mois">
        <?php for ($m = 1; $m <= 12; $m++): ?>
            <a href="<?= e(url('creatures.php', array_merge($f, array('mois' => $m)))) ?>"
               <?= $m === $f['mois'] ? 'aria-current="true"' : '' ?>><?= moisCourt($m) ?></a>
        <?php endfor; ?>
    </div>
</section>

<form class="filtres" method="get" action="creatures.php">
    <input type="hidden" name="mois" value="<?= $f['mois'] ?>">
    <input type="hidden" name="heure" value="<?= $f['heure'] ?>">
    <label>Type
        <select name="type">
            <option value="">Poissons et insectes</option>
            <option value="poisson" <?= $f['type'] === 'poisson' ? 'selected' : '' ?>>Poissons</option>
            <option value="insecte" <?= $f['type'] === 'insecte' ? 'selected' : '' ?>>Insectes</option>
        </select>
    </label>
    <label>Hémisphère
        <select name="hemisphere">
            <option value="NH">Nord</option>
            <option value="SH" <?= $f['hemisphere'] === 'SH' ? 'selected' : '' ?>>Sud</option>
        </select>
    </label>
    <label>Zone
        <select name="zone">
            <option value="">Toutes</option>
            <?php foreach ($zones as $z): ?>
                <option value="<?= e($z) ?>" <?= $f['zone'] === $z ? 'selected' : '' ?>><?= e($z) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Météo
        <select name="meteo">
            <option value="">Peu importe</option>
            <option value="beau" <?= $f['meteo'] === 'beau' ? 'selected' : '' ?>>Il ne pleut pas</option>
            <option value="pluie" <?= $f['meteo'] === 'pluie' ? 'selected' : '' ?>>Il pleut</option>
        </select>
    </label>
    <label>Nom contient
        <input type="search" name="q" value="<?= e($f['q']) ?>" maxlength="40" placeholder="ex. bass">
    </label>
    <label class="case"><input type="checkbox" name="depart" value="1" <?= $f['depart'] ? 'checked' : '' ?>> Dernier mois seulement</label>
    <label>Trier par
        <select name="tri">
            <option value="prix_desc">Prix décroissant</option>
            <option value="prix_asc" <?= $f['tri'] === 'prix_asc' ? 'selected' : '' ?>>Prix croissant</option>
            <option value="nom" <?= $f['tri'] === 'nom' ? 'selected' : '' ?>>Nom</option>
            <option value="numero" <?= $f['tri'] === 'numero' ? 'selected' : '' ?>>N° encyclopédie</option>
        </select>
    </label>
    <button type="submit">Rechercher</button>
</form>

<?php if ($resume): ?>
<section aria-labelledby="t-zones">
    <h2 id="t-zones">Où chercher</h2>
    <ul class="zones">
        <?php foreach ($resume as $r): ?>
            <li>
                <a href="<?= e(url('creatures.php', array_merge($f, array('zone' => $r['zone_lieu'])))) ?>"
                   <?= $f['zone'] === $r['zone_lieu'] ? 'aria-current="true"' : '' ?>>
                    <strong><?= e($r['zone_lieu']) ?></strong>
                    <span><?= $r['nb'] ?> <?= $r['type_creature'] === 'poisson' ? 'poisson' : 'insecte' ?><?= $r['nb'] > 1 ? 's' : '' ?></span>
                    <span class="discret">jusqu'à <?= nombre($r['meilleur_prix']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
        <?php if ($f['zone'] !== ''): ?>
            <li><a class="effacer" href="<?= e(url('creatures.php', array_merge($f, array('zone' => '')))) ?>">Toutes les zones</a></li>
        <?php endif; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (!$creatures): ?>
    <p class="vide">Aucune créature ne correspond à ces critères. Essayez une autre heure ou retirez un filtre.</p>
<?php else: ?>
<div class="tableau-defilant">
<table class="tableau">
    <thead>
        <tr>
            <th scope="col"><span class="visuellement-cache">Image</span></th>
            <th scope="col">Nom</th>
            <th scope="col">Lieu</th>
            <th scope="col">Ombre</th>
            <th scope="col">Météo</th>
            <th scope="col" class="nombre">Prix de vente</th>
            <th scope="col">Ce mois-ci</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($creatures as $c): ?>
        <tr>
            <td class="vignette"><?= img(imageCreature($c['nom_fichier_creature']), $c['nom_creature'], 40) ?></td>
            <td>
                <a href="<?= e(url('creature.php', array('id' => $c['id_creature'], 'hemisphere' => $f['hemisphere']))) ?>"><?= e($c['nom_creature']) ?></a>
                <span class="discret"><?= $c['type_creature'] === 'poisson' ? 'Poisson' : 'Insecte' ?> n°<?= (int) $c['numero_encyclopedie'] ?></span>
            </td>
            <td><?= e($c['nom_lieu']) ?> <span class="discret"><?= e($c['zone_lieu']) ?></span></td>
            <td class="court"><?= $c['ombre'] !== null ? e($c['ombre']) : '<span class="discret">—</span>' ?></td>
            <td class="court"><?= e(meteoFr($c['meteo'])) ?></td>
            <td class="nombre"><?= nombre($c['prix_vente_creature']) ?></td>
            <td>
                <?php if ($c['nouveau']): ?><span class="badge nouveau">Arrive ce mois</span><?php endif; ?>
                <?php if ($c['dernier_mois']): ?><span class="badge depart">Dernier mois</span><?php endif; ?>
                <?php if (!$c['nouveau'] && !$c['dernier_mois']): ?><span class="discret">—</span><?php endif; ?>
                <?php if ($c['captures_requises'] > 0): ?><span class="badge info" title="N'apparaît qu'après ce nombre total de captures">Après <?= (int) $c['captures_requises'] ?> captures</span><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
