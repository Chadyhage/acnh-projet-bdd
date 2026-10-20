<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $titre_page; ?> · ACNH Data</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" href="index.php">ACNH<span>Data</span></a>
        <nav>
            <a href="index.php" class="<?php if ($page_active == 'index') { echo 'active'; } ?>">Accueil</a>
            <a href="creatures.php?type=tous&hemisphere=NH&mois=<?php echo date('n'); ?>&heure=<?php echo date('G'); ?>" class="<?php if ($page_active == 'creatures') { echo 'active'; } ?>">Poissons &amp; insectes</a>
            <a href="meubles.php?saison=toutes&categorie=toutes" class="<?php if ($page_active == 'meubles') { echo 'active'; } ?>">Meubles de saison</a>
            <a href="villageois.php?nom=&espece=toutes&personnalite=toutes&genre=tous" class="<?php if ($page_active == 'villageois') { echo 'active'; } ?>">Villageois</a>
        </nav>
    </div>
</header>
<main class="wrap">
