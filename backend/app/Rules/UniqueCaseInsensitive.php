<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * UniqueCaseInsensitive — pengganti Rule::unique() untuk kode/nama master
 * data: "corp-a" dan "CORP-A" dianggap SAMA (temuan audit 2026-10-04 #11).
 * Rule::unique() membandingkan apa adanya — di PostgreSQL (prod) itu peka
 * huruf, di SQLite (test) juga — jadi duplikat beda huruf lolos.
 *
 * lower(kolom) = lower(nilai) di-bind sebagai parameter: aman di SQLite
 * maupun PostgreSQL (lihat memori "Jebakan SQLite vs PostgreSQL" — bukan
 * ILIKE). API fluent sengaja meniru Rule::unique(): ->ignore($id) dan
 * ->where(fn ($q) => ...), supaya penggantiannya satu-banding-satu.
 *
 * Pesan gagal mengambil pesan kustom '<atribut>.unique' yang sudah
 * didaftarkan pemanggil (messages() komponen / array pesan service), jadi
 * teks Indonesia yang sudah ada tetap dipakai tanpa ditulis ulang.
 */
class UniqueCaseInsensitive implements ValidationRule, ValidatorAwareRule
{
    protected ?string $ignoreId = null;

    protected string $ignoreColumn = 'id';

    /** @var list<Closure> */
    protected array $wheres = [];

    protected ?Validator $validator = null;

    public function __construct(protected string $table, protected string $column) {}

    public static function on(string $table, string $column): self
    {
        return new self($table, $column);
    }

    public function ignore(mixed $id, string $column = 'id'): self
    {
        $this->ignoreId = $id === null ? null : (string) $id;
        $this->ignoreColumn = $column;

        return $this;
    }

    public function where(Closure $callback): self
    {
        $this->wheres[] = $callback;

        return $this;
    }

    public function setValidator(\Illuminate\Contracts\Validation\Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || ! is_scalar($value)) {
            return;
        }

        $query = DB::table($this->table)
            ->whereRaw('lower('.$this->column.') = ?', [mb_strtolower(trim((string) $value))]);

        if ($this->ignoreId !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }

        foreach ($this->wheres as $callback) {
            $callback($query);
        }

        if ($query->exists()) {
            $custom = $this->validator?->customMessages[$attribute.'.unique'] ?? null;
            $fail($custom ?? 'Nilai ini sudah digunakan (tidak membedakan huruf besar/kecil).');
        }
    }
}
