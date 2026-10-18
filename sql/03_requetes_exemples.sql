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
--    Résultat : 32 poissons (coelacanth et golden trout en tête, 15 000 clochettes).
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
--    prix par zone (jointure + sous-requête EXISTS + filtres + GROUP BY + ORDER BY).
--    C'est la requête complexe présentée dans le dossier (partie 6).
--    Page : creatures.php?hemisphere=NH&mois=10&heure=18&meteo=pluie
--    Le filtre météo « il pleut » retire les insectes qui fuient la pluie.
--    Résultat : Mer/poisson 15 (15 000) ; Rivière/poisson 13 (15 000) ;
--    Étang/poisson 4 (4 000) ; Sol/insecte 4 (600) ; Arbres/insecte 3 (2 500) ;
--    Rochers/insecte 3 (300) ; Autre/insecte 1 (70) ; Air/insecte 1 (60).
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
--    Résultat : 20 créatures.
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
--    Résultat : 99 lignes = 71 meubles différents dans 18 saisons ou événements
--    (un meuble peut appartenir à plusieurs saisons).
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
--    Page : meubles.php?evenement=<id de Halloween>&hemisphere=NH   (evenement=59)
--    Résultat : 15 meubles, puis les deux prochaines périodes de Halloween.
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
  AND p.date_fin >= '2026-10-23'          -- date du jour
ORDER BY p.date_debut
LIMIT 2;

-- ---------------------------------------------------------------------
-- 6. Catalogue : meubles ayant au moins une variante bleue, fabricables,
--    avec leur nombre de variantes (GROUP BY)
--    Page : meubles.php?vue=catalogue&couleur=<id de Blue>&bricolable=1&tri=variantes
--    Résultat : 116 meubles.
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
-- 7. Villageois par caractéristiques : chats qui aiment le style « Cute »
--    Page : villageois.php?espece=<id de Cat>&style=<id de Cute>   (espece=11&style=2)
--    Résultat : 5 villageois sur 417 (Bob, Felicity, Merry, Rosie, Tangy).
--    Le PHP n'ajoute une condition que pour les filtres remplis :
--    &genre=Female ajouterait  AND v.genre = 'Female'.
-- ---------------------------------------------------------------------
SELECT v.nom_villageois, es.nom_espece, pe.nom_personnalite,
       v.jour_anniversaire, v.mois_anniversaire
FROM villageois v
JOIN espece es       ON es.id_espece = v.id_espece
JOIN personnalite pe ON pe.id_personnalite = v.id_personnalite
WHERE es.nom_espece = 'Cat'
  AND EXISTS (SELECT 1 FROM aimer a JOIN style s ON s.id_style = a.id_style
              WHERE a.id_villageois = v.id_villageois AND s.nom_style = 'Cute')
ORDER BY v.nom_villageois;

-- ---------------------------------------------------------------------
-- 8. Contrôles d'intégrité (doivent tous renvoyer 0)
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
