<div
    class="settings-card"
    wire:loading.class="settings-card--busy"
    wire:target="save"
>
    <h2 class="settings-card__title">Ganti Password</h2>
    <p class="settings-card__subtitle">Perbarui password akun Anda</p>

    @if ($successMessage)
        <div
            class="settings-toast settings-toast--success"
            role="status"
            wire:key="toast-success-{{ $successMessage }}-{{ now()->timestamp }}"
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition
        >
            {{ $successMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div
            class="settings-toast settings-toast--error"
            role="alert"
            wire:key="toast-error-{{ $errorMessage }}-{{ now()->timestamp }}"
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition
        >
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="save" novalidate>
        <div class="form-field">
            <label for="old_password" class="form-field__label">
                Password Lama <span class="form-field__required">*</span>
            </label>
            <input
                type="password"
                id="old_password"
                wire:model="old_password"
                class="form-field__input @error('old_password') form-field__input--error @enderror"
                autocomplete="current-password"
                autofocus
            >
            @error('old_password')
                <p class="form-field__error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field">
            <label for="new_password" class="form-field__label">
                Password Baru <span class="form-field__required">*</span>
            </label>
            <input
                type="password"
                id="new_password"
                wire:model="new_password"
                class="form-field__input @error('new_password') form-field__input--error @enderror"
                autocomplete="new-password"
            >
            @error('new_password')
                <p class="form-field__error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field">
            <label for="new_password_confirmation" class="form-field__label">
                Konfirmasi Password Baru <span class="form-field__required">*</span>
            </label>
            <input
                type="password"
                id="new_password_confirmation"
                wire:model="new_password_confirmation"
                class="form-field__input @error('new_password_confirmation') form-field__input--error @enderror"
                autocomplete="new-password"
            >
            @error('new_password_confirmation')
                <p class="form-field__error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="settings-button" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Simpan</span>
            <span wire:loading wire:target="save">Menyimpan&hellip;</span>
        </button>
    </form>
</div>
