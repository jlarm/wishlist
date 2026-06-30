<?php

namespace App\Concerns;

use App\Enums\Priority;
use App\Enums\VisibilityStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait WishlistItemValidationRules
{
    /**
     * Get the validation rules shared by the create and update requests.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function wishlistItemRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'size' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'visibility_status' => ['required', Rule::enum(VisibilityStatus::class)],
        ];
    }
}
