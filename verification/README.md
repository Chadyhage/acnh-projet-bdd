# Vérifications automatiques

Ces scripts répondent à la question « comment être sûr que le site affiche les bons
résultats ? ». Ils ne font pas partie du site : ils l'interrogent comme un visiteur
(requêtes GET) et comparent ce qui est affiché avec un calcul refait autrement.

| Script | Ce qu'il vérifie |
|---|---|
| `tester_pages.py` | Ouvre 3 727 adresses (toutes les fiches, toutes les heures et tous les mois, tous les filtres, des paramètres invalides ou piégés) : pas d'erreur PHP ni SQL, bon code HTTP (404 pour une fiche inexistante), HTML bien fermé. |
| `verifier_resultats.py` | Recalcule les résultats **directement à partir des CSV du Google Sheet**, sans réutiliser les requêtes du site, et les compare aux pages. |

## Résultats (18/10/2026)

```
tester_pages.py
3727 requêtes
échecs : 0 / 3727

verifier_resultats.py
1. Créatures : 608 pages comparées (33860 lignes de résultats), erreurs = 0
2. Villageois : 84 filtres comparés, erreurs = 0
3. Meubles de saison : 192 recherches comparées (2412 meubles affichés), erreurs = 0
4. Catalogue : 21 filtres comparés, erreurs = 0
TOTAL erreurs : 0
```

- **Créatures** : pour les 2 hémisphères × 12 mois × 24 heures, la liste affichée est
  identique à celle qu'on obtient en lisant les horaires du Google Sheet
  (« 4 PM – 9 AM », « All day »…), ainsi que les filtres météo et « dernier mois ».
- **Meubles de saison** : chaque saison/événement, 48 dates de 2026 dans chaque
  hémisphère et 3 périodes de plusieurs jours, recalculées à partir des dates de
  l'onglet *Seasons and Events*.

## Les relancer

Prérequis : Python 3 et le module `pymysql` (`pip install pymysql`), Wamp démarré, base
importée. Depuis le dossier du projet :

```
python verification/tester_pages.py
python verification/verifier_resultats.py
```

Adresse du site par défaut : `http://localhost/acnh/site/` (variable `ACNH_URL` pour en
changer) ; connexion MySQL : `127.0.0.1:3306`, `root`, sans mot de passe (variables
`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`).
