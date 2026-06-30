<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/InvitationAcceptanceController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

const props = defineProps<{
    token: string;
    valid: boolean;
    email: string | null;
    expiresAt: string | null;
}>();

defineOptions({
    layout: {
        title: 'Accept your invitation',
        description: 'Create your account to join the wishlist',
    },
});

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(store(props.token).url, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Accept invitation" />

    <div v-if="!valid" class="flex flex-col items-center gap-4 text-center">
        <p class="text-sm text-muted-foreground">
            This invitation is invalid, has expired, or has already been used.
        </p>
        <TextLink :href="login()">Go to login</TextLink>
    </div>

    <form v-else class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    :model-value="email ?? ''"
                    disabled
                    readonly
                />
                <p class="text-xs text-muted-foreground">
                    Your invitation is tied to this email address.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Your full name"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="form.processing"
            >
                <Spinner v-if="form.processing" />
                Create account
            </Button>

            <p class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="login()">Log in</TextLink>
            </p>
        </div>
    </form>
</template>
