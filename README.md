# ACNH Data — Projet transversal SQL (EPSI B1)

Équipe : Chady, Ulrich et Abdalah.

Le Google Sheet *Data Spreadsheet for Animal Crossing New Horizons* est transformé en une base
MySQL de 10 tables (`acnh_site`), puis exploité par un site PHP de 4 pages. Toutes les recherches
sont faites en SQL par le serveur, à partir des paramètres GET visibles dans l'URL
(exemple : `creatures.php?type=poisson&hemisphere=NH&mois=10&heure=18`).

## Lancer le site avec WampServer

1. Copier ce dossier dans `C:\wamp64\www\` sous le nom `acnh_site`
   (le fichier `C:\wamp64\www\acnh_site\index.php` doit exister).
2. Démarrer Wamp et attendre que l'icône soit verte.
3. Créer la base : ouvrir phpMyAdmin depuis <http://localhost/>, utilisateur `root` sans mot de
   passe, serveur **MySQL**, onglet **Importer**, fichier `sql/acnh_site.sql`, puis **Importer**.
4. Ouvrir le site : <http://localhost/acnh_site/>

Autre façon de créer la base, à partir des CSV : <http://localhost/acnh_site/migration/import.php>.
Paramètres de connexion : `config.php` (`127.0.0.1`, `root`, mot de passe vide, base `acnh_site`).

## Contenu

| Fichier ou dossier | Rôle |
|---|---|
| `index.php` | Accueil : les trois recherches |
| `creatures.php` | Poissons et insectes disponibles à un instant T (1 requête) |
| `meubles.php` | Meubles d'une saison ou d'un événement, par catégorie (2 requêtes) |
| `villageois.php` | Recherche de villageois par nom, espèce, personnalité et genre (3 requêtes) |
| `config.php` | Paramètres de connexion MySQL |
| `includes/db.php` | Connexion PDO, exécution des requêtes (`run_query`), lecture des paramètres GET |
| `includes/header.php`, `includes/footer.php` | Haut de page (menu) et bas de page (panneau des requêtes SQL) |
| `assets/style.css` | Mise en page |
| `sql/acnh_site.sql` | **La base complète** (structure + données) à importer dans phpMyAdmin |
| `sql/01_creation_tables.sql` | Création des 10 tables (clés, contraintes) |
| `sql/02_migration.sql` | Remplissage des tables à partir des CSV (`INSERT INTO … SELECT`) |
| `sql/03_requetes_du_site.sql` | Les 6 requêtes du site avec des valeurs d'exemple et le résultat attendu |
| `migration/import.php` | Lance la migration complète : CSV → tables temporaires → 10 tables |
| `data/csv/` | Les 8 onglets du Google Sheet utilisés, exportés en CSV |
| `conception/` | MCD (Excalidraw) et MRD (dbdiagram.io), avec leurs images |

## La base `acnh_site` (10 tables, 5 139 lignes)

- **Créatures** : `lieu`, `creature`, `disponibilite` (une ligne = une plage horaire d'un mois,
  dans un hémisphère).
- **Meubles** : `categorie`, `meuble`, `saison`, `proposer` (table associative : un meuble peut être
  proposé pendant plusieurs saisons, une saison propose plusieurs meubles).
- **Villageois** : `espece`, `personnalite`, `villageois`.

## Les requêtes du site

| Page | Requête | Où dans le code |
|---|---|---|
| `creatures.php` | Créatures disponibles à un instant T (requête complexe : 2 jointures, plusieurs conditions, `GROUP BY`, `ORDER BY`) | lignes 16 à 39, exécutée ligne 43 |
| `meubles.php` | Liste des saisons (menu déroulant) | lignes 15-16 |
| `meubles.php` | Meubles de la saison choisie | lignes 22 à 41, exécutée ligne 44 |
| `villageois.php` | Liste des espèces, liste des personnalités | lignes 11 à 14 |
| `villageois.php` | Villageois filtrés (`WHERE 1 = 1` + une ligne `AND` par filtre rempli) | lignes 23 à 49, exécutée ligne 52 |

Chaque requête passe par la fonction `run_query()` (`includes/db.php`, ligne 30) : requête préparée
PDO, paramètres nommés (`:mois`, `:heure1`…). En bas de chaque page de recherche, le panneau
**« Requêtes SQL exécutées »** affiche l'URL reçue, la requête, la valeur de chaque paramètre et le
nombre de lignes.

## Comment la migration fonctionne

1. **Structure** : `sql/01_creation_tables.sql` recrée la base `acnh_site` vide (10 tables).
2. **Chargement** : `migration/import.php` copie chaque fichier CSV, tel quel, dans une table
   temporaire (`fish.csv` → `tmp_fish`, `villagers.csv` → `tmp_villagers`…).
3. **Remplissage** : `sql/02_migration.sql` remplit chaque table avec une requête
   `INSERT INTO … SELECT … FROM tmp_…` qui ne garde que les colonnes utiles et convertit les
   valeurs (`NFS` → `NULL`, `Yes` → 1, `4 PM` → 16, une cellule « A; B » → deux lignes), puis
   supprime les tables temporaires.

## Source des données

Google Sheet : <https://docs.google.com/spreadsheets/d/13d_LAJPlxMa_DubPTuirkIV4DERBMXbrWQsmSh8ReK4>
(onglets Fish, Insects, Villagers, Housewares, Miscellaneous, Wall-mounted, Ceiling Decor,
Seasons and Events).
