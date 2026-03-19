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
	public array $blocks = [];

	/**
	 * The document status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $documentStatus = 'draft';

	/**
	 * The scheduled publish date.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $scheduledDate = null;

	/**
	 * Key-value meta data from document panel fields.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	public array $meta = [];

	/**
	 * Get validation rules with conditional scheduledDate requirement.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function rules(): array
	{
		return [
			'blocks'         => 'required|array',
			'documentStatus' => 'required|in:draft,published,scheduled,pending',
			'scheduledDate'  => 'scheduled' === $this->documentStatus
				? 'required|date|after:now'
				: 'nullable|date',
			'meta'           => 'array',
			'meta.*'         => 'nullable|max:65535',
		];
	}
}
