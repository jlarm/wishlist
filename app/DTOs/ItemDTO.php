<?php

namespace App\DTOs;

final class ItemDTO
{
    public function __construct(
        public string $uuid,
        public string $user_id,
        public string $name,
        public string $image,
        public string $size,
        public string $color,
        public string $link,
        public string $price,
        public string $store,
        public bool $purchased,
        public string $purchased_by,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            user_id: $data['user_id'],
            name: $data['name'],
            image: $data['image'],
            size: $data['size'],
            color: $data['color'],
            link: $data['link'],
            price: $data['price'],
            store: $data['store'],
            purchased: $data['purchased'],
            purchased_by: $data['purchased_by'],
        );
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'image' => $this->image,
            'size' => $this->size,
            'color' => $this->color,
            'link' => $this->link,
            'price' => $this->price,
            'store' => $this->store,
            'purchased' => $this->purchased,
            'purchased_by' => $this->purchased_by,
        ];
    }
}