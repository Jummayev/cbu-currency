<?php

namespace Cbu\Currency\Facades;

use Illuminate\Support\Facades\Facade;

class CbuCurrency extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cbu-currency';
    }
}
