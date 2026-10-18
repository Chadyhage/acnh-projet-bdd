"""Vérification fonctionnelle du site (python verification/verifier_resultats.py) : chaque résultat affiché est comparé à un calcul
refait de façon indépendante à partir des CSV bruts du Google Sheet.
Aucune requête SQL du site n'est réutilisée."""
import csv, re, html, datetime, urllib.request, urllib.parse, collections
import os, sys
RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = os.environ.get('ACNH_URL', 'http://localhost/acnh/site/')      # adresse du site (Wamp par défaut)
def connexion():
    """Connexion MySQL : variables DB_HOST, DB_PORT, DB_USER, DB_PASS (ou DB_SOCKET) comme pour le site."""
    import pymysql
    if os.environ.get('DB_SOCKET'):
        return pymysql.connect(unix_socket=os.environ['DB_SOCKET'], user=os.environ.get('DB_USER', 'root'),
                               password=os.environ.get('DB_PASS', ''), database='acnh', charset='utf8mb4')
    return pymysql.connect(host=os.environ.get('DB_HOST', '127.0.0.1'), port=int(os.environ.get('DB_PORT', '3306')),
                           user=os.environ.get('DB_USER', 'root'), password=os.environ.get('DB_PASS', ''),
                           database='acnh', charset='utf8mb4')

CSV = os.path.join(RACINE, 'data', 'csv') + os.sep
db = connexion()
cur = db.cursor()
def lire(f): return list(csv.DictReader(open(CSV + f, encoding='utf-8')))
def page(path):
    return urllib.request.urlopen(BASE + path, timeout=60).read().decode('utf-8')
q = urllib.parse.urlencode
erreurs = collections.Counter()
def ko(cat, msg):
    erreurs[cat] += 1
    if erreurs[cat] <= 5: print(f'  [{cat}]', msg)

MOIS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

# ------------------------------------------------------------------ 1. Créatures à l'instant T
def h24(t):                                      # '4 PM' -> 16, '12 AM' -> 0
    n, ap = t.split()
    n = int(n) % 12
    return n + (12 if ap == 'PM' else 0)
def heures(txt):
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
creatures = []
for f, typ in (('fish.csv', 'poisson'), ('insects.csv', 'insecte')):
    for r in lire(f):
        meteo = r.get('Weather') or ('Rain only' if r['Where/How'] == 'Sea (rainy days)' else 'Any weather')
        creatures.append((typ, r['Name'], meteo, {(h, m + 1): heures(r[f'{h} {MOIS[m]}']) for h in ('NH', 'SH') for m in range(12)}))
RE_CREA = re.compile(r'<a href="creature\.php\?id=\d+[^"]*">([^<]+)</a>\s*<span class="discret">(Poisson|Insecte) n°\d+</span>')
def liste_creatures(html_page):
    return {(t.lower(), html.unescape(n)) for n, t in RE_CREA.findall(html_page)}
n = lignes_crea = 0
for hemi in ('NH', 'SH'):
    for mois in range(1, 13):
        for hr in range(24):
            attendu = {(t, nom) for t, nom, _, dispo in creatures if hr in dispo[(hemi, mois)]}
            obtenu = liste_creatures(page('creatures.php?' + q({'hemisphere': hemi, 'mois': mois, 'heure': hr})))
            n += 1
            lignes_crea += len(obtenu)
            if attendu != obtenu:
                ko('créatures', f'{hemi} mois {mois} {hr}h : manquants {sorted(attendu - obtenu)[:3]} en trop {sorted(obtenu - attendu)[:3]}')
for meteo, exclu in (('beau', 'Rain only'), ('pluie', 'Any except rain')):
    for hemi, mois, hr in (('NH', 6, 10), ('NH', 9, 20), ('SH', 1, 3), ('NH', 2, 18)):
        attendu = {(t, nom) for t, nom, m, dispo in creatures if hr in dispo[(hemi, mois)] and m != exclu}
        obtenu = liste_creatures(page('creatures.php?' + q({'hemisphere': hemi, 'mois': mois, 'heure': hr, 'meteo': meteo})))
        n += 1
        if attendu != obtenu: ko('créatures météo', f'{meteo} {hemi} {mois} {hr}h : {sorted(attendu ^ obtenu)[:4]}')
for hemi in ('NH', 'SH'):
    for mois in range(1, 13):
        suivant = mois % 12 + 1
        attendu = {(t, nom) for t, nom, _, d in creatures if 12 in d[(hemi, mois)] and not d[(hemi, suivant)]}
        obtenu = liste_creatures(page('creatures.php?' + q({'hemisphere': hemi, 'mois': mois, 'heure': 12, 'depart': 1})))
        n += 1
        if attendu != obtenu: ko('créatures départ', f'{hemi} {mois} : {sorted(attendu ^ obtenu)[:4]}')
print(f'1. Créatures : {n} pages comparées ({lignes_crea} lignes de résultats), erreurs = {sum(v for k, v in erreurs.items() if k.startswith("créatures"))}')

# ------------------------------------------------------------------ 2. Villageois
vill = lire('villagers.csv')
def total_villageois(params):
    m = re.search(r'(\d+) villageois (?:sur|dans la base)', page('villageois.php?' + q(params)))
    return int(m.group(1))
tests = 0
for champ, colonnes, table in (('espece', ['Species'], 'espece'), ('personnalite', ['Personality'], 'personnalite'),
                                ('loisir', ['Hobby'], 'loisir'), ('style', ['Style 1', 'Style 2'], 'style'),
                                ('couleur', ['Color 1', 'Color 2'], 'couleur')):
    cur.execute(f"SELECT id_{table}, nom_{table} FROM {table}")
    for i, nom in cur.fetchall():
        attendu = sum(1 for v in vill if nom in [v[c] for c in colonnes])
        if attendu == 0: continue                       # couleur jamais préférée : absente du menu
        tests += 1
        if total_villageois({champ: i}) != attendu: ko('villageois', f'{champ}={nom} attendu {attendu}')
for mois in range(1, 13):
    tests += 1
    attendu = sum(1 for v in vill if int(v['Birthday'].split('/')[0]) == mois)
    if total_villageois({'mois': mois}) != attendu: ko('villageois', f'mois {mois}')
for g in ('Male', 'Female'):
    tests += 1
    if total_villageois({'genre': g}) != sum(1 for v in vill if v['Gender'] == g): ko('villageois', g)
cur.execute("SELECT id_espece FROM espece WHERE nom_espece='Cat'"); chat = cur.fetchone()[0]
cur.execute("SELECT id_style FROM style WHERE nom_style='Cute'"); cute = cur.fetchone()[0]
tests += 1
attendu = sum(1 for v in vill if v['Species'] == 'Cat' and 'Cute' in (v['Style 1'], v['Style 2']) and v['Gender'] == 'Female')
if total_villageois({'espece': chat, 'style': cute, 'genre': 'Female'}) != attendu: ko('villageois', 'combinaison')
print(f'2. Villageois : {tests} filtres comparés, erreurs = {erreurs["villageois"]}')

# ------------------------------------------------------------------ 3. Meubles de saison
meubles = []
for f, cat in (('housewares.csv', 'Housewares'), ('miscellaneous.csv', 'Miscellaneous'),
               ('wall_mounted.csv', 'Wall-mounted'), ('ceiling_decor.csv', 'Ceiling Decor')):
    for r in lire(f): meubles.append((cat, r))
par_evt = collections.defaultdict(set)
for cat, r in meubles:
    for e in r['Season/Event'].split(';'):
        e = e.strip()
        if e and e != 'NA': par_evt[e].add(r['Name'])
RE_NOM = re.compile(r'<span class="nom">([^<]+)</span>')
cur.execute("SELECT id_evenement, nom_evenement FROM evenement")
id_evt = {nom: i for i, nom in cur.fetchall()}
tests = lignes_saison = 0
for e, noms in par_evt.items():
    tests += 1
    obtenu = {html.unescape(x) for x in RE_NOM.findall(page(f'meubles.php?evenement={id_evt[e]}'))}
    lignes_saison += len(obtenu)
    if obtenu != noms: ko('saison événement', f'{e} : {sorted(noms ^ obtenu)[:4]}')
# Périodes : calcul indépendant des dates
saisons = lire('seasons_and_events.csv')
MOISN = {m: i + 1 for i, m in enumerate(MOIS)}
def jour(annee, txt):
    m, d = txt.split()
    try: return datetime.date(annee, MOISN[m], int(d))
    except ValueError: return datetime.date(annee, MOISN[m], 28)      # 29 février hors année bissextile
def periodes(row, hemi, annee):
    v = row.get(f'{annee} {hemi}', 'NA')
    if not v or v == 'NA': return None
    if '–' in v:
        a, b = [x.strip() for x in v.split('–')]
        debut, fin = jour(annee, a), jour(annee, b)
        if fin < debut: fin = jour(annee + 1, b)
    else:
        debut = fin = jour(annee, v.strip())
    return debut, fin
def attendus_date(d, hemi):
    noms = set()
    for row in saisons:
        if row['Name'] not in par_evt: continue
        for an in (d.year - 1, d.year):
            p = periodes(row, hemi, an)
            if p and p[0] <= d <= p[1]: noms |= par_evt[row['Name']]
    return noms
for hemi in ('NH', 'SH'):
    for m in range(1, 13):
        for j in (1, 10, 20, 28):
            d = datetime.date(2026, m, j)
            tests += 1
            obtenu = {html.unescape(x) for x in RE_NOM.findall(page('meubles.php?' + q({'du': d.isoformat(), 'au': d.isoformat(), 'hemisphere': hemi})))}
            attendu = attendus_date(d, hemi)
            lignes_saison += len(obtenu)
            if obtenu != attendu: ko('saison date', f'{hemi} {d} : {sorted(attendu ^ obtenu)[:4]}')
for (du, au) in (('2026-12-01', '2026-12-31'), ('2027-03-25', '2027-04-30'), ('2031-01-01', '2031-01-31')):
    tests += 1
    dd, aa = datetime.date.fromisoformat(du), datetime.date.fromisoformat(au)
    attendu = set()
    for row in saisons:
        if row['Name'] not in par_evt: continue
        for an in (dd.year - 1, dd.year, aa.year):
            p = periodes(row, 'NH', an)
            if p and p[0] <= aa and p[1] >= dd: attendu |= par_evt[row['Name']]
    obtenu = {html.unescape(x) for x in RE_NOM.findall(page('meubles.php?' + q({'du': du, 'au': au})))}
    if obtenu != attendu: ko('saison date', f'{du}..{au} : {sorted(attendu ^ obtenu)[:4]}')
print(f'3. Meubles de saison : {tests} recherches comparées ({lignes_saison} meubles affichés), erreurs = {erreurs["saison événement"] + erreurs["saison date"]}')

# ------------------------------------------------------------------ 4. Catalogue des meubles
def total_meubles(params):
    m = re.search(r'<p class="intro">([\d ]+) meubles? trouvé', page('meubles.php?' + q(dict(params, vue='catalogue'))))
    return int(m.group(1).replace(' ', ''))
items = collections.defaultdict(lambda: {'cat': None, 'couleurs': set(), 'diy': False, 'saison': False})
for cat, r in meubles:
    it = items[(cat, r['Name'])]
    it['cat'] = cat; it['couleurs'] |= {r['Color 1'], r['Color 2']}; it['diy'] = r['DIY'] == 'Yes'
    it['saison'] = it['saison'] or r['Season/Event'] not in ('', 'NA')
tests = 1
if total_meubles({}) != len(items): ko('catalogue', f'total {len(items)}')
cur.execute("SELECT id_categorie, nom_categorie FROM categorie")
for i, nom in cur.fetchall():
    tests += 1
    if total_meubles({'categorie': i}) != sum(1 for it in items.values() if it['cat'] == nom): ko('catalogue', nom)
cur.execute("SELECT DISTINCT c.id_couleur, c.nom_couleur FROM couleur c JOIN colorer co ON co.id_couleur = c.id_couleur")
for i, nom in cur.fetchall():
    tests += 1
    if total_meubles({'couleur': i}) != sum(1 for it in items.values() if nom in it['couleurs']): ko('catalogue', 'couleur ' + nom)
tests += 2
if total_meubles({'bricolable': 1}) != sum(1 for it in items.values() if it['diy']): ko('catalogue', 'DIY')
if total_meubles({'saisonnier': 1}) != sum(1 for it in items.values() if it['saison']): ko('catalogue', 'saison')
print(f'4. Catalogue : {tests} filtres comparés, erreurs = {erreurs["catalogue"]}')

print('\nTOTAL erreurs :', sum(erreurs.values()))
sys.exit(1 if sum(erreurs.values()) else 0)
