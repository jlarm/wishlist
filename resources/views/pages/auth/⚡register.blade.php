<?php

use App\Livewire\Forms\RegisterForm;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Register')]
class extends Component
{
    public RegisterForm $form;

    public function register(): void
    {
        $this->validate();

        $user = $this->form->create();

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('home'), navigate: true);
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-white px-4 py-12">
    <div class="w-[400px]">
        <flux:card>
            <div>
                <flux:heading class="text-center" size="xl">Create your account</flux:heading>
                <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                    Or
                    <flux:link wire:navigate href="{{ route('login') }}" variant="subtle" class="text-sm">sign in to
                        your account
                    </flux:link>
                </p>
            </div>

            <form wire:submit="register">
                <div class="space-y-6">
                    <flux:field>
                        <div class="mb-3 flex justify-between">
                            <flux:label>Name</flux:label>
                        </div>
                        <flux:input wire:model="form.name" type="text"/>
                        <flux:error name="form.name"/>
                    </flux:field>

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

                    <flux:field>
                        <div class="mb-3 flex justify-between">
                            <flux:label>Confirm Password</flux:label>
                        </div>
                        <flux:input wire:model="form.password_confirmation" type="password"/>
                        <flux:error name="form.password_confirmation"/>
                    </flux:field>

                    <flux:button type="submit" variant="primary" class="w-full">Register</flux:button>
                </div>
            </form>
        </flux:card>
    </div>
</div>
