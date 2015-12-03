<?php

namespace LaraCart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Daylight\Auth\Accounts\ConfirmationBroker
 */
class Cart extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laracart.cart';
    }
}
