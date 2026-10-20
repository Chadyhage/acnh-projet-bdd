"""Test de toutes les pages (python verification/tester_pages.py) : beaucoup de combinaisons de paramètres GET,
y compris des valeurs invalides ou piégées. Échoue si statut HTTP inattendu, avertissement PHP, erreur SQL ou HTML mal formé."""
import urllib.request, urllib.parse, re, concurrent.futures as cf
from html.parser import HTMLParser
import os, sys
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


cur = connexion().cursor()
def col(sql):
    cur.execute(sql)
    return [r[0] for r in cur.fetchall()]

VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'}
class Checker(HTMLParser):
    """Vérifie que chaque balise ouverte est bien fermée."""
    def __init__(self):
        super().__init__(); self.stack = []; self.errors = []
    def handle_starttag(self, tag, attrs):
        if tag not in VOID: self.stack.append(tag)
    def handle_endtag(self, tag):
        if tag in VOID: return
        while self.stack and self.stack[-1] != tag and self.stack[-1] in ('p', 'li', 'option', 'tr', 'td', 'th'):
            self.stack.pop()
        if not self.stack or self.stack[-1] != tag:
            self.errors.append(f'fermeture inattendue </{tag}>')
        else:
            self.stack.pop()

PROBLEMES = re.compile(r'(<b>(Warning|Notice|Deprecated|Fatal error|Parse error)</b>|Uncaught [A-Za-z]+|SQLSTATE\[|on line <b>\d+)')

def check(path):
    try:
        r = urllib.request.urlopen(BASE + path, timeout=60)
        status, html = r.status, r.read().decode('utf-8')
    except urllib.error.HTTPError as e:
        status, html = e.code, e.read().decode('utf-8')
    errs = []
    if status != 200: errs.append(f'HTTP {status}')
    m = PROBLEMES.search(html)
    if m: errs.append('PHP/SQL : ' + html[max(0, m.start() - 80):m.start() + 200].replace('\n', ' '))
    c = Checker(); c.feed(html); c.close()
    if c.stack: errs.append('balises non fermées : ' + str(c.stack))
    errs += c.errors[:3]
    if not html.rstrip().endswith('</html>'): errs.append('page tronquée')
    return path, errs

q = urllib.parse.urlencode
cas = ['index.php']
# Poissons et insectes : toutes les heures, tous les mois, deux hémisphères, trois types
for t in ('', 'poisson', 'insecte'):
    for h in ('NH', 'SH'):
        for m in range(1, 13):
            for hr in range(24):
                cas.append('creatures.php?' + q({'type': t, 'hemisphere': h, 'mois': m, 'heure': hr}))
cas += ['creatures.php', 'creatures.php?mois=13&heure=24', 'creatures.php?mois=-1&heure=abc', 'creatures.php?type=xx&hemisphere=ZZ',
        'creatures.php?mois[]=1&heure[]=2&type[]=poisson', 'creatures.php?' + q({'type': "'; DROP TABLE creature; --"})]
# Meubles de saison : chaque mois de 2026 et 2027, deux hémisphères, et des dates invalides
for an in (2026, 2027):
    for m in range(1, 13):
        for h in ('NH', 'SH'):
            cas.append(f'meubles.php?du={an}-{m:02d}-01&au={an}-{m:02d}-28&hemisphere={h}')
cas += ['meubles.php', 'meubles.php?du=2026-12-31&au=2026-01-01', 'meubles.php?du=2026-02-30', 'meubles.php?du=1999-01-01',
        'meubles.php?du=2000-01-01&au=2060-12-31', 'meubles.php?du[]=x&au[]=y', 'meubles.php?' + q({'du': "2026-01-01' OR 1=1 --"})]
# Villageois : chaque valeur de chaque filtre, des combinaisons et des valeurs invalides
for champ, sql in (('espece', 'SELECT id_espece FROM espece'), ('personnalite', 'SELECT id_personnalite FROM personnalite'),
                   ('style', 'SELECT id_style FROM style')):
    for v in col(sql):
        cas.append(f'villageois.php?{champ}={v}')
for g in ('Male', 'Female', 'x'):
    cas.append(f'villageois.php?genre={g}')
for e in col('SELECT id_espece FROM espece'):
    for s in col('SELECT id_style FROM style'):
        cas.append(f'villageois.php?espece={e}&style={s}&genre=Female')
cas += ['villageois.php', 'villageois.php?espece=99999', 'villageois.php?espece=abc&style=-1', 'villageois.php?espece[]=1',
        'villageois.php?' + q({'genre': '<script>alert(1)</script>'})]

print(len(cas), 'requêtes', flush=True)
echecs = 0
with cf.ThreadPoolExecutor(6) as ex:
    for path, errs in ex.map(check, cas):
        if errs:
            echecs += 1
            if echecs <= 25: print('ÉCHEC', path, errs[:3])
print('échecs :', echecs, '/', len(cas))
sys.exit(1 if echecs else 0)
