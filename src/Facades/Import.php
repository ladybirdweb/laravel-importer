<?php

namespace LWS\Import\Facades;

use Illuminate\Support\Facades\Facade;

class Import extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'import';
    }
}
