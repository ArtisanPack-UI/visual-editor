{{--
 * Pattern Settings Panel
 *
 * Displays pattern metadata fields (name, slug, category, description,
 * keywords, status, synced/standard toggle) inside the pattern editor
 * sidebar's Pattern tab.
 *
 * Expects these Alpine store properties on $store.editor:
 *   - patternSettings.name
 *   - patternSettings.slug
 *   - patternSettings.category
 *   - patternSettings.description
 *   - patternSettings.keywords
 *   - patternSettings.status
 *   - patternSettings.isSynced
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	x-data="{
		autoSlug: true,

		get settings() {
			return Alpine.store( 'editor' )?.patternSettings || {};
		},

		update( field, value ) {
			const store = Alpine.store( 'editor' );
			if ( ! store || ! store.patternSettings ) return;
			store.patternSettings[ field ] = value;

			if ( 'name' === field && this.autoSlug ) {
				const slug = value.normalize( 'NFD' )
					.replace( /[\u0300-\u036f]/g, '' )
					.toLowerCase()
					.replace( /[^a-z0-9\s-]/g, '' )
					.replace( /\s+/g, '-' )
					.replace( /-+/g, '-' )
					.replace( /^-|-$/g, '' );
				store.patternSettings.slug = slug;
			}

			store.markDirty();
			store._dispatchChange();
			document.dispatchEvent( new CustomEvent( 've-pattern-settings-change', {
				detail: { field, value, settings: JSON.parse( JSON.stringify( store.patternSettings ) ) },
				bubbles: true,
			} ) );
		},
	}"
	class="flex flex-col gap-4"
>
	<h3 class="text-sm font-semibold text-base-content">
		{{ __( 'visual-editor::ve.pattern_editor_settings_title' ) }}
	</h3>

	{{-- Pattern Name --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.pattern_editor_name' ) }}
		</label>
		<input
			type="text"
			class="input input-sm input-bordered w-full"
			:value="settings.name || ''"
			x-on:input="update( 'name', $event.target.value )"
		/>
	</div>

	{{-- Pattern Slug --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.pattern_editor_slug' ) }}
		</label>
		<input
			type="text"
			class="input input-sm input-bordered w-full font-mono text-xs"
			:value="settings.slug || ''"
			x-on:change="autoSlug = false; update( 'slug', $event.target.value )"
		/>
	</div>

	{{-- Category --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.pattern_editor_category' ) }}
		</label>
		@php
			$categories = veApplyFilters( 've.pattern-editor.categories', [
				'text'           => __( 'visual-editor::ve.pattern_category_text' ),
				'media'          => __( 'visual-editor::ve.pattern_category_media' ),
				'layout'         => __( 'visual-editor::ve.pattern_category_layout' ),
				'header'         => __( 'visual-editor::ve.pattern_category_header' ),
				'footer'         => __( 'visual-editor::ve.pattern_category_footer' ),
				'call-to-action' => __( 'visual-editor::ve.pattern_category_call_to_action' ),
				'gallery'        => __( 'visual-editor::ve.pattern_category_gallery' ),
				'testimonial'    => __( 'visual-editor::ve.pattern_category_testimonial' ),
			] );
		@endphp
		<select
			class="select select-sm select-bordered w-full"
			x-model="settings.category"
			x-on:change="update( 'category', settings.category )"
		>
			<option value="">{{ __( 'visual-editor::ve.pattern_editor_category_none' ) }}</option>
			@foreach ( $categories as $value => $label )
				<option value="{{ $value }}">{{ $label }}</option>
			@endforeach
		</select>
	</div>

	{{-- Description --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.pattern_editor_description' ) }}
		</label>
		<textarea
			class="textarea textarea-sm textarea-bordered w-full"
			rows="3"
			x-model.lazy="settings.description"
			x-on:change="update( 'description', settings.description )"
			placeholder="{{ __( 'visual-editor::ve.pattern_editor_description_placeholder' ) }}"
		></textarea>
	</div>

	{{-- Keywords --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.pattern_editor_keywords' ) }}
		</label>
		<input
			type="text"
			class="input input-sm input-bordered w-full"
			:value="settings.keywords || ''"
			x-on:change="update( 'keywords', $event.target.value )"
			placeholder="{{ __( 'visual-editor::ve.pattern_editor_keywords_placeholder' ) }}"
		/>
		<p class="text-xs text-base-content/40">
			{{ __( 'visual-editor::ve.pattern_editor_keywords_hint' ) }}
		</p>
	</div>

	{{-- Status --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.pattern_editor_status' ) }}
		</label>
		<select
			class="select select-sm select-bordered w-full"
			x-model="settings.status"
			x-on:change="update( 'status', settings.status )"
		>
			<option value="active">{{ __( 'visual-editor::ve.pattern_editor_status_active' ) }}</option>
			<option value="draft">{{ __( 'visual-editor::ve.pattern_editor_status_draft' ) }}</option>
		</select>
	</div>

	{{-- Synced / Standard Toggle --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.pattern_editor_sync_mode' ) }}
		</label>
		<div class="flex items-center gap-3">
			<input
				type="checkbox"
				class="toggle toggle-sm toggle-primary"
				:checked="settings.isSynced"
				x-on:change="update( 'isSynced', $event.target.checked )"
			/>
			<span
				class="text-xs text-base-content/70"
				x-text="settings.isSynced ? '{{ __( 'visual-editor::ve.pattern_editor_synced' ) }}' : '{{ __( 'visual-editor::ve.pattern_editor_standard' ) }}'"
			></span>
		</div>
		<p class="text-xs text-base-content/40">
			<template x-if="settings.isSynced">
				<span>{{ __( 'visual-editor::ve.pattern_editor_synced_hint' ) }}</span>
			</template>
			<template x-if="! settings.isSynced">
				<span>{{ __( 'visual-editor::ve.pattern_editor_standard_hint' ) }}</span>
			</template>
		</p>
	</div>

	{{-- Metadata (read-only) --}}
	<template x-if="settings.createdBy || settings.updatedAt">
		<div class="flex flex-col gap-2 pt-2 border-t border-base-300">
			<h4 class="text-xs font-medium text-base-content/60">
				{{ __( 'visual-editor::ve.pattern_editor_metadata' ) }}
			</h4>
			<template x-if="settings.createdBy">
				<p class="text-xs text-base-content/50">
					{{ __( 'visual-editor::ve.pattern_editor_created_by' ) }}:
					<span x-text="settings.createdBy"></span>
				</p>
			</template>
			<template x-if="settings.updatedAt">
				<p class="text-xs text-base-content/50">
					{{ __( 'visual-editor::ve.pattern_editor_last_modified' ) }}:
					<span x-text="settings.updatedAt"></span>
				</p>
			</template>
		</div>
	</template>
</div>
