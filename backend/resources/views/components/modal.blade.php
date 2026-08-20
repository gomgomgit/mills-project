{{--
    Reusable scrollable modal shell — fixes a recurring bug where a modal's
    body content (a long form, a repeater grid, etc.) could not be scrolled:
    `.kc-modal__body { flex: 1; overflow-y: auto }` only works when EVERY
    ancestor up to the height-constrained box (`max-height: 90vh` here) is
    also `display: flex; flex-direction: column`, with `min-height: 0` on
    each flex item that needs to shrink below its content size. A plain
    `<form>` (or any other wrapper) breaking that chain — as happened in
    Kelola Machinery's "Tambah/Edit Machinery" modal — silently disables
    the scroll: the body just grows past `max-height` instead.

    This component owns the whole chain (backdrop → box → form → body →
    actions) so that break can't happen again, and ships its own scoped
    CSS (`kcm-modal-*` — distinct prefix from any page's own `.kc-modal-*`
    styles) so a page adopting it doesn't need to duplicate/get the CSS
    right itself.

    Usage — drop-in shell for any "Tambah/Edit X" or confirmation modal.
    Give it: title="..." wide submit="save" backdrop-key="...". Put any
    error-alert markup in the "error" named slot, the form fields (as
    long/tall as needed — it scrolls) as the default slot content, and the
    footer buttons in the "actions" named slot. See
    resources/views/livewire/master-data/kelola-machinery.blade.php for a
    full worked example.

    Props:
      title        (string, required) — modal heading text
      wide         (bool, default false) — max-width: 920px instead of 420px
      submit       (string|null) — Livewire method name for `wire:submit`;
                   omit for a modal with no form (e.g. a delete confirmation
                   using button wire:click instead)
      backdropKey  (string|null) — passed through as `wire:key` on the
                   backdrop, same purpose as any other Livewire wire:key
--}}
@props([
    'title',
    'wide' => false,
    'submit' => null,
    'backdropKey' => null,
])

<div class="kcm-modal-backdrop" @if ($backdropKey) wire:key="{{ $backdropKey }}" @endif>
    <div class="kcm-modal @if ($wide) kcm-modal--wide @endif" role="dialog" aria-modal="true">
        <h3 class="kcm-modal__title">{{ $title }}</h3>

        {{ $error ?? '' }}

        <form @if ($submit) wire:submit="{{ $submit }}" @endif novalidate class="kcm-modal__form">
            <div class="kcm-modal__body">
                {{ $slot }}
            </div>
            <div class="kcm-modal__actions">
                {{ $actions }}
            </div>
        </form>
    </div>
</div>

<style>
    .kcm-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        z-index: 50;
    }

    .kcm-modal {
        width: 100%;
        max-width: 420px;
        max-height: 90vh;
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
    }

    .kcm-modal--wide {
        max-width: 920px;
    }

    .kcm-modal__title {
        margin: 0 0 16px;
        font-size: 17px;
        font-weight: 700;
        flex-shrink: 0;
    }

    /* The scroll fix: form (and every ancestor above it) stays in the flex
       column chain, and both form and body get min-height: 0 so they can
       actually shrink below their content's natural height instead of
       forcing the modal box past max-height. */
    .kcm-modal__form {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }

    .kcm-modal__body {
        overflow-y: auto;
        padding-right: 4px;
        flex: 1;
        min-height: 0;
    }

    .kcm-modal__actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 20px;
        flex-shrink: 0;
    }
</style>
