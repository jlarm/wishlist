export type SelectOption = {
    value: string;
    label: string;
};

export type PurchaseInfo = {
    purchased_by_name: string | null;
    purchased_at: string | null;
    note: string | null;
    purchased_by_me: boolean;
    can_unmark: boolean;
};

export type WishlistItem = {
    id: number;
    user_id: number;
    owner_name?: string;
    title: string;
    description: string | null;
    url: string | null;
    image_url: string | null;
    price: string | null;
    size: string | null;
    color: string | null;
    priority: string;
    priority_label: string;
    priority_weight: number;
    notes: string | null;
    visibility_status: string;
    is_owner: boolean;
    created_at: string | null;
    updated_at: string | null;
    can: {
        update: boolean;
        delete: boolean;
        // Only present for non-owners. Purchase data never reaches the owner.
        purchase?: boolean;
    };
    is_purchased?: boolean;
    purchase?: PurchaseInfo | null;
};

export type WishlistUserSummary = {
    id: number;
    name: string;
    is_admin: boolean;
    is_me: boolean;
    wishlist_items_count: number;
};
