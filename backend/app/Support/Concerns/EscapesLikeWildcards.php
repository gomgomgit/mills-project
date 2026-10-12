<?php

namespace App\Support\Concerns;

/**
 * Pencarian teks bebas yang memperlakukan `%` dan `_` sebagai HURUF BIASA.
 *
 * Keduanya adalah wildcard LIKE. Tanpa di-escape, mengetik `100%` mencari
 * "100 lalu apa saja" — ia menemukan "Machine 0100" yang tidak memuat tanda
 * persen sama sekali; dan `BU_A` ikut mengenai `BUxA`, karena `_` cocok
 * dengan satu karakter apa pun. Pengguna tidak punya cara menduga itu: yang
 * ia ketik adalah nama yang ia lihat di layar (temuan QA MD-KEL-11).
 *
 * KLAUSA ESCAPE DITULIS EKSPLISIT, dan itu bukan hiasan: PostgreSQL
 * menetapkan karakter escape LIKE ke backslash secara bawaan sementara
 * SQLite TIDAK punya bawaan sama sekali. Tanpa `escape '\'` yang ditulis
 * sendiri, backslash hasil escape di sini akan diperlakukan sebagai huruf
 * biasa oleh SQLite — pencarian `BU_A` tetap mengenai `BUxA`, seluruh uji
 * tetap hijau, dan cacatnya hanya muncul di produksi (atau sebaliknya,
 * tergantung mesinnya). Dinyatakan eksplisit, kedua mesin memperlakukannya
 * sama.
 *
 * lower()+LIKE, BUKAN ILIKE: ILIKE hanya ada di PostgreSQL dan ditolak
 * SQLite sebagai galat sintaks — pilihan yang sama dengan
 * App\Rules\UniqueCaseInsensitive, dengan sebab yang sama.
 */
trait EscapesLikeWildcards
{
    /**
     * Pola LIKE "memuat" untuk satu kata kunci: sudah dikecilkan hurufnya,
     * wildcard-nya sudah di-escape, dan dibungkus `%…%`.
     */
    protected function likeContains(string $term): string
    {
        return '%'.$this->escapeLike(mb_strtolower($term)).'%';
    }

    /**
     * Ekspresi SQL pendamping `likeContains()` — selalu berpasangan dengan
     * klausa ESCAPE-nya, supaya tidak ada pemanggil yang lupa menuliskannya.
     */
    protected function likeExpression(string $column): string
    {
        return 'lower('.$column.') like ? escape \'\\\'';
    }

    /**
     * Meng-escape wildcard LIKE supaya kata kunci yang memuat `%` atau `_`
     * mencari karakter itu apa adanya, bukan mencocokkan apa saja.
     *
     * Backslash di-escape LEBIH DULU; kalau tidak, backslash yang
     * ditambahkan untuk `%` dan `_` akan ikut ter-escape lagi.
     */
    protected function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
