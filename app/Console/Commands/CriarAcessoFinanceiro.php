<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CriarAcessoFinanceiro extends Command
{
    protected $signature = 'financeiro:criar-acesso';

    protected $description = 'Cria o primeiro acesso do Controle Financeiro sem expor o PIN no terminal.';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->warn('Já existe um usuário cadastrado. Nenhuma alteração foi feita.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Seu nome'));
        $username = Str::lower(trim((string) $this->ask('Nome de usuário para entrar', 'usuario')));

        if ($name === '' || mb_strlen($name) > 100) {
            $this->error('Informe um nome com até 100 caracteres.');

            return self::FAILURE;
        }

        if (! preg_match('/^[a-z0-9._-]{3,40}$/D', $username)) {
            $this->error('O usuário deve ter de 3 a 40 caracteres: letras minúsculas, números, ponto, hífen ou sublinhado.');

            return self::FAILURE;
        }

        $pin = (string) $this->secret('Crie seu PIN numérico de 6 dígitos');
        $confirmation = (string) $this->secret('Repita o PIN para confirmar');

        if (! preg_match('/^[0-9]{6}$/D', $pin)) {
            $this->error('O PIN deve conter exatamente 6 números.');

            return self::FAILURE;
        }

        if (! hash_equals($pin, $confirmation)) {
            $this->error('Os PINs informados não coincidem.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'username' => $username,
            // E-mail interno necessário ao esquema padrão do Laravel; não é usado para login.
            'email' => $username.'@controle-financeiro.invalid',
            'password' => Hash::make($pin),
        ]);

        $this->info('Acesso criado! Entre com seu usuário e o PIN de 6 dígitos.');

        return self::SUCCESS;
    }
}
