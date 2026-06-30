<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:make-admin {email? : The email address of the admin}')]
#[Description('Create a new admin user, or promote an existing user to admin')]
class MakeAdminCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->resolveEmail();

        if ($email === null) {
            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            return $this->promote($existing);
        }

        return $this->createAdmin($email);
    }

    /**
     * Prompt for and validate the admin's email address.
     */
    private function resolveEmail(): ?string
    {
        $email = $this->argument('email') ?? text(
            label: 'Admin email address',
            required: true,
        );

        $email = mb_strtolower(trim($email));

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return null;
        }

        return $email;
    }

    /**
     * Promote an existing user to admin.
     */
    private function promote(User $user): int
    {
        if ($user->is_admin) {
            $this->info("{$user->email} is already an admin.");

            return self::SUCCESS;
        }

        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
        ])->save();

        $this->info("Promoted {$user->email} to admin.");

        return self::SUCCESS;
    }

    /**
     * Create a brand-new admin user.
     */
    private function createAdmin(string $email): int
    {
        $name = text(label: 'Name', required: true);

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value): ?string => Validator::make(
                ['password' => $value],
                ['password' => ['required', 'string', Password::default()]],
            )->errors()->first('password') ?: null,
        );

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->is_admin = true;
        $user->email_verified_at = Carbon::now();
        $user->save();

        $this->info("Created admin {$user->email}.");

        return self::SUCCESS;
    }
}
