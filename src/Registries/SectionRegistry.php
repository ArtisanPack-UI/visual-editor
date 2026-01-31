<?php

declare( strict_types=1 );

/**
 * Section Registry
 *
 * Manages the registration and retrieval of section types for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Registries
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\VisualEditor\Registries;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Section Registry class.
 *
 * Provides a centralized registry for managing section types in the visual editor.
 * Sections are pre-designed layouts that contain blocks.
 *
 * @since 1.0.0
 */
class SectionRegistry
{
	/**
	 * The registered sections.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array>
	 */
	protected array $sections = [];

	/**
	 * The section categories.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array>
	 */
	protected array $categories = [];

	/**
	 * Create a new SectionRegistry instance.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$this->categories = [
			'headers'      => [
				'name' => __( 'Headers' ),
				'icon' => 'fas.bars',
			],
			'content'      => [
				'name' => __( 'Content' ),
				'icon' => 'fas.file-lines',
			],
			'features'     => [
				'name' => __( 'Features' ),
				'icon' => 'fas.table-cells',
			],
			'social_proof' => [
				'name' => __( 'Social Proof' ),
				'icon' => 'fas.star',
			],
			'cta'          => [
				'name' => __( 'Call to Action' ),
				'icon' => 'fas.bullhorn',
			],
			'contact'      => [
				'name' => __( 'Contact' ),
				'icon' => 'fas.envelope',
			],
		];
	}

	/**
	 * Register a section type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type   The section type identifier.
	 * @param array  $config The section configuration.
	 *
	 * @throws InvalidArgumentException If the type or configuration is invalid.
	 *
	 * @return self
	 */
	public function register( string $type, array $config ): self
	{
		$this->validateRegistration( $type, $config );

		$this->sections[ $type ] = array_merge( [
			'name'             => $type,
			'description'      => '',
			'icon'             => 'fas.object-group',
			'category'         => 'content',
			'default_blocks'   => [],
			'default_settings' => [],
			'settings_schema'  => [],
		], $config );

		return $this;
	}

	/**
	 * Unregister a section type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The section type identifier.
	 *
	 * @return self
	 */
	public function unregister( string $type ): self
	{
		unset( $this->sections[ $type ] );

		return $this;
	}

	/**
	 * Check if a section type is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The section type identifier.
	 *
	 * @return bool
	 */
	public function has( string $type ): bool
	{
		return isset( $this->sections[ $type ] );
	}

	/**
	 * Get a section type configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The section type identifier.
	 *
	 * @return array|null
	 */
	public function get( string $type ): ?array
	{
		return $this->sections[ $type ] ?? null;
	}

	/**
	 * Get all registered sections.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	public function all(): Collection
	{
		return collect( $this->sections );
	}

	/**
	 * Get sections by category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category name.
	 *
	 * @return Collection
	 */
	public function getByCategory( string $category ): Collection
	{
		return $this->all()->filter( fn ( $section ) => ( $section['category'] ?? '' ) === $category );
	}

	/**
	 * Get all categories with their sections.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection
	 */
	public function getGroupedByCategory(): Collection
	{
		$sections = $this->all();

		return collect( $this->categories )->map( function ( $category, $key ) use ( $sections ) {
			return array_merge( $category, [
				'sections' => $sections->filter( fn ( $section ) => ( $section['category'] ?? '' ) === $key ),
			] );
		} )->filter( fn ( $category ) => $category['sections']->isNotEmpty() );
	}

	/**
	 * Register a section category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    The category key.
	 * @param array  $config The category configuration.
	 *
	 * @return self
	 */
	public function registerCategory( string $key, array $config ): self
	{
		$this->categories[ $key ] = array_merge( [
			'name' => $key,
			'icon' => 'fas.folder',
		], $config );

		return $this;
	}

	/**
	 * Get all categories.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function getCategories(): array
	{
		return $this->categories;
	}

	/**
	 * Get the default settings for a section type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The section type.
	 *
	 * @return array
	 */
	public function getDefaultSettings( string $type ): array
	{
		return $this->sections[ $type ]['default_settings'] ?? [];
	}

	/**
	 * Get the default blocks for a section type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The section type.
	 *
	 * @return array
	 */
	public function getDefaultBlocks( string $type ): array
	{
		return $this->sections[ $type ]['default_blocks'] ?? [];
	}

	/**
	 * Register the default sections.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function registerDefaults(): void
	{
		// Headers
		$this->register( 'hero', [
			'name'           => __( 'Hero' ),
			'description'    => __( 'Large hero section with headline and call to action' ),
			'icon'           => 'fas.object-group',
			'category'       => 'headers',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Welcome to Our Website' ), 'level' => 'h1' ] ],
				[ 'type' => 'text', 'content' => [ 'text' => __( 'We help small businesses succeed online.' ) ] ],
				[ 'type' => 'button_group', 'content' => [ 'buttons' => [
					[ 'text' => __( 'Get Started' ), 'url' => '#', 'style' => 'primary' ],
					[ 'text' => __( 'Learn More' ), 'url' => '#', 'style' => 'secondary' ],
				] ] ],
			],
			'default_settings' => [
				'background' => 'white',
				'padding'    => 'large',
				'alignment'  => 'center',
				'min_height' => '80vh',
			],
			'settings_schema' => [
				'background' => [ 'type' => 'background_picker' ],
				'padding'    => [ 'type' => 'select', 'options' => [ 'small', 'medium', 'large', 'none' ] ],
				'alignment'  => [ 'type' => 'select', 'options' => [ 'left', 'center', 'right' ] ],
				'min_height' => [ 'type' => 'select', 'options' => [ 'auto', '50vh', '80vh', '100vh' ] ],
			],
		] );

		$this->register( 'hero_with_image', [
			'name'           => __( 'Hero with Image' ),
			'description'    => __( 'Hero section with side image' ),
			'icon'           => 'fas.image',
			'category'       => 'headers',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Your Headline Here' ), 'level' => 'h1' ] ],
				[ 'type' => 'text', 'content' => [ 'text' => __( 'Supporting text that describes your value proposition.' ) ] ],
				[ 'type' => 'button_group', 'content' => [ 'buttons' => [
					[ 'text' => __( 'Get Started' ), 'url' => '#', 'style' => 'primary' ],
				] ] ],
				[ 'type' => 'image', 'content' => [ 'media_id' => null, 'alt' => '' ] ],
			],
			'default_settings' => [
				'background'     => 'white',
				'padding'        => 'large',
				'image_position' => 'right',
			],
		] );

		// Content
		$this->register( 'text', [
			'name'           => __( 'Text' ),
			'description'    => __( 'Simple text content section' ),
			'icon'           => 'fas.file-lines',
			'category'       => 'content',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Section Heading' ), 'level' => 'h2' ] ],
				[ 'type' => 'text', 'content' => [ 'text' => __( 'Add your content here.' ) ] ],
			],
			'default_settings' => [
				'background' => 'white',
				'padding'    => 'medium',
				'max_width'  => 'prose',
			],
		] );

		$this->register( 'text_with_image', [
			'name'           => __( 'Text with Image' ),
			'description'    => __( 'Text content with side image' ),
			'icon'           => 'fas.image',
			'category'       => 'content',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Section Heading' ), 'level' => 'h2' ] ],
				[ 'type' => 'text', 'content' => [ 'text' => __( 'Add your content here.' ) ] ],
				[ 'type' => 'image', 'content' => [ 'media_id' => null, 'alt' => '' ] ],
			],
			'default_settings' => [
				'background'     => 'white',
				'padding'        => 'large',
				'image_position' => 'right',
			],
		] );

		// Features
		$this->register( 'features', [
			'name'           => __( 'Features' ),
			'description'    => __( 'Highlight key features or benefits' ),
			'icon'           => 'fas.table-cells',
			'category'       => 'features',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Why Choose Us' ), 'level' => 'h2' ] ],
				[ 'type' => 'feature_item', 'content' => [ 'icon' => 'star', 'title' => __( 'Feature One' ), 'description' => __( 'Description of feature one.' ) ] ],
				[ 'type' => 'feature_item', 'content' => [ 'icon' => 'shield', 'title' => __( 'Feature Two' ), 'description' => __( 'Description of feature two.' ) ] ],
				[ 'type' => 'feature_item', 'content' => [ 'icon' => 'clock', 'title' => __( 'Feature Three' ), 'description' => __( 'Description of feature three.' ) ] ],
			],
			'default_settings' => [
				'background' => 'gray-50',
				'padding'    => 'large',
				'columns'    => 3,
			],
			'settings_schema' => [
				'background' => [ 'type' => 'background_picker' ],
				'padding'    => [ 'type' => 'select', 'options' => [ 'small', 'medium', 'large' ] ],
				'columns'    => [ 'type' => 'select', 'options' => [ 2, 3, 4 ] ],
			],
		] );

		$this->register( 'services', [
			'name'           => __( 'Services' ),
			'description'    => __( 'Showcase your services' ),
			'icon'           => 'fas.briefcase',
			'category'       => 'features',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Our Services' ), 'level' => 'h2' ] ],
				[ 'type' => 'service_card', 'content' => [ 'title' => __( 'Service One' ), 'description' => __( 'Description of service one.' ), 'icon' => 'briefcase' ] ],
				[ 'type' => 'service_card', 'content' => [ 'title' => __( 'Service Two' ), 'description' => __( 'Description of service two.' ), 'icon' => 'cog' ] ],
				[ 'type' => 'service_card', 'content' => [ 'title' => __( 'Service Three' ), 'description' => __( 'Description of service three.' ), 'icon' => 'chart' ] ],
			],
			'default_settings' => [
				'background' => 'white',
				'padding'    => 'large',
				'columns'    => 3,
			],
		] );

		// Social Proof
		$this->register( 'testimonials', [
			'name'           => __( 'Testimonials' ),
			'description'    => __( 'Customer testimonials slider or grid' ),
			'icon'           => 'fas.comments',
			'category'       => 'social_proof',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'What Our Customers Say' ), 'level' => 'h2' ] ],
				[ 'type' => 'testimonial', 'content' => [ 'quote' => __( 'Great service!' ), 'author' => __( 'John Doe' ), 'title' => __( 'CEO' ), 'image' => null ] ],
				[ 'type' => 'testimonial', 'content' => [ 'quote' => __( 'Highly recommend!' ), 'author' => __( 'Jane Smith' ), 'title' => __( 'Manager' ), 'image' => null ] ],
				[ 'type' => 'testimonial', 'content' => [ 'quote' => __( 'Exceeded expectations!' ), 'author' => __( 'Bob Wilson' ), 'title' => __( 'Owner' ), 'image' => null ] ],
			],
			'default_settings' => [
				'background' => 'gray-50',
				'padding'    => 'large',
				'layout'     => 'grid',
			],
		] );

		$this->register( 'stats', [
			'name'           => __( 'Statistics' ),
			'description'    => __( 'Display key statistics' ),
			'icon'           => 'fas.chart-bar',
			'category'       => 'social_proof',
			'default_blocks' => [
				[ 'type' => 'stat_item', 'content' => [ 'value' => '100+', 'label' => __( 'Clients' ) ] ],
				[ 'type' => 'stat_item', 'content' => [ 'value' => '500+', 'label' => __( 'Projects' ) ] ],
				[ 'type' => 'stat_item', 'content' => [ 'value' => '10+', 'label' => __( 'Years' ) ] ],
				[ 'type' => 'stat_item', 'content' => [ 'value' => '24/7', 'label' => __( 'Support' ) ] ],
			],
			'default_settings' => [
				'background' => 'primary',
				'padding'    => 'medium',
			],
		] );

		// CTA
		$this->register( 'cta', [
			'name'           => __( 'Call to Action' ),
			'description'    => __( 'Call to action banner' ),
			'icon'           => 'fas.bullhorn',
			'category'       => 'cta',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Ready to Get Started?' ), 'level' => 'h2' ] ],
				[ 'type' => 'text', 'content' => [ 'text' => __( 'Contact us today for a free consultation.' ) ] ],
				[ 'type' => 'button_group', 'content' => [ 'buttons' => [
					[ 'text' => __( 'Contact Us' ), 'url' => '#', 'style' => 'primary' ],
				] ] ],
			],
			'default_settings' => [
				'background' => 'primary',
				'padding'    => 'large',
				'alignment'  => 'center',
			],
		] );

		// Contact
		$this->register( 'contact', [
			'name'           => __( 'Contact' ),
			'description'    => __( 'Contact section with form' ),
			'icon'           => 'fas.envelope',
			'category'       => 'contact',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Get in Touch' ), 'level' => 'h2' ] ],
				[ 'type' => 'text', 'content' => [ 'text' => __( 'We\'d love to hear from you.' ) ] ],
				[ 'type' => 'form', 'content' => [ 'form_id' => null ] ],
				[ 'type' => 'global_content', 'content' => [ 'key' => 'contact_info' ] ],
			],
			'default_settings' => [
				'background' => 'white',
				'padding'    => 'large',
				'layout'     => 'split',
			],
		] );

		$this->register( 'faq', [
			'name'           => __( 'FAQ' ),
			'description'    => __( 'Frequently asked questions' ),
			'icon'           => 'fas.circle-question',
			'category'       => 'contact',
			'default_blocks' => [
				[ 'type' => 'heading', 'content' => [ 'text' => __( 'Frequently Asked Questions' ), 'level' => 'h2' ] ],
				[ 'type' => 'faq_item', 'content' => [ 'question' => __( 'Question one?' ), 'answer' => __( 'Answer to question one.' ) ] ],
				[ 'type' => 'faq_item', 'content' => [ 'question' => __( 'Question two?' ), 'answer' => __( 'Answer to question two.' ) ] ],
				[ 'type' => 'faq_item', 'content' => [ 'question' => __( 'Question three?' ), 'answer' => __( 'Answer to question three.' ) ] ],
			],
			'default_settings' => [
				'background' => 'white',
				'padding'    => 'large',
			],
		] );
	}

	/**
	 * Validate section registration parameters.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type   The section type identifier.
	 * @param array  $config The section configuration.
	 *
	 * @throws InvalidArgumentException If the type or configuration is invalid.
	 *
	 * @return void
	 */
	protected function validateRegistration( string $type, array $config ): void
	{
		if ( '' === trim( $type ) ) {
			throw new InvalidArgumentException( __( 'Section type cannot be empty.' ) );
		}

		if ( !preg_match( '/^[a-zA-Z0-9_-]+$/', $type ) ) {
			throw new InvalidArgumentException(
				__( 'Section type ":type" contains invalid characters. Only alphanumeric characters, hyphens, and underscores are allowed.', [
					'type' => $type,
				] ),
			);
		}

		if ( isset( $config['category'] ) && !isset( $this->categories[ $config['category'] ] ) ) {
			throw new InvalidArgumentException(
				__( 'Section category ":category" is not registered. Register it first with registerCategory().', [
					'category' => $config['category'],
				] ),
			);
		}
	}
}
