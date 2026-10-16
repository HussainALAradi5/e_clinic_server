<?php

declare(strict_types=1);

namespace App\Modules\Reference\Addresses\Contracts;

use App\Modules\Reference\Addresses\Data\AddressData;
use App\Modules\Reference\Addresses\Models\Address;

interface AddressServiceInterface
{
    /**
     * **Internal** — creates or updates the owner's address after validating the country format
     * (called by PersonService / CompanyService inside their transaction).
     */
    public function save(?Address $current, AddressData $data): Address;

    /** **Internal** — removes an owner's address (owner sets `address_id = null`). */
    public function remove(?Address $address): void;

    /** Address with its country; readable only when the caller can read its owner. */
    public function find(string $id): Address;

    /** Printable address (EN/AR), one line or several. */
    public function format(Address $address, string $locale = 'en', bool $multiline = false): string;
}
