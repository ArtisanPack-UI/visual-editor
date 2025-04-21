<?php

namespace Digitalshopfront\VisualEditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Digitalshopfront\VisualEditor\VisualEditor
 */
class VisualEditor extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'visualEditor';
    }
}
