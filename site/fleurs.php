<?php
/**
 * Outil de croisement de fleurs.
 * URL : fleurs.php?espece=6&couleur1=3&couleur2=7&mode=equiprobable
 *
 * Le joueur choisit une espèce et deux couleurs VISIBLES. Les génotypes des parents
 * sont inconnus : chaque couleur correspond à plusieurs génotypes possibles.
 *  - mode « equiprobable » : tous les génotypes qui donnent la couleur choisie ont la même chance
 *    (hypothèse affichée : les résultats sont des estimations, pas des chances garanties) ;
 *  - mode « graines » : les deux parents viennent de sacs de graines, leur génotype est connu
 *    (résultats exacts).
 * Probabilité d'une couleur d'enfant = moyenne, sur tous les couples (génotype 1, génotype 2)
 * possibles, de la probabilité stockée dans la table CROISER.
 */
require 'config.php';
require 'includes/fonctions.php';

$especes = requete($dbh,
    "SELECT id_espece_fleur, nom_espece_fleur FROM espece_fleur ORDER BY nom_espece_fleur")->fetchAll();
$f = array(
    'espece'   => getEntier('espece', 1, 100, ''),
    'couleur1' => getEntier('couleur1', 1, 1000, ''),
    'couleur2' => getEntier('couleur2', 1, 1000, ''),
    'mode'     => getChoix('mode', array('equiprobable', 'graines'), 'equiprobable'),
);
if (!in_array($f['espece'], array_map('intval', array_column($especes, 'id_espece_fleur')), true)) {
    $f['espece'] = (int) $especes[array_search('Rose', array_column($especes, 'nom_espece_fleur'))]['id_espece_fleur'];
}

// Couleurs de l'espèce qui ont des génotypes (la rose dorée n'en a pas : elle ne vient pas d'un croisement)
$couleurs = requete($dbh,
    "SELECT f.id_fleur, c.id_couleur, c.nom_couleur, f.origine_fleur,
            COUNT(g.id_genotype) AS nb_genotypes,
            SUM(g.est_graine) AS en_graine
     FROM fleur f
     JOIN couleur c   ON c.id_couleur = f.id_couleur
     JOIN genotype g  ON g.id_fleur = f.id_fleur
     WHERE f.id_espece_fleur = :espece
     GROUP BY f.id_fleur, c.id_couleur, c.nom_couleur, f.origine_fleur
     ORDER BY f.origine_fleur DESC, c.nom_couleur",
    array(':espece' => $f['espece']))->fetchAll();
$parCouleur = array();
foreach ($couleurs as $c) {
    $parCouleur[(int) $c['id_couleur']] = $c;
}
foreach (array('couleur1', 'couleur2') as $champ) {
    if (!isset($parCouleur[$f[$champ]])) {
        $f[$champ] = '';                 // couleur absente de cette espèce : il faut la rechoisir
    }
}
$nomEspece = '';
foreach ($especes as $e) {
    if ((int) $e['id_espece_fleur'] === $f['espece']) {
        $nomEspece = $e['nom_espece_fleur'];
    }
}

$resultats = array();
$erreur = '';
if ($f['couleur1'] !== '' && $f['couleur2'] !== '') {
    $p1 = $parCouleur[$f['couleur1']];
    $p2 = $parCouleur[$f['couleur2']];
    $filtreGraine = $f['mode'] === 'graines' ? ' AND g.est_graine = 1' : '';

    if ($f['mode'] === 'graines' && (!$p1['en_graine'] || !$p2['en_graine'])) {
        $sansGraine = $p1['en_graine'] ? $p2 : $p1;
        $erreur = 'La couleur ' . $sansGraine['nom_couleur'] . ' (' . couleurFr($sansGraine['nom_couleur']) . ') ne se vend pas'
            . ' en graines : choisissez « Génotypes inconnus » ou une couleur vendue en graines.';
    } else {
        // Étape 1 : nombre de génotypes possibles pour chaque parent
        $sqlNb = "SELECT COUNT(*) FROM genotype g WHERE g.id_fleur = :fleur" . $filtreGraine;
        $nb1 = (int) requete($dbh, $sqlNb, array(':fleur' => $p1['id_fleur']))->fetchColumn();
        $nb2 = (int) requete($dbh, $sqlNb, array(':fleur' => $p2['id_fleur']))->fetchColumn();

        // Étape 2 : probabilités par couleur d'enfant, moyenne sur les nb1 x nb2 couples de génotypes
        $resultats = requete($dbh,
            "SELECT co.nom_couleur AS couleur_enfant,
                    fe.origine_fleur,
                    SUM(cr.probabilite) / (:nb1 * :nb2) AS probabilite
             FROM croiser cr
             JOIN genotype p1 ON p1.id_genotype = cr.id_genotype_parent_1
             JOIN genotype p2 ON p2.id_genotype = cr.id_genotype_parent_2
             JOIN fleur fe    ON fe.id_fleur = cr.id_fleur_enfant
             JOIN couleur co  ON co.id_couleur = fe.id_couleur
             WHERE p1.id_fleur = :fleur1
               AND p2.id_fleur = :fleur2"
               . ($f['mode'] === 'graines' ? "\n               AND p1.est_graine = 1\n               AND p2.est_graine = 1" : '') . "
             GROUP BY co.nom_couleur, fe.origine_fleur
             ORDER BY probabilite DESC, co.nom_couleur",
            array(':nb1' => $nb1, ':nb2' => $nb2, ':fleur1' => $p1['id_fleur'], ':fleur2' => $p2['id_fleur']))->fetchAll();
    }
}

// Table des génotypes de l'espèce (pour comprendre le calcul)
$genotypes = requete($dbh,
    "SELECT g.code_genotype, g.est_graine, c.nom_couleur
     FROM genotype g
     JOIN fleur f   ON f.id_fleur = g.id_fleur
     JOIN couleur c ON c.id_couleur = f.id_couleur
     WHERE f.id_espece_fleur = :espece
     ORDER BY g.code_genotype",
    array(':espece' => $f['espece']))->fetchAll();
$codeGraine = array();                   // couleur => génotype unique des fleurs vendues en graines
foreach ($genotypes as $g) {
    if ($g['est_graine']) {
        $codeGraine[$g['nom_couleur']] = $g['code_genotype'];
    }
}

/** « 1 génotype », « 9 génotypes ». */
function nbGenotypes($n)
{
    return $n . ' génotype' . ($n > 1 ? 's' : '');
}

/** « 2001 » -> « RR yy ww Ss » (R rouge, Y jaune, W blanc, S nuance pour les roses). */
function notationGenes($code)
{
    $lettres = array('R', 'Y', 'W', 'S');
    $paires = array();
    for ($i = 0; $i < strlen($code); $i++) {
        $l = $lettres[$i];
        $paires[] = array(strtolower($l) . strtolower($l), $l . strtolower($l), $l . $l)[(int) $code[$i]];
    }
    return implode(' ', $paires);
}

$titre = 'Croisement de fleurs';
$pageActive = 'fleurs';
require 'includes/header.php';
?>
<div class="entete-page">
    <h1>Croisement de fleurs</h1>
    <p class="intro">Choisissez une espèce et la couleur des deux fleurs que vous plantez côte à côte : l'outil donne les couleurs possibles de la fleur qui naîtra, avec leur probabilité.</p>
</div>

<form class="filtres" method="get" action="fleurs.php">
    <label>Espèce
        <select name="espece" onchange="this.form.couleur1.value='';this.form.couleur2.value='';this.form.submit()">
            <?php foreach ($especes as $e): ?>
                <option value="<?= (int) $e['id_espece_fleur'] ?>" <?= $f['espece'] === (int) $e['id_espece_fleur'] ? 'selected' : '' ?>>
                    <?= e($e['nom_espece_fleur']) ?> (<?= e(especeFleurFr($e['nom_espece_fleur'])) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php foreach (array('couleur1' => 'Fleur 1', 'couleur2' => 'Fleur 2') as $champ => $libelle): ?>
        <label><?= $libelle ?>
            <select name="<?= $champ ?>">
                <option value="">— Couleur —</option>
                <?php foreach ($couleurs as $c): ?>
                    <option value="<?= (int) $c['id_couleur'] ?>" <?= $f[$champ] === (int) $c['id_couleur'] ? 'selected' : '' ?>>
                        <?= e($c['nom_couleur']) ?> (<?= e(couleurFr($c['nom_couleur'])) ?>)<?= $c['en_graine'] ? ', graine' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endforeach; ?>
    <fieldset class="choix-mode">
        <legend>Origine des deux fleurs</legend>
        <label class="case"><input type="radio" name="mode" value="equiprobable" <?= $f['mode'] === 'equiprobable' ? 'checked' : '' ?>> Génotypes inconnus</label>
        <label class="case"><input type="radio" name="mode" value="graines" <?= $f['mode'] === 'graines' ? 'checked' : '' ?>> Fleurs de graines (magasin)</label>
    </fieldset>
    <button type="submit">Calculer</button>
</form>

<?php if ($erreur): ?>
    <p class="note erreur"><?= e($erreur) ?></p>
<?php elseif ($resultats): ?>
    <?php $totalHybrides = 0; foreach ($resultats as $r) { if ($r['origine_fleur'] === 'Hybride') { $totalHybrides += $r['probabilite']; } } ?>
    <section class="resultat-croisement" aria-labelledby="t-resultat">
        <h2 id="t-resultat">
            <?= pastille($parCouleur[$f['couleur1']]['nom_couleur'], false) ?><?= e($parCouleur[$f['couleur1']]['nom_couleur']) ?>
            ×
            <?= pastille($parCouleur[$f['couleur2']]['nom_couleur'], false) ?><?= e($parCouleur[$f['couleur2']]['nom_couleur']) ?>
            <span class="discret"><?= e($nomEspece) ?></span>
        </h2>
        <div class="tableau-defilant">
        <table class="tableau probabilites">
            <thead><tr><th scope="col">Couleur de l'enfant</th><th scope="col">Origine</th><th scope="col" class="nombre">Probabilité</th><th scope="col"><span class="visuellement-cache">Barre</span></th></tr></thead>
            <tbody>
            <?php foreach ($resultats as $r): $pct = 100 * $r['probabilite']; ?>
                <tr>
                    <td><?= pastille($r['couleur_enfant']) ?> <span class="discret"><?= e(couleurFr($r['couleur_enfant'])) ?></span></td>
                    <td><?= $r['origine_fleur'] === 'Hybride' ? '<span class="badge info">Hybride</span>' : '<span class="discret">Graine</span>' ?></td>
                    <td class="nombre"><?= number_format($pct, 2, ',', ' ') ?> %</td>
                    <td class="barre-cellule"><span class="barre" style="width:<?= round($pct, 2) ?>%;background:<?= e(couleurHex($r['couleur_enfant'])) ?>"></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="explication">
            <?php if ($f['mode'] === 'graines'): ?>
                <p><strong>Résultat exact.</strong> Une fleur achetée en sac de graines a toujours le même génotype :
                <?= e(notationGenes($codeGraine[$p1['nom_couleur']])) ?> pour la fleur 1 et
                <?= e(notationGenes($codeGraine[$p2['nom_couleur']])) ?> pour la fleur 2.</p>
            <?php elseif ($p1['id_fleur'] === $p2['id_fleur']): ?>
                <p><strong>Estimation.</strong> Une fleur <?= e(couleurFr($p1['nom_couleur'], true)) ?> peut avoir
                <?= nbGenotypes($nb1) ?> : on suppose qu'ils ont tous la même chance et on fait la moyenne sur les
                <?= $nb1 * $nb2 ?> couples possibles. Ce ne sont pas les chances garanties de deux fleurs précises.</p>
            <?php else: ?>
                <p><strong>Estimation.</strong> Une fleur <?= e(couleurFr($p1['nom_couleur'], true)) ?> peut avoir
                <?= nbGenotypes($nb1) ?> et une fleur <?= e(couleurFr($p2['nom_couleur'], true)) ?> <?= nbGenotypes($nb2) ?> :
                on suppose qu'ils ont tous la même chance et on fait la moyenne sur les <?= $nb1 * $nb2 ?> couples possibles.
                Ce ne sont pas les chances garanties de deux fleurs précises.</p>
            <?php endif; ?>
            <p>Chance d'obtenir une couleur hybride (qui ne se vend pas en graines) : <strong><?= number_format(100 * $totalHybrides, 2, ',', ' ') ?> %</strong>.</p>
            <?php if ($nomEspece === 'Rose'): ?>
                <p class="discret">La rose dorée ne s'obtient pas par croisement : on arrose une rose noire avec l'arrosoir en or.</p>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <p class="vide">Choisissez les couleurs des deux fleurs puis « Calculer ».</p>
<?php endif; ?>

<details class="genotypes">
    <summary>Table des <?= count($genotypes) ?> génotypes : <?= e($nomEspece) ?></summary>
    <p>Chaque fleur porte <?= $nomEspece === 'Rose' ? '4 gènes (R rouge, Y jaune, W blanc, S nuance)' : '3 gènes (R rouge, Y jaune, W blanc)' ?>.
    Chaque gène vaut 0, 1 ou 2 ; l'enfant reçoit au hasard la moitié de chaque gène de chaque parent (loi de Mendel).</p>
    <div class="tableau-defilant">
    <table class="tableau compact">
        <thead><tr><th scope="col">Code</th><th scope="col">Gènes</th><th scope="col">Couleur visible</th><th scope="col">Vendue en graines</th></tr></thead>
        <tbody>
        <?php foreach ($genotypes as $g): ?>
            <tr><td><code><?= e($g['code_genotype']) ?></code></td><td><?= e(notationGenes($g['code_genotype'])) ?></td>
                <td><?= pastille($g['nom_couleur']) ?></td><td><?= $g['est_graine'] ? 'Oui' : '' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</details>

<?php require 'includes/footer.php'; ?>
