<?php

/**
 * WebFormNoDisabledFieldTest — regresi temuan audit 2026-10-04.
 *
 * Konvensi web: form web TIDAK boleh punya input/select/textarea yang
 * `disabled` atau `readonly`. Nilai terhitung (Total Cages, Cages Remain,
 * Net Weight, UOM, Percentage) ditampilkan sebagai teks (kotak nilai
 * ber-data-testid), bukan sebagai <input disabled>.
 *
 * Tiap test merender form dengan satu baris detail yang sudah terisi
 * sehingga nilai terhitungnya muncul, lalu memeriksa HTML hasil render:
 *   1. tidak ada kontrol form ber-atribut disabled/readonly, dan
 *   2. nilai terhitung ada di elemen teks dengan data-testid yang sama,
 *      dan ikut berubah saat input sumbernya diubah (re-render Livewire).
 */

use App\Enums\Uom;
use App\Enums\UserRole;
use App\Livewire\Data\FormCagesTrack;
use App\Livewire\Data\FormCpoDispatch;
use App\Livewire\Data\FormGrading;
use App\Livewire\Data\FormKernelDispatch;
use App\Livewire\Data\FormSolidWasteDisposal;
use App\Models\BusinessUnit;
use App\Models\GradingParameter;
use App\Models\Machinery;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

/**
 * Kembalikan semua tag <input>/<select>/<textarea> di $html yang membawa
 * atribut disabled atau readonly (boolean atau ber-nilai).
 *
 * @return list<string>
 */
function disabledOrReadonlyFieldControls(string $html): array
{
    preg_match_all('/<(input|select|textarea)\b[^>]*>/i', $html, $matches);

    return array_values(array_filter(
        $matches[0],
        fn (string $tag) => preg_match('/\s(disabled|readonly)(\s|=|>|\/)/i', $tag) === 1,
    ));
}

/** Teks di dalam elemen ber-data-testid tertentu (null bila tidak ada / bukan elemen teks). */
function computedValueText(string $html, string $testId): ?string
{
    $pattern = '/<(span|div)\b[^>]*data-testid="'.preg_quote($testId, '/').'"[^>]*>(.*?)<\/\1>/s';

    return preg_match($pattern, $html, $m) === 1 ? trim(html_entity_decode(strip_tags($m[2]))) : null;
}

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
});

it('Form Cages Track: Total Cages & Cages Remain dirender sebagai teks, bukan input disabled', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->cagesTrack()->create();
    Machinery::factory()->count(10)->create(['station_id' => $station->id]);

    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);
    $component->set('form.production_line_id', $station->production_line_id);
    $component->call('addDetailRow');
    $component->call('toggleCage', 0, 1);
    $component->call('toggleCage', 0, 2);

    $html = $component->html();
    $jumlah = $component->get('jumlahCages');

    expect(disabledOrReadonlyFieldControls($html))->toBe([])
        ->and(computedValueText($html, 'detail-total-cages-0'))->toBe('2')
        ->and(computedValueText($html, 'detail-cages-remain-0'))->toBe((string) ($jumlah - 2));

    $component->call('toggleCage', 0, 3);
    $html = $component->html();

    expect(computedValueText($html, 'detail-total-cages-0'))->toBe('3')
        ->and(computedValueText($html, 'detail-cages-remain-0'))->toBe((string) ($jumlah - 3));
});

dataset('dispatch net weight forms', [
    'CPO Dispatch' => [FormCpoDispatch::class, 'cpoDispatch'],
    'Kernel Dispatch' => [FormKernelDispatch::class, 'kernelDispatch'],
    'Solid Waste Disposal' => [FormSolidWasteDisposal::class, 'solidWasteDisposal'],
]);

it('Net Weight dirender sebagai teks, bukan input disabled, dan ikut berubah', function (string $componentClass, string $stationState) {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->{$stationState}()->create();

    $component = Livewire::actingAs($this->supervisor)->test($componentClass);
    $component->set('form.production_line_id', $station->production_line_id);
    $component->call('addDetailRow');

    $html = $component->html();
    expect(disabledOrReadonlyFieldControls($html))->toBe([])
        ->and(computedValueText($html, 'detail-net-weight-0'))->toBe('-');

    $component->set('detailRows.0.gross_weight_mt', 10);
    $component->set('detailRows.0.tare_weight_mt', 3);
    $html = $component->html();

    expect(disabledOrReadonlyFieldControls($html))->toBe([])
        ->and(computedValueText($html, 'detail-net-weight-0'))->toBe('7');
})->with('dispatch net weight forms');

it('Form Grading: UOM & Percentage dirender sebagai teks dan select Production Line tidak disabled', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->grading()->create();
    $parameter = GradingParameter::factory()->create(['uom' => Uom::Kg]);

    // Mill Management memilih Business Unit sendiri — sebelum dipilih,
    // daftar Production Line masih kosong (dulu select-nya dirender disabled).
    $component = Livewire::actingAs($this->millManagement)->test(FormGrading::class);

    $html = $component->html();
    expect($html)->toContain('data-testid="production-line-select"')
        ->and(disabledOrReadonlyFieldControls($html))->toBe([]);

    $component->set('form.netto', 1000);
    $component->call('addDetailRow');
    $component->set('detailRows.0.grading_parameter_id', $parameter->id);
    $component->set('detailRows.0.quantity', 100);

    $html = $component->html();
    $uom = $component->instance()->rowUom(0);
    $percentage = $component->instance()->rowPercentage(0);

    expect(disabledOrReadonlyFieldControls($html))->toBe([])
        ->and($uom)->not->toBeNull()
        ->and(computedValueText($html, 'detail-uom-0'))->toBe((string) $uom)
        ->and(computedValueText($html, 'detail-percentage-0'))->toBe((string) $percentage);
});

it('tidak ada view form data web yang memakai disabled/readonly pada kontrol form', function () {
    $offenders = [];

    foreach (glob(resource_path('views/livewire/data/form-*.blade.php')) as $path) {
        $source = file_get_contents($path);
        // Samarkan ekspresi Blade agar `->`/`=>` di dalamnya tidak memotong tag.
        $masked = preg_replace_callback('/\{\{.*?\}\}|\{!!.*?!!\}/s', fn ($m) => str_repeat('_', strlen($m[0])), $source);
        $masked = str_replace(['->', '=>'], '__', $masked);

        preg_match_all('/<(input|select|textarea)\b[^>]*>/i', $masked, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$tag, $offset]) {
            if (preg_match('/(?<![\w:-])(disabled|readonly)\b|@disabled|@readonly/i', $tag) === 1) {
                $offenders[] = basename($path).':'.(substr_count($source, "\n", 0, $offset) + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
