<?php

namespace App\Livewire\Concerns;

/**
 * Aksi "Reset filter" + hitungan filter aktif untuk layar daftar yang
 * memakai x-filter.bar (components/filter/bar.blade.php).
 *
 * Layar pemakai cukup mendeklarasikan filterDefaults(): peta properti
 * filter => nilai bawaannya — nilai yang SAMA dengan deklarasi properti,
 * sehingga reset mengembalikan layar persis ke keadaan saat pertama dibuka.
 *
 * Setelah nilai dikembalikan, halaman kembali ke 1 (bila layar punya
 * $page) dan afterFilterReset() dipanggil untuk efek samping khusus layar
 * (mis. Kelola Mesin mengosongkan grup yang terbuka) — menyalin apa yang
 * dilakukan hook updated<Prop>() masing-masing, karena Livewire TIDAK
 * menjalankan hook itu untuk properti yang diubah dari dalam aksi.
 *
 * Pembatasan cakupan (mill akun terikat) tetap dilakukan render() layar
 * seperti biasa: mengembalikan business_unit_id ke '' bagi Supervisor tidak
 * melebarkan apa pun, karena render() memakunya kembali ke mill akun.
 */
trait HasFilterReset
{
    /**
     * @return array<string, mixed>
     */
    abstract protected function filterDefaults(): array;

    public function resetFilters(): void
    {
        foreach ($this->filterDefaults() as $property => $default) {
            $this->{$property} = $default;
        }

        if (property_exists($this, 'page')) {
            $this->page = 1;
        }

        $this->afterFilterReset();
    }

    protected function afterFilterReset(): void {}

    /**
     * Jumlah filter yang menyimpang dari bawaannya. `$except` untuk
     * properti yang bukan pilihan user di layar ini (mis. mill yang dipaku
     * ke akun Supervisor) — tidak dihitung sebagai "filter aktif".
     *
     * @param  list<string>  $except
     */
    public function activeFilterCount(array $except = []): int
    {
        $count = 0;

        foreach ($this->filterDefaults() as $property => $default) {
            if (in_array($property, $except, true)) {
                continue;
            }

            if ((string) $this->{$property} !== (string) $default) {
                $count++;
            }
        }

        return $count;
    }
}
