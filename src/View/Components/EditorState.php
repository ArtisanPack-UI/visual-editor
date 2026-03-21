<?php

/**
 * Editor State Component.
 *
 * Initializes the central Alpine.js editor store that manages
 * block tree state, undo/redo history, editor mode, and save status.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Editor State component for managing all editor state via Alpine.js store.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class EditorState extends Component
{
	/**
	 * Valid editor modes.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const MODES = [
		'visual',
		'code',
		'template',
	];

	/**
	 * Valid device preview options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const DEVICES = [
		'desktop',
		'tablet',
		'mobile',
	];

	/**
	 * Valid save statuses.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const SAVE_STATUSES = [
		'saved',
		'unsaved',
		'saving',
		'error',
	];

	/**
	 * Valid document statuses.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const DOCUMENT_STATUSES = [
		'draft',
		'published',
		'scheduled',
		'pending',
	];

	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null  $id               Optional custom ID.
	 * @param array<mixed> $initialBlocks    Pre-populated block tree.
	 * @param int          $maxHistorySize   Maximum undo/redo history states.
	 * @param string       $mode             Editor mode: visual or code.
	 * @param bool         $showSidebar      Whether sidebar is initially visible.
	 * @param bool         $showInserter     Whether inserter is initially visible.
	 * @param string       $devicePreview    Initial device preview: desktop, tablet, or mobile.
	 * @param string       $saveStatus       Initial save status.
	 * @param bool         $autosave         Whether autosave is enabled.
	 * @param int          $autosaveInterval Seconds between autosaves.
	 * @param string       $documentStatus   Initial document status: draft, published, scheduled, or pending.
	 * @param string|null  $scheduledDate    Date/time string for scheduled publishing.
	 * @param array<mixed> $patterns         Available patterns for the pattern browser.
	 * @param array<mixed> $blockTransforms  Map of block type to available transform targets.
	 * @param array<mixed> $blockVariations  Map of block type to available variations.
	 * @param string       $defaultBlockType     The default block type used when adding blocks without an explicit type.
	 * @param array<mixed> $defaultInnerBlocksMap Map of block type to default inner blocks created on insertion.
	 * @param array<string, mixed> $initialMeta  Initial meta key-value pairs for document panel fields.
	 * @param array<int, array{name: string, slug: string, color: string}> $initialPalette Initial color palette entries for global styles.
	 * @param array{fontFamilies: array<string, string>, elements: array<string, array<string, string>>} $initialTypography Initial typography presets for global styles.
	 * @param array{scale: array<int, array{name: string, slug: string, value: string}>, blockGap: string, customSteps: array<int, array{name: string, slug: string, value: string}>} $initialSpacing Initial spacing scale for global styles.
	 */
	public function __construct(
		public ?string $id = null,
		public array $initialBlocks = [],
		public int $maxHistorySize = 50,
		public string $mode = 'visual',
		public bool $showSidebar = true,
		public bool $showInserter = false,
		public string $devicePreview = 'desktop',
		public string $saveStatus = 'saved',
		public bool $autosave = true,
		public int $autosaveInterval = 60,
		public string $documentStatus = 'draft',
		public ?string $scheduledDate = null,
		public array $patterns = [],
		public array $blockTransforms = [],
		public array $blockVariations = [],
		public string $defaultBlockType = 'paragraph',
		public array $defaultInnerBlocksMap = [],
		public array $initialMeta = [],
		public array $initialPalette = [],
		public array $initialTypography = [],
		public array $initialSpacing = [],
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->mode, self::MODES, true ) ) {
			$this->mode = 'visual';
		}

		if ( ! in_array( $this->devicePreview, self::DEVICES, true ) ) {
			$this->devicePreview = 'desktop';
		}

		if ( ! in_array( $this->saveStatus, self::SAVE_STATUSES, true ) ) {
			$this->saveStatus = 'saved';
		}

		if ( ! in_array( $this->documentStatus, self::DOCUMENT_STATUSES, true ) ) {
			$this->documentStatus = 'draft';
		}

		if ( $this->maxHistorySize < 1 ) {
			$this->maxHistorySize = 50;
		}

		if ( $this->autosaveInterval < 1 ) {
			$this->autosaveInterval = 60;
		}

		if ( '' === trim( $this->defaultBlockType ) ) {
			$this->defaultBlockType = 'paragraph';
		}

		if ( [] === $this->initialPalette ) {
			$this->initialPalette = app( 'visual-editor.color-palette' )->toStoreFormat();
		}

		if ( [] === $this->initialTypography ) {
			$this->initialTypography = app( 'visual-editor.typography-presets' )->toStoreFormat();
		}

		if ( [] === $this->initialSpacing ) {
			$this->initialSpacing = app( 'visual-editor.spacing-scale' )->toStoreFormat();
		}
	}

	/**
	 * Get the save statuses as an uppercase-keyed map for JavaScript.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function saveStatusMap(): array
	{
		return array_combine(
			array_map( 'strtoupper', self::SAVE_STATUSES ),
			self::SAVE_STATUSES,
		);
	}

	/**
	 * Get the document statuses as an uppercase-keyed map for JavaScript.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function documentStatusMap(): array
	{
		return array_combine(
			array_map( 'strtoupper', self::DOCUMENT_STATUSES ),
			self::DOCUMENT_STATUSES,
		);
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.0.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.editor-state' );
	}
}
