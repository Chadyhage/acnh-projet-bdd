<?php
require_once __DIR__ . '/includes/db.php';

$page_title = 'Meubles de saison';
$active     = 'meubles';

// Les 4 catégories du périmètre (valeurs fixes de la table categorie).
$categories = [1 => 'Housewares', 2 => 'Miscellaneous', 3 => 'Wall-mounted', 4 => 'Ceiling Decor'];

// --- 1. Paramètres GET : obligatoires dans l'URL ----------------------------
require_url_params(['saison' => 'toutes', 'categorie' => 'toutes']);

try {
    // --- 2. Liste des saisons (menu déroulant), lue dans la base ------------
    $saisons = run_query('Liste des saisons',
        "SELECT id_saison, nom_saison\nFROM saison\nORDER BY nom_saison");

    $saison    = get_int('saison', 1, PHP_INT_MAX, null);   // null = toutes
    $categorie = get_int('categorie', 1, 4, null);          // null = toutes

    // --- 3. Construction de la requête : un AND par filtre rempli -----------
    $where  = ['1 = 1'];
    $params = [];
    if ($saison !== null) {
        $where[] = 's.id_saison = :saison';
        $params['saison'] = $saison;
    }
    if ($categorie !== null) {
        $where[] = 'm.id_categorie = :categorie';
        $params['categorie'] = $categorie;
    }

    // proposer est la table associative : elle relie chaque meuble à ses saisons.
    $sql = "SELECT m.id_meuble, m.nom_meuble, cat.nom_categorie, s.nom_saison,
       m.prix_achat, m.prix_vente_meuble
FROM meuble m
JOIN categorie cat ON cat.id_categorie = m.id_categorie
JOIN proposer p    ON p.id_meuble      = m.id_meuble
JOIN saison s      ON s.id_saison      = p.id_saison
WHERE " . implode("\n  AND ", $where) . "
ORDER BY s.nom_saison, cat.nom_categorie, m.nom_meuble";

    // --- 4. Exécution --------------------------------------------------------
    $rows  = run_query('Meubles de la saison choisie', $sql, $params);
    $error = null;
} catch (PDOException $ex) {
    $saisons = $rows = [];
    $saison = $categorie = null;
    $error = $ex->getMessage();
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Meubles de saison</h1>
    <p>Choisissez une saison ou un événement du jeu : la base renvoie les meubles proposés à ce moment-là.</p>
</div>

<form class="filters" method="get">
    <label>Saison
        <select name="saison">
            <option value="toutes">Toutes</option>
            <?php foreach ($saisons as $s): ?>
                <option value="<?= $s['id_saison'] ?>" <?= $saison === (int) $s['id_saison'] ? 'selected' : '' ?>><?= e($s['nom_saison']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Catégorie
        <select name="categorie">
            <option value="toutes">Toutes</option>
            <?php foreach ($categories as $id => $nom): ?>
                <option value="<?= $id ?>" <?= $categorie === $id ? 'selected' : '' ?>><?= e($nom) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="actions">
        <button type="submit">Rechercher</button>
        <a class="btn-ghost" href="<?= e(URL_MEUBLES) ?>">Réinitialiser</a>
    </div>
</form>

<?php if ($error): ?>
    <p class="alert">Erreur SQL : <?= e($error) ?></p>
<?php else: ?>
    <p class="count"><strong><?= count($rows) ?></strong> résultat(s)</p>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Nom</th><th>Saison</th><th>Catégorie</th><th class="num">Prix d'achat</th><th class="num">Prix de vente</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="strong"><?= e(ucfirst($r['nom_meuble'])) ?></td>
                    <td><?= e($r['nom_saison']) ?></td>
                    <td><?= e($r['nom_categorie']) ?></td>
                    <td class="num"><?= $r['prix_achat'] === null ? '<span class="muted">Non vendu</span>' : number_format($r['prix_achat'], 0, ',', ' ') ?></td>
                    <td class="num"><?= number_format($r['prix_vente_meuble'], 0, ',', ' ') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="empty">Aucun meuble pour cette saison dans cette catégorie.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
