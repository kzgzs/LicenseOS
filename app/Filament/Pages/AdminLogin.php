<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\HtmlString;

class AdminLogin extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
                TextInput::make('captcha')
                    ->label('Verification Code')
                    ->required()
                    ->maxLength(5)
                    ->helperText(new HtmlString(
                        '<img src="' . route('captcha.generate') . '" id="admin-captcha-img" class="inline-block h-8 rounded cursor-pointer mt-1 border align-middle" onclick="this.src=\'' . route('captcha.generate') . '?t=\'+Date.now()" title="Click to refresh">'
                    )),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        // Validate captcha first
        $data = $this->form->getState();
        $sessionCode = session('captcha_code', '');
        if (strtolower($data['captcha'] ?? '') !== $sessionCode) {
            $this->addError('captcha', 'The verification code is incorrect.');
            return null;
        }
        session()->forget('captcha_code');

        // Proceed with normal login
        return parent::authenticate();
    }
}
