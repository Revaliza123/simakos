<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function home(): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return redirect()->route(Auth::user()->role === 'admin' ? 'overview' : 'tenant.overview');
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password yang dimasukkan tidak sesuai.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route(Auth::user()->role === 'admin' ? 'overview' : 'tenant.overview');
    }

    public function showRegistration(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            ...$validated,
            'role' => 'tenant',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('tenant.overview');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda berhasil keluar.');
    }
}
