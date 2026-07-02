<?php

namespace App\Http\Requests;

use App\Enums\PurchaseStatus;
use App\Models\WishlistItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var WishlistItem $item */
        $item = $this->route('wishlistItem');

        return $this->user()->can('purchase', $item);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A claim starts as a reservation by default, but a giver may skip
            // straight to a purchase. Delivered is only reachable by advancing.
            'status' => ['nullable', Rule::in([PurchaseStatus::Reserved->value, PurchaseStatus::Purchased->value])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
