<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A promo code or add-on the customer asked for cannot be applied. `field` is the
 * form field it belongs to and `reason` a key into `reservation.messages`.
 */
class PricingOptionRejected extends RuntimeException
{
    public function __construct(public readonly string $field, public readonly string $reason)
    {
        parent::__construct("{$field}: {$reason}");
    }
}
