<?php
require_once 'config.php';

$titre_page = 'Accueil';
$page_active = 'index';
include 'includes/header.php';
?>
<div class="page-head">
    <h1>Animal Crossing : New Horizons — base de données</h1>
    <p>Chaque page exécute ses recherches en SQL côté serveur, à partir des paramètres GET de l'URL. La requête exécutée est affichée en bas de chaque page.</p>
</div>

<div class="home-grid">
    <a class="home-card" href="creatures.php?type=tous&hemisphere=NH&mois=<?php echo date('n'); ?>&heure=<?php echo date('G'); ?>">
        <h2>Poissons &amp; insectes</h2>
        <p>Ce qui peut être attrapé selon l'hémisphère, le mois et l'heure.</p>
        <span class="home-tables">creature · lieu · disponibilite</span>
    </a>
    <a class="home-card" href="meubles.php?saison=toutes&categorie=toutes">
        <h2>Meubles de saison</h2>
        <p>Meubles proposés pendant une saison ou un événement (Halloween, Toy Day…), par catégorie.</p>
        <span class="home-tables">meuble · categorie · saison · proposer</span>
    </a>
    <a class="home-card" href="villageois.php?nom=&espece=toutes&personnalite=toutes&genre=tous">
        <h2>Villageois</h2>
        <p>Consultation et recherche par nom, espèce, personnalité et genre.</p>
        <span class="home-tables">villageois · espece · personnalite</span>
    </a>
</div>

<?php include 'includes/footer.php'; ?>
