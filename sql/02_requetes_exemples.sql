-- =====================================================================
-- Requêtes du site, avec des valeurs d'exemple (à exécuter dans phpMyAdmin,
-- onglet SQL, après l'import de la base)
--
-- Dans le PHP, chaque valeur écrite en dur ici ('NH', 10, 18…) est un
-- paramètre de requête préparée PDO (:hemisphere, :mois, :heure…) rempli
-- avec la valeur reçue dans l'URL (GET), après vérification.
-- Exemple : creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18
-- =====================================================================
USE acnh;

-- ---------------------------------------------------------------------
-- 1. Poissons et insectes disponibles à un instant T
--    Page : creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18
--    Une ligne de DISPONIBILITE = un créneau [heure_debut ; heure_fin[ d'un mois.
--    Un créneau qui passe minuit (ex. 16 h -> 9 h) a heure_debut > heure_fin.
-- ---------------------------------------------------------------------
SELECT c.nom_creature, c.type_creature, l.nom_lieu, l.zone_lieu, c.meteo, c.prix_vente_creature
FROM creature c
JOIN lieu l ON l.id_lieu = c.id_lieu
WHERE EXISTS (SELECT 1 FROM disponibilite d
              WHERE d.id_creature = c.id_creature
                AND d.hemisphere = 'NH'
                AND d.mois = 10
                AND (   (d.heure_debut < d.heure_fin AND 18 >= d.heure_debut AND 18 < d.heure_fin)
                     OR (d.heure_debut > d.heure_fin AND (18 >= d.heure_debut OR 18 < d.heure_fin))))
  AND c.type_creature = 'poisson'
ORDER BY c.prix_vente_creature DESC, c.nom_creature;

-- ---------------------------------------------------------------------
-- 2. Résumé « Où chercher » de la même page : nombre de créatures et meilleur
--    prix par zone (jointure + filtres + GROUP BY + ORDER BY).
--    Page : creatures.php?hemisphere=NH&mois=10&heure=18&meteo=pluie
--    Le filtre météo « il pleut » retire les insectes qui fuient la pluie.
-- ---------------------------------------------------------------------
SELECT l.zone_lieu, c.type_creature,
       COUNT(*) AS nb,
       MAX(c.prix_vente_creature) AS meilleur_prix
FROM creature c
JOIN lieu l ON l.id_lieu = c.id_lieu
WHERE EXISTS (SELECT 1 FROM disponibilite d
              WHERE d.id_creature = c.id_creature
                AND d.hemisphere = 'NH'
                AND d.mois = 10
                AND (   (d.heure_debut < d.heure_fin AND 18 >= d.heure_debut AND 18 < d.heure_fin)
                     OR (d.heure_debut > d.heure_fin AND (18 >= d.heure_debut OR 18 < d.heure_fin))))
  AND c.meteo <> 'Any except rain'
GROUP BY l.zone_lieu, c.type_creature
ORDER BY nb DESC, meilleur_prix DESC, l.zone_lieu, c.type_creature;

-- ---------------------------------------------------------------------
-- 3. Dernier mois pour attraper une créature : présente en octobre,
--    absente en novembre (page d'accueil et case « Dernier mois seulement »)
-- ---------------------------------------------------------------------
SELECT c.nom_creature, c.type_creature, c.prix_vente_creature
FROM creature c
WHERE EXISTS     (SELECT 1 FROM disponibilite d
                  WHERE d.id_creature = c.id_creature AND d.hemisphere = 'NH' AND d.mois = 10)
  AND NOT EXISTS (SELECT 1 FROM disponibilite d
                  WHERE d.id_creature = c.id_creature AND d.hemisphere = 'NH' AND d.mois = 11)
ORDER BY c.prix_vente_creature DESC, c.nom_creature;

-- ---------------------------------------------------------------------
-- 4. Meubles de saison disponibles sur une période (dates réelles par année)
--    Page : meubles.php?du=2026-12-01&au=2026-12-31&hemisphere=NH
--    Une période chevauche [du ; au] si elle commence avant « au »
--    et finit après « du ».
-- ---------------------------------------------------------------------
SELECT e.nom_evenement, e.type_evenement, p.date_debut, p.date_fin,
       m.nom_meuble, cat.nom_categorie, m.prix_achat
FROM periode p
JOIN evenement e   ON e.id_evenement = p.id_evenement
JOIN proposer pr   ON pr.id_evenement = e.id_evenement
JOIN meuble m      ON m.id_meuble = pr.id_meuble
JOIN categorie cat ON cat.id_categorie = m.id_categorie
WHERE p.hemisphere_periode = 'NH'
  AND p.date_debut <= '2026-12-31'
  AND p.date_fin >= '2026-12-01'
ORDER BY p.date_debut, e.nom_evenement, m.nom_meuble;

-- ---------------------------------------------------------------------
-- 5. Meubles d'une saison / d'un événement, et ses deux prochaines dates
--    Page : meubles.php?evenement=<id de Halloween>&hemisphere=NH
-- ---------------------------------------------------------------------
SELECT m.nom_meuble, cat.nom_categorie, m.prix_achat, m.bricolable
FROM meuble m
JOIN categorie cat ON cat.id_categorie = m.id_categorie
JOIN proposer pr   ON pr.id_meuble = m.id_meuble
JOIN evenement e   ON e.id_evenement = pr.id_evenement
WHERE e.nom_evenement = 'Halloween'
ORDER BY m.nom_meuble;

SELECT p.date_debut, p.date_fin
FROM periode p
JOIN evenement e ON e.id_evenement = p.id_evenement
WHERE e.nom_evenement = 'Halloween'
  AND p.hemisphere_periode = 'NH'
  AND p.date_fin >= '2026-10-12'
ORDER BY p.date_debut
LIMIT 2;

-- ---------------------------------------------------------------------
-- 6. Catalogue : meubles ayant au moins une variante bleue, fabricables,
--    avec leur nombre de variantes (GROUP BY)
--    Page : meubles.php?vue=catalogue&couleur=<id de Blue>&bricolable=1&tri=variantes
-- ---------------------------------------------------------------------
SELECT m.nom_meuble, cat.nom_categorie, m.prix_achat, COUNT(v.id_variante) AS nb_variantes
FROM meuble m
JOIN categorie cat ON cat.id_categorie = m.id_categorie
JOIN variante v    ON v.id_meuble = m.id_meuble
WHERE m.bricolable = 1
  AND EXISTS (SELECT 1 FROM variante vc
              JOIN colorer co ON co.id_variante = vc.id_variante
              JOIN couleur cl ON cl.id_couleur = co.id_couleur
              WHERE vc.id_meuble = m.id_meuble AND cl.nom_couleur = 'Blue')
GROUP BY m.id_meuble, m.nom_meuble, cat.nom_categorie, m.prix_achat
ORDER BY nb_variantes DESC, m.nom_meuble;

-- ---------------------------------------------------------------------
-- 7. Villageois par caractéristiques : chats, style « Cute », féminins
--    Page : villageois.php?espece=<id de Cat>&style=<id de Cute>&genre=Female
--    Le PHP n'ajoute une condition que pour les filtres remplis.
-- ---------------------------------------------------------------------
SELECT v.nom_villageois, es.nom_espece, pe.nom_personnalite,
       v.jour_anniversaire, v.mois_anniversaire
FROM villageois v
JOIN espece es       ON es.id_espece = v.id_espece
JOIN personnalite pe ON pe.id_personnalite = v.id_personnalite
WHERE es.nom_espece = 'Cat'
  AND v.genre = 'Female'
  AND EXISTS (SELECT 1 FROM aimer a JOIN style s ON s.id_style = a.id_style
              WHERE a.id_villageois = v.id_villageois AND s.nom_style = 'Cute')
ORDER BY v.nom_villageois;

-- ---------------------------------------------------------------------
-- 8. Croisement de fleurs : rose rouge x rose jaune, génotypes inconnus
--    Page : fleurs.php?espece=<id de Rose>&couleur1=<id de Red>&couleur2=<id de Yellow>
--
--    Étape 1 : nombre de génotypes possibles pour chaque parent
--    (résultat : Red 13, Yellow 18)
-- ---------------------------------------------------------------------
SELECT c.nom_couleur, COUNT(*) AS nb_genotypes
FROM genotype g
JOIN fleur f        ON f.id_fleur = g.id_fleur
JOIN espece_fleur e ON e.id_espece_fleur = f.id_espece_fleur
JOIN couleur c      ON c.id_couleur = f.id_couleur
WHERE e.nom_espece_fleur = 'Rose'
  AND c.nom_couleur IN ('Red', 'Yellow')
GROUP BY c.nom_couleur;

--    Étape 2 : la requête de fleurs.php, présentée dans le dossier (partie 6).
--    Pour chaque couleur d'enfant, moyenne des probabilités sur les
--    13 x 18 = 234 couples de génotypes (hypothèse : tous les génotypes
--    compatibles ont la même chance). Paramètres remplacés par leur valeur :
--    :fleur1 = 36 (rose rouge), :fleur2 = 52 (rose jaune), :nb1 = 13, :nb2 = 18.
--    Résultat : Yellow 0,2818 ; Pink 0,1947 ; Red 0,1904 ; Orange 0,1605 ;
--    White 0,1485 ; Purple 0,0192 ; Black 0,0037 ; Blue 0,0011.
SELECT co.nom_couleur AS couleur_enfant,
       fe.origine_fleur,
       SUM(cr.probabilite) / (13 * 18) AS probabilite
FROM croiser cr
JOIN genotype p1 ON p1.id_genotype = cr.id_genotype_parent_1
JOIN genotype p2 ON p2.id_genotype = cr.id_genotype_parent_2
JOIN fleur fe    ON fe.id_fleur = cr.id_fleur_enfant
JOIN couleur co  ON co.id_couleur = fe.id_couleur
WHERE p1.id_fleur = 36
  AND p2.id_fleur = 52
GROUP BY co.nom_couleur, fe.origine_fleur
ORDER BY probabilite DESC, co.nom_couleur;

--    Même calcul, en retrouvant les deux fleurs par leur nom au lieu de leur
--    identifiant (plus lisible dans phpMyAdmin, mêmes résultats, en %).
SELECT co.nom_couleur AS couleur_enfant,
       fe.origine_fleur,
       ROUND(100 * SUM(cr.probabilite) / (13 * 18), 2) AS pourcentage
FROM croiser cr
JOIN genotype p1    ON p1.id_genotype = cr.id_genotype_parent_1
JOIN genotype p2    ON p2.id_genotype = cr.id_genotype_parent_2
JOIN fleur f1       ON f1.id_fleur = p1.id_fleur
JOIN fleur f2       ON f2.id_fleur = p2.id_fleur
JOIN espece_fleur e ON e.id_espece_fleur = f1.id_espece_fleur
JOIN couleur c1     ON c1.id_couleur = f1.id_couleur
JOIN couleur c2     ON c2.id_couleur = f2.id_couleur
JOIN fleur fe       ON fe.id_fleur = cr.id_fleur_enfant
JOIN couleur co     ON co.id_couleur = fe.id_couleur
WHERE e.nom_espece_fleur = 'Rose'
  AND c1.nom_couleur = 'Red'
  AND c2.nom_couleur = 'Yellow'
GROUP BY co.nom_couleur, fe.origine_fleur
ORDER BY pourcentage DESC, couleur_enfant;

--    Même croisement avec des fleurs achetées en graines (génotype connu,
--    un seul génotype par parent : résultat exact, 50 % orange / 50 % jaune)
SELECT co.nom_couleur AS couleur_enfant,
       ROUND(100 * SUM(cr.probabilite), 2) AS pourcentage
FROM croiser cr
JOIN genotype p1    ON p1.id_genotype = cr.id_genotype_parent_1
JOIN genotype p2    ON p2.id_genotype = cr.id_genotype_parent_2
JOIN fleur f1       ON f1.id_fleur = p1.id_fleur
JOIN fleur f2       ON f2.id_fleur = p2.id_fleur
JOIN espece_fleur e ON e.id_espece_fleur = f1.id_espece_fleur
JOIN couleur c1     ON c1.id_couleur = f1.id_couleur
JOIN couleur c2     ON c2.id_couleur = f2.id_couleur
JOIN fleur fe       ON fe.id_fleur = cr.id_fleur_enfant
JOIN couleur co     ON co.id_couleur = fe.id_couleur
WHERE e.nom_espece_fleur = 'Rose'
  AND c1.nom_couleur = 'Red'    AND p1.est_graine = 1
  AND c2.nom_couleur = 'Yellow' AND p2.est_graine = 1
GROUP BY co.nom_couleur
ORDER BY pourcentage DESC, couleur_enfant;

-- ---------------------------------------------------------------------
-- 9. Contrôles d'intégrité (doivent tous renvoyer 0)
-- ---------------------------------------------------------------------
-- Pour chaque couple de génotypes, les probabilités des enfants font 1
SELECT COUNT(*) AS couples_dont_la_somme_ne_fait_pas_1
FROM (SELECT id_genotype_parent_1, id_genotype_parent_2, SUM(probabilite) AS total
      FROM croiser
      GROUP BY id_genotype_parent_1, id_genotype_parent_2
      HAVING ABS(total - 1) > 0.000001) t;

-- Chaque créature est disponible au moins un mois dans chaque hémisphère
SELECT COUNT(*) AS creatures_sans_disponibilite
FROM creature c
WHERE NOT EXISTS (SELECT 1 FROM disponibilite d WHERE d.id_creature = c.id_creature AND d.hemisphere = 'NH')
   OR NOT EXISTS (SELECT 1 FROM disponibilite d WHERE d.id_creature = c.id_creature AND d.hemisphere = 'SH');

-- Chaque meuble a au moins une variante
SELECT COUNT(*) AS meubles_sans_variante
FROM meuble m
WHERE NOT EXISTS (SELECT 1 FROM variante v WHERE v.id_meuble = m.id_meuble);
