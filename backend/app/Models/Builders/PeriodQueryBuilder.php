<?php

namespace App\Models\Builders;

use App\Models\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;

/**
 * Query builder Period yang menolak menyebut kolom yang sudah pindah ke
 * `period_stations` (lihat Period::MOVED_TO_PERIOD_STATIONS).
 *
 * MENGAPA PERLU, PADAHAL KOLOMNYA SUDAH TIDAK ADA DI DB
 * Karena di DB test kolom yang hilang TIDAK menimbulkan error. Laravel mengutip
 * identifier dengan tanda kutip ganda, dan SQLite — yang dipakai seluruh test
 * suite (phpunit.xml memaksa sqlite :memory:) — masih mengaktifkan misfeature
 * "double-quoted string literal": `"status" = 'closed'` atas tabel yang tidak
 * punya kolom `status` diperlakukan sebagai perbandingan dua STRING, bernilai
 * false, tanpa satu pun exception. Diverifikasi 2026-09-26:
 *
 *   select count(*) from periods where "status" = 'closed'  -> 0 baris, no error
 *   select count(*) from periods where  status  = 'closed'  -> no such column
 *
 * PostgreSQL (dev dan produksi) melempar. Jadi sebuah kueri yang lupa diubah
 * akan HIJAU di test dan MELEDAK di produksi — kebalikan dari yang berguna, dan
 * kelas bug yang sama dengan `$period->status === 'closed'` yang selalu false.
 * Penjaga atribut di model tidak menutup jalur ini karena query builder tidak
 * pernah melewati getAttribute().
 *
 * Yang dijaga: seluruh metode where..., having..., orderBy..., groupBy... — baik yang
 * didefinisikan Eloquent Builder sendiri (where, whereNull, whereIn, orderBy,
 * update) maupun yang hanya diteruskan ke query builder lewat __call
 * (orWhereNull, whereNotIn, ...), termasuk bentuk array, nested closure, dan
 * dynamic where (whereStatus()). Yang TIDAK terjaga, dan memang tidak bisa:
 * DB::table('periods') mentah — ia tidak melewati Eloquent sama sekali.
 *
 * Nama kolom berkualifikasi tabel lain dibiarkan lewat, sehingga JOIN ke
 * `period_stations` (mis. where('period_stations.status', ...)) tetap sah —
 * itu justru bentuk kueri yang benar sekarang.
 */
class PeriodQueryBuilder extends Builder
{
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->guardPeriodColumns($column, 'where()');

        return parent::where($column, $operator, $value, $boolean);
    }

    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        $this->guardPeriodColumns($columns, 'whereNull()/whereNotNull()');

        return parent::whereNull($columns, $boolean, $not);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $this->guardPeriodColumns($column, 'whereIn()');

        return parent::whereIn($column, $values, $boolean, $not);
    }

    public function orderBy($column, $direction = 'asc')
    {
        $this->guardPeriodColumns($column, 'orderBy()');

        return parent::orderBy($column, $direction);
    }

    public function update(array $values)
    {
        $this->guardPeriodColumns(array_keys($values), 'update()');

        return parent::update($values);
    }

    /**
     * Metode where yang tidak didefinisikan Eloquent Builder (orWhereNull,
     * whereNotIn, whereBetween, dynamic where, ...) diteruskan ke query builder
     * lewat __call dan karena itu tidak pernah melewati override di atas — ini
     * jaring yang menangkap semuanya.
     */
    public function __call($method, $parameters)
    {
        if (preg_match('/^(or)?(where|having|orderBy|groupBy)/i', $method)) {
            if ($parameters !== []) {
                $this->guardPeriodColumns($parameters[0], $method.'()');
            }

            // Dynamic where: whereStatus('closed'), orWhereClosedAt(null).
            if (preg_match('/^(?:or)?[Ww]here([A-Z][A-Za-z0-9]*)$/', $method, $matches)) {
                $this->guardPeriodColumn(Str::snake($matches[1]), $method.'()');
            }
        }

        return parent::__call($method, $parameters);
    }

    /**
     * @param  mixed  $columns  string, list<string>, atau array<string, mixed>
     *                          (bentuk where(['status' => 'closed']))
     */
    protected function guardPeriodColumns($columns, string $method): void
    {
        if ($columns instanceof \Closure || $columns instanceof Builder) {
            return;
        }

        foreach ((array) $columns as $key => $column) {
            foreach ([$key, $column] as $candidate) {
                if (is_string($candidate)) {
                    $this->guardPeriodColumn($candidate, $method);
                }
            }
        }
    }

    protected function guardPeriodColumn(string $column, string $method): void
    {
        $segments = explode('.', $column);
        $name = array_pop($segments);
        $table = $segments === [] ? 'periods' : end($segments);

        // Kolom milik tabel lain (mis. period_stations.status pada sebuah JOIN)
        // memang boleh — hanya `periods` yang kehilangan kolomnya.
        if ($table !== 'periods') {
            return;
        }

        if (! in_array($name, Period::MOVED_TO_PERIOD_STATIONS, true)) {
            return;
        }

        throw new LogicException(sprintf(
            'Kueri Period menyebut kolom periods.%s di %s, padahal kolom itu pindah ke '
            .'period_stations.%s pada 2026-09-25. Di SQLite (koneksi test) kueri semacam ini '
            .'TIDAK gagal — identifier berkutip ganda atas kolom yang tidak ada diperlakukan '
            .'sebagai string literal, jadi kondisinya sekadar bernilai false dan test bisa '
            .'hijau sementara PostgreSQL meledak. Pakai JOIN atau whereHas ke period_stations, '
            ."mis. Period::whereHas('stations', fn (\$q) => \$q->where('station_type', \$type)"
            ."->where('status', 'closed')).",
            $name,
            $method,
            $name,
        ));
    }
}
