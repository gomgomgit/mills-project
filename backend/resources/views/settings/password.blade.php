<x-layouts.app title="Ganti Password" heading="Pengaturan Akun">
    <x-slot:styles>
        <style>
            :root {
                --color-destructive: #DC2626;
                --color-success: #16A34A;
                --radius-button: 8px;
            }

            .shell-body {
                display: flex;
                justify-content: center;
            }

            .settings-card {
                width: 100%;
                max-width: 440px;
                height: fit-content;
                padding: 32px;
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            }

            .settings-card--busy {
                opacity: 0.85;
            }

            .settings-card__title {
                margin: 0 0 4px;
                font-size: 20px;
                font-weight: 700;
            }

            .settings-card__subtitle {
                margin: 0 0 24px;
                font-size: 14px;
                color: var(--color-text-muted);
            }

            .settings-toast {
                margin-bottom: 16px;
                padding: 10px 12px;
                border-radius: var(--radius-input);
                font-size: 14px;
            }

            .settings-toast--success {
                background: #f0fdf4;
                border: 1px solid var(--color-success);
                color: var(--color-success);
            }

            .settings-toast--error {
                background: #fef2f2;
                border: 1px solid var(--color-destructive);
                color: var(--color-destructive);
            }

            .form-field {
                margin-bottom: 16px;
            }

            .form-field__label {
                display: block;
                margin-bottom: 6px;
                font-size: 14px;
                font-weight: 500;
            }

            .form-field__required {
                color: var(--color-destructive);
            }

            .form-field__input {
                width: 100%;
                padding: 10px 12px;
                font-size: 14px;
                font-family: inherit;
                color: var(--color-text);
                border: 1px solid var(--color-border);
                border-radius: var(--radius-input);
                background: #fff;
            }

            .form-field__input:focus {
                outline: none;
                border-color: var(--color-brand);
                box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
            }

            .form-field__input--error {
                border-color: var(--color-destructive);
            }

            .form-field__error {
                margin: 6px 0 0;
                font-size: 13px;
                color: var(--color-destructive);
            }

            .settings-button {
                width: 100%;
                padding: 11px 16px;
                font-size: 15px;
                font-weight: 600;
                font-family: inherit;
                color: #fff;
                background: var(--color-brand);
                border: none;
                border-radius: var(--radius-button);
                cursor: pointer;
            }

            .settings-button:hover:not(:disabled) {
                background: var(--color-brand-hover);
            }

            .settings-button:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
        </style>
    </x-slot:styles>

    {{ $slot }}
</x-layouts.app>
