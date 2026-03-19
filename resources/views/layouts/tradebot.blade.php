<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Tradebot') }} – {{ $title ?? 'Dashboard' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-animated min-h-screen antialiased">

<div class="flex h-screen overflow-hidden">

    {{-- Sidebar --}}
    <aside class="w-64 shrink-0 flex flex-col glass-card rounded-none border-0 border-r border-white/[0.08]">
        {{-- Logo --}}
        <div class="p-6 border-b border-white/[0.08]">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-neon-green/20 border border-neon-green/30 flex items-center justify-center">
                    <svg class="w-4 h-4 text-neon-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <div>
                    <div class="font-bold text-white text-sm">Tradebot</div>
                    <div class="text-xs text-white/30">
                        @if(\App\Services\TradingSettings::isLive())
                            <span class="text-yellow-400">● LIVE</span>
                        @else
                            <span class="text-neon-blue">● PAPER</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 p-4 space-y-1">
            <a href="{{ route('tradebot.dashboard') }}"
               class="sidebar-link {{ request()->routeIs('tradebot.dashboard') ? 'active' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('tradebot.sources') }}"
               class="sidebar-link {{ request()->routeIs('tradebot.sources') ? 'active' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                </svg>
                Sources
            </a>
            <a href="{{ route('tradebot.trades') }}"
               class="sidebar-link {{ request()->routeIs('tradebot.trades') ? 'active' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Trade History
            </a>
            <a href="{{ route('tradebot.analysis') }}"
               class="sidebar-link {{ request()->routeIs('tradebot.analysis') ? 'active' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                Analysis
            </a>
            <a href="{{ route('tradebot.logs') }}"
               class="sidebar-link {{ request()->routeIs('tradebot.logs') ? 'active' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Logs
            </a>
        </nav>

        {{-- Footer --}}
        <div class="p-4 border-t border-white/[0.08]">
            <a href="{{ route('profile.show') }}" class="sidebar-link">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
        </div>
    </aside>

    {{-- Main content --}}
    <main class="flex-1 overflow-y-auto">
        {{-- Top bar --}}
        <header class="sticky top-0 z-10 px-8 py-4 border-b border-white/[0.06] bg-black/20 flex items-center justify-between header-blur">
            <div>
                <h1 class="text-lg font-semibold text-white">{{ $title ?? 'Dashboard' }}</h1>
                <p class="text-xs text-white/30 mt-0.5">{{ now()->format('D, d M Y · H:i') }} UTC</p>
            </div>
            <div class="flex items-center gap-3">
                {{-- Trading mode toggle --}}
                @livewire('trading-mode-toggle')

                {{-- User --}}
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-neon-green/20 border border-neon-green/30 flex items-center justify-center text-xs font-bold text-neon-green">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <div class="p-8">
            {{ $slot }}
        </div>
    </main>

</div>

@livewireScripts
</body>
</html>
