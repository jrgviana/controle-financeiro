<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PinLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:40'],
            'pin' => ['required', 'regex:/^[0-9]{6}$/D'],
        ], [
            'username.required' => 'Informe seu usuário.',
            'pin.required' => 'Informe seu PIN.',
            'pin.regex' => 'O PIN deve conter exatamente 6 números.',
        ]);

        $username = Str::lower(trim($data['username']));
        $key = 'financeiro:login:'.hash('sha256', $username.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'pin' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ]);
        }

        if (! Auth::attempt(['username' => $username, 'password' => $data['pin']])) {
            RateLimiter::hit($key, 300);

            return back()
                ->withErrors(['pin' => 'Usuário ou PIN incorretos.'])
                ->onlyInput('username');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
