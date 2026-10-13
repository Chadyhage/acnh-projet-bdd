<?php
/**
 * Fonctions communes à toutes les pages du site.
 * Rôle principal : lire les paramètres GET de façon sûre, exécuter les requêtes
 * préparées et mettre en forme les données (dates, heures, prix, images).
 */

// Toutes les requêtes exécutées par la page (affichées en bas : « Requêtes SQL de cette page »)
$GLOBALS['requetes_sql'] = array();

// Images : le Google Sheet donne un nom de fichier, l'URL se reconstruit avec le CDN de catalogue.ac
define('CDN_IMAGES', 'https://nh-cdn.catalogue.ac/');

// ---------------------------------------------------------------------
// Sécurité : affichage et lecture des paramètres GET
// ---------------------------------------------------------------------

/** Échappe un texte avant de l'afficher dans le HTML (protection contre l'injection de code). */
function e($texte)
{
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

/** Paramètre GET entier compris entre $min et $max ; sinon la valeur par défaut. */
function getEntier($nom, $min, $max, $defaut)
{
    if (!isset($_GET[$nom]) || !is_string($_GET[$nom]) || !preg_match('/^\d{1,9}$/', $_GET[$nom])) {
        return $defaut;
    }
    $valeur = (int) $_GET[$nom];
    return ($valeur < $min || $valeur > $max) ? $defaut : $valeur;
}

/** Paramètre GET accepté seulement s'il fait partie d'une liste autorisée (liste blanche). */
function getChoix($nom, array $autorises, $defaut)
{
    if (isset($_GET[$nom]) && is_string($_GET[$nom]) && in_array($_GET[$nom], $autorises, true)) {
        return $_GET[$nom];
    }
    return $defaut;
}

/** Texte libre de recherche : espaces retirés, 40 caractères au plus. */
function getTexte($nom)
{
    if (!isset($_GET[$nom]) || !is_string($_GET[$nom])) {
        return '';
    }
    return mb_substr(trim($_GET[$nom]), 0, 40);
}

/** Date AAAA-MM-JJ valide (années 2000 à 2060, celles du Google Sheet) ; sinon la valeur par défaut. */
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

/** Échappe les caractères spéciaux de LIKE pour une recherche « contient ». */
function motifLike($texte)
{
    return '%' . addcslashes($texte, '%_\\') . '%';
}

// ---------------------------------------------------------------------
// Base de données
// ---------------------------------------------------------------------

/**
 * Prépare et exécute une requête avec ses paramètres, puis la garde en mémoire
 * pour l'afficher en bas de page. Renvoie le PDOStatement.
 */
function requete(PDO $dbh, $sql, array $parametres = array())
{
    $stmt = $dbh->prepare($sql);
    $stmt->execute($parametres);
    $GLOBALS['requetes_sql'][] = array('sql' => $sql, 'parametres' => $parametres);
    return $stmt;
}

/**
 * Condition SQL « le créneau de la ligne $d contient l'heure :heure ».
 * Un créneau qui passe minuit (ex. 16 h -> 9 h) a une heure de début plus grande que l'heure de fin.
 */
function sqlCreneauContientHeure($d)
{
    return "(($d.heure_debut < $d.heure_fin AND :heure >= $d.heure_debut AND :heure < $d.heure_fin)
                        OR ($d.heure_debut > $d.heure_fin AND (:heure >= $d.heure_debut OR :heure < $d.heure_fin)))";
}

// ---------------------------------------------------------------------
// Liens et pagination (les filtres restent dans l'URL, en GET)
// ---------------------------------------------------------------------

/** Construit une URL « page.php?a=1&b=2 » en ignorant les paramètres vides. */
function url($page, array $parametres = array())
{
    $parametres = array_filter($parametres, function ($v) {
        return $v !== null && $v !== '' && $v !== false;
    });
    return $page . ($parametres ? '?' . http_build_query($parametres) : '');
}

/** Liens de pagination : « Précédent 1 2 3 … Suivant ». */
function pagination($page, array $filtres, $total, $parPage, $pageActuelle)
{
    $nbPages = (int) ceil($total / $parPage);
    if ($nbPages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pages de résultats">';
    if ($pageActuelle > 1) {
        $html .= '<a href="' . e(url($page, array_merge($filtres, array('page' => $pageActuelle - 1)))) . '">Précédent</a>';
    }
    for ($p = 1; $p <= $nbPages; $p++) {
        if ($p === 1 || $p === $nbPages || abs($p - $pageActuelle) <= 2) {
            if ($p === $pageActuelle) {
                $html .= '<span aria-current="page">' . $p . '</span>';
            } else {
                $html .= '<a href="' . e(url($page, array_merge($filtres, array('page' => $p)))) . '">' . $p . '</a>';
            }
        } elseif (abs($p - $pageActuelle) === 3) {
            $html .= '<span class="ellipse">…</span>';
        }
    }
    if ($pageActuelle < $nbPages) {
        $html .= '<a href="' . e(url($page, array_merge($filtres, array('page' => $pageActuelle + 1)))) . '">Suivant</a>';
    }
    return $html . '</nav>';
}

// ---------------------------------------------------------------------
// Mise en forme
// ---------------------------------------------------------------------

/** 12000 -> « 12 000 » (espace insécable entre les milliers, à la française). */
function nombre($n)
{
    return number_format((float) $n, 0, ',', "\u{00A0}");
}

/** Prix en clochettes, ou « Non vendu » si NULL. */
function prix($n)
{
    return $n === null ? 'Non vendu' : nombre($n) . "\u{00A0}clochettes";
}

/** Noms des mois en français. */
function nomMois($m)
{
    $noms = array(1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
                  'août', 'septembre', 'octobre', 'novembre', 'décembre');
    return $noms[(int) $m];
}

/** « d'octobre », « de mars » (élision devant une voyelle). */
function deMois($m)
{
    $nom = nomMois($m);
    return (in_array($nom[0], array('a', 'o'), true) ? "d'" : 'de ') . $nom;
}

/** Abréviation des mois (calendriers). */
function moisCourt($m)
{
    $noms = array(1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.');
    return $noms[(int) $m];
}

/** 18 -> « 18 h » ; 24 -> « minuit ». */
function heure($h)
{
    $h = (int) $h;
    return ($h === 0 || $h === 24) ? 'minuit' : $h . "\u{00A0}h";
}

/**
 * Couleur du ciel à l'heure $h (0 à 23) pour le ruban des heures :
 * nuit avant 6 h et à partir de 18 h, aube à 6 h, crépuscule à 17 h.
 */
function ciel($h)
{
    $couleurs = array('#172038', '#1a2440', '#1d2847', '#212d50', '#283662', '#34427a',
                      '#f2c38a', '#f3d3a2', '#dfe6dc', '#d3e6eb', '#cbe4ee', '#c5e2f0',
                      '#c2e0f0', '#c5e2f0', '#cbe4ee', '#d3e6eb', '#e2e2d4', '#f0b37e',
                      '#4a3d70', '#3c3566', '#2f2d5a', '#25284d', '#1e2344', '#19203b');
    return $couleurs[(int) $h % 24];
}

/** Créneau [début, fin) -> « 16 h – 9 h », ou « Toute la journée ». */
function creneau($debut, $fin)
{
    if ((int) $debut === 0 && (int) $fin === 24) {
        return 'Toute la journée';
    }
    return heure($debut) . " – " . heure($fin);
}

/** « 2026-12-01 » -> « 1er déc. 2026 ». */
function dateCourte($dateSql)
{
    $d = DateTime::createFromFormat('!Y-m-d', $dateSql);
    $jour = (int) $d->format('j');
    return ($jour === 1 ? '1er' : $jour) . "\u{00A0}" . moisCourt($d->format('n')) . "\u{00A0}" . $d->format('Y');
}

/** « 2026-12-01 » -> « mardi 1er décembre 2026 ». */
function dateLongue(DateTime $d)
{
    $jours = array('dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi');
    $jour = (int) $d->format('j');
    return $jours[(int) $d->format('w')] . ' ' . ($jour === 1 ? '1er' : $jour) . ' ' . nomMois($d->format('n')) . ' ' . $d->format('Y');
}

/** Période « du 1er déc. 2026 au 14 déc. 2026 » (ou « le … » si un seul jour). */
function periodeTexte($debut, $fin)
{
    return $debut === $fin ? 'le ' . dateCourte($debut) : 'du ' . dateCourte($debut) . ' au ' . dateCourte($fin);
}

/**
 * Période vue depuis aujourd'hui (dates « AAAA-MM-JJ ») : « en cours jusqu'au 30 oct. 2026 »
 * si elle a déjà commencé, sinon le texte de periodeTexte().
 */
function periodeDepuisAujourdhui($debut, $fin, $aujourdhui)
{
    if ($debut > $aujourdhui) {
        return periodeTexte($debut, $fin);
    }
    return $fin === $aujourdhui ? "en cours, dernier jour aujourd'hui" : "en cours jusqu'au " . dateCourte($fin);
}

/** Libellé français d'un type d'événement du Google Sheet. */
function typeEvenement($type)
{
    $libelles = array(
        'Calendar season'     => 'Saison',
        'Crafting season'     => 'Saison de bricolage',
        'Blooming season'     => 'Floraison',
        'Zodiac season'       => 'Signe du zodiaque',
        'Basegame event'      => 'Événement du jeu',
        'Special event'       => 'Événement spécial',
        'Nook Shopping event' => 'Nook Shopping',
        'Autre'               => 'Autre',
    );
    return isset($libelles[$type]) ? $libelles[$type] : $type;
}

/** Météo d'apparition en français (la base garde les valeurs du Google Sheet). */
function meteoFr($meteo)
{
    $fr = array('Any weather' => 'Tous les temps', 'Any except rain' => 'Sauf sous la pluie', 'Rain only' => 'Sous la pluie');
    return isset($fr[$meteo]) ? $fr[$meteo] : $meteo;
}

/** Genre d'un villageois en français (la base garde Male / Female). */
function genreFr($genre)
{
    return $genre === 'Female' ? 'Féminin' : ($genre === 'Male' ? 'Masculin' : $genre);
}

/** Libellé de l'hémisphère. */
function hemisphereTexte($h)
{
    return $h === 'SH' ? 'hémisphère Sud' : 'hémisphère Nord';
}

/**
 * Traduction des couleurs (affichage seulement : la base garde les noms du Google Sheet).
 * $feminin = true pour accorder avec « fleur » : une fleur blanche, une fleur noire.
 */
function couleurFr($nom, $feminin = false)
{
    $fr = array('White' => 'blanc', 'Yellow' => 'jaune', 'Red' => 'rouge', 'Pink' => 'rose', 'Orange' => 'orange',
                'Purple' => 'violet', 'Black' => 'noir', 'Blue' => 'bleu', 'Green' => 'vert', 'Gold' => 'doré',
                'Aqua' => 'turquoise', 'Beige' => 'beige', 'Brown' => 'marron', 'Gray' => 'gris', 'Colorful' => 'multicolore');
    $feminins = array('White' => 'blanche', 'Purple' => 'violette', 'Black' => 'noire', 'Blue' => 'bleue',
                      'Green' => 'verte', 'Gold' => 'dorée', 'Gray' => 'grise');
    if ($feminin && isset($feminins[$nom])) {
        return $feminins[$nom];
    }
    return isset($fr[$nom]) ? $fr[$nom] : $nom;
}

/** Nom français des espèces de fleurs (affichage seulement). */
function especeFleurFr($nom)
{
    $fr = array('Rose' => 'rose', 'Tulip' => 'tulipe', 'Pansy' => 'pensée', 'Cosmos' => 'cosmos', 'Lily' => 'lys',
                'Hyacinth' => 'jacinthe', 'Windflower' => 'anémone', 'Mum' => 'chrysanthème');
    return isset($fr[$nom]) ? $fr[$nom] : $nom;
}

/** Couleur d'affichage (pastille) d'un nom de couleur. */
function couleurHex($nom)
{
    $hex = array('White' => '#f4f4ef', 'Yellow' => '#f2c94c', 'Red' => '#d64541', 'Pink' => '#f2a1bd', 'Orange' => '#ef8d32',
                 'Purple' => '#8e63c9', 'Black' => '#2f2f35', 'Blue' => '#4c7fd9', 'Green' => '#58a55c', 'Gold' => '#c9a227',
                 'Aqua' => '#45c1c4', 'Beige' => '#e3cfa6', 'Brown' => '#8a5a3b', 'Gray' => '#9aa0a6',
                 'Colorful' => 'conic-gradient(#d64541, #f2c94c, #58a55c, #4c7fd9, #8e63c9, #d64541)');
    return isset($hex[$nom]) ? $hex[$nom] : '#cccccc';
}

/** Pastille de couleur + nom. */
function pastille($nom, $avecTexte = true)
{
    return '<span class="pastille"><span class="rond" style="background:' . e(couleurHex($nom)) . '"></span>'
        . ($avecTexte ? e($nom) : '') . '</span>';
}

// ---------------------------------------------------------------------
// Images (URL reconstruite à partir du nom de fichier stocké en base)
// ---------------------------------------------------------------------

function imageCreature($fichier)
{
    return CDN_IMAGES . 'MenuIcon/' . rawurlencode($fichier) . '.png';
}

function imageMeuble($fichier)
{
    return CDN_IMAGES . 'FtrIcon/' . rawurlencode($fichier) . '.png';
}

function imageVillageois($fichier)
{
    return CDN_IMAGES . 'NpcIcon/' . rawurlencode($fichier) . '.png';
}

/** Balise <img> avec chargement différé et texte alternatif. */
function img($url, $alt, $taille = 64)
{
    return '<img src="' . e($url) . '" alt="' . e($alt) . '" width="' . (int) $taille . '" height="' . (int) $taille
        . '" loading="lazy" decoding="async">';
}

// ---------------------------------------------------------------------
// Bloc « Requêtes SQL de cette page »
// ---------------------------------------------------------------------

/** Affiche les requêtes exécutées et leurs paramètres (sert à la démonstration). */
function afficherRequetes()
{
    $requetes = $GLOBALS['requetes_sql'];
    if (!$requetes) {
        return;
    }
    echo '<details class="sql"><summary>Requêtes SQL de cette page (' . count($requetes) . ')</summary>';
    foreach ($requetes as $i => $r) {
        // Retire l'indentation commune pour un affichage propre
        $lignes = explode("\n", rtrim($r['sql']));
        $marge = null;
        foreach (array_slice($lignes, 1) as $l) {
            if (trim($l) !== '') {
                $m = strlen($l) - strlen(ltrim($l));
                $marge = $marge === null ? $m : min($marge, $m);
            }
        }
        foreach ($lignes as $k => $l) {
            if ($k > 0 && $marge) {
                $lignes[$k] = substr($l, $marge);
            }
        }
        echo '<div class="requete"><p class="num">Requête ' . ($i + 1) . '</p><pre><code>' . e(implode("\n", $lignes)) . '</code></pre>';
        if ($r['parametres']) {
            echo '<p class="params">Paramètres : ';
            $morceaux = array();
            foreach ($r['parametres'] as $nom => $valeur) {
                $morceaux[] = '<code>' . e($nom) . '</code> = <code>' . e(var_export($valeur, true)) . '</code>';
            }
            echo implode(', ', $morceaux) . '</p>';
        }
        echo '</div>';
    }
    echo '</details>';
}
