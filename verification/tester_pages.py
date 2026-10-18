"""Test de toutes les pages (python verification/tester_pages.py) : toutes les pages, beaucoup de combinaisons de paramètres GET.
Échoue si statut HTTP inattendu, avertissement PHP, erreur SQL ou HTML mal formé."""
import urllib.request, urllib.parse, re, concurrent.futures as cf
from html.parser import HTMLParser
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
db = connexion()
cur = db.cursor()
def col(sql):
    cur.execute(sql); return [r[0] for r in cur.fetchall()]

VOID = {'area','base','br','col','embed','hr','img','input','link','meta','source','track','wbr'}
class Checker(HTMLParser):
    def __init__(self):
        super().__init__(); self.stack=[]; self.errors=[]; self.ids=set()
    def handle_starttag(self, tag, attrs):
        d = dict(attrs)
        if 'id' in d:
            if d['id'] in self.ids: self.errors.append('id en double: '+d['id'])
            self.ids.add(d['id'])
        if tag == 'img' and 'alt' not in d: self.errors.append('img sans alt')
        if tag not in VOID: self.stack.append(tag)
    def handle_endtag(self, tag):
        if tag in VOID: return
        # éléments à fermeture implicite tolérés : p, li, option, optgroup, tr, td, th
        while self.stack and self.stack[-1] != tag and self.stack[-1] in ('p','li','option','optgroup','tr','td','th','dt','dd'):
            self.stack.pop()
        if not self.stack or self.stack[-1] != tag:
            self.errors.append(f'fermeture inattendue </{tag}> (pile: {self.stack[-4:]})')
        else:
            self.stack.pop()

def fetch(path):
    try:
        r = urllib.request.urlopen(BASE + path, timeout=60)
        return r.status, r.read().decode('utf-8')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8')

PROBLEMES = re.compile(r'(<b>(Warning|Notice|Deprecated|Fatal error|Parse error)</b>|Uncaught [A-Za-z]+|SQLSTATE\[|on line <b>\d+|Stack trace:)')

def check(path, attendu=200):
    status, html = fetch(path)
    errs = []
    if status != attendu: errs.append(f'HTTP {status} (attendu {attendu})')
    m = PROBLEMES.search(html)
    if m: errs.append('PHP/SQL: ' + html[max(0,m.start()-80):m.start()+200].replace('\n',' '))
    c = Checker(); c.feed(html); c.close()
    if c.stack and c.stack != []: errs.append('balises non fermées: ' + str(c.stack))
    errs += c.errors[:3]
    if not html.rstrip().endswith('</html>'): errs.append('page tronquée')
    return path, errs, html

cas = []
q = urllib.parse.urlencode
# Accueil
cas += [('index.php',200), ('index.php?hemisphere=SH',200), ('index.php?hemisphere=XX',200), ('index.php?hemisphere[]=SH',200)]
# Créatures : toutes les heures et tous les mois, deux hémisphères
for h in ('NH','SH'):
    for m in range(1,13):
        for hr in range(24):
            cas.append(('creatures.php?'+q({'hemisphere':h,'mois':m,'heure':hr}),200))
zones = col("SELECT DISTINCT zone_lieu FROM lieu")
for z in zones: cas.append(('creatures.php?'+q({'zone':z,'mois':7,'heure':21}),200))
for t in ('poisson','insecte','xx',''): cas.append(('creatures.php?'+q({'type':t}),200))
for mt in ('beau','pluie','neige'): cas.append(('creatures.php?'+q({'meteo':mt,'mois':6,'heure':10}),200))
for tri in ('prix_desc','prix_asc','nom','numero','DROP'): cas.append(('creatures.php?'+q({'tri':tri}),200))
cas += [('creatures.php?'+q({'q':"bass"}),200), ('creatures.php?'+q({'q':"%_\\'\"<script>"}),200),
        ('creatures.php?'+q({'q':'a'*500}),200), ('creatures.php?mois=13&heure=24',200), ('creatures.php?mois=-1&heure=abc',200),
        ('creatures.php?mois[]=1&heure[]=2&type[]=poisson&q[]=x',200), ('creatures.php?depart=1',200), ('creatures.php?depart=1&mois=9&heure=12&type=insecte',200)]
# Fiches créatures
for i in col("SELECT id_creature FROM creature"):
    cas.append((f'creature.php?id={i}',200)); cas.append((f'creature.php?id={i}&hemisphere=SH',200))
cas += [('creature.php?id=0',404), ('creature.php?id=999999',404), ('creature.php?id=abc',404), ('creature.php',404), ('creature.php?id[]=1',404)]
# Meubles : vue saison
evts = col("SELECT DISTINCT id_evenement FROM proposer")
for e in evts:
    cas.append((f'meubles.php?evenement={e}',200)); cas.append((f'meubles.php?evenement={e}&hemisphere=SH',200))
types = col("SELECT DISTINCT type_evenement FROM evenement")
for t in types: cas.append(('meubles.php?'+q({'type_evt':t,'du':'2026-01-01','au':'2026-12-31'}),200))
for m in range(1,13):
    cas.append((f'meubles.php?du=2026-{m:02d}-01&au=2026-{m:02d}-28',200))
    cas.append((f'meubles.php?du=2026-{m:02d}-15&hemisphere=SH',200))
cas += [('meubles.php?du=2026-12-31&au=2026-01-01',200), ('meubles.php?du=2026-02-30',200), ('meubles.php?du=1999-01-01',200),
        ('meubles.php?du=2026-01-01&au=2030-01-01',200), ('meubles.php?du=2060-12-31',200), ('meubles.php?du=2000-01-01',200),
        ('meubles.php?evenement=999999',200), ('meubles.php?evenement=abc&categorie=xyz',200), ('meubles.php?du[]=x&au[]=y&q[]=z',200),
        ('meubles.php?'+q({'q':'chair','du':'2026-12-01','au':'2026-12-31'}),200)]
cats = col("SELECT id_categorie FROM categorie")
for c in cats: cas.append((f'meubles.php?categorie={c}&du=2026-12-01&au=2026-12-31',200))
# Meubles : catalogue
couleurs = col("SELECT DISTINCT id_couleur FROM colorer")
for c in couleurs: cas.append((f'meubles.php?vue=catalogue&couleur={c}',200))
for c in cats: cas.append((f'meubles.php?vue=catalogue&categorie={c}',200))
for tri in ('nom','prix_asc','prix_desc','variantes','x'): cas.append((f'meubles.php?vue=catalogue&tri={tri}',200))
for p in (0,1,2,10,50,84,85,86,99999): cas.append((f'meubles.php?vue=catalogue&page={p}',200))
cas += [('meubles.php?vue=catalogue&bricolable=1&saisonnier=1',200), ('meubles.php?vue=catalogue&q=zzzzzz',200),
        ('meubles.php?vue=catalogue&'+q({'q':'%'}),200), ('meubles.php?vue=autre',200)]
# Fiches meubles (toutes)
for i in col("SELECT id_meuble FROM meuble"):
    cas.append((f'meuble.php?id={i}',200))
cas += [('meuble.php?id=1&hemisphere=SH',200), ('meuble.php?id=0',404), ('meuble.php?id=99999999',404)]
# Villageois
for champ, sql in (('espece','SELECT id_espece FROM espece'), ('personnalite','SELECT id_personnalite FROM personnalite'),
                   ('loisir','SELECT id_loisir FROM loisir'), ('style','SELECT id_style FROM style'), ('couleur','SELECT DISTINCT id_couleur FROM preferer')):
    for v in col(sql): cas.append((f'villageois.php?{champ}={v}',200))
for m in range(1,13): cas.append((f'villageois.php?mois={m}&tri=anniversaire',200))
for g in ('Male','Female','x'): cas.append((f'villageois.php?genre={g}',200))
for p in (1,2,13,14,15,999): cas.append((f'villageois.php?page={p}',200))
cas += [('villageois.php?tri=espece',200), ('villageois.php?'+q({'q':'bo'}),200), ('villageois.php?espece=99999',200),
        ('villageois.php?espece[]=1',200)]
for i in col("SELECT id_villageois FROM villageois"):
    cas.append((f'villageois_fiche.php?id={i}',200))
cas += [('villageois_fiche.php?id=0',404), ('villageois_fiche.php?id=5000',404)]

print(len(cas), 'requêtes', flush=True)
echecs = 0
with cf.ThreadPoolExecutor(6) as ex:
    futs = {ex.submit(check, p, a): p for p, a in cas}
    for fut in cf.as_completed(futs):
        path, errs, _ = fut.result()
        if errs:
            echecs += 1
            if echecs <= 25: print('ÉCHEC', path, errs[:3])
print('échecs :', echecs, '/', len(cas))
sys.exit(1 if echecs else 0)
