<?php

/**
 * Document Form Object.
 *
 * Handles validation and data management for document saving
 * in the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Forms
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * Form object for document persistence.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Forms
 *
 * @since      1.0.0
 */
class DocumentForm extends Form
{
	/**
	 * The document ID being edited.
	 *
	 * @since 1.0.0
	 *
	 * @var int|null
	 */
	public ?int $documentId = null;

	/**
	 * The block content of the document.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array<string, mixed>>
	 */
	#[Validate( 'required|array' )]
	public array $blocks = [];

	/**
	 * The document status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[Validate( 'required|in:draft,published,scheduled,pending' )]
	public string $documentStatus = 'draft';

	/**
	 * The scheduled publish date.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	#[Validate( 'nullable|date' )]
	public ?string $scheduledDate = null;
}
