# ACNH Data — Projet transversal SQL (EPSI B1)

Équipe : Chady, Ulrich et Abdalah.

Le Google Sheet *Data Spreadsheet for Animal Crossing New Horizons* est transformé en une base
MySQL de 10 tables (`acnh_site`), puis utilisé par un site PHP de 4 pages. Toutes les recherches
sont faites en SQL par le serveur, à partir des paramètres GET de l'URL
(exemple : `creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18`).

## Lancer le site avec WampServer

1. Copier ce dossier dans `C:\wamp64\www\` sous le nom `acnh_site`
   (le fichier `C:\wamp64\www\acnh_site\index.php` doit exister).
2. Démarrer Wamp et attendre que l'icône soit verte.
3. Créer la base : ouvrir phpMyAdmin (<http://localhost/phpmyadmin/>), utilisateur `root` sans mot
   de passe, serveur **MySQL**, onglet **Importer**, fichier `sql/acnh_site.sql`, puis **Importer**.
4. Ouvrir le site : <http://localhost/acnh_site/>

Autre façon de créer la base, à partir des fichiers CSV : <http://localhost/acnh_site/migration/import.php>.
La connexion à la base est dans `config.php` (`localhost`, `root`, mot de passe vide, base `acnh_site`).

## Contenu

| Fichier ou dossier | Rôle |
|---|---|
| `index.php` | Accueil : les trois recherches |
| `creatures.php` | Poissons et insectes disponibles à un instant donné (1 requête) |
| `meubles.php` | Meubles d'une saison ou d'un événement, par catégorie (2 requêtes) |
| `villageois.php` | Recherche de villageois par nom, espèce, personnalité et genre (3 requêtes) |
| `config.php` | Connexion à la base (`$dbh`) |
| `includes/header.php`, `includes/footer.php` | Haut de page (menu) et bas de page |
| `includes/fonctions.php` | 3 fonctions : option choisie dans un menu, heure sur deux chiffres, affichage d'une requête |
| `assets/style.css` | Mise en page |
| `sql/acnh_site.sql` | **La base complète** (tables + données) à importer dans phpMyAdmin |
| `sql/01_creation_tables.sql` | Création des 10 tables (clés, contraintes) |
| `sql/02_requetes_du_site.sql` | Les 6 requêtes du site avec des valeurs d'exemple et le résultat attendu |
| `migration/import.php` | Migration : lit les CSV et remplit les 10 tables avec des requêtes INSERT |
| `data/csv/` | Les 8 onglets du Google Sheet utilisés, exportés en CSV |
| `conception/` | MCD (Excalidraw) et MRD (dbdiagram.io), avec leurs images |

## La base `acnh_site` (10 tables, 5 645 lignes)

- **Créatures** : `lieu`, `creature`, `disponibilite` (une ligne = une plage horaire d'un mois,
  dans un hémisphère ; une plage qui passe minuit est coupée en deux lignes).
- **Meubles** : `categorie`, `meuble`, `saison`, `proposer` (table associative : un meuble peut être
  proposé pendant plusieurs saisons, une saison propose plusieurs meubles).
- **Villageois** : `espece`, `personnalite`, `villageois`.

## Les requêtes du site

| Page | Requête | Où dans le code |
|---|---|---|
| `creatures.php` | Créatures disponibles à un instant donné (requête complexe : 2 jointures, plusieurs conditions, `GROUP BY`, `ORDER BY`) | lignes 51 à 71 |
| `meubles.php` | Liste des saisons (menu déroulant) | lignes 10 à 15 |
| `meubles.php` | Meubles de la saison choisie | lignes 49 à 70 |
| `villageois.php` | Liste des espèces, liste des personnalités | lignes 10 à 22 |
| `villageois.php` | Villageois filtrés (`WHERE 1 = 1` + une ligne `AND` par filtre rempli) | lignes 71 à 100 |

Chaque page suit le même ordre : 1. lecture des paramètres GET avec `isset()` et vérification avec
`in_array()` (les listes des menus sont lues avant, pour connaître les valeurs autorisées ; le nom,
texte libre, est seulement envoyé dans un `?`) ; 2. requête SQL avec des `?`, puis `prepare()`,
`execute()` et `fetchAll()` ; 3. affichage. En bas de chaque page de recherche, le panneau **« Requêtes SQL exécutées »** affiche
l'URL reçue, la requête, la valeur donnée à chaque `?` et le nombre de lignes.

## Comment la migration fonctionne

1. On exporte les 8 onglets utiles du Google Sheet en CSV (`data/csv/`).
2. `migration/import.php` recrée la base vide avec `sql/01_creation_tables.sql`.
3. Il lit chaque CSV ligne par ligne (`fgetcsv`), convertit les valeurs utiles en PHP
   (`NFS` → `NULL`, `Yes` → 1, `4 PM` → 16, une case « A; B » → deux valeurs) et les insère avec
   des requêtes `INSERT INTO … VALUES (?, …)`.

## Source des données

Google Sheet : <https://docs.google.com/spreadsheets/d/13d_LAJPlxMa_DubPTuirkIV4DERBMXbrWQsmSh8ReK4>
(onglets Fish, Insects, Villagers, Housewares, Miscellaneous, Wall-mounted, Ceiling Decor,
Seasons and Events).
