<?php
// Connexion à la base de données (incluse au début de chaque page avec require_once)
// WampServer : utilisateur root, mot de passe vide.
try {
    $dbh = new PDO('mysql:host=localhost;dbname=acnh_site;charset=utf8mb4', 'root', '');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// Heure de Paris, pour le bouton « Maintenant » de la page des créatures
date_default_timezone_set('Europe/Paris');
