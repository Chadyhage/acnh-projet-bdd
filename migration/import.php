<?php
/**
 * Migration : Google Sheet (CSV) -> base MySQL « acnh »
 * =====================================================================
 * Entrées :
 *   - data/csv/*.csv          un fichier par onglet du Google Sheet
 *                             (Fichier > Télécharger > Valeurs séparées par des virgules)
 *   - data/fleurs/genotypes.csv  table génotype -> couleur des fleurs
 *                             (trouvée en ligne, voir data/fleurs/SOURCES.md)
 *   - sql/01_schema.sql       structure de la base
 *
 * Lancement :
 *   - en ligne de commande :  php migration/import.php
 *   - ou dans le navigateur : http://localhost/acnh/migration/import.php (projet copié dans C:\wamp64\www\acnh)
 *
 * Ce que fait le script, dans l'ordre :
 *   0. recrée la base vide à partir de sql/01_schema.sql
 *   1. poissons et insectes      -> creature, lieu, disponibilite
 *   2. saisons et événements     -> evenement, periode
 *   3. meubles (4 onglets)       -> meuble, categorie, variante, couleur, colorer, source, provenir, proposer
 *   4. villageois                -> villageois, espece, personnalite, loisir, style, aimer, preferer, meubler
 *   5. fleurs (onglet Plants)    -> espece_fleur, fleur
 *   6. génotypes des fleurs      -> genotype
 *   7. croisements calculés      -> croiser
 * Les étapes 1 à 7 sont faites dans une transaction : en cas d'erreur, rien n'est importé.
 */

// --- Connexion (paramètres Wamp par défaut, modifiables par variables d'environnement)
$hote = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$mdp  = getenv('DB_PASS') ?: '';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(300);

try {
    // Pas de nom de base ici : le script commence par (re)créer la base.
    $dbh = new PDO("mysql:host=$hote;port=$port;charset=utf8mb4", $user, $mdp, array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));
} catch (PDOException $e) {
    exit("Connexion impossible : " . $e->getMessage() . "\n");
}

define('RACINE', dirname(__DIR__));

// =====================================================================
// Fonctions utilitaires
// =====================================================================

/** Lit un CSV et renvoie un tableau de lignes associatives (clé = en-tête de colonne). */
function lireCsv($chemin)
{
    $f = fopen(RACINE . '/' . $chemin, 'r');
    if (!$f) {
        throw new Exception("Fichier introuvable : $chemin");
    }
    $entetes = fgetcsv($f, 0, ',', '"', '');
    $lignes = array();
    while (($ligne = fgetcsv($f, 0, ',', '"', '')) !== false) {
        if ($ligne === array(null)) {
            continue; // ligne vide
        }
        $lignes[] = array_combine($entetes, array_pad($ligne, count($entetes), ''));
    }
    fclose($f);
    return $lignes;
}

/** « NA », « NFS » ou vide -> NULL, sinon entier. */
function entierOuNull($v)
{
    $v = trim($v);
    return ($v === '' || $v === 'NA' || $v === 'NFS') ? null : (int) $v;
}

/** « Yes » -> 1, « No » -> 0, autre (« NA ») -> NULL. */
function ouiNon($v)
{
    $v = trim($v);
    if ($v === 'Yes') {
        return 1;
    }
    if ($v === 'No') {
        return 0;
    }
    return null;
}

/** « NA » / « None » / vide -> NULL, sinon le texte. */
function texteOuNull($v)
{
    $v = trim($v);
    return ($v === '' || $v === 'NA' || $v === 'None') ? null : $v;
}

/** Sépare une cellule à plusieurs valeurs « A; B; C ». */
function separer($v)
{
    $valeurs = array_map('trim', explode(';', $v));
    return array_values(array_filter($valeurs, function ($x) {
        return $x !== '' && $x !== 'NA';
    }));
}

/** « 4 PM » -> 16, « 12 AM » -> 0. */
function heure24($h)
{
    if (!preg_match('/^(\d{1,2})\s*(AM|PM)$/', trim($h), $m)) {
        throw new Exception("Heure illisible : « $h »");
    }
    $heure = ((int) $m[1]) % 12;
    return $m[2] === 'PM' ? $heure + 12 : $heure;
}

/**
 * Cellule d'un mois (« 4 PM – 9 AM », « All day », « 9 AM – 4 PM; 9 PM – 4 AM », « NA »)
 * -> liste de créneaux [debut, fin]. La fin est exclue ; minuit en fin de créneau = 24.
 */
function creneaux($cellule)
{
    $cellule = trim(str_replace("\xC2\xA0", ' ', $cellule));   // espaces insécables du Sheet
    if ($cellule === 'NA' || $cellule === '') {
        return array();
    }
    if ($cellule === 'All day') {
        return array(array(0, 24));
    }
    $resultat = array();
    foreach (separer($cellule) as $plage) {
        $bornes = array_map('trim', preg_split('/–|-/u', $plage));
        $debut = heure24($bornes[0]);
        $fin = heure24($bornes[1]);
        $resultat[] = array($debut, $fin === 0 ? 24 : $fin);
    }
    return $resultat;
}

/** « Feb 29 » + année -> « 2026-02-28 » (le 29 février est ramené au 28 si l'année n'est pas bissextile). */
function versDate($jourMois, $annee)
{
    $d = DateTime::createFromFormat('!M j Y', "$jourMois $annee");
    if ($d === false) {
        throw new Exception("Date illisible : « $jourMois »");
    }
    if (strpos($jourMois, 'Feb 29') === 0 && $d->format('m') === '03') {
        $d->modify('-1 day');
    }
    return $d->format('Y-m-d');
}

/**
 * « Feb 25 – May 31 » ou « Apr 11 » pour une année -> [date_debut, date_fin].
 * Si la fin tombe avant le début (ex. Dec 22 – Jan 5), elle est l'année suivante.
 */
function periodeDates($cellule, $annee)
{
    $cellule = trim(str_replace("\xC2\xA0", ' ', $cellule));
    if ($cellule === 'NA' || $cellule === '') {
        return null;
    }
    $morceaux = array_map('trim', preg_split('/–/u', $cellule));
    $finTexte = isset($morceaux[1]) ? $morceaux[1] : $morceaux[0];
    $debut = versDate($morceaux[0], $annee);
    $fin = versDate($finTexte, $annee);
    if ($fin < $debut) {
        $fin = versDate($finTexte, $annee + 1);
    }
    return array($debut, $fin);
}

/**
 * Renvoie l'id d'une valeur dans une table de référence ; l'insère si elle n'existe pas encore.
 * Ex. : idReference($dbh, 'couleur', 'Red') -> 3
 */
function idReference(PDO $dbh, $table, $valeur, $autres = array())
{
    static $cache = array();
    if (isset($cache[$table][$valeur])) {
        return $cache[$table][$valeur];
    }
    $colonnes = array_merge(array("nom_$table" => $valeur), $autres);
    $sql = "INSERT INTO $table (" . implode(', ', array_keys($colonnes)) . ")
            VALUES (" . implode(', ', array_fill(0, count($colonnes), '?')) . ")";
    $dbh->prepare($sql)->execute(array_values($colonnes));
    $cache[$table][$valeur] = (int) $dbh->lastInsertId();
    return $cache[$table][$valeur];
}

/** Zone utilisée par les filtres du site, déduite du texte « Where/How ». */
function zoneDuLieu($lieu)
{
    $regles = array(
        '/^Sea|^Pier/'                => 'Mer',
        '/^River/'                    => 'Rivière',
        '/^Pond/'                     => 'Étang',
        '/flowers/i'                  => 'Fleurs',
        '/trees|stumps/i'             => 'Arbres',
        '/water|rivers\/ponds/i'      => 'Eau',
        '/^Flying/'                   => 'Air',
        '/rocks/i'                    => 'Rochers',
        '/shoreline|beach/i'          => 'Plage',
        '/ground|snowballs|turnips/i' => 'Sol',
    );
    foreach ($regles as $motif => $zone) {
        if (preg_match($motif, $lieu)) {
            return $zone;
        }
    }
    return 'Autre';
}

/**
 * Probabilités d'un gène de l'enfant selon les gènes des deux parents (valeurs 0, 1, 2),
 * en quarts : chaque parent donne au hasard une de ses deux moitiés (loi de Mendel).
 * Ex. : 1 x 1 -> [0 => 1, 1 => 2, 2 => 1]  (soit 25 %, 50 %, 25 %)
 */
function croiserUnGene($a, $b)
{
    $moities = array(0 => array(0, 0), 1 => array(0, 1), 2 => array(1, 1));
    $resultat = array();
    foreach ($moities[$a] as $x) {
        foreach ($moities[$b] as $y) {
            $resultat[$x + $y] = (isset($resultat[$x + $y]) ? $resultat[$x + $y] : 0) + 1;
        }
    }
    return $resultat;   // la somme vaut toujours 4
}

/**
 * Tous les génotypes enfants possibles de deux génotypes (« 2001 » x « 0200 »),
 * avec leur nombre de chances sur 4^n (n = nombre de gènes).
 */
function croiserGenotypes($g1, $g2)
{
    $enfants = array('' => 1);
    for ($i = 0; $i < strlen($g1); $i++) {
        $suivants = array();
        foreach ($enfants as $debut => $chances) {
            foreach (croiserUnGene((int) $g1[$i], (int) $g2[$i]) as $valeur => $quarts) {
                $suivants[$debut . $valeur] = $chances * $quarts;
            }
        }
        $enfants = $suivants;
    }
    return $enfants;
}

// =====================================================================
// 0. Structure : la base est recréée à partir du script SQL
// =====================================================================
$debutChrono = microtime(true);
$schema = file_get_contents(RACINE . '/sql/01_schema.sql');
$schema = preg_replace('/--.*$/m', '', $schema);              // retire les commentaires
foreach (array_filter(array_map('trim', explode(';', $schema))) as $instruction) {
    $dbh->exec($instruction);
}
echo "0. Base « acnh » recréée (sql/01_schema.sql)\n";

$dbh->beginTransaction();
try {
    // -----------------------------------------------------------------
    // 1. Poissons et insectes -> creature, lieu, disponibilite
    // -----------------------------------------------------------------
    $insCreature = $dbh->prepare(
        "INSERT INTO creature (id_lieu, type_creature, numero_encyclopedie, nom_creature, prix_vente_creature,
                               ombre, difficulte, meteo, captures_requises, description, phrase_capture, nom_fichier_creature)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insDispo = $dbh->prepare(
        "INSERT INTO disponibilite (id_creature, hemisphere, mois, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?)"
    );
    $moisAnglais = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');

    foreach (array('data/csv/fish.csv' => 'poisson', 'data/csv/insects.csv' => 'insecte') as $fichier => $type) {
        foreach (lireCsv($fichier) as $l) {
            $lieu  = $l['Where/How'];
            $meteo = isset($l['Weather']) ? $l['Weather'] : 'Any weather';   // pas de colonne Weather pour les poissons
            if ($lieu === 'Sea (rainy days)') {        // le cœlacanthe : la pluie est une condition, pas un lieu
                $lieu  = 'Sea';
                $meteo = 'Rain only';
            }
            $idLieu = idReference($dbh, 'lieu', $lieu, array('zone_lieu' => zoneDuLieu($lieu)));

            $insCreature->execute(array(
                $idLieu, $type, (int) $l['#'], $l['Name'], (int) $l['Sell'],
                isset($l['Shadow']) ? $l['Shadow'] : null,
                isset($l['Catch Difficulty']) ? $l['Catch Difficulty'] : null,
                $meteo, (int) $l['Total Catches to Unlock'], $l['Description'], $l['Catch phrase'], $l['Icon Filename'],
            ));
            $idCreature = (int) $dbh->lastInsertId();

            foreach (array('NH', 'SH') as $hemi) {
                foreach ($moisAnglais as $i => $m) {
                    foreach (creneaux($l["$hemi $m"]) as $c) {
                        $insDispo->execute(array($idCreature, $hemi, $i + 1, $c[0], $c[1]));
                    }
                }
            }
        }
    }
    echo "1. Poissons et insectes importés\n";

    // -----------------------------------------------------------------
    // 2. Saisons et événements -> evenement, periode (une période par année et par hémisphère)
    // -----------------------------------------------------------------
    $insEvenement = $dbh->prepare(
        "INSERT INTO evenement (nom_evenement, type_evenement, nom_affiche, date_variable) VALUES (?, ?, ?, ?)"
    );
    $insPeriode = $dbh->prepare(
        "INSERT INTO periode (id_evenement, annee, hemisphere_periode, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)"
    );
    $idsEvenement = array();
    foreach (lireCsv('data/csv/seasons_and_events.csv') as $l) {
        $insEvenement->execute(array($l['Name'], $l['Type'], $l['Display Name'], ouiNon($l['Date Varies by Year'])));
        $idEvt = (int) $dbh->lastInsertId();
        $idsEvenement[$l['Name']] = $idEvt;

        foreach ($l as $colonne => $valeur) {
            if (preg_match('/^(\d{4}) (NH|SH)$/', $colonne, $m)) {
                $dates = periodeDates($valeur, (int) $m[1]);
                if ($dates !== null) {
                    $insPeriode->execute(array($idEvt, (int) $m[1], $m[2], $dates[0], $dates[1]));
                }
            }
        }
    }
    echo "2. Saisons et événements importés\n";

    // -----------------------------------------------------------------
    // 3. Meubles : une ligne du Sheet = une variante.
    //    Le meuble est créé à la première ligne de son Internal ID.
    // -----------------------------------------------------------------
    $insMeuble = $dbh->prepare(
        "INSERT INTO meuble (id_categorie, nom_meuble, prix_achat, prix_vente_meuble, bricolable, personnalisable,
                             taille, exterieur, etiquette, serie, catalogue, exclusif_evenement, points_hha,
                             version_ajout_meuble, id_interne)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insVariante = $dbh->prepare(
        "INSERT INTO variante (id_meuble, nom_variation, nom_motif, nom_fichier_variante, id_unique) VALUES (?, ?, ?, ?, ?)"
    );
    $insColorer  = $dbh->prepare("INSERT IGNORE INTO colorer (id_variante, id_couleur) VALUES (?, ?)");
    $insProvenir = $dbh->prepare("INSERT INTO provenir (id_meuble, id_source) VALUES (?, ?)");
    $insProposer = $dbh->prepare("INSERT INTO proposer (id_meuble, id_evenement) VALUES (?, ?)");
    $insEvtAutre = $dbh->prepare(
        "INSERT INTO evenement (nom_evenement, type_evenement, nom_affiche, date_variable) VALUES (?, 'Autre', ?, 0)"
    );

    $onglets = array(
        'data/csv/housewares.csv'    => 'Housewares',
        'data/csv/miscellaneous.csv' => 'Miscellaneous',
        'data/csv/wall_mounted.csv'  => 'Wall-mounted',
        'data/csv/ceiling_decor.csv' => 'Ceiling Decor',
    );
    $idsMeuble = array();   // Internal ID du jeu -> id_meuble
    foreach ($onglets as $fichier => $categorie) {
        $idCategorie = idReference($dbh, 'categorie', $categorie);
        foreach (lireCsv($fichier) as $l) {
            $interne = (int) $l['Internal ID'];

            if (!isset($idsMeuble[$interne])) {
                $insMeuble->execute(array(
                    $idCategorie, $l['Name'], entierOuNull($l['Buy']), (int) $l['Sell'],
                    ouiNon($l['DIY']), ouiNon($l['Body Customize']), $l['Size'], ouiNon($l['Outdoor']),
                    $l['Tag'], texteOuNull($l['HHA Series']), $l['Catalog'], ouiNon($l['Season/Event Exclusive']),
                    (int) $l['HHA Base Points'], $l['Version Added'], $interne,
                ));
                $idMeuble = (int) $dbh->lastInsertId();
                $idsMeuble[$interne] = $idMeuble;

                foreach (separer($l['Source']) as $source) {
                    $insProvenir->execute(array($idMeuble, idReference($dbh, 'source', $source)));
                }
                foreach (separer($l['Season/Event']) as $evt) {
                    if (!isset($idsEvenement[$evt])) {     // ex. « Happy Home Paradise », absent de l'onglet des saisons
                        $insEvtAutre->execute(array($evt, $evt));
                        $idsEvenement[$evt] = (int) $dbh->lastInsertId();
                    }
                    $insProposer->execute(array($idMeuble, $idsEvenement[$evt]));
                }
            }

            $insVariante->execute(array(
                $idsMeuble[$interne], texteOuNull($l['Variation']), texteOuNull($l['Pattern']),
                $l['Filename'], $l['Unique Entry ID'],
            ));
            $idVariante = (int) $dbh->lastInsertId();
            foreach (array_unique(array($l['Color 1'], $l['Color 2'])) as $couleur) {
                if (texteOuNull($couleur) !== null) {
                    $insColorer->execute(array($idVariante, idReference($dbh, 'couleur', $couleur)));
                }
            }
        }
    }
    echo "3. Meubles importés\n";

    // -----------------------------------------------------------------
    // 4. Villageois -> villageois, espece, personnalite, loisir, aimer, preferer, meubler
    // -----------------------------------------------------------------
    $insVillageois = $dbh->prepare(
        "INSERT INTO villageois (id_espece, id_personnalite, id_loisir, nom_villageois, genre, sous_type,
                                 jour_anniversaire, mois_anniversaire, phrase_fetiche, chanson_preferee,
                                 citation_preferee, version_ajout_villageois, nom_fichier_villageois)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insAimer    = $dbh->prepare("INSERT IGNORE INTO aimer (id_villageois, id_style) VALUES (?, ?)");
    $insPreferer = $dbh->prepare("INSERT IGNORE INTO preferer (id_villageois, id_couleur) VALUES (?, ?)");
    $insMeubler  = $dbh->prepare("INSERT INTO meubler (id_villageois, id_meuble, quantite) VALUES (?, ?, ?)");
    $refsHorsPerimetre = 0;

    foreach (lireCsv('data/csv/villagers.csv') as $l) {
        $anniv = array_map('intval', explode('/', $l['Birthday']));     // format « mois/jour »
        $insVillageois->execute(array(
            idReference($dbh, 'espece', $l['Species']),
            idReference($dbh, 'personnalite', $l['Personality']),
            idReference($dbh, 'loisir', $l['Hobby']),
            $l['Name'], $l['Gender'], $l['Subtype'], $anniv[1], $anniv[0],
            $l['Catchphrase'], $l['Favorite Song'], $l['Favorite Saying'], $l['Version Added'], $l['Filename'],
        ));
        $idVillageois = (int) $dbh->lastInsertId();

        foreach (array($l['Style 1'], $l['Style 2']) as $style) {
            $insAimer->execute(array($idVillageois, idReference($dbh, 'style', $style)));
        }
        foreach (array($l['Color 1'], $l['Color 2']) as $couleur) {
            $insPreferer->execute(array($idVillageois, idReference($dbh, 'couleur', $couleur)));
        }
        // Furniture List : Internal ID séparés par « ; » ; un même meuble peut revenir plusieurs fois
        foreach (array_count_values(separer($l['Furniture List'])) as $interne => $quantite) {
            if (isset($idsMeuble[(int) $interne])) {
                $insMeubler->execute(array($idVillageois, $idsMeuble[(int) $interne], $quantite));
            } else {
                $refsHorsPerimetre++;   // tapis, vêtement, poisson… : objets hors périmètre
            }
        }
    }
    echo "4. Villageois importés\n";

    // -----------------------------------------------------------------
    // 5. Fleurs (onglet Plants) : on garde les lignes « <couleur>-<espèce> plant »
    // -----------------------------------------------------------------
    $insFleur = $dbh->prepare(
        "INSERT INTO fleur (id_espece_fleur, id_couleur, origine_fleur, prix_vente_fleur) VALUES (?, ?, ?, ?)"
    );
    $idsFleur = array();   // « Rose|Red » -> id_fleur
    foreach (lireCsv('data/csv/plants.csv') as $l) {
        if (preg_match('/^([a-z]+)-(cosmos|hyacinth|lily|mum|pansy|rose|tulip|windflower) plant$/', $l['Name'], $m)) {
            $espece = ucfirst($m[2]);
            $couleur = ucfirst($m[1]);
            $insFleur->execute(array(
                idReference($dbh, 'espece_fleur', $espece),
                idReference($dbh, 'couleur', $couleur),
                $l['Source'] === 'Seed bag' ? 'Graine' : 'Hybride',
                (int) $l['Sell'],
            ));
            $idsFleur["$espece|$couleur"] = (int) $dbh->lastInsertId();
        }
    }
    echo "5. Fleurs importées\n";

    // -----------------------------------------------------------------
    // 6. Génotypes : chaque génotype donne une couleur visible (data/fleurs/genotypes.csv)
    // -----------------------------------------------------------------
    $insGenotype = $dbh->prepare(
        "INSERT INTO genotype (id_fleur, code_genotype, est_graine) VALUES (?, ?, ?)"
    );
    $genotypes = array();   // espèce -> liste de [id_genotype, code, id_fleur]
    foreach (lireCsv('data/fleurs/genotypes.csv') as $l) {
        $cle = $l['espece'] . '|' . $l['couleur'];
        if (!isset($idsFleur[$cle])) {
            throw new Exception("Fleur inconnue dans genotypes.csv : $cle");
        }
        $insGenotype->execute(array($idsFleur[$cle], $l['code_genotype'], (int) $l['est_graine']));
        $genotypes[$l['espece']][] = array((int) $dbh->lastInsertId(), $l['code_genotype'], $idsFleur[$cle]);
    }
    echo "6. Génotypes importés\n";

    // -----------------------------------------------------------------
    // 7. Croisements : pour chaque couple de génotypes d'une même espèce,
    //    probabilité de chaque couleur d'enfant (calcul exact, loi de Mendel)
    // -----------------------------------------------------------------
    $insCroiser = $dbh->prepare(
        "INSERT INTO croiser (id_genotype_parent_1, id_genotype_parent_2, id_fleur_enfant, probabilite)
         VALUES (?, ?, ?, ?)"
    );
    foreach ($genotypes as $espece => $liste) {
        $couleurDe = array();                     // code génotype -> id_fleur (sa couleur)
        foreach ($liste as $g) {
            $couleurDe[$g[1]] = $g[2];
        }
        foreach ($liste as $p1) {
            foreach ($liste as $p2) {
                $enfants = croiserGenotypes($p1[1], $p2[1]);
                $total = pow(4, strlen($p1[1]));    // 64 (3 gènes) ou 256 (roses, 4 gènes)
                if (array_sum($enfants) !== $total) {
                    throw new Exception("Croisement incohérent : {$p1[1]} x {$p2[1]}");
                }
                $parCouleur = array();               // id_fleur enfant -> nombre de chances sur $total
                foreach ($enfants as $code => $chances) {
                    $f = $couleurDe[$code];
                    $parCouleur[$f] = (isset($parCouleur[$f]) ? $parCouleur[$f] : 0) + $chances;
                }
                foreach ($parCouleur as $idFleurEnfant => $chances) {
                    $insCroiser->execute(array($p1[0], $p2[0], $idFleurEnfant, sprintf('%.8f', $chances / $total)));
                }
            }
        }
    }
    echo "7. Croisements calculés\n";

    $dbh->commit();
} catch (Throwable $e) {
    $dbh->rollBack();
    exit("ERREUR, import annulé : " . $e->getMessage() . "\n");
}

// =====================================================================
// Bilan
// =====================================================================
echo "\nImport terminé en " . round(microtime(true) - $debutChrono, 1) . " s\n\n";
$dbh->exec('USE acnh');
$tables = $dbh->query("SELECT table_name AS t FROM information_schema.tables
                       WHERE table_schema = 'acnh' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $n = $dbh->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    printf("  %-15s %6d lignes\n", $t, $n);
}
echo "\nRéférences de meubles de villageois hors périmètre (non importées) : $refsHorsPerimetre\n";
