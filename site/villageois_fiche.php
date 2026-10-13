<?php
/**
 * Fiche d'un villageois : caractéristiques, styles et couleurs préférés,
 * meubles de sa maison (table associative MEUBLER).
 * URL : villageois_fiche.php?id=12
 */
require 'config.php';
require 'includes/fonctions.php';

$id = getEntier('id', 1, 1000000, 0);
$v = requete($dbh,
    "SELECT v.*, es.nom_espece, pe.nom_personnalite, lo.nom_loisir
     FROM villageois v
     JOIN espece es       ON es.id_espece = v.id_espece
     JOIN personnalite pe ON pe.id_personnalite = v.id_personnalite
     JOIN loisir lo       ON lo.id_loisir = v.id_loisir
     WHERE v.id_villageois = :id",
    array(':id' => $id))->fetch();

if (!$v) {
    http_response_code(404);
    $titre = 'Villageois introuvable';
    $pageActive = 'villageois';
    require 'includes/header.php';
    echo '<p class="vide">Ce villageois n\'existe pas. <a href="villageois.php">Retour à la liste</a></p>';
    require 'includes/footer.php';
    exit;
}

$styles = requete($dbh,
    "SELECT s.id_style, s.nom_style FROM aimer a JOIN style s ON s.id_style = a.id_style
     WHERE a.id_villageois = :id ORDER BY s.nom_style",
    array(':id' => $id))->fetchAll();
$couleurs = requete($dbh,
    "SELECT c.id_couleur, c.nom_couleur FROM preferer p JOIN couleur c ON c.id_couleur = p.id_couleur
     WHERE p.id_villageois = :id ORDER BY c.nom_couleur",
    array(':id' => $id))->fetchAll();
$meubles = requete($dbh,
    "SELECT m.id_meuble, m.nom_meuble, cat.nom_categorie, mb.quantite,
            (SELECT va.nom_fichier_variante FROM variante va
             WHERE va.id_meuble = m.id_meuble ORDER BY va.id_variante LIMIT 1) AS image
     FROM meubler mb
     JOIN meuble m      ON m.id_meuble = mb.id_meuble
     JOIN categorie cat ON cat.id_categorie = m.id_categorie
     WHERE mb.id_villageois = :id
     ORDER BY cat.nom_categorie, m.nom_meuble",
    array(':id' => $id))->fetchAll();

$titre = $v['nom_villageois'];
$pageActive = 'villageois';
require 'includes/header.php';
?>
<p class="fil"><a href="villageois.php">Villageois</a> / <?= e($v['nom_villageois']) ?></p>

<div class="fiche">
    <div class="fiche-image"><?= img(imageVillageois($v['nom_fichier_villageois']), $v['nom_villageois'], 128) ?></div>
    <div>
        <h1><?= e($v['nom_villageois']) ?></h1>
        <p class="intro">
            <a href="<?= e(url('villageois.php', array('espece' => $v['id_espece']))) ?>"><?= e($v['nom_espece']) ?></a>,
            <a href="<?= e(url('villageois.php', array('personnalite' => $v['id_personnalite']))) ?>"><?= e($v['nom_personnalite']) ?></a>
        </p>
        <dl class="caracteristiques">
            <div><dt>Genre</dt><dd><?= e(genreFr($v['genre'])) ?> (sous-type <?= e($v['sous_type']) ?>)</dd></div>
            <div><dt>Anniversaire</dt><dd><?= (int) $v['jour_anniversaire'] === 1 ? '1er' : (int) $v['jour_anniversaire'] ?> <?= nomMois($v['mois_anniversaire']) ?></dd></div>
            <div><dt>Loisir</dt><dd><?= e($v['nom_loisir']) ?></dd></div>
            <div><dt>Tic de langage</dt><dd>« <?= e($v['phrase_fetiche']) ?> »</dd></div>
            <div><dt>Chanson préférée</dt><dd><?= e($v['chanson_preferee']) ?></dd></div>
            <div><dt>Styles aimés</dt><dd><?php foreach ($styles as $i => $s): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('villageois.php', array('style' => $s['id_style']))) ?>"><?= e($s['nom_style']) ?></a><?php endforeach; ?></dd></div>
            <div><dt>Couleurs préférées</dt><dd><?php foreach ($couleurs as $c): ?><a href="<?= e(url('villageois.php', array('couleur' => $c['id_couleur']))) ?>"><?= pastille($c['nom_couleur']) ?></a> <?php endforeach; ?></dd></div>
            <div><dt>Ajouté en version</dt><dd><?= e($v['version_ajout_villageois']) ?></dd></div>
        </dl>
        <blockquote class="citation"><?= e($v['citation_preferee']) ?></blockquote>
    </div>
</div>

<section aria-labelledby="t-maison">
    <h2 id="t-maison">Dans sa maison</h2>
    <?php if (!$meubles): ?>
        <p class="vide">Aucun meuble du périmètre importé (les tapis, vêtements et autres objets hors périmètre ne font pas partie de la base).</p>
    <?php else: ?>
        <p class="intro"><?= count($meubles) ?> meubles différents (hors tapis, vêtements et autres objets hors périmètre).</p>
        <ul class="grille-meubles">
            <?php foreach ($meubles as $m): ?>
                <li><a href="<?= e(url('meuble.php', array('id' => $m['id_meuble']))) ?>">
                    <?= img(imageMeuble($m['image']), $m['nom_meuble'], 72) ?>
                    <span class="nom"><?= e($m['nom_meuble']) ?><?= $m['quantite'] > 1 ? ' (×' . (int) $m['quantite'] . ')' : '' ?></span>
                    <span class="discret"><?= e($m['nom_categorie']) ?></span>
                </a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
