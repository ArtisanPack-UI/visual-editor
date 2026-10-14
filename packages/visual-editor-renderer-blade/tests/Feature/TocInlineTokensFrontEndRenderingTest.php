<?php

/**
 * Heading anchors and TOC entries for headings with inline `{{token}}`s,
 * rendered through `<x-ve-blocks :post>` (#858).
 *
 * The heading text renders token-resolved, so its auto-generated anchor
 * and `artisanpack/toc` entry must be derived from the same resolved
 * text rather than the raw token string.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.14.0
 */

declare( strict_types=1 );

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;

const TOC_TOKEN_RESOLVER = 'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentResolver';

beforeEach( function (): void {
	app()->instance( TOC_TOKEN_RESOLVER, new class {
		public function render( string $content, array $context = [] ): string
		{
			return preg_replace_callback(
				'/\{\{\s*([^{}]+?)\s*\}\}/',
				fn ( array $m ) => match ( trim( $m[1] ) ) {
					'site.name'    => 'Acme',
					'site.tagline' => 'Built Better',
					'site.raw'     => '{{site.tagline}}',
					default        => '',
				},
				$content,
			) ?? $content;
		}

		public function signatureFor( string $content ): string
		{
			return 'test-sig';
		}
	} );
} );

/**
 * Build a canonical-shape block.
 *
 * @param  array<string, mixed>              $attributes
 * @param  array<int, array<string, mixed>>  $innerBlocks
 *
 * @return array<string, mixed>
 */
function tokenTocBlock( string $name, array $attributes = [], array $innerBlocks = [], string $clientId = 'cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

function tokenTocPost(): Model
{
	$post = new class extends Model {
		protected $table = 'posts';
	};

	$post->setRawAttributes( [ 'title' => 'Core Package' ], true );

	return $post;
}

/**
 * @param  array<int, array<string, mixed>>  $tree
 */
function renderTokenTocTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" />', [
		'tree' => $tree,
		'post' => tokenTocPost(),
	] );
}

it( 'derives heading anchors and TOC entries from token-resolved heading content (#858)', function (): void {
	$tree = [
		tokenTocBlock( 'artisanpack/toc', [], [], 'toc-1' ),
		tokenTocBlock( 'core/heading', [ 'level' => 2, 'content' => 'About {{site.name}}' ], [], 'h-1' ),
		tokenTocBlock( 'core/group', [], [
			tokenTocBlock( 'artisanpack/heading', [ 'level' => 3, 'content' => '{{ site.tagline }}' ], [], 'h-2' ),
		], 'group-1' ),
	];

	$html = renderTokenTocTree( $tree );

	expect( $html )->toContain( 'id="about-acme"' )
		->and( $html )->toContain( 'href="#about-acme">About Acme</a>' )
		->and( $html )->toContain( 'id="built-better"' )
		->and( $html )->toContain( 'href="#built-better">Built Better</a>' )
		->and( $html )->not->toContain( 'site-name' )
		->and( $html )->not->toContain( 'site-tagline' )
		->and( $html )->not->toContain( '{{' );
} );

it( 'keeps author-set anchors on tokenized headings', function (): void {
	$tree = [
		tokenTocBlock( 'artisanpack/toc', [], [], 'toc-1' ),
		tokenTocBlock( 'core/heading', [ 'level' => 2, 'content' => 'About {{site.name}}', 'anchor' => 'overview' ], [], 'h-1' ),
	];

	$html = renderTokenTocTree( $tree );

	expect( $html )->toContain( 'id="overview"' )
		->and( $html )->toContain( 'href="#overview">About Acme</a>' )
		->and( $html )->not->toContain( 'id="about-acme"' );
} );

it( 'keeps static and tokenized headings paired with their own text', function (): void {
	$tree = [
		tokenTocBlock( 'artisanpack/toc', [], [], 'toc-1' ),
		tokenTocBlock( 'core/paragraph', [ 'content' => 'Call {{site.name}}' ], [], 'p-1' ),
		tokenTocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static heading' ], [], 'h-1' ),
		tokenTocBlock( 'core/heading', [ 'level' => 2, 'content' => 'About {{site.name}}' ], [], 'h-2' ),
		tokenTocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Acme' ], [], 'h-3' ),
	];

	$html = renderTokenTocTree( $tree );

	expect( $html )->toContain( 'id="static-heading"' )
		->and( $html )->toContain( 'href="#static-heading">Static heading</a>' )
		->and( $html )->toContain( 'id="about-acme"' )
		->and( $html )->toContain( 'href="#about-acme">About Acme</a>' )
		->and( $html )->toContain( 'id="acme"' )
		->and( $html )->toContain( 'href="#acme">Acme</a>' )
		->and( $html )->toContain( 'Call Acme' );
} );

it( 'derives anchors from the resolved value of a bound heading that holds a token', function (): void {
	$post = tokenTocPost();
	$post->setRawAttributes( [ 'title' => 'Welcome to {{site.name}}' ], true );

	$tree = [
		tokenTocBlock( 'artisanpack/toc', [], [], 'toc-1' ),
		tokenTocBlock( 'core/heading', [
			'level'    => 2,
			'content'  => 'Static title',
			'bindings' => [
				'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ],
			],
		], [], 'h-1' ),
	];

	$html = Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" />', [
		'tree' => $tree,
		'post' => $post,
	] );

	expect( $html )->toContain( 'id="welcome-to-acme"' )
		->and( $html )->toContain( 'href="#welcome-to-acme">Welcome to Acme</a>' );
} );

it( 'falls back to the raw heading text when the token resolver is unavailable', function (): void {
	app()->bind( TOC_TOKEN_RESOLVER, function (): never {
		throw new RuntimeException( 'Resolver unavailable.' );
	} );

	$tree = [
		tokenTocBlock( 'artisanpack/toc', [], [], 'toc-1' ),
		tokenTocBlock( 'core/heading', [ 'level' => 2, 'content' => 'About {{site.name}}' ], [], 'h-1' ),
	];

	$html = renderTokenTocTree( $tree );

	expect( $html )->toContain( 'id="about-site-name"' )
		->and( $html )->toContain( 'href="#about-site-name"' );
} );

it( 'derives post-content heading anchors from token-resolved HTML without resolving it twice', function (): void {
	$tree = [
		tokenTocBlock( 'artisanpack/toc', [], [], 'toc-1' ),
		tokenTocBlock( 'core/post-content', [
			'_resolvedContent' => '<h2>About {{site.name}}</h2><p>Literal: {{site.raw}}</p><h2 id="kept">Kept</h2><h3>{{site.tagline}}</h3>',
		], [], 'pc-1' ),
	];

	$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $html )->toContain( '<h2 id="about-acme">About Acme</h2>' )
		->and( $html )->toContain( 'href="#about-acme">About Acme</a>' )
		->and( $html )->toContain( '<h2 id="kept">Kept</h2>' )
		->and( $html )->toContain( '<h3 id="built-better">Built Better</h3>' )
		->and( $html )->toContain( 'href="#built-better">Built Better</a>' )
		->and( $html )->toContain( 'Literal: {{site.tagline}}' );
} );
