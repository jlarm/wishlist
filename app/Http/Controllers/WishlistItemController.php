<?php

namespace App\Http\Controllers;

use App\Actions\RecordItemPrice;
use App\Enums\ClaimRemovalReason;
use App\Enums\Priority;
use App\Enums\VisibilityStatus;
use App\Http\Requests\StoreWishlistItemRequest;
use App\Http\Requests\UpdateWishlistItemRequest;
use App\Models\WishlistItem;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishlistItemController extends Controller
{
    /**
     * Fields a gift-giver may be buying against, with their human labels. A
     * change to any of these is passed on to whoever has claimed the item.
     *
     * @var array<string, string>
     */
    private const CLAIM_RELEVANT_FIELDS = [
        'title' => 'Title',
        'description' => 'Description',
        'url' => 'Link',
        'size' => 'Size',
        'color' => 'Color',
        'notes' => 'Notes',
    ];

    public function __construct(private readonly PurchaseService $purchases) {}

    /**
     * Show the form for creating a new wishlist item.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', WishlistItem::class);

        return Inertia::render('WishlistItems/Create', [
            'priorities' => Priority::options(),
            'visibilities' => VisibilityStatus::options(),
            'prefill' => $this->sharedLink($request),
        ]);
    }

    /**
     * The product link handed over by the bookmarklet or the phone's share
     * sheet, if any. Share sheets often put the link inside "text" rather than
     * "url", so the first http(s) URL found in either is used.
     *
     * @return array{url: string, title: string|null}|null
     */
    private function sharedLink(Request $request): ?array
    {
        $candidates = [$request->query('url'), $request->query('text')];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || ! preg_match('~https?://\S+~i', $candidate, $match)) {
                continue;
            }

            $url = mb_substr($match[0], 0, 2048);

            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $title = $request->query('title');

            return [
                'url' => $url,
                'title' => is_string($title) && trim($title) !== '' ? mb_substr(trim($title), 0, 255) : null,
            ];
        }

        return null;
    }

    /**
     * Store a newly created wishlist item.
     */
    public function store(StoreWishlistItemRequest $request, RecordItemPrice $recordPrice): RedirectResponse
    {
        $user = $request->user();

        // New wishes join the end of the owner's ranking.
        $item = $user->wishlistItems()->make($request->validated());
        $item->position = (int) $user->wishlistItems()->max('position') + 1;
        $item->save();

        // Seed the price history from the starting price so the chart begins
        // populating immediately instead of waiting for the second nightly check.
        if ($item->price !== null) {
            $recordPrice($item, $item->price);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item added to your wishlist.')]);

        return to_route('wishlists.show', $request->user());
    }

    /**
     * Show the form for editing a wishlist item.
     */
    public function edit(Request $request, WishlistItem $wishlistItem): Response
    {
        $this->authorize('update', $wishlistItem);

        return Inertia::render('WishlistItems/Edit', [
            // Owner-only payload — deliberately contains no purchase data.
            'item' => [
                'id' => $wishlistItem->id,
                'title' => $wishlistItem->title,
                'description' => $wishlistItem->description,
                'url' => $wishlistItem->url,
                'image_url' => $wishlistItem->image_url,
                'price' => $wishlistItem->price,
                'size' => $wishlistItem->size,
                'color' => $wishlistItem->color,
                'tags' => $wishlistItem->tags,
                'priority' => $wishlistItem->priority->value,
                'notes' => $wishlistItem->notes,
                'visibility_status' => $wishlistItem->visibility_status->value,
            ],
            'priorities' => Priority::options(),
            'visibilities' => VisibilityStatus::options(),
        ]);
    }

    /**
     * Update the given wishlist item.
     */
    public function update(UpdateWishlistItemRequest $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $wishlistItem->fill($request->validated());

        $changes = collect(self::CLAIM_RELEVANT_FIELDS)
            ->filter(fn (string $label, string $field): bool => $wishlistItem->isDirty($field))
            ->mapWithKeys(fn (string $label, string $field): array => [$label => [
                'from' => $wishlistItem->getOriginal($field),
                'to' => $wishlistItem->getAttribute($field),
            ]])
            ->all();
        $wasHidden = $wishlistItem->isDirty('visibility_status')
            && $wishlistItem->visibility_status === VisibilityStatus::Hidden;

        // A new link hasn't been checked yet, so forget the old one's verdict.
        if ($wishlistItem->isDirty('url')) {
            $wishlistItem->availability = null;
            $wishlistItem->link_failures = 0;
            $wishlistItem->availability_checked_at = null;
        }

        $wishlistItem->save();

        // Keep whoever claimed it in the loop. The owner gets no hint either way.
        if ($wasHidden) {
            $this->purchases->alertClaimerOfRemoval($wishlistItem, ClaimRemovalReason::Hidden);
        } else {
            $this->purchases->alertClaimerOfChanges($wishlistItem, $changes);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item updated.')]);

        return to_route('wishlists.show', $request->user());
    }

    /**
     * Delete the given wishlist item.
     */
    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('delete', $wishlistItem);

        $wishlistItem->delete();

        $this->purchases->alertClaimerOfRemoval($wishlistItem, ClaimRemovalReason::Deleted);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item deleted.')]);

        return to_route('wishlists.show', $request->user());
    }
}
