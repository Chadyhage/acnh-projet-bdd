<?php
/**
 * Connexion à la base « acnh ».
 * Comme dans le cours, la connexion est rangée dans la variable $dbh
 * et ce fichier est inclus en haut de chaque page (require 'config.php').
 *
 * Paramètres Wamp par défaut : MySQL sur localhost:3306, utilisateur root, mot de passe vide.
 */
$hote        = getenv('DB_HOST') ?: 'localhost';
$port        = getenv('DB_PORT') ?: '3306';
$base        = 'acnh';
$utilisateur = getenv('DB_USER') ?: 'root';
$motDePasse  = getenv('DB_PASS') ?: '';

// Heure de l'île = heure de Paris (sert à « maintenant » sur les pages)
date_default_timezone_set('Europe/Paris');

try {
    $dbh = new PDO("mysql:host=$hote;port=$port;dbname=$base;charset=utf8mb4", $utilisateur, $motDePasse, array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // une erreur SQL lève une exception
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // résultats en tableaux associatifs
        PDO::ATTR_EMULATE_PREPARES   => true,                     // autorise un même paramètre nommé plusieurs fois
    ));
} catch (PDOException $e) {
    http_response_code(500);
    exit('<!doctype html><meta charset="utf-8"><title>Base indisponible</title>'
        . '<div style="font-family:sans-serif;margin:2rem;max-width:44rem;line-height:1.5">'
        . '<p><strong>Connexion à la base « acnh » impossible.</strong></p>'
        . '<p>Vérifiez que Wamp est démarré (icône verte), puis importez <code>bdd/acnh_complet.sql.zip</code> '
        . 'dans phpMyAdmin en choisissant le serveur <strong>MySQL</strong> (port ' . htmlspecialchars($port) . '). '
        . 'Le fichier README.md du projet explique chaque étape.</p>'
        . '<p style="color:#555">Message de MySQL : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></div>');
}
