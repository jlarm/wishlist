<?php

namespace App\Http\Controllers;

use App\Models\WishlistItem;
use App\Services\ProductMetadataScraper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductMetadataController extends Controller
{
    public function __construct(private ProductMetadataScraper $scraper) {}

    /**
     * Fetch title, description, price and candidate images for a product URL.
     *
     * Always responds with JSON so the client's useHttp request can consume it;
     * the scraper safely returns an empty payload for missing or unsafe URLs.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('create', WishlistItem::class);

        return response()->json(
            $this->scraper->fetch((string) $request->input('url', ''))
        );
    }
}
