-- =====================================================================
--  Projet transversal SQL — Animal Crossing : New Horizons (EPSI B1)
--  Étape 3 de la migration : tables temporaires (copie brute des CSV) -> 10 tables finales
-- =====================================================================
-- Ce script est lancé par migration/import.php, juste après le chargement des CSV.
-- À ce moment, la base « acnh_site » contient :
--   - les 10 tables finales, vides (créées par sql/01_creation_tables.sql) ;
--   - une table temporaire par fichier CSV, copie exacte du fichier :
--       tmp_fish, tmp_insects, tmp_housewares, tmp_miscellaneous, tmp_wall_mounted,
--       tmp_ceiling_decor, tmp_seasons_and_events, tmp_villagers
--     (mêmes colonnes que le CSV, tout en texte, + num_ligne = numéro de la ligne dans le fichier).
--
-- Principe : pour chaque table finale, une requête INSERT INTO … SELECT qui
--   - choisit les colonnes utiles (les autres colonnes du Sheet sont ignorées) ;
--   - convertit les valeurs :
--       CAST(Sell AS UNSIGNED)          texte -> nombre            '80' -> 80
--       DIY = 'Yes'                     Yes/No -> 1/0
--       IF(Buy = 'NFS', NULL, …)        « pas de valeur » -> NULL
--       JOIN lieu l ON l.nom_lieu = …   nom -> id (clé étrangère)
--   - trie les lignes (ORDER BY) : les id (AUTO_INCREMENT) suivent l'ordre du Google Sheet.
-- Toutes les tables tmp_ sont supprimées à la fin (partie 5).
-- =====================================================================


-- ---------------------------------------------------------------------
-- 0. Deux petites tables d'aide
-- ---------------------------------------------------------------------

-- tmp_nombres : les nombres 1 à 10, pour découper une cellule qui contient plusieurs valeurs.
-- Exemple : la 2e valeur de la cellule « A; B » (2 valeurs = nombre de « ; » + 1)
--   SUBSTRING_INDEX('A; B', ';', 2)      -> 'A; B'   (tout ce qui est avant le 2e « ; »)
--   SUBSTRING_INDEX('A; B', ';', -1)     -> ' B'     (ce qui est après le dernier « ; »)
--   TRIM(' B')                           -> 'B'
-- En joignant chaque ligne aux nombres 1, 2… (JOIN tmp_nombres), on obtient une ligne par valeur.
CREATE TABLE tmp_nombres (n INT PRIMARY KEY);
INSERT INTO tmp_nombres VALUES (1), (2), (3), (4), (5), (6), (7), (8), (9), (10);

-- tmp_heures : heure écrite dans le Google Sheet -> heure de 0 à 23
CREATE TABLE tmp_heures (texte VARCHAR(5) PRIMARY KEY, heure INT NOT NULL);
INSERT INTO tmp_heures VALUES
    ('12 AM', 0),  ('1 AM', 1),   ('2 AM', 2),   ('3 AM', 3),   ('4 AM', 4),   ('5 AM', 5),
    ('6 AM', 6),   ('7 AM', 7),   ('8 AM', 8),   ('9 AM', 9),   ('10 AM', 10), ('11 AM', 11),
    ('12 PM', 12), ('1 PM', 13),  ('2 PM', 14),  ('3 PM', 15),  ('4 PM', 16),  ('5 PM', 17),
    ('6 PM', 18),  ('7 PM', 19),  ('8 PM', 20),  ('9 PM', 21),  ('10 PM', 22), ('11 PM', 23);


-- =====================================================================
-- 1. POISSONS ET INSECTES : tmp_fish, tmp_insects -> lieu, creature, disponibilite
-- =====================================================================

-- 1.a Les deux fichiers dans une seule table de travail, avec seulement les colonnes utiles.
--     Les insectes n'ont ni ombre ni difficulté (NULL) ; les poissons n'ont pas de colonne
--     Weather : ils sortent par tous les temps (« Any weather »).
--     ordre = 1, 2, 3… : les poissons puis les insectes, dans l'ordre des fichiers.
CREATE TABLE tmp_creatures (ordre INT AUTO_INCREMENT PRIMARY KEY)
SELECT 1 AS fichier, num_ligne, 'poisson' AS type_creature, `#`, Name, Sell, `Where/How`,
       Shadow, `Catch Difficulty`, 'Any weather' AS Weather, `Total Catches to Unlock`,
       Description, `Catch phrase`, `Icon Filename`,
       `NH Jan`, `NH Feb`, `NH Mar`, `NH Apr`, `NH May`, `NH Jun`,
       `NH Jul`, `NH Aug`, `NH Sep`, `NH Oct`, `NH Nov`, `NH Dec`,
       `SH Jan`, `SH Feb`, `SH Mar`, `SH Apr`, `SH May`, `SH Jun`,
       `SH Jul`, `SH Aug`, `SH Sep`, `SH Oct`, `SH Nov`, `SH Dec`
FROM tmp_fish
UNION ALL
SELECT 2, num_ligne, 'insecte', `#`, Name, Sell, `Where/How`,
       NULL, NULL, Weather, `Total Catches to Unlock`,
       Description, `Catch phrase`, `Icon Filename`,
       `NH Jan`, `NH Feb`, `NH Mar`, `NH Apr`, `NH May`, `NH Jun`,
       `NH Jul`, `NH Aug`, `NH Sep`, `NH Oct`, `NH Nov`, `NH Dec`,
       `SH Jan`, `SH Feb`, `SH Mar`, `SH Apr`, `SH May`, `SH Jun`,
       `SH Jul`, `SH Aug`, `SH Sep`, `SH Oct`, `SH Nov`, `SH Dec`
FROM tmp_insects
ORDER BY fichier, num_ligne;

-- 1.b Le cœlacanthe vit en « Sea (rainy days) » : on sépare le lieu (Sea) de la météo (Rain only).
UPDATE tmp_creatures SET `Where/How` = 'Sea', Weather = 'Rain only' WHERE `Where/How` = 'Sea (rainy days)';

-- 1.c lieu : chaque lieu une seule fois, numéroté dans l'ordre d'apparition (MIN(ordre)),
--     avec une zone qui regroupe les lieux proches (gardée, non affichée).
INSERT INTO lieu (nom_lieu, zone_lieu)
SELECT `Where/How`,
       CASE
           WHEN `Where/How` LIKE 'Sea%' OR `Where/How` LIKE 'Pier%'              THEN 'Mer'
           WHEN `Where/How` LIKE 'River%'                                        THEN 'Rivière'
           WHEN `Where/How` LIKE 'Pond%'                                         THEN 'Étang'
           WHEN `Where/How` LIKE '%flowers%'                                     THEN 'Fleurs'
           WHEN `Where/How` LIKE '%trees%' OR `Where/How` LIKE '%stumps%'        THEN 'Arbres'
           WHEN `Where/How` LIKE '%water%' OR `Where/How` LIKE '%rivers/ponds%'  THEN 'Eau'
           WHEN `Where/How` LIKE 'Flying%'                                       THEN 'Air'
           WHEN `Where/How` LIKE '%rocks%'                                       THEN 'Rochers'
           WHEN `Where/How` LIKE '%shoreline%' OR `Where/How` LIKE '%beach%'     THEN 'Plage'
           WHEN `Where/How` LIKE '%ground%' OR `Where/How` LIKE '%snowballs%'
                OR `Where/How` LIKE '%turnips%'                                  THEN 'Sol'
           ELSE 'Autre'
       END
FROM tmp_creatures
GROUP BY `Where/How`
ORDER BY MIN(ordre);

-- 1.d creature : une ligne par poisson ou insecte ; le nom du lieu est remplacé par son id.
INSERT INTO creature (id_lieu, type_creature, numero_encyclopedie, nom_creature, prix_vente_creature,
                      ombre, difficulte, meteo, captures_requises, description, phrase_capture,
                      nom_fichier_creature)
SELECT l.id_lieu, t.type_creature, CAST(t.`#` AS UNSIGNED), t.Name, CAST(t.Sell AS UNSIGNED),
       t.Shadow, t.`Catch Difficulty`, t.Weather, CAST(t.`Total Catches to Unlock` AS UNSIGNED),
       t.Description, t.`Catch phrase`, t.`Icon Filename`
FROM tmp_creatures t
JOIN lieu l ON l.nom_lieu = t.`Where/How`
ORDER BY t.ordre;

-- 1.e Les 24 colonnes de mois (NH Jan … SH Dec) deviennent des lignes :
--     une ligne par créature, hémisphère et mois, avec le texte de la cellule (« 4 PM – 9 AM »).
CREATE TABLE tmp_cellules (type_creature VARCHAR(10), nom_creature VARCHAR(50),
                           hemisphere CHAR(2), mois INT, cellule VARCHAR(50));
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  1, `NH Jan` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  2, `NH Feb` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  3, `NH Mar` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  4, `NH Apr` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  5, `NH May` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  6, `NH Jun` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  7, `NH Jul` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  8, `NH Aug` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH',  9, `NH Sep` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH', 10, `NH Oct` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH', 11, `NH Nov` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'NH', 12, `NH Dec` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  1, `SH Jan` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  2, `SH Feb` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  3, `SH Mar` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  4, `SH Apr` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  5, `SH May` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  6, `SH Jun` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  7, `SH Jul` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  8, `SH Aug` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH',  9, `SH Sep` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH', 10, `SH Oct` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH', 11, `SH Nov` FROM tmp_creatures;
INSERT INTO tmp_cellules SELECT type_creature, Name, 'SH', 12, `SH Dec` FROM tmp_creatures;
-- 1.f Nettoyage des cellules
DELETE FROM tmp_cellules WHERE cellule = 'NA';                                         -- NA = absente ce mois-là
UPDATE tmp_cellules SET cellule = REPLACE(cellule, CHAR(0xC2A0 USING utf8mb4), ' ');   -- espaces insécables du Sheet
UPDATE tmp_cellules SET cellule = '12 AM – 12 AM' WHERE cellule = 'All day';           -- toute la journée = minuit -> minuit

-- 1.g Une cellule peut contenir deux créneaux (« 9 AM – 4 PM; 9 PM – 4 AM ») : une ligne par créneau.
CREATE TABLE tmp_creneaux AS
SELECT c.type_creature, c.nom_creature, c.hemisphere, c.mois, n.n AS num_creneau,
       TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.cellule, ';', n.n), ';', -1)) AS creneau
FROM tmp_cellules c
JOIN tmp_nombres n ON n.n <= LENGTH(c.cellule) - LENGTH(REPLACE(c.cellule, ';', '')) + 1;

-- 1.h disponibilite : « 4 PM – 9 AM » -> heure_debut 16, heure_fin 9 (grâce à tmp_heures).
--     Une fin à minuit (0) est notée 24 : le créneau va jusqu'au bout de la journée.
--     (LEFT JOIN : une heure illisible donnerait NULL, que la table refuse : l'import s'arrêterait.)
INSERT INTO disponibilite (id_creature, hemisphere, mois, heure_debut, heure_fin)
SELECT cr.id_creature, t.hemisphere, t.mois, debut.heure, IF(fin.heure = 0, 24, fin.heure)
FROM tmp_creneaux t
JOIN creature cr ON cr.type_creature = t.type_creature AND cr.nom_creature = t.nom_creature
LEFT JOIN tmp_heures debut ON debut.texte = TRIM(SUBSTRING_INDEX(t.creneau, '–', 1))
LEFT JOIN tmp_heures fin   ON fin.texte   = TRIM(SUBSTRING_INDEX(t.creneau, '–', -1))
ORDER BY cr.id_creature, t.hemisphere, t.mois, t.num_creneau;


-- =====================================================================
-- 2. MEUBLES : tmp_housewares, tmp_miscellaneous, tmp_wall_mounted, tmp_ceiling_decor
--    -> categorie, meuble
-- Dans le Google Sheet, une ligne = une VARIANTE (un même meuble existe en plusieurs couleurs
-- ou motifs) : on garde une seule ligne par meuble.
-- =====================================================================

-- 2.a Les 4 onglets dans une seule table de travail, avec seulement les colonnes utiles.
--     ordre = 1, 2, 3… dans l'ordre des onglets puis des lignes.
CREATE TABLE tmp_variantes (ordre INT AUTO_INCREMENT PRIMARY KEY)
SELECT 1 AS onglet, 'Housewares' AS categorie, num_ligne,
       CAST(`Internal ID` AS UNSIGNED) AS id_interne, Name, Buy, Sell, DIY, `Body Customize`, Size,
       Outdoor, Tag, `HHA Series`, Catalog, `Season/Event`, `HHA Base Points`, `Version Added`
FROM tmp_housewares
UNION ALL
SELECT 2, 'Miscellaneous', num_ligne,
       CAST(`Internal ID` AS UNSIGNED), Name, Buy, Sell, DIY, `Body Customize`, Size,
       Outdoor, Tag, `HHA Series`, Catalog, `Season/Event`, `HHA Base Points`, `Version Added`
FROM tmp_miscellaneous
UNION ALL
SELECT 3, 'Wall-mounted', num_ligne,
       CAST(`Internal ID` AS UNSIGNED), Name, Buy, Sell, DIY, `Body Customize`, Size,
       Outdoor, Tag, `HHA Series`, Catalog, `Season/Event`, `HHA Base Points`, `Version Added`
FROM tmp_wall_mounted
UNION ALL
SELECT 4, 'Ceiling Decor', num_ligne,
       CAST(`Internal ID` AS UNSIGNED), Name, Buy, Sell, DIY, `Body Customize`, Size,
       Outdoor, Tag, `HHA Series`, Catalog, `Season/Event`, `HHA Base Points`, `Version Added`
FROM tmp_ceiling_decor
ORDER BY onglet, num_ligne;

-- 2.b tmp_meubles : une ligne par meuble = la première variante de chaque Internal ID.
--     (Prix, taille, saisons… sont identiques sur toutes les variantes d'un meuble.)
CREATE TABLE tmp_meubles AS
SELECT * FROM tmp_variantes
WHERE ordre IN (SELECT MIN(ordre) FROM tmp_variantes GROUP BY id_interne);

-- 2.c categorie : une catégorie par onglet du Sheet
INSERT INTO categorie (nom_categorie) VALUES ('Housewares'), ('Miscellaneous'), ('Wall-mounted'), ('Ceiling Decor');

-- 2.d meuble : une ligne par meuble, valeurs converties ; la catégorie est remplacée par son id.
INSERT INTO meuble (id_categorie, nom_meuble, prix_achat, prix_vente_meuble, bricolable, personnalisable,
                    taille, exterieur, etiquette, serie, catalogue, points_hha, version_ajout_meuble, id_interne)
SELECT c.id_categorie,
       t.Name,
       IF(t.Buy = 'NFS', NULL, CAST(t.Buy AS UNSIGNED)),                       -- NFS (pas en vente) -> NULL
       CAST(t.Sell AS UNSIGNED),
       t.DIY = 'Yes',                                                           -- Yes -> 1, No -> 0
       t.`Body Customize` = 'Yes',
       t.Size,
       t.Outdoor = 'Yes',
       t.Tag,
       NULLIF(t.`HHA Series`, 'None'),                                          -- None -> NULL
       t.Catalog,
       CAST(t.`HHA Base Points` AS UNSIGNED),
       t.`Version Added`,
       t.id_interne
FROM tmp_meubles t
JOIN categorie c ON c.nom_categorie = t.categorie
ORDER BY t.ordre;


-- =====================================================================
-- 3. SAISONS : colonne Season/Event des meubles + onglet Seasons and Events
--    -> saison, proposer
-- =====================================================================

-- 3.a Colonne Season/Event : « Bunny Day (days before); Bunny Day »
--     -> une ligne par saison (NA = meuble sans saison).
CREATE TABLE tmp_saisons_meubles AS
SELECT t.id_interne, t.ordre + n.n / 10 AS position,
       TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(t.`Season/Event`, ';', n.n), ';', -1)) AS nom_saison
FROM tmp_meubles t
JOIN tmp_nombres n ON n.n <= LENGTH(t.`Season/Event`) - LENGTH(REPLACE(t.`Season/Event`, ';', '')) + 1
WHERE t.`Season/Event` <> 'NA';

-- 3.b saison : les saisons de l'onglet Seasons and Events qui proposent au moins un meuble,
--     dans l'ordre de l'onglet, avec leur type ; puis celles citées par des meubles mais absentes
--     de l'onglet (Happy Home Paradise), avec le type « Autre ». (Une seule requête : les id se suivent.)
INSERT INTO saison (nom_saison, type_saison)
SELECT nom_saison, type_saison
FROM (SELECT 1 AS etape, num_ligne AS position, Name AS nom_saison, Type AS type_saison
      FROM tmp_seasons_and_events
      WHERE Name IN (SELECT nom_saison FROM tmp_saisons_meubles)
      UNION ALL
      SELECT 2, MIN(position), nom_saison, 'Autre'
      FROM tmp_saisons_meubles
      WHERE nom_saison NOT IN (SELECT Name FROM tmp_seasons_and_events)
      GROUP BY nom_saison) saisons
ORDER BY etape, position;

-- 3.c proposer : meuble <-> saison (table associative)
INSERT INTO proposer (id_meuble, id_saison)
SELECT m.id_meuble, s.id_saison
FROM tmp_saisons_meubles t
JOIN meuble m ON m.id_interne = t.id_interne
JOIN saison s ON s.nom_saison = t.nom_saison;


-- =====================================================================
-- 4. VILLAGEOIS : tmp_villagers -> espece, personnalite, villageois
-- =====================================================================

-- 4.a Tables de référence : chaque valeur une seule fois, dans l'ordre d'apparition
INSERT INTO espece (nom_espece)
SELECT Species FROM tmp_villagers GROUP BY Species ORDER BY MIN(num_ligne);

INSERT INTO personnalite (nom_personnalite)
SELECT Personality FROM tmp_villagers GROUP BY Personality ORDER BY MIN(num_ligne);

-- 4.b villageois : les noms d'espèce et de personnalité sont remplacés par leur id.
--     Birthday est au format mois/jour : « 7/9 » = 9 juillet.
INSERT INTO villageois (id_espece, id_personnalite, nom_villageois, genre, sous_type,
                        jour_anniversaire, mois_anniversaire, phrase_fetiche, chanson_preferee,
                        citation_preferee, version_ajout_villageois, nom_fichier_villageois)
SELECT e.id_espece, p.id_personnalite, t.Name, t.Gender, t.Subtype,
       CAST(SUBSTRING_INDEX(t.Birthday, '/', -1) AS UNSIGNED),                 -- jour = après le « / »
       CAST(SUBSTRING_INDEX(t.Birthday, '/', 1) AS UNSIGNED),                  -- mois = avant le « / »
       t.Catchphrase, t.`Favorite Song`, t.`Favorite Saying`, t.`Version Added`, t.Filename
FROM tmp_villagers t
JOIN espece e       ON e.nom_espece = t.Species
JOIN personnalite p ON p.nom_personnalite = t.Personality
ORDER BY t.num_ligne;


-- =====================================================================
-- 5. FIN : suppression des tables temporaires (copies des CSV et tables de travail)
-- =====================================================================
DROP TABLE tmp_fish, tmp_insects, tmp_seasons_and_events, tmp_housewares, tmp_miscellaneous,
           tmp_wall_mounted, tmp_ceiling_decor, tmp_villagers,
           tmp_nombres, tmp_heures, tmp_creatures, tmp_cellules, tmp_creneaux,
           tmp_variantes, tmp_meubles, tmp_saisons_meubles;
