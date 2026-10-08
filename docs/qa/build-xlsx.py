"""
Membangun workbook Excel dari keluaran parse-cases.py.

    python3 build-xlsx.py cases.json Mill-Smart-Log-Test-Case-Blackbox.xlsx

Butuh openpyxl. Enam sheet: Panduan, Kasus Uji, Rekap, Lingkungan & Akun,
Matriks Stasiun, Sudah Diketahui.

Sheet Kasus Uji memuat SATU BARIS PER JALAN (1.509 baris), bukan per kasus
unik: kasus yang berlaku di 18 stasiun dijabarkan menjadi 18 baris dan nama
stasiunnya disubstitusikan ke dalam langkahnya. Sheet 'Lembar Hasil' yang
dulu terpisah dibuang karena isinya jadi duplikat — kolom pengisiannya kini
menempel langsung pada barisnya.

Daftar stasiun, daftar tingkat Kelola, dan tabel K-1..K-7 ditulis di berkas
ini — bukan diurai dari markdown — karena bentuknya tabel, bukan kasus uji.
Kalau markdown-nya berubah di bagian itu, ubah juga di sini.
"""
import json, re, sys
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.utils import get_column_letter

CASES = json.load(open(sys.argv[1], encoding='utf-8'))
OUT = sys.argv[2]

# MODE RINGKAS (argumen ketiga 'ringkas') — memangkas empat kasus yang
# menguji KOMPONEN BERSAMA sebanyak 18 kali menjadi 3 kali.
#
# Keempatnya menguji perilaku dua komponen yang dipakai ke-18 layar Data
# Preview: ListFilterBar (MB-PRV-02/04/08) dan RecordVerificationStatus
# (MB-PRV-07). Kodenya SATU, jadi perilakunya tidak dapat berbeda per
# stasiun — menjalankannya 18 kali tidak menjawab pertanyaan baru.
#
# BUKAN setiap pola 18× layak dipangkas. Ke-18 stasiun punya service,
# komponen Livewire, Form, dan Data Preview-nya SENDIRI-SENDIRI (18 berkas
# masing-masing), jadi bug di satu stasiun tidak mengatakan apa pun tentang
# yang lain. Yang dipangkas hanya yang terbukti berbagi kode. Breadcrumb,
# misalnya, TIDAK dipangkas: ia ditulis ulang di setiap view, bukan komponen.
#
# Tiga stasiun yang dipilih sama dengan MB-SYNC: satu 24-slot, satu
# per-transaksi, satu berkolom status — supaya bentuk daftar yang berbeda
# tetap terlewati.
RINGKAS = len(sys.argv) > 3 and sys.argv[3] == 'ringkas'

SHARED_COMPONENT_CASES = {
    'MB-PRV-02': 'ListFilterBar',
    'MB-PRV-04': 'ListFilterBar',
    'MB-PRV-07': 'RecordVerificationStatus',
    'MB-PRV-08': 'ListFilterBar',
}

# ── palet ────────────────────────────────────────────────────────────────
INK   = '1F2933'
HEAD  = '12343B'
BAND  = 'F2F6F7'
ACCENT= 'D20000'
GREY  = '6B7280'
SOFT  = 'E8EDEF'

F_TITLE  = Font(name='Calibri', size=16, bold=True, color=HEAD)
F_SUB    = Font(name='Calibri', size=10, color=GREY, italic=True)
F_HEAD   = Font(name='Calibri', size=10, bold=True, color='FFFFFF')
F_BODY   = Font(name='Calibri', size=10, color=INK)
F_BOLD   = Font(name='Calibri', size=10, bold=True, color=INK)
F_MONO   = Font(name='Consolas', size=9, color=INK)
F_RED    = Font(name='Calibri', size=10, bold=True, color=ACCENT)
F_H2     = Font(name='Calibri', size=12, bold=True, color=HEAD)

FILL_HEAD = PatternFill('solid', fgColor=HEAD)
FILL_BAND = PatternFill('solid', fgColor=BAND)
FILL_SOFT = PatternFill('solid', fgColor=SOFT)
FILL_HI   = PatternFill('solid', fgColor='FDE8E8')
FILL_IN   = PatternFill('solid', fgColor='FFFDF0')

THIN = Side(style='thin', color='D4DADD')
BOX  = Border(left=THIN, right=THIN, top=THIN, bottom=THIN)

TOP  = Alignment(vertical='top', wrap_text=True)
TOPL = Alignment(vertical='top', horizontal='left', wrap_text=True)
TOPC = Alignment(vertical='top', horizontal='center', wrap_text=True)
MIDC = Alignment(vertical='center', horizontal='center', wrap_text=True)

# Kode jalan masuk dari bagian 0.3 markdown. Di Excel kodenya DIPERLUAS
# menjadi kalimat, supaya satu sel dapat dijalankan tanpa berpindah sheet —
# di markdown kodenya dipertahankan supaya sumbernya tetap enak dibaca.
PRELUDE = {
 '[W-LOGIN]':     'Login di web (buka /login, isi username dan password akun pada kolom Peran, tekan Login)',
 '[W-BROWSE]':    'Login di web, lalu sidebar > Data > <Stasiun> (/data/<stasiun>)',
 '[W-DETAIL]':    'Login di web, buka /data/<stasiun> (<Stasiun>), lalu klik salah satu baris tabel',
 '[W-FORM-NEW]':  'Login di web, buka /data/<stasiun> (<Stasiun>), lalu tekan Tambah (/data/<stasiun>/create)',
 '[W-FORM-EDIT]': 'Login di web, buka satu record <Stasiun>, lalu tekan Edit (/data/<stasiun>/<id>/edit)',
 '[W-REPORT]':    'Login di web, sidebar > Laporan Stasiun (/reports), tekan tile <Stasiun> (/reports/<stasiun>), lalu pilih Periode dan Production Line',
 '[W-DASH]':      'Login di web, lalu sidebar > Dashboard (/dashboard)',
 '[W-PPA]':       'Login di web, lalu sidebar > Production Process Activity (/production-process-activity)',
 '[W-MGMT]':      'Login di web, lalu sidebar > Laporan Manajemen (/reports/management)',
 '[W-PERIOD]':    'Login di web, lalu sidebar > Kelola Periode Pelaporan (/master-data/periods)',
 '[W-TREE]':      'Login di web, lalu sidebar > Struktur Mills (/master-data/tree-view)',
 '[W-KELOLA]':    'Login di web, lalu sidebar > Kelola <Tingkat> (/master-data/<tingkat>)',
 '[W-USERS]':     'Login di web, lalu sidebar > Kelola User & Role (/users)',
 '[W-MILLSET]':   'Login di web, lalu sidebar > Mills Setting (/mill-settings)',
 '[W-PWD]':       'Login di web, lalu sidebar > Ganti Password (/settings/password)',
 '[M-LOGIN]':     'Login di mobile (buka aplikasi, isi username dan password, tekan Login), mendarat di /home',
 '[M-LIST]':      'Login di mobile, tekan Station List (/stations); pilih Production Line bila diminta',
 '[M-MON]':       'Login di mobile, Station List, lalu tekan tile <Stasiun> (/stations/<stasiun>/monitor)',
 '[M-FORM]':      'Login di mobile, buka Monitor <Stasiun>, lalu tekan New Data (/stations/<stasiun>/form/<id>)',
 '[M-PREVIEW]':   'Login di mobile, buka Monitor <Stasiun>, lalu tekan Load Data (/stations/<stasiun>/preview)',
 '[M-REPORT]':    'Login di mobile, Dashboard & Reporting > Reporting, tekan tile <Stasiun> (/reports/<stasiun>), lalu pilih Periode dan Production Line',
 '[M-PWD]':       'Login di mobile, menu (ikon tiga garis) > Ganti Password (/settings/password)',
}


def expand(s):
    """Mengganti setiap kode jalan masuk dengan kalimat penuhnya."""
    for code, full in PRELUDE.items():
        s = s.replace('`' + code + '`', full).replace(code, full)
    return s


def clean(s):
    """markdown -> teks datar untuk sel Excel"""
    s = expand(s)
    s = s.replace('**', '').replace('`', '')
    s = s.replace('§', 'bagian ')
    return s.strip()

def put(ws, r, c, val, font=F_BODY, align=TOP, fill=None, border=True):
    cell = ws.cell(row=r, column=c, value=val)
    cell.font = font; cell.alignment = align
    if fill: cell.fill = fill
    if border: cell.border = BOX
    return cell

def header_row(ws, r, labels, widths):
    for i, (lab, w) in enumerate(zip(labels, widths), start=1):
        put(ws, r, i, lab, F_HEAD, MIDC, FILL_HEAD)
        ws.column_dimensions[get_column_letter(i)].width = w
    ws.row_dimensions[r].height = 28

def sheet_title(ws, text, sub, span):
    ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=span)
    c = ws.cell(row=1, column=1, value=text); c.font = F_TITLE
    c.alignment = Alignment(vertical='center')
    ws.row_dimensions[1].height = 26
    ws.merge_cells(start_row=2, start_column=1, end_row=2, end_column=span)
    c = ws.cell(row=2, column=1, value=sub); c.font = F_SUB
    c.alignment = Alignment(vertical='center', wrap_text=True)
    ws.row_dimensions[2].height = 30

# ── data pendukung ───────────────────────────────────────────────────────
STATIONS18 = ['Weighbridge','Grading','Cages Track','Sterilizer','Threshing','Pressing',
  'Clarification','Kernel Plant','Boiler Room','Effluent Plant','Depricarping','Engine Room',
  'Process Water','Storage Tank','Solid Waste Disposal','Kernel Dispatch','CPO Dispatch',
  'Process Quality Control']

REPORT11 = ['Weighbridge','Grading','Cages Track','Sterilizer','Threshing','Pressing',
  'Clarification','Kernel Plant','Boiler Room','Depricarping','Storage Tank']

KELOLA8 = ['Corporate','Company','Business Unit','Production Line','Station',
  'Machinery Group','Machinery','User & Role']

STATION_SLUG = {
 'Weighbridge': 'weighbridge', 'Grading': 'grading', 'Cages Track': 'cages-track',
 'Sterilizer': 'sterilizer', 'Threshing': 'threshing', 'Pressing': 'pressing',
 'Clarification': 'clarification', 'Kernel Plant': 'kernel-plant',
 'Boiler Room': 'boiler-room', 'Effluent Plant': 'effluent-plant',
 'Depricarping': 'depricarping', 'Engine Room': 'engine-room',
 'Process Water': 'process-water', 'Storage Tank': 'storage-tank',
 'Solid Waste Disposal': 'solid-waste-disposal', 'Kernel Dispatch': 'kernel-dispatch',
 'CPO Dispatch': 'cpo-dispatch', 'Process Quality Control': 'process-quality-control',
}

KELOLA_SLUG = {
 'Corporate': 'corporates', 'Company': 'companies', 'Business Unit': 'business-units',
 'Production Line': 'production-lines', 'Station': 'stations',
 'Machinery Group': 'machinery-groups', 'Machinery': 'machinery',
 'User & Role': 'users',
}


def fill(text, variant):
    """Mengganti penanda <Stasiun>/<stasiun>/<Tingkat> dengan varian nyata.

    Tanpa ini setiap baris masih menyuruh penguji mengganti sendiri —
    dan penguji yang salah mengganti menguji stasiun yang salah.
    """
    if variant == '\u2014':
        return text
    # 'Depricarping (24 slot)' -> nama dasar 'Depricarping' untuk slug-nya
    base = variant.split(' (')[0].strip()
    if base in STATION_SLUG:
        text = text.replace('<Stasiun>', variant).replace('<stasiun>', STATION_SLUG[base])
    elif base in KELOLA_SLUG:
        text = (text.replace('<Tingkat>', variant).replace('<tingkat>', KELOLA_SLUG[base])
                    .replace('<Stasiun>', variant))
    else:
        return text

    # Sesudah substitusi, nama stasiunnya sering muncul DUA KALI dalam satu
    # langkah: sekali dari teks jalan masuk ("sidebar > Data > Sterilizer
    # (/data/sterilizer)") dan sekali dari kalimat kasusnya sendiri ("untuk
    # <Stasiun>"). Yang kedua diluluhkan — ia sudah tidak menambah apa pun,
    # dan langkah yang mengulang namanya terbaca seperti salin-tempel.
    out = []
    for line in text.split('\n'):
        if line.count(variant) > 1:
            head, sep, tail = line.partition(variant)
            tail = tail.replace(' untuk ' + variant, '', 1)
            line = head + sep + tail
        out.append(line)
    return '\n'.join(out)


SYNC3 = ['Depricarping (24 slot)','Weighbridge (per-transaksi)','Effluent Plant (kolom status)']
NO_SLOT = {'Weighbridge','Grading','Kernel Dispatch','CPO Dispatch'}

def variants(cid):
    if RINGKAS and cid in SHARED_COMPONENT_CASES:
        return SYNC3
    p = re.sub(r'-\d+$', '', cid)
    if p in ('MB-MON','MB-FORM','MB-PRV','WB-BRW','WB-DTL','WB-FRM'): return STATIONS18
    if p in ('LP-W','LP-M'): return REPORT11
    if p == 'MB-SYNC': return SYNC3
    if p == 'MD-KEL': return KELOLA8
    return ['—']

def applies(cid, station):
    if cid in ('MB-FORM-03','MB-FORM-04') and station in NO_SLOT:
        return 'Tidak berlaku — stasiun per-transaksi, pakai MB-FORM-11'
    if cid == 'MB-FORM-11' and station not in NO_SLOT:
        return 'Tidak berlaku — hanya 4 stasiun per-transaksi'
    return ''

TOTAL_JALAN = sum(len(variants(c['id'])) for c in CASES)
JALAN_TXT = f'{TOTAL_JALAN:,}'.replace(',', '.')

wb = Workbook()

# ═════════════════════ 1. PANDUAN ═══════════════════════════════════════
ws = wb.active; ws.title = 'Panduan'
ws.sheet_view.showGridLines = False
sheet_title(ws, 'Mill Smart Log — Test Case Blackbox'
               + (' (RINGKAS)' if RINGKAS else ''),
  (f'Pengujian fungsional Web dan Mobile. {len(CASES)} kasus unik, ± {JALAN_TXT} jalan, 154 layar tercakup.'
   + (' VERSI RINGKAS — empat kasus komponen bersama dipangkas dari 18× ke 3×; lihat kotak merah di bawah.' if RINGKAS else
      ' Seluruh kasus dapat dijalankan tanpa melihat kode.')), 4)
for i, w in enumerate([26, 62, 30, 30], start=1):
    ws.column_dimensions[get_column_letter(i)].width = w

r = 4
blocks = [
 ('Isi berkas ini', [
   ('Kasus Uji', f'Satu baris per JALAN — {JALAN_TXT} baris. Kasus yang berlaku di 18 stasiun sudah dijabarkan '
                 'menjadi 18 baris, dan nama stasiunnya ada DI DALAM langkahnya. Kolom berlatar kuning diisi penguji.'),
   ('Rekap', 'Hitungan otomatis Lulus/Gagal/Tidak diuji per bagian. Terisi sendiri dari Kasus Uji.'),
   ('Lingkungan & Akun', 'Yang harus disiapkan sebelum menguji, 6 akun uji, dan matriks peran.'),
   ('Matriks Stasiun', '18 stasiun beserta keanehan masing-masing yang perlu diperhatikan.'),
   ('Sudah Diketahui', 'Dua daftar yang WAJIB dibaca sebelum melapor: catatan yang sudah diketahui tim, '
                       'dan hal yang terlihat seperti bug tetapi bukan.'),
 ]),
 ('Mengapa ada "pola" dan bukan 108 set kasus', [
   ('Alasannya', 'Monitor, Form, dan Data Preview (mobile) serta Data Browser, Detail, dan Form (web) '
                 'berbentuk sama di ke-18 stasiun. Polanya ditulis sekali lalu dijalankan 18×; '
                 'yang berbeda per stasiun ada di sheet Matriks Stasiun.'),
   ('Akibatnya', 'Kolom "Jml Jalan" di sheet Kasus Uji menyebut berapa kali satu kasus dijalankan. '
                 '18× berarti sekali per stasiun, 11× sekali per stasiun yang sudah punya laporan.'),
 ]),
 ('Aturan pengisian kolom Hasil', [
   ('Lulus', 'Yang terlihat SAMA dengan Hasil Diharapkan, seluruhnya.'),
   ('Gagal', 'Ada perbedaan. Tulis APA YANG TERLIHAT di kolom Catatan, bukan dugaan sebabnya.'),
   ('Tidak diuji', 'Prasyaratnya tidak dapat disiapkan. JANGAN ditandai Lulus.'),
   ('Terhalang', 'Kasus lain yang gagal membuat kasus ini tidak bisa dijalankan. Sebut ID penghalangnya.'),
 ]),
 ('Sebelum menutup satu putaran', [
   ('Periksa tiga hal', '1) Setiap kasus Prioritas Tinggi punya Hasil, bukan sel kosong. '
                        '2) Setiap baris Gagal punya No. Bug. '
                        '3) Setiap baris Tidak diuji menyebut prasyarat mana yang tak bisa disiapkan.'),
 ]),
]
for head, rows in blocks:
    put(ws, r, 1, head, F_H2, Alignment(vertical='center'), border=False)
    ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=4)
    ws.row_dimensions[r].height = 24
    r += 1
    for a, b in rows:
        put(ws, r, 1, a, F_BOLD, TOPL, FILL_SOFT)
        put(ws, r, 2, b, F_BODY, TOPL)
        ws.merge_cells(start_row=r, start_column=2, end_row=r, end_column=4)
        ws.row_dimensions[r].height = max(16, 15 * (len(b) // 78 + 1))
        r += 1
    r += 1

if RINGKAS:
    # Kotak ini WAJIB ada di versi ringkas. Pembaca yang tidak tahu bahwa
    # cakupannya dipersempit akan melaporkan "semua lulus" untuk rencana
    # yang memang lebih kecil — dan itu pernyataan yang menyesatkan.
    put(ws, r, 1, 'VERSI RINGKAS — BACA SEBELUM MEMAKAI', F_RED, TOPL, FILL_HI)
    put(ws, r, 2,
        'Empat kasus dipangkas dari 18x menjadi 3x, menghemat 60 jalan '
        '(1.509 menjadi 1.449):\n'
        '  - MB-PRV-02, MB-PRV-04, MB-PRV-08 menguji ListFilterBar\n'
        '  - MB-PRV-07 menguji RecordVerificationStatus\n'
        'Keduanya KOMPONEN BERSAMA yang dipakai ke-18 layar Data Preview: satu '
        'berkas kode, jadi perilakunya tidak dapat berbeda per stasiun. Dijalankan '
        'pada tiga stasiun berbeda bentuk (Depricarping 24-slot, Weighbridge '
        'per-transaksi, Effluent Plant berkolom status).\n\n'
        'RISIKONYA, dinyatakan terbuka: kalau kelak salah satu layar Data Preview '
        'berhenti memakai komponen itu dan menulis filternya sendiri, pemangkasan '
        'ini tidak akan menangkapnya. Versi PENUH tetap ada di berkas tanpa '
        'akhiran "-Ringkas" — pakai itu untuk putaran rilis.\n\n'
        'TIDAK dipangkas meski juga 18x: breadcrumb (MB-MON-06) ditulis ulang di '
        'setiap view, bukan komponen bersama; dan ke-18 stasiun punya service, '
        'Form, serta Data Preview-nya sendiri-sendiri — bug di satu stasiun tidak '
        'mengatakan apa pun tentang yang lain.',
        F_BODY, TOPL, FILL_HI)
    ws.merge_cells(start_row=r, start_column=2, end_row=r, end_column=4)
    ws.row_dimensions[r].height = 190
    r += 2

put(ws, r, 1, 'Kasus yang prasyaratnya tidak tersedia BUKAN kasus yang lulus. Menandainya Lulus '
              'membuat laporan pengujian menyatakan sesuatu yang tidak pernah diperiksa — dan itu '
              'lebih berbahaya daripada kolom yang dibiarkan kosong.', F_RED, TOPL, FILL_HI)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=4)
ws.row_dimensions[r].height = 44

# ═════════════════════ 2. KASUS UJI (satu baris per jalan) ═════════════
ws = wb.create_sheet('Kasus Uji')
ws.sheet_view.showGridLines = False
sheet_title(ws, 'Kasus Uji — setiap jalan dijabarkan satu baris'
               + (' (RINGKAS)' if RINGKAS else ''),
  'Kasus yang berlaku di 18 stasiun dijabarkan menjadi 18 baris, masing-masing '
  'menyebut stasiunnya DI DALAM langkahnya — tidak ada lagi "18×" yang harus '
  'diterjemahkan sendiri. Kolom berlatar kuning diisi penguji.', 17)
COLS = ['No','ID Jalan','ID Kasus','Stasiun / Varian','Bagian','Judul','Peran',
        'Prasyarat','Langkah','Hasil Diharapkan','Prioritas','Catatan QA',
        'Tanggal','Penguji','Hasil','No. Bug','Catatan / apa yang terlihat']
W    = [5, 26, 12, 22, 17, 32, 14, 24, 54, 54, 9, 30, 11, 13, 13, 11, 32]
header_row(ws, 4, COLS, W)
ws.freeze_panes = 'E5'

dv = DataValidation(type='list', formula1='"Lulus,Gagal,Tidak diuji,Terhalang"',
                    allow_blank=True, showDropDown=False)
dv.error = 'Pilih salah satu: Lulus, Gagal, Tidak diuji, Terhalang.'
dv.errorTitle = 'Nilai tidak dikenal'
ws.add_data_validation(dv)


def wrapped(text, per_line):
    """Tinggi baris dihitung dari jumlah baris NYATA plus pembungkusannya.

    Dari total panjang teks saja, kolom Langkah justru yang terpotong.
    """
    return sum(max(1, -(-len(ln) // per_line)) for ln in text.split('\n'))


r, n = 5, 0
DV_ROWS = []
for c in CASES:
    for v in variants(c['id']):
        n += 1
        na = applies(c['id'], v)
        band = FILL_BAND if n % 2 == 0 else None
        langkah = fill(clean(c['langkah']), v)
        hasil = fill(clean(c['hasil']), v)
        prasyarat = fill(clean(c['prasyarat']) or '—', v)
        catatan = fill(clean(c['catatan']) or '', v)
        id_jalan = c['id'] if v == '—' else f"{c['id']} / {v}"

        put(ws, r, 1, n, F_BODY, TOPC, band)
        put(ws, r, 2, id_jalan, F_MONO, TOPL, band)
        put(ws, r, 3, c['id'], F_MONO, TOPL, band)
        put(ws, r, 4, v, F_BOLD if v != '—' else F_BODY, TOPL, band)
        put(ws, r, 5, clean(c['section']), F_BODY, TOPL, band)
        put(ws, r, 6, clean(c['title']), F_BOLD, TOPL, band)
        put(ws, r, 7, clean(c['peran']), F_BODY, TOPL, band)
        put(ws, r, 8, prasyarat, F_BODY, TOPL, band)
        if na:
            # Baris yang tidak berlaku: langkahnya DIGANTI alasannya, bukan
            # dibiarkan seolah dapat dijalankan.
            put(ws, r, 9, na, F_RED, TOPL, FILL_SOFT)
            put(ws, r, 10, na, F_RED, TOPL, FILL_SOFT)
        else:
            put(ws, r, 9, langkah, F_BODY, TOPL, band)
            put(ws, r, 10, hasil, F_BODY, TOPL, band)
        put(ws, r, 11, c['prioritas'], F_RED if c['prioritas'] == 'Tinggi' else F_BODY,
            TOPC, FILL_HI if c['prioritas'] == 'Tinggi' else band)
        put(ws, r, 12, catatan, F_BODY, TOPL, band)
        for col in (13, 14, 15, 16, 17):
            put(ws, r, col, None, F_BODY, TOPL, FILL_SOFT if na else FILL_IN)
        if not na:
            DV_ROWS.append(r)

        tall = max(wrapped(na or langkah, 44), wrapped(na or hasil, 44),
                   wrapped(catatan, 26), 1)
        ws.row_dimensions[r].height = max(16, 13.2 * tall + 4)
        r += 1

# Satu rentang validasi per blok baris bersambung — bukan satu per sel.
spans, start_row, prev = [], None, None
for row in DV_ROWS:
    if start_row is None:
        start_row = prev = row; continue
    if row == prev + 1:
        prev = row; continue
    spans.append((start_row, prev)); start_row = prev = row
if start_row is not None:
    spans.append((start_row, prev))
for a, b in spans:
    dv.add(f'O{a}:O{b}' if a != b else f'O{a}')

ws.auto_filter.ref = f'A4:Q{r-1}'
LAST = r - 1
TOTAL_RUNS = n

# ═════════════════════ 4. REKAP ═════════════════════════════════════════
ws = wb.create_sheet('Rekap')
ws.sheet_view.showGridLines = False
sheet_title(ws, 'Rekap — terisi otomatis',
  'Angka di sini dihitung dari sheet Kasus Uji. Tidak perlu diisi tangan.', 9)
COLS = ['Bagian','Awalan ID','Kasus unik','Jml jalan','Lulus','Gagal','Tidak diuji',
        'Terhalang','Belum diisi']
header_row(ws, 4, COLS, [34, 14, 13, 12, 11, 11, 13, 12, 13])

GROUPS = [
 ('Aturan lintas-sistem','LS-'), ('Autentikasi & Akun','AU-'),
 ('Mobile — Operasional Stasiun','MB-'), ('Web — Data Stasiun','WB-'),
 ('Laporan Periode Stasiun','LP-'), ('Dashboard & Laporan lain','DS-'),
 ('Master Data','MD-'), ('User & Role, Pengaturan Mill','AD-'),
]
r = 5
for name, pfx in GROUPS:
    uniq = sum(1 for c in CASES if c['id'].startswith(pfx))
    runs = sum(len(variants(c['id'])) for c in CASES if c['id'].startswith(pfx))
    put(ws, r, 1, name, F_BOLD, TOPL)
    put(ws, r, 2, pfx, F_MONO, TOPC)
    put(ws, r, 3, uniq, F_BODY, TOPC)
    put(ws, r, 4, runs, F_BODY, TOPC)
    base = f"'Kasus Uji'!$C$5:$C${LAST}"
    hasil = f"'Kasus Uji'!$O$5:$O${LAST}"
    for col, crit in ((5, 'Lulus'), (6, 'Gagal'), (7, 'Tidak diuji'), (8, 'Terhalang')):
        put(ws, r, col, f'=COUNTIFS({base},"{pfx}*",{hasil},"{crit}")', F_BODY, TOPC)
    put(ws, r, 9, f'=D{r}-E{r}-F{r}-G{r}-H{r}', F_BODY, TOPC)
    r += 1

put(ws, r, 1, 'TOTAL', F_BOLD, TOPL, FILL_SOFT)
put(ws, r, 2, '', F_BODY, TOPC, FILL_SOFT)
put(ws, r, 3, f'=SUM(C5:C{r-1})', F_BOLD, TOPC, FILL_SOFT)
put(ws, r, 4, f'=SUM(D5:D{r-1})', F_BOLD, TOPC, FILL_SOFT)
for col in (5, 6, 7, 8, 9):
    L = get_column_letter(col)
    put(ws, r, col, f'=SUM({L}5:{L}{r-1})', F_BOLD, TOPC, FILL_SOFT)
r += 2
put(ws, r, 1, 'Kasus Prioritas Tinggi yang belum diisi', F_BOLD, TOPL, FILL_HI)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=3)
put(ws, r, 4, f'=COUNTIFS(\'Kasus Uji\'!$K$5:$K${LAST},"Tinggi",'
              f'\'Kasus Uji\'!$O$5:$O${LAST},"")', F_RED, TOPC, FILL_HI)
r += 1
put(ws, r, 1, 'Baris Gagal yang belum punya No. Bug', F_BOLD, TOPL, FILL_HI)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=3)
put(ws, r, 4, f'=COUNTIFS(\'Kasus Uji\'!$O$5:$O${LAST},"Gagal",'
              f'\'Kasus Uji\'!$P$5:$P${LAST},"")', F_RED, TOPC, FILL_HI)


# ═════════════════════ 5. LINGKUNGAN & AKUN ═════════════════════════════
ws = wb.create_sheet('Lingkungan & Akun')
ws.sheet_view.showGridLines = False
sheet_title(ws, 'Lingkungan & akun uji',
  'Seluruh kasus mengandaikan isi sheet ini sudah disiapkan. Tanpa 6 akun di bawah, '
  'kasus isolasi mill tidak dapat diuji sama sekali.', 6)
for i, w in enumerate([30, 58, 18, 18, 18, 30], start=1):
    ws.column_dimensions[get_column_letter(i)].width = w

r = 4
def block_head(r, text, span=6):
    put(ws, r, 1, text, F_H2, Alignment(vertical='center'), border=False)
    ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=span)
    ws.row_dimensions[r].height = 24
    return r + 1

r = block_head(r, 'Yang harus siap sebelum menguji')
for lab in ('Hal', 'Keterangan'):
    pass
put(ws, r, 1, 'Hal', F_HEAD, MIDC, FILL_HEAD)
put(ws, r, 2, 'Keterangan', F_HEAD, MIDC, FILL_HEAD)
ws.merge_cells(start_row=r, start_column=2, end_row=r, end_column=6)
r += 1
PREP = [
 ('Aplikasi Web', 'Dibuka di browser desktop. Lebar layar minimal 1280px untuk layar tabel.'),
 ('Aplikasi Mobile', 'Dibuka di perangkat atau emulator. Lebar acuan 390px.'),
 ('Koneksi', 'Perlu bisa DIMATIKAN untuk menguji mode offline mobile.'),
 ('Data master', 'Minimal: 1 Corporate, 1 Company, 2 Mill, masing-masing 2 Production Line, '
                 'dan stasiun aktif untuk ke-18 jenis.'),
 ('Periode Pelaporan', 'Minimal satu periode yang mencakup hari ini, dengan stasiun berstatus Terbuka.'),
]
for i, (a, b) in enumerate(PREP):
    band = FILL_BAND if i % 2 else None
    put(ws, r, 1, a, F_BOLD, TOPL, band)
    put(ws, r, 2, b, F_BODY, TOPL, band)
    ws.merge_cells(start_row=r, start_column=2, end_row=r, end_column=6)
    ws.row_dimensions[r].height = max(16, 13.5 * (len(b) // 92 + 1))
    r += 1
r += 1

r = block_head(r, 'Akun uji — minimal 6')
for i, lab in enumerate(['Kode akun','Peran','Mill','Dipakai untuk'], start=1):
    put(ws, r, i, lab, F_HEAD, MIDC, FILL_HEAD)
ws.merge_cells(start_row=r, start_column=4, end_row=r, end_column=6)
r += 1
AKUN = [
 ('OPR-A','Station Operator','Mill A','Alur input mobile'),
 ('SPV-A','Supervisor','Mill A','Input + verifikasi "Checked"'),
 ('MGR-A','Mill Management','Mill A','Verifikasi "Acknowledged", Pengaturan Mill'),
 ('SPV-B','Supervisor','Mill B','Menguji data Mill A tidak terlihat'),
 ('ADM','Admin','— (tidak terikat mill)','Master data, Periode Pelaporan'),
 ('NOMILL','Supervisor','tanpa mill','Menguji sistem menolak dengan jelas'),
]
for i, row in enumerate(AKUN):
    band = FILL_BAND if i % 2 else None
    put(ws, r, 1, row[0], F_MONO, TOPL, band)
    put(ws, r, 2, row[1], F_BODY, TOPL, band)
    put(ws, r, 3, row[2], F_BOLD if row[0] in ('SPV-B','NOMILL') else F_BODY, TOPL, band)
    put(ws, r, 4, row[3], F_BODY, TOPL, band)
    ws.merge_cells(start_row=r, start_column=4, end_row=r, end_column=6)
    r += 1
put(ws, r, 1, 'Akun NOMILL sengaja dibuat cacat. Beberapa kasus menguji bahwa sistem MENOLAK '
              'DENGAN PESAN YANG MENYEBUT SEBABNYA alih-alih menampilkan halaman kosong — '
              'halaman kosong terbaca sebagai "tidak ada data", padahal yang benar adalah '
              '"akun Anda belum diatur".', F_RED, TOPL, FILL_HI)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=6)
ws.row_dimensions[r].height = 44
r += 2

r = block_head(r, 'Matriks peran — acuan seluruh kasus LS-01')
put(ws, r, 1, 'Kemampuan', F_HEAD, MIDC, FILL_HEAD)
for i, lab in enumerate(['Operator','Supervisor','Mill Management','Admin'], start=2):
    put(ws, r, i, lab, F_HEAD, MIDC, FILL_HEAD)
put(ws, r, 6, 'Catatan', F_HEAD, MIDC, FILL_HEAD)
r += 1
MATRIX = [
 ('Login Mobile','v','v','v','v','Admin bukan alur yang dirancang'),
 ('Login Web','v terbatas','v','v','v','Operator: hanya 3 layar, lihat baris terbawah'),
 ('Input data stasiun (Mobile)','v','v','v','—',''),
 ('Input data stasiun (Web)','—','v','v','v',''),
 ('Isi Checked By','—','v','—','v',''),
 ('Isi Acknowledged By','—','—','v','v',''),
 ('Lihat Data Browser / Detail (Web)','—','v','v','v',''),
 ('Lihat "Data Saya" (Web)','v','v','—','—',''),
 ('Laporan periode stasiun (Web)','—','v','v','v',''),
 ('Laporan periode stasiun (Mobile)','v','v','v','v',''),
 ('Dashboard (Web)','—','v','v','v',''),
 ('Laporan Manajemen','—','—','v','v',''),
 ('Master data (Corporate…Mesin)','—','—','—','v',''),
 ('Periode Pelaporan: tambah/ubah/hapus','—','—','—','v',''),
 ('Tutup / Buka Periode','—','—','—','v','HANYA Admin'),
 ('Pengaturan Mill','—','—','v mill sendiri','v semua mill',''),
 ('Kelola User & Role','—','—','—','v',''),
 ('Pemilih Mill pada laporan','—','—','—','v','HANYA Admin'),
]
for i, row in enumerate(MATRIX):
    band = FILL_BAND if i % 2 else None
    put(ws, r, 1, row[0], F_BOLD, TOPL, band)
    for j in range(4):
        val = row[j + 1]
        put(ws, r, 2 + j, '✓' if val.startswith('v') and val == 'v' else val,
            F_BODY, TOPC, FILL_HI if val == '—' else band)
    put(ws, r, 6, row[5], F_RED if row[5].startswith('HANYA') else F_BODY, TOPL, band)
    r += 1
put(ws, r, 1, 'Akses web Operator dibatasi pada: Beranda Operator, Ganti Password, dan Data Saya. '
              'Seluruh alamat lain harus menjawab halaman 403 (Akses Ditolak) — bukan halaman '
              'kosong, bukan diarahkan diam-diam ke beranda.', F_BOLD, TOPL, FILL_SOFT)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=6)
ws.row_dimensions[r].height = 32

# ═════════════════════ 6. MATRIKS STASIUN ═══════════════════════════════
ws = wb.create_sheet('Matriks Stasiun')
ws.sheet_view.showGridLines = False
sheet_title(ws, 'Matriks 18 stasiun',
  '"Kolom wajib" = kolom identitas unit yang harus diisi sebelum Simpan. '
  '"Jml kolom bacaan" = banyaknya kolom pengukuran per baris slot waktu, dipakai pada MB-FORM-05. '
  '± berarti jumlahnya berbeda per stasiun dan dibaca dari layar saat menguji.', 7)
COLS = ['No','Stasiun','Kolom wajib','Jml kolom bacaan','Pola','Laporan periode',
        'Keanehan yang perlu diperhatikan']
header_row(ws, 4, COLS, [5, 24, 26, 17, 17, 16, 62])
ws.freeze_panes = 'C5'

ST = [
 (1,'Weighbridge','No. Kartu / Kendaraan','per-transaksi','Per-transaksi','Ada (web+mobile)',
   'BUKAN pola 24 slot — satu record per transaksi timbang, ada arus Masuk & Keluar'),
 (2,'Grading','Parameter grading','per-muatan','Per-transaksi','Ada (web+mobile)',
   'Satu record per muatan, ada beberapa baris parameter'),
 (3,'Cages Track','ID Cage','±','24 slot','Ada (web+mobile)',''),
 (4,'Sterilizer','ID Sterilizer','±','24 slot','Ada (web+mobile)',
   'Ada checkbox "Checked by SPV" per baris — Operator tidak boleh bisa mengisinya'),
 (5,'Threshing','ID Thresher','5','24 slot','Ada (web+mobile)','Punya master target'),
 (6,'Pressing','ID Presser','5','24 slot','Ada (web+mobile)','Punya master target'),
 (7,'Clarification','ID unit','±','24 slot','Ada (web+mobile)',''),
 (8,'Kernel Plant','ID unit kernel plant','7','24 slot','Ada (web+mobile)',
   'Punya master target. Dua pasang kolom berbagi satu standar (2 ripple mill, 2 kernel silo)'),
 (9,'Boiler Room','ID boiler','±','24 slot','Ada (web+mobile)',''),
 (10,'Effluent Plant','ID unit','14','24 slot','BELUM dibangun',
   'PALING BANYAK KOLOM. Ada 3 kolom status pilihan (on/off/fault, run/stop) dan 2 kolom teks bebas'),
 (11,'Depricarping','ID Presser','7','24 slot','Ada (web+mobile)',
   'Punya master target. Ada kolom Downtime (menit) berupa angka + kolom Temuan'),
 (12,'Engine Room','ID unit','±','24 slot','Belum',''),
 (13,'Process Water','ID unit','±','24 slot','Belum','Ada kolom shift & petugas'),
 (14,'Storage Tank','ID tangki','±','24 slot','Ada (web+mobile)',''),
 (15,'Solid Waste Disposal','ID unit','±','24 slot','Belum',''),
 (16,'Kernel Dispatch','No. Kartu / Kendaraan','per-transaksi','Per-transaksi','Belum',
   'Pola per-transaksi, ada kolom status pilihan'),
 (17,'CPO Dispatch','No. Kartu / Kendaraan','per-transaksi','Per-transaksi','Belum',
   'Pola per-transaksi'),
 (18,'Process Quality Control','ID unit','±','24 slot','Belum',''),
]
r = 5
for i, row in enumerate(ST):
    band = FILL_BAND if i % 2 else None
    put(ws, r, 1, row[0], F_BODY, TOPC, band)
    put(ws, r, 2, row[1], F_BOLD, TOPL, band)
    put(ws, r, 3, row[2], F_BODY, TOPL, band)
    put(ws, r, 4, row[3], F_BODY, TOPC, band)
    put(ws, r, 5, row[4], F_BODY, TOPC, FILL_HI if row[4] == 'Per-transaksi' else band)
    put(ws, r, 6, row[5], F_BODY if row[5].startswith('Ada') else F_RED, TOPC, band)
    put(ws, r, 7, row[6], F_BODY, TOPL, band)
    ws.row_dimensions[r].height = max(16, 13.5 * (len(row[6]) // 66 + 1))
    r += 1
r += 1
put(ws, r, 1, 'Weighbridge, Grading, Kernel Dispatch, dan CPO Dispatch tidak memakai 24 slot waktu. '
              'Untuk keempat stasiun ini LEWATI MB-FORM-03 dan MB-FORM-04 (aturan urutan slot) dan '
              'ganti dengan MB-FORM-11. Di sheet Kasus Uji baris-baris itu sudah ditandai '
              '"Tidak berlaku".', F_RED, TOPL, FILL_HI)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=7)
ws.row_dimensions[r].height = 44
r += 1
put(ws, r, 1, '11 stasiun sudah punya laporan periode, jadi LP- dijalankan 11× untuk web dan 11× '
              'untuk mobile. Tile stasiun yang laporannya belum ada harus KELABU DAN TIDAK DAPAT '
              'DITEKAN — diuji oleh DS-04 dan DS-07, bukan hilang dari layar.', F_BOLD, TOPL, FILL_SOFT)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=7)
ws.row_dimensions[r].height = 32

# ═════════════════════ 7. SUDAH DIKETAHUI ═══════════════════════════════
ws = wb.create_sheet('Sudah Diketahui')
ws.sheet_view.showGridLines = False
sheet_title(ws, 'Baca ini sebelum melapor',
  'Dua daftar. Yang pertama sudah diketahui tim dan bukan temuan baru. Yang kedua TERLIHAT '
  'seperti bug tetapi perilakunya benar — keenamnya akan dilaporkan oleh QA yang belum '
  'membaca halaman ini.', 3)
for i, w in enumerate([10, 58, 62], start=1):
    ws.column_dimensions[get_column_letter(i)].width = w

r = 4
put(ws, r, 1, 'Catatan yang sudah diketahui tim pengembang', F_H2,
    Alignment(vertical='center'), border=False)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=3); r += 1
put(ws, r, 1, 'Kalau QA menemukannya, cukup rujuk nomornya alih-alih membuat laporan bug baru.',
    F_SUB, TOPL, border=False)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=3); r += 1
put(ws, r, 1, 'No', F_HEAD, MIDC, FILL_HEAD)
put(ws, r, 2, 'Hal', F_HEAD, MIDC, FILL_HEAD)
put(ws, r, 3, 'Mengapa begitu', F_HEAD, MIDC, FILL_HEAD); r += 1
KNOWN = [
 ('K-1','Satuan suhu ditulis C, bukan °C pada laporan Kernel Plant (web & mobile)',
  'Disengaja: nilainya datang dari data master apa adanya, dan mengubahnya di tampilan akan '
  'membuat web dan mobile berselisih.'),
 ('K-2','SELESAI 2026-10-08 — penamaan Shell Bin Kernel Loss pada Kernel Plant sudah seragam',
  'Kolom yang dulu bernama shell_loss_percent kini "Shell Bin Kernel Loss" di Form, Detail, '
  'Data Preview, Laporan, dan header ekspor CSV — mengikuti nama standar di master. Nama lamanya '
  'menyebut zat yang salah: kehilangan SHELL, padahal yang diukur KERNEL yang terbuang ke shell '
  'bin. Angkanya tidak pernah salah dan tidak berubah, hanya namanya. Yang diperiksa QA: label '
  'barunya konsisten di kelima tempat itu, dan header CSV berbunyi "Shell Bin Kernel Loss (%)".'),
 ('K-3','SELESAI 2026-10-08 — jumlah kolom bacaan pada laporan Depricarping sudah benar',
  'Dulu berbunyi "minimal satu dari ENAM kolom bacaan diisi"; kolom bacaan Depricarping ada '
  'SEMBILAN, dan layar yang sama menyebut "sembilan" di tempat lain. Salah di WEB DAN MOBILE '
  '(catatan ini dulu menyebut mobile saja), dan bukan kosmetik: angka itu dipakai pembaca untuk '
  'menafsirkan penyebut cakupan. Asal-usulnya salin-tempel dari Threshing dan Pressing, yang '
  'memang enam kolom. Kini dijaga uji yang menurunkan angkanya dari READING_FIELDS.'),
 ('K-4','Laporan Depricarping (Mobile): tiga angka pada blok downtime tampil tanpa gaya',
  'Kosmetik, sudah tercatat.'),
 ('K-5','DIPERBAIKI 2026-10-08 — catatan ini dulu salah',
  'Ia berbunyi "Dashboard hanya meringkas 3 stasiun, bukan 18 — disengaja". Blok tiga-stasiun '
  'itu SUDAH DIBUANG pada 2026-09-17 atas permintaan user. Keadaan sebenarnya: halaman '
  '/dashboard adalah TAMPILAN DEMO dengan angka hard-coded — tanpa filter tanggal, tanpa '
  'pemilih mill, tidak membaca data stasiun sama sekali. Ia menyatakan dirinya demo (judul '
  '"CPO Mill Demo" + chip "Data dummy"). Ringkasan tiga stasiun kini hanya ada di balik API '
  '/api/dashboard/summary, bukan di layar. DS-01..DS-03 ditulis ulang mengikuti keadaan ini.'),
 ('K-6','Satu kasus uji otomatis kadang gagal acak (DashboardServiceTest)',
  'Tidak memengaruhi perilaku aplikasi; sedang diselidiki.'),
 ('K-7','Cakupan baris kode belum diukur',
  'Tidak ada hubungannya dengan hasil pengujian manual.'),
]
for i, row in enumerate(KNOWN):
    band = FILL_BAND if i % 2 else None
    put(ws, r, 1, row[0], F_MONO, TOPC, band)
    put(ws, r, 2, row[1], F_BOLD, TOPL, band)
    put(ws, r, 3, row[2], F_BODY, TOPL, band)
    ws.row_dimensions[r].height = max(16, 13.5 * (max(len(row[1]) // 60, len(row[2]) // 66) + 1))
    r += 1
r += 2

put(ws, r, 1, 'Hal yang TERLIHAT seperti bug tetapi BUKAN', F_H2,
    Alignment(vertical='center'), border=False)
ws.merge_cells(start_row=r, start_column=1, end_row=r, end_column=3); r += 1
put(ws, r, 1, 'Kasus', F_HEAD, MIDC, FILL_HEAD)
put(ws, r, 2, 'Kelihatan salah', F_HEAD, MIDC, FILL_HEAD)
put(ws, r, 3, 'Sebenarnya', F_HEAD, MIDC, FILL_HEAD); r += 1
NOTBUG = [
 ('LS-16','Laporan tidak menandai nilai yang jelas di luar batas',
  'DISENGAJA. Kolom targetnya teks bebas yang bentuknya tidak dibatasi, dan satu sel bisa '
  'memuat dua angka dengan satuan berbeda. Ada kalimat di layar yang menjelaskannya.'),
 ('MB-FORM-04','Slot waktu 01:00 dianggap SESUDAH 23:00',
  'BENAR. Shift dimulai 07:00, jadi urutannya 07:00 → 23:00 → 00:00 → 06:00.'),
 ('DS-02','Dashboard menampilkan angka yang sama untuk semua peran dan semua mill',
  'BENAR. Halaman itu demo dengan angka hard-coded, dan menyatakannya lewat chip "Data dummy". '
  'Jangan jalankan uji isolasi mill di sana — ia tidak membaca mill sama sekali.'),
 ('DS-10','Satu stasiun menunjukkan "belum ada input hari ini" DAN "input terakhir 3 menit lalu"',
  'BENAR. Dua jendela waktu yang berbeda: "hari ini" dibatasi tanggal, "input terakhir" tidak. '
  'Keterangan di halaman menjelaskannya.'),
 ('LP-W-05','Persen cakupan 0,0% di satu periode dan tanda pisah di periode lain, keduanya "tanpa data"',
  'BENAR, DAN KEDUANYA BERBEDA. 0,0% = penyebutnya ada, nol yang terisi. '
  'Tanda pisah = penyebutnya tidak terbentuk.'),
 ('LP-W-03','Jumlah slot terisi pada cakupan LEBIH BESAR daripada penyebut parameter mana pun',
  'BENAR. Slot yang hanya mengisi kolom temuan terhitung terisi tanpa menambah penyebut parameter.'),
 ('LS-08','Production Line tunggal tetap harus dipilih manual',
  'DISENGAJA. Memilihnya otomatis membuat pembaca mengira ia melihat seluruh mill.'),
]
for i, row in enumerate(NOTBUG):
    band = FILL_BAND if i % 2 else None
    put(ws, r, 1, row[0], F_MONO, TOPC, band)
    put(ws, r, 2, row[1], F_BODY, TOPL, band)
    put(ws, r, 3, row[2], F_BOLD, TOPL, FILL_HI)
    ws.row_dimensions[r].height = max(16, 13.5 * (max(len(row[1]) // 60, len(row[2]) // 64) + 1))
    r += 1

wb.active = 0
print('cases', len(CASES), 'runs', TOTAL_RUNS, 'last row', LAST)
wb.save(OUT)
