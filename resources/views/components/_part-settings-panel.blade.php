{{--
 * Part Settings Panel
 *
 * Displays template part metadata fields (name, slug, area, description,
 * status) inside the template part editor sidebar's Part tab.
 *
 * Expects these Alpine store properties on $store.editor:
 *   - partSettings.name
 *   - partSettings.slug
 *   - partSettings.area
 *   - partSettings.description
 *   - partSettings.status
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
			return Alpine.store( 'editor' )?.partSettings || {};
		},

		update( field, value ) {
			const store = Alpine.store( 'editor' );
			if ( ! store || ! store.partSettings ) return;
			store.partSettings[ field ] = value;

			if ( 'name' === field && this.autoSlug ) {
				const slug = value.toLowerCase()
					.replace( /[^a-z0-9\s-]/g, '' )
					.replace( /\s+/g, '-' )
					.replace( /-+/g, '-' )
					.replace( /^-|-$/g, '' );
				store.partSettings.slug = slug;
			}

			store.markDirty();
			store._dispatchChange();
			document.dispatchEvent( new CustomEvent( 've-part-settings-change', {
				detail: { field, value, settings: JSON.parse( JSON.stringify( store.partSettings ) ) },
				bubbles: true,
			} ) );
		},
	}"
	class="flex flex-col gap-4"
>
	<h3 class="text-sm font-semibold text-base-content">
		{{ __( 'visual-editor::ve.part_editor_settings_title' ) }}
	</h3>

	{{-- Part Name --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.part_editor_name' ) }}
		</label>
		<input
			type="text"
			class="input input-sm input-bordered w-full"
			:value="settings.name || ''"
			x-on:input="update( 'name', $event.target.value )"
		/>
	</div>

	{{-- Part Slug --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.part_editor_slug' ) }}
		</label>
		<input
			type="text"
			class="input input-sm input-bordered w-full font-mono text-xs"
			:value="settings.slug || ''"
			x-on:focus="autoSlug = false"
			x-on:change="update( 'slug', $event.target.value )"
		/>
	</div>

	{{-- Area --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.part_editor_area' ) }}
		</label>
		<select
			class="select select-sm select-bordered w-full"
			x-model="settings.area"
			x-on:change="update( 'area', settings.area )"
		>
			<option value="header">{{ __( 'visual-editor::ve.template_part_area_header' ) }}</option>
			<option value="footer">{{ __( 'visual-editor::ve.template_part_area_footer' ) }}</option>
			<option value="sidebar">{{ __( 'visual-editor::ve.template_part_area_sidebar' ) }}</option>
			<option value="custom">{{ __( 'visual-editor::ve.template_part_area_custom' ) }}</option>
		</select>
	</div>

	{{-- Description --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.part_editor_description' ) }}
		</label>
		<textarea
			class="textarea textarea-sm textarea-bordered w-full"
			rows="3"
			x-model.lazy="settings.description"
			x-on:change="update( 'description', settings.description )"
			placeholder="{{ __( 'visual-editor::ve.part_editor_description_placeholder' ) }}"
		></textarea>
	</div>

	{{-- Status --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.part_editor_status' ) }}
		</label>
		<select
			class="select select-sm select-bordered w-full"
			x-model="settings.status"
			x-on:change="update( 'status', settings.status )"
		>
			<option value="active">{{ __( 'visual-editor::ve.part_editor_status_active' ) }}</option>
			<option value="draft">{{ __( 'visual-editor::ve.part_editor_status_draft' ) }}</option>
		</select>
	</div>

	{{-- Metadata (read-only) --}}
	<template x-if="settings.createdAt || settings.updatedAt">
		<div class="flex flex-col gap-2 pt-2 border-t border-base-300">
			<h4 class="text-xs font-medium text-base-content/60">
				{{ __( 'visual-editor::ve.part_editor_metadata' ) }}
			</h4>
			<template x-if="settings.createdBy">
				<p class="text-xs text-base-content/50">
					{{ __( 'visual-editor::ve.part_editor_created_by' ) }}:
					<span x-text="settings.createdBy"></span>
				</p>
			</template>
			<template x-if="settings.updatedAt">
				<p class="text-xs text-base-content/50">
					{{ __( 'visual-editor::ve.part_editor_last_modified' ) }}:
					<span x-text="settings.updatedAt"></span>
				</p>
			</template>
		</div>
	</template>
</div>
