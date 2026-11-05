<?php

namespace App\Tenancy;

use App\Models\Restaurant;

/** Nhà hàng của request hiện tại. Do middleware nhận diện theo slug hoặc host đặt. */
class TenantContext
{
    private ?Restaurant $restaurant = null;

    public function set(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function get(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function id(): ?int
    {
        return $this->restaurant?->getKey();
    }

    public function forget(): void
    {
        $this->restaurant = null;
    }
}
