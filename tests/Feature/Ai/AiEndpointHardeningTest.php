<?php

/**
 * Access, rate-limit and input hardening for the AI endpoints (#828).
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Ai\Support\AiAccess;
use ArtisanPackUI\VisualEditor\Ai\Support\AltTextImageGuard;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\AltTextRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\RewriteContentRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\SuggestLayoutRequest;
use ArtisanPackUI\VisualEditor\Livewire\Ai\AiTools;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\Ai\AiAgentTestSetup;

/**
 * A user whose RBAC grants exactly the given capabilities.
 *
 * @param  array<int, string>  $capabilities
 */
function aiUser( array $capabilities = [] ): GenericUser
{
	return new class( [ 'id' => 1 ], $capabilities ) extends GenericUser {
		/** @param array<int, string> $capabilities */
		public function __construct( array $attributes, private array $capabilities )
		{
			parent::__construct( $attributes );
		}

		public function hasCapability( string $capability ): bool
		{
			return in_array( $capability, $this->capabilities, true );
		}
	};
}

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
	config()->set( 'app.url', 'https://example.test' );
} );

describe( 'access', function (): void {
	it( 'denies users without the capability and guests', function (): void {
		expect( AiAccess::allows( null ) )->toBeFalse();
		expect( AiAccess::allows( aiUser() ) )->toBeFalse();
		expect( AiAccess::allows( aiUser( [ 'use_ai_features' ] ) ) )->toBeTrue();
	} );

	it( 'denies everyone when the capability is blanked', function (): void {
		config()->set( 'artisanpack.visual-editor.ai.capability', '' );

		expect( AiAccess::allows( aiUser( [ 'use_ai_features' ] ) ) )->toBeFalse();
	} );

	it( 'gates and throttles every AI route', function (): void {
		foreach ( [ 'features', 'suggest-next-block', 'suggest-layout', 'alt-text', 'rewrite', 'heading-hierarchy' ] as $name ) {
			$middleware = Route::getRoutes()->getByName( 'visual-editor.api.ai.' . $name )->gatherMiddleware();

			expect( $middleware )->toContain( 'can:' . AiAccess::ABILITY );
			expect( $middleware )->toContain( 'throttle:20,1,ve-ai' );
		}
	} );

	it( 'returns 403 to a signed-in user without the capability', function (): void {
		$this->actingAs( aiUser() )
			->withoutMiddleware( ValidateCsrfToken::class )
			->postJson( '/visual-editor/api/ai/heading-hierarchy', [ 'blocks' => [ [ 'type' => 'core/heading' ] ] ] )
			->assertForbidden();
	} );

	it( 'rejects a server path on the alt-text endpoint even for allowed users', function (): void {
		$this->actingAs( aiUser( [ 'use_ai_features' ] ) )
			->withoutMiddleware( ValidateCsrfToken::class )
			->postJson( '/visual-editor/api/ai/alt-text', [ 'image' => [ 'source' => 'path', 'value' => base_path( 'composer.json' ) ] ] )
			->assertUnprocessable();

		expect( $this->prompter->calls )->toBeEmpty();
	} );

	it( 'refuses the Livewire listeners without the ability', function (): void {
		$tools = new class extends AiTools {
			/** @var array<int, string> */
			public array $events = [];

			public function dispatch( $event, ...$params )
			{
				$this->events[] = $event;

				return null;
			}
		};

		$tools->checkHeadings( [ [ 'id' => 'h', 'type' => 'core/heading' ] ] );

		expect( $tools->events )->toBe( [ 'ap-ve-ai:visual_editor.heading_hierarchy:forbidden' ] );
		expect( $this->prompter->calls )->toBeEmpty();
	} );
} );

describe( 'alt-text image guard', function (): void {
	it( 'accepts data URIs, base64 pairs and same-site URLs', function (): void {
		expect( AltTextImageGuard::violation( 'data:image/png;base64,AAAA' ) )->toBeNull();
		expect( AltTextImageGuard::violation( [ 'source' => 'base64', 'value' => 'AAAA' ] ) )->toBeNull();
		expect( AltTextImageGuard::violation( 'https://example.test/storage/a.jpg' ) )->toBeNull();
		expect( AltTextImageGuard::assertAllowed( 'https://example.test/a.jpg', 'ai.alt_text' ) )
			->toBe( [ 'source' => 'url', 'value' => 'https://example.test/a.jpg' ] );
	} );

	it( 'rejects server paths, bare strings and other hosts', function ( mixed $image ): void {
		expect( AltTextImageGuard::violation( $image ) )->not->toBeNull();
	} )->with( [
		'path pair'      => [ [ 'source' => 'path', 'value' => '/etc/passwd' ] ],
		'bare path'      => [ '/etc/passwd' ],
		'relative path'  => [ 'storage/app/private/x.jpg' ],
		'foreign host'   => [ 'https://evil.test/a.jpg' ],
		'metadata ip'    => [ 'http://169.254.169.254/latest/meta-data' ],
		'file scheme'    => [ [ 'source' => 'url', 'value' => 'file:///etc/passwd' ] ],
		'empty'          => [ '' ],
		'non-string'     => [ 42 ],
	] );

	it( 'does not trust the request Host header', function (): void {
		$this->app->instance( 'request', \Illuminate\Http\Request::create( 'http://169.254.169.254/' ) );

		expect( AltTextImageGuard::violation( 'http://169.254.169.254/latest/meta-data' ) )->not->toBeNull();
	} );

	it( 'allows configured extra hosts', function (): void {
		config()->set( 'artisanpack.visual-editor.ai.alt_text.allowed_hosts', [ 'cdn.example.test' ] );

		expect( AltTextImageGuard::violation( 'https://cdn.example.test/a.jpg' ) )->toBeNull();
	} );

	it( 'is enforced by the request rules', function (): void {
		$rules = ( new AltTextRequest() )->rules();

		expect( Validator::make( [ 'image' => [ 'source' => 'path', 'value' => '/etc/passwd' ] ], $rules )->fails() )->toBeTrue();
		expect( Validator::make( [ 'image' => 'data:image/png;base64,AAAA' ], $rules )->passes() )->toBeTrue();
	} );
} );

describe( 'size caps', function (): void {
	it( 'caps rewrite content length', function (): void {
		config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_text_chars', 10 );

		$validator = Validator::make( [ 'content' => str_repeat( 'a', 11 ), 'intent' => 'shorter' ], ( new RewriteContentRequest() )->rules() );

		expect( $validator->fails() )->toBeTrue();
	} );

	it( 'applies the block limits to layout suggestions', function (): void {
		config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_blocks', 1 );

		$validator = Validator::make(
			[ 'section_content' => [ [ 'type' => 'a' ], [ 'type' => 'b' ] ], 'available_patterns' => [ 'hero' ] ],
			( new SuggestLayoutRequest() )->rules(),
		);

		expect( $validator->fails() )->toBeTrue();
	} );

	it( 'rejects an oversized next-block suggestion payload instead of sending it', function (): void {
		config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_bytes', 100 );

		expect( fn () => \ArtisanPackUI\VisualEditor\Ai\Agents\ContentBlockSuggestionAgent::for( [
			'existing_blocks' => [ [ 'type' => 'core/paragraph', 'attrs' => [ 'content' => str_repeat( 'x', 500 ) ] ] ],
			'cursor_position' => 0,
		] )->run() )->toThrow( \ArtisanPackUI\Ai\Exceptions\FeatureError::class, 'too large' );

		expect( $this->prompter->calls )->toBeEmpty();
	} );
} );

describe( 'second-pass hardening', function (): void {
	it( 'caps the number and length of layout pattern slugs', function (): void {
		$validator = Validator::make(
			[ 'section_content' => [ [ 'type' => 'a' ] ], 'available_patterns' => array_fill( 0, 201, 'hero' ) ],
			( new SuggestLayoutRequest() )->rules(),
		);
		expect( $validator->fails() )->toBeTrue();

		expect( fn () => \ArtisanPackUI\VisualEditor\Ai\Agents\LayoutSuggestionAgent::for( [
			'section_content'    => [ [ 'type' => 'a' ] ],
			'available_patterns' => array_map( static fn ( int $i ): string => "p{$i}", range( 1, 201 ) ),
		] )->run() )->toThrow( \ArtisanPackUI\Ai\Exceptions\FeatureError::class );

		expect( $this->prompter->calls )->toBeEmpty();
	} );

	it( 'counts pattern slugs toward the byte cap', function (): void {
		config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_bytes', 200 );

		expect( fn () => \ArtisanPackUI\VisualEditor\Ai\Agents\LayoutSuggestionAgent::for( [
			'section_content'    => [ [ 'type' => 'a' ] ],
			'available_patterns' => array_map( static fn ( int $i ): string => str_repeat( 'x', 100 ) . $i, range( 1, 5 ) ),
		] )->run() )->toThrow( \ArtisanPackUI\Ai\Exceptions\FeatureError::class, 'too large' );
	} );

	it( 'rate-limits the Livewire listeners', function (): void {
		config()->set( 'artisanpack.visual-editor.ai.throttle', '1,1' );
		Gate::define( AiAccess::ABILITY, fn ( $user = null ) => true );
		\Illuminate\Support\Facades\RateLimiter::clear( 've-ai-livewire:' . request()->ip() );
		$this->prompter->queue( [ 'issues' => [] ] );

		$tools = new class extends AiTools {
			/** @var array<int, string> */
			public array $events = [];

			public function dispatch( $event, ...$params )
			{
				$this->events[] = $event;

				return null;
			}
		};

		$tools->checkHeadings( [ [ 'id' => 'h', 'type' => 'core/heading' ] ] );
		$tools->checkHeadings( [ [ 'id' => 'h', 'type' => 'core/heading' ] ] );

		expect( $tools->events[1] )->toBe( 'ap-ve-ai:visual_editor.heading_hierarchy:throttled' );
		expect( $this->prompter->calls )->toHaveCount( 1 );
	} );

	it( 'rejects image URLs on non-default ports', function (): void {
		expect( AltTextImageGuard::violation( 'http://example.test:6379/a.jpg' ) )->not->toBeNull();
		expect( AltTextImageGuard::violation( 'https://example.test:443/a.jpg' ) )->toBeNull();
	} );

	it( 'caps base64 image size', function (): void {
		$huge = 'data:image/png;base64,' . str_repeat( 'A', AltTextImageGuard::MAX_BASE64_LENGTH );

		expect( AltTextImageGuard::violation( $huge ) )->not->toBeNull();
	} );

	it( 'caps the Livewire rewrite intent', function (): void {
		Gate::define( AiAccess::ABILITY, fn ( $user = null ) => true );
		\Illuminate\Support\Facades\RateLimiter::clear( 've-ai-livewire:' . request()->ip() );

		$tools = new class extends AiTools {
			/** @var array<int, string> */
			public array $events = [];

			public function dispatch( $event, ...$params )
			{
				$this->events[] = $event;

				return null;
			}
		};

		$tools->rewriteContent( 'hello', str_repeat( 'x', 257 ) );

		expect( $tools->events )->toBe( [ 'ap-ve-ai:ai.content_rewrite:invalid-input' ] );
		expect( $this->prompter->calls )->toBeEmpty();
	} );
} );
