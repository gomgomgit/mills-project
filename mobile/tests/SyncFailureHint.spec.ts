/**
 * Audit 2026-10-04 #9 — sync-failure UX: a rejected record must not look
 * like a plain "Tersimpan", and the sync result dialog must say WHICH
 * station a failed id belongs to.
 */
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SyncFailureHint from '@/components/SyncFailureHint.vue'
import SyncResultDialog from '@/components/SyncResultDialog.vue'

describe('SyncFailureHint', () => {
  it('shows "Gagal sinkron: <alasan>" for a saved record with a stored reason', () => {
    const w = mount(SyncFailureHint, { props: { record: { status: 'saved', sync_error: 'Periode sudah ditutup.' } } })
    expect(w.get('[data-testid="sync-failure-hint"]').text()).toBe('Gagal sinkron: Periode sudah ditutup.')
  })

  it.each([
    [{ status: 'saved', sync_error: null }],
    [{ status: 'saved', sync_error: '   ' }],
    [{ status: 'synced', sync_error: 'stale' }],
    [{ status: 'draft_paused', sync_error: 'x' }],
    [null],
  ])('stays hidden for %j', (record) => {
    const w = mount(SyncFailureHint, { props: { record } })
    expect(w.find('[data-testid="sync-failure-hint"]').exists()).toBe(false)
  })
})

describe('SyncResultDialog', () => {
  it('lists each failed item with its station name', () => {
    const w = mount(SyncResultDialog, {
      props: {
        open: true,
        errorMessage: null,
        summary: {
          byStation: {},
          items: [
            { id: 'a', label: 'TH-01', ok: false, reason: 'Ditolak', stationName: 'Threshing' },
            { id: 'b', label: 'GR-01', ok: true, stationName: 'Grading' },
          ],
          syncedCount: 1,
          failedCount: 1,
        },
      },
    })

    const items = w.findAll('[data-testid="sync-dialog-failed-item"]')
    expect(items).toHaveLength(1)
    expect(items[0].text()).toContain('Threshing')
    expect(items[0].text()).toContain('TH-01')
    expect(items[0].text()).toContain('Ditolak')
  })
})
