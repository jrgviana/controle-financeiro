<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#3247a8">
    <meta name="description" content="Contas, cartões, pagamentos e planejamento financeiro em um só lugar.">
    <title>@yield('title', 'Controle Financeiro') — Controle Financeiro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="finance-app min-h-screen font-sans text-slate-900 antialiased">
    @php
        $links = [
            ['route' => 'dashboard', 'label' => 'Início', 'symbol' => '◫', 'active' => 'dashboard'],
            ['route' => 'contas.index', 'label' => 'Contas', 'symbol' => '▤', 'active' => 'contas.*'],
            ['route' => 'contas-fixas.index', 'label' => 'Contas mensais', 'symbol' => '▦', 'active' => 'contas-fixas.*'],
            ['route' => 'pagamentos.index', 'label' => 'Pagamentos', 'symbol' => '✓', 'active' => 'pagamentos.*'],
            ['route' => 'gastos.index', 'label' => 'Gastos', 'symbol' => '↗', 'active' => 'gastos.*'],
            ['route' => 'recebimentos.index', 'label' => 'Recebimentos', 'symbol' => '↙', 'active' => 'recebimentos.*'],
            ['route' => 'cartoes.index', 'label' => 'Cartões', 'symbol' => '▣', 'active' => 'cartoes.*'],
        ];
    @endphp
    <a href="#conteudo" class="finance-skip-link">Ir para o conteúdo</a>
    <header class="finance-header sticky top-0 z-40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex min-h-[76px] items-center justify-between gap-3 py-3">
                <a href="{{ route('dashboard') }}" class="group flex min-w-0 items-center gap-3" aria-label="Controle Financeiro — início">
                    <div class="finance-brand-mark flex size-11 shrink-0 items-center justify-center rounded-2xl font-bold text-white" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M12 3v18M16.5 6.5H9.8a3.2 3.2 0 0 0 0 6.4h4.4a3.2 3.2 0 0 1 0 6.4H7.5"/>
                        </svg>
                    </div>
                    <span class="min-w-0">
                        <span class="block truncate text-base font-bold tracking-tight text-slate-900 sm:text-lg">Controle Financeiro</span>
                        <span class="block text-xs text-slate-500">Sua vida financeira, organizada</span>
                    </span>
                </a>
                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="{{ route('pagamentos.index') }}"
                        class="finance-header-cta hidden items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white sm:inline-flex">
                        <span aria-hidden="true">✓</span> Pagar contas
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="finance-exit rounded-xl px-3 py-2.5 text-sm font-semibold sm:px-4">
                            Sair
                        </button>
                    </form>
                </div>
            </div>
            <nav class="finance-desktop-nav hidden flex-wrap items-center gap-1 border-t border-slate-100 py-2 lg:flex" aria-label="Menu principal">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}"
                        @class(['finance-nav-link', 'is-active' => request()->routeIs($link['active'])])
                        @if (request()->routeIs($link['active'])) aria-current="page" @endif>
                        <span class="finance-nav-symbol" aria-hidden="true">{{ $link['symbol'] }}</span>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
            <details class="finance-mobile-menu relative border-t border-slate-100 py-2 lg:hidden">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between rounded-xl px-3 py-2 font-semibold text-slate-700">
                    <span class="flex items-center gap-2">
                        <span aria-hidden="true">☰</span>
                        Menu
                    </span>
                    <span class="finance-menu-chevron" aria-hidden="true">⌄</span>
                </summary>
                <nav class="grid grid-cols-2 gap-2 p-2 sm:grid-cols-3" aria-label="Menu do aplicativo">
                    @foreach ($links as $link)
                        <a href="{{ route($link['route']) }}"
                            @class(['finance-nav-link', 'is-active' => request()->routeIs($link['active'])])
                            @if (request()->routeIs($link['active'])) aria-current="page" @endif>
                            <span class="finance-nav-symbol" aria-hidden="true">{{ $link['symbol'] }}</span>
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            </details>
        </div>
    </header>

    <main id="conteudo" class="finance-main mx-auto w-full min-w-0 max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
        @isset($slot)
            {{ $slot }}
        @endisset
        @yield('content')
    </main>
    <footer class="mx-auto max-w-7xl px-4 pb-8 pt-4 text-center text-xs text-slate-400 sm:px-6 lg:px-8">
        Controle Financeiro · Valores atualizados a partir dos lançamentos confirmados
    </footer>
    @livewireScripts
</body>
</html>
