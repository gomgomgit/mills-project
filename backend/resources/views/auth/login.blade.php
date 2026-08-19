<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Mills Smart Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        /* Design tokens — uiux-spec (screen_type "auth"): brand #249360,
           destructive #DC2626, input radius 6px, button radius 8px, Inter.
           Inlined here since this backend scaffold has no frontend build
           pipeline (no package.json / vite config) yet — see
           implementation_notes. */
        :root {
            --color-brand: #249360;
            --color-brand-hover: #1d7a4e;
            --color-destructive: #DC2626;
            --color-text: #1f2937;
            --color-text-muted: #6b7280;
            --color-border: #d1d5db;
            --radius-input: 6px;
            --radius-button: 8px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--color-text);
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            margin: 24px;
            padding: 32px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }

        .login-card--busy {
            opacity: 0.85;
        }

        .login-card__title {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: 700;
        }

        .login-card__subtitle {
            margin: 0 0 24px;
            font-size: 14px;
            color: var(--color-text-muted);
        }

        .login-alert {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: var(--radius-input);
            background: #fef2f2;
            border: 1px solid var(--color-destructive);
            color: var(--color-destructive);
            font-size: 14px;
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

        .form-field__input-group {
            position: relative;
        }

        .form-field__input-group .form-field__input {
            padding-right: 84px;
        }

        .form-field__toggle-password {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 500;
            font-family: inherit;
            color: var(--color-brand);
            background: transparent;
            border: none;
            cursor: pointer;
        }

        .form-field__toggle-password:hover {
            text-decoration: underline;
        }

        .form-field__error {
            margin: 6px 0 0;
            font-size: 13px;
            color: var(--color-destructive);
        }

        .login-button {
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

        .login-button:hover:not(:disabled) {
            background: var(--color-brand-hover);
        }

        .login-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    {{ $slot }}

    @livewireScripts
</body>
</html>
