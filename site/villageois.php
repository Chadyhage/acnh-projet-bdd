<?php
/**
 * Villageois : recherche par caractéristiques.
 * URL : villageois.php?espece=3&personnalite=2&genre=Female&loisir=1&style=4&couleur=7&mois=10&q=bo&tri=nom&page=1
 */
require 'config.php';
require 'includes/fonctions.php';

// Listes des menus déroulants (= valeurs autorisées)
$especes       = requete($dbh, "SELECT id_espece AS id, nom_espece AS nom FROM espece ORDER BY nom_espece")->fetchAll();
$personnalites = requete($dbh, "SELECT id_personnalite AS id, nom_personnalite AS nom FROM personnalite ORDER BY nom_personnalite")->fetchAll();
$loisirs       = requete($dbh, "SELECT id_loisir AS id, nom_loisir AS nom FROM loisir ORDER BY nom_loisir")->fetchAll();
$styles        = requete($dbh, "SELECT id_style AS id, nom_style AS nom FROM style ORDER BY nom_style")->fetchAll();
$couleurs      = requete($dbh, "SELECT DISTINCT c.id_couleur AS id, c.nom_couleur AS nom
                                FROM couleur c JOIN preferer p ON p.id_couleur = c.id_couleur
                                ORDER BY c.nom_couleur")->fetchAll();

/** Id choisi dans une liste, ou '' s'il est absent / inconnu. */
function choixDansListe($nom, array $liste)
{
    $id = getEntier($nom, 1, 100000, '');
    return in_array($id, array_map('intval', array_column($liste, 'id')), true) ? $id : '';
}

$tris = array(
    'nom'          => 'v.nom_villageois',
    'anniversaire' => 'v.mois_anniversaire, v.jour_anniversaire, v.nom_villageois',
    'espece'       => 'es.nom_espece, v.nom_villageois',
);
$f = array(
    'q'            => getTexte('q'),
    'espece'       => choixDansListe('espece', $especes),
    'personnalite' => choixDansListe('personnalite', $personnalites),
    'genre'        => getChoix('genre', array('Male', 'Female'), ''),
    'loisir'       => choixDansListe('loisir', $loisirs),
    'style'        => choixDansListe('style', $styles),
    'couleur'      => choixDansListe('couleur', $couleurs),
    'mois'         => getEntier('mois', 1, 12, ''),
    'tri'          => getChoix('tri', array_keys($tris), 'nom'),
);

// --- Construction du WHERE : une condition par filtre rempli
$conditions = array('1 = 1');
$params = array();
if ($f['q'] !== '') {
    $conditions[] = 'v.nom_villageois LIKE :q';
    $params[':q'] = motifLike($f['q']);
}
foreach (array('espece', 'personnalite', 'loisir') as $champ) {
    if ($f[$champ] !== '') {
        $conditions[] = "v.id_$champ = :$champ";
        $params[":$champ"] = $f[$champ];
    }
}
if ($f['genre'] !== '') {
    $conditions[] = 'v.genre = :genre';
    $params[':genre'] = $f['genre'];
}
if ($f['style'] !== '') {        // table associative AIMER
    $conditions[] = 'EXISTS (SELECT 1 FROM aimer a WHERE a.id_villageois = v.id_villageois AND a.id_style = :style)';
    $params[':style'] = $f['style'];
}
if ($f['couleur'] !== '') {      // table associative PREFERER
    $conditions[] = 'EXISTS (SELECT 1 FROM preferer p WHERE p.id_villageois = v.id_villageois AND p.id_couleur = :couleur)';
    $params[':couleur'] = $f['couleur'];
}
if ($f['mois'] !== '') {
    $conditions[] = 'v.mois_anniversaire = :mois';
    $params[':mois'] = $f['mois'];
}
$where = implode("\n               AND ", $conditions);

$total = (int) requete($dbh, "SELECT COUNT(*) FROM villageois v WHERE $where", $params)->fetchColumn();
$totalBase = (int) requete($dbh, "SELECT COUNT(*) FROM villageois")->fetchColumn();
$parPage = 30;
$nbPages = max(1, (int) ceil($total / $parPage));
$f['page'] = min(getEntier('page', 1, 1000, 1), $nbPages);
$decalage = ($f['page'] - 1) * $parPage;

$villageois = requete($dbh,
    "SELECT v.id_villageois, v.nom_villageois, v.genre, v.jour_anniversaire, v.mois_anniversaire,
            v.nom_fichier_villageois, es.nom_espece, pe.nom_personnalite, lo.nom_loisir
     FROM villageois v
     JOIN espece es       ON es.id_espece = v.id_espece
     JOIN personnalite pe ON pe.id_personnalite = v.id_personnalite
     JOIN loisir lo       ON lo.id_loisir = v.id_loisir
     WHERE $where
     ORDER BY " . $tris[$f['tri']] . "
     LIMIT $parPage OFFSET $decalage",
    $params)->fetchAll();

/** Menu déroulant à partir d'une liste [id, nom]. */
function menu($nom, $libelle, array $liste, $valeur)
{
    echo '<label>' . e($libelle) . ' <select name="' . e($nom) . '"><option value="">Tous</option>';
    foreach ($liste as $item) {
        echo '<option value="' . (int) $item['id'] . '"' . ($valeur === (int) $item['id'] ? ' selected' : '') . '>' . e($item['nom']) . '</option>';
    }
    echo '</select></label>';
}

$titre = 'Villageois';
$pageActive = 'villageois';
require 'includes/header.php';
?>
<div class="entete-page">
    <h1>Villageois</h1>
    <p class="intro"><?= $total === $totalBase
        ? $totalBase . ' villageois dans la base. Combinez les filtres pour trouver ceux qui vous intéressent.'
        : $total . ' villageois sur ' . $totalBase . ' correspondent à la recherche.' ?></p>
</div>

<form class="filtres" method="get" action="villageois.php">
    <label>Nom contient <input type="search" name="q" value="<?= e($f['q']) ?>" maxlength="40" placeholder="ex. Bob"></label>
    <?php menu('espece', 'Espèce', $especes, $f['espece']); ?>
    <?php menu('personnalite', 'Personnalité', $personnalites, $f['personnalite']); ?>
    <label>Genre
        <select name="genre">
            <option value="">Tous</option>
            <option value="Female" <?= $f['genre'] === 'Female' ? 'selected' : '' ?>>Féminin</option>
            <option value="Male" <?= $f['genre'] === 'Male' ? 'selected' : '' ?>>Masculin</option>
        </select>
    </label>
    <?php menu('loisir', 'Loisir', $loisirs, $f['loisir']); ?>
    <?php menu('style', 'Style aimé', $styles, $f['style']); ?>
    <?php menu('couleur', 'Couleur préférée', $couleurs, $f['couleur']); ?>
    <label>Anniversaire en
        <select name="mois">
            <option value="">Tous les mois</option>
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $f['mois'] === $m ? 'selected' : '' ?>><?= nomMois($m) ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <label>Trier par
        <select name="tri">
            <option value="nom">Nom</option>
            <option value="anniversaire" <?= $f['tri'] === 'anniversaire' ? 'selected' : '' ?>>Date d'anniversaire</option>
            <option value="espece" <?= $f['tri'] === 'espece' ? 'selected' : '' ?>>Espèce</option>
        </select>
    </label>
    <button type="submit">Rechercher</button>
    <a class="lien-secondaire" href="villageois.php">Effacer les filtres</a>
</form>

<?php if (!$villageois): ?>
    <p class="vide">Aucun villageois ne correspond. Retirez un filtre pour élargir la recherche.</p>
<?php else: ?>
    <ul class="grille-villageois">
        <?php foreach ($villageois as $v): ?>
            <li><a href="<?= e(url('villageois_fiche.php', array('id' => $v['id_villageois']))) ?>">
                <?= img(imageVillageois($v['nom_fichier_villageois']), $v['nom_villageois'], 80) ?>
                <span class="nom"><?= e($v['nom_villageois']) ?></span>
                <span class="discret"><?= e($v['nom_espece']) ?>, <?= e($v['nom_personnalite']) ?></span>
                <span class="discret">Né<?= $v['genre'] === 'Female' ? 'e' : '' ?> le <?= (int) $v['jour_anniversaire'] === 1 ? '1er' : (int) $v['jour_anniversaire'] ?> <?= nomMois($v['mois_anniversaire']) ?></span>
            </a></li>
        <?php endforeach; ?>
    </ul>
    <?= pagination('villageois.php', $f, $total, $parPage, $f['page']) ?>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
