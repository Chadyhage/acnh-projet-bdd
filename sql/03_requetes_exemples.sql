-- =====================================================================
-- Toutes les requêtes du site, avec des valeurs d'exemple
-- (à exécuter dans phpMyAdmin, onglet SQL, après l'import de la base).
--
-- Dans le PHP, chaque valeur écrite en dur ici ('NH', 10, 18…) est un paramètre
-- de requête préparée PDO (:hemisphere, :mois, :heure…), rempli avec la valeur
-- reçue dans l'URL (GET) après vérification. Le site n'exécute pas d'autre requête.
-- =====================================================================
USE acnh;

-- ---------------------------------------------------------------------
-- Page « Poissons et insectes » (creatures.php)
-- Une ligne de disponibilite = un créneau [heure_debut ; heure_fin[ d'un mois.
-- Un créneau qui passe minuit (ex. 19 h -> 4 h) a heure_debut > heure_fin :
-- l'heure doit alors être après le début OU avant la fin.
-- ---------------------------------------------------------------------

-- Requête 1 : les créatures disponibles à un instant T
--   URL : creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18
--   La ligne « AND c.type_creature = … » n'est ajoutée que si le filtre Type est rempli.
--   Résultat : 32 poissons (coelacanth et golden trout en tête, 15 000 clochettes).
SELECT c.nom_creature, c.type_creature, l.nom_lieu, c.prix_vente_creature
FROM creature c
JOIN lieu l          ON l.id_lieu = c.id_lieu
JOIN disponibilite d ON d.id_creature = c.id_creature
WHERE d.hemisphere = 'NH'
  AND d.mois = 10
  AND (   (d.heure_debut < d.heure_fin AND 18 >= d.heure_debut AND 18 < d.heure_fin)
       OR (d.heure_debut > d.heure_fin AND (18 >= d.heure_debut OR 18 < d.heure_fin)))
  AND c.type_creature = 'poisson'
ORDER BY c.prix_vente_creature DESC, c.nom_creature;

-- Requête 2 (requête complexe du dossier) : où chercher ? nombre de créatures et meilleur prix par zone
--   URL : creatures.php?hemisphere=NH&mois=10&heure=18   (sans filtre Type)
--   Si le filtre Type est rempli, la même ligne « AND c.type_creature = … » est ajoutée
--   (avec type=poisson : 3 zones, Mer 15, Rivière 13, Étang 4).
--   Résultat : 10 zones, 54 créatures : Mer 15 (15 000) ; Rivière 13 (15 000) ; Sol 7 (1 500) ;
--   Arbres 5 (2 500) ; Étang 4 (4 000) ; Rochers 3 (300) ; Air 3 (160) ; Fleurs 2 (1 000) ;
--   Eau 1 (180) ; Autre 1 (70).
SELECT l.zone_lieu, COUNT(*) AS nb, MAX(c.prix_vente_creature) AS meilleur_prix
FROM creature c
JOIN lieu l          ON l.id_lieu = c.id_lieu
JOIN disponibilite d ON d.id_creature = c.id_creature
WHERE d.hemisphere = 'NH'
  AND d.mois = 10
  AND (   (d.heure_debut < d.heure_fin AND 18 >= d.heure_debut AND 18 < d.heure_fin)
       OR (d.heure_debut > d.heure_fin AND (18 >= d.heure_debut OR 18 < d.heure_fin)))
GROUP BY l.zone_lieu
ORDER BY nb DESC, meilleur_prix DESC, l.zone_lieu;

-- ---------------------------------------------------------------------
-- Page « Meubles de saison » (meubles.php)
-- ---------------------------------------------------------------------

-- Requête 3 : les meubles disponibles sur une période
--   URL : meubles.php?du=2026-12-01&au=2026-12-31&hemisphere=NH
--   Une période chevauche [du ; au] si elle commence avant « au » et finit après « du ».
--   DISTINCT : un meuble qui appartient à plusieurs saisons n'apparaît qu'une fois.
--   Résultat : 71 meubles.
SELECT DISTINCT m.nom_meuble, cat.nom_categorie, m.prix_achat
FROM meuble m
JOIN categorie cat ON cat.id_categorie = m.id_categorie
JOIN proposer pr   ON pr.id_meuble = m.id_meuble
JOIN periode p     ON p.id_evenement = pr.id_evenement
WHERE p.hemisphere_periode = 'NH'
  AND p.date_debut <= '2026-12-31'
  AND p.date_fin >= '2026-12-01'
ORDER BY m.nom_meuble;

-- ---------------------------------------------------------------------
-- Page « Villageois » (villageois.php)
-- ---------------------------------------------------------------------

-- Requêtes 4, 5 et 6 : les choix des listes déroulantes
--   Résultat : 35 espèces, 8 personnalités, 6 styles. L'id est envoyé dans l'URL, le nom est affiché.
--   Dans le bloc en bas de la page Villageois, ces requêtes et la recherche sont numérotées 1 à 4.
SELECT id_espece, nom_espece FROM espece ORDER BY nom_espece;
SELECT id_personnalite, nom_personnalite FROM personnalite ORDER BY nom_personnalite;
SELECT id_style, nom_style FROM style ORDER BY nom_style;

-- Requête 7 : la recherche de villageois
--   URL : villageois.php?espece=11&style=2   (11 = Cat, 2 = Cute)
--   Une ligne AND est ajoutée pour chaque filtre rempli (espèce, personnalité, genre, style).
--   « WHERE 1 = 1 » est toujours vrai : il permet d'ajouter chaque condition avec AND.
--   Le style passe par la table associative aimer (un villageois aime 1 ou 2 styles).
--   Résultat : 5 villageois (Bob, Felicity, Merry, Rosie, Tangy).
SELECT v.nom_villageois, e.nom_espece, p.nom_personnalite, v.genre, v.jour_anniversaire, v.mois_anniversaire
FROM villageois v
JOIN espece e       ON e.id_espece = v.id_espece
JOIN personnalite p ON p.id_personnalite = v.id_personnalite
WHERE 1 = 1
  AND v.id_espece = 11
  AND v.id_villageois IN (SELECT a.id_villageois FROM aimer a WHERE a.id_style = 2)
ORDER BY v.nom_villageois;

-- ---------------------------------------------------------------------
-- Contrôles d'intégrité (doivent tous renvoyer 0)
-- ---------------------------------------------------------------------
-- Chaque créature est disponible au moins un mois dans chaque hémisphère
SELECT COUNT(*) AS creatures_sans_disponibilite
FROM creature c
WHERE NOT EXISTS (SELECT 1 FROM disponibilite d WHERE d.id_creature = c.id_creature AND d.hemisphere = 'NH')
   OR NOT EXISTS (SELECT 1 FROM disponibilite d WHERE d.id_creature = c.id_creature AND d.hemisphere = 'SH');

-- Chaque meuble a au moins une variante
SELECT COUNT(*) AS meubles_sans_variante
FROM meuble m
WHERE NOT EXISTS (SELECT 1 FROM variante v WHERE v.id_meuble = m.id_meuble);
