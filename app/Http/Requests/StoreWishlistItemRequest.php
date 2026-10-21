<?php

namespace App\Http\Requests;

use App\Concerns\WishlistItemValidationRules;
use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWishlistItemRequest extends FormRequest
{
    use WishlistItemValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Adding to someone else's list makes a surprise, which only admins may do.
     */
    public function authorize(): bool
    {
        if (! $this->filled('for_user_id')) {
            return $this->user()->can('create', WishlistItem::class);
        }

        $recipient = $this->recipient();

        return $recipient !== null && $this->user()->can('createFor', [WishlistItem::class, $recipient]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->wishlistItemRules(),
            'for_user_id' => ['nullable', 'integer'],
            // A surprise can be claimed as it's added: left blank it's just an
            // idea, otherwise reserved or already bought by the admin adding it.
            'claim_status' => [
                'nullable',
                'exclude_without:for_user_id',
                Rule::in([PurchaseStatus::Reserved->value, PurchaseStatus::Purchased->value]),
            ],
            'price_paid' => ['nullable', 'exclude_without:for_user_id', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * The person a surprise is being added for, or null when adding to the
     * user's own list.
     */
    public function recipient(): ?User
    {
        if (! $this->filled('for_user_id')) {
            return null;
        }

        return User::query()->find($this->integer('for_user_id'));
    }

    /**
     * The claim to record alongside a surprise, or null to leave it unclaimed.
     */
    public function claimStatus(): ?PurchaseStatus
    {
        return PurchaseStatus::tryFrom((string) $this->validated('claim_status'));
    }
}
