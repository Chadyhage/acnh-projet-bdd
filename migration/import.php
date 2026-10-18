<?php
/**
 * Migration : Google Sheet (fichiers CSV) -> base MySQL « acnh »
 * =====================================================================
 * La migration se fait en 3 étapes :
 *
 *   1. Structure    sql/01_schema.sql     recrée la base vide (21 tables).
 *   2. Chargement   ce script             copie chaque fichier CSV, tel quel, dans une table temporaire :
 *                                         data/csv/fish.csv -> tmp_fish, data/csv/villagers.csv -> tmp_villagers…
 *                                         (une colonne par colonne du fichier, tout en texte,
 *                                         + num_ligne = numéro de la ligne dans le fichier).
 *   3. Migration    sql/02_migration.sql  remplit les tables finales avec INSERT INTO … SELECT … FROM tmp_… :
 *                                         on ne garde que les colonnes utiles, on convertit les valeurs
 *                                         (« NA » -> NULL, « Yes » -> 1, « 4 PM » -> 16…),
 *                                         puis on supprime les tables temporaires.
 *
 * Lancement (WampServer démarré) :
 *   - navigateur : http://localhost/acnh/migration/import.php   (projet copié dans C:\wamp64\www\acnh)
 *   - ou en ligne de commande : php migration/import.php
 * Option : import.php?garder=1 (ou php migration/import.php --garder) laisse les tables
 *          temporaires dans la base, pour les regarder dans phpMyAdmin.
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
$garderTmp = isset($_GET['garder']) || in_array('--garder', isset($argv) ? $argv : array(), true);

try {
    // Pas de nom de base ici : le script commence par (re)créer la base.
    $dbh = new PDO("mysql:host=$hote;port=$port;charset=utf8mb4", $user, $mdp, array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));
} catch (PDOException $e) {
    exit("Connexion impossible : " . $e->getMessage() . "\n");
}
// Les textes écrits dans les requêtes ('NA', 'Yes'…) utilisent la même collation que la base,
// pour pouvoir être comparés aux colonnes sans erreur « Illegal mix of collations ».
$dbh->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
// Mode strict : une valeur impossible à convertir arrête l'import au lieu d'être remplacée en silence par 0.
$dbh->exec("SET SESSION sql_mode = 'TRADITIONAL'");

define('RACINE', dirname(__DIR__));

/** Lit un fichier .sql et renvoie ses requêtes (une requête se termine par « ; » en fin de ligne). */
function requetesDuFichier($chemin)
{
    $sql = file_get_contents(RACINE . '/' . $chemin);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);       // retire les lignes de commentaire
    $sql = preg_replace('/\s--\s.*$/m', '', $sql);       // et les commentaires en fin de ligne
    return array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));
}

/**
 * Copie un fichier CSV, tel quel, dans une table temporaire.
 * Ex. : data/csv/fish.csv -> tmp_fish (num_ligne, `#`, `Name`, `Sell`, `Where/How`…), toutes les colonnes en texte.
 */
function chargerCsv(PDO $dbh, $fichier, $table)
{
    $f = fopen(RACINE . '/data/csv/' . $fichier, 'r');
    if (!$f) {
        throw new Exception("Fichier introuvable : data/csv/$fichier");
    }
    $entetes = fgetcsv($f, 0, ',', '"', '');

    // 1. La table : num_ligne + une colonne TEXT par colonne du CSV (même nom que l'en-tête)
    $colonnes = array('num_ligne INT NOT NULL');
    foreach ($entetes as $entete) {
        $colonnes[] = '`' . $entete . '` TEXT';
    }
    $dbh->exec("DROP TABLE IF EXISTS $table");
    $dbh->exec("CREATE TABLE $table (" . implode(', ', $colonnes) . ")");

    // 2. Les lignes du fichier, sans aucune transformation
    $insertion = $dbh->prepare("INSERT INTO $table VALUES (?" . str_repeat(', ?', count($entetes)) . ")");
    $numLigne = 0;
    $dbh->beginTransaction();
    while (($ligne = fgetcsv($f, 0, ',', '"', '')) !== false) {
        if ($ligne === array(null)) {
            continue;   // ligne vide
        }
        $numLigne++;
        $insertion->execute(array_merge(array($numLigne), array_pad($ligne, count($entetes), '')));
    }
    $dbh->commit();
    fclose($f);
    return $numLigne;
}

$debutChrono = microtime(true);
try {
    // =================================================================
    // 1. Structure : la base est recréée à partir de sql/01_schema.sql
    // =================================================================
    foreach (requetesDuFichier('sql/01_schema.sql') as $requete) {
        $dbh->exec($requete);
    }
    echo "1. Base « acnh » recréée (sql/01_schema.sql)\n\n";

    // =================================================================
    // 2. Chargement : chaque CSV -> une table temporaire tmp_<nom du fichier>
    // =================================================================
    echo "2. Fichiers CSV copiés dans des tables temporaires :\n";
    $fichiers = array('fish', 'insects', 'seasons_and_events', 'housewares',
                      'miscellaneous', 'wall_mounted', 'ceiling_decor', 'villagers');
    foreach ($fichiers as $nom) {
        $n = chargerCsv($dbh, "$nom.csv", "tmp_$nom");
        printf("   %-24s -> %-24s %6d lignes\n", "$nom.csv", "tmp_$nom", $n);
    }

    // =================================================================
    // 3. Migration : tables temporaires -> tables finales (sql/02_migration.sql)
    // =================================================================
    echo "\n3. Tables finales remplies (sql/02_migration.sql)\n";
    foreach (requetesDuFichier('sql/02_migration.sql') as $requete) {
        if ($garderTmp && stripos($requete, 'DROP TABLE tmp_') === 0) {
            echo "   Tables temporaires conservées (option garder).\n";
            continue;
        }
        if (stripos($requete, 'SELECT') === 0) {          // requête de contrôle : on affiche son résultat
            foreach ($dbh->query($requete)->fetchAll() as $resultat) {
                foreach ($resultat as $colonne => $valeur) {
                    echo "   Contrôle - $colonne : $valeur\n";
                }
            }
        } else {
            $dbh->exec($requete);
        }
    }
} catch (Throwable $e) {
    exit("\nERREUR, import arrêté : " . $e->getMessage()
        . "\nRequête en cause :\n" . (isset($requete) ? $requete : '(aucune)') . "\n");
}

// =====================================================================
// Bilan : nombre de lignes de chaque table finale
// =====================================================================
echo "\nImport terminé en " . round(microtime(true) - $debutChrono, 1) . " s\n\n";
$tables = $dbh->query("SELECT table_name AS t FROM information_schema.tables
                       WHERE table_schema = 'acnh' AND table_name NOT LIKE 'tmp\\_%'
                       ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$total = 0;
foreach ($tables as $t) {
    $n = (int) $dbh->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $total += $n;
    printf("  %-16s %6d lignes\n", $t, $n);
}
printf("  %-16s %6d lignes (%d tables)\n", 'TOTAL', $total, count($tables));
if ($garderTmp) {
    $nbTmp = $dbh->query("SELECT COUNT(*) FROM information_schema.tables
                          WHERE table_schema = 'acnh' AND table_name LIKE 'tmp\\_%'")->fetchColumn();
    echo "\n  + $nbTmp tables temporaires tmp_… conservées : relancer import.php sans l'option pour les supprimer.\n";
}
