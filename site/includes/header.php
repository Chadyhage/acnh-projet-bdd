<?php
/**
 * En-tête commun : <head>, bandeau et menu.
 * Variables attendues : $titre (titre de la page), $pageActive (entrée du menu à surligner).
 */
$menu = array(
    'accueil'    => array('index.php', 'Accueil'),
    'creatures'  => array('creatures.php', 'Poissons et insectes'),
    'meubles'    => array('meubles.php', 'Meubles'),
    'villageois' => array('villageois.php', 'Villageois'),
    'fleurs'     => array('fleurs.php', 'Croisement de fleurs'),
);
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre) ?> – Base Animal Crossing</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" href="assets/feuille.svg" type="image/svg+xml">
</head>
<body>
<header class="bandeau">
    <div class="bandeau-interieur">
        <a class="marque" href="index.php">
            <img src="assets/feuille.svg" alt="" width="28" height="28">
            <span>Base Animal Crossing <small>New Horizons</small></span>
        </a>
        <nav class="menu" aria-label="Menu principal">
            <?php foreach ($menu as $cle => $entree): ?>
                <a href="<?= e($entree[0]) ?>"<?= $cle === $pageActive ? ' aria-current="page"' : '' ?>><?= e($entree[1]) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main class="contenu">
