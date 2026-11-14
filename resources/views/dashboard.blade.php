<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc">
    <title>Visão geral — Controle Financeiro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-slate-900 font-bold text-white">$</div>
                <div>
                    <p class="text-base font-semibold tracking-tight">Controle Financeiro</p>
                    <p class="text-xs text-slate-500">Seu painel pessoal</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium transition hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-sky-100">Sair</button>
            </form>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-5 py-10 sm:py-14">
        <p class="text-sm font-semibold text-sky-700">Visão geral</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Olá, {{ auth()->user()->name }}!</h1>
        <p class="mt-3 max-w-2xl text-base leading-7 text-slate-500">Seu ambiente está configurado e seu acesso está protegido. Agora podemos construir cada funcionalidade financeira, uma de cada vez.</p>

        <section class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Primeira etapa concluída</h2>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Acesso ativo</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-600">Nesta versão ainda não há lançamentos nem valores financeiros. Vamos adicionar essas informações somente quando as regras de cada módulo estiverem definidas e testadas.</p>
        </section>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach ([
                ['titulo' => 'Gastos diários', 'descricao' => 'Registro de compras e despesas pagas.'],
                ['titulo' => 'Cartões', 'descricao' => 'Fechamento, faturas e parcelas.'],
                ['titulo' => 'Crediários', 'descricao' => 'Parcelamentos diretos com pessoas ou lojas.'],
            ] as $item)
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Módulo futuro</p>
                    <h3 class="mt-3 text-lg font-semibold">{{ $item['titulo'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $item['descricao'] }}</p>
                </div>
            @endforeach
        </div>
    </main>
</body>
</html>
