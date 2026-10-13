<?php
/**
 * Fiche d'un meuble : caractéristiques, variantes et couleurs, où l'obtenir,
 * saisons / événements (avec les prochaines dates) et villageois qui l'ont chez eux.
 * URL : meuble.php?id=42&hemisphere=NH
 */
require 'config.php';
require 'includes/fonctions.php';

$id = getEntier('id', 1, 1000000, 0);
$hemisphere = getChoix('hemisphere', array('NH', 'SH'), 'NH');
$aujourdhui = (new DateTime())->format('Y-m-d');

$meuble = requete($dbh,
    "SELECT m.*, cat.nom_categorie
     FROM meuble m
     JOIN categorie cat ON cat.id_categorie = m.id_categorie
     WHERE m.id_meuble = :id",
    array(':id' => $id))->fetch();

if (!$meuble) {
    http_response_code(404);
    $titre = 'Meuble introuvable';
    $pageActive = 'meubles';
    require 'includes/header.php';
    echo '<p class="vide">Ce meuble n\'existe pas. <a href="meubles.php?vue=catalogue">Retour au catalogue</a></p>';
    require 'includes/footer.php';
    exit;
}

// Variantes et leurs couleurs (une ligne par variante, couleurs regroupées)
$variantes = requete($dbh,
    "SELECT v.id_variante, v.nom_variation, v.nom_motif, v.nom_fichier_variante,
            GROUP_CONCAT(c.nom_couleur ORDER BY c.nom_couleur SEPARATOR ',') AS couleurs
     FROM variante v
     LEFT JOIN colorer co ON co.id_variante = v.id_variante
     LEFT JOIN couleur c  ON c.id_couleur = co.id_couleur
     WHERE v.id_meuble = :id
     GROUP BY v.id_variante, v.nom_variation, v.nom_motif, v.nom_fichier_variante
     ORDER BY v.id_variante",
    array(':id' => $id))->fetchAll();

// Où l'obtenir
$sources = requete($dbh,
    "SELECT s.nom_source
     FROM provenir pr
     JOIN source s ON s.id_source = pr.id_source
     WHERE pr.id_meuble = :id
     ORDER BY s.nom_source",
    array(':id' => $id))->fetchAll(PDO::FETCH_COLUMN);

// Saisons / événements et leur prochaine période dans l'hémisphère choisi
$evenements = requete($dbh,
    "SELECT e.id_evenement, e.nom_evenement, e.type_evenement,
            (SELECT MIN(p.date_debut) FROM periode p
             WHERE p.id_evenement = e.id_evenement AND p.hemisphere_periode = :hemisphere
               AND p.date_fin >= :aujourdhui) AS prochain_debut
     FROM proposer pr
     JOIN evenement e ON e.id_evenement = pr.id_evenement
     WHERE pr.id_meuble = :id
     ORDER BY e.nom_evenement",
    array(':id' => $id, ':hemisphere' => $hemisphere, ':aujourdhui' => $aujourdhui))->fetchAll();
foreach ($evenements as $k => $ev) {
    $evenements[$k]['periode'] = null;
    if ($ev['prochain_debut'] !== null) {
        $evenements[$k]['periode'] = requete($dbh,
            "SELECT date_debut, date_fin FROM periode
             WHERE id_evenement = :evt AND hemisphere_periode = :hemisphere AND date_debut = :debut",
            array(':evt' => $ev['id_evenement'], ':hemisphere' => $hemisphere, ':debut' => $ev['prochain_debut']))->fetch();
    }
}

// Villageois qui ont ce meuble chez eux
$villageois = requete($dbh,
    "SELECT vi.id_villageois, vi.nom_villageois, vi.nom_fichier_villageois, mb.quantite
     FROM meubler mb
     JOIN villageois vi ON vi.id_villageois = mb.id_villageois
     WHERE mb.id_meuble = :id
     ORDER BY vi.nom_villageois",
    array(':id' => $id))->fetchAll();

$titre = $meuble['nom_meuble'];
$pageActive = 'meubles';
require 'includes/header.php';
?>
<p class="fil"><a href="meubles.php?vue=catalogue">Meubles</a> / <?= e($meuble['nom_meuble']) ?></p>

<div class="fiche">
    <div class="fiche-image"><?= img(imageMeuble($variantes[0]['nom_fichier_variante']), $meuble['nom_meuble'], 128) ?></div>
    <div>
        <h1><?= e($meuble['nom_meuble']) ?></h1>
        <p class="intro"><?= e($meuble['nom_categorie']) ?>, type <?= e($meuble['etiquette']) ?></p>
        <dl class="caracteristiques">
            <div><dt>Prix d'achat</dt><dd><?= prix($meuble['prix_achat']) ?></dd></div>
            <div><dt>Prix de revente</dt><dd><?= prix($meuble['prix_vente_meuble']) ?></dd></div>
            <div><dt>Bricolage</dt><dd><?= $meuble['bricolable'] ? 'Oui' : 'Non' ?></dd></div>
            <div><dt>Personnalisable</dt><dd><?= $meuble['personnalisable'] ? 'Oui' : 'Non' ?></dd></div>
            <div><dt>Taille</dt><dd><?= e($meuble['taille']) ?> cases</dd></div>
            <div><dt>Extérieur</dt><dd><?= $meuble['exterieur'] ? 'Oui' : 'Non' ?></dd></div>
            <div><dt>Série</dt><dd><?= $meuble['serie'] !== null ? e($meuble['serie']) : 'Aucune' ?></dd></div>
            <div><dt>Catalogue</dt><dd><?= e($meuble['catalogue']) ?></dd></div>
            <div><dt>Points HHA</dt><dd><?= nombre($meuble['points_hha']) ?></dd></div>
            <div><dt>Ajouté en version</dt><dd><?= e($meuble['version_ajout_meuble']) ?></dd></div>
        </dl>
        <p><strong>Où l'obtenir :</strong> <?= e(implode(', ', $sources)) ?></p>
    </div>
</div>

<?php if ($evenements): ?>
<section aria-labelledby="t-saisons">
    <div class="titre-avec-choix">
        <h2 id="t-saisons">Saisons et événements</h2>
        <p class="bascule">
            <a href="<?= e(url('meuble.php', array('id' => $id, 'hemisphere' => 'NH'))) ?>" <?= $hemisphere === 'NH' ? 'aria-current="true"' : '' ?>>Nord</a>
            <a href="<?= e(url('meuble.php', array('id' => $id, 'hemisphere' => 'SH'))) ?>" <?= $hemisphere === 'SH' ? 'aria-current="true"' : '' ?>>Sud</a>
        </p>
    </div>
    <ul class="liste-simple">
        <?php foreach ($evenements as $ev): ?>
            <li>
                <a href="<?= e(url('meubles.php', array('evenement' => $ev['id_evenement'], 'hemisphere' => $hemisphere))) ?>"><?= e($ev['nom_evenement']) ?></a>
                <span class="discret"><?= e(typeEvenement($ev['type_evenement'])) ?></span>
                <?php if (!$ev['periode']): ?>
                    sans date fixe
                <?php elseif ($ev['periode']['date_debut'] <= $aujourdhui): ?>
                    <?= periodeDepuisAujourdhui($ev['periode']['date_debut'], $ev['periode']['date_fin'], $aujourdhui) ?>
                <?php else: ?>
                    prochaine fois <?= periodeTexte($ev['periode']['date_debut'], $ev['periode']['date_fin']) ?>
                <?php endif; ?>
                <?= $meuble['exclusif_evenement'] ? '<span class="badge depart">Exclusif</span>' : '' ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section aria-labelledby="t-variantes">
    <h2 id="t-variantes"><?= count($variantes) ?> variante<?= count($variantes) > 1 ? 's' : '' ?></h2>
    <ul class="grille-variantes">
        <?php foreach ($variantes as $v): ?>
            <li>
                <?= img(imageMeuble($v['nom_fichier_variante']), $meuble['nom_meuble'] . ' ' . $v['nom_variation'], 64) ?>
                <span><?= $v['nom_variation'] !== null ? e($v['nom_variation']) : 'Modèle unique' ?></span>
                <?php if ($v['nom_motif'] !== null): ?><span class="discret">Motif : <?= e($v['nom_motif']) ?></span><?php endif; ?>
                <span class="couleurs"><?php foreach (array_filter(explode(',', (string) $v['couleurs'])) as $c) { echo pastille($c, false); } ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($villageois): ?>
<section aria-labelledby="t-villageois">
    <h2 id="t-villageois">Chez <?= count($villageois) ?> villageois</h2>
    <ul class="grille-villageois petite">
        <?php foreach ($villageois as $vi): ?>
            <li><a href="<?= e(url('villageois_fiche.php', array('id' => $vi['id_villageois']))) ?>">
                <?= img(imageVillageois($vi['nom_fichier_villageois']), $vi['nom_villageois'], 56) ?>
                <span><?= e($vi['nom_villageois']) ?><?= $vi['quantite'] > 1 ? ' (×' . (int) $vi['quantite'] . ')' : '' ?></span>
            </a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
