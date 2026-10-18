<?php
/**
 * Accueil : « Aujourd'hui sur l'île ».
 * Comme dans le jeu, tout dépend de la date et de l'heure réelles (heure de Paris) :
 * créatures de sortie maintenant, saisons et événements en cours, anniversaires du mois.
 * URL : index.php?hemisphere=SH (hémisphère Nord par défaut)
 */
require 'config.php';
require 'includes/fonctions.php';

$maintenant = new DateTime();
$hemisphere = getChoix('hemisphere', array('NH', 'SH'), 'NH');
$mois = (int) $maintenant->format('n');
$heure = (int) $maintenant->format('G');
$jour = (int) $maintenant->format('j');
$aujourdhui = $maintenant->format('Y-m-d');
$dans30jours = (clone $maintenant)->modify('+30 days')->format('Y-m-d');
$moisSuivant = $mois === 12 ? 1 : $mois + 1;

// 1. Créatures de sortie à cette heure-ci, comptées par type
$parType = requete($dbh,
    "SELECT c.type_creature, COUNT(*) AS nb
     FROM creature c
     WHERE EXISTS (SELECT 1 FROM disponibilite d
                   WHERE d.id_creature = c.id_creature
                     AND d.hemisphere = :hemisphere
                     AND d.mois = :mois
                     AND " . sqlCreneauContientHeure('d') . ")
     GROUP BY c.type_creature",
    array(':hemisphere' => $hemisphere, ':mois' => $mois, ':heure' => $heure))->fetchAll(PDO::FETCH_KEY_PAIR);
$nbPoissons = isset($parType['poisson']) ? (int) $parType['poisson'] : 0;
$nbInsectes = isset($parType['insecte']) ? (int) $parType['insecte'] : 0;

// 2. Dernier mois pour les attraper : présentes ce mois-ci, absentes le mois prochain
$departs = requete($dbh,
    "SELECT c.id_creature, c.nom_creature, c.type_creature, c.prix_vente_creature, c.nom_fichier_creature
     FROM creature c
     WHERE EXISTS (SELECT 1 FROM disponibilite d
                   WHERE d.id_creature = c.id_creature AND d.hemisphere = :hemisphere AND d.mois = :mois)
       AND NOT EXISTS (SELECT 1 FROM disponibilite d
                       WHERE d.id_creature = c.id_creature AND d.hemisphere = :hemisphere AND d.mois = :mois_suivant)
     ORDER BY c.prix_vente_creature DESC, c.nom_creature",
    array(':hemisphere' => $hemisphere, ':mois' => $mois, ':mois_suivant' => $moisSuivant))->fetchAll();

// 3. Saisons et événements en cours aujourd'hui, avec le nombre de meubles proposés
$enCours = requete($dbh,
    "SELECT e.id_evenement, e.nom_evenement, e.type_evenement, p.date_debut, p.date_fin,
            COUNT(pr.id_meuble) AS nb_meubles
     FROM periode p
     JOIN evenement e      ON e.id_evenement = p.id_evenement
     LEFT JOIN proposer pr ON pr.id_evenement = e.id_evenement
     WHERE p.hemisphere_periode = :hemisphere
       AND p.date_debut <= :aujourdhui
       AND p.date_fin >= :aujourdhui
     GROUP BY e.id_evenement, e.nom_evenement, e.type_evenement, p.date_debut, p.date_fin
     ORDER BY nb_meubles DESC, p.date_fin, e.nom_evenement",
    array(':hemisphere' => $hemisphere, ':aujourdhui' => $aujourdhui))->fetchAll();

// 4. Événements avec des meubles qui commencent dans les 30 prochains jours
$bientot = requete($dbh,
    "SELECT e.id_evenement, e.nom_evenement, e.type_evenement, p.date_debut, p.date_fin
     FROM periode p
     JOIN evenement e ON e.id_evenement = p.id_evenement
     WHERE p.hemisphere_periode = :hemisphere
       AND p.date_debut > :aujourdhui
       AND p.date_debut <= :limite
       AND EXISTS (SELECT 1 FROM proposer pr WHERE pr.id_evenement = e.id_evenement)
     ORDER BY p.date_debut, e.nom_evenement",
    array(':hemisphere' => $hemisphere, ':aujourdhui' => $aujourdhui, ':limite' => $dans30jours))->fetchAll();

// 5. Anniversaires du mois (les deux hémisphères fêtent le même jour)
$anniversaires = requete($dbh,
    "SELECT v.id_villageois, v.nom_villageois, v.jour_anniversaire, v.nom_fichier_villageois, es.nom_espece
     FROM villageois v
     JOIN espece es ON es.id_espece = v.id_espece
     WHERE v.mois_anniversaire = :mois
     ORDER BY v.jour_anniversaire, v.nom_villageois",
    array(':mois' => $mois))->fetchAll();
$aVenir = array_values(array_filter($anniversaires, function ($v) use ($jour) {
    return (int) $v['jour_anniversaire'] >= $jour;
}));

// 6. Contenu de la base
$taille = requete($dbh,
    "SELECT (SELECT COUNT(*) FROM villageois) AS villageois,
            (SELECT COUNT(*) FROM creature)   AS creatures,
            (SELECT COUNT(*) FROM meuble)     AS meubles,
            (SELECT COUNT(*) FROM variante)   AS variantes,
            (SELECT COUNT(*) FROM evenement)  AS evenements,
            (SELECT COUNT(*) FROM periode)    AS periodes")->fetch();

$lienCreatures = array('hemisphere' => $hemisphere === 'NH' ? '' : $hemisphere);
$titre = 'Accueil';
$pageActive = 'accueil';
require 'includes/header.php';
?>
<section class="accueil" aria-labelledby="t-accueil">
    <div class="titre-avec-choix">
        <p class="date"><?= ucfirst(dateLongue($maintenant)) ?>, <?= heure($heure) ?></p>
        <p class="bascule">
            <a href="index.php" <?= $hemisphere === 'NH' ? 'aria-current="true"' : '' ?>>Nord</a>
            <a href="index.php?hemisphere=SH" <?= $hemisphere === 'SH' ? 'aria-current="true"' : '' ?>>Sud</a>
        </p>
    </div>
    <h1 id="t-accueil">Aujourd'hui sur l'île</h1>
    <p class="resume">
        Dans l'<?= hemisphereTexte($hemisphere) ?>,
        <a href="<?= e(url('creatures.php', array_merge($lienCreatures, array('type' => 'poisson')))) ?>"><?= $nbPoissons ?> poisson<?= $nbPoissons > 1 ? 's' : '' ?></a>
        et <a href="<?= e(url('creatures.php', array_merge($lienCreatures, array('type' => 'insecte')))) ?>"><?= $nbInsectes ?> insecte<?= $nbInsectes > 1 ? 's' : '' ?></a>
        sont de sortie à cette heure-ci.
    </p>
    <nav class="heures" aria-label="Créatures de sortie à une autre heure">
        <?php for ($h = 0; $h < 24; $h++): ?>
            <a class="heure <?= ($h >= 6 && $h < 18) ? 'jour' : 'nuit' ?>" style="--ciel:<?= ciel($h) ?>"
               href="<?= e(url('creatures.php', array_merge($lienCreatures, array('mois' => $mois, 'heure' => $h)))) ?>"
               <?= $h === $heure ? 'aria-current="true"' : '' ?> title="Créatures à <?= heure($h) ?>"><?= $h ?></a>
        <?php endfor; ?>
    </nav>
</section>

<div class="colonnes">
    <div>
        <section aria-labelledby="t-saisons">
            <h2 id="t-saisons">Saisons et événements en cours</h2>
            <?php if (!$enCours): ?>
                <p class="vide">Aucune saison ni aucun événement aujourd'hui.</p>
            <?php else: ?>
                <ul class="liste-simple">
                    <?php foreach ($enCours as $ev): ?>
                        <li>
                            <?php if ($ev['nb_meubles'] > 0): ?>
                                <a href="<?= e(url('meubles.php', array('evenement' => $ev['id_evenement'], 'hemisphere' => $hemisphere))) ?>"><?= e($ev['nom_evenement']) ?></a>
                            <?php else: ?>
                                <span><?= e($ev['nom_evenement']) ?></span>
                            <?php endif; ?>
                            <span class="discret"><?= e(typeEvenement($ev['type_evenement'])) ?>, jusqu'au <?= dateCourte($ev['date_fin']) ?></span>
                            <span class="badge <?= $ev['nb_meubles'] > 0 ? 'info' : 'neutre' ?>"><?= $ev['nb_meubles'] > 0 ? (int) $ev['nb_meubles'] . ' meuble' . ($ev['nb_meubles'] > 1 ? 's' : '') : 'Pas de meuble' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p><a href="<?= e(url('meubles.php', array('hemisphere' => $hemisphere === 'NH' ? '' : $hemisphere))) ?>">Voir les meubles de saison disponibles aujourd'hui</a></p>

            <?php if ($bientot): ?>
                <h3>Dans les 30 prochains jours</h3>
                <ul class="liste-simple">
                    <?php foreach ($bientot as $ev): ?>
                        <li>
                            <a href="<?= e(url('meubles.php', array('evenement' => $ev['id_evenement'], 'hemisphere' => $hemisphere))) ?>"><?= e($ev['nom_evenement']) ?></a>
                            <span class="discret"><?= e(typeEvenement($ev['type_evenement'])) ?>, <?= periodeTexte($ev['date_debut'], $ev['date_fin']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section aria-labelledby="t-departs">
            <h2 id="t-departs">Dernier mois pour les attraper</h2>
            <?php if (!$departs): ?>
                <p class="vide">Aucune créature ne part à la fin <?= deMois($mois) ?>.</p>
            <?php else: ?>
                <p class="intro">
                    <?= count($departs) ?> créature<?= count($departs) > 1 ? 's sont là' : ' est là' ?> en <?= nomMois($mois) ?>
                    mais plus en <?= nomMois($moisSuivant) ?><?= count($departs) > 8 ? ' ; voici les 8 qui se vendent le plus cher' : '' ?>.
                </p>
                <ul class="liste-creatures">
                    <?php foreach (array_slice($departs, 0, 8) as $c): ?>
                        <li>
                            <?= img(imageCreature($c['nom_fichier_creature']), $c['nom_creature'], 40) ?>
                            <span>
                                <a href="<?= e(url('creature.php', array('id' => $c['id_creature'], 'hemisphere' => $hemisphere))) ?>"><?= e($c['nom_creature']) ?></a>
                                <span class="discret"><?= $c['type_creature'] === 'poisson' ? 'Poisson' : 'Insecte' ?></span>
                            </span>
                            <span class="nombre"><?= prix($c['prix_vente_creature']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><a href="<?= e(url('creatures.php', array_merge($lienCreatures, array('depart' => 1)))) ?>">Celles qu'on peut attraper maintenant</a></p>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section aria-labelledby="t-anniversaires">
            <h2 id="t-anniversaires">Anniversaires <?= deMois($mois) ?></h2>
            <?php if (!$aVenir): ?>
                <p class="vide">Plus d'anniversaire ce mois-ci.</p>
            <?php else: ?>
                <ul class="liste-anniversaires">
                    <?php foreach (array_slice($aVenir, 0, 10) as $v): ?>
                        <li<?= (int) $v['jour_anniversaire'] === $jour ? ' class="aujourdhui"' : '' ?>>
                            <span class="jour"><?= (int) $v['jour_anniversaire'] === $jour ? "Aujourd'hui" : ((int) $v['jour_anniversaire'] === 1 ? '1er' : (int) $v['jour_anniversaire']) . ' ' . moisCourt($mois) ?></span>
                            <a href="<?= e(url('villageois_fiche.php', array('id' => $v['id_villageois']))) ?>">
                                <?= img(imageVillageois($v['nom_fichier_villageois']), '', 40) ?>
                                <span><?= e($v['nom_villageois']) ?></span>
                            </a>
                            <span class="discret"><?= e($v['nom_espece']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p><a href="<?= e(url('villageois.php', array('mois' => $mois, 'tri' => 'anniversaire'))) ?>">Les <?= count($anniversaires) ?> anniversaires <?= deMois($mois) ?></a></p>
        </section>
    </div>
</div>

<p class="contenu-base">
    La base contient <?= nombre($taille['villageois']) ?> villageois, <?= nombre($taille['creatures']) ?> poissons et insectes,
    <?= nombre($taille['meubles']) ?> meubles déclinés en <?= nombre($taille['variantes']) ?> variantes
    et <?= nombre($taille['evenements']) ?> saisons et événements (<?= nombre($taille['periodes']) ?> périodes datées).
</p>

<?php require 'includes/footer.php'; ?>
