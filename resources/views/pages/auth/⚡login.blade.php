<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Login')]
class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->form->authenticate();

        Session::regenerate();

        $this->redirect(route('home'), navigate: true);
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-white px-4 py-12">
    <div class="w-[400px]">
        <flux:card>
            <div>
                <flux:heading class="text-center" size="xl">Sign in to your account</flux:heading>
                <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    Or <flux:link wire:navigate href="{{ route('register') }}" variant="subtle" class="text-sm">create a new account</flux:link>
                </p>
            </div>

            <form wire:submit="login">
                <div class="space-y-6">
                    <flux:field>
                        <div class="mb-3 flex justify-between">
                            <flux:label>Email</flux:label>
                        </div>
                        <flux:input wire:model="form.email" type="email" placeholder="email@example.com"/>
                        <flux:error name="form.email"/>
                    </flux:field>

                    <flux:field>
                        <div class="mb-3 flex justify-between">
                            <flux:label>Password</flux:label>
                        </div>
                        <flux:input wire:model="form.password" type="password"/>
                        <flux:error name="form.password"/>
                    </flux:field>

                    <flux:checkbox wire:model="form.remember">Remember me</flux:checkbox>

                    <flux:button type="submit" variant="primary" class="w-full">Sign in</flux:button>
                </div>
            </form>
        </flux:card>
    </div>
</div>
