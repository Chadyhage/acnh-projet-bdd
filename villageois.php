<?php
require_once __DIR__ . '/includes/db.php';

$page_title = 'Villageois';
$active     = 'villageois';

require_url_params(['nom' => '', 'espece' => 'toutes', 'personnalite' => 'toutes', 'genre' => 'tous']);

try {
    // --- 1. Listes déroulantes (valeurs lues dans la base) ------------------
    $especes = run_query('Liste des espèces',
        "SELECT id_espece, nom_espece\nFROM espece\nORDER BY nom_espece");
    $personnalites = run_query('Liste des personnalités',
        "SELECT id_personnalite, nom_personnalite\nFROM personnalite\nORDER BY nom_personnalite");

    // --- 2. Paramètres GET ---------------------------------------------------
    $nom          = is_string($_GET['nom'] ?? null) ? trim($_GET['nom']) : '';
    $espece       = get_int('espece', 1, PHP_INT_MAX, null);
    $personnalite = get_int('personnalite', 1, PHP_INT_MAX, null);
    $genre        = get_choice('genre', ['Male', 'Female'], '');

    // --- 3. Construction de la requête : un AND par filtre rempli -----------
    $where  = ['1 = 1'];
    $params = [];
    if ($nom !== '') {
        $where[] = "v.nom_villageois LIKE CONCAT('%', :nom, '%')";
        $params['nom'] = $nom;
    }
    if ($espece !== null) {
        $where[] = 'v.id_espece = :espece';
        $params['espece'] = $espece;
    }
    if ($personnalite !== null) {
        $where[] = 'v.id_personnalite = :personnalite';
        $params['personnalite'] = $personnalite;
    }
    if ($genre !== '') {
        $where[] = 'v.genre = :genre';
        $params['genre'] = $genre;
    }

    $sql = "SELECT v.id_villageois, v.nom_villageois, es.nom_espece,
       pe.nom_personnalite, v.genre,
       v.jour_anniversaire, v.mois_anniversaire, v.phrase_fetiche
FROM villageois v
JOIN espece es       ON es.id_espece       = v.id_espece
JOIN personnalite pe ON pe.id_personnalite = v.id_personnalite
WHERE " . implode("\n  AND ", $where) . "
ORDER BY v.nom_villageois";

    // --- 4. Exécution --------------------------------------------------------
    $rows  = run_query('Villageois filtrés', $sql, $params);
    $error = null;
} catch (PDOException $ex) {
    $especes = $personnalites = $rows = [];
    $nom = ''; $espece = $personnalite = null; $genre = '';
    $error = $ex->getMessage();
}

$noms_mois = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.',
              'août', 'sept.', 'oct.', 'nov.', 'déc.'];

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Villageois</h1>
    <p>Consultez la liste complète des villageois ou combinez les filtres.</p>
</div>

<form class="filters" method="get">
    <label>Nom
        <input type="text" name="nom" value="<?= e($nom) ?>" placeholder="ex. Bob">
    </label>
    <label>Espèce
        <select name="espece">
            <option value="toutes">Toutes</option>
            <?php foreach ($especes as $s): ?>
                <option value="<?= $s['id_espece'] ?>" <?= $espece === (int) $s['id_espece'] ? 'selected' : '' ?>><?= e($s['nom_espece']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Personnalité
        <select name="personnalite">
            <option value="toutes">Toutes</option>
            <?php foreach ($personnalites as $p): ?>
                <option value="<?= $p['id_personnalite'] ?>" <?= $personnalite === (int) $p['id_personnalite'] ? 'selected' : '' ?>><?= e($p['nom_personnalite']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Genre
        <select name="genre">
            <option value="tous">Tous</option>
            <option value="Male" <?= $genre === 'Male' ? 'selected' : '' ?>>Masculin</option>
            <option value="Female" <?= $genre === 'Female' ? 'selected' : '' ?>>Féminin</option>
        </select>
    </label>
    <div class="actions">
        <button type="submit">Rechercher</button>
        <a class="btn-ghost" href="<?= e(URL_VILLAGEOIS) ?>">Réinitialiser</a>
    </div>
</form>

<?php if ($error): ?>
    <p class="alert">Erreur SQL : <?= e($error) ?></p>
<?php else: ?>
    <p class="count"><strong><?= count($rows) ?></strong> villageois</p>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Nom</th><th>Espèce</th><th>Personnalité</th><th>Genre</th><th>Anniversaire</th><th>Phrase fétiche</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="strong"><?= e($r['nom_villageois']) ?></td>
                    <td><?= e($r['nom_espece']) ?></td>
                    <td><?= e($r['nom_personnalite']) ?></td>
                    <td><?= $r['genre'] === 'Male' ? 'Masculin' : 'Féminin' ?></td>
                    <td><?= (int) $r['jour_anniversaire'] ?> <?= $noms_mois[(int) $r['mois_anniversaire']] ?></td>
                    <td class="muted">« <?= e($r['phrase_fetiche']) ?> »</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="empty">Aucun villageois ne correspond.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
