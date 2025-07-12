<?php

namespace App\Observers;

use Illuminate\Support\Str;
use App\Models\Item;

class ItemObserver
{
    public function creating(Item $item): void
    {
        $item->uuid = Str::uuid();
    }
}
