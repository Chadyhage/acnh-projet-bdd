<?php
require_once __DIR__ . '/includes/db.php';

$page_title = 'Poissons & insectes';
$active     = 'creatures';

// --- 1. Paramètres GET : tous obligatoires dans l'URL ----------------------
require_url_params(['type' => 'tous', 'hemisphere' => 'NH', 'mois' => (int) date('n'), 'heure' => (int) date('G')]);
$type       = get_choice('type', ['tous', 'poisson', 'insecte'], 'tous');
$hemisphere = get_choice('hemisphere', ['NH', 'SH'], 'NH');
$mois       = get_int('mois', 1, 12, (int) date('n'));
$heure      = get_int('heure', 0, 23, (int) date('G'));

// --- 2. Construction de la requête ------------------------------------------
// Une plage horaire peut passer minuit (ex. 16 -> 9) : d'où les deux cas.
$where  = [
    'd.hemisphere = :hemisphere',
    'd.mois = :mois',
    "(\n        (d.heure_debut < d.heure_fin\n           AND :heure1 >= d.heure_debut AND :heure2 < d.heure_fin)\n     OR (d.heure_debut > d.heure_fin\n           AND (:heure3 >= d.heure_debut OR :heure4 < d.heure_fin))\n      )",
];
$params = [
    'hemisphere' => $hemisphere,
    'mois'       => $mois,
    'heure1'     => $heure, 'heure2' => $heure, 'heure3' => $heure, 'heure4' => $heure,
];
if ($type !== 'tous') {
    array_unshift($where, 'c.type_creature = :type');
    $params = ['type' => $type] + $params;
}

$sql = "SELECT c.id_creature, c.nom_creature, c.type_creature,
       l.nom_lieu, c.ombre, c.meteo, c.prix_vente_creature
FROM creature c
JOIN lieu l          ON l.id_lieu = c.id_lieu
JOIN disponibilite d ON d.id_creature = c.id_creature
WHERE " . implode("\n  AND ", $where) . "
GROUP BY c.id_creature, c.nom_creature, c.type_creature,
         l.nom_lieu, c.ombre, c.meteo, c.prix_vente_creature
ORDER BY c.prix_vente_creature DESC, c.nom_creature";

// --- 3. Exécution -----------------------------------------------------------
try {
    $rows  = run_query('Créatures disponibles à cet instant', $sql, $params);
    $error = null;
} catch (PDOException $ex) {
    $rows  = [];
    $error = $ex->getMessage();
}

$noms_mois = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet',
              'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Poissons et insectes disponibles</h1>
    <p>Choisissez un instant : la base calcule ce qui peut être attrapé à ce moment-là.</p>
</div>

<form class="filters" method="get">
    <label>Type
        <select name="type">
            <option value="tous" <?= $type === 'tous' ? 'selected' : '' ?>>Tous</option>
            <option value="poisson" <?= $type === 'poisson' ? 'selected' : '' ?>>Poissons</option>
            <option value="insecte" <?= $type === 'insecte' ? 'selected' : '' ?>>Insectes</option>
        </select>
    </label>
    <label>Hémisphère
        <select name="hemisphere">
            <option value="NH" <?= $hemisphere === 'NH' ? 'selected' : '' ?>>Nord</option>
            <option value="SH" <?= $hemisphere === 'SH' ? 'selected' : '' ?>>Sud</option>
        </select>
    </label>
    <label>Mois
        <select name="mois">
            <?php foreach ($noms_mois as $n => $nom): ?>
                <option value="<?= $n ?>" <?= $mois === $n ? 'selected' : '' ?>><?= $nom ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Heure
        <select name="heure">
            <?php for ($h = 0; $h < 24; $h++): ?>
                <option value="<?= $h ?>" <?= $heure === $h ? 'selected' : '' ?>><?= sprintf('%02dh', $h) ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <div class="actions">
        <button type="submit">Rechercher</button>
        <a class="btn-ghost" href="<?= e(url_creatures_maintenant()) ?>">Maintenant</a>
    </div>
</form>

<?php if ($error): ?>
    <p class="alert">Erreur SQL : <?= e($error) ?></p>
<?php else: ?>
    <p class="count"><strong><?= count($rows) ?></strong> créature(s) · <?= $noms_mois[$mois] ?>, <?= sprintf('%02dh', $heure) ?>, hémisphère <?= $hemisphere === 'NH' ? 'Nord' : 'Sud' ?></p>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Nom</th><th>Type</th><th>Lieu</th><th>Ombre</th><th>Météo</th><th class="num">Prix de vente</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="strong"><?= e(ucfirst($r['nom_creature'])) ?></td>
                    <td><span class="tag tag-<?= e($r['type_creature']) ?>"><?= e($r['type_creature']) ?></span></td>
                    <td><?= e($r['nom_lieu']) ?></td>
                    <td><?= e($r['ombre'] ?? '—') ?></td>
                    <td><?= e($r['meteo']) ?></td>
                    <td class="num"><?= number_format($r['prix_vente_creature'], 0, ',', ' ') ?> clochettes</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="empty">Aucune créature à cet instant.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
