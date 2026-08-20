@props(['title', 'heading' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Mills Smart Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        /* Shared page shell (sidebar + header) — single source of truth for
           every screen in resources/views/. Design tokens per uiux-spec:
           brand #249360, Inter. Inlined here (no frontend build pipeline
           in this backend scaffold — see implementation_notes on the
           screens that migrated to this component) rather than an
           external stylesheet. Page-specific styles stay in each page's
           own view, passed via the `styles` slot below. */
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
    {{ $styles ?? '' }}
</head>
<body>
    <aside class="shell-sidebar">
        <p class="shell-sidebar__brand">Mills Smart Log</p>
        <ul class="shell-sidebar__nav">
            <li><a href="{{ route('dashboard') }}"{!! request()->routeIs('dashboard') ? ' class="active"' : '' !!}>Dashboard</a></li>
            <li><a href="{{ route('production-process-activity') }}"{!! request()->routeIs('production-process-activity') ? ' class="active"' : '' !!}>Production Process Activity</a></li>
            <li><a href="{{ route('reports.management') }}"{!! request()->routeIs('reports.management') ? ' class="active"' : '' !!}>Laporan Manajemen</a></li>
            <li><a href="{{ route('master-data.corporates') }}"{!! request()->routeIs('master-data.corporates') ? ' class="active"' : '' !!}>Kelola Corporate</a></li>
            <li><a href="{{ route('master-data.companies') }}"{!! request()->routeIs('master-data.companies') ? ' class="active"' : '' !!}>Kelola Company</a></li>
            <li><a href="{{ route('master-data.business-units') }}"{!! request()->routeIs('master-data.business-units') ? ' class="active"' : '' !!}>Kelola Business Unit</a></li>
            <li><a href="{{ route('master-data.production-lines') }}"{!! request()->routeIs('master-data.production-lines') ? ' class="active"' : '' !!}>Kelola Production Line</a></li>
            <li><a href="{{ route('master-data.stations') }}"{!! request()->routeIs('master-data.stations') ? ' class="active"' : '' !!}>Kelola Station</a></li>
            <li><a href="{{ route('master-data.machinery-groups') }}"{!! request()->routeIs('master-data.machinery-groups') ? ' class="active"' : '' !!}>Kelola Machinery Group</a></li>
            <li><a href="{{ route('master-data.machinery') }}"{!! request()->routeIs('master-data.machinery') ? ' class="active"' : '' !!}>Kelola Machinery</a></li>
            <li><a href="{{ route('mill-settings') }}"{!! request()->routeIs('mill-settings') ? ' class="active"' : '' !!}>Mills Setting</a></li>
            <li><a href="{{ route('users.index') }}"{!! request()->routeIs('users.index') ? ' class="active"' : '' !!}>Kelola User & Role</a></li>
            <li><a href="{{ route('settings.password') }}"{!! request()->routeIs('settings.password') ? ' class="active"' : '' !!}>Ganti Password</a></li>
        </ul>
    </aside>

    <div class="shell-main">
        <header class="shell-header">
            <h1 class="shell-header__title">{{ $heading ?? $title }}</h1>
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
