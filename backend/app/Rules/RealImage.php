<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\File;

/**
 * RealImage — file yang diunggah HARUS benar-benar gambar JPG/PNG, dibuktikan
 * dari ISI file (getimagesizefromstring), bukan dari nama/ekstensinya.
 *
 * Kenapa aturan bawaan 'image' / 'mimes:jpg,jpeg,png' tidak cukup untuk
 * upload Livewire: TemporaryUploadedFile::getMimeType() bertanya ke
 * Flysystem, yang untuk isi "text/plain" (dianggap tidak meyakinkan) jatuh
 * kembali ke tebakan dari EKSTENSI nama file — jadi file teks bernama
 * "logo.png" lolos sebagai image/png dan tersimpan sebagai logo (temuan
 * audit 2026-10-04 di Corporate & Mills Setting; pola yang sama di Company,
 * Business Unit, gambar Mesin, home image).
 *
 * Dipakai di komponen Livewire (saat file dipilih DAN saat simpan) dan di
 * service (jalur API) supaya keduanya menolak hal yang sama.
 */
class RealImage implements ValidationRule
{
    /**
     * @param  list<int>  $allowedTypes  konstanta IMAGETYPE_*
     */
    public function __construct(
        protected string $label = 'File',
        protected array $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG],
        protected string $formats = 'JPG atau PNG',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! $value instanceof File) {
            $fail("{$this->label} harus berupa file gambar {$this->formats}.");

            return;
        }

        try {
            $contents = method_exists($value, 'get') ? $value->get() : file_get_contents($value->getRealPath());
        } catch (\Throwable) {
            $contents = false;
        }

        $info = is_string($contents) && $contents !== '' ? @getimagesizefromstring($contents) : false;

        if ($info === false || ! in_array($info[2] ?? null, $this->allowedTypes, true)) {
            $fail("{$this->label} harus berupa file gambar {$this->formats} yang valid.");
        }
    }
}
