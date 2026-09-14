{{--
    Approve/un-approve action bar for the Detail screens (2026-09-14).

    Rendered INSIDE a Livewire Detail component that uses the
    HandlesRecordVerification trait, so the wire:click targets below bind to
    that component. Each button is rendered only when the logged-in user's
    role may write that level (Supervisor → Checked, Mill Management →
    Acknowledged, Admin → both); a user with no verification rights sees
    nothing here at all.

    Kept as one shared component rather than copied into all 18 detail
    views so the wording, states, and role gating stay identical across
    stations.
--}}
@props(['canCheck', 'canAcknowledge', 'isChecked', 'isAcknowledged', 'message' => null])

@if ($canCheck || $canAcknowledge)
    <div class="rv-actions" data-testid="verification-actions">
        @if ($canCheck)
            <button
                type="button"
                wire:click="toggleChecked"
                class="rv-actions__button {{ $isChecked ? 'rv-actions__button--undo' : 'rv-actions__button--approve' }}"
                data-testid="toggle-checked-button"
            >
                {{ $isChecked ? 'Batalkan tanda diperiksa' : 'Tandai sudah diperiksa (Checked)' }}
            </button>
        @endif

        @if ($canAcknowledge)
            <button
                type="button"
                wire:click="toggleAcknowledged"
                class="rv-actions__button {{ $isAcknowledged ? 'rv-actions__button--undo' : 'rv-actions__button--approve' }}"
                data-testid="toggle-acknowledged-button"
            >
                {{ $isAcknowledged ? 'Batalkan tanda dikonfirmasi' : 'Tandai sudah dikonfirmasi (Acknowledged)' }}
            </button>
        @endif
    </div>

    @if ($message)
        <p class="rv-actions__message" data-testid="verification-message">{{ $message }}</p>
    @endif

    <style>
        .rv-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 16px 0 4px;
        }

        .rv-actions__button {
            padding: 9px 16px;
            min-height: 44px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .rv-actions__button--approve {
            background: #249360;
            border-color: #249360;
            color: #fff;
        }

        .rv-actions__button--approve:hover {
            background: #1d7a4e;
            border-color: #1d7a4e;
        }

        .rv-actions__button--undo {
            background: #fff;
            border-color: #d1d5db;
            color: #1f2937;
        }

        .rv-actions__button--undo:hover {
            background: #f3f4f6;
        }

        .rv-actions__message {
            margin: 0 0 8px;
            font-size: 13px;
            color: #249360;
        }

        @media (max-width: 767px) {
            .rv-actions__button {
                flex: 1 1 100%;
            }
        }
    </style>
@endif
