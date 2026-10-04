<?php

namespace App\Livewire\Concerns;

/**
 * File unggahan dicek SAAT DIPILIH (hook updated<Prop>), bukan baru saat
 * Simpan: file yang bukan gambar sungguhan (App\Rules\RealImage) langsung
 * ditolak dengan pesan Indonesia di bawah kolomnya (view menyembunyikan
 * pratinjau selama ada error). File-nya sengaja TIDAK dibuang dari
 * properti: save() memvalidasi ulang dan menolaknya lagi (service-nya
 * juga), jadi Simpan tidak diam-diam berhasil tanpa logo.
 */
trait ValidatesUploadOnSelect
{
    /**
     * @param  array<string, mixed>|null  $rules  null = pakai rules() komponen
     * @param  array<string, string>  $messages
     */
    protected function validateUploadNow(string $property, ?array $rules = null, array $messages = []): void
    {
        if (property_exists($this, 'successMessage')) {
            $this->successMessage = null;
        }

        if ($rules === null) {
            $this->validateOnly($property);
        } else {
            $this->validate($rules, $messages);
        }
    }
}
