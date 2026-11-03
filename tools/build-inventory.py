"""Static legacy inventory. Does not bootstrap WordPress or connect to services."""
from pathlib import Path
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
paths = [ROOT / 'h3-management.php']
for folder in ('includes', 'admin', 'templates', 'models', 'src', 'runtime'):
    paths.extend(sorted((ROOT / folder).rglob('*.php')))
result = {'format': 1, 'scope': 'Static legacy PHP; no request execution', 'sources': {}, 'shortcodes': [], 'actions': []}
method_pattern = re.compile(r'(?m)^\s*(?:(?:public|private|protected|static)\s+)*function\s+([A-Za-z_][A-Za-z_0-9]*)\s*\(')
for path in sorted(paths):
    relative = path.relative_to(ROOT).as_posix()
    raw = path.read_bytes()
    text = raw.decode('utf-8-sig').replace('\r\n', '\n')
    result['sources'][relative] = hashlib.sha256(text.encode('utf-8')).hexdigest()
    methods = list(method_pattern.finditer(text))
    slices = {}
    for i, method in enumerate(methods):
        body = text[method.start():methods[i+1].start() if i+1 < len(methods) else len(text)]
        line = text.count('\n', 0, method.start()) + 1
        # Trim leading whitespace/newlines to point at declaration.
        leading = method.group(0)[:len(method.group(0))-len(method.group(0).lstrip())]
        line += leading.count('\n')
        slices[method.group(1)] = (body, line)
        fields = sorted(set((m.group(1), m.group(2)) for m in re.finditer(r"\$_(GET|POST|FILES|COOKIE|REQUEST)\s*\[\s*['\"]([^'\"]+)['\"]\s*\]", body)))
        if fields:
            cases = sorted(set(re.findall(r"\bcase\s+['\"]([^'\"]+)['\"]\s*:", body)))
            result['actions'].append({'file': relative, 'method': method.group(1), 'line': line, 'fields': [a+':'+b for a,b in fields], 'case_labels': cases})
    for match in re.finditer(r"add_shortcode\(\s*['\"]([^'\"]+)['\"]\s*,\s*(?:array\s*\(|\[)\s*&?\$this\s*,\s*['\"]([^'\"]+)['\"]", text):
        tag, handler = match.groups()
        body, line = slices[handler]
        defaults = re.search(r'shortcode_atts\s*\(\s*(?:array\s*\((.*?)\)|\[(.*?)\])\s*,\s*\$atts', body, re.S)
        expression = re.sub(r'\s+', ' ', (defaults.group(1) if defaults.group(1) is not None else defaults.group(2)).strip()) if defaults else None
        result['shortcodes'].append({'tag': tag, 'handler': handler, 'file': relative, 'line': line, 'defaults_source': expression})
result['shortcodes'].sort(key=lambda x: x['tag'])

def write(name, value):
    with (ROOT/name).open('w', encoding='utf-8', newline='\n') as handle:
        handle.write(value)

write('docs/inventory.json', json.dumps(result, ensure_ascii=False, indent=2)+'\n')
short = ['# Shortcode-Inventar', '', 'Generiert mit `python tools/build-inventory.py`. Statische Registrierung und Standardattribute aus dem Altbestand. Kein Laufzeittest. `keine direkten Defaults` bedeutet: kein direkter shortcode_atts-Aufruf im Handler; nicht zwingend keine mittelbaren Parameter.', '', '| Shortcode | Handler / Quelle | Standardattribute (PHP-Ausdruck) |', '|---|---|---|']
for item in result['shortcodes']:
    source = '../'+item['file']+'#L'+str(item['line'])
    defaults = (item['defaults_source'] or 'keine direkten Defaults').replace('|', '\\|')
    short.append('| `'+item['tag']+'` | ['+item['handler']+']('+source+') | '+defaults+' |')
short.extend(['', 'h3-ticker-datenbank-change ist ein historisches Wartungswerkzeug mit Schreibwirkung. In v2 geschütztes Werkzeug statt öffentlicher Ausführung; nicht zur Vorschau aufrufen.', '', 'Die alten Defaults werden dokumentiert, nicht als fachlich korrekt bestätigt. Insbesondere race/event-Fallbacks und active sind pro Handler zu prüfen.', ''])
write('docs/shortcodes.md', '\n'.join(short))
actions = ['# Request- und Aktionsinventar', '', 'Generiert mit `python tools/build-inventory.py`. Literale GET/POST/FILES/COOKIE/REQUEST-Felder je Methode und case-Labels aus deren Quelltext. Dynamische Schlüssel, indirekte Aufrufe und JavaScript sind nicht vollständig erfasst. Labels können auch interne Schalter sein. Kein Berechtigungs- oder Erreichbarkeitsnachweis.', '', '| Methode / Quelle | Request-Felder | case-Labels |', '|---|---|---|']
for item in result['actions']:
    source='../'+item['file']+'#L'+str(item['line'])
    actions.append('| ['+item['method']+']('+source+') | '+', '.join('`'+x+'`' for x in item['fields'])+' | '+', '.join('`'+x+'`' for x in item['case_labels'])+' |')
actions.extend(['', 'Nicht ausführen: Ein Aufruf kann schreiben, Mails auslösen oder Medien ändern. Referenztests müssen synthetische Daten verwenden und echte Seiteneffekte verhindern.', ''])
write('docs/actions.md', '\n'.join(actions))
print('Inventory: {} shortcodes, {} request-bearing methods, {} source files'.format(len(result['shortcodes']), len(result['actions']), len(result['sources'])))
