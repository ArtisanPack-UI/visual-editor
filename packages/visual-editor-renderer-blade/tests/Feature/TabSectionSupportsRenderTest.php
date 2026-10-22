<?php

/**
 * `artisanpack/tabs` panels carry their tab section's own supports (#922):
 * class, block style, spacing, border and shadow, and an aria-label that
 * replaces aria-labelledby.
 *
 * @since 1.15.2
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * @param  array<string, mixed>  $sectionAttributes
 */
function renderTabsWithSection( array $sectionAttributes ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'clientId'    => 'tabs-1',
		'name'        => 'artisanpack/tabs',
		'attributes'  => [],
		'innerBlocks' => [ [
			'clientId'    => 'section-1',
			'name'        => 'artisanpack/tab-section',
			'attributes'  => array_merge( [ 'label' => 'One', 'tabId' => 'one' ], $sectionAttributes ),
			'innerBlocks' => [ [
				'clientId'    => 'p-1',
				'name'        => 'core/paragraph',
				'attributes'  => [ 'content' => 'Panel one' ],
				'innerBlocks' => [],
			] ],
		] ],
	] ] ] );
}

/**
 * The rendered panel's opening tag.
 */
function tabPanelTag( string $html ): string
{
	preg_match( '/<div[^>]*role="tabpanel"[^>]*>/', $html, $matches );

	return $matches[0] ?? '';
}

it( 'puts the section class and block style on the panel', function (): void {
	$panel = tabPanelTag( renderTabsWithSection( [ 'className' => 'is-style-repro my-panel' ] ) );

	expect( $panel )->toContain( 'class="wp-block-artisanpack-tab-section ap-tab-section is-style-repro my-panel"' );
} );

it( 'prints the section padding and border on the panel', function (): void {
	$panel = tabPanelTag( renderTabsWithSection( [ 'style' => [
		'spacing' => [ 'padding' => [ 'top' => '3rem', 'left' => '3rem' ] ],
		'border'  => [ 'width' => '4px', 'style' => 'solid', 'color' => '#ff0000' ],
	] ] ) );

	expect( $panel )->toContain( 'padding-top: 3rem;' )
		->and( $panel )->toContain( 'padding-left: 3rem;' )
		->and( $panel )->toContain( 'border-width: 4px;' )
		->and( $panel )->toContain( 'border-color: #ff0000;' );
} );

it( 'keeps the panel id and labels it by its tab', function (): void {
	$panel = tabPanelTag( renderTabsWithSection( [] ) );

	expect( $panel )->toContain( 'id="tabs-panel-one"' )
		->and( $panel )->toContain( 'aria-labelledby="tabs-tab-one"' )
		->and( $panel )->not->toContain( 'aria-label=' );
} );

it( 'uses the section aria-label instead of aria-labelledby', function (): void {
	$panel = tabPanelTag( renderTabsWithSection( [ 'ariaLabel' => 'First "panel"' ] ) );

	expect( $panel )->toContain( 'aria-label="First &quot;panel&quot;"' )
		->and( $panel )->not->toContain( 'aria-labelledby' );
} );

it( 'ignores an anchor so the panel id stays the tab target', function (): void {
	$panel = tabPanelTag( renderTabsWithSection( [ 'anchor' => 'custom' ] ) );

	expect( $panel )->toContain( 'id="tabs-panel-one"' )
		->and( $panel )->not->toContain( 'id="custom"' );
} );
