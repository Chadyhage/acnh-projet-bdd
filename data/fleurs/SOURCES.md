# Génotypes des fleurs : sources et vérifications

Le Google Sheet donne les couleurs de fleurs (onglet *Plants*) mais pas leur génétique.
Comme demandé, la génétique a été recherchée sur Internet. Le fichier `genotypes.csv`
contient **270 lignes** : pour chacune des 8 espèces, tous les génotypes possibles et la
couleur visible qu'ils donnent.

| Espèce | Gènes | Génotypes |
|---|---|---|
| Rose | R, Y, W, S | 3⁴ = 81 |
| Tulipe, pensée, cosmos, lys, jacinthe, anémone, chrysanthème | R, Y, W | 3³ = 27 chacune |

## Notation

Chaque gène est noté par un chiffre : **0** = deux allèles récessifs (rr), **1** = un allèle
de chaque (Rr), **2** = deux allèles dominants (RR). Le code `2001` d'une rose se lit donc
RR yy ww Ss.

- R : rouge, Y : jaune, W : blanc, S : nuance (roses seulement).
- Colonnes de `genotypes.csv` : `espece`, `couleur` (nom anglais du Google Sheet),
  `code_genotype`, `est_graine` (1 = génotype des fleurs vendues en sac de graines).

## Source principale

Table dataminée par Aeter, publiée sur le site *satogu* :
<https://aiterusawato.github.io/satogu/acnh/flowers/genotypes.html>
(dépôt : <https://github.com/aiterusawato/satogu>, fichier `acnh/flowers/genotypes.md`).

## Recoupement avec 5 sources indépendantes

Chaque génotype a été comparé, un par un, avec cinq autres projets qui ont encodé la
même table chacun de leur côté :

| Source | Génotypes identiques | Écart |
|---|---|---|
| <https://github.com/micwalk/ACNH-Flowers> | 270 / 270 | aucun |
| <https://github.com/zhongzachary/acnh_flowers> | 270 / 270 | aucun |
| <https://github.com/BranLwyd/acnh_flowers> | 270 / 270 | aucun (ce projet note le gène W à l'envers) |
| <https://github.com/joeyparrish/acnh-flowers> | 269 / 270 | tulipe `100` : rose au lieu de rouge |
| <https://github.com/mdsherry/acnh_flowers> | 269 / 270 | jacinthe `211` : bleue au lieu de rouge |

Pour les deux seuls écarts, quatre sources sur cinq (et la table d'Aeter) donnent la même
couleur : c'est cette couleur qui est retenue.

## Fleurs vendues en graines

Une fleur achetée en sac de graines a toujours le même génotype :

| Espèce | Blanche | Jaune | Rouge |
|---|---|---|---|
| Rose | 0010 | 0200 | 2001 |
| Tulipe | 001 | 020 | 201 |
| Pensée | 001 | 020 | 200 |
| Cosmos | 001 | 021 | 200 |
| Lys | 002 | 020 | 201 |
| Jacinthe | 001 | 020 | 201 |
| Anémone | 001 | orange : 020 | 200 |
| Chrysanthème | 001 | 020 | 200 |

L'anémone n'a pas de graine jaune : le magasin vend une anémone orange à la place.

## Calcul des croisements (table CROISER)

Pour chaque gène, l'enfant reçoit un allèle au hasard de chaque parent (loi de Mendel) :
4 cas équiprobables de 1/4 par gène. Les gènes sont indépendants, donc un croisement a
4³ = 64 cas équiprobables (4⁴ = 256 pour les roses). Le script `migration/import.php`
calcule, pour chaque couple ordonné de génotypes d'une même espèce, la probabilité de
chaque couleur d'enfant, et l'enregistre dans `croiser` (32 445 lignes). Le script vérifie
que la somme des cas vaut bien 64 (ou 256) pour chaque couple.

## Hypothèse quand les génotypes des parents sont inconnus

Le joueur ne voit que la couleur des fleurs. Une rose rouge peut avoir 13 génotypes
différents. L'outil suppose que **tous les génotypes qui donnent la couleur choisie ont la
même chance** et fait la moyenne sur tous les couples possibles
(13 × 18 = 234 couples pour rouge × jaune). Les résultats sont donc des estimations ;
avec des fleurs de graines (mode « Fleurs de graines »), le génotype est connu et le
résultat est exact.

Vérification : les 684 combinaisons proposées par le site (8 espèces, toutes les paires de
couleurs, deux modes) ont été comparées à un calcul indépendant en fractions exactes ;
aucune différence.
