-- =====================================================================
--  Les 6 requêtes SQL du site, avec des valeurs d'exemple
--  À exécuter dans phpMyAdmin (onglet SQL) sur la base acnh_site.
--
--  Dans le PHP, chaque valeur écrite ici en dur ('NH', 10, 18…) est un « ? » :
--  la valeur vient de l'URL ($_GET), elle est vérifiée, puis envoyée avec execute().
--  En bas de chaque page, le panneau « Requêtes SQL exécutées » affiche la requête
--  et la valeur donnée à chaque « ? ».
-- =====================================================================
USE acnh_site;

-- ---------------------------------------------------------------------
-- Page « Poissons & insectes » (creatures.php)
-- ---------------------------------------------------------------------

-- Requête 1 (requête complexe du dossier) : créatures disponibles à un instant donné
--   URL : creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18
--   Une ligne de disponibilite = une plage horaire d'un mois : la créature est là
--   de heure_debut (incluse) à heure_fin (exclue).
--   La ligne « AND type_creature = … » n'est ajoutée que si un type est choisi.
--   Résultat : 32 poissons (coelacanth et golden trout en tête, 15 000 clochettes).
SELECT nom_creature, type_creature, nom_lieu, ombre, meteo, prix_vente_creature
FROM creature
JOIN lieu ON creature.id_lieu = lieu.id_lieu
JOIN disponibilite ON creature.id_creature = disponibilite.id_creature
WHERE hemisphere = 'NH'
  AND mois = 10
  AND heure_debut <= 18
  AND heure_fin > 18
  AND type_creature = 'poisson'
GROUP BY creature.id_creature
ORDER BY prix_vente_creature DESC, nom_creature;

-- ---------------------------------------------------------------------
-- Page « Meubles de saison » (meubles.php)
-- ---------------------------------------------------------------------

-- Requête 2 : la liste des saisons (menu déroulant). Résultat : 93 saisons ou événements.
SELECT id_saison, nom_saison
FROM saison
ORDER BY nom_saison;

-- Requête 3 : les meubles d'une saison
--   URL : meubles.php?saison=38&categorie=1   (38 = Halloween, 1 = Housewares)
--   proposer est la table associative entre meuble et saison.
--   WHERE 1 = 1 est toujours vrai : chaque filtre choisi ajoute une ligne AND.
--   Résultat : 9 meubles (15 avec categorie=toutes).
SELECT nom_meuble, nom_saison, nom_categorie, prix_achat, prix_vente_meuble
FROM meuble
JOIN categorie ON meuble.id_categorie = categorie.id_categorie
JOIN proposer ON meuble.id_meuble = proposer.id_meuble
JOIN saison ON proposer.id_saison = saison.id_saison
WHERE 1 = 1
  AND saison.id_saison = 38
  AND categorie.id_categorie = 1
ORDER BY nom_saison, nom_categorie, nom_meuble;

-- ---------------------------------------------------------------------
-- Page « Villageois » (villageois.php)
-- ---------------------------------------------------------------------

-- Requêtes 4 et 5 : les menus déroulants. Résultat : 35 espèces, 8 personnalités.
SELECT id_espece, nom_espece
FROM espece
ORDER BY nom_espece;

SELECT id_personnalite, nom_personnalite
FROM personnalite
ORDER BY nom_personnalite;

-- Requête 6 : la recherche de villageois
--   URL : villageois.php?nom=&espece=11&personnalite=3&genre=Female   (11 = Cat, 3 = Peppy)
--   Une ligne AND est ajoutée pour chaque filtre rempli (nom, espèce, personnalité, genre).
--   Le filtre nom utilise LIKE : '%bo%' = « contient bo ».
--   Résultat : 5 villageoises (Felicity, Merry, Rosie, Tabby, Tangy) ; 14 sans le filtre personnalité.
SELECT nom_villageois, nom_espece, nom_personnalite, genre,
       jour_anniversaire, mois_anniversaire, phrase_fetiche
FROM villageois
JOIN espece ON villageois.id_espece = espece.id_espece
JOIN personnalite ON villageois.id_personnalite = personnalite.id_personnalite
WHERE 1 = 1
  AND villageois.id_espece = 11
  AND villageois.id_personnalite = 3
  AND genre = 'Female'
ORDER BY nom_villageois;
