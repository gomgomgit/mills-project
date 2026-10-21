{{--
    Alur Proses Stasiun PKS (/alur-proses, nama rute `alur-proses`).

    HALAMAN PUBLIK, BERDIRI SENDIRI, DAN DI LUAR BLOK ASDLC — mengikuti
    preseden `/health` dan `/beranda` di routes/web.php, yang keduanya
    berkomentar demikian. Ia belum punya business-spec maupun tech-spec; bila
    kelak angkanya disambungkan ke data nyata atau stasiunnya dibuat dapat
    diketuk menuju form/laporannya, ia WAJIB lewat alur ASDLC lebih dulu
    (Phase 2 -> 3 -> 4) karena saat itu ia berhenti menjadi halaman statis.

    TIDAK MEMAKAI <x-layouts.app> DENGAN SENGAJA. Tur ini satu panggung
    `position:sticky` setinggi 100dvh yang digulir; sidebar 240px milik layout
    app akan memotong panggungnya dan membuat kameranya tidak pernah terlihat
    utuh. Karena itu halaman ini membawa kerangka dokumennya sendiri —
    doctype, head, body — dan token desainnya DISALIN NILAINYA dari
    partials/design-tokens (brand #249360, aksen #D97706, teks #1F2937, muted
    #6B7280, border #E3E3E3, tint #D6F6E5) alih-alih di-include, supaya satu
    blok <style> tunggal tetap dapat dibaca utuh.

    BERASAL DARI berkas desain pks-intro.html (2026-10-20). Tiga penyelarasan
    ke master station_types dicatat di komentar array STOPS di bawah; yang
    paling penting: 17 perhentian menjadi 18, karena desain aslinya
    menggabungkan Weighbridge dan Grading.

    ANGKA MASIH CONTOH — nol di antaranya datang dari basis data. Lencana
    "Angka contoh" di kanan atas menyatakannya dan JANGAN dihapus sebelum
    angkanya sungguh nyata.

    Three.js r128 (MIT) di-VENDOR LOKAL di public/vendor/three/three.min.js,
    bukan dari CDN: pabrik tidak selalu punya internet, dan halaman yang
    diam-diam jatuh ke mode tanpa-3D setiap kali jaringan putus akan terbaca
    sebagai rusak. Bila WebGL benar-benar tidak tersedia, kelas .no3d memberi
    latar grid dan seluruh teks serta navigasinya tetap berfungsi.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Alur Proses Stasiun — Mills Smart Log</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<style>
/* Tata letak: satu pabrik isometrik yang menempel (sticky) dan digulir
   melewati 18 stasiun, URUT `sort_order` master station_types — bukan urut
   alur proses. Palet dan font mengikuti token desain web
   (partials/design-tokens): brand #249360, aksen #D97706, Inter. */
:root{
  --paper:#F3F4F6;
  --card:#FFFFFF;
  --text:#1F2937;
  --mute:#6B7280;
  --line:#E3E3E3;
  --tint:#D6F6E5;
  --green:#249360;
  --green-dk:#1D7A4E;
  --fruit:#D97706;
  --display:"Inter","Segoe UI",system-ui,sans-serif;
  --mono:ui-monospace,SFMono-Regular,Consolas,"Liberation Mono",monospace;
  color-scheme:light;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{background:var(--paper);color:var(--text);font:400 15px/1.55 var(--display);-webkit-font-smoothing:antialiased;overflow-x:hidden}
:focus-visible{outline:2px solid var(--fruit);outline-offset:3px;border-radius:4px}
h1,p{margin:0}

.top{position:fixed;top:0;left:0;right:0;z-index:30;display:flex;align-items:center;gap:12px;pointer-events:none;
  padding-top:calc(16px + env(safe-area-inset-top,0px));padding-bottom:16px;
  padding-left:max(16px,env(safe-area-inset-left,0px));padding-right:max(16px,env(safe-area-inset-right,0px))}
.mark{display:inline-flex;align-items:center;gap:10px;background:var(--green);color:#fff;border-radius:6px;padding:8px 14px 8px 10px;pointer-events:auto;
  box-shadow:0 8px 24px -12px rgba(20,32,26,.5)}
.mark b{font:800 13px/1 var(--display);letter-spacing:.04em;background:var(--fruit);color:#fff;border-radius:4px;padding:5px 6px}
.mark span{font:600 14px/1.1 var(--display)}
.tag{margin-left:auto;font:500 11px/1 var(--mono);letter-spacing:.1em;text-transform:uppercase;color:var(--mute);background:var(--card);
  border:1px solid var(--line);border-radius:999px;padding:8px 12px;pointer-events:auto}

a.tag--link{text-decoration:none;color:var(--green);font-weight:600;letter-spacing:.06em;transition:background .15s,color .15s}
a.tag--link:hover{background:var(--tint);color:var(--green-dk)}

/* Tinggi panggung = (jumlah stasiun - 1) langkah + satu layar. DULU
   `70vh * 16` untuk 17 stasiun; kini 17 langkah untuk 18 stasiun. Angka ini
   WAJIB ikut berubah bersama jumlah stasiun: ia yang menentukan stepPx(), dan
   bila ia tertinggal maka stasiun terakhir tidak akan pernah tercapai oleh
   gulir — tile-nya ada, tick-nya ada, dan hanya kameranya yang tidak sampai. */
.intro{position:relative;height:calc(70vh * 17 + 100vh)}
.stage{position:sticky;top:0;height:100vh;height:100dvh;overflow:hidden;background:var(--paper)}
#scene{display:block;width:100%;height:100%}
.stage.no3d{background:
  linear-gradient(90deg,rgba(31,107,74,.07) 1px,transparent 1px) 0 0/40px 40px,
  linear-gradient(rgba(31,107,74,.07) 1px,transparent 1px) 0 0/40px 40px,var(--paper)}

.card{position:absolute;z-index:5;left:max(28px,env(safe-area-inset-left,0px));top:calc(50% + 30px);transform:translateY(-50%);
  width:min(390px,calc(100% - 32px));max-height:calc(100% - 110px);overflow:auto;background:var(--card);padding:20px 22px 16px;border-radius:10px;
  box-shadow:0 24px 60px -24px rgba(20,32,26,.38),0 0 0 1px rgba(20,32,26,.06)}
.count{display:flex;justify-content:space-between;gap:10px;font:500 11.5px/1 var(--mono);letter-spacing:.08em;text-transform:uppercase;color:var(--mute)}
.count b{color:var(--fruit);font-weight:500}
.card h1{font-size:27px;line-height:1.05;font-weight:800;letter-spacing:-.01em;text-transform:uppercase;margin:12px 0 8px;text-wrap:balance}
.card p{color:var(--mute);font-size:14px}
.lbl{display:block;font:500 10px/1 var(--mono);letter-spacing:.1em;text-transform:uppercase;color:var(--mute);margin:14px 0 7px}
.eq{display:flex;flex-wrap:wrap;gap:5px}
.eq span{font:500 11.5px/1.2 var(--mono);background:var(--tint);color:var(--green-dk);border-radius:4px;padding:5px 7px}
.flow{display:grid;grid-template-columns:1fr auto 1fr;align-items:stretch;gap:6px;margin-top:12px}
.flow div{background:#F4F6F2;border-radius:6px;padding:7px 10px;min-width:0}
.flow small{display:block;font:500 10px/1 var(--mono);letter-spacing:.1em;text-transform:uppercase;color:var(--mute);margin-bottom:5px}
.flow span{font:600 13px/1.3 var(--display)}
.flow i{align-self:center;font-style:normal;color:var(--fruit);font-weight:700}
.card dl{margin:10px 0 0;display:grid}
.rec{margin:0;font-size:13px;line-height:1.5;color:var(--text)}
.rec b{font-weight:700}
.rec--none{color:var(--mute);font-style:italic}
.card dd small{font-weight:500;color:var(--mute);font-size:11px}
.card dd .na{color:var(--mute)}
.picker{margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;pointer-events:auto}
.picker select{font:500 11.5px/1 var(--mono);letter-spacing:.02em;color:var(--text);background:var(--card);
  border:1px solid var(--line);border-radius:999px;padding:7px 26px 7px 12px;cursor:pointer;appearance:none;max-width:200px;
  background-image:linear-gradient(45deg,transparent 50%,var(--mute) 50%),linear-gradient(135deg,var(--mute) 50%,transparent 50%);
  background-position:calc(100% - 14px) 12px,calc(100% - 9px) 12px;background-size:5px 5px;background-repeat:no-repeat}
.picker select:disabled{opacity:.5;cursor:not-allowed}
.card dl div{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-top:1px solid var(--line);font-size:13.5px}
.card dt{color:var(--mute)}
.card dd{margin:0;font:500 12.5px/1.45 var(--mono);font-variant-numeric:tabular-nums;text-align:right}
.fade{transition:opacity .22s,transform .22s}
.swap .fade{opacity:0;transform:translateY(6px)}
.foot{margin-top:12px;padding-top:12px;border-top:1px solid var(--line);display:grid;gap:10px}
.ticks{display:grid;grid-template-columns:repeat(18,1fr);gap:3px}
.tick{height:16px;border:0;padding:0;background:transparent;cursor:pointer;position:relative}
.tick::before{content:"";position:absolute;left:0;right:0;top:5px;height:6px;border-radius:3px;background:var(--line);transition:background .2s}
.tick:hover::before{background:#B9CFC3}
.tick.done::before{background:#9CC5B2}
.tick[aria-current="step"]::before{background:var(--fruit)}
.jump{width:100%;height:36px;border-radius:8px;border:1px solid var(--line);background:var(--card);color:var(--text);font:500 13px/1 var(--display);padding:0 32px 0 10px;cursor:pointer;appearance:none;
  background-image:linear-gradient(45deg,transparent 50%,var(--mute) 50%),linear-gradient(135deg,var(--mute) 50%,transparent 50%);
  background-position:calc(100% - 17px) 15px,calc(100% - 12px) 15px;background-size:5px 5px;background-repeat:no-repeat}
.nav{position:absolute;z-index:5;right:max(20px,calc(env(safe-area-inset-right,0px) + 12px));bottom:calc(20px + env(safe-area-inset-bottom,0px));display:flex;gap:6px}
.zoom{position:absolute;z-index:5;right:max(20px,calc(env(safe-area-inset-right,0px) + 12px));bottom:calc(20px + 42px + 8px + env(safe-area-inset-bottom,0px));display:flex;gap:6px}
.nav button,.zoom button{width:42px;height:42px;border-radius:50%;border:1px solid var(--line);background:var(--card);color:var(--text);font:600 18px/1 var(--display);cursor:pointer;
  box-shadow:0 8px 24px -12px rgba(20,32,26,.4)}
.nav button:disabled,.zoom button:disabled{opacity:.4;cursor:default}
.hint{position:absolute;z-index:4;left:50%;bottom:calc(30px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);
  font:500 11px/1 var(--mono);letter-spacing:.14em;text-transform:uppercase;color:var(--mute);transition:opacity .4s;white-space:nowrap}
@media (max-width:720px){
  .mark span{font-size:12.5px}
  .tag{display:none}
  .card{left:12px;right:12px;width:auto;top:auto;bottom:calc(12px + env(safe-area-inset-bottom,0px));transform:none;padding:14px 14px 12px;max-height:58%}
  .card h1{font-size:19px;margin:8px 0 4px}
  /* Pada 390px prosa deskriptif disembunyikan supaya kartunya muat, TETAPI
     data nyata TIDAK: .card > p menyasar paragraf deskripsi stasiun saja,
     bukan .rec, dan #sFacility tetap tampil. Aturan lama (.card p, .card dl)
     menyembunyikan keduanya dan akan membuat versi ponsel kehilangan
     satu-satunya angka nyata di halaman ini. */
  /* `:not(.rec)` BUKAN hiasan: .rec juga sebuah <p> di dalam .fade, jadi
     tanpa pengecualian ini aturannya ikut menyembunyikannya — dan itu
     persis yang terjadi pada versi pertama, yang komentarnya justru
     mengklaim sebaliknya. Diukur di browser pada 390px, bukan dibaca dari
     sumbernya. */
  .card > .fade > p:not(.rec){display:none}
  .rec{font-size:12px}
  .lbl{margin:8px 0 5px}
  .eq{max-height:48px;overflow:hidden}
  .eq span{font-size:10.5px;padding:4px 6px}
  .flow{margin-top:8px}.flow div{padding:6px 8px}.flow span{font-size:12px}
  .foot{margin-top:10px;padding-top:10px;gap:8px}
  .nav{top:calc(70px + env(safe-area-inset-top,0px));bottom:auto;right:12px}
  .nav button,.zoom button{width:36px;height:36px;font-size:15px}
  .zoom{top:calc(70px + 36px + 6px + env(safe-area-inset-top,0px));bottom:auto;right:12px}
  .hint{display:none}
}
@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important}}
</style>

</head>
<body>
<header class="top">
  <div class="mark"><span>Mills Smart Log</span></div>
  {{-- PEMILIH DI HEADER, BUKAN DI KARTU. Kartunya berganti isi tiap kali
       pengguna menggulir; sebuah pemilih yang ikut berganti akan kehilangan
       fokus di tengah pemilihan. Header tidak pernah berganti, jadi di
       sanalah tempatnya.

       Ketiganya <form method="GET"> biasa yang memuat ulang halaman, bukan
       fetch: halaman ini sudah punya tautan dalam berkode (#weighbridge),
       jadi hash-nya dibawa serta dan perhentian yang sedang dibaca kembali ke
       tempatnya sesudah muat ulang. Satu jalur, bukan dua. --}}
  <form class="picker" method="GET" action="{{ route('alur-proses') }}" data-testid="scope-form">
    @if ($overview['scope']['is_admin'])
    <select name="mill" data-testid="mill-select" aria-label="Mill">
      <option value="">Pilih mill…</option>
      @foreach ($overview['mills'] as $mill)
      <option value="{{ $mill['id'] }}" @selected($mill['id'] === $overview['scope']['business_unit_id'])>{{ $mill['name'] }}</option>
      @endforeach
    </select>
    @else
    {{-- Peran terikat mill tidak memilih mill: service membuang nilai kiriman
         apa pun, jadi sebuah <select> di sini hanya akan berbohong. --}}
    <span class="tag" data-testid="mill-fixed">{{ $overview['scope']['business_unit_name'] ?? 'Tanpa mill' }}</span>
    @endif

    <select name="line" data-testid="line-select" aria-label="Production Line" @disabled($overview['scope']['needs_mill'])>
      <option value="">Pilih Production Line…</option>
      @foreach ($overview['lines'] as $line)
      <option value="{{ $line['id'] }}" @selected($line['id'] === $overview['scope']['production_line_id'])>{{ $line['name'] }}</option>
      @endforeach
    </select>

    <select name="hari" data-testid="days-select" aria-label="Jendela hari">
      @foreach ($overview['scope']['allowed_days'] as $d)
      <option value="{{ $d }}" @selected($d === $overview['scope']['days'])>{{ $d }} hari terakhir</option>
      @endforeach
    </select>
    <noscript><button type="submit" class="tag">Terapkan</button></noscript>
  </form>
  {{-- Halaman ini penuh layar tanpa sidebar, jadi ia butuh jalan keluar;
       tanpanya pembaca terjebak di sini. Mengarah ke `home`, yang sudah
       mengalihkan tiap peran ke berandanya masing-masing — satu tautan
       melayani semuanya, dan tidak ada daftar peran kedua yang bisa basi. --}}
  <a class="tag tag--link" href="{{ route('home') }}">Beranda &rarr;</a>
</header>

<section class="intro" id="intro" aria-label="Tur stasiun pabrik kelapa sawit">
  <div class="stage" id="stage">
    <canvas id="scene" aria-hidden="true"></canvas>
    <aside class="card" id="card" aria-live="polite">
      <div class="fade">
        <div class="count"><span><b id="sNum">01</b> / 18</span><span id="sKey">Jembatan timbang</span></div>
        <h1 id="sTitle">Weighbridge</h1>
        <p id="sText">Truk TBS dari kebun ditimbang masuk dan keluar di jembatan timbang. Selisih bruto dan tara menjadi tonase TBS yang diterima pabrik hari itu.</p>
        <span class="lbl">Peralatan</span>
        <div class="eq" id="sEq"><span>Weighbridge</span><span>Timbangan truk</span></div>
        <div class="flow"><div><small>Masuk</small><span id="sIn">Truk TBS dari kebun</span></div><i aria-hidden="true">→</i><div><small>Keluar</small><span id="sOut">Tonase TBS tercatat</span></div></div>
        <span class="lbl" id="sProdLbl">Produksi</span>
        <dl id="sProd"></dl>
        <p class="rec" id="sRec"></p>
      </div>
      <div class="foot">
        <div class="ticks" id="ticks" role="group" aria-label="Pilih stasiun"></div>
        <label class="lbl" for="jump" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Lompat ke stasiun</label>
        <select class="jump" id="jump"></select>
      </div>
    </aside>
    <div class="nav"><button type="button" id="prev" aria-label="Stasiun sebelumnya">↑</button><button type="button" id="next" aria-label="Stasiun berikutnya">↓</button></div>
    <div class="zoom"><button type="button" id="zoomOut" aria-label="Perkecil (−)" title="Perkecil (−)">−</button><button type="button" id="zoomIn" aria-label="Perbesar (+)" title="Perbesar (+)">+</button></div>
    <div class="hint" id="hint">Gulir untuk menyusuri stasiun</div>
  </div>
</section>

<script src="{{ asset('vendor/three/three.min.js') }}"></script>
<script>
(() => {
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ============ 18 STASIUN — DISELARASKAN DENGAN MASTER station_types ============
   SUMBER URUTAN DAN NAMA: tabel `station_types`, dibaca langsung 2026-10-20,
   BUKAN urutan alur proses dan BUKAN urutan deklarasi StationType enum
   (ketiganya memang berbeda). Urutan di bawah = `sort_order` 10..180.

   TIGA PENYELARASAN dari berkas desain aslinya, dan masing-masing sebabnya:

   1. 'FFB Reception' DIPECAH menjadi Weighbridge (10) dan Grading (20).
      Desain aslinya menggabungkan keduanya menjadi satu perhentian, padahal
      di sistem ini keduanya JENIS STASIUN TERPISAH — masing-masing punya
      form, record, dan laporan periodenya sendiri. Menggabungkannya berarti
      dua stasiun yang justru paling banyak recordnya tidak punya perhentian
      sendiri di sini. Geometri 3D-nya memang sudah terpisah: papan
      'WEIGHBRIDGE' di (-48,-20) dan 'GRADING' di (-47,8), jadi pemecahan ini
      tidak menambah satu kotak pun — hanya satu titik fokus kamera lagi.

   2. 'Oil Dispatch' -> 'CPO Dispatch' (170). Nama di master.

   3. 'Depericarping' -> 'Depricarping' (110). Ejaan master memang begitu, dan
      ejaan itu LOAD-BEARING: ia kode stasiun `depricarping` yang dipakai
      REPORT_ROUTES, rute, dan nama berkas di seluruh kode. Mengoreksinya
      menjadi ejaan yang "benar" di sini akan membuat satu-satunya halaman
      yang menyebut stasiun ini berbeda dari delapan belas tempat lain.

   KONSEKUENSI URUTAN sort_order, DISENGAJA DAN PERLU DIKETAHUI: `sort_order`
   hampir mengikuti alur proses, tetapi tidak persis. Tiga lompatan jauh:
   Effluent Plant (100, x=117) -> Depricarping (110, x=-12) -> Engine Room
   (120, x=18). Kameranya karena itu menyeberangi pabrik tiga kali alih-alih
   berjalan maju terus. Itu harga yang dipilih dengan sadar: pembaca yang di
   sini melihat '11 / 18 Depricarping' akan melihat Depricarping sebagai tile
   ke-11 juga di grid Laporan Stasiun. Penomoran yang tidak cocok adalah biaya
   kebenaran; kamera yang melompat hanya biaya rasa.

   ANGKA DI `rows` MASIH CONTOH — belum satu pun tersambung ke data nyata.
   Lencana 'Angka contoh' di kanan atas menyatakannya, dan lencana itu JANGAN
   dihapus sebelum angkanya sungguh datang dari basis data. ============ */
/* ANGKA PRODUKSI dari App\Services\StationProductionService, berkunci kode
   stasiun. Digabung ke STOPS di bawah lewat `code` — BUKAN lewat indeks:
   urutan STOPS mengikuti sort_order master dan akan bergeser lagi bila master
   berubah, sementara kodenya tidak. Penggabungan berbasis indeks sudah pernah
   salah di proyek ini ketika FFB Reception dipecah menjadi dua. */
const LIVE = @json($overview['stations']);
const SCOPE = @json($overview['scope']);
/* Jendela hari datang dari SCOPE, yang diisi service — bukan diketik ulang
   di sini. Angka yang hidup di dua tempat akan berbeda di salah satunya, dan
   yang berbeda diam-diam adalah kalimat di layar. */
const REC_DAYS = SCOPE.days;

const STOPS = [
  {code:'weighbridge', title:'Weighbridge', key:'Jembatan timbang', focus:[-42,-1], size:48, mark:8,
   text:'Truk TBS dari kebun berhenti di timbangan masuk untuk ditimbang bruto, membongkar muatan di loading ramp, lalu berhenti di timbangan keluar untuk ditimbang tara. Selisih bruto dan tara menjadi tonase TBS yang diterima pabrik hari itu.',
   inp:'Truk TBS dari kebun', out:'Tonase TBS tercatat',
   eq:['Weighbridge','Timbangan truk']},
  {code:'grading', title:'Grading', key:'Sortasi mutu', focus:[-47,9], size:38, mark:9,
   text:'Sebagian muatan diturunkan di lantai grading dan disortir mutunya: janjang matang, mentah, lewat matang, dan brondolan. Hasilnya menentukan potongan harga ke pemasok.',
   inp:'Sampel muatan TBS', out:'TBS di loading ramp',
   eq:['Grading','Loading Ramp']},
  {code:'cages-track', title:'Cages Track', key:'Lori & rel', focus:[12,10], size:46, mark:8,
   text:'TBS dari ramp diisi ke lori (cage), lalu ditarik capstan di atas rel. Transfer carriage memindahkan lori antarjalur rebusan.',
   inp:'TBS di loading ramp', out:'Lori berisi TBS',
   eq:['Cages','Rail tracks','Transfer Carriage','Capstan']},
  {code:'sterilizer', title:'Sterilizer', key:'Rebusan', focus:[42,10], size:52, mark:10,
   text:'Lori masuk sterilizer lewat drawbridge dan direbus dengan uap. Uap dibuang lewat blow off, kondensat lewat blow down lalu ke recovery.',
   inp:'Lori berisi TBS', out:'TBS masak + kondensat',
   eq:['Drawbridges','Sterilizer','Blow off / blow down','Recovery']},
  {code:'threshing', title:'Threshing', key:'Penebahan', focus:[82,12], size:52, mark:21,
   text:'Tippler membalik lori ke bunch elevator. Thresher memisahkan brondolan dari janjangan, dan janjang kosong (EFB) dibawa conveyor keluar.',
   inp:'TBS masak', out:'Brondolan + EFB',
   eq:['Tippler','Bunch Elevator','FFB Conveyors','Thresher','Fruit Elevator','Conveyor Under Thresher','FFB Distribution Conveyor','EFB Recycle Conveyor','EFB Conveyors']},
  {code:'pressing', title:'Pressing', key:'Kempa', focus:[78,50], size:50, mark:17,
   text:'Brondolan dilumatkan di digester dan diperas screw press. Minyak kasar diencerkan, disaring vibrating screen, lalu ditampung di crude oil tank.',
   inp:'Brondolan', out:'Crude oil + press cake',
   eq:['Digester Feed Conveyor','Press/Digester','Top Cross Conveyor','Dilution tanks','Vibrating Screen','Crude oil tank & pumps']},
  {code:'clarification', title:'Clarification', key:'Klarifikasi', focus:[41,52], size:52, mark:16,
   text:'Minyak kasar diendapkan di CST. Minyak dimurnikan di purifier dan dikeringkan di vacuum dryer, sementara sludge diolah decanter untuk mengambil sisa minyak.',
   inp:'Crude oil', out:'CPO + sludge + solid',
   eq:['CST','Sludge Tank','Pure oil tank','Precleaner','Decanter','Purifier','Vacuum Dryer','Dried oil pump','Solid Conveyor','Sludge Pits','Sludge recovery tanks']},
  // ZOOM DIRAPATKAN 48 -> 42 dan fokus digeser (5,52) -> (2,51). Lihat
  // catatan di entri 'depricarping' di bawah: keduanya duduk di diagonal
  // pandang isometrik yang SAMA, sehingga satu-satunya hal yang membedakan
  // keduanya di layar adalah seberapa penuh bangunannya mengisi bingkai.
  {code:'kernel-plant', title:'Kernel Plant', key:'Stasiun kernel', focus:[2,51], size:42, mark:22,
   text:'Biji dipecah di ripple mill. Inti dipisahkan dari cangkang di LTDS dan claybath, lalu dikeringkan di kernel dryer.',
   inp:'Biji (nut)', out:'Kernel kering + cangkang',
   eq:['Nut Hopper','Ripple Mills','LTDS 1 & n System','Claybath','Kernel Dryers']},
  {code:'boiler-room', title:'Boiler Room', key:'Boiler', focus:[-10,98], size:58, mark:19,
   text:'Air diolah di RO dan demin plant supaya aman untuk boiler. Serat dan cangkang dibawa conveyor sebagai bahan bakar untuk membuat uap.',
   inp:'Serat, cangkang, air demin', out:'Uap bertekanan',
   eq:['RO Feed pump','Carbon Filter','RO Plant','Demin Plant','Boiler 1/2/n','Boiler Feeding Conveyors 1/2/3/n']},
  {code:'effluent-plant', title:'Effluent Plant', key:'IPAL', focus:[117,102], size:54, mark:7,
   text:'Limbah cair (POME) dipompa ke kolam bertahap. Surface aerator menambah oksigen, dan sebagian limbah dipompa balik lewat recycle pump.',
   inp:'POME', out:'Limbah olahan untuk lahan',
   eq:['Effluent pump 1/n','Ponds','Surface aerators','Recycle pumps']},
  // ZOOM DIRAPATKAN 46 -> 34 dan fokus digeser (-12,42) -> (-15,39), yaitu
  // pusat gugus depericarper yang sebenarnya (menara + dua siklon, x -19..-11,
  // z 31..47) alih-alih titik di antaranya dan gedung kernel plant.
  //
  // SEBABNYA, DAN KENAPA MENGGESER KAMERA SAJA TIDAK CUKUP: pada nilai desain
  // aslinya (46/48, fokus berjarak 17 di x dan 10 di z) titik fokus kedua
  // perhentian ini hanya bergeser 7% lebar bingkai di layar, sementara setiap
  // transisi antarperhentian lain bergeser 23-56%. Akibatnya kamera praktis
  // tidak bergerak dan KEDUA bangunan masuk penuh di kedua bingkai, sehingga
  // dua stasiun yang berbeda terbaca sebagai satu perhentian yang terulang.
  //
  // Dan pergeseran mendatar itu TIDAK DAPAT diperbaiki dengan menggeser fokus:
  // depericarper dan kernel plant berdiri hampir persis di diagonal pandang
  // isometrik yang sama (camRight ~ (1,0,-1)/V2, jadi selisih x dan z-nya
  // saling menghapus di layar). Yang membedakan keduanya karena itu adalah
  // ZOOM: pada 34, menara depericarper mengisi bingkai dan gedung kernel plant
  // jatuh ke pinggir; pada 46, keduanya sama-sama kecil di tengah.
  //
  // Nilai 46/48 datang verbatim dari berkas desain pks-intro.html dan bukan
  // akibat pemecahan Weighbridge/Grading — di sana pun kedua perhentian ini
  // sudah tertukar pandang.
  {code:'depricarping', title:'Depricarping', key:'Pemisahan serat & biji', focus:[-21,65], size:34, mark:26,
   text:'Cake breaker conveyor (CBC) memecah press cake. Depericarper memisahkan serat untuk bahan bakar boiler, dan destoner membuang batu dari biji.',
   inp:'Press cake', out:'Biji (nut) + serat',
   eq:['CBCs','Depericarper System','Destoner System']},
  {code:'engine-room', title:'Engine Room', key:'Kamar mesin', focus:[18,96], size:42, mark:13,
   text:'Turbin uap memutar generator. Uap bekasnya ditampung BPV lalu dibagi ke rebusan dan klarifikasi, dan listriknya dibagi lewat MSB/MCC.',
   inp:'Uap dari boiler', out:'Listrik + uap proses',
   eq:['Turbine 1/2/n','Aircompressor 1/n','BPV','MSB/MCC']},
  {code:'process-water', title:'Process Water', key:'Air proses', focus:[129,14], size:48, mark:9,
   text:'Air sungai dipompa ke water clarifier, disaring di sand filter, ditampung di water basin dan ground storage tank, lalu dibagi ke seluruh pabrik.',
   inp:'Air sungai', out:'Air bersih proses',
   eq:['Raw water pump','Water clarifier','Sand Filters','Water Basin','Ground Storage tank','Distribution pump / Clean water pump']},
  {code:'storage-tank', title:'Storage Tank', key:'Tangki timbun', focus:[52,100], size:62, mark:17,
   text:'CPO disimpan di tangki timbun yang dipanaskan, sedangkan kernel ditampung di bulk silo sampai dikirim.',
   inp:'CPO + kernel kering', out:'Stok siap kirim',
   eq:['CPO Tank 1/2/n','Bulk Silo 1/n']},
  {code:'solid-waste-disposal', title:'Solid Waste Disposal', key:'Limbah padat', focus:[-42,112], size:46, mark:10,
   text:'Limbah padat seperti EFB dan serat sisa dikumpulkan, lalu dimuat excavator ke truk dan dikembalikan ke kebun sebagai mulsa.',
   inp:'EFB + solid', out:'Mulsa ke kebun',
   eq:['Excavator','Solids/Fibers']},
  {code:'kernel-dispatch', title:'Kernel Dispatch', key:'Pengiriman kernel', focus:[115,46], size:44, mark:11,
   text:'Kernel dikemas dalam karung goni, ditumpuk di gudang, lalu dimuat ke truk oleh tenaga muat.',
   inp:'Kernel dari bulk silo', out:'Kernel terkirim',
   eq:['Gunny Sacks','Despatch Labor']},
  {code:'cpo-dispatch', title:'CPO Dispatch', key:'Pengiriman CPO', focus:[153,64], size:56, mark:9,
   text:'CPO dipompa dari tangki timbun lewat pumping station ke jetty, lalu dimuat ke tongkang di sungai.',
   inp:'CPO di tangki', out:'CPO di tongkang',
   eq:['Pumping stations','Jetty']},
  {code:'process-quality-control', title:'Process Quality Control', key:'Lab QC', focus:[-46,28], size:40, mark:10,
   text:'Laboratorium memeriksa sampel dari tiap stasiun: ALB, kadar air, kotoran, serta losses minyak dan kernel.',
   inp:'Sampel tiap stasiun', out:'Laporan mutu & losses',
   eq:['Laboratory Chemicals/Consumables','Labor']},
];
const N = STOPS.length;
const pad = n => String(n).padStart(2,'0');

const card = document.getElementById('card'), ticks = document.getElementById('ticks'), jump = document.getElementById('jump');
const intro = document.getElementById('intro'), stage = document.getElementById('stage'), hint = document.getElementById('hint');
const prev = document.getElementById('prev'), next = document.getElementById('next');
let current = -1;

STOPS.forEach((s,i) => {
  const b = document.createElement('button');
  b.className = 'tick'; b.type = 'button'; b.title = `${i+1}. ${s.title}`; b.setAttribute('aria-label', `${i+1}: ${s.title}`);
  // Kode stasiun dibawa di DOM supaya menyambungkan tur ini ke layar form /
  // laporan stasiun kelak tidak perlu menebak dari nomor urut.
  b.dataset.stationCode = s.code;
  b.addEventListener('click', () => goTo(i));
  ticks.appendChild(b);
  const opt = new Option(`${i+1}. ${s.title} · ${s.key}`, i); opt.dataset.stationCode = s.code; jump.add(opt);
});
jump.addEventListener('change', () => goTo(Number(jump.value)));

/* PEMILIH: kirim saat berubah, dan BAWA SERTA HASH-NYA.
   Tanpa baris hash itu, mengganti line akan melempar pembaca kembali ke
   stasiun 01 — ia memilih line justru KARENA sedang melihat stasiun
   tertentu, dan memulangkannya ke awal adalah cara tercepat membuat
   pemilihnya terasa rusak.
   <form> tetap dipakai apa adanya supaya tanpa JavaScript halaman ini masih
   berfungsi lewat tombol Terapkan di dalam <noscript>. */
const scopeForm = document.querySelector('[data-testid="scope-form"]');
if (scopeForm) {
  scopeForm.addEventListener('change', (e) => {
    // Mengganti mill membatalkan line yang dipilih: line milik mill lama
    // tidak sah di mill baru, dan service akan membuangnya. Mengosongkannya
    // di sini membuat alasannya terlihat alih-alih tampak seperti bug.
    if (e.target.name === 'mill') {
      const line = scopeForm.querySelector('[name="line"]');
      if (line) line.value = '';
    }
    const params = new URLSearchParams(new FormData(scopeForm));
    location.href = `${scopeForm.action}?${params}${location.hash}`;
  });
}
const stepPx = () => (intro.offsetHeight - innerHeight) / (N - 1);
function goTo(i){ i = Math.max(0, Math.min(N-1, i)); scrollTo({top: intro.offsetTop + i * stepPx(), behavior: reduced ? 'auto' : 'smooth'}); }
prev.addEventListener('click', () => goTo(current - 1));
next.addEventListener('click', () => goTo(current + 1));
/* Zoom: pengali ukuran pandang kamera, lepas dari stasiun yang sedang dipilih.
   Gulir biasa tetap berarti pindah stasiun, jadi roda hanya memperbesar bila
   Ctrl/⌘ ditahan (itu juga yang dikirim pinch di trackpad). */
const ZOOM_MIN = 0.6, ZOOM_MAX = 3, ZOOM_STEP = 1.25;
const zoomInBtn = document.getElementById('zoomIn'), zoomOutBtn = document.getElementById('zoomOut');
let zoomTarget = 1;
function setZoom(z){
  zoomTarget = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, z));
  zoomOutBtn.disabled = zoomTarget <= ZOOM_MIN + 1e-6;
  zoomInBtn.disabled = zoomTarget >= ZOOM_MAX - 1e-6;
}
zoomInBtn.addEventListener('click', () => setZoom(zoomTarget * ZOOM_STEP));
zoomOutBtn.addEventListener('click', () => setZoom(zoomTarget / ZOOM_STEP));
stage.addEventListener('wheel', e => {
  if (!(e.ctrlKey || e.metaKey)) return;
  e.preventDefault();
  setZoom(zoomTarget * Math.exp(-e.deltaY * 0.0025));
}, {passive: false});
addEventListener('keydown', e => {
  if (e.target.closest('input,textarea,select')) return;
  if (!e.ctrlKey && !e.metaKey && !e.altKey) {
    if (e.key === '+' || e.key === '=') { e.preventDefault(); setZoom(zoomTarget * ZOOM_STEP); }
    if (e.key === '-' || e.key === '_') { e.preventDefault(); setZoom(zoomTarget / ZOOM_STEP); }
    if (e.key === '0') { e.preventDefault(); setZoom(1); }
  }
  if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); goTo(current + 1); }
  if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); goTo(current - 1); }
});

function setStop(i){
  if (i === current) return;
  const first = current === -1; current = i;
  [...ticks.children].forEach((b,j) => {
    b.classList.toggle('done', j < i);
    if (j === i) b.setAttribute('aria-current','step'); else b.removeAttribute('aria-current');
  });
  jump.value = i;
  prev.disabled = i === 0; next.disabled = i === N-1;
  const s = STOPS[i];
  const apply = () => {
    document.getElementById('sNum').textContent = pad(i+1);
    document.getElementById('sKey').textContent = s.key;
    document.getElementById('sTitle').textContent = s.title;
    document.getElementById('sText').textContent = s.text;
    document.getElementById('sIn').textContent = s.inp;
    document.getElementById('sOut').textContent = s.out;
    document.getElementById('sEq').innerHTML = s.eq.map(e => `<span>${esc(e)}</span>`).join('');
    renderLive(s.code);
    card.classList.remove('swap');
  };
  if (first || reduced) apply(); else { card.classList.add('swap'); setTimeout(apply, 180); }
}
/* esc() TETAP DIPERLUKAN walaupun Target Operasi sudah dibuang, dan
   alasannya bergeser bukan hilang: nama Production Line
   (SCOPE.production_line_name) ditulis manusia lewat master data dan masuk ke
   innerHTML di renderLive(). Label dan satuan metriknya memang konstanta
   server, tetapi meloloskan sebagian nilai dan tidak sebagian lain adalah
   cara aturan ini berhenti dipatuhi. */
function esc(v){
  return String(v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

const MONTHS_ID = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
function formatDateId(iso){
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
  if (!m) return null;
  return `${Number(m[3])} ${MONTHS_ID[Number(m[2])-1]} ${m[1]}`;
}
function plural(n, kata){ return `${n.toLocaleString('id-ID')} ${kata}`; }

/* Blok produksi. Nol angka dihitung di sini — seluruhnya datang jadi dari
   StationProductionService; klien hanya memilih kalimatnya. */
function renderLive(code){
  const d = LIVE[code] || {};
  const prod = document.getElementById('sProd');
  const lbl = document.getElementById('sProdLbl');
  const rec = document.getElementById('sRec');

  lbl.textContent = SCOPE.production_line_id
    ? `Produksi · ${REC_DAYS} hari terakhir`
    : 'Produksi';

  /* SELAMA MILL/LINE BELUM DIPILIH, KARTUNYA MENGATAKANNYA — bukan
     memperlihatkan em dash berderet yang tidak dapat dibedakan dari stasiun
     yang memang tidak berproduksi. Dua keadaan yang berbeda harus terbaca
     berbeda, dan ini yang pertama. */
  if (SCOPE.needs_mill || SCOPE.needs_line) {
    prod.innerHTML = '';
    rec.className = 'rec rec--none';
    rec.textContent = SCOPE.needs_mill
      ? 'Pilih mill dan Production Line untuk menampilkan angka produksi.'
      : 'Pilih Production Line untuk menampilkan angka produksi.';
    return;
  }

  prod.innerHTML = (d.metrics || []).map(m => {
    const v = m.value === null || m.value === undefined
      ? '<span class="na">—</span>'
      : `${esc(m.value.toLocaleString('id-ID'))} <small>${esc(m.unit)}</small>`;
    return `<div><dt>${esc(m.label)}</dt><dd>${v}</dd></div>`;
  }).join('');

  if (!d.row_count) {
    /* "Tidak ada produksi tercatat" DITULIS, bukan disembunyikan di balik em
       dash. Pada line yang memang tidak menjalankan stasiun ini, itulah
       satu-satunya informasi yang tidak ada di tempat lain. */
    rec.className = 'rec rec--none';
    rec.textContent = `Tidak ada pencatatan di line ini dalam ${REC_DAYS} hari terakhir.`;
  } else {
    rec.className = 'rec';
    rec.innerHTML = `Dari <b>${esc(d.row_count.toLocaleString('id-ID'))}</b> baris tercatat`
      + ` di ${esc(SCOPE.production_line_name || '')}.`;
  }
}

function stopFloat(){
  const p = (scrollY - intro.offsetTop) / Math.max(1, intro.offsetHeight - innerHeight);
  return Math.min(1, Math.max(0, p)) * (N - 1);
}
function onScroll(){ const s = stopFloat(); setStop(Math.round(s)); hint.style.opacity = s > 0.15 ? 0 : 1; }
addEventListener('scroll', onScroll, {passive:true});
// Tautan dalam: #s1 … #s18 ATAU kode stasiunnya (#weighbridge,
// #cpo-dispatch, …). Bentuk berkode dipakai supaya tautan dari layar lain
// tidak bergantung pada nomor urut, yang akan bergeser bila master
// station_types berubah — persis yang baru saja terjadi ketika FFB Reception
// dipecah menjadi Weighbridge dan Grading dan seluruh nomor sesudahnya maju
// satu. Nomor tetap diterima supaya tautan lama tidak mati.
function fromHash(){
  const h = location.hash.slice(1);
  if (!h) return;
  const byNum = h.match(/^s(\d{1,2})$/);
  const idx = byNum ? Number(byNum[1]) - 1 : STOPS.findIndex(s => s.code === h);
  if (idx < 0) return;
  const i = Math.max(0, Math.min(N-1, idx)), root = document.documentElement;
  root.style.scrollBehavior = 'auto';
  scrollTo(0, intro.offsetTop + i * stepPx());
  root.style.scrollBehavior = '';
  onScroll();
}
addEventListener('hashchange', fromHash);
fromHash();
onScroll();

/* ============ 3D mill ============ */
function build(){
  if (!window.THREE) { stage.classList.add('no3d'); return; }
  let renderer;
  try { renderer = new THREE.WebGLRenderer({canvas: document.getElementById('scene'), antialias: true}); }
  catch (e) { stage.classList.add('no3d'); return; }
  renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.setClearColor(0xA3BE86);

  const scene = new THREE.Scene();
  const cam = new THREE.OrthographicCamera(-1,1,1,-1,1,2000);
  const OFF = new THREE.Vector3(1, 0.86, 1).normalize().multiplyScalar(420);
  cam.position.copy(OFF); cam.lookAt(0,0,0);
  const camRight = new THREE.Vector3(1,0,0).applyQuaternion(cam.quaternion);
  const camUp = new THREE.Vector3(0,1,0).applyQuaternion(cam.quaternion);

  scene.add(new THREE.HemisphereLight(0xffffff, 0xd9dfd2, 0.78));
  const sun = new THREE.DirectionalLight(0xffffff, 0.5);
  sun.castShadow = true; sun.shadow.mapSize.set(2048, 2048);
  Object.assign(sun.shadow.camera, {left:-100, right:100, top:100, bottom:-100, near:1, far:700});
  sun.shadow.bias = -0.0006;
  scene.add(sun, sun.target);
  const SUN_OFF = new THREE.Vector3(-35, 130, 75);

  const C = {ground:0xA3BE86, pave:0xC2C6B6, road:0x9CA196, white:0xF8F9F7, roof:0xE1E5DE, wall:0xD5DBD3,
    green:0x249360, greenDk:0x1D7A4E, fruit:0xE8641B, fruitDk:0x9A3412, fruitBk:0x3B1F14, efb:0x6B4E36, fiber:0xA88A5E,
    steel:0xA9B1B8, steelDk:0x5E6770, dark:0x2C332F, glass:0x3A4A44, rail:0x6B7280, sleeper:0x8D7660,
    accent:0xD97706, tank:0xE7EAE4, oil:0xD9A23B, soil:0xB9A283, palm1:0x4F8F3A, palm2:0x5F9F46, trunk:0x8A6E4E,
    water:0x9DBFD6, river:0x8FB3C9, sack:0xD6C59C, yellow:0xE9B949};

  const BOX = new THREE.BoxGeometry(1,1,1);
  const mats = {};
  const mat = c => mats[c] || (mats[c] = new THREE.MeshLambertMaterial({color:c}));
  function box(w,h,d,c,x,y,z,parent=scene,shadow=true){
    const m = new THREE.Mesh(BOX, Array.isArray(c) ? c : mat(c));
    m.scale.set(w,h,d); m.position.set(x, y + h/2, z);
    m.castShadow = shadow; m.receiveShadow = true; parent.add(m); return m;
  }
  // cylinder; axis 'y' (standing, y = base), 'x' or 'z' (lying, y = centre)
  function cyl(r,len,c,x,y,z,axis='y',parent=scene,seg=24){
    const g = new THREE.CylinderGeometry(r,r,len,seg);
    if (axis === 'x') g.rotateZ(Math.PI/2); if (axis === 'z') g.rotateX(Math.PI/2);
    const m = new THREE.Mesh(g, mat(c));
    m.position.set(x, axis === 'y' ? y + len/2 : y, z);
    m.castShadow = true; m.receiveShadow = true; parent.add(m); return m;
  }
  function cone(r,h,c,x,y,z,down=false,parent=scene){
    const g = new THREE.ConeGeometry(r,h,24); if (down) g.rotateX(Math.PI);
    const m = new THREE.Mesh(g, mat(c)); m.position.set(x,y+h/2,z); m.castShadow = true; parent.add(m); return m;
  }
  function legs(x,z,s,h){ for (const dx of [-s,s]) for (const dz of [-s,s]) box(0.3,h,0.3,C.steelDk,x+dx,0,z+dz,scene,false); }
  // standing silo on legs with hopper bottom
  function silo(x,z,r,h,c,lh=4){ legs(x,z,r*0.7,lh); cone(r,1.6,C.steelDk,x,lh,z,true); cyl(r,h,c,x,lh+1.6,z); cone(r*1.05,1.2,C.steelDk,x,lh+1.6+h,z); }
  function cyclone(x,y,z,r=1.2){ cyl(r,2.2,C.steel,x,y+2.4,z); cone(r,2.4,C.steel,x,y,z,true); cyl(0.3,1.2,C.steelDk,x,y+4.6,z); }
  const rng = (s => () => (s = (s * 16807) % 2147483647) / 2147483647)(20261020);
  const maxAniso = renderer.capabilities.getMaxAnisotropy();

  function sign(text, w, h, face, x, y, z, bg='#249360', fg='#FFFFFF'){
    const cv = document.createElement('canvas'); cv.width = 1024; cv.height = Math.round(1024*h/w);
    const g = cv.getContext('2d');
    g.fillStyle = bg; g.fillRect(0,0,cv.width,cv.height);
    let fs = Math.min(cv.height*0.56, 160);
    g.font = `700 ${fs}px "Inter", "Segoe UI", sans-serif`;
    while (g.measureText(text).width > cv.width*0.9 && fs > 20) { fs -= 4; g.font = `700 ${fs}px "Inter", "Segoe UI", sans-serif`; }
    g.fillStyle = fg; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText(text, cv.width/2, cv.height*0.54);
    const t = new THREE.CanvasTexture(cv); t.anisotropy = maxAniso;
    const tm = new THREE.MeshLambertMaterial({map:t}), plain = mat(parseInt(bg.slice(1),16));
    const arr = face === 'x' ? [tm,plain,plain,plain,plain,plain] : [plain,plain,plain,plain,tm,plain];
    return face === 'x' ? box(0.3,h,w,arr,x,y,z,scene,false) : box(w,h,0.3,arr,x,y,z,scene,false);
  }
  // free-standing sign on two legs
  function post(text, w, x, z, face='z', h=1.6, top=4){
    if (face === 'z') { box(0.3,top,0.3,C.steelDk,x-w/2+0.6,0,z-0.2); box(0.3,top,0.3,C.steelDk,x+w/2-0.6,0,z-0.2); }
    else { box(0.3,top,0.3,C.steelDk,x-0.2,0,z-w/2+0.6); box(0.3,top,0.3,C.steelDk,x-0.2,0,z+w/2-0.6); }
    sign(text, w, h, face, x, top, z);
  }
  // cutaway shed: back walls on -x and -z, roof over the back third only, so the camera sees the machines
  function shed(x1,z1,x2,z2,h){
    const w = x2-x1, d = z2-z1, cx = (x1+x2)/2, cz = (z1+z2)/2, rd = d*0.32;
    box(w,h,0.4,C.wall,cx,0,z1); box(0.4,h,d,C.wall,x1,0,cz);
    for (const [x,z] of [[x2,z2],[x2,z1+0.2],[x1+0.2,z2],[cx,z2],[x2,cz]]) box(0.6,h,0.6,C.steelDk,x,0,z);
    box(w+0.6,0.5,rd,C.roof,cx,h,z1+rd/2-0.3);
    for (let x=x1+w/4; x<x2; x+=w/4) box(0.4,0.4,d,C.steelDk,x,h-0.2,cz,scene,false);
    box(w+0.8,0.9,0.3,C.green,cx,h-0.6,z2+0.3,scene,false);
    box(0.3,0.9,d+0.8,C.green,x2+0.3,h-0.6,cz,scene,false);
  }
  // pipe on a rack along axis-aligned points [[x,z],...]
  function pipe(points, y, c=C.steelDk, r=0.28){
    for (let i=0;i<points.length-1;i++){
      const [ax,az] = points[i], [bx,bz] = points[i+1];
      if (ax === bx) box(r*2,r*2,Math.abs(bz-az)+r*2,c,ax,y,(az+bz)/2);
      else box(Math.abs(bx-ax)+r*2,r*2,r*2,c,(ax+bx)/2,y,az);
      const len = Math.hypot(bx-ax,bz-az);
      for (let t=0;t<=len;t+=10){ const k = t/len; box(0.3,y,0.3,C.steel,ax+(bx-ax)*k,0,az+(bz-az)*k,scene,false); }
    }
  }
  function rail(x1,x2,z){
    box(x2-x1,0.25,2.6,0xC9C2B6,(x1+x2)/2,0,z,scene,false);
    for (const dz of [-0.6,0.6]) box(x2-x1,0.18,0.14,C.rail,(x1+x2)/2,0.28,z+dz,scene,false);
    for (let x=x1+0.6;x<x2;x+=1.6) box(0.4,0.08,2.2,C.sleeper,x,0.25,z,scene,false);
  }
  function person(x,z,c=C.fruit,carry=false){
    box(0.55,1.1,0.4,c,x,0.75,z); box(0.5,0.75,0.35,C.dark,x,0,z);
    const h = new THREE.Mesh(new THREE.SphereGeometry(0.28,10,8), mat(0xC68B59)); h.position.set(x,2.15,z); scene.add(h);
    box(0.62,0.22,0.62,C.yellow,x,2.32,z,scene,false);
    if (carry) box(0.9,0.45,0.6,C.sack,x,2.5,z);
  }
  // fruit-like heap from instanced pieces
  function heap(cx,cz,r,n,colors,size=0.6,baseY=0,peak=0.8){
    const geo = new THREE.IcosahedronGeometry(size,0);
    const meshes = colors.map(c => new THREE.InstancedMesh(geo, mat(c), n)), counts = colors.map(() => 0), d = new THREE.Object3D();
    for (let i=0;i<n;i++){
      const k = i % colors.length, a = rng()*6.28, rr = Math.sqrt(rng())*r;
      d.position.set(cx+Math.cos(a)*rr, baseY + Math.max(size*0.5,(r-rr)*peak) + rng()*0.4, cz+Math.sin(a)*rr);
      d.rotation.set(rng()*3,rng()*3,0); d.scale.setScalar(0.8+rng()*0.5); d.updateMatrix();
      meshes[k].setMatrixAt(counts[k]++, d.matrix);
    }
    meshes.forEach((m,i) => { m.count = counts[i]; m.castShadow = true; scene.add(m); });
  }
  function makeTruck(kind){
    const g = new THREE.Group();
    box(2.3,2.6,2.4,C.white,4.2,0.9,0,g); box(0.08,1,2.1,C.glass,5.38,2.2,0,g,false);
    box(10.4,0.45,2.1,C.dark,-0.2,0.55,0,g);
    if (kind === 'tanker') { cyl(1.35,8.2,0xD7DBDF,-1.4,2.5,0,'x',g); box(8.2,0.35,2.75,C.green,-1.4,2.3,0,g,false); }
    else if (kind === 'sacks') { box(7.6,0.4,2.5,C.green,-1.4,1,0,g); for (let i=0;i<5;i++) box(1.3,1.2,2.2,C.sack,-4.4+i*1.5,1.4,0,g); }
    else {
      // bak berengsel di ujung belakang (x -5.2), supaya truk TBS bisa jungkit di loading ramp
      const bed = new THREE.Group(); bed.position.set(-5.2,1,0); g.add(bed);
      box(7.6,1.8,2.5,kind === 'waste' ? C.yellow : C.green,3.8,0,0,bed);
      g.userData.load = box(7.2,0.7,2.2,kind === 'waste' ? C.efb : C.fruitDk,3.8,1.6,0,bed);
      g.userData.bed = bed;
    }
    const wg = new THREE.CylinderGeometry(0.55,0.55,0.4,12).rotateX(Math.PI/2);
    for (const x of [-4,-2.8,3.8]) for (const z of [-1.1,1.1]) { const w = new THREE.Mesh(wg, mat(C.dark)); w.position.set(x,0.55,z); g.add(w); }
    g.traverse(o => { o.castShadow = true; });
    scene.add(g); return g;
  }
  function makeLorry(full=true){
    const g = new THREE.Group();
    box(2.6,1.8,2,C.steelDk,0,0.5,0,g); const f = box(2.4,0.5,1.8,C.fruitDk,0,2.3,0,g); f.visible = full;
    for (const x of [-0.8,0.8]) for (const z of [-0.65,0.65]) box(0.5,0.5,0.15,C.dark,x,0.15,z,g);
    scene.add(g); return g;
  }

  /* ground, river, roads, mill yard */
  const ground = new THREE.Mesh(new THREE.PlaneGeometry(1600,1600).rotateX(-Math.PI/2), mat(C.ground));
  ground.receiveShadow = true; scene.add(ground);
  box(38,0.55,1600,C.river,169,-0.5,0,scene,false);
  box(2,0.3,1600,C.soil,149.5,-0.2,0,scene,false); box(2,0.3,1600,C.soil,188.5,-0.2,0,scene,false);
  box(214,0.1,166,C.pave,41,0,53,scene,false);
  box(440,0.12,7,C.road,-5,0,-14,scene,false);
  for (let x=-222;x<212;x+=6) box(2.6,0.14,0.25,C.white,x,0,-14,scene,false);
  box(440,0.12,7,C.road,55,0,128,scene,false);
  box(7,0.12,35,C.road,-34,0,4.5,scene,false);
  for (const z of [-9.5,-3.5]) box(32,0.12,4.6,C.road,-50,0,z,scene,false);

  /* 1. FFB reception — dua jembatan timbang di halaman, bukan di badan jalan:
        timbangan 1 (masuk, bruto) lalu timbangan 2 (keluar, tara) */
  for (const z of [-9.5,-3.5]) box(16,0.3,4,C.steelDk,-50,0,z,scene,false);
  post('SCALE 1 · IN', 8, -50, -12.1, 'z', 1.1, 3.6);
  post('SCALE 2 · OUT', 8, -50, -0.6, 'z', 1.1, 3);
  box(5,3.2,4,C.white,-48,0,-20); box(5.2,0.4,4.2,C.green,-48,3.2,-20); box(0.08,1.1,3,C.glass,-45.45,1.5,-20,scene,false);
  post('WEIGHBRIDGE', 9, -48, -23.5, 'z', 1.4, 4);
  box(11,0.25,9,C.wall,-47,0,8,scene,false);
  for (const x of [-51.5,-42.5]) for (const z of [4,12]) box(0.4,5,0.4,C.steelDk,x,0,z,scene,false);
  box(11.4,0.3,4,C.roof,-47,5,6);
  heap(-49,9,2.2,40,[C.fruit,C.fruitDk,C.fruitBk],0.5,0.25,0.4);
  heap(-44.5,8,1.8,26,[C.fruit,C.fruitDk,C.fruitBk],0.5,0.25,0.4);
  person(-46.5,11,C.green); person(-43,10.5,C.fruit);
  post('GRADING', 7, -47, 14, 'z', 1.3, 3);
  const rampA = 0.2, rampY = 4.6, rampX = -14, rampZ = 10;
  for (const x of [-25,-14,-3]) for (const z of [3.5,16.5]) box(0.7, rampY + (rampX - x)*Math.sin(rampA)*0.95, 0.7, C.steelDk, x, 0, z);
  const deck = box(24,0.6,14,C.steel,rampX,rampY,rampZ); deck.rotation.z = -rampA;
  for (let i=0;i<8;i++){ const x = -24 + i*3.2; box(0.25,1.6,0.25,C.green,x+1.4,rampY+(rampX-x-1.4)*Math.sin(rampA)-0.4,16.6,scene,false); }
  box(0.4,1.4,14,C.green,-26,rampY+2.2,rampZ);
  post('LOADING RAMP', 10, -14, 21, 'z', 1.4, 5);
  { const geo = new THREE.IcosahedronGeometry(0.55,0), kinds = [C.fruit, C.fruitDk, C.fruitBk];
    const per = 260, meshes = kinds.map(c => new THREE.InstancedMesh(geo, mat(c), per)), d = new THREE.Object3D(), counts = [0,0,0];
    for (let n=0;n<per*3;n++){
      const k = n % 3, u = -11 + rng()*20, v = -6 + rng()*12;
      const hh = Math.max(0, 1.6 - Math.abs(v)*0.18 - Math.abs(Math.sin(u*0.5))*0.6);
      d.position.set(rampX + u, rampY + 0.6 - u*Math.sin(rampA) + rng()*hh + 0.3, rampZ + v);
      d.rotation.set(rng()*3, rng()*3, 0); d.scale.setScalar(0.8 + rng()*0.6); d.updateMatrix();
      meshes[k].setMatrixAt(counts[k]++, d.matrix);
    }
    meshes.forEach(m => { m.castShadow = true; scene.add(m); });
  }

  /* 2. cages / tracks: main line, transfer carriage, capstan */
  rail(-8,70,10); rail(20,64,3); rail(20,64,17);
  box(3.6,0.2,24,0xC9C2B6,17,0,10,scene,false);
  for (const dx of [-1,1]) box(0.14,0.2,24,C.rail,17+dx,0.2,10,scene,false);
  const carriage = new THREE.Group(); scene.add(carriage);
  box(3.4,0.6,3.4,C.fruit,0,0.4,0,carriage); box(0.3,1.6,3.4,C.steelDk,1.6,0.4,0,carriage);
  const carLorry = makeLorry(); carriage.add(carLorry); carLorry.position.set(0,1,0);
  carriage.position.set(17,0,3);
  box(1.8,0.8,1.8,C.steelDk,6,0,15); const capDrum = cyl(0.7,1.3,C.green,6,0.8,15);
  box(0.12,0.12,5,C.dark,6,0.9,12.4,scene,false);
  post('CAPSTAN', 6, 6, 18.5, 'z', 1.1, 2.6);
  post('TRANSFER CARRIAGE', 12, 17, 24, 'z', 1.3, 3.2);
  [[24,3],[29,3],[24,17],[30,17],[34,17]].forEach(([x,z]) => makeLorry().position.set(x,0.3,z));

  /* 3. sterilization */
  for (const z of [3,10,17]){
    cyl(2.4,30,C.steel,42,2.6,z,'x');
    cyl(2.6,0.6,C.steelDk,26.8,2.6,z,'x'); cyl(2.6,0.6,C.steelDk,57.2,2.6,z,'x');
    for (const x of [31,42,53]) box(1,1.2,3.6,C.steelDk,x,0,z);
    box(28,0.25,0.25,C.greenDk,42,5.1,z,scene,false);
    const db = box(2.6,0.2,2.2,C.green,25,0.6,z); db.rotation.z = 0.35;
  }
  cyl(0.35,6,C.steelDk,55,4.8,10);
  cyl(1.8,5,C.steel,63,0,22); cone(1.9,1,C.steelDk,63,5,22); cyl(0.3,4,C.steelDk,63,6,22);
  box(10,0.3,0.4,C.steelDk,58,4.2,19,scene,false);
  post('STERILIZER', 11, 42, 24, 'z', 1.5, 5);
  post('BLOW DOWN', 7, 63, 26.5, 'z', 1.2, 3);

  /* 4. threshing */
  shed(66,0,94,22,17);
  const tippler = new THREE.Group(); scene.add(tippler); tippler.position.set(69.5,3.6,10);
  for (const dx of [-1.6,1.6]) { const r = new THREE.Mesh(new THREE.TorusGeometry(3,0.35,8,28).rotateY(Math.PI/2), mat(C.fruit)); r.position.x = dx; r.castShadow = true; tippler.add(r); }
  const tipLorry = makeLorry(); tippler.add(tipLorry); tipLorry.position.set(0,-2.7,0);
  box(4.6,0.8,7,C.steelDk,69.5,0,10);
  const be = box(10,0.8,2,C.steelDk,75.5,4.2,10); be.rotation.z = 0.75;
  for (const x of [76,84]) box(0.6,5.4,0.6,C.steelDk,x,0,7.6); for (const x of [76,84]) box(0.6,5.4,0.6,C.steelDk,x,0,12.4);
  const drum = cyl(2.5,8,C.green,84,7.8,10,'x',scene,10);
  box(14,0.6,1.4,C.steelDk,84,1.6,10);
  box(1.6,16,1.6,C.steel,91,0,17); box(2,1.4,2,C.steelDk,91,16,17);
  box(1.4,1.4,22,C.steelDk,91,15,30);
  const efbConv = box(16,0.6,1.4,C.steelDk,99,6,24); efbConv.rotation.y = -0.6; efbConv.rotation.z = 0.25;
  box(4,4,4,C.steelDk,106,6,28);
  heap(106,28,4.5,140,[C.efb],0.7,0,0.8);
  sign('THRESHING', 12, 1.6, 'z', 80, 18.2, 22.5);

  /* 5. pressing */
  shed(66,40,94,62,13);
  box(26,0.5,8,C.steel,80,5,46);
  box(26,0.8,1.2,C.steelDk,80,11.6,46.5);
  for (const x of [71,76.5,82,87.5]){
    cyl(1.6,6,C.green,x,5.5,46); cone(1.6,1.2,C.greenDk,x,11.5,46);
    box(5,1.6,1.8,C.fruit,x,0.4,54); cyl(0.9,1.4,C.steelDk,x+2.9,1.2,54,'x');
    box(0.5,5.4,0.5,C.steelDk,x,0,49.5,scene,false);
  }
  box(26,0.8,1.2,C.steelDk,80,3,58.5);
  for (const z of [43,46.5]) cyl(0.9,2.6,C.tank,63.5,0,z);
  legs(63.2,52,1,2); box(3,1.2,2.6,C.steel,63.2,2,52);
  cyl(1.8,3.5,C.oil,63.2,0,58);
  sign('PRESSING', 12, 1.6, 'z', 80, 14.2, 62.5);

  /* 6. depericarping
     GUGUSNYA DIGESER (-7, +24) DARI TATA LETAK DESAIN ASLI, dan sebabnya
     dapat diukur, bukan selera.

     MASALAHNYA: pada posisi lamanya (menara di -12,40) stasiun ini berdiri
     PERSIS DI BELAKANG gedung Kernel Plant (2,51) pada sudut pandang
     isometrik tur ini. camRight = (0.707, 0, -0.707), sehingga selisih x dan
     z dunia saling MENGHAPUS di layar: dx=-14 dan dz=-11 hanya menyisakan
     jarak layar 9,4 satuan, sementara ambang supaya dua perhentian terbaca
     terpisah pada frustum setinggi 34 adalah sekitar 27. Akibatnya dua
     stasiun yang berbeda terbaca sebagai satu perhentian yang terulang.

     DAN INI TIDAK DAPAT DIPERBAIKI DENGAN KAMERA. Keduanya BERTUMPUK pada
     sumbu pandang, bukan sekadar berdekatan; tidak ada nilai focus/size yang
     memisahkan dua benda yang satu tepat di belakang yang lain. Percobaan
     pertama menggesernya ke (-38,43) dan hanya MENUKAR tabrakannya: di sana
     ia bertumpuk dengan gedung Laboratorium QC (-46,28), jarak layar 9,8 —
     sama buruknya.

     POSISI INI DICARI, BUKAN DITEBAK: sapuan atas seluruh lahan yang bebas
     tabrakan, dengan syarat tetap berada dalam 25 satuan dari Kernel Plant
     (depericarper memang bertetangga dengannya di pabrik sungguhan — serat
     dan biji langsung masuk ke sana, jadi memindahkannya jauh akan
     mengkhianati kenyataan demi kerapian gambar). Hasil terbaiknya (-19,64)
     dengan jarak layar 23,7 ke tetangga terdekatnya (Boiler Room). Masih
     sedikit di bawah 27, tetapi dua setengah kali lebih baik daripada 9,4 dan
     satu-satunya yang dapat dicapai tanpa memindahkannya keluar dari
     lingkungan kernel plant.

     CONVEYOR CBC IKUT DIROUTE ULANG, bukan dibiarkan: jalur utamanya tetap di
     z=37 membawa press cake dari Pressing ke barat, lalu satu conveyor silang
     membawanya ke utara menuju posisi baru. Conveyor yang berhenti di udara
     hanya memindahkan satu cacat tampilan ke cacat yang lain. */
  box(90,1,1.4,C.steelDk,21,6,37);
  for (let x=-18;x<66;x+=12) box(0.4,6,0.4,C.steel,x,0,37,scene,false);
  box(1.4,1,26,C.steelDk,-21,6,50);
  for (const z of [43,56]) box(0.4,6,0.4,C.steel,-21,0,z,scene,false);
  box(3,20,3,C.steel,-19,0,64); cyclone(-19,20,64,1.6);
  cyl(1.2,5,C.green,-21,2.4,59,'x'); legs(-21,59,1.4,1.2);
  box(2.6,7,2.6,C.steel,-25,0,70); cyclone(-25,7,70,1.1);
  box(4,1,1.2,C.steelDk,-22,8.5,67);
  post('DEPERICARPER', 10, -21, 55.5, 'z', 1.4, 3.4);

  /* 7. kernel plant */
  box(16,18,12,C.wall,0,0,48); box(16.3,1,12.3,C.green,0,15.5,48); box(15.6,0.5,11.6,C.roof,0,18,48);
  for (const y of [4,9,14]) box(12,1.2,0.1,C.glass,0,y,54.05,scene,false);
  cyclone(-3,18.5,46,1.1); cyclone(3,18.5,46,1.1);
  for (const z of [44,51]) silo(12,z,2,7,C.steel);
  for (const x of [-7,-2]) silo(x,59,2,7,C.green);
  box(2.4,2.2,2.4,C.steelDk,4,0,58); box(2.4,2.2,2.4,C.steelDk,7,0,58);
  cyl(1.5,2.6,C.tank,12,0,58.5);
  box(1.4,1.4,24,C.steelDk,-14,12,72);
  sign('KERNEL PLANT', 13, 1.8, 'x', 8.16, 15.5, 48);

  /* 8. clarification */
  box(36,0.4,26,C.wall,41,0,50,scene,false);
  cyl(5,9,C.tank,40,0.4,50); cone(5.3,2,C.steelDk,40,9.4,50); box(10.2,0.5,0.3,C.green,40,7,55.1,scene,false);
  cyl(2.8,7,C.tank,52,0.4,43); cone(3,1.2,C.steelDk,52,7.4,43);
  cyl(2.8,6,C.tank,29,0.4,43); cone(3,1.2,C.steelDk,29,6.4,43);
  cyl(1.1,2.4,C.steelDk,57,0.4,51);
  for (const x of [49,52,55]) box(0.3,4.6,0.3,C.steelDk,x,0,58,scene,false);
  cyl(1.3,7,C.green,52,6.2,58,'x');
  for (const x of [42,44,46]) cyl(0.65,1.8,C.white,x,0.4,59);
  box(5,1.2,1.6,C.green,33,0.4,59); cyl(0.8,4,C.steel,33,1.9,59,'x');
  for (const x of [26,28]) cyl(1.2,3,C.tank,x,0.4,52);
  box(5,0.45,3.4,C.efb,27,0.3,59);
  box(1.4,1,1.2,C.steelDk,56,0.4,61.5);
  box(8,0.5,0.9,C.steelDk,28,3.8,61.5);
  sign('CLARIFICATION', 13, 1.6, 'z', 40, 2.6, 63.5);
  pipe([[66,50],[58,50]], 3.2, C.oil);
  pipe([[52,60],[52,72],[48,72],[48,86]], 5.5, C.oil);

  /* 9. boiler room */
  box(18,15,16,C.steel,-5,0,94); box(18.3,1,16.3,C.greenDk,-5,13,94); box(17.6,0.5,15.6,C.steelDk,-5,15,94);
  for (const x of [-11,-5,1]) box(3,4.6,0.2,C.dark,x,0,102.05,scene,false);
  cyl(1.5,44,C.steelDk,-18,0,88,'y',scene,16); box(3.4,1.4,3.4,C.fruit,-18,40,88);
  box(4,4,4,C.steelDk,-15.5,0,94);
  const bfc = box(18,1.2,1.4,C.steelDk,-6,10,82); bfc.rotation.z = 0.12;
  sign('BOILER ROOM', 9, 1.6, 'x', 4.16, 9.5, 94);
  box(10,0.3,8,C.wall,-25,0,108,scene,false);
  for (let i=0;i<4;i++) cyl(0.6,4,C.white,-28.5+i*2.3,0.3,106);
  box(8,1.6,2,C.green,-25,0.3,110.5); for (const x of [-27.5,-25,-22.5]) cyl(0.8,3,C.tank,x,1.9,110.5);
  post('RO · DEMIN PLANT', 10, -25, 114, 'z', 1.3, 3.2);
  pipe([[-8,86],[-8,80],[-26,80],[-26,26],[42,26]], 7.6, C.steelDk);

  /* 10. engine room */
  shed(9,88,27,101,9);
  for (const z of [93,97.5]) { box(5,0.6,2.4,C.steelDk,15,0,z); cyl(1,4,C.green,14,1.6,z,'x'); box(2.4,2,2,C.fruit,18,0.6,z); }
  for (const z of [92,95]) { box(2,1.4,1.4,C.steel,23.5,0,z); cyl(0.5,1.6,C.tank,23.5,1.4,z); }
  legs(22,99,0.9,2.6); cyl(1,5,C.steel,22,3.6,99,'x');
  for (let i=0;i<5;i++) box(0.8,2.2,1.2,C.greenDk,10.4,0,90.6+i*1.4);
  for (const x of [13,19]) { box(3,3,3,C.steelDk,x,0,106); box(3.4,0.3,3.4,C.steel,x,3,106); }
  sign('ENGINE ROOM', 9, 1.3, 'z', 18, 9.6, 101.6);

  /* 11. storage tank */
  box(48,1,0.6,C.wall,59,0,84); box(48,1,0.6,C.wall,59,0,110); box(0.6,1,26,C.wall,35,0,97); box(0.6,1,26,C.wall,83,0,97);
  for (const x of [47,71]) { cyl(8.5,12,C.tank,x,0,97,'y',scene,32); box(0.3,2,0.3,C.steelDk,x,12,97);
    const top = new THREE.Mesh(new THREE.ConeGeometry(8.8,1.6,32), mat(C.roof)); top.position.set(x,12.8,97); top.castShadow = true; scene.add(top);
    cyl(8.6,1.2,C.green,x,9.6,97,'y',scene,32); }
  silo(89,116,3.2,9,C.steel,5);
  sign('CPO STORAGE TANK', 13, 1.6, 'z', 59, 3, 110.5);
  post('BULK SILO', 7, 89, 121.6, 'z', 1.2, 2.6);

  /* 12. effluent plant */
  const ponds = [0x5B4636,0x5F5040,0x655B47,0x6A684F,0x6D7656,0x6E8566];
  const aerators = [];
  ponds.forEach((c,i) => { const x = 104 + (i%3)*13, z = 92 + Math.floor(i/3)*15;
    box(12,0.7,13,C.soil,x,0,z,scene,false); box(10.4,0.75,11.4,c,x,0,z,scene,false);
    if (i >= 2) { box(1.4,0.5,1.4,C.yellow,x,0.75,z); const p = new THREE.Group(); p.position.set(x,1.3,z); scene.add(p);
      box(3,0.15,0.4,C.steelDk,0,0,0,p); box(0.4,0.15,3,C.steelDk,0,0,0,p); aerators.push(p);
      const ring = new THREE.Mesh(new THREE.RingGeometry(1.6,2.2,24).rotateX(-Math.PI/2), new THREE.MeshBasicMaterial({color:0xFFFFFF, transparent:true, opacity:0.55})); ring.position.set(x,0.8,z); scene.add(ring); aerators.push(ring); } });
  for (const z of [86,101]) { box(2,1.4,1.6,C.green,96,0,z); cyl(0.3,1,C.steelDk,96,1.4,z); }
  post('EFFLUENT PLANT', 11, 117, 116, 'z', 1.4, 3.4);

  /* 13. oil dispatch: pumping station + jetty + barge */
  pipe([[71,86],[71,80],[144,80],[144,64]], 2.4, C.oil);
  box(5,3,4,C.white,144,0,60); box(5.2,0.4,4.2,C.green,144,3,60);
  box(14,0.5,5,C.wall,154,1.2,60);
  for (const x of [149,153,157,160]) for (const z of [58,62]) box(0.5,1.8,0.5,C.steelDk,x,-0.6,z,scene,false);
  box(0.6,5,0.6,C.fruit,159,1.7,60); const arm = box(5,0.4,0.4,C.fruit,161,6.4,60); arm.rotation.z = -0.35;
  const barge = new THREE.Group(); scene.add(barge);
  box(9,2.6,28,C.navy || 0x1E2B44,0,-1.2,0,barge); box(9.2,0.4,28.2,C.fruit,0,0.9,0,barge,false);
  for (const z of [-8,0,8]) { cyl(3,0.9,C.tank,0,1.4,z,'y',barge); box(0.4,1.2,0.4,C.steelDk,0,2.3,z,barge); }
  box(7,3,4,C.white,0,1.4,-12,barge);
  barge.position.set(167,0,60);
  sign('JETTY · OIL DISPATCH', 13, 1.4, 'z', 144, 4.6, 62.4);

  /* 14. kernel dispatch */
  shed(102,36,128,52,9);
  for (let r=0;r<3;r++) for (let c=0;c<5;c++) { const h = 3 + Math.floor(rng()*3);
    for (let k=0;k<h;k++) box(1.6,0.55,1.1,C.sack,106+c*3.4,0.2+k*0.58,40+r*3,scene,k===h-1); }
  { const t = makeTruck('sacks'); t.position.set(116,0,57); }
  person(110,54,C.fruit,true); person(113,56,C.green,true); person(122,50,C.fruit);
  sign('KERNEL DISPATCH', 11, 1.3, 'z', 115, 9.4, 52.6);

  /* 15. process water */
  box(3,2.6,3,C.white,147,0,12); box(3.2,0.3,3.2,C.green,147,2.6,12);
  pipe([[146,12],[134,12]], 1.6, C.steelDk);
  cyl(5,3,C.tank,128,0,8); cyl(4.4,0.2,C.water,128,3,8); box(9.6,0.4,0.6,C.steelDk,128,3.3,8);
  for (const x of [119,122.5,126]) cyl(1.2,4.2,C.tank,x,0,20);
  box(10,1.2,7,C.wall,138,0,22); box(9,0.25,6,C.water,138,1,22,scene,false);
  cyl(3.5,5,C.tank,116,0,4); cyl(3.6,0.6,C.green,116,4,4);
  box(2,1.4,1.6,C.green,116,0,10); box(2,1.4,1.6,C.green,119,0,10);
  post('PROCESS WATER', 10, 128, 27.5, 'z', 1.4, 3.2);

  /* 16. solid waste disposal */
  box(20,0.2,18,C.soil,-44,0,112,scene,false);
  heap(-44,114,6.5,300,[C.efb,C.fiber,C.efb],0.75,0.2,0.75);
  const exc = new THREE.Group(); exc.position.set(-51,0,106); scene.add(exc);
  for (const dz of [-1.3,1.3]) box(4.4,1,1,C.dark,0,0,dz,exc);
  const upper = new THREE.Group(); upper.position.y = 1; exc.add(upper);
  box(3.6,1.6,2.6,C.yellow,-0.3,0,0,upper); box(1.4,1.6,1.3,C.glass,1,1.6,-0.6,upper);
  const boom = new THREE.Group(); boom.position.set(1.2,1.6,0.6); upper.add(boom);
  const b1 = box(5,0.5,0.5,C.yellow,2.3,0,0,boom); b1.rotation.z = 0.5;
  box(0.5,3,0.5,C.yellow,4.6,-1.4,0,boom); box(1.2,0.9,1,C.dark,4.8,-2.2,0,boom);
  { const t = makeTruck('waste'); t.rotation.y = -Math.PI/2; t.position.set(-36,0,113); }
  post('SOLID WASTE', 9, -46, 122, 'z', 1.3, 3);

  /* 17. quality control lab */
  box(12,5,8,C.white,-46,0,28); box(12.3,0.6,8.3,C.green,-46,5,28);
  for (const x of [-49,-46,-43]) box(2,1.4,0.1,C.glass,x,2,32.05,scene,false);
  box(0.1,1.4,4,C.glass,-39.95,2,28,scene,false);
  for (const x of [-49,-45]) box(1.4,0.9,1,C.steel,x,5.6,26);
  person(-41,34,C.white);
  sign('LABORATORIUM QC', 10, 1.3, 'z', -46, 3.6, 32.3);

  /* palm plantation around the mill (triangular planting grid, gaps for estate roads) */
  { const pts = [];
    for (let r=0, z=-170; z<280; z+=8, r++){
      if (r % 9 === 4) continue;
      for (let x=-200 + (r%2)*4.5; x<320; x+=9){
        if (x > -70 && x < 150 && z > -33 && z < 138) continue;
        if (x > 145 && x < 194) continue;
        if (z > -20 && z < -8) continue;
        if (z > 122 && z < 134) continue;
        pts.push([x + (rng()-0.5)*0.8, z + (rng()-0.5)*0.8, 4 + rng()*3.5]);
      }
    }
    const trunkG = new THREE.CylinderGeometry(0.35,0.5,1,6).translate(0,0.5,0);
    const frondG = new THREE.BoxGeometry(1,1,1).translate(0.5,0,0);
    const trunks = new THREE.InstancedMesh(trunkG, mat(C.trunk), pts.length);
    const F = 8, half = Math.ceil(pts.length/2);
    const f1 = new THREE.InstancedMesh(frondG, mat(C.palm1), half*F), f2 = new THREE.InstancedMesh(frondG, mat(C.palm2), (pts.length-half)*F);
    const d = new THREE.Object3D();
    pts.forEach(([x,z,h],i) => {
      d.position.set(x,0,z); d.rotation.set(0,0,0); d.scale.set(1,h,1); d.updateMatrix(); trunks.setMatrixAt(i,d.matrix);
      const rot = rng()*6.28, target = i < half ? f1 : f2, base = (i < half ? i : i-half) * F;
      for (let k=0;k<F;k++){
        const up = k % 2 === 0;
        d.position.set(x,h,z); d.rotation.set(0, rot + k*Math.PI*2/F, up ? -0.15 : 0.45);
        d.scale.set(up ? 3.6 : 4.4, 0.12, 1.1); d.updateMatrix(); target.setMatrixAt(base+k, d.matrix);
      }
    });
    for (const m of [trunks,f1,f2]) { m.castShadow = true; m.receiveShadow = true; scene.add(m); }
  }

  /* moving parts */
  const lorries = [];
  for (let i=0;i<8;i++){ const g = makeLorry(); g.position.set(0,0.3,10); lorries.push({g, pos:-6 + i*9.4}); }
  const trucks = [];
  for (let i=0;i<4;i++){ const t = makeTruck('tbs'); trucks.push({m:t, pos:-210 + i*55, z:-15.7, dir:1, min:-215, max:205, v:8}); }
  for (let i=0;i<2;i++){ const t = makeTruck(i ? 'sacks' : 'tanker'); t.rotation.y = Math.PI; trucks.push({m:t, pos:30 + i*100, z:129.7, dir:-1, min:-150, max:260, v:7}); }

  /* truk TBS satu putaran penuh: berhenti di timbangan 1 (bruto), mundur ke
     loading ramp dan menjungkit bak, berhenti di timbangan 2 (tara), keluar.
     Dua truk, berjarak setengah putaran — tidak pernah berpapasan: saat yang
     satu di jalan raya, yang lain sedang di halaman. */
  const P = (x,z) => new THREE.Vector3(x,0,z);
  const L = (a,b) => new THREE.LineCurve3(P(...a),P(...b));
  const Q = (a,c,b) => new THREE.QuadraticBezierCurve3(P(...a),P(...c),P(...b));
  const S = (a,c1,c2,b) => new THREE.CubicBezierCurve3(P(...a),P(...c1),P(...c2),P(...b));
  function lane(...cs){ const p = new THREE.CurvePath(); cs.forEach(c => p.add(c)); return p; }
  const TIP_V = 10, TIP_ACC = 1.2;
  const tipSteps = [
    {path: lane(L([-170,-12.3],[-80,-12.3]), S([-80,-12.3],[-72,-12.3],[-72,-9.5],[-64,-9.5]), L([-64,-9.5],[-50,-9.5]))},
    {wait: 5},
    {path: lane(L([-50,-9.5],[-38,-9.5]), Q([-38,-9.5],[-34,-9.5],[-34,-5.5]), L([-34,-5.5],[-34,19]))},
    {wait: 0.8},
    {path: lane(L([-34,19],[-34,14]), Q([-34,14],[-34,10],[-30,10])), rev: true},
    {wait: 6, dump: true},
    {path: lane(Q([-30,10],[-34,10],[-34,6]), L([-34,6],[-34,0.5]), Q([-34,0.5],[-34,-3.5],[-38,-3.5]), L([-38,-3.5],[-50,-3.5]))},
    {wait: 5},
    {path: lane(L([-50,-3.5],[-58,-3.5]), S([-58,-3.5],[-66,-3.5],[-64,-12.3],[-72,-12.3]), L([-72,-12.3],[-170,-12.3]))},
  ];
  let tipT = 0;
  for (const s of tipSteps){
    if (s.path){ const run = s.path.getLength() / TIP_V; s.acc = Math.min(TIP_ACC, run); s.dur = run + s.acc; }
    else s.dur = s.wait;
    s.t0 = tipT; tipT += s.dur;
  }
  const tipDump = tipSteps.findIndex(s => s.dump);
  // jarak tempuh berprofil trapesium: mulai dan berhenti dengan halus
  function tipRun(t, T, a){
    const v = 1 / (T - a);
    if (t < a) return 0.5*v*t*t/a;
    if (t > T - a) { const r = T - t; return 1 - 0.5*v*r*r/a; }
    return v*(t - a/2);
  }
  function tipPose(t){
    let i = tipSteps.length - 1; while (tipSteps[i].t0 > t) i--;
    const s = tipSteps[i], k = t - s.t0;
    if (s.path) { const u = Math.min(1, Math.max(0, tipRun(k, s.dur, s.acc))); return {at:s.path.getPointAt(u), dir:s.path.getTangentAt(u), rev:!!s.rev, step:i, k:0}; }
    const p = tipSteps[i-1];
    return {at:p.path.getPointAt(1), dir:p.path.getTangentAt(1), rev:!!p.rev, step:i, k:k/s.dur};
  }
  // mulai dengan truk pertama tepat di timbangan 1, juga untuk reduced-motion
  const tipFrom = tipSteps[1].t0;
  // tumpukan TBS hasil jungkit di kaki loading ramp; tumbuh selagi bak menumpahkan
  const tipHeap = new THREE.Group(); tipHeap.position.set(-22.6,0,10); scene.add(tipHeap);
  { const hg = new THREE.IcosahedronGeometry(0.55,0), tones = [C.fruit,C.fruitDk,C.fruitBk];
    for (let i=0;i<24;i++){
      const a = i*2.4, r = Math.sqrt((i%8 + 1)/8)*1.9, m = new THREE.Mesh(hg, mat(tones[i%3]));
      m.position.set(Math.cos(a)*r*0.7, 0.45 + Math.max(0, 1.5 - r*0.7) + (i%3)*0.18, Math.sin(a)*r);
      m.rotation.set(i, i*1.7, 0); m.castShadow = true; tipHeap.add(m);
    } }
  const tippers = [0, 0.5].map(h => ({m: makeTruck('tbs'), ph: h*tipT}));

  const puffs = [], puffG = new THREE.IcosahedronGeometry(1,1);
  function emitter(x,y,z,n,color,spread,rise,life){
    for (let i=0;i<n;i++){
      const m = new THREE.Mesh(puffG, new THREE.MeshLambertMaterial({color, transparent:true, opacity:0.6, depthWrite:false}));
      scene.add(m); puffs.push({m, x, y, z, ph:i/n, spread, rise, life, seed:rng()*6.28});
    }
  }
  emitter(55,10.8,10,9,0xFFFFFF,1.2,9,4);
  emitter(63,10,22,8,0xFFFFFF,1,8,3.5);
  emitter(-18,44,88,14,0xC9CCC8,2.2,16,6);

  const marker = new THREE.Group();
  const pin = new THREE.Mesh(new THREE.ConeGeometry(1.3,3,4).rotateX(Math.PI), mat(C.accent)); pin.position.y = 1.5; marker.add(pin);
  const ringM = new THREE.MeshBasicMaterial({color:C.accent, transparent:true, opacity:0.5, depthWrite:false});
  const ring = new THREE.Mesh(new THREE.RingGeometry(2.6,3.2,40).rotateX(-Math.PI/2), ringM); ring.position.y = 0.3;
  scene.add(marker, ring);

  /* camera rig */
  const smooth = t => t*t*(3-2*t);
  function desired(){
    const s = stopFloat(), i = Math.min(N-2, Math.floor(s)), f = s - i;
    const e = smooth(Math.min(1, Math.max(0, (f - 0.18) / 0.64)));
    const a = STOPS[i], b = STOPS[i+1], L = (p,q) => p + (q-p)*e;
    return {x:L(a.focus[0],b.focus[0]), z:L(a.focus[1],b.focus[1]), size:L(a.size,b.size), mark:L(a.mark,b.mark)};
  }
  const cur = desired();
  let W = 1, H = 1;
  function resize(){ W = stage.clientWidth; H = stage.clientHeight; renderer.setSize(W, H, false); }
  addEventListener('resize', resize); resize();
  let zoomCur = zoomTarget;
  const focusV = new THREE.Vector3(), target = new THREE.Vector3();
  function applyCamera(){
    const aspect = W / H, viewH = cur.size * (aspect < 1 ? 1.5 : 1) / zoomCur, viewW = viewH * aspect;
    cam.left = -viewW/2; cam.right = viewW/2; cam.top = viewH/2; cam.bottom = -viewH/2; cam.updateProjectionMatrix();
    let sx = 0, sy = 0;
    if (W > 720) sx = ((card.offsetLeft + card.offsetWidth) / 2) / W * viewW;
    else sy = ((card.offsetHeight + 12) / 2 - 30) / H * viewH;
    focusV.set(cur.x, 0, cur.z);
    target.copy(focusV).addScaledVector(camRight, -sx).addScaledVector(camUp, -sy - viewH*0.04);
    cam.position.copy(target).add(OFF);
    sun.position.copy(focusV).add(SUN_OFF); sun.target.position.copy(focusV);
  }

  let clock = 0, last = performance.now(), visible = true;
  function animate(dt){
    clock += dt;
    for (const l of lorries){ l.pos += 2.6*dt; if (l.pos > 66) l.pos -= 72; l.g.position.x = l.pos; }
    for (const t of trucks){ t.pos += t.dir*t.v*dt; if (t.pos > t.max) t.pos = t.min; if (t.pos < t.min) t.pos = t.max; t.m.position.set(t.pos,0,t.z); }
    for (const tr of tippers){
      const p = tipPose((clock + tipFrom + tr.ph) % tipT), f = p.rev ? -1 : 1;
      tr.m.position.copy(p.at); tr.m.rotation.y = Math.atan2(-p.dir.z*f, p.dir.x*f);
      // jungkit: bak naik, muatan meluncur keluar ke ramp, bak turun; muatan kembali di putaran berikutnya
      const {bed, load} = tr.m.userData, d = p.step === tipDump ? p.k : 0;
      bed.rotation.z = 0.75 * smooth(Math.min(1, d/0.3, (1-d)/0.3));
      const out = smooth(Math.min(1, Math.max(0, (d-0.3)/0.35)));
      load.position.x = 3.8 - out*7;
      load.visible = p.step < tipDump || (p.step === tipDump && out < 1);
      // satu truk saja yang menumpahkan pada satu waktu; tumpukan baru dibuang saat truk berikutnya mulai
      if (p.step === tipDump) tipHeap.scale.setScalar(Math.max(0.001, out));
    }
    drum.rotation.x += dt * 2.4;
    capDrum.rotation.y += dt * 1.5;
    const tp = (clock % 8) / 8; tippler.rotation.x = tp < 0.5 ? smooth(Math.min(1, tp*3)) * 2.3 : smooth(Math.max(0, 1 - (tp-0.5)*3)) * 2.3;
    tipLorry.children[1].visible = tippler.rotation.x < 1.6 && tp < 0.5;
    carriage.position.z = 10 + Math.sin(clock*0.4) * 7;
    upper.rotation.y = Math.sin(clock*0.5) * 0.9 - 0.4; boom.rotation.z = Math.sin(clock*1.0) * 0.18;
    barge.position.y = Math.sin(clock*0.8) * 0.12;
    for (const a of aerators) if (a.isGroup) a.rotation.y += dt*4; else a.scale.setScalar(1 + ((clock*0.8) % 1) * 0.5);
    for (const p of puffs){
      const k = (clock / p.life + p.ph) % 1;
      p.m.position.set(p.x + Math.sin(p.seed + k*3)*p.spread*k, p.y + k*p.rise, p.z + Math.cos(p.seed)*p.spread*k*0.6);
      p.m.scale.setScalar(0.8 + k*p.spread*1.6); p.m.material.opacity = 0.55 * (1-k) * Math.min(1, k*6);
    }
  }
  new IntersectionObserver(es => { visible = es[0].isIntersecting; if (visible) { last = performance.now(); requestAnimationFrame(frame); } }).observe(intro);
  function frame(now){
    if (!visible) return;
    const dt = Math.min(0.05, (now - last) / 1000); last = now;
    const d = desired(), k = reduced ? 1 : 1 - Math.pow(0.0025, dt);
    for (const key of ['x','z','size','mark']) cur[key] += (d[key] - cur[key]) * k;
    zoomCur += (zoomTarget - zoomCur) * k;
    if (!reduced) animate(dt);
    marker.position.set(cur.x, cur.mark + (reduced ? 0 : Math.sin(now/400)*0.6), cur.z);
    pin.rotation.y = now/900;
    ring.position.x = cur.x; ring.position.z = cur.z;
    const rk = reduced ? 0 : (now/1600) % 1; ring.scale.setScalar(1 + rk*1.4); ringM.opacity = 0.55*(1-rk);
    applyCamera(); renderer.render(scene, cam);
    requestAnimationFrame(frame);
  }
  animate(reduced ? 3 : 0.01);
  requestAnimationFrame(frame);
}
const fontWait = document.fonts && document.fonts.load
  ? Promise.race([document.fonts.load('700 100px "Inter"'), new Promise(r => setTimeout(r, 1500))])
  : Promise.resolve();
fontWait.catch(()=>{}).then(build);
})();
</script>
</body>
</html>
