{{--
 * Template Settings Panel
 *
 * Displays template metadata fields (name, slug, type, content type)
 * inside the template editor sidebar's Template tab.
 *
 * Expects these Alpine store properties on $store.editor:
 *   - templateSettings.name
 *   - templateSettings.slug
 *   - templateSettings.type
 *   - templateSettings.contentType
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	x-data="{
		get settings() {
			return Alpine.store( 'editor' )?.templateSettings || {};
		},

		update( field, value ) {
			const store = Alpine.store( 'editor' );
			if ( ! store || ! store.templateSettings ) return;
			store.templateSettings[ field ] = value;
			store.markDirty();
			store._dispatchChange();
			document.dispatchEvent( new CustomEvent( 've-template-settings-change', {
				detail: { field, value, settings: JSON.parse( JSON.stringify( store.templateSettings ) ) },
				bubbles: true,
			} ) );
		},
	}"
	class="flex flex-col gap-4"
>
	<h3 class="text-sm font-semibold text-base-content">
		{{ __( 'visual-editor::ve.template_settings_title' ) }}
	</h3>

	{{-- Template Name --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.template_name' ) }}
		</label>
		<input
			type="text"
			class="input input-sm input-bordered w-full"
			:value="settings.name || ''"
			x-on:change="update( 'name', $event.target.value )"
		/>
	</div>

	{{-- Template Slug --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.template_slug' ) }}
		</label>
		<input
			type="text"
			class="input input-sm input-bordered w-full font-mono text-xs"
			:value="settings.slug || ''"
			x-on:change="update( 'slug', $event.target.value )"
		/>
	</div>

	{{-- Template Type --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.template_type' ) }}
		</label>
		<select
			class="select select-sm select-bordered w-full"
			:value="settings.type || 'page'"
			x-on:change="update( 'type', $event.target.value )"
		>
			<option value="page">{{ __( 'visual-editor::ve.template_type_page' ) }}</option>
			<option value="archive">{{ __( 'visual-editor::ve.template_type_archive' ) }}</option>
			<option value="single">{{ __( 'visual-editor::ve.template_type_single' ) }}</option>
			<option value="search">{{ __( 'visual-editor::ve.template_type_search' ) }}</option>
			<option value="404">{{ __( 'visual-editor::ve.template_type_404' ) }}</option>
			<option value="custom">{{ __( 'visual-editor::ve.template_type_custom' ) }}</option>
		</select>
	</div>

	{{-- Content Type --}}
	<div class="flex flex-col gap-1">
		<label class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.template_content_type' ) }}
		</label>
		<input
			type="text"
			class="input input-sm input-bordered w-full"
			:value="settings.contentType || ''"
			x-on:change="update( 'contentType', $event.target.value )"
			placeholder="{{ __( 'visual-editor::ve.template_content_type_any' ) }}"
		/>
	</div>
</div>
