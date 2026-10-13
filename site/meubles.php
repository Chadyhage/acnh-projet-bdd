<?php
/**
 * Meubles (onglets Housewares, Miscellaneous, Wall-mounted, Ceiling Decor).
 *
 * Vue « saison » (par défaut) : meubles de saison disponibles
 *   - pour une période : meubles.php?du=2026-12-01&au=2026-12-31&hemisphere=NH
 *   - ou pour une saison / un événement : meubles.php?evenement=42
 * Vue « catalogue » : tous les meubles, filtres + pagination
 *   meubles.php?vue=catalogue&categorie=1&couleur=4&bricolable=1&q=chair&tri=prix_asc&page=2
 */
require 'config.php';
require 'includes/fonctions.php';

$aujourdhui = (new DateTime())->format('Y-m-d');
$vue = getChoix('vue', array('saison', 'catalogue'), 'saison');

// Listes pour les menus déroulants (valeurs autorisées)
$categories = requete($dbh, "SELECT id_categorie, nom_categorie FROM categorie ORDER BY nom_categorie")->fetchAll();
$idsCategories = array_map('intval', array_column($categories, 'id_categorie'));

$f = array(
    'vue'        => $vue,
    'hemisphere' => getChoix('hemisphere', array('NH', 'SH'), 'NH'),
    'categorie'  => getEntier('categorie', 1, 1000, ''),
    'q'          => getTexte('q'),
);
if (!in_array($f['categorie'], $idsCategories, true)) {
    $f['categorie'] = '';                          // valeur inconnue : filtre ignoré
}

/** Image du meuble = sa première variante (sous-requête corrélée). */
$sqlImage = "(SELECT v.nom_fichier_variante FROM variante v
              WHERE v.id_meuble = m.id_meuble ORDER BY v.id_variante LIMIT 1)";

if ($vue === 'saison') {
    // ------------------------------------------------------------------ VUE SAISON
    $evenements = requete($dbh,
        "SELECT e.id_evenement, e.nom_evenement, e.type_evenement, COUNT(*) AS nb_meubles
         FROM evenement e
         JOIN proposer pr ON pr.id_evenement = e.id_evenement
         GROUP BY e.id_evenement, e.nom_evenement, e.type_evenement
         ORDER BY e.type_evenement, e.nom_evenement")->fetchAll();
    $types = array_values(array_unique(array_column($evenements, 'type_evenement')));

    $f['evenement'] = getEntier('evenement', 1, 100000, '');
    if (!in_array($f['evenement'], array_map('intval', array_column($evenements, 'id_evenement')), true)) {
        $f['evenement'] = '';
    }
    $f['type_evt'] = getChoix('type_evt', $types, '');
    $f['du'] = getDateParam('du', $aujourdhui);
    $f['au'] = getDateParam('au', $f['du']);
    if ($f['au'] < $f['du']) {                         // dates inversées : on les remet dans l'ordre
        list($f['du'], $f['au']) = array($f['au'], $f['du']);
    }
    $limite = (new DateTime($f['du']))->modify('+365 days')->format('Y-m-d');
    $periodeReduite = $f['au'] > $limite;              // une année au plus, pour garder une page lisible
    if ($periodeReduite) {
        $f['au'] = $limite;
    }

    // Filtres sur les meubles, communs aux deux recherches
    $conditionsMeuble = array();
    $params = array();
    if ($f['categorie']) {
        $conditionsMeuble[] = 'm.id_categorie = :categorie';
        $params[':categorie'] = $f['categorie'];
    }
    if ($f['q'] !== '') {
        $conditionsMeuble[] = 'm.nom_meuble LIKE :q';
        $params[':q'] = motifLike($f['q']);
    }

    if ($f['evenement']) {
        // --- Recherche par saison / événement
        $evt = requete($dbh,
            "SELECT nom_evenement, nom_affiche, type_evenement FROM evenement WHERE id_evenement = :id",
            array(':id' => $f['evenement']))->fetch();
        $prochaines = requete($dbh,
            "SELECT date_debut, date_fin
             FROM periode
             WHERE id_evenement = :id AND hemisphere_periode = :hemisphere AND date_fin >= :aujourdhui
             ORDER BY date_debut
             LIMIT 2",
            array(':id' => $f['evenement'], ':hemisphere' => $f['hemisphere'], ':aujourdhui' => $aujourdhui))->fetchAll();

        $params[':evenement'] = $f['evenement'];
        $meubles = requete($dbh,
            "SELECT m.id_meuble, m.nom_meuble, m.prix_achat, m.bricolable, cat.nom_categorie, $sqlImage AS image
             FROM meuble m
             JOIN categorie cat ON cat.id_categorie = m.id_categorie
             JOIN proposer pr   ON pr.id_meuble = m.id_meuble
             WHERE pr.id_evenement = :evenement"
             . ($conditionsMeuble ? "\n               AND " . implode("\n               AND ", $conditionsMeuble) : '') . "
             ORDER BY m.nom_meuble",
            $params)->fetchAll();
    } else {
        // --- Recherche par période : événements dont une période chevauche [du ; au]
        $conditions = array_merge(array(
            'p.hemisphere_periode = :hemisphere',
            'p.date_debut <= :au',
            'p.date_fin >= :du',
        ), $conditionsMeuble);
        $params[':hemisphere'] = $f['hemisphere'];
        $params[':du'] = $f['du'];
        $params[':au'] = $f['au'];
        if ($f['type_evt'] !== '') {
            $conditions[] = 'e.type_evenement = :type_evt';
            $params[':type_evt'] = $f['type_evt'];
        }
        $lignes = requete($dbh,
            "SELECT e.id_evenement, e.nom_evenement, e.type_evenement, p.date_debut, p.date_fin,
                    m.id_meuble, m.nom_meuble, m.prix_achat, m.bricolable, cat.nom_categorie, $sqlImage AS image
             FROM periode p
             JOIN evenement e   ON e.id_evenement = p.id_evenement
             JOIN proposer pr   ON pr.id_evenement = e.id_evenement
             JOIN meuble m      ON m.id_meuble = pr.id_meuble
             JOIN categorie cat ON cat.id_categorie = m.id_categorie
             WHERE " . implode("\n               AND ", $conditions) . "
             ORDER BY p.date_debut, e.nom_evenement, m.nom_meuble",
            $params)->fetchAll();

        // Regroupement par événement (et par période) pour l'affichage
        $groupes = array();
        foreach ($lignes as $l) {
            $cle = $l['id_evenement'] . '|' . $l['date_debut'];
            if (!isset($groupes[$cle])) {
                $groupes[$cle] = array('evenement' => $l, 'meubles' => array());
            }
            $groupes[$cle]['meubles'][] = $l;
        }
        $nbMeubles = count(array_unique(array_column($lignes, 'id_meuble')));
    }
} else {
    // ------------------------------------------------------------------ VUE CATALOGUE
    $couleurs = requete($dbh,
        "SELECT DISTINCT c.id_couleur, c.nom_couleur
         FROM couleur c JOIN colorer co ON co.id_couleur = c.id_couleur
         ORDER BY c.nom_couleur")->fetchAll();
    $tris = array(
        'nom'       => 'm.nom_meuble',
        'prix_asc'  => 'm.prix_achat IS NULL, m.prix_achat, m.nom_meuble',
        'prix_desc' => 'm.prix_achat DESC, m.nom_meuble',
        'variantes' => 'nb_variantes DESC, m.nom_meuble',
    );
    $f['couleur']    = getEntier('couleur', 1, 1000, '');
    if (!in_array($f['couleur'], array_map('intval', array_column($couleurs, 'id_couleur')), true)) {
        $f['couleur'] = '';
    }
    $f['bricolable'] = getChoix('bricolable', array('1'), '');
    $f['saisonnier'] = getChoix('saisonnier', array('1'), '');
    $f['tri']        = getChoix('tri', array_keys($tris), 'nom');

    $conditions = array('1 = 1');
    $params = array();
    if ($f['categorie']) {
        $conditions[] = 'm.id_categorie = :categorie';
        $params[':categorie'] = $f['categorie'];
    }
    if ($f['q'] !== '') {
        $conditions[] = 'm.nom_meuble LIKE :q';
        $params[':q'] = motifLike($f['q']);
    }
    if ($f['bricolable'] === '1') {
        $conditions[] = 'm.bricolable = 1';
    }
    if ($f['saisonnier'] === '1') {
        $conditions[] = 'EXISTS (SELECT 1 FROM proposer pr WHERE pr.id_meuble = m.id_meuble)';
    }
    if ($f['couleur']) {
        $conditions[] = 'EXISTS (SELECT 1 FROM variante vc JOIN colorer co ON co.id_variante = vc.id_variante
                                 WHERE vc.id_meuble = m.id_meuble AND co.id_couleur = :couleur)';
        $params[':couleur'] = $f['couleur'];
        // Vignette : la première variante de la couleur demandée
        $sqlImage = "(SELECT v.nom_fichier_variante FROM variante v JOIN colorer co2 ON co2.id_variante = v.id_variante
                      WHERE v.id_meuble = m.id_meuble AND co2.id_couleur = :couleur ORDER BY v.id_variante LIMIT 1)";
    }
    $where = implode("\n               AND ", $conditions);

    $total = (int) requete($dbh, "SELECT COUNT(*) FROM meuble m WHERE $where", $params)->fetchColumn();
    $parPage = 24;
    $nbPages = max(1, (int) ceil($total / $parPage));
    $f['page'] = min(getEntier('page', 1, 100000, 1), $nbPages);
    $decalage = ($f['page'] - 1) * $parPage;     // entier calculé par PHP : peut être écrit dans la requête

    $meubles = requete($dbh,
        "SELECT m.id_meuble, m.nom_meuble, m.prix_achat, m.bricolable, cat.nom_categorie,
                COUNT(v.id_variante) AS nb_variantes, $sqlImage AS image
         FROM meuble m
         JOIN categorie cat ON cat.id_categorie = m.id_categorie
         JOIN variante v    ON v.id_meuble = m.id_meuble
         WHERE $where
         GROUP BY m.id_meuble, m.nom_meuble, m.prix_achat, m.bricolable, cat.nom_categorie
         ORDER BY " . $tris[$f['tri']] . "
         LIMIT $parPage OFFSET $decalage",
        $params)->fetchAll();
}

/** Affiche une grille de vignettes de meubles. */
function grilleMeubles(array $meubles)
{
    echo '<ul class="grille-meubles">';
    foreach ($meubles as $m) {
        echo '<li><a href="' . e(url('meuble.php', array('id' => $m['id_meuble']))) . '">'
            . img(imageMeuble($m['image']), $m['nom_meuble'], 72)
            . '<span class="nom">' . e($m['nom_meuble']) . '</span>'
            . '<span class="discret">' . e($m['nom_categorie']) . '</span>'
            . '<span class="prix">' . e(prix($m['prix_achat'])) . '</span>'
            . ($m['bricolable'] ? '<span class="badge info">Bricolage</span>' : '')
            . (isset($m['nb_variantes']) && $m['nb_variantes'] > 1 ? '<span class="badge neutre">' . (int) $m['nb_variantes'] . ' variantes</span>' : '')
            . '</a></li>';
    }
    echo '</ul>';
}

$titre = $vue === 'saison' ? 'Meubles de saison' : 'Catalogue des meubles';
$pageActive = 'meubles';
require 'includes/header.php';
?>
<div class="entete-page">
    <h1>Meubles</h1>
    <nav class="onglets" aria-label="Vues">
        <a href="<?= e(url('meubles.php', array('hemisphere' => $f['hemisphere']))) ?>" <?= $vue === 'saison' ? 'aria-current="page"' : '' ?>>Meubles de saison</a>
        <a href="<?= e(url('meubles.php', array('vue' => 'catalogue'))) ?>" <?= $vue === 'catalogue' ? 'aria-current="page"' : '' ?>>Tout le catalogue</a>
    </nav>
</div>

<?php if ($vue === 'saison'): ?>

<form class="filtres filtres-groupes" method="get" action="meubles.php">
    <fieldset>
        <legend>Par période</legend>
        <label>Du <input type="date" name="du" value="<?= e($f['du']) ?>" min="2000-01-01" max="2060-12-31"></label>
        <label>Au <input type="date" name="au" value="<?= e($f['au']) ?>" min="2000-01-01" max="2060-12-31"></label>
        <label>Type
            <select name="type_evt">
                <option value="">Tous</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= e($t) ?>" <?= $f['type_evt'] === $t ? 'selected' : '' ?>><?= e(typeEvenement($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </fieldset>
    <fieldset>
        <legend>Ou par saison / événement</legend>
        <label>Saison ou événement
            <select name="evenement">
                <option value="">— Choisir —</option>
                <?php $typeCourant = null; foreach ($evenements as $ev): ?>
                    <?php if ($ev['type_evenement'] !== $typeCourant): ?>
                        <?= $typeCourant !== null ? '</optgroup>' : '' ?><optgroup label="<?= e(typeEvenement($ev['type_evenement'])) ?>">
                        <?php $typeCourant = $ev['type_evenement']; ?>
                    <?php endif; ?>
                    <option value="<?= (int) $ev['id_evenement'] ?>" <?= $f['evenement'] === (int) $ev['id_evenement'] ? 'selected' : '' ?>>
                        <?= e($ev['nom_evenement']) ?> (<?= (int) $ev['nb_meubles'] ?>)
                    </option>
                <?php endforeach; ?>
                <?= $typeCourant !== null ? '</optgroup>' : '' ?>
            </select>
        </label>
    </fieldset>
    <fieldset>
        <legend>Affiner</legend>
        <label>Hémisphère
            <select name="hemisphere">
                <option value="NH">Nord</option>
                <option value="SH" <?= $f['hemisphere'] === 'SH' ? 'selected' : '' ?>>Sud</option>
            </select>
        </label>
        <label>Catégorie
            <select name="categorie">
                <option value="">Toutes</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id_categorie'] ?>" <?= $f['categorie'] === (int) $c['id_categorie'] ? 'selected' : '' ?>><?= e($c['nom_categorie']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nom contient <input type="search" name="q" value="<?= e($f['q']) ?>" maxlength="40"></label>
    </fieldset>
    <button type="submit">Rechercher</button>
</form>

<?php if ($f['evenement']): ?>
    <section aria-labelledby="t-resultats">
        <h2 id="t-resultats"><?= e($evt['nom_evenement']) ?> <span class="discret"><?= e(typeEvenement($evt['type_evenement'])) ?></span></h2>
        <p>
            <?php if ($prochaines): ?>
                Prochaines dates (<?= hemisphereTexte($f['hemisphere']) ?>) :
                <?php foreach ($prochaines as $i => $p): ?><?= $i ? ', puis ' : '' ?><?= periodeDepuisAujourdhui($p['date_debut'], $p['date_fin'], $aujourdhui) ?><?php endforeach; ?>.
            <?php else: ?>
                Pas de date fixe dans le Google Sheet pour cet événement (ex. anniversaire du joueur).
            <?php endif; ?>
            <?= count($meubles) ?> meuble<?= count($meubles) > 1 ? 's' : '' ?>.
            <a href="<?= e(url('meubles.php', array('hemisphere' => $f['hemisphere']))) ?>">Rechercher par période</a>
        </p>
        <?php if ($meubles) { grilleMeubles($meubles); } else { echo '<p class="vide">Aucun meuble avec ces filtres.</p>'; } ?>
    </section>
<?php else: ?>
    <section aria-labelledby="t-resultats">
        <h2 id="t-resultats">
            <?= $f['du'] === $f['au'] ? 'Disponibles le ' . dateCourte($f['du']) : 'Disponibles entre le ' . dateCourte($f['du']) . ' et le ' . dateCourte($f['au']) ?>
        </h2>
        <?php if ($periodeReduite): ?><p class="note">La période est limitée à un an : recherche jusqu'au <?= dateCourte($f['au']) ?>.</p><?php endif; ?>
        <p class="intro"><?= ucfirst(hemisphereTexte($f['hemisphere'])) ?> : <?= $nbMeubles ?> meuble<?= $nbMeubles > 1 ? 's' : '' ?> dans <?= count($groupes) ?> saison<?= count($groupes) > 1 ? 's' : '' ?> ou événement<?= count($groupes) > 1 ? 's' : '' ?>.</p>
        <?php if (!$groupes): ?>
            <p class="vide">Aucun meuble de saison sur cette période. Élargissez les dates ou retirez un filtre.</p>
        <?php endif; ?>
        <?php foreach ($groupes as $g): $ev = $g['evenement']; ?>
            <div class="groupe">
                <h3>
                    <a href="<?= e(url('meubles.php', array('evenement' => $ev['id_evenement'], 'hemisphere' => $f['hemisphere']))) ?>"><?= e($ev['nom_evenement']) ?></a>
                    <span class="discret"><?= e(typeEvenement($ev['type_evenement'])) ?>, <?= periodeTexte($ev['date_debut'], $ev['date_fin']) ?></span>
                </h3>
                <?php grilleMeubles($g['meubles']); ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php else: ?>

<form class="filtres" method="get" action="meubles.php">
    <input type="hidden" name="vue" value="catalogue">
    <label>Nom contient <input type="search" name="q" value="<?= e($f['q']) ?>" maxlength="40" placeholder="ex. chair"></label>
    <label>Catégorie
        <select name="categorie">
            <option value="">Toutes</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id_categorie'] ?>" <?= $f['categorie'] === (int) $c['id_categorie'] ? 'selected' : '' ?>><?= e($c['nom_categorie']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Couleur
        <select name="couleur">
            <option value="">Toutes</option>
            <?php foreach ($couleurs as $c): ?>
                <option value="<?= (int) $c['id_couleur'] ?>" <?= $f['couleur'] === (int) $c['id_couleur'] ? 'selected' : '' ?>><?= e($c['nom_couleur']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Trier par
        <select name="tri">
            <option value="nom">Nom</option>
            <option value="prix_asc" <?= $f['tri'] === 'prix_asc' ? 'selected' : '' ?>>Prix croissant</option>
            <option value="prix_desc" <?= $f['tri'] === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
            <option value="variantes" <?= $f['tri'] === 'variantes' ? 'selected' : '' ?>>Nombre de variantes</option>
        </select>
    </label>
    <label class="case"><input type="checkbox" name="bricolable" value="1" <?= $f['bricolable'] ? 'checked' : '' ?>> Fabricable (bricolage)</label>
    <label class="case"><input type="checkbox" name="saisonnier" value="1" <?= $f['saisonnier'] ? 'checked' : '' ?>> Meubles de saison uniquement</label>
    <button type="submit">Rechercher</button>
</form>

<p class="intro"><?= nombre($total) ?> meuble<?= $total > 1 ? 's' : '' ?> trouvé<?= $total > 1 ? 's' : '' ?><?= $total > $parPage ? ', page ' . $f['page'] . ' sur ' . $nbPages : '' ?>.</p>
<?php if ($meubles) { grilleMeubles($meubles); } else { echo '<p class="vide">Aucun meuble ne correspond. Retirez un filtre pour élargir la recherche.</p>'; } ?>
<?= pagination('meubles.php', $f, $total, $parPage, $f['page']) ?>

<?php endif; ?>

<?php require 'includes/footer.php'; ?>
