/**
 * Label pilihan enum untuk tampilan baca (audit 2026-10-05) — cermin
 * backend App\Support\Display::OPTION_LABELS / Display::option(), yang
 * dipakai Detail web dan ekspor Data Browser. Detail Data Preview mobile
 * memakai helper ini agar menampilkan label yang sama ("Ya", "Run"), bukan
 * nilai mentah tersimpan ("y", "run"). tests/optionLabel.spec.ts menjaga
 * peta ini tetap identik dengan sisi backend.
 */
export const OPTION_LABELS: Record<string, string> = {
  // Effluent Plant: biogas_flare_status
  on: 'On',
  off: 'Off',
  fault: 'Fault',
  // Effluent Plant: dosing_pump_1_status / sludge_dewatering_status;
  // Engine Room: diesel_gen_*_status (run/standby/off)
  run: 'Run',
  stop: 'Stop',
  standby: 'Standby',
  // Boiler Room: blowdown_executed / sootblowing_executed
  y: 'Ya',
  n: 'Tidak',
}

/** Kosong -> "-", nilai dikenal -> labelnya, tak dikenal -> apa adanya. */
export function optionLabel(value: string | null | undefined): string {
  const raw = value ?? ''
  if (raw === '') return '-'
  return OPTION_LABELS[raw] ?? raw
}
