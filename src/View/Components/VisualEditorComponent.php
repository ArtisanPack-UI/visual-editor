<?php

/**
 * VisualEditor Blade component.
 *
 * Renders the mount point for the React editor bound to a specific Eloquent
 * model. Reverse-resolves the model's class to a resource slug via the
 * `artisanpack.visual-editor.resources` map and emits the `data-resource`,
 * `data-id`, and `data-api-base` attributes the React bootstrap consumes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use RuntimeException;

class VisualEditorComponent extends Component
{
	public string $resource;

	public string $modelId;

	public string $apiBase;

	public ?string $initialTitle;

	public ?string $initialSlug;

	public ?string $initialStatus;

	public ?string $initialExcerpt;

	/**
	 * @var array{ id: int, url: string, alt?: string }|null
	 */
	public ?array $initialFeaturedImage;

	public int|string|null $initialAuthorId;

	public ?bool $initialCommentsOpen;

	/**
	 * @var array<int, array{ value: int|string, label: string }>|null
	 */
	public ?array $authorOptions;

	/**
	 * @var array{ excerpt?: bool, featuredImage?: bool, comments?: bool }|null
	 */
	public ?array $supports;

	public ?string $previewUrl;

	public function __construct(
		public Model $model,
		?string $resource = null,
		?string $apiBase = null,
		?string $initialTitle = null,
		?string $initialSlug = null,
		?string $initialStatus = null,
		?string $initialExcerpt = null,
		?array $initialFeaturedImage = null,
		int|string|null $initialAuthorId = null,
		?bool $initialCommentsOpen = null,
		?array $authorOptions = null,
		?array $supports = null,
		?string $previewUrl = null,
	) {
		$key = $model->getKey();

		if ( ! $model->exists || null === $key || '' === (string) $key ) {
			throw new RuntimeException( sprintf(
				'Visual editor cannot mount against an unsaved %s. Persist the model before rendering <x-visual-editor>.',
				$model::class
			) );
		}

		$this->resource             = $resource ?? $this->resolveResourceSlug( $model );
		$this->modelId              = (string) $key;
		$this->apiBase              = $apiBase ?? $this->defaultApiBase();
		$this->initialTitle         = $initialTitle;
		$this->initialSlug          = $initialSlug;
		$this->initialStatus        = $initialStatus;
		$this->initialExcerpt       = $initialExcerpt;
		$this->initialFeaturedImage = $initialFeaturedImage;
		$this->initialAuthorId      = $initialAuthorId;
		$this->initialCommentsOpen  = $initialCommentsOpen;
		$this->authorOptions        = $authorOptions;
		$this->supports             = $supports;
		$this->previewUrl           = $previewUrl;
	}

	public function render(): View
	{
		return view( 'visual-editor::components.editor' );
	}

	/**
	 * Looks up the resource slug for the given model class.
	 *
	 * @since 1.0.0
	 */
	protected function resolveResourceSlug( Model $model ): string
	{
		$map   = (array) config( 'artisanpack.visual-editor.resources', [] );
		$class = $model::class;

		foreach ( $map as $slug => $modelClass ) {
			if ( is_string( $slug ) && $modelClass === $class ) {
				return $slug;
			}
		}

		throw new RuntimeException( sprintf(
			'Model %s is not registered in config("artisanpack.visual-editor.resources"). '
			. 'Add it to the resources map or pass an explicit :resource="..." prop.',
			$class
		) );
	}

	/**
	 * Returns the default API base path for the editor.
	 *
	 * @since 1.0.0
	 */
	protected function defaultApiBase(): string
	{
		return (string) config( 'artisanpack.visual-editor.api.base', '/visual-editor/api' );
	}
}
