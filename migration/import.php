<?php
// =====================================================================
// Migration : fichiers CSV du Google Sheet -> base MySQL « acnh_site »
// =====================================================================
// À ouvrir dans le navigateur, Wamp démarré : http://localhost/acnh_site/migration/import.php
//
// 1. On recrée la base vide avec le fichier sql/01_creation_tables.sql.
// 2. On lit chaque fichier CSV (dossier data/csv) ligne par ligne.
// 3. Pour chaque ligne, on convertit les valeurs utiles et on les insère avec une requête INSERT.

set_time_limit(300);   // la migration peut durer plus de 30 secondes

// ---------- Connexion (sans nom de base : le script crée la base) ----------
try {
    $dbh = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}


// ---------- Fonctions ----------

// Lit un fichier CSV et renvoie toutes ses lignes.
// Chaque ligne est un tableau « nom de la colonne => valeur », par exemple $ligne['Name'].
function lireCsv($fichier)
{
    $f = fopen('../data/csv/' . $fichier, 'r');

    // fgetcsv lit une ligne et la découpe en cases (une virgule entre guillemets reste dans sa case).
    // Paramètres : fichier, longueur maximale (0 = sans limite), séparateur, guillemet, caractère d'échappement (aucun).
    $entetes = fgetcsv($f, 0, ',', '"', '');

    $lignes = [];
    while (($cases = fgetcsv($f, 0, ',', '"', '')) !== false) {
        $ligne = [];
        for ($i = 0; $i < count($entetes); $i++) {
            $ligne[$entetes[$i]] = $cases[$i];
        }
        $lignes[] = $ligne;
    }

    fclose($f);
    return $lignes;
}

// Convertit une heure écrite dans le Google Sheet en nombre de 0 à 23.
// Exemples : '4 PM' -> 16, '9 AM' -> 9, '12 AM' -> 0 (minuit), '12 PM' -> 12 (midi)
function convertirHeure($texte)
{
    $texte = trim($texte);
    $heure = intval($texte);              // '4 PM' -> 4

    if (str_contains($texte, 'PM') && $heure != 12) {
        $heure = $heure + 12;             // après-midi
    }
    if (str_contains($texte, 'AM') && $heure == 12) {
        $heure = 0;                       // minuit
    }
    return $heure;
}

// Ajoute dans la table disponibilite les plages horaires d'une case du Google Sheet.
// Exemples de case : 'NA' (absente ce mois-là), 'All day', '4 PM – 9 AM', '9 AM – 4 PM; 9 PM – 4 AM'
function ajouterPlages($dbh, $id_creature, $hemisphere, $mois, $cellule)
{
    if ($cellule == 'NA') {
        return;                           // pas de plage ce mois-ci
    }
    if ($cellule == 'All day') {
        $cellule = '12 AM – 12 AM';       // toute la journée = de minuit à minuit
    }

    // Le Google Sheet contient des espaces insécables (&nbsp; en HTML) : on les remplace par des espaces normaux
    $cellule = str_replace(html_entity_decode('&nbsp;'), ' ', $cellule);

    $requete = $dbh->prepare('INSERT INTO disponibilite (id_creature, hemisphere, mois, heure_debut, heure_fin)
                              VALUES (?, ?, ?, ?, ?)');

    // Une case peut contenir deux plages séparées par « ; »
    $plages = explode(';', $cellule);
    foreach ($plages as $plage) {
        $heures = explode('–', $plage);   // '4 PM – 9 AM' -> '4 PM ' et ' 9 AM'
        $debut = convertirHeure($heures[0]);
        $fin = convertirHeure($heures[1]);

        if ($fin == 0) {
            $fin = 24;                    // une fin à minuit est notée 24
        }

        if ($debut < $fin) {
            // Plage normale, par exemple 9 h -> 16 h
            $requete->execute([$id_creature, $hemisphere, $mois, $debut, $fin]);
        } else {
            // Plage qui passe minuit : 21 h -> 4 h devient 21 h -> 24 h, puis 0 h -> 4 h
            $requete->execute([$id_creature, $hemisphere, $mois, $debut, 24]);
            $requete->execute([$id_creature, $hemisphere, $mois, 0, $fin]);
        }
    }
}

// 'Yes' -> 1, tout le reste -> 0 (pour les colonnes BOOLEAN)
function ouiNon($texte)
{
    if ($texte == 'Yes') {
        return 1;
    }
    return 0;
}


// ---------- 1. Création de la base et des 10 tables ----------
$sql = file_get_contents('../sql/01_creation_tables.sql');
$dbh->exec($sql);                     // exec : exécute les requêtes du fichier, sans résultat à lire
$dbh->exec('USE acnh_site');
echo "<h1>Migration du Google Sheet vers la base acnh_site</h1>";
echo "<p>1. Base et tables créées (sql/01_creation_tables.sql)</p>";


// ---------- 2. Lieux, créatures et disponibilités (fish.csv, insects.csv) ----------
// On met les poissons et les insectes dans un seul tableau, en ajoutant le type de chaque créature.
$creatures = [];
foreach (lireCsv('fish.csv') as $ligne) {
    $ligne['type'] = 'poisson';
    $ligne['Weather'] = 'Any weather';        // les poissons n'ont pas de colonne Weather
    $creatures[] = $ligne;
}
foreach (lireCsv('insects.csv') as $ligne) {
    $ligne['type'] = 'insecte';
    $ligne['Shadow'] = null;                   // les insectes n'ont ni ombre ni difficulté
    $ligne['Catch Difficulty'] = null;
    $creatures[] = $ligne;
}

$ajoutLieu = $dbh->prepare('INSERT INTO lieu (nom_lieu) VALUES (?)');
$ajoutCreature = $dbh->prepare('INSERT INTO creature (id_lieu, type_creature, numero_encyclopedie, nom_creature,
        prix_vente_creature, ombre, difficulte, meteo, captures_requises, description, phrase_capture,
        nom_fichier_creature)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$ids_lieux = [];                               // nom du lieu => id_lieu
$mois_du_sheet = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
                  7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];

foreach ($creatures as $ligne) {
    $lieu = $ligne['Where/How'];
    $meteo = $ligne['Weather'];

    // Le cœlacanthe vit en « Sea (rainy days) » : le lieu est Sea et la météo Rain only
    if ($lieu == 'Sea (rainy days)') {
        $lieu = 'Sea';
        $meteo = 'Rain only';
    }

    // Le lieu est ajouté la première fois qu'on le rencontre
    if (!isset($ids_lieux[$lieu])) {
        $ajoutLieu->execute([$lieu]);
        $ids_lieux[$lieu] = $dbh->lastInsertId();   // lastInsertId : l'id AUTO_INCREMENT qui vient d'être créé
    }

    $ajoutCreature->execute([$ids_lieux[$lieu], $ligne['type'], $ligne['#'], $ligne['Name'], $ligne['Sell'],
                             $ligne['Shadow'], $ligne['Catch Difficulty'], $meteo,
                             $ligne['Total Catches to Unlock'], $ligne['Description'], $ligne['Catch phrase'],
                             $ligne['Icon Filename']]);
    $id_creature = $dbh->lastInsertId();

    // Une colonne par hémisphère et par mois : NH Jan, NH Feb… SH Dec
    foreach (['NH', 'SH'] as $hemisphere) {
        for ($mois = 1; $mois <= 12; $mois++) {
            $cellule = $ligne[$hemisphere . ' ' . $mois_du_sheet[$mois]];
            ajouterPlages($dbh, $id_creature, $hemisphere, $mois, $cellule);
        }
    }
}
echo "<p>2. Lieux, créatures et disponibilités ajoutés (fish.csv, insects.csv)</p>";


// ---------- 3. Catégories et meubles (un onglet du Google Sheet par catégorie) ----------
$onglets = [
    'Housewares'    => 'housewares.csv',
    'Miscellaneous' => 'miscellaneous.csv',
    'Wall-mounted'  => 'wall_mounted.csv',
    'Ceiling Decor' => 'ceiling_decor.csv',
];

$ajoutCategorie = $dbh->prepare('INSERT INTO categorie (nom_categorie) VALUES (?)');
$ajoutMeuble = $dbh->prepare('INSERT INTO meuble (id_categorie, nom_meuble, prix_achat, prix_vente_meuble,
        bricolable, personnalisable, taille, exterieur, etiquette, serie, catalogue, points_hha,
        version_ajout_meuble, id_interne)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$meubles_ajoutes = [];        // Internal ID des meubles déjà ajoutés
$saisons_des_meubles = [];    // pour la table proposer : [id_meuble, nom de la saison]
$noms_saisons = [];           // les saisons citées par les meubles, dans l'ordre

foreach ($onglets as $categorie => $fichier) {
    $ajoutCategorie->execute([$categorie]);
    $id_categorie = $dbh->lastInsertId();

    foreach (lireCsv($fichier) as $ligne) {
        // Dans le Sheet, un meuble a une ligne par variante (couleur, motif) : on garde la première
        if (in_array($ligne['Internal ID'], $meubles_ajoutes)) {
            continue;                     // on passe directement à la ligne suivante du fichier
        }
        $meubles_ajoutes[] = $ligne['Internal ID'];

        // NFS (« not for sale ») = pas en vente -> NULL
        if ($ligne['Buy'] == 'NFS') {
            $prix_achat = null;
        } else {
            $prix_achat = $ligne['Buy'];
        }
        // None = pas de série -> NULL
        if ($ligne['HHA Series'] == 'None') {
            $serie = null;
        } else {
            $serie = $ligne['HHA Series'];
        }

        $ajoutMeuble->execute([$id_categorie, $ligne['Name'], $prix_achat, $ligne['Sell'],
                               ouiNon($ligne['DIY']), ouiNon($ligne['Body Customize']), $ligne['Size'],
                               ouiNon($ligne['Outdoor']), $ligne['Tag'], $serie, $ligne['Catalog'],
                               $ligne['HHA Base Points'], $ligne['Version Added'], $ligne['Internal ID']]);
        $id_meuble = $dbh->lastInsertId();

        // Saisons du meuble : 'Bunny Day (days before); Bunny Day' -> deux saisons (NA = aucune)
        if ($ligne['Season/Event'] != 'NA') {
            foreach (explode(';', $ligne['Season/Event']) as $saison) {
                $saison = trim($saison);
                $saisons_des_meubles[] = [$id_meuble, $saison];
                if (!in_array($saison, $noms_saisons)) {
                    $noms_saisons[] = $saison;
                }
            }
        }
    }
}
echo "<p>3. Catégories et meubles ajoutés (4 onglets de meubles)</p>";


// ---------- 4. Saisons et table associative proposer (seasons_and_events.csv) ----------
$ajoutSaison = $dbh->prepare('INSERT INTO saison (nom_saison, type_saison) VALUES (?, ?)');
$ids_saisons = [];                                 // nom de la saison => id_saison

// Les saisons de l'onglet Seasons and Events qui proposent au moins un meuble
foreach (lireCsv('seasons_and_events.csv') as $ligne) {
    if (in_array($ligne['Name'], $noms_saisons)) {
        $ajoutSaison->execute([$ligne['Name'], $ligne['Type']]);
        $ids_saisons[$ligne['Name']] = $dbh->lastInsertId();
    }
}
// Les saisons citées par des meubles mais absentes de l'onglet : type « Autre »
foreach ($noms_saisons as $saison) {
    if (!isset($ids_saisons[$saison])) {
        $ajoutSaison->execute([$saison, 'Autre']);
        $ids_saisons[$saison] = $dbh->lastInsertId();
    }
}

// Un lien meuble <-> saison par saison citée
$ajoutProposer = $dbh->prepare('INSERT INTO proposer (id_meuble, id_saison) VALUES (?, ?)');
foreach ($saisons_des_meubles as $lien) {
    $ajoutProposer->execute([$lien[0], $ids_saisons[$lien[1]]]);
}
echo "<p>4. Saisons et liens meuble-saison ajoutés (seasons_and_events.csv)</p>";


// ---------- 5. Espèces, personnalités et villageois (villagers.csv) ----------
$ajoutEspece = $dbh->prepare('INSERT INTO espece (nom_espece) VALUES (?)');
$ajoutPersonnalite = $dbh->prepare('INSERT INTO personnalite (nom_personnalite) VALUES (?)');
$ajoutVillageois = $dbh->prepare('INSERT INTO villageois (id_espece, id_personnalite, nom_villageois, genre,
        sous_type, jour_anniversaire, mois_anniversaire, phrase_fetiche, chanson_preferee, citation_preferee,
        version_ajout_villageois, nom_fichier_villageois)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$ids_especes = [];                                 // nom de l'espèce => id_espece
$ids_personnalites = [];                           // nom de la personnalité => id_personnalite

foreach (lireCsv('villagers.csv') as $ligne) {
    // Espèce et personnalité : ajoutées la première fois qu'on les rencontre
    if (!isset($ids_especes[$ligne['Species']])) {
        $ajoutEspece->execute([$ligne['Species']]);
        $ids_especes[$ligne['Species']] = $dbh->lastInsertId();
    }
    if (!isset($ids_personnalites[$ligne['Personality']])) {
        $ajoutPersonnalite->execute([$ligne['Personality']]);
        $ids_personnalites[$ligne['Personality']] = $dbh->lastInsertId();
    }

    // Anniversaire au format mois/jour : '7/9' = 9 juillet
    $date = explode('/', $ligne['Birthday']);

    $ajoutVillageois->execute([$ids_especes[$ligne['Species']], $ids_personnalites[$ligne['Personality']],
                               $ligne['Name'], $ligne['Gender'], $ligne['Subtype'], $date[1], $date[0],
                               $ligne['Catchphrase'], $ligne['Favorite Song'], $ligne['Favorite Saying'],
                               $ligne['Version Added'], $ligne['Filename']]);
}
echo "<p>5. Espèces, personnalités et villageois ajoutés (villagers.csv)</p>";


// ---------- 6. Bilan : nombre de lignes de chaque table ----------
$tables = ['lieu', 'creature', 'disponibilite', 'categorie', 'meuble', 'saison', 'proposer',
           'espece', 'personnalite', 'villageois'];
$total = 0;

echo "<h2>Bilan</h2><ul>";
foreach ($tables as $table) {
    $requete = $dbh->prepare("SELECT COUNT(*) AS nb FROM " . $table);   // $table vient de la liste ci-dessus, pas de l'URL
    $requete->execute();
    $resultat = $requete->fetch(PDO::FETCH_ASSOC);
    echo "<li>" . $table . " : " . $resultat['nb'] . " lignes</li>";
    $total = $total + $resultat['nb'];
}
echo "</ul><p><strong>Total : $total lignes. Migration terminée.</strong></p>";
