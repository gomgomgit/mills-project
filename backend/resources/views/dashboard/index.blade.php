<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Mills Smart Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        /* Placeholder shell for screen-025--dashboard-web — see
           App\Livewire\Dashboard\DashboardHome's docblock. Styles copied
           verbatim from resources/views/master-data/corporates.blade.php
           (this codebase's shared shell pattern; no extracted layout
           component exists yet). Replace wholesale once screen-025's real
           implementation lands. */
        :root {
            --color-brand: #249360;
            --color-brand-hover: #1d7a4e;
            --color-text: #1f2937;
            --color-text-muted: #6b7280;
            --color-border: #d1d5db;
            --radius-input: 6px;
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
        }
    </style>
</head>
<body>
    <aside class="shell-sidebar">
        <p class="shell-sidebar__brand">Mills Smart Log</p>
        <ul class="shell-sidebar__nav">
            <li><a href="{{ url('/dashboard') }}" class="active">Dashboard</a></li>
            <li><a href="{{ route('master-data.corporates') }}">Kelola Corporate</a></li>
            <li><a href="{{ route('master-data.companies') }}">Kelola Company</a></li>
            <li><a href="{{ route('master-data.business-units') }}">Kelola Business Unit</a></li>
            <li><a href="{{ route('data.weighbridge') }}">Data Browser Weighbridge</a></li>
            <li><a href="{{ route('settings.password') }}">Ganti Password</a></li>
        </ul>
    </aside>

    <div class="shell-main">
        <header class="shell-header">
            <h1 class="shell-header__title">Dashboard</h1>
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
