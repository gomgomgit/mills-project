import type { Directive } from 'vue'

/**
 * v-stacked-table — menyalin teks setiap `<th>` ke atribut `data-label` pada
 * sel `<td>` yang sekolom dengannya.
 *
 * Dipakai bersama src/assets/stacked-table.css, yang pada lebar ponsel
 * mengubah tabel rekap laporan menjadi kartu bertumpuk dan mencetak label itu
 * lewat `content: attr(data-label)` (temuan QA LP-M-02 / LP-M-03).
 *
 * MENGAPA DIREKTIF, BUKAN `data-label` DITULIS DI MARKUP. Ke-25 tabel laporan
 * mobile punya 5 sampai 11 kolom, sebagian dirender `v-for` dari daftar
 * metrik yang panjangnya berbeda per stasiun. Menuliskan labelnya tangan
 * berarti ratusan atribut dan DUA sumber kebenaran untuk nama kolom yang
 * sama: `<th>` dan `data-label` akan menyimpang pada perubahan berikutnya,
 * dan kartu akan menyebut kolom dengan nama yang sudah tidak ada di web.
 *
 * Dijalankan ulang pada `updated` karena Vue menulis ulang sel saat datanya
 * berganti (periode/line baru, metrik yang berbeda jumlahnya), dan atribut
 * yang ditambahkan di luar render akan hilang bersama sel lamanya.
 */
function terapkanLabel(table: HTMLElement): void {
  const headerRow = table.querySelector('thead tr')

  if (!headerRow) {
    return
  }

  const labels = Array.from(headerRow.children).map((th) => (th.textContent ?? '').trim())

  if (labels.length === 0) {
    return
  }

  // tfoot ikut: baris total juga menjadi satu kartu, dan sel-selnya butuh
  // label yang sama supaya angkanya tidak kehilangan artinya.
  table.querySelectorAll('tbody tr, tfoot tr').forEach((row) => {
    Array.from(row.children).forEach((cell, index) => {
      const label = labels[index]

      if (label === undefined || label === '') {
        cell.removeAttribute('data-label')

        return
      }

      // colspan membuat pemetaan per indeks tidak lagi benar: sebuah sel yang
      // menutupi beberapa kolom bukan milik satu label mana pun. Dibiarkan
      // tanpa label alih-alih diberi label yang salah.
      if (cell.hasAttribute('colspan')) {
        cell.removeAttribute('data-label')

        return
      }

      cell.setAttribute('data-label', label)
    })
  })
}

export const vStackedTable: Directive<HTMLElement> = {
  mounted(el) {
    terapkanLabel(el)
  },
  updated(el) {
    terapkanLabel(el)
  },
}

export default vStackedTable
