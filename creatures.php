<?php
require_once 'config.php';
require_once 'includes/fonctions.php';

$titre_page = 'Poissons & insectes';
$page_active = 'creatures';


// ---------- 1. Lecture des paramètres de l'URL ($_GET) ----------
// Si un paramètre manque ou n'est pas autorisé, on prend la valeur par défaut.

if (isset($_GET['type'])) {
    $type = $_GET['type'];
} else {
    $type = 'tous';
}
if (!in_array($type, ['tous', 'poisson', 'insecte'])) {
    $type = 'tous';
}

if (isset($_GET['hemisphere'])) {
    $hemisphere = $_GET['hemisphere'];
} else {
    $hemisphere = 'NH';
}
if (!in_array($hemisphere, ['NH', 'SH'])) {
    $hemisphere = 'NH';
}

if (isset($_GET['mois'])) {
    $mois = intval($_GET['mois']);    // le texte de l'URL devient un nombre
} else {
    $mois = date('n');                  // mois actuel
}
if (!in_array($mois, range(1, 12))) {      // range(1, 12) = [1, 2, …, 12]
    $mois = date('n');
}

if (isset($_GET['heure'])) {
    $heure = intval($_GET['heure']);  // le texte de l'URL devient un nombre
} else {
    $heure = date('G');                 // heure actuelle
}
if (!in_array($heure, range(0, 23))) {     // range(0, 23) = [0, 1, …, 23]
    $heure = date('G');
}


// ---------- 2. Requête SQL : créatures disponibles à cet instant ----------
// Chaque « ? » reçoit une valeur du tableau $valeurs, dans l'ordre.
$sql = "SELECT nom_creature, type_creature, nom_lieu, ombre, meteo, prix_vente_creature
FROM creature
JOIN lieu ON creature.id_lieu = lieu.id_lieu
JOIN disponibilite ON creature.id_creature = disponibilite.id_creature
WHERE hemisphere = ?
  AND mois = ?
  AND heure_debut <= ?
  AND heure_fin > ?";
$valeurs = [$hemisphere, $mois, $heure, $heure];

// Le type n'est ajouté que si l'on a choisi « poisson » ou « insecte »
if ($type != 'tous') {
    $sql = $sql . "\n  AND type_creature = ?";
    $valeurs[] = $type;
}

$sql = $sql . "\nGROUP BY creature.id_creature\nORDER BY prix_vente_creature DESC, nom_creature";

$requete = $dbh->prepare($sql);
$requete->execute($valeurs);
$creatures = $requete->fetchAll(PDO::FETCH_ASSOC);


// ---------- 3. Affichage ----------
$noms_mois = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
              7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];

if ($hemisphere == 'NH') {
    $nom_hemisphere = 'Nord';
} else {
    $nom_hemisphere = 'Sud';
}

include 'includes/header.php';
?>
<div class="page-head">
    <h1>Poissons et insectes disponibles</h1>
    <p>Choisissez un instant : la base calcule ce qui peut être attrapé à ce moment-là.</p>
</div>

<form class="filters" method="get">
    <label>Type
        <select name="type">
            <option value="tous" <?php echo selectionne('tous', $type); ?>>Tous</option>
            <option value="poisson" <?php echo selectionne('poisson', $type); ?>>Poissons</option>
            <option value="insecte" <?php echo selectionne('insecte', $type); ?>>Insectes</option>
        </select>
    </label>
    <label>Hémisphère
        <select name="hemisphere">
            <option value="NH" <?php echo selectionne('NH', $hemisphere); ?>>Nord</option>
            <option value="SH" <?php echo selectionne('SH', $hemisphere); ?>>Sud</option>
        </select>
    </label>
    <label>Mois
        <select name="mois">
            <?php
            foreach ($noms_mois as $numero => $nom) {
                echo "<option value='$numero' " . selectionne($numero, $mois) . ">$nom</option>";
            }
            ?>
        </select>
    </label>
    <label>Heure
        <select name="heure">
            <?php
            for ($h = 0; $h < 24; $h++) {
                echo "<option value='$h' " . selectionne($h, $heure) . ">" . afficherHeure($h) . "</option>";
            }
            ?>
        </select>
    </label>
    <div class="actions">
        <button type="submit">Rechercher</button>
        <a class="btn-ghost" href="creatures.php?type=tous&hemisphere=NH&mois=<?php echo date('n'); ?>&heure=<?php echo date('G'); ?>">Maintenant</a>
    </div>
</form>

<p class="count"><strong><?php echo count($creatures); ?></strong> créature(s) · <?php echo $noms_mois[$mois] . ', ' . afficherHeure($heure) . ', hémisphère ' . $nom_hemisphere; ?></p>
<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Nom</th><th>Type</th><th>Lieu</th><th>Ombre</th><th>Météo</th><th class="num">Prix de vente</th></tr></thead>
        <tbody>
        <?php
        // ucfirst : première lettre en majuscule ; number_format(4000, 0, ',', ' ') affiche « 4 000 »
        foreach ($creatures as $creature) {
            // Les insectes n'ont pas d'ombre (NULL dans la base)
            if ($creature['ombre'] == null) {
                $ombre = '—';
            } else {
                $ombre = $creature['ombre'];
            }

            echo "<tr>
                <td class='strong'>" . htmlspecialchars(ucfirst($creature['nom_creature'])) . "</td>
                <td><span class='tag tag-" . $creature['type_creature'] . "'>" . $creature['type_creature'] . "</span></td>
                <td>" . htmlspecialchars($creature['nom_lieu']) . "</td>
                <td>" . htmlspecialchars($ombre) . "</td>
                <td>" . htmlspecialchars($creature['meteo']) . "</td>
                <td class='num'>" . number_format($creature['prix_vente_creature'], 0, ',', ' ') . " clochettes</td>
            </tr>";
        }
        if (count($creatures) == 0) {
            echo "<tr><td colspan='6' class='empty'>Aucune créature à cet instant.</td></tr>";
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
    <?php afficherRequete('Créatures disponibles à cet instant', $sql, $valeurs, count($creatures)); ?>
</section>

<?php include 'includes/footer.php'; ?>
