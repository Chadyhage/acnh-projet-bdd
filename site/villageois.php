<?php
/**
 * Recherche de villageois par caractéristiques.
 * Exemple d'URL : villageois.php?espece=11&style=2   (les chats qui aiment le style Cute)
 * Chaque filtre est facultatif : sans filtre, les 417 villageois sont affichés.
 */
require 'config.php';
require 'includes/fonctions.php';

// 1. Lire les paramètres de l'URL, en les vérifiant (0 ou vide = filtre non utilisé)
$espece       = getEntier('espece', 1, 1000, 0);
$personnalite = getEntier('personnalite', 1, 1000, 0);
$genre        = getChoix('genre', array('Male', 'Female'), '');
$style        = getEntier('style', 1, 1000, 0);

// 2. Les choix des listes déroulantes, lus dans la base
$especes       = requete($dbh, "SELECT id_espece, nom_espece FROM espece ORDER BY nom_espece")->fetchAll();
$personnalites = requete($dbh, "SELECT id_personnalite, nom_personnalite FROM personnalite ORDER BY nom_personnalite")->fetchAll();
$styles        = requete($dbh, "SELECT id_style, nom_style FROM style ORDER BY nom_style")->fetchAll();

// 3. La requête de recherche : une condition est ajoutée pour chaque filtre rempli.
//    « WHERE 1 = 1 » est toujours vrai : il permet d'ajouter ensuite chaque condition avec AND.
$sql = "SELECT v.nom_villageois, e.nom_espece, p.nom_personnalite, v.genre, v.jour_anniversaire, v.mois_anniversaire
FROM villageois v
JOIN espece e       ON e.id_espece = v.id_espece
JOIN personnalite p ON p.id_personnalite = v.id_personnalite
WHERE 1 = 1";
$params = array();
if ($espece) {
    $sql .= "\n  AND v.id_espece = :espece";
    $params[':espece'] = $espece;
}
if ($personnalite) {
    $sql .= "\n  AND v.id_personnalite = :personnalite";
    $params[':personnalite'] = $personnalite;
}
if ($genre !== '') {
    $sql .= "\n  AND v.genre = :genre";
    $params[':genre'] = $genre;
}
if ($style) {      // le style est dans la table associative aimer (un villageois aime 1 ou 2 styles)
    $sql .= "\n  AND v.id_villageois IN (SELECT a.id_villageois FROM aimer a WHERE a.id_style = :style)";
    $params[':style'] = $style;
}
$sql .= "\nORDER BY v.nom_villageois";
$villageois = requete($dbh, $sql, $params)->fetchAll();

// 4. Affichage
$titre = 'Villageois';
$pageActive = 'villageois';
require 'includes/header.php';
?>
<h1>Villageois</h1>

<form method="get" action="villageois.php">
    <label>Espèce
        <select name="espece">
            <option value="">Toutes</option>
            <?php foreach ($especes as $x): ?>
                <option value="<?= $x['id_espece'] ?>" <?= (int) $x['id_espece'] === $espece ? 'selected' : '' ?>><?= e($x['nom_espece']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Personnalité
        <select name="personnalite">
            <option value="">Toutes</option>
            <?php foreach ($personnalites as $x): ?>
                <option value="<?= $x['id_personnalite'] ?>" <?= (int) $x['id_personnalite'] === $personnalite ? 'selected' : '' ?>><?= e($x['nom_personnalite']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Genre
        <select name="genre">
            <option value="">Tous</option>
            <option value="Female" <?= $genre === 'Female' ? 'selected' : '' ?>>Féminin</option>
            <option value="Male" <?= $genre === 'Male' ? 'selected' : '' ?>>Masculin</option>
        </select>
    </label>
    <label>Style aimé
        <select name="style">
            <option value="">Tous</option>
            <?php foreach ($styles as $x): ?>
                <option value="<?= $x['id_style'] ?>" <?= (int) $x['id_style'] === $style ? 'selected' : '' ?>><?= e($x['nom_style']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Rechercher</button>
</form>

<p class="resume"><?= count($villageois) ?> villageois trouvé<?= count($villageois) > 1 ? 's' : '' ?>.</p>

<table class="liste">
    <tr><th>Nom</th><th>Espèce</th><th>Personnalité</th><th>Genre</th><th>Anniversaire</th></tr>
    <?php foreach ($villageois as $v): ?>
        <tr><td class="nom"><?= e($v['nom_villageois']) ?></td><td><?= e($v['nom_espece']) ?></td>
            <td><?= e($v['nom_personnalite']) ?></td><td><?= $v['genre'] === 'Female' ? 'Féminin' : 'Masculin' ?></td>
            <td><?= (int) $v['jour_anniversaire'] === 1 ? '1er' : (int) $v['jour_anniversaire'] ?> <?= nomMois($v['mois_anniversaire']) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php require 'includes/footer.php'; ?>
