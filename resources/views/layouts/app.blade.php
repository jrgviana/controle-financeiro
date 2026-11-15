<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>@yield('title', 'Controle Financeiro') — Controle Financeiro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-slate-900 font-bold text-white">$</div>
                <div>
                    <p class="text-base font-semibold tracking-tight">Controle Financeiro</p>
                    <p class="text-xs text-slate-500">Seu painel pessoal</p>
                </div>
            </a>
            <div class="flex flex-wrap items-center gap-3 sm:gap-5">
                <nav class="flex items-center gap-1 text-sm" aria-label="Menu principal">
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 font-medium transition hover:bg-slate-100 {{ request()->routeIs('dashboard') ? 'bg-slate-100 text-slate-950' : 'text-slate-600' }}">Visão geral</a>
                    <a href="{{ route('contas.index') }}" class="rounded-lg px-3 py-2 font-medium transition hover:bg-slate-100 {{ request()->routeIs('contas.*') ? 'bg-slate-100 text-slate-950' : 'text-slate-600' }}">Contas</a>
                    <a href="{{ route('gastos.index') }}" class="rounded-lg px-3 py-2 font-medium transition hover:bg-slate-100 {{ request()->routeIs('gastos.*') ? 'bg-slate-100 text-slate-950' : 'text-slate-600' }}">Gastos diários</a>
                    <a href="{{ route('recebimentos.index') }}" class="rounded-lg px-3 py-2 font-medium transition hover:bg-slate-100 {{ request()->routeIs('recebimentos.*') ? 'bg-slate-100 text-slate-950' : 'text-slate-600' }}">Recebimentos</a>
                    <a href="{{ route('cartoes.index') }}" class="rounded-lg px-3 py-2 font-medium transition hover:bg-slate-100 {{ request()->routeIs('cartoes.*') ? 'bg-slate-100 text-slate-950' : 'text-slate-600' }}">Cartões</a>
                </nav>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium transition hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-sky-100">Sair</button>
                </form>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-5 py-10 sm:py-14">
        @isset($slot)
            {{ $slot }}
        @endisset
        @yield('content')
    </main>
    @livewireScripts
</body>
</html>
