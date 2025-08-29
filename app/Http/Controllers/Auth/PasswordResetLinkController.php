<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Поле Email обязательно для заполнения.',
            'email.email' => 'Введите корректный Email адрес.',
        ]);

        $status = Password::sendResetLink(
            $request->only('email'),
        );

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', 'Ссылка для восстановления пароля отправлена на ваш Email.');
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => 'Пользователь с таким Email не найден.']);
    }
}
