<?php
/**
 * Fonctions communes aux pages du site :
 *  - lire les paramètres GET de l'URL en les vérifiant ;
 *  - exécuter les requêtes préparées (PDO) ;
 *  - afficher en bas de page les requêtes exécutées et leurs paramètres.
 */

// Requêtes exécutées par la page, affichées en bas (« Requêtes SQL de cette page »)
$GLOBALS['requetes_sql'] = array();

/** Protège un texte avant de l'afficher dans le HTML (htmlspecialchars). */
function e($texte)
{
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

/** Paramètre GET : nombre entier compris entre $min et $max, sinon la valeur par défaut. */
function getEntier($nom, $min, $max, $defaut)
{
    if (!isset($_GET[$nom]) || !is_string($_GET[$nom]) || !preg_match('/^\d{1,9}$/', $_GET[$nom])) {
        return $defaut;
    }
    $valeur = (int) $_GET[$nom];
    return ($valeur < $min || $valeur > $max) ? $defaut : $valeur;
}

/** Paramètre GET : accepté seulement s'il fait partie de la liste autorisée, sinon la valeur par défaut. */
function getChoix($nom, array $autorises, $defaut)
{
    if (isset($_GET[$nom]) && is_string($_GET[$nom]) && in_array($_GET[$nom], $autorises, true)) {
        return $_GET[$nom];
    }
    return $defaut;
}

/** Paramètre GET : date AAAA-MM-JJ valide (années 2000 à 2060, celles du Google Sheet), sinon la valeur par défaut. */
function getDateParam($nom, $defaut)
{
    if (!isset($_GET[$nom]) || !is_string($_GET[$nom])) {
        return $defaut;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $_GET[$nom]);
    if ($d === false || $d->format('Y-m-d') !== $_GET[$nom] || (int) $d->format('Y') < 2000 || (int) $d->format('Y') > 2060) {
        return $defaut;
    }
    return $_GET[$nom];
}

/**
 * Exécute une requête préparée : PDO remplace chaque paramètre (:mois, :heure…) par sa valeur.
 * La requête est aussi gardée pour être affichée en bas de page.
 */
function requete(PDO $dbh, $sql, array $parametres = array())
{
    $stmt = $dbh->prepare($sql);
    $stmt->execute($parametres);
    $GLOBALS['requetes_sql'][] = array('sql' => $sql, 'parametres' => $parametres);
    return $stmt;
}

/** 15000 -> « 15 000 ». */
function nombre($n)
{
    return number_format((float) $n, 0, ',', "\u{00A0}");
}

/** 12 -> « décembre ». */
function nomMois($m)
{
    $noms = array(1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
                  'août', 'septembre', 'octobre', 'novembre', 'décembre');
    return $noms[(int) $m];
}

/** « 2026-12-01 » -> « 01/12/2026 ». */
function dateFr($dateSql)
{
    return DateTime::createFromFormat('Y-m-d', $dateSql)->format('d/m/Y');
}

/** Affiche les requêtes exécutées par la page et la valeur de leurs paramètres. */
function afficherRequetes()
{
    $requetes = $GLOBALS['requetes_sql'];
    if (!$requetes) {
        return;
    }
    echo '<details class="sql"><summary>Requêtes SQL de cette page (' . count($requetes) . ')</summary>';
    foreach ($requetes as $i => $r) {
        echo '<p><strong>Requête ' . ($i + 1) . '</strong></p><pre>' . e($r['sql']) . '</pre>';
        if ($r['parametres']) {
            $morceaux = array();
            foreach ($r['parametres'] as $nom => $valeur) {
                $morceaux[] = '<code>' . e($nom) . '</code> = <code>' . e(var_export($valeur, true)) . '</code>';
            }
            echo '<p>Paramètres : ' . implode(', ', $morceaux) . '</p>';
        }
    }
    echo '</details>';
}
