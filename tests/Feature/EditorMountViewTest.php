<?php

declare( strict_types=1 );

$viewPath   = __DIR__ . '/../../resources/views/editor/mount.blade.php';
$routesPath = __DIR__ . '/../../routes/web.php';

test( 'mount blade view exists', function () use ( $viewPath ): void {
    expect( file_exists( $viewPath ) )->toBeTrue();
} );

test( 'mount blade view renders a visual-editor mount element with the data attributes the new bootstrap consumes', function () use ( $viewPath ): void {
    $contents = file_get_contents( $viewPath );

    expect( $contents )->toContain( 'data-ap-visual-editor' );
    expect( $contents )->toContain( 'data-resource="{{ $resource }}"' );
    expect( $contents )->toContain( 'data-id="{{ $modelId }}"' );
    expect( $contents )->toContain( 'data-api-base="{{ $apiBase }}"' );
} );

test( 'mount blade view pulls the new editor entry through @vite', function () use ( $viewPath ): void {
    $contents = file_get_contents( $viewPath );

    expect( $contents )->toContain( "@vite(['resources/js/visual-editor/editor/main.tsx'])" );
    expect( $contents )->not->toContain( '_legacy' );
} );

test( 'package routes register the /editor path', function () use ( $routesPath ): void {
    // Whitespace-insensitive so the assertions survive php-cs-fixer's
    // WordPress-style spacing inside parentheses.
    $contents = preg_replace( '/\s+/', '', (string) file_get_contents( $routesPath ) );

    expect( $contents )->toContain( "Route::get('/editor'" );
    expect( $contents )->toContain( 'visual-editor::editor.mount' );
    expect( $contents )->toContain( "'resource'" );
    expect( $contents )->toContain( "'modelId'" );
    expect( $contents )->toContain( "'apiBase'" );
    expect( $contents )->not->toContain( '/editor-spike' );
} );

test( 'editor mounts stamp the block-CSS ability for the Custom CSS field (#881)', function (): void {
    foreach ( [ 'editor/mount', 'components/editor', 'site-editor/index' ] as $view ) {
        expect( file_get_contents( __DIR__ . '/../../resources/views/' . $view . '.blade.php' ) )
            ->toContain( '@can( \\ArtisanPackUI\\VisualEditor\\Effects\\BlockCssAccess::ABILITY ) data-can-edit-block-css="true" @endcan' );
    }
} );
