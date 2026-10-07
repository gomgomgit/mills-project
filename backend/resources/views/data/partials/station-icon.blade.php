{{--
    Ikon stasiun per `station_types.code`, dipakai grid Production Process
    Activity (screen-035).

    DIPISAHKAN KE PARTIAL pada 2026-10-07 karena gridnya berhenti menjadi 18
    blok literal dan menjadi loop atas `station_types`. Sebelum itu setiap SVG
    menempel pada tile-nya masing-masing; sebuah loop tidak dapat membawanya,
    jadi ikonnya butuh satu tempat yang dapat dicari per kode.

    IKONNYA TETAP DI KODE, BUKAN DI BASIS DATA, dan itu disengaja: pasangannya
    ada di repo LAIN — `ACTIVE_ICONS` dan `PLACEHOLDER_ICONS` pada mobile
    `StationGrid.vue`. Ia ada di sini supaya satu stasiun tidak bergambar beda
    di web dan di ponsel. Memindahkannya ke master berarti mobile harus
    mengambil ikon lewat jaringan untuk mendapatkan paritas yang sekarang
    gratis.

    Jalur SVG di bawah SALINAN PERSIS dari yang sebelumnya tertanam di
    production-process-activity.blade.php, yang sendirinya salinan dari
    StationGrid.vue. Tidak satu pun digambar ulang saat dipindahkan ke sini —
    kalau ada yang berubah, itu bug pemindahan, bukan perbaikan desain.

    Kode yang TIDAK ada di sini mendapat ikon cadangan (kotak bertanda tanya),
    bukan sel kosong: baris master baru tanpa ikon harus terlihat sebagai
    "stasiun ini belum punya gambar", bukan sebagai tile yang rusak.

    @param string $code  station_types.code
--}}
@php
    $iconPaths = [
        'weighbridge' => '<circle cx="12" cy="13" r="8"></circle><path d="M12 9v4l3 2"></path><path d="M9 3h6"></path>',
        'pressing' => '<path d="M4 20V10l8-6 8 6v10"></path><line x1="12" y1="14" x2="12" y2="20"></line>',
        'storage-tank' => '<rect x="4" y="6" width="16" height="14" rx="1"></rect><path d="M8 6V4h8v2"></path>',
        'grading' => '<rect x="3" y="4" width="18" height="4"></rect><rect x="3" y="10" width="18" height="4"></rect><rect x="3" y="16" width="18" height="4"></rect>',
        'clarification' => '<circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="3"></circle>',
        'effluent-plant' => '<circle cx="12" cy="12" r="9"></circle><path d="M8 12h8M12 8v8"></path>',
        'cages-track' => '<rect x="3" y="7" width="18" height="13" rx="1"></rect><path d="M3 11h18"></path><path d="M8 7V4h8v3"></path>',
        'engine-room' => '<rect x="6" y="2" width="12" height="20" rx="1"></rect><line x1="6" y1="8" x2="18" y2="8"></line><line x1="6" y1="14" x2="18" y2="14"></line>',
        'cpo-dispatch' => '<rect x="3" y="5" width="10" height="14" rx="2"></rect><line x1="3" y1="10" x2="13" y2="10"></line><line x1="3" y1="14" x2="13" y2="14"></line><path d="M15 12h6"></path><path d="M18 9l3 3-3 3"></path>',
        'sterilizer' => '<rect x="4" y="4" width="16" height="16" rx="2"></rect><line x1="8" y1="9" x2="16" y2="9"></line><line x1="8" y1="13" x2="16" y2="13"></line>',
        'boiler-room' => '<path d="M6 21V9a6 6 0 0 1 12 0v12"></path><line x1="6" y1="15" x2="18" y2="15"></line>',
        'kernel-dispatch' => '<rect x="3" y="9" width="12" height="10" rx="1"></rect><path d="M15 12h6"></path><path d="M18 9l3 3-3 3"></path>',
        'kernel-plant' => '<rect x="5" y="3" width="14" height="18" rx="1"></rect><line x1="9" y1="8" x2="15" y2="8"></line><line x1="9" y1="12" x2="15" y2="12"></line>',
        'process-water' => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"></path>',
        'threshing' => '<circle cx="12" cy="12" r="9"></circle><line x1="8" y1="12" x2="16" y2="12"></line>',
        'depricarping' => '<rect x="4" y="4" width="16" height="16" rx="8"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line>',
        'solid-waste-disposal' => '<path d="M4 7h16"></path><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>',
        'process-quality-control' => '<path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"></path><path d="M9 12l2 2 4-4"></path>',
    ];

    $fallbackIcon = '<rect x="4" y="4" width="16" height="16" rx="2"></rect><path d="M12 16v.01"></path><path d="M10 9a2 2 0 1 1 3 1.7c-.6.4-1 .8-1 1.3"></path>';
@endphp

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
    {!! $iconPaths[$code] ?? $fallbackIcon !!}
</svg>
