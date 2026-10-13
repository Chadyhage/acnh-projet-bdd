"""Moteur de référence pour les croisements de fleurs, écrit indépendamment du PHP et du SQL.
Calcul en fractions exactes (loi de Mendel), à partir de data/fleurs/genotypes.csv."""
import csv, os
from fractions import Fraction

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
A, GRAINES = {}, {}            # A[espece][code] = couleur ; GRAINES[espece][couleur] = code
with open(os.path.join(RACINE, 'data', 'fleurs', 'genotypes.csv'), encoding='utf-8') as f:
    for r in csv.DictReader(f):
        A.setdefault(r['espece'], {})[r['code_genotype']] = r['couleur']
        if r['est_graine'] == '1':
            GRAINES.setdefault(r['espece'], {})[r['couleur']] = r['code_genotype']


def gene(a, b):
    """Un gène : chaque parent donne un de ses deux allèles au hasard (4 cas de 1/4)."""
    allele = {0: (0, 0), 1: (0, 1), 2: (1, 1)}
    resultat = {}
    for x in allele[a]:
        for y in allele[b]:
            resultat[x + y] = resultat.get(x + y, 0) + Fraction(1, 4)
    return resultat


def croise(g1, g2):
    """Probabilité de chaque génotype enfant (les gènes sont indépendants)."""
    res = {'': Fraction(1)}
    for a, b in zip(g1, g2):
        res = {k + str(v): p * q for k, p in res.items() for v, q in gene(int(a), int(b)).items()}
    return res


def par_couleur(espece, g1, g2):
    out = {}
    for g, p in croise(g1, g2).items():
        out[A[espece][g]] = out.get(A[espece][g], 0) + p
    return out


def outil(espece, c1, c2, mode='equi'):
    """Même question que la page fleurs.php : couleurs d'enfant et probabilités."""
    if mode == 'graine':
        G1, G2 = [GRAINES[espece][c1]], [GRAINES[espece][c2]]
    else:
        G1 = [g for g, c in A[espece].items() if c == c1]
        G2 = [g for g, c in A[espece].items() if c == c2]
    total = {}
    for g1 in G1:
        for g2 in G2:
            for c, p in par_couleur(espece, g1, g2).items():
                total[c] = total.get(c, 0) + p / (len(G1) * len(G2))
    return dict(sorted(total.items(), key=lambda x: -x[1]))


if __name__ == '__main__':
    pct = lambda r: ', '.join(f'{c} {float(p) * 100:.2f} %' for c, p in r.items())
    print('Rose rouge x jaune (graines) :', pct(outil('Rose', 'Red', 'Yellow', 'graine')))
    print('Rose rouge x jaune (génotypes inconnus) :', pct(outil('Rose', 'Red', 'Yellow')))
