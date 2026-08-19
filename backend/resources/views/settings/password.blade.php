<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ganti Password — Mill Smart Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        /* Design tokens — uiux-spec (screen_type "settings"): brand #249360,
           destructive #DC2626, input radius 6px, button radius 8px, Inter.
           Inlined here (same approach as resources/views/auth/login.blade.php)
           since this backend scaffold has no frontend build pipeline yet —
           see implementation_notes. This file also doubles as the shared
           "shell" (sidebar + header) layout, since no shell layout existed
           yet from screen-001/002 (both pre-auth, so neither needed one) —
           see implementation_notes. */
        :root {
            --color-brand: #249360;
            --color-brand-hover: #1d7a4e;
            --color-destructive: #DC2626;
            --color-success: #16A34A;
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
            background: #f3f4f6;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--color-text);
        }

        .shell-sidebar {
            width: 220px;
            flex-shrink: 0;
            background: #111827;
            color: #f9fafb;
            padding: 24px 16px;
        }

        .shell-sidebar__brand {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 24px;
        }

        .shell-sidebar__nav {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .shell-sidebar__nav a {
            display: block;
            padding: 8px 10px;
            border-radius: var(--radius-input);
            color: #d1d5db;
            text-decoration: none;
            font-size: 14px;
        }

        .shell-sidebar__nav a.active,
        .shell-sidebar__nav a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .shell-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .shell-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            background: #fff;
            border-bottom: 1px solid var(--color-border);
        }

        .shell-header__title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .shell-header__user {
            font-size: 14px;
            color: var(--color-text-muted);
        }

        .shell-body {
            flex: 1;
            padding: 32px 24px;
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
</head>
<body>
    <aside class="shell-sidebar">
        <p class="shell-sidebar__brand">Mill Smart Log</p>
        <ul class="shell-sidebar__nav">
            <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('settings.password') }}" class="active">Ganti Password</a></li>
        </ul>
    </aside>

    <div class="shell-main">
        <header class="shell-header">
            <h1 class="shell-header__title">Pengaturan Akun</h1>
            @auth
                <span class="shell-header__user">{{ auth()->user()->name }}</span>
            @endauth
        </header>

        <main class="shell-body">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
