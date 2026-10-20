<?php
/** @var string $page_title */
/** @var string $active */
$nav = [
    'index'      => ['index.php', 'Accueil'],
    'creatures'  => [e(url_creatures_maintenant()), 'Poissons & insectes'],
    'meubles'    => [e(URL_MEUBLES), 'Meubles de saison'],
    'villageois' => [e(URL_VILLAGEOIS), 'Villageois'],
];
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> · ACNH Data</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" href="index.php">ACNH<span>Data</span></a>
        <nav>
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main class="wrap">
