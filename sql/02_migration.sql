-- =====================================================================
-- Projet transversal Animal Crossing : New Horizons — EPSI B1
-- Migration : tables temporaires (copie brute des CSV) -> tables finales
-- =====================================================================
-- Ce script est lancé par migration/import.php, juste après le chargement des CSV.
-- À ce moment, la base « acnh » contient :
--   - les 21 tables finales, vides (créées par sql/01_schema.sql) ;
--   - une table temporaire par fichier CSV, copie exacte du fichier :
--       tmp_fish, tmp_insects, tmp_seasons_and_events, tmp_housewares,
--       tmp_miscellaneous, tmp_wall_mounted, tmp_ceiling_decor, tmp_villagers
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
--
-- Les tables de travail créées en route commencent aussi par tmp_.
-- Toutes les tables tmp_ sont supprimées à la fin (partie 5).
-- =====================================================================


-- ---------------------------------------------------------------------
-- 0. Deux petites tables d'aide
-- ---------------------------------------------------------------------

-- tmp_nombres : les nombres 1 à 30, pour découper une cellule qui contient plusieurs valeurs.
-- Exemple : la 2e valeur de la cellule « A; B; C » (3 valeurs = nombre de « ; » + 1)
--   SUBSTRING_INDEX('A; B; C', ';', 2)   -> 'A; B'   (tout ce qui est avant le 2e « ; »)
--   SUBSTRING_INDEX('A; B', ';', -1)     -> ' B'     (ce qui est après le dernier « ; »)
--   TRIM(' B')                           -> 'B'
-- En joignant chaque ligne aux nombres 1, 2, 3 (JOIN tmp_nombres), on obtient une ligne par valeur.
CREATE TABLE tmp_nombres (n INT PRIMARY KEY);
INSERT INTO tmp_nombres VALUES (1), (2), (3), (4), (5), (6), (7), (8), (9), (10),
    (11), (12), (13), (14), (15), (16), (17), (18), (19), (20),
    (21), (22), (23), (24), (25), (26), (27), (28), (29), (30);

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
--     avec une zone qui regroupe les lieux proches (sert aux filtres du site).
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
--    -> categorie, couleur, meuble, variante, colorer, source, provenir
-- Dans le Google Sheet, une ligne = une VARIANTE (un même meuble existe en plusieurs couleurs
-- ou motifs) : on sépare le meuble (une seule fois) de ses variantes.
-- =====================================================================

-- 2.a Les 4 onglets dans une seule table de travail, avec seulement les 22 colonnes utiles
--     (sur 41 à 45). ordre = 1, 2, 3… dans l'ordre des onglets puis des lignes.
CREATE TABLE tmp_variantes (ordre INT AUTO_INCREMENT PRIMARY KEY)
SELECT 1 AS onglet, 'Housewares' AS categorie, num_ligne,
       CAST(`Internal ID` AS UNSIGNED) AS id_interne, Name, Buy, Sell, DIY, `Body Customize`, Size,
       Outdoor, Tag, `HHA Series`, Catalog, `Season/Event`, `Season/Event Exclusive`, `HHA Base Points`,
       `Version Added`, Source, Variation, Pattern, `Color 1`, `Color 2`, Filename, `Unique Entry ID`
FROM tmp_housewares
UNION ALL
SELECT 2, 'Miscellaneous', num_ligne,
       CAST(`Internal ID` AS UNSIGNED), Name, Buy, Sell, DIY, `Body Customize`, Size,
       Outdoor, Tag, `HHA Series`, Catalog, `Season/Event`, `Season/Event Exclusive`, `HHA Base Points`,
       `Version Added`, Source, Variation, Pattern, `Color 1`, `Color 2`, Filename, `Unique Entry ID`
FROM tmp_miscellaneous
UNION ALL
SELECT 3, 'Wall-mounted', num_ligne,
       CAST(`Internal ID` AS UNSIGNED), Name, Buy, Sell, DIY, `Body Customize`, Size,
       Outdoor, Tag, `HHA Series`, Catalog, `Season/Event`, `Season/Event Exclusive`, `HHA Base Points`,
       `Version Added`, Source, Variation, Pattern, `Color 1`, `Color 2`, Filename, `Unique Entry ID`
FROM tmp_wall_mounted
UNION ALL
SELECT 4, 'Ceiling Decor', num_ligne,
       CAST(`Internal ID` AS UNSIGNED), Name, Buy, Sell, DIY, `Body Customize`, Size,
       Outdoor, Tag, `HHA Series`, Catalog, `Season/Event`, `Season/Event Exclusive`, `HHA Base Points`,
       `Version Added`, Source, Variation, Pattern, `Color 1`, `Color 2`, Filename, `Unique Entry ID`
FROM tmp_ceiling_decor
ORDER BY onglet, num_ligne;

-- 2.b tmp_meubles : une ligne par meuble = la première variante de chaque Internal ID.
--     (Les colonnes du meuble — prix, taille, source… — sont identiques sur toutes ses variantes.)
CREATE TABLE tmp_meubles AS
SELECT * FROM tmp_variantes
WHERE ordre IN (SELECT MIN(ordre) FROM tmp_variantes GROUP BY id_interne);

-- 2.c categorie : une catégorie par onglet du Sheet
INSERT INTO categorie (nom_categorie) VALUES ('Housewares'), ('Miscellaneous'), ('Wall-mounted'), ('Ceiling Decor');

-- 2.d couleur : les couleurs citées par les variantes (Color 1, Color 2) et par les villageois,
--     chacune une seule fois, numérotées dans l'ordre d'apparition.
--     position = numéro de ligne + 0.1 pour Color 1, + 0.2 pour Color 2 (Color 1 passe avant) ;
--     les villageois (+ 100000) passent après les meubles. « None » = variante sans couleur.
INSERT INTO couleur (nom_couleur)
SELECT nom_couleur
FROM (SELECT `Color 1` AS nom_couleur, ordre + 0.1 AS position FROM tmp_variantes
      UNION ALL
      SELECT `Color 2`, ordre + 0.2 FROM tmp_variantes
      UNION ALL
      SELECT `Color 1`, 100000 + num_ligne + 0.1 FROM tmp_villagers
      UNION ALL
      SELECT `Color 2`, 100000 + num_ligne + 0.2 FROM tmp_villagers) couleurs_citees
WHERE nom_couleur <> 'None'
GROUP BY nom_couleur
ORDER BY MIN(position);

-- 2.e meuble : une ligne par meuble, valeurs converties ; la catégorie est remplacée par son id.
INSERT INTO meuble (id_categorie, nom_meuble, prix_achat, prix_vente_meuble, bricolable, personnalisable,
                    taille, exterieur, etiquette, serie, catalogue, exclusif_evenement, points_hha,
                    version_ajout_meuble, id_interne)
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
       CASE t.`Season/Event Exclusive` WHEN 'Yes' THEN 1 WHEN 'No' THEN 0 END,   -- NA -> NULL
       CAST(t.`HHA Base Points` AS UNSIGNED),
       t.`Version Added`,
       t.id_interne
FROM tmp_meubles t
JOIN categorie c ON c.nom_categorie = t.categorie
ORDER BY t.ordre;

-- 2.f variante : toutes les lignes des 4 onglets ; le meuble est retrouvé grâce à son Internal ID.
INSERT INTO variante (id_meuble, nom_variation, nom_motif, nom_fichier_variante, id_unique)
SELECT m.id_meuble,
       IF(t.Variation IN ('NA', 'None'), NULL, t.Variation),                    -- pas de variation -> NULL
       IF(t.Pattern IN ('NA', 'None'), NULL, t.Pattern),                        -- pas de motif -> NULL
       t.Filename,
       t.`Unique Entry ID`
FROM tmp_variantes t
JOIN meuble m ON m.id_interne = t.id_interne
ORDER BY t.ordre;

-- 2.g colorer : la ou les couleurs de chaque variante (Color 1, Color 2).
--     Si Color 1 = Color 2, une seule ligne est créée ; « None » n'est pas une couleur : pas de ligne.
INSERT INTO colorer (id_variante, id_couleur)
SELECT v.id_variante, c.id_couleur
FROM tmp_variantes t
JOIN variante v ON v.id_unique = t.`Unique Entry ID`
JOIN couleur c ON c.nom_couleur = t.`Color 1` OR c.nom_couleur = t.`Color 2`;

-- 2.h Source : « Nook's Cranny; HHP Office » -> une ligne par source (découpage avec tmp_nombres).
--     position = ordre du meuble + n / 10 : la 1re source passe avant la 2e.
CREATE TABLE tmp_sources AS
SELECT t.id_interne, t.ordre + n.n / 10 AS position,
       TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(t.Source, ';', n.n), ';', -1)) AS nom_source
FROM tmp_meubles t
JOIN tmp_nombres n ON n.n <= LENGTH(t.Source) - LENGTH(REPLACE(t.Source, ';', '')) + 1;

-- 2.i source : chaque source une seule fois, dans l'ordre d'apparition
INSERT INTO source (nom_source)
SELECT nom_source FROM tmp_sources GROUP BY nom_source ORDER BY MIN(position);

-- 2.j provenir : meuble <-> source
INSERT INTO provenir (id_meuble, id_source)
SELECT m.id_meuble, s.id_source
FROM tmp_sources t
JOIN meuble m ON m.id_interne = t.id_interne
JOIN source s ON s.nom_source = t.nom_source;


-- =====================================================================
-- 3. SAISONS ET ÉVÉNEMENTS : tmp_seasons_and_events -> evenement, periode, proposer
-- =====================================================================

-- 3.a Colonne Season/Event des meubles : « Bunny Day (days before); Bunny Day »
--     -> une ligne par événement (NA = meuble sans saison ni événement).
CREATE TABLE tmp_evenements_meubles AS
SELECT t.id_interne, t.ordre + n.n / 10 AS position,
       TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(t.`Season/Event`, ';', n.n), ';', -1)) AS nom_evenement
FROM tmp_meubles t
JOIN tmp_nombres n ON n.n <= LENGTH(t.`Season/Event`) - LENGTH(REPLACE(t.`Season/Event`, ';', '')) + 1
WHERE t.`Season/Event` <> 'NA';

-- 3.b evenement : les lignes de l'onglet Seasons and Events, puis les événements cités par des
--     meubles mais absents de l'onglet (Happy Home Paradise), ajoutés avec le type « Autre ».
--     (Une seule requête : les id se suivent sans trou.)
INSERT INTO evenement (nom_evenement, type_evenement, nom_affiche, date_variable)
SELECT nom_evenement, type_evenement, nom_affiche, date_variable
FROM (SELECT 1 AS etape, num_ligne AS position, Name AS nom_evenement, Type AS type_evenement,
             `Display Name` AS nom_affiche, `Date Varies by Year` = 'Yes' AS date_variable
      FROM tmp_seasons_and_events
      UNION ALL
      SELECT 2, MIN(position), nom_evenement, 'Autre', nom_evenement, 0
      FROM tmp_evenements_meubles
      WHERE nom_evenement NOT IN (SELECT Name FROM tmp_seasons_and_events)
      GROUP BY nom_evenement) evenements
ORDER BY etape, position;

-- 3.c Les 122 colonnes « année hémisphère » (2022 NH … 2060 SH) deviennent des lignes :
--     une ligne par événement, année et hémisphère, avec le texte de la cellule (« Feb 25 – May 31 »).
--     num_colonne garde l'ordre des colonnes du fichier.
CREATE TABLE tmp_periodes (nom_evenement VARCHAR(80), num_ligne INT, num_colonne INT,
                           annee INT, hemisphere CHAR(2), cellule VARCHAR(20));
INSERT INTO tmp_periodes SELECT Name, num_ligne,   1, 2022, 'NH', `2022 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,   2, 2022, 'SH', `2022 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,   3, 2023, 'NH', `2023 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,   4, 2023, 'SH', `2023 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,   5, 2024, 'NH', `2024 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,   6, 2024, 'SH', `2024 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,   7, 2000, 'NH', `2000 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,   8, 2000, 'SH', `2000 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,   9, 2001, 'NH', `2001 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  10, 2001, 'SH', `2001 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  11, 2002, 'NH', `2002 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  12, 2002, 'SH', `2002 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  13, 2003, 'NH', `2003 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  14, 2003, 'SH', `2003 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  15, 2004, 'NH', `2004 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  16, 2004, 'SH', `2004 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  17, 2005, 'NH', `2005 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  18, 2005, 'SH', `2005 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  19, 2006, 'NH', `2006 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  20, 2006, 'SH', `2006 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  21, 2007, 'NH', `2007 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  22, 2007, 'SH', `2007 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  23, 2008, 'NH', `2008 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  24, 2008, 'SH', `2008 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  25, 2009, 'NH', `2009 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  26, 2009, 'SH', `2009 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  27, 2010, 'NH', `2010 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  28, 2010, 'SH', `2010 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  29, 2011, 'NH', `2011 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  30, 2011, 'SH', `2011 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  31, 2012, 'NH', `2012 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  32, 2012, 'SH', `2012 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  33, 2013, 'NH', `2013 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  34, 2013, 'SH', `2013 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  35, 2014, 'NH', `2014 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  36, 2014, 'SH', `2014 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  37, 2015, 'NH', `2015 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  38, 2015, 'SH', `2015 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  39, 2016, 'NH', `2016 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  40, 2016, 'SH', `2016 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  41, 2017, 'NH', `2017 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  42, 2017, 'SH', `2017 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  43, 2018, 'NH', `2018 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  44, 2018, 'SH', `2018 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  45, 2019, 'NH', `2019 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  46, 2019, 'SH', `2019 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  47, 2020, 'NH', `2020 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  48, 2020, 'SH', `2020 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  49, 2021, 'NH', `2021 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  50, 2021, 'SH', `2021 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  51, 2025, 'NH', `2025 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  52, 2025, 'SH', `2025 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  53, 2026, 'NH', `2026 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  54, 2026, 'SH', `2026 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  55, 2027, 'NH', `2027 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  56, 2027, 'SH', `2027 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  57, 2028, 'NH', `2028 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  58, 2028, 'SH', `2028 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  59, 2029, 'NH', `2029 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  60, 2029, 'SH', `2029 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  61, 2030, 'NH', `2030 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  62, 2030, 'SH', `2030 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  63, 2031, 'NH', `2031 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  64, 2031, 'SH', `2031 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  65, 2032, 'NH', `2032 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  66, 2032, 'SH', `2032 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  67, 2033, 'NH', `2033 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  68, 2033, 'SH', `2033 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  69, 2034, 'NH', `2034 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  70, 2034, 'SH', `2034 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  71, 2035, 'NH', `2035 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  72, 2035, 'SH', `2035 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  73, 2036, 'NH', `2036 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  74, 2036, 'SH', `2036 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  75, 2037, 'NH', `2037 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  76, 2037, 'SH', `2037 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  77, 2038, 'NH', `2038 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  78, 2038, 'SH', `2038 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  79, 2039, 'NH', `2039 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  80, 2039, 'SH', `2039 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  81, 2040, 'NH', `2040 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  82, 2040, 'SH', `2040 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  83, 2041, 'NH', `2041 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  84, 2041, 'SH', `2041 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  85, 2042, 'NH', `2042 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  86, 2042, 'SH', `2042 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  87, 2043, 'NH', `2043 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  88, 2043, 'SH', `2043 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  89, 2044, 'NH', `2044 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  90, 2044, 'SH', `2044 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  91, 2045, 'NH', `2045 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  92, 2045, 'SH', `2045 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  93, 2046, 'NH', `2046 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  94, 2046, 'SH', `2046 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  95, 2047, 'NH', `2047 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  96, 2047, 'SH', `2047 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  97, 2048, 'NH', `2048 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  98, 2048, 'SH', `2048 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne,  99, 2049, 'NH', `2049 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 100, 2049, 'SH', `2049 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 101, 2050, 'NH', `2050 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 102, 2050, 'SH', `2050 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 103, 2051, 'NH', `2051 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 104, 2051, 'SH', `2051 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 105, 2052, 'NH', `2052 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 106, 2052, 'SH', `2052 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 107, 2053, 'NH', `2053 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 108, 2053, 'SH', `2053 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 109, 2054, 'NH', `2054 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 110, 2054, 'SH', `2054 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 111, 2055, 'NH', `2055 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 112, 2055, 'SH', `2055 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 113, 2056, 'NH', `2056 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 114, 2056, 'SH', `2056 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 115, 2057, 'NH', `2057 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 116, 2057, 'SH', `2057 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 117, 2058, 'NH', `2058 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 118, 2058, 'SH', `2058 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 119, 2059, 'NH', `2059 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 120, 2059, 'SH', `2059 SH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 121, 2060, 'NH', `2060 NH` FROM tmp_seasons_and_events;
INSERT INTO tmp_periodes SELECT Name, num_ligne, 122, 2060, 'SH', `2060 SH` FROM tmp_seasons_and_events;
DELETE FROM tmp_periodes WHERE cellule = 'NA';                     -- NA = pas de date cette année-là

-- 3.d Début et fin : « Feb 25 – May 31 » -> « Feb 25 » et « May 31 » ;
--     « Apr 11 » (un seul jour, sans « – ») -> début = fin = « Apr 11 ».
ALTER TABLE tmp_periodes ADD debut VARCHAR(10), ADD fin VARCHAR(10), ADD annee_fin INT;
UPDATE tmp_periodes
SET debut = TRIM(SUBSTRING_INDEX(cellule, '–', 1)),
    fin = TRIM(SUBSTRING_INDEX(cellule, '–', -1)),
    annee_fin = annee;

-- 3.e Une période qui passe le 31 décembre (« Dec 1 – Feb 29 ») se termine l'année suivante.
--     Pour comparer le début et la fin, on les place dans une même année bissextile (2000).
UPDATE tmp_periodes
SET annee_fin = annee + 1
WHERE STR_TO_DATE(CONCAT(fin, ' 2000'), '%b %e %Y') < STR_TO_DATE(CONCAT(debut, ' 2000'), '%b %e %Y');

-- 3.f Le 29 février (toujours en fin de période dans le Sheet) n'existe que les années bissextiles
--     (de 2000 à 2061 : les années divisibles par 4). Les autres années, la période finit le 28.
UPDATE tmp_periodes SET fin = 'Feb 28' WHERE fin = 'Feb 29' AND annee_fin % 4 <> 0;

-- 3.g periode : texte + année -> vraie date : STR_TO_DATE('Feb 25 2026', '%b %e %Y') = 2026-02-25.
INSERT INTO periode (id_evenement, annee, hemisphere_periode, date_debut, date_fin)
SELECT e.id_evenement, t.annee, t.hemisphere,
       STR_TO_DATE(CONCAT(t.debut, ' ', t.annee), '%b %e %Y'),
       STR_TO_DATE(CONCAT(t.fin, ' ', t.annee_fin), '%b %e %Y')
FROM tmp_periodes t
JOIN evenement e ON e.nom_evenement = t.nom_evenement
ORDER BY t.num_ligne, t.num_colonne;

-- 3.h proposer : meuble <-> saison/événement
INSERT INTO proposer (id_meuble, id_evenement)
SELECT m.id_meuble, e.id_evenement
FROM tmp_evenements_meubles t
JOIN meuble m ON m.id_interne = t.id_interne
JOIN evenement e ON e.nom_evenement = t.nom_evenement;


-- =====================================================================
-- 4. VILLAGEOIS : tmp_villagers
--    -> espece, personnalite, loisir, style, villageois, aimer, preferer, meubler
-- =====================================================================

-- 4.a Tables de référence : chaque valeur une seule fois, dans l'ordre d'apparition
INSERT INTO espece (nom_espece)
SELECT Species FROM tmp_villagers GROUP BY Species ORDER BY MIN(num_ligne);

INSERT INTO personnalite (nom_personnalite)
SELECT Personality FROM tmp_villagers GROUP BY Personality ORDER BY MIN(num_ligne);

INSERT INTO loisir (nom_loisir)
SELECT Hobby FROM tmp_villagers GROUP BY Hobby ORDER BY MIN(num_ligne);

-- style : deux colonnes (Style 1, Style 2) ; position = ligne + 0.1 ou + 0.2 (Style 1 passe avant)
INSERT INTO style (nom_style)
SELECT nom_style
FROM (SELECT `Style 1` AS nom_style, num_ligne + 0.1 AS position FROM tmp_villagers
      UNION ALL
      SELECT `Style 2`, num_ligne + 0.2 FROM tmp_villagers) styles_cites
GROUP BY nom_style
ORDER BY MIN(position);

-- 4.b villageois : les noms d'espèce, de personnalité et de loisir sont remplacés par leur id.
--     Birthday est au format mois/jour : « 7/9 » = 9 juillet.
INSERT INTO villageois (id_espece, id_personnalite, id_loisir, nom_villageois, genre, sous_type,
                        jour_anniversaire, mois_anniversaire, phrase_fetiche, chanson_preferee,
                        citation_preferee, version_ajout_villageois, nom_fichier_villageois)
SELECT e.id_espece, p.id_personnalite, l.id_loisir, t.Name, t.Gender, t.Subtype,
       CAST(SUBSTRING_INDEX(t.Birthday, '/', -1) AS UNSIGNED),                 -- jour = après le « / »
       CAST(SUBSTRING_INDEX(t.Birthday, '/', 1) AS UNSIGNED),                  -- mois = avant le « / »
       t.Catchphrase, t.`Favorite Song`, t.`Favorite Saying`, t.`Version Added`, t.Filename
FROM tmp_villagers t
JOIN espece e       ON e.nom_espece = t.Species
JOIN personnalite p ON p.nom_personnalite = t.Personality
JOIN loisir l       ON l.nom_loisir = t.Hobby
ORDER BY t.num_ligne;

-- 4.c aimer et preferer : les colonnes Style 1/Style 2 et Color 1/Color 2
--     (si les deux colonnes ont la même valeur, une seule ligne est créée).
INSERT INTO aimer (id_villageois, id_style)
SELECT v.id_villageois, s.id_style
FROM tmp_villagers t
JOIN villageois v ON v.nom_villageois = t.Name
JOIN style s ON s.nom_style = t.`Style 1` OR s.nom_style = t.`Style 2`;

INSERT INTO preferer (id_villageois, id_couleur)
SELECT v.id_villageois, c.id_couleur
FROM tmp_villagers t
JOIN villageois v ON v.nom_villageois = t.Name
JOIN couleur c ON c.nom_couleur = t.`Color 1` OR c.nom_couleur = t.`Color 2`;

-- 4.d Furniture List : « 1051;2554;2606… » = Internal ID des objets de la maison
--     -> une ligne par objet (découpage avec tmp_nombres).
CREATE TABLE tmp_maisons AS
SELECT t.Name AS nom_villageois,
       CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(t.`Furniture List`, ';', n.n), ';', -1)) AS UNSIGNED) AS id_interne
FROM tmp_villagers t
JOIN tmp_nombres n ON n.n <= LENGTH(t.`Furniture List`) - LENGTH(REPLACE(t.`Furniture List`, ';', '')) + 1;

-- 4.e meubler : un même meuble peut être plusieurs fois dans une maison -> quantite = COUNT(*).
--     Les objets qui ne sont pas des meubles importés (tapis, vêtements…) ne trouvent pas de
--     meuble dans le JOIN : ils sont laissés de côté (contrôle ci-dessous).
INSERT INTO meubler (id_villageois, id_meuble, quantite)
SELECT v.id_villageois, m.id_meuble, COUNT(*)
FROM tmp_maisons t
JOIN villageois v ON v.nom_villageois = t.nom_villageois
JOIN meuble m ON m.id_interne = t.id_interne
GROUP BY v.id_villageois, m.id_meuble;

-- Contrôle (résultat affiché par import.php) : objets des maisons hors périmètre du projet
SELECT COUNT(DISTINCT nom_villageois, id_interne) AS references_hors_perimetre,
       COUNT(DISTINCT id_interne) AS objets_differents_hors_perimetre
FROM tmp_maisons
WHERE id_interne NOT IN (SELECT id_interne FROM meuble);


-- =====================================================================
-- 5. FIN : suppression des tables temporaires (copies des CSV et tables de travail)
-- =====================================================================
DROP TABLE tmp_fish, tmp_insects, tmp_seasons_and_events, tmp_housewares, tmp_miscellaneous,
           tmp_wall_mounted, tmp_ceiling_decor, tmp_villagers,
           tmp_nombres, tmp_heures, tmp_creatures, tmp_cellules, tmp_creneaux,
           tmp_variantes, tmp_meubles, tmp_sources, tmp_evenements_meubles, tmp_periodes, tmp_maisons;
