<?php
/**
 * Haut de chaque page : titre et menu.
 * Variables attendues : $titre (titre de la page) et $pageActive (entrée du menu en gras).
 */
$menu = array(
    'accueil'    => array('index.php', 'Accueil'),
    'creatures'  => array('creatures.php', 'Poissons et insectes'),
    'meubles'    => array('meubles.php', 'Meubles de saison'),
    'villageois' => array('villageois.php', 'Villageois'),
);
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre) ?> - Base Animal Crossing</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <p class="marque">Base Animal Crossing : New Horizons</p>
    <nav>
        <?php foreach ($menu as $cle => $lien): ?>
            <a href="<?= $lien[0] ?>"<?= $cle === $pageActive ? ' class="actif"' : '' ?>><?= $lien[1] ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<main>
