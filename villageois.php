<?php
require_once 'config.php';
require_once 'includes/fonctions.php';

$titre_page = 'Villageois';
$page_active = 'villageois';


// ---------- 1. Listes des espèces et des personnalités (pour les menus déroulants) ----------
$sql_especes = "SELECT id_espece, nom_espece
FROM espece
ORDER BY nom_espece";
$requete = $dbh->prepare($sql_especes);
$requete->execute();
$especes = $requete->fetchAll(PDO::FETCH_ASSOC);

$sql_personnalites = "SELECT id_personnalite, nom_personnalite
FROM personnalite
ORDER BY nom_personnalite";
$requete = $dbh->prepare($sql_personnalites);
$requete->execute();
$personnalites = $requete->fetchAll(PDO::FETCH_ASSOC);


// ---------- 2. Lecture des paramètres de l'URL ($_GET) ----------
if (isset($_GET['nom']) && is_string($_GET['nom'])) {   // le nom doit être un texte
    $nom = trim($_GET['nom']);
} else {
    $nom = '';
}

if (isset($_GET['espece'])) {
    $espece = $_GET['espece'];
} else {
    $espece = 'toutes';
}
// L'espèce doit être « toutes » ou l'id d'une espèce de la liste
$ids_especes = [];
foreach ($especes as $ligne) {
    $ids_especes[] = $ligne['id_espece'];
}
if (!in_array($espece, $ids_especes)) {
    $espece = 'toutes';
}

if (isset($_GET['personnalite'])) {
    $personnalite = $_GET['personnalite'];
} else {
    $personnalite = 'toutes';
}
$ids_personnalites = [];
foreach ($personnalites as $ligne) {
    $ids_personnalites[] = $ligne['id_personnalite'];
}
if (!in_array($personnalite, $ids_personnalites)) {
    $personnalite = 'toutes';
}

if (isset($_GET['genre'])) {
    $genre = $_GET['genre'];
} else {
    $genre = 'tous';
}
if (!in_array($genre, ['tous', 'Male', 'Female'])) {
    $genre = 'tous';
}


// ---------- 3. Requête SQL : villageois filtrés ----------
// WHERE 1 = 1 est toujours vrai : chaque filtre rempli ajoute une ligne AND.
$sql = "SELECT nom_villageois, nom_espece, nom_personnalite, genre,
       jour_anniversaire, mois_anniversaire, phrase_fetiche
FROM villageois
JOIN espece ON villageois.id_espece = espece.id_espece
JOIN personnalite ON villageois.id_personnalite = personnalite.id_personnalite
WHERE 1 = 1";
$valeurs = [];

if ($nom != '') {
    $sql = $sql . "\n  AND nom_villageois LIKE ?";
    $valeurs[] = '%' . $nom . '%';          // LIKE '%bo%' = le nom contient « bo »
}
if ($espece != 'toutes') {
    $sql = $sql . "\n  AND villageois.id_espece = ?";
    $valeurs[] = $espece;
}
if ($personnalite != 'toutes') {
    $sql = $sql . "\n  AND villageois.id_personnalite = ?";
    $valeurs[] = $personnalite;
}
if ($genre != 'tous') {
    $sql = $sql . "\n  AND genre = ?";
    $valeurs[] = $genre;
}

$sql = $sql . "\nORDER BY nom_villageois";

$requete = $dbh->prepare($sql);
$requete->execute($valeurs);
$villageois = $requete->fetchAll(PDO::FETCH_ASSOC);


// ---------- 4. Affichage ----------
$noms_mois = [1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin',
              7 => 'juil.', 8 => 'août', 9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.'];

include 'includes/header.php';
?>
<div class="page-head">
    <h1>Villageois</h1>
    <p>Consultez la liste complète des villageois ou combinez les filtres.</p>
</div>

<form class="filters" method="get">
    <label>Nom
        <input type="text" name="nom" value="<?php echo htmlspecialchars($nom); ?>" placeholder="ex. Bob">
    </label>
    <label>Espèce
        <select name="espece">
            <option value="toutes">Toutes</option>
            <?php
            foreach ($especes as $ligne) {
                echo "<option value='" . $ligne['id_espece'] . "' " . selectionne($ligne['id_espece'], $espece) . ">"
                    . htmlspecialchars($ligne['nom_espece']) . "</option>";
            }
            ?>
        </select>
    </label>
    <label>Personnalité
        <select name="personnalite">
            <option value="toutes">Toutes</option>
            <?php
            foreach ($personnalites as $ligne) {
                echo "<option value='" . $ligne['id_personnalite'] . "' " . selectionne($ligne['id_personnalite'], $personnalite) . ">"
                    . htmlspecialchars($ligne['nom_personnalite']) . "</option>";
            }
            ?>
        </select>
    </label>
    <label>Genre
        <select name="genre">
            <option value="tous">Tous</option>
            <option value="Male" <?php echo selectionne('Male', $genre); ?>>Masculin</option>
            <option value="Female" <?php echo selectionne('Female', $genre); ?>>Féminin</option>
        </select>
    </label>
    <div class="actions">
        <button type="submit">Rechercher</button>
        <a class="btn-ghost" href="villageois.php?nom=&espece=toutes&personnalite=toutes&genre=tous">Réinitialiser</a>
    </div>
</form>

<p class="count"><strong><?php echo count($villageois); ?></strong> villageois</p>
<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Nom</th><th>Espèce</th><th>Personnalité</th><th>Genre</th><th>Anniversaire</th><th>Phrase fétiche</th></tr></thead>
        <tbody>
        <?php
        foreach ($villageois as $ligne) {
            if ($ligne['genre'] == 'Male') {
                $genre_affiche = 'Masculin';
            } else {
                $genre_affiche = 'Féminin';
            }

            echo "<tr>
                <td class='strong'>" . htmlspecialchars($ligne['nom_villageois']) . "</td>
                <td>" . htmlspecialchars($ligne['nom_espece']) . "</td>
                <td>" . htmlspecialchars($ligne['nom_personnalite']) . "</td>
                <td>$genre_affiche</td>
                <td>" . $ligne['jour_anniversaire'] . " " . $noms_mois[$ligne['mois_anniversaire']] . "</td>
                <td class='muted'>« " . htmlspecialchars($ligne['phrase_fetiche']) . " »</td>
            </tr>";
        }
        if (count($villageois) == 0) {
            echo "<tr><td colspan='6' class='empty'>Aucun villageois ne correspond.</td></tr>";
        }
        ?>
        </tbody>
    </table>
</div>

<section class="sql-panel" id="sql">
    <div class="sql-head">
        <h2>Requêtes SQL exécutées</h2>
        <p>Action utilisateur → paramètres GET → requête SQL → résultat affiché</p>
    </div>
    <div class="sql-url">
        <span>URL reçue</span>
        <code><?php echo htmlspecialchars($_SERVER['REQUEST_URI']); /* adresse demandée par le navigateur */ ?></code>
    </div>
    <?php
    afficherRequete('Liste des espèces', $sql_especes, [], count($especes));
    afficherRequete('Liste des personnalités', $sql_personnalites, [], count($personnalites));
    afficherRequete('Villageois filtrés', $sql, $valeurs, count($villageois));
    ?>
</section>

<?php include 'includes/footer.php'; ?>
