"""Vérification des résultats du site (python verification/verifier_resultats.py) : chaque résultat affiché est comparé
à un calcul refait de façon indépendante à partir des CSV bruts du Google Sheet. Aucune requête SQL du site n'est réutilisée
(la base sert seulement à connaître les id des listes déroulantes et la zone de chaque lieu)."""
import csv, re, html, datetime, urllib.request, urllib.parse, collections
import os, sys
RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = os.environ.get('ACNH_URL', 'http://localhost/acnh/site/')      # adresse du site (Wamp par défaut)
CSV = os.path.join(RACINE, 'data', 'csv') + os.sep


def connexion():
    """Connexion MySQL : variables DB_HOST, DB_PORT, DB_USER, DB_PASS (ou DB_SOCKET) comme pour le site."""
    import pymysql
    if os.environ.get('DB_SOCKET'):
        return pymysql.connect(unix_socket=os.environ['DB_SOCKET'], user=os.environ.get('DB_USER', 'root'),
                               password=os.environ.get('DB_PASS', ''), database='acnh', charset='utf8mb4')
    return pymysql.connect(host=os.environ.get('DB_HOST', '127.0.0.1'), port=int(os.environ.get('DB_PORT', '3306')),
                           user=os.environ.get('DB_USER', 'root'), password=os.environ.get('DB_PASS', ''),
                           database='acnh', charset='utf8mb4')


cur = connexion().cursor()
def lire(f): return list(csv.DictReader(open(CSV + f, encoding='utf-8')))
def page(path): return urllib.request.urlopen(BASE + path, timeout=60).read().decode('utf-8')
q = urllib.parse.urlencode
erreurs = collections.Counter()
def ko(cat, msg):
    erreurs[cat] += 1
    if erreurs[cat] <= 5: print(f'  [{cat}]', msg)
def nombre(txt): return int(html.unescape(txt).replace('\xa0', '').replace(' ', ''))

MOIS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

# ------------------------------------------------------------------ 1. Poissons et insectes à l'instant T
def h24(t):                                      # '4 PM' -> 16, '12 AM' -> 0
    n, ap = t.split()
    return int(n) % 12 + (12 if ap == 'PM' else 0)
def heures(txt):                                 # '4 PM – 9 AM' -> {16, 17, …, 8}
    txt = txt.replace('\xa0', ' ').strip()
    if txt == 'NA': return set()
    if txt == 'All day': return set(range(24))
    s = set()
    for morceau in txt.split(';'):
        a, b = [x.strip() for x in morceau.split('–')]
        h, fin = h24(a), h24(b)
        while h != fin:
            s.add(h); h = (h + 1) % 24
    return s
cur.execute('SELECT nom_lieu, zone_lieu FROM lieu')
zone_de = dict(cur.fetchall())
creatures = []
for f, typ in (('fish.csv', 'poisson'), ('insects.csv', 'insecte')):
    for r in lire(f):
        lieu = 'Sea' if r['Where/How'] == 'Sea (rainy days)' else r['Where/How']
        dispo = {(h, m + 1): heures(r[f'{h} {MOIS[m]}']) for h in ('NH', 'SH') for m in range(12)}
        creatures.append((typ, r['Name'], int(r['Sell']), zone_de[lieu], dispo))
RE_CREA = re.compile(r'<tr><td class="nom">([^<]+)</td><td>(poisson|insecte)</td>')
RE_ZONE = re.compile(r'<tr><td>([^<]+)</td><td>(\d+)</td><td>([^<]+)</td></tr>')
n = lignes = 0
for typ in ('', 'poisson', 'insecte'):
    for hemi in ('NH', 'SH'):
        for mois in range(1, 13):
            for hr in range(24):
                choisis = [c for c in creatures if hr in c[4][(hemi, mois)] and typ in ('', c[0])]
                p = page('creatures.php?' + q({'type': typ, 'hemisphere': hemi, 'mois': mois, 'heure': hr}))
                n += 1
                obtenu = {(t, html.unescape(nom)) for nom, t in RE_CREA.findall(p)}
                lignes += len(obtenu)
                if obtenu != {(c[0], c[1]) for c in choisis}:
                    ko('créatures', f'{typ or "tous"} {hemi} mois {mois} {hr} h : écart {sorted(obtenu ^ {(c[0], c[1]) for c in choisis})[:3]}')
                # Résumé « Où chercher » : nombre et meilleur prix par zone
                attendu = collections.defaultdict(lambda: [0, 0])
                for c in choisis:
                    attendu[c[3]][0] += 1; attendu[c[3]][1] = max(attendu[c[3]][1], c[2])
                zones = {html.unescape(z): (int(nb), nombre(px)) for z, nb, px in RE_ZONE.findall(p)}
                if zones != {z: tuple(v) for z, v in attendu.items()}:
                    ko('où chercher', f'{typ or "tous"} {hemi} mois {mois} {hr} h')
print(f'1. Poissons et insectes : {n} pages comparées ({lignes} lignes), erreurs = {erreurs["créatures"] + erreurs["où chercher"]}')

# ------------------------------------------------------------------ 2. Villageois
vill = lire('villagers.csv')
RE_VILL = re.compile(r'<tr><td class="nom">([^<]+)</td>')
def noms_villageois(params): return {html.unescape(x) for x in RE_VILL.findall(page('villageois.php?' + q(params)))}
tests = 0
filtres = []
for champ, colonnes in (('espece', ['Species']), ('personnalite', ['Personality']), ('style', ['Style 1', 'Style 2'])):
    cur.execute(f'SELECT id_{champ}, nom_{champ} FROM {champ}')
    for i, nom in cur.fetchall():
        filtres.append(({champ: i}, lambda v, c=colonnes, n=nom: n in [v[x] for x in c]))
for g in ('Male', 'Female'):
    filtres.append(({'genre': g}, lambda v, g=g: v['Gender'] == g))
cur.execute("SELECT id_espece FROM espece WHERE nom_espece = 'Cat'"); chat = cur.fetchone()[0]
cur.execute("SELECT id_style FROM style WHERE nom_style = 'Cute'"); cute = cur.fetchone()[0]
filtres.append(({'espece': chat, 'style': cute}, lambda v: v['Species'] == 'Cat' and 'Cute' in (v['Style 1'], v['Style 2'])))
filtres.append(({'espece': chat, 'style': cute, 'genre': 'Female'},
                lambda v: v['Species'] == 'Cat' and 'Cute' in (v['Style 1'], v['Style 2']) and v['Gender'] == 'Female'))
filtres.append(({}, lambda v: True))
for params, garder in filtres:
    tests += 1
    attendu = {v['Name'] for v in vill if garder(v)}
    if noms_villageois(params) != attendu: ko('villageois', f'{params}')
print(f'2. Villageois : {tests} recherches comparées, erreurs = {erreurs["villageois"]}')

# ------------------------------------------------------------------ 3. Meubles de saison
par_evt = collections.defaultdict(set)              # saison ou événement -> noms des meubles
for f in ('housewares.csv', 'miscellaneous.csv', 'wall_mounted.csv', 'ceiling_decor.csv'):
    for r in lire(f):
        for e in r['Season/Event'].split(';'):
            e = e.strip()
            if e and e != 'NA': par_evt[e].add(r['Name'])
saisons = lire('seasons_and_events.csv')
MOISN = {m: i + 1 for i, m in enumerate(MOIS)}
def jour(annee, txt):
    m, d = txt.split()
    try: return datetime.date(annee, MOISN[m], int(d))
    except ValueError: return datetime.date(annee, MOISN[m], 28)      # 29 février hors année bissextile
def periode(row, hemi, annee):
    v = row.get(f'{annee} {hemi}', 'NA')
    if not v or v == 'NA': return None
    if '–' in v:
        a, b = [x.strip() for x in v.split('–')]
        debut, fin = jour(annee, a), jour(annee, b)
        if fin < debut: fin = jour(annee + 1, b)
    else:
        debut = fin = jour(annee, v.strip())
    return debut, fin
def attendus(du, au, hemi):
    noms = set()
    for row in saisons:
        if row['Name'] not in par_evt: continue
        for an in range(du.year - 1, au.year + 1):
            p = periode(row, hemi, an)
            if p and p[0] <= au and p[1] >= du: noms |= par_evt[row['Name']]
    return noms
RE_MEUBLE = re.compile(r'<tr><td class="nom">([^<]+)</td>')
tests = lignes = 0
recherches = []
for hemi in ('NH', 'SH'):
    for m in range(1, 13):
        for j in (1, 10, 20, 28):
            d = datetime.date(2026, m, j)
            recherches.append((d, d, hemi))
        recherches.append((datetime.date(2026, m, 1), datetime.date(2026, m, 28), hemi))
recherches += [(datetime.date(2026, 12, 1), datetime.date(2026, 12, 31), 'NH'), (datetime.date(2027, 3, 25), datetime.date(2027, 4, 30), 'NH'),
               (datetime.date(2031, 1, 1), datetime.date(2031, 1, 31), 'SH')]
for du, au, hemi in recherches:
    tests += 1
    obtenu = {html.unescape(x) for x in RE_MEUBLE.findall(page('meubles.php?' + q({'du': du.isoformat(), 'au': au.isoformat(), 'hemisphere': hemi})))}
    lignes += len(obtenu)
    if obtenu != attendus(du, au, hemi): ko('meubles', f'{hemi} {du}..{au} : écart {sorted(obtenu ^ attendus(du, au, hemi))[:4]}')
print(f'3. Meubles de saison : {tests} recherches comparées ({lignes} meubles affichés), erreurs = {erreurs["meubles"]}')

print('\nTOTAL erreurs :', sum(erreurs.values()))
sys.exit(1 if sum(erreurs.values()) else 0)
