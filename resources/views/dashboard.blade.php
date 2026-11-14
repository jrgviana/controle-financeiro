@extends('layouts.app')

@section('title', 'Visão geral')

@section('content')
    <p class="text-sm font-semibold text-sky-700">Visão geral</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Olá, {{ auth()->user()->name }}!</h1>
    <p class="mt-3 max-w-2xl text-base leading-7 text-slate-500">Vamos construir seu controle financeiro por etapas, começando pelas contas onde você guarda seu dinheiro.</p>

    <section class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Etapa 02 · Contas financeiras</h2>
            <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Disponível</span>
        </div>
        <p class="mt-3 text-sm leading-6 text-slate-600">Cadastre sua conta corrente, poupança ou carteira e informe o saldo inicial. Ainda não há movimentações: o saldo inicial não representa um saldo atualizado por gastos ou receitas.</p>
        <a href="{{ route('contas.index') }}" class="mt-5 inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-sky-100">
            Gerenciar minhas contas
        </a>
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
@endsection
