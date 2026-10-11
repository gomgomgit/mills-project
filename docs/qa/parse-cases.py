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
        for k in ('langkah', 'hasil'):
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
               'peran': '', 'prasyarat': '', 'langkah': [], 'hasil': [],
               'prioritas': '', 'catatan': '', 'jalankan': '',
               'berlaku': '', 'catatan_jalan': ''}
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
               'Jalankan di': 'jalankan', 'Cara menjalankan': 'langkah',
               'Berlaku untuk': 'berlaku', 'Catatan jalan': 'catatan_jalan'}.get(label)
        # "Prasyarat tambahan untuk Grading" dan kerabatnya: labelnya memuat
        # nama hal yang bersangkutan, jadi tidak bisa didaftar satu per satu.
        # Isinya digabung ke Prasyarat — di sanalah penguji mencarinya.
        #
        # Digabung dengan MENAMBAH, bukan menimpa, dan dalam dua arah: pada
        # MB-FORM-01 label tambahannya datang SEBELUM "Prasyarat:", jadi
        # penugasan biasa membuat baris kedua menimpa yang pertama dan
        # catatan Grading hilang tanpa jejak.
        if key is None and label.startswith('Prasyarat'):
            key = 'prasyarat'
            val = f'{label}: {val}'
        if key == 'prasyarat' and cur['prasyarat']:
            val = cur['prasyarat'] + ' — ' + val
        if key is None:
            # Label yang tidak dikenal DULU dibuang tanpa jejak, dan isi
            # "Berlaku untuk" pada enam kasus hilang dari ketiga Excel tanpa
            # ada yang tahu. Sekarang ia berteriak ke stderr: menambah label
            # baru di markdown tidak lagi bisa menguapkan isinya.
            print(f'LABEL TIDAK DIKENAL pada {cur["id"]}: "{label}" — isinya '
                  f'TIDAK masuk ke cases.json', file=sys.stderr)
            field = None; continue
        field = key
        if key in ('langkah', 'hasil'):
            if val: cur[key].append(val)
        else:
            cur[key] = val
        continue
    # continuation lines
    if field in ('langkah', 'hasil') and ln.strip():
        cur[field].append(ln.strip())
    elif field and ln.strip():
        # Pemisah bagian ("---") BUKAN lanjutan nilai. Tanpa penjagaan ini,
        # kasus terakhir pada setiap bagian mendapat Prioritas "Tinggi ---",
        # dan nilai itu lalu tidak cocok dengan daftar pilihan kolom Hasil
        # maupun rumus Rekap yang mencocokkan "Tinggi" persis.
        if ln.strip().strip('-') == '':
            field = None
            continue
        cur[field] = (cur[field] + ' ' + ln.strip()).strip()

flush()
print(len(cases))
json.dump(cases, open(sys.argv[1], 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
