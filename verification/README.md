# Vérifications automatiques

Ces scripts répondent à la question « comment être sûr que le site affiche les bons
résultats ? ». Ils ne font pas partie du site : ils l'interrogent comme un visiteur
(requêtes GET) et comparent ce qui est affiché avec un calcul refait autrement.

| Script | Ce qu'il vérifie |
|---|---|
| `tester_pages.py` | Ouvre 2 057 adresses (toutes les heures et tous les mois, tous les filtres, des paramètres invalides ou piégés) : pas d'erreur PHP ni SQL, code HTTP 200, HTML bien fermé. |
| `verifier_resultats.py` | Recalcule les résultats **directement à partir des CSV du Google Sheet**, sans réutiliser les requêtes du site, et les compare aux pages. |

## Résultats (20/10/2026)

```
tester_pages.py
2057 requêtes
échecs : 0 / 2057

verifier_resultats.py
1. Poissons et insectes : 1728 pages comparées (67720 lignes), erreurs = 0
2. Villageois : 54 recherches comparées, erreurs = 0
3. Meubles de saison : 123 recherches comparées (3085 meubles affichés), erreurs = 0
TOTAL erreurs : 0
```

- **Poissons et insectes** : pour les 3 types × 2 hémisphères × 12 mois × 24 heures, la liste
  affichée et le résumé « Où chercher » sont identiques à ceux qu'on obtient en lisant les
  horaires du Google Sheet (« 4 PM – 9 AM », « All day »…).
- **Villageois** : chaque espèce, personnalité, style et genre, et des combinaisons.
- **Meubles de saison** : 48 dates et 12 mois de 2026 dans chaque hémisphère, et 3 périodes
  de plusieurs semaines, recalculés à partir des dates de l'onglet *Seasons and Events*.

Contrôle sur le PC (WampServer, PHP 8.3, MySQL 8.4) : 2 159 adresses (celles des deux scripts et
deux adresses d'exemple du dossier) ont été ouvertes sur `http://localhost/acnh/site/` : 0 erreur,
et les 2 148 pages qui ne dépendent pas de la date du jour sont identiques, octet par octet, à
celles du site vérifié.

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
