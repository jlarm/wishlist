<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\User;
use Livewire\Attributes\Validate;
use Livewire\Form;

final class RegisterForm extends Form
{
    #[Validate(['required', 'string', 'max:255'])]
    public string $name = '';

    #[Validate(['required', 'email', 'max:255', 'unique:users,email'])]
    public string $email = '';

    #[Validate(['required', 'string', 'min:8', 'confirmed'])]
    public string $password = '';

    #[Validate(['required', 'string'])]
    public string $password_confirmation = '';

    public function create(): User
    {
        return User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);
    }
}
