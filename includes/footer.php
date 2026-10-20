<?php if (!empty($GLOBALS['executed_queries'])): ?>
<section class="sql-panel" id="sql">
    <div class="sql-head">
        <h2>Requêtes SQL exécutées</h2>
        <p>Action utilisateur → paramètres GET → requête SQL → résultat affiché</p>
    </div>
    <div class="sql-url">
        <span>URL reçue</span>
        <code><?= e(basename($_SERVER['PHP_SELF'])) ?><?= ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . e($_SERVER['QUERY_STRING']) : '' ?></code>
    </div>
    <?php foreach ($GLOBALS['executed_queries'] as $q): ?>
        <article class="sql-card">
            <header>
                <strong><?= e($q['label']) ?></strong>
                <span class="sql-meta"><?= $q['count'] ?> ligne(s) · <?= $q['ms'] ?> ms</span>
            </header>
            <pre><?= highlight_sql($q['sql']) ?></pre>
            <?php if ($q['params']): ?>
                <table class="params">
                    <thead><tr><th>Paramètre</th><th>Valeur liée</th></tr></thead>
                    <tbody>
                    <?php foreach ($q['params'] as $k => $v): ?>
                        <tr><td><code>:<?= e(ltrim($k, ':')) ?></code></td><td><code><?= e(var_export($v, true)) ?></code></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-param">Aucun paramètre : requête fixe.</p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
</main>
<footer class="foot wrap">Projet transversal SQL · EPSI B1 · PHP + MySQL</footer>
</body>
</html>
