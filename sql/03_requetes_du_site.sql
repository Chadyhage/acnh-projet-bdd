-- =====================================================================
--  Les 6 requêtes SQL du site, avec des valeurs d'exemple
--  (à exécuter dans phpMyAdmin, onglet SQL, sur la base acnh_site).
--
--  Dans le PHP, chaque valeur écrite ici en dur ('NH', 10, 18…) est un paramètre
--  nommé (:hemisphere, :mois, :heure…) : PDO le remplace par la valeur reçue dans
--  l'URL (GET), après vérification. Le site n'exécute pas d'autre requête.
--  En bas de chaque page, le panneau « Requêtes SQL exécutées » affiche la requête
--  réellement envoyée et la valeur de chaque paramètre.
-- =====================================================================
USE acnh_site;

-- ---------------------------------------------------------------------
-- Page « Poissons & insectes » (creatures.php)
-- ---------------------------------------------------------------------

-- Requête 1 (requête complexe du dossier) : créatures disponibles à un instant T
--   URL : creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18
--   Une ligne de disponibilite = une plage horaire [heure_debut ; heure_fin[ d'un mois.
--   Une plage qui passe minuit (ex. 21 h -> 4 h) a heure_debut > heure_fin :
--   l'heure doit alors être après le début OU avant la fin.
--   La ligne « c.type_creature = … » n'est ajoutée que si un type est choisi (type=tous : pas de ligne).
--   GROUP BY : une seule ligne par créature, même si plusieurs plages contenaient l'heure.
--   Résultat : 32 poissons (coelacanth et golden trout en tête, 15 000 clochettes) ; 54 avec type=tous.
SELECT c.id_creature, c.nom_creature, c.type_creature,
       l.nom_lieu, c.ombre, c.meteo, c.prix_vente_creature
FROM creature c
JOIN lieu l          ON l.id_lieu = c.id_lieu
JOIN disponibilite d ON d.id_creature = c.id_creature
WHERE c.type_creature = 'poisson'
  AND d.hemisphere = 'NH'
  AND d.mois = 10
  AND (
        (d.heure_debut < d.heure_fin
           AND 18 >= d.heure_debut AND 18 < d.heure_fin)
     OR (d.heure_debut > d.heure_fin
           AND (18 >= d.heure_debut OR 18 < d.heure_fin))
      )
GROUP BY c.id_creature, c.nom_creature, c.type_creature,
         l.nom_lieu, c.ombre, c.meteo, c.prix_vente_creature
ORDER BY c.prix_vente_creature DESC, c.nom_creature;

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
--   « WHERE 1 = 1 » est toujours vrai : chaque filtre rempli ajoute une ligne AND.
--   Résultat : 9 meubles (15 avec categorie=toutes).
SELECT m.id_meuble, m.nom_meuble, cat.nom_categorie, s.nom_saison,
       m.prix_achat, m.prix_vente_meuble
FROM meuble m
JOIN categorie cat ON cat.id_categorie = m.id_categorie
JOIN proposer p    ON p.id_meuble      = m.id_meuble
JOIN saison s      ON s.id_saison      = p.id_saison
WHERE 1 = 1
  AND s.id_saison = 38
  AND m.id_categorie = 1
ORDER BY s.nom_saison, cat.nom_categorie, m.nom_meuble;

-- ---------------------------------------------------------------------
-- Page « Villageois » (villageois.php)
-- ---------------------------------------------------------------------

-- Requêtes 4 et 5 : les listes déroulantes. Résultat : 35 espèces, 8 personnalités.
SELECT id_espece, nom_espece
FROM espece
ORDER BY nom_espece;

SELECT id_personnalite, nom_personnalite
FROM personnalite
ORDER BY nom_personnalite;

-- Requête 6 : la recherche de villageois
--   URL : villageois.php?nom=&espece=11&personnalite=3&genre=Female   (11 = Cat, 3 = Peppy)
--   Une ligne AND est ajoutée pour chaque filtre rempli (nom, espèce, personnalité, genre).
--   Le filtre « nom » utilise LIKE : '%bo%' = « contient bo ».
--   Résultat : 5 villageoises (Felicity, Merry, Rosie, Tabby, Tangy) ; 14 sans le filtre personnalité.
SELECT v.id_villageois, v.nom_villageois, es.nom_espece,
       pe.nom_personnalite, v.genre,
       v.jour_anniversaire, v.mois_anniversaire, v.phrase_fetiche
FROM villageois v
JOIN espece es       ON es.id_espece       = v.id_espece
JOIN personnalite pe ON pe.id_personnalite = v.id_personnalite
WHERE 1 = 1
  AND v.id_espece = 11
  AND v.id_personnalite = 3
  AND v.genre = 'Female'
ORDER BY v.nom_villageois;

-- ---------------------------------------------------------------------
-- Contrôles d'intégrité (doivent renvoyer 0)
-- ---------------------------------------------------------------------
-- Chaque créature est disponible au moins un mois dans chaque hémisphère
SELECT COUNT(*) AS creatures_sans_disponibilite
FROM creature c
WHERE NOT EXISTS (SELECT 1 FROM disponibilite d WHERE d.id_creature = c.id_creature AND d.hemisphere = 'NH')
   OR NOT EXISTS (SELECT 1 FROM disponibilite d WHERE d.id_creature = c.id_creature AND d.hemisphere = 'SH');

-- Chaque saison propose au moins un meuble
SELECT COUNT(*) AS saisons_sans_meuble
FROM saison s
WHERE NOT EXISTS (SELECT 1 FROM proposer p WHERE p.id_saison = s.id_saison);
