<div class="login-card" wire:loading.class="login-card--busy" wire:target="login">
    <h1 class="login-card__title">Mills Smart Log</h1>
    <p class="login-card__subtitle">Masuk untuk melanjutkan</p>

    @if ($errorMessage)
        <div class="login-alert" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="login" novalidate>
        <div class="form-field">
            <label for="username" class="form-field__label">
                Username <span class="form-field__required">*</span>
            </label>
            <input
                type="text"
                id="username"
                wire:model="username"
                class="form-field__input @error('username') form-field__input--error @enderror"
                autocomplete="username"
                autofocus
            >
            @error('username')
                <p class="form-field__error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field" x-data="{ showPassword: false }">
            <label for="password" class="form-field__label">
                Password <span class="form-field__required">*</span>
            </label>
            <div class="form-field__input-group">
                <input
                    :type="showPassword ? 'text' : 'password'"
                    id="password"
                    wire:model="password"
                    class="form-field__input @error('password') form-field__input--error @enderror"
                    autocomplete="current-password"
                >
                <button
                    type="button"
                    class="form-field__toggle-password"
                    @click="showPassword = !showPassword"
                    :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                    tabindex="-1"
                >
                    <span x-text="showPassword ? 'Sembunyikan' : 'Tampilkan'"></span>
                </button>
            </div>
            @error('password')
                <p class="form-field__error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-field">
            <label for="business_unit_id" class="form-field__label">
                Business Area <span class="form-field__required">*</span>
            </label>
            <x-searchable-select
                id="business_unit_id"
                wire:model="business_unit_id"
                :options="collect($businessUnits)->map(fn ($businessUnit) => ['value' => $businessUnit->id, 'label' => $businessUnit->name])->all()"
                placeholder="Pilih business area…"
                :class="'form-field__input'.($errors->has('business_unit_id') ? ' form-field__input--error' : '')"
            />
            @error('business_unit_id')
                <p class="form-field__error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="login-button" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memproses&hellip;</span>
        </button>
    </form>
</div>
