<?php
/**
 * Accueil : les trois recherches du site. Cette page n'exécute aucune requête.
 */
require 'includes/fonctions.php';

$titre = 'Accueil';
$pageActive = 'accueil';
require 'includes/header.php';
?>
<h1>Base Animal Crossing : New Horizons</h1>
<p>Ce site interroge notre base MySQL, remplie à partir du Google Sheet du jeu.
   Chaque recherche passe par l'URL (paramètres GET) et est exécutée en SQL par le serveur.</p>

<ul class="accueil">
    <li><a href="creatures.php">Poissons et insectes disponibles à un instant T</a> :
        choisir un mois, une heure et un hémisphère.</li>
    <li><a href="meubles.php">Meubles de saison</a> :
        choisir une période (du … au …) et un hémisphère.</li>
    <li><a href="villageois.php">Villageois</a> :
        filtrer par espèce, personnalité, genre et style.</li>
</ul>
<?php require 'includes/footer.php'; ?>
