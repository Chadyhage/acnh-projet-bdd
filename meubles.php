<?php
require_once 'config.php';
require_once 'includes/fonctions.php';

$titre_page = 'Meubles de saison';
$page_active = 'meubles';


// ---------- 1. Liste des saisons (pour le menu déroulant) ----------
$sql_saisons = "SELECT id_saison, nom_saison
FROM saison
ORDER BY nom_saison";
$requete = $dbh->prepare($sql_saisons);
$requete->execute();
$saisons = $requete->fetchAll(PDO::FETCH_ASSOC);

// Les 4 catégories (une par onglet du Google Sheet)
$categories = [1 => 'Housewares', 2 => 'Miscellaneous', 3 => 'Wall-mounted', 4 => 'Ceiling Decor'];


// ---------- 2. Lecture des paramètres de l'URL ($_GET) ----------
if (isset($_GET['saison'])) {
    $saison = $_GET['saison'];
} else {
    $saison = 'toutes';
}
// La saison doit être « toutes » ou l'id d'une saison de la liste
$ids_saisons = [];
foreach ($saisons as $ligne) {
    $ids_saisons[] = $ligne['id_saison'];
}
if (!in_array($saison, $ids_saisons)) {
    $saison = 'toutes';
}

if (isset($_GET['categorie'])) {
    $categorie = $_GET['categorie'];
} else {
    $categorie = 'toutes';
}
if (!in_array($categorie, [1, 2, 3, 4])) {
    $categorie = 'toutes';
}


// ---------- 3. Requête SQL : meubles de la saison choisie ----------
// proposer est la table associative qui relie chaque meuble à ses saisons.
// WHERE 1 = 1 est toujours vrai : chaque filtre choisi ajoute une ligne AND.
$sql = "SELECT nom_meuble, nom_saison, nom_categorie, prix_achat, prix_vente_meuble
FROM meuble
JOIN categorie ON meuble.id_categorie = categorie.id_categorie
JOIN proposer ON meuble.id_meuble = proposer.id_meuble
JOIN saison ON proposer.id_saison = saison.id_saison
WHERE 1 = 1";
$valeurs = [];

if ($saison != 'toutes') {
    $sql = $sql . "\n  AND saison.id_saison = ?";
    $valeurs[] = $saison;
}
if ($categorie != 'toutes') {
    $sql = $sql . "\n  AND categorie.id_categorie = ?";
    $valeurs[] = $categorie;
}

$sql = $sql . "\nORDER BY nom_saison, nom_categorie, nom_meuble";

$requete = $dbh->prepare($sql);
$requete->execute($valeurs);
$meubles = $requete->fetchAll(PDO::FETCH_ASSOC);


// ---------- 4. Affichage ----------
include 'includes/header.php';
?>
<div class="page-head">
    <h1>Meubles de saison</h1>
    <p>Choisissez une saison ou un événement du jeu : la base renvoie les meubles proposés à ce moment-là.</p>
</div>

<form class="filters" method="get">
    <label>Saison
        <select name="saison">
            <option value="toutes">Toutes</option>
            <?php
            foreach ($saisons as $ligne) {
                echo "<option value='" . $ligne['id_saison'] . "' " . selectionne($ligne['id_saison'], $saison) . ">"
                    . htmlspecialchars($ligne['nom_saison']) . "</option>";
            }
            ?>
        </select>
    </label>
    <label>Catégorie
        <select name="categorie">
            <option value="toutes">Toutes</option>
            <?php
            foreach ($categories as $id => $nom) {
                echo "<option value='$id' " . selectionne($id, $categorie) . ">$nom</option>";
            }
            ?>
        </select>
    </label>
    <div class="actions">
        <button type="submit">Rechercher</button>
        <a class="btn-ghost" href="meubles.php?saison=toutes&categorie=toutes">Réinitialiser</a>
    </div>
</form>

<p class="count"><strong><?php echo count($meubles); ?></strong> résultat(s)</p>
<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Nom</th><th>Saison</th><th>Catégorie</th><th class="num">Prix d'achat</th><th class="num">Prix de vente</th></tr></thead>
        <tbody>
        <?php
        // ucfirst : première lettre en majuscule ; number_format(4000, 0, ',', ' ') affiche « 4 000 »
        foreach ($meubles as $meuble) {
            // prix_achat vaut NULL quand le meuble n'est pas en vente
            if ($meuble['prix_achat'] == null) {
                $prix_achat = "<span class='muted'>Non vendu</span>";
            } else {
                $prix_achat = number_format($meuble['prix_achat'], 0, ',', ' ');
            }

            echo "<tr>
                <td class='strong'>" . htmlspecialchars(ucfirst($meuble['nom_meuble'])) . "</td>
                <td>" . htmlspecialchars($meuble['nom_saison']) . "</td>
                <td>" . htmlspecialchars($meuble['nom_categorie']) . "</td>
                <td class='num'>$prix_achat</td>
                <td class='num'>" . number_format($meuble['prix_vente_meuble'], 0, ',', ' ') . "</td>
            </tr>";
        }
        if (count($meubles) == 0) {
            echo "<tr><td colspan='5' class='empty'>Aucun meuble pour cette saison dans cette catégorie.</td></tr>";
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
    afficherRequete('Liste des saisons', $sql_saisons, [], count($saisons));
    afficherRequete('Meubles de la saison choisie', $sql, $valeurs, count($meubles));
    ?>
</section>

<?php include 'includes/footer.php'; ?>
