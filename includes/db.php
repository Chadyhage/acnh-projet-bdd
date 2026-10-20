<?php
require_once __DIR__ . '/../config.php';
date_default_timezone_set('Europe/Paris');

/** Connexion PDO unique. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_EMULATE_PREPARES   => false,   // vraies requêtes préparées MySQL
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

/** Requêtes exécutées sur la page, affichées dans le panneau « Requête SQL ». */
$GLOBALS['executed_queries'] = [];

/**
 * Exécute une requête préparée et la garde en mémoire pour l'afficher.
 * $label : nom court affiché au-dessus de la requête.
 */
function run_query(string $label, string $sql, array $params = []): array
{
    $start = microtime(true);
    $stmt  = db()->prepare($sql);
    $stmt->execute($params);
    $rows  = $stmt->fetchAll();
    $GLOBALS['executed_queries'][] = [
        'label'  => $label,
        'sql'    => $sql,
        'params' => $params,
        'count'  => count($rows),
        'ms'     => round((microtime(true) - $start) * 1000, 1),
    ];
    return $rows;
}

/** Échappement HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Lit un paramètre GET autorisé (liste blanche) ou renvoie la valeur par défaut. */
function get_choice(string $key, array $allowed, $default)
{
    $v = $_GET[$key] ?? null;
    return in_array($v, $allowed, true) ? $v : $default;
}

/** Lit un entier GET compris entre $min et $max, sinon la valeur par défaut. */
function get_int(string $key, int $min, int $max, ?int $default): ?int
{
    if (!isset($_GET[$key]) || !is_string($_GET[$key]) || !ctype_digit($_GET[$key])) {
        return $default;
    }
    $v = (int) $_GET[$key];
    return ($v >= $min && $v <= $max) ? $v : $default;
}

/**
 * Oblige la page à recevoir TOUS ses paramètres dans l'URL.
 * S'il en manque un, on redirige vers la même page avec l'URL complète
 * (valeurs par défaut visibles). Ainsi chaque requête SQL est pilotée par l'URL.
 */
function require_url_params(array $defaults): void
{
    $missing = array_diff_key($defaults, $_GET);
    if ($missing) {
        $query = http_build_query(array_merge($defaults, array_intersect_key($_GET, $defaults)));
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?' . $query);
        exit;
    }
}

/** URL complètes par défaut (utilisées par le menu et les boutons). */
function url_creatures_maintenant(): string
{
    return 'creatures.php?' . http_build_query(['type' => 'tous', 'hemisphere' => 'NH', 'mois' => (int) date('n'), 'heure' => (int) date('G')]);
}
const URL_MEUBLES    = 'meubles.php?saison=toutes&categorie=toutes';
const URL_VILLAGEOIS = 'villageois.php?nom=&espece=toutes&personnalite=toutes&genre=tous';

/** Coloration minimale des mots-clés SQL pour l'affichage. */
function highlight_sql(string $sql): string
{
    $html = e($sql);
    $html = preg_replace('/(:[a-z_0-9]+)/i', '<span class="sql-param">$1</span>', $html);
    $kw = 'SELECT|DISTINCT|FROM|JOIN|LEFT JOIN|ON|WHERE|AND|OR|NOT|IN|LIKE|CONCAT|GROUP BY|ORDER BY|DESC|ASC|AS|LIMIT|BETWEEN';
    return preg_replace('/\b(' . $kw . ')\b/', '<span class="sql-kw">$1</span>', $html);
}
