<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>Entrar — Controle Financeiro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-5 py-12">
        <div class="grid w-full max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60 md:grid-cols-2">
            <section class="hidden flex-col justify-between bg-slate-900 p-10 text-white md:flex">
                <div class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-white/10 text-xl font-semibold">$</div>
                    <div class="text-lg font-semibold tracking-tight">Controle Financeiro</div>
                </div>
                <div>
                    <p class="mb-4 text-xs font-semibold uppercase tracking-[.22em] text-sky-300">Seu espaço financeiro</p>
                    <h1 class="text-4xl font-semibold leading-tight tracking-tight">Organização com mais clareza e tranquilidade.</h1>
                    <p class="mt-5 text-base leading-relaxed text-slate-300">Um lugar para acompanhar seus compromissos e, em breve, seus lançamentos, cartões e crediários.</p>
                </div>
                <p class="text-xs text-slate-400">Acesso privado · Primeira etapa do projeto</p>
            </section>

            <section class="px-6 py-12 sm:px-11 sm:py-16">
                <div class="mb-10 flex items-center gap-3 md:hidden">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-slate-900 font-bold text-white">$</div>
                    <span class="text-lg font-semibold tracking-tight">Controle Financeiro</span>
                </div>

                <p class="mb-2 text-sm font-semibold text-sky-700">Bem-vindo</p>
                <h2 class="text-3xl font-semibold tracking-tight">Entre na sua conta</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">Informe seu usuário e o PIN numérico de seis dígitos.</p>

                <form method="POST" action="{{ route('login.store') }}" class="mt-9 space-y-5">
                    @csrf
                    <div>
                        <label for="username" class="mb-2 block text-sm font-medium">Usuário</label>
                        <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" required autofocus maxlength="40"
                               class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base outline-none transition focus:border-sky-700 focus:ring-4 focus:ring-sky-100"
                               placeholder="Seu nome de usuário">
                        @error('username') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="pin" class="mb-2 block text-sm font-medium">PIN numérico</label>
                        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="current-password" required
                               class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base tracking-[.18em] outline-none transition focus:border-sky-700 focus:ring-4 focus:ring-sky-100"
                               placeholder="••••••" aria-describedby="pin-help">
                        <p id="pin-help" class="mt-2 text-xs text-slate-500">Apenas números, sem símbolos nem letras.</p>
                        @error('pin') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-slate-900 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-sky-200">Entrar no sistema</button>
                </form>
                <p class="mt-10 text-center text-xs leading-5 text-slate-400">Uso local. Não há cadastro público de usuários.</p>
            </section>
        </div>
    </main>
</body>
</html>
