# Base Animal Crossing : New Horizons

Projet transversal SQL de l'EPSI (B1) : d'un Google Sheet métier à un site web.
Équipe : Chady, Ulrich et Abdalah.

Le Google Sheet *Data Spreadsheet for Animal Crossing New Horizons* est transformé en une
base MySQL relationnelle de 21 tables, puis exploité par un site PHP : poissons et insectes
disponibles à un instant donné, meubles de saison et recherche de villageois. Toutes les
recherches sont faites en SQL côté serveur, à partir de paramètres GET visibles dans l'URL
(exemple : `creatures.php?type=poisson&mois=10&heure=18`).

## Contenu du dépôt

| Dossier | Contenu |
|---|---|
| `site/` | Le site PHP (PDO, requêtes préparées). Page d'accueil : `site/index.php` |
| `bdd/` | `acnh_complet.sql` : la base complète (structure + données) ; `acnh_complet.sql.zip` : le même fichier compressé pour phpMyAdmin |
| `sql/` | `01_schema.sql` : création des 21 tables ; `02_migration.sql` : remplissage des tables finales à partir des tables temporaires (copies des CSV) ; `03_requetes_exemples.sql` : les requêtes du site avec des valeurs d'exemple |
| `migration/` | `import.php` : lance la migration complète (structure, chargement des CSV, remplissage) |
| `data/` | `csv/` : les 8 onglets du Google Sheet utilisés, exportés en CSV |
| `conception/` | MCD (Excalidraw et Merise Studio) et MRD (fichier DBML pour dbdiagram.io), avec leurs images |
| `verification/` | Scripts qui comparent les résultats du site à un calcul indépendant |

## Lancer le projet avec WampServer

1. Copier le dossier du projet dans `C:\wamp64\www\` et le nommer `acnh`
   (le fichier `C:\wamp64\www\acnh\site\index.php` doit exister).
2. Démarrer Wamp et attendre que l'icône soit verte.
3. Créer la base, au choix :
   - **phpMyAdmin** (recommandé) : l'ouvrir par le lien « phpmyadmin » de la page d'accueil
     de Wamp (<http://localhost/>) ; son adresse contient la version, par exemple
     <http://localhost/phpmyadmin5.2.3/>. Se connecter avec l'utilisateur `root` sans mot de
     passe et le choix de serveur **MySQL**, onglet **Importer**, choisir
     `bdd/acnh_complet.sql.zip`, cliquer **Importer**.
     La base `acnh` est créée (environ 3 Mo de données) ; aucune autre base n'est modifiée.
   - **Script de migration** : ouvrir <http://localhost/acnh/migration/import.php>. Le script
     recrée la base à partir des CSV du dossier `data/csv/` (moins de 20 secondes) et
     affiche le nombre de lignes de chaque table.
4. Ouvrir le site : <http://localhost/acnh/site/>

Paramètres de connexion (fichier `site/config.php`) : `localhost`, port `3306`, utilisateur
`root`, mot de passe vide, base `acnh`. Ils peuvent être changés dans ce fichier ou par les
variables d'environnement `DB_HOST`, `DB_PORT`, `DB_USER` et `DB_PASS`.
Les images (poissons, meubles, villageois) viennent du site catalogue.ac : il faut une
connexion Internet pour les voir.

En ligne de commande, la migration se lance aussi avec `php migration/import.php`.

## Comment la migration fonctionne

1. **Structure** : `sql/01_schema.sql` recrée la base `acnh` vide (21 tables).
2. **Chargement** : `migration/import.php` copie chaque fichier CSV, tel quel, dans une table
   temporaire (`fish.csv` → `tmp_fish`, `villagers.csv` → `tmp_villagers`…) : mêmes colonnes
   que le fichier, tout en texte, plus le numéro de ligne.
3. **Remplissage** : `sql/02_migration.sql` remplit chaque table finale avec une requête
   `INSERT INTO … SELECT … FROM tmp_…` qui ne garde que les colonnes utiles et convertit les
   valeurs (`NA` → `NULL`, `Yes` → 1, `4 PM` → 16, une cellule « A; B » → deux lignes),
   puis supprime les tables temporaires.

Pour regarder les tables temporaires dans phpMyAdmin, lancer
<http://localhost/acnh/migration/import.php?garder=1> : elles restent dans la base jusqu'au
lancement suivant sans l'option.

## Les pages du site

| Page | Ce qu'elle permet | Paramètres GET |
|---|---|---|
| `index.php` | Aujourd'hui sur l'île : créatures de sortie à l'heure actuelle, saisons et événements en cours, anniversaires du mois | `hemisphere` |
| `creatures.php` | Poissons et insectes disponibles à un instant T, résumé par zone | `type`, `hemisphere`, `mois`, `heure`, `zone`, `meteo`, `depart`, `q`, `tri` |
| `creature.php` | Fiche d'une créature et son calendrier sur 12 mois | `id`, `hemisphere` |
| `meubles.php` | Meubles de saison par période ou par saison/événement ; catalogue complet filtrable | `du`, `au`, `type_evt`, `evenement`, `hemisphere`, `categorie`, `q`, `vue`, `couleur`, `bricolable`, `saisonnier`, `tri`, `page` |
| `meuble.php` | Fiche d'un meuble : variantes, couleurs, sources, saisons, villageois qui l'ont | `id`, `hemisphere` |
| `villageois.php` | Recherche de villageois par espèce, personnalité, genre, loisir, style, couleur, mois d'anniversaire | `espece`, `personnalite`, `genre`, `loisir`, `style`, `couleur`, `mois`, `q`, `tri`, `page` |
| `villageois_fiche.php` | Fiche d'un villageois et les meubles de sa maison | `id` |

En bas de chaque page, le bloc **« Requêtes SQL de cette page »** affiche les requêtes
exécutées et la valeur de leurs paramètres : on voit directement le lien
action de l'utilisateur, paramètres reçus, requête SQL, résultat affiché.

## Choix techniques

- **PHP + PDO** : une seule connexion `$dbh` (fichier `config.php`), requêtes préparées avec
  paramètres nommés ; aucune valeur de l'URL n'est collée dans le SQL.
- **Paramètres contrôlés** : chaque paramètre GET est vérifié (liste de valeurs autorisées,
  nombre entre deux bornes, date valide) ; une valeur inconnue est ignorée.
- **Filtres en SQL** : chaque filtre rempli ajoute une condition au `WHERE` ; le JavaScript
  n'est utilisé nulle part pour filtrer.
- **MySQL 8 (InnoDB)** : clés étrangères, contraintes `CHECK`, `UNIQUE` et index sur les
  colonnes de recherche ; les requêtes respectent le mode `ONLY_FULL_GROUP_BY` de MySQL, et la
  migration s'exécute en mode strict (une valeur impossible à convertir arrête l'import).
- **Hémisphère Nord par défaut**, heure de Paris pour « maintenant ».

## Sources des données

- Google Sheet : <https://docs.google.com/spreadsheets/d/13d_LAJPlxMa_DubPTuirkIV4DERBMXbrWQsmSh8ReK4>
  (onglets Fish, Insects, Villagers, Housewares, Miscellaneous, Wall-mounted, Ceiling Decor,
  Seasons and Events).
- Images : <https://nh-cdn.catalogue.ac/> (même adresse que les formules `=IMAGE()` du Google Sheet).
