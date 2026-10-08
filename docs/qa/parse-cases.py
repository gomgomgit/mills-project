"""
Mengurai blackbox-test-cases.md menjadi JSON — satu objek per kasus uji.

    python3 parse-cases.py cases.json

Berpasangan dengan build-xlsx.py:

    python3 parse-cases.py cases.json
    python3 build-xlsx.py cases.json Mill-Smart-Log-Test-Case-Blackbox.xlsx

Markdown-nya adalah sumber kebenaran. Jangan menyunting .xlsx langsung —
suntingan akan hilang pada pembuatan berikutnya.
"""
import re, json, sys, os

SRC = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'blackbox-test-cases.md')
lines = open(SRC, encoding='utf-8').read().split('\n')

CASE_RE = re.compile(r'^### ([A-Z][A-Z-]*-\d+) — (.*)$')
SEC_RE  = re.compile(r'^## (\d+)\. (.*)$')
FIELD_RE = re.compile(r'^- \*\*([^:]+):\*\*\s*(.*)$')

cases, section = [], ''
cur = None

def flush():
    global cur
    if cur:
        for k in ('langkah',):
            cur[k] = '\n'.join(cur[k]).strip()
        cases.append(cur)
    cur = None

field = None
for ln in lines:
    m = SEC_RE.match(ln)
    if m:
        flush(); section = m.group(2); field = None; continue
    m = CASE_RE.match(ln)
    if m:
        flush()
        cur = {'id': m.group(1), 'title': m.group(2), 'section': section,
               'peran': '', 'prasyarat': '', 'langkah': [], 'hasil': '',
               'prioritas': '', 'catatan': '', 'jalankan': ''}
        field = None
        continue
    if cur is None:
        continue
    if ln.startswith('### ') or ln.startswith('## '):
        flush(); field = None; continue
    m = FIELD_RE.match(ln)
    if m:
        label, val = m.group(1).strip(), m.group(2).strip()
        key = {'Peran': 'peran', 'Prasyarat': 'prasyarat', 'Langkah': 'langkah',
               'Hasil diharapkan': 'hasil', 'Prioritas': 'prioritas',
               'Catatan QA': 'catatan', 'Catatan': 'catatan',
               'Jalankan di': 'jalankan', 'Cara menjalankan': 'langkah'}.get(label)
        if key is None:
            field = None; continue
        field = key
        if key == 'langkah':
            if val: cur['langkah'].append(val)
        else:
            cur[key] = val
        continue
    # continuation lines
    if field == 'langkah' and ln.strip():
        cur['langkah'].append(ln.strip())
    elif field and ln.strip() and field != 'langkah':
        cur[field] = (cur[field] + ' ' + ln.strip()).strip()

flush()
print(len(cases))
json.dump(cases, open(sys.argv[1], 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
