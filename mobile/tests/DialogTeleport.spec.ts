/**
 * DialogTeleport.spec.ts — ConfirmDialog.vue & SyncResultDialog.vue harus
 * merender overlay-nya langsung di <body> (audit 2026-10-04: backdrop
 * dialog hasil sinkronisasi tidak menutup header). Overlay `position:
 * fixed` yang tetap tinggal di dalam <main> layar ikut terikat pada konteks
 * tumpukan/containing block induknya; di <body> ia selalu menutup seluruh
 * viewport, sama untuk semua dialog.
 *
 * Stub Teleport global (tests/setup/teleportStub.ts) dimatikan di sini.
 */
import { afterEach, describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import SyncResultDialog from '@/components/SyncResultDialog.vue'

const mounted: VueWrapper[] = []

function host(): HTMLElement {
  const el = document.createElement('main')
  el.className = 'screen-host'
  document.body.appendChild(el)
  return el
}

afterEach(() => {
  mounted.splice(0).forEach((w) => w.unmount())
  document.body.innerHTML = ''
})

describe('overlay dialog di-teleport ke <body>', () => {
  it('ConfirmDialog: overlay adalah anak langsung <body>, bukan di dalam layar', async () => {
    const wrapper = mount(ConfirmDialog, {
      props: { open: true, message: 'Hapus?' },
      attachTo: host(),
      global: { stubs: { teleport: false } },
    })
    mounted.push(wrapper)

    const overlay = document.querySelector('[data-testid="confirm-dialog-overlay"]')
    expect(overlay).not.toBeNull()
    expect(overlay?.parentElement).toBe(document.body)
    expect(document.querySelector('.screen-host [data-testid="confirm-dialog-overlay"]')).toBeNull()

    // Event tetap bekerja dari node yang sudah dipindah.
    ;(document.querySelector('.confirm-dialog-button--confirm') as HTMLButtonElement).click()
    expect(wrapper.emitted('confirm')).toHaveLength(1)
  })

  it('SyncResultDialog: overlay adalah anak langsung <body>, bukan di dalam layar', async () => {
    const wrapper = mount(SyncResultDialog, {
      props: { open: true, summary: null, errorMessage: 'Gagal' },
      attachTo: host(),
      global: { stubs: { teleport: false } },
    })
    mounted.push(wrapper)

    const overlay = document.querySelector('[data-testid="sync-dialog-overlay"]')
    expect(overlay).not.toBeNull()
    expect(overlay?.parentElement).toBe(document.body)
    expect(document.querySelector('.screen-host [data-testid="sync-dialog-overlay"]')).toBeNull()

    ;(document.querySelector('[data-testid="sync-dialog-close"]') as HTMLButtonElement).click()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('tidak merender apa pun di <body> saat tertutup', () => {
    mounted.push(mount(ConfirmDialog, { props: { open: false, message: 'x' }, attachTo: host(), global: { stubs: { teleport: false } } }))
    mounted.push(mount(SyncResultDialog, { props: { open: false, summary: null, errorMessage: null }, attachTo: host(), global: { stubs: { teleport: false } } }))

    expect(document.querySelector('.confirm-dialog-overlay, .sync-dialog-overlay')).toBeNull()
  })
})
