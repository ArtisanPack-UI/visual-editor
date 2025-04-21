<?php

namespace Digitalshopfront\VisualEditor;

use Illuminate\Support\ServiceProvider;

class VisualEditorServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton( 'package', function ( $app ) {
            return new VisualEditor();
        } );
    }
}
