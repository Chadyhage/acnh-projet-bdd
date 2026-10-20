<?php
require_once __DIR__ . '/includes/db.php';

$page_title = 'Accueil';
$active     = 'index';

$cards = [
    [url_creatures_maintenant(), 'Poissons & insectes', 'Ce qui peut être attrapé selon l\'hémisphère, le mois et l\'heure.', 'creature · lieu · disponibilite'],
    [URL_MEUBLES, 'Meubles de saison', 'Meubles proposés pendant une saison ou un événement (Halloween, Toy Day…), par catégorie.', 'meuble · categorie · saison · proposer'],
    [URL_VILLAGEOIS, 'Villageois', 'Consultation et recherche par nom, espèce, personnalité et genre.', 'villageois · espece · personnalite'],
];

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Animal Crossing : New Horizons — base de données</h1>
    <p>Chaque page exécute ses recherches en SQL côté serveur, à partir des paramètres GET de l'URL. La requête exécutée est affichée en bas de chaque page.</p>
</div>

<div class="home-grid">
    <?php foreach ($cards as [$href, $title, $desc, $tables]): ?>
        <a class="home-card" href="<?= e($href) ?>">
            <h2><?= e($title) ?></h2>
            <p><?= e($desc) ?></p>
            <span class="home-tables"><?= e($tables) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
