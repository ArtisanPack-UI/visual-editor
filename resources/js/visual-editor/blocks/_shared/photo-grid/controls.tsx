/**
 * Photo Grid inspector controls (#594).
 *
 * Renders the Photo Grid sub-section that appears in the Dimensions
 * panel of `artisanpack/group`, `artisanpack/columns`, and
 * `artisanpack/grid`. Reads / writes the block's `photoGrid`
 * attribute and pulls defaults from `settings.artisanpack.photoGrid`
 * (theme.json → editorSettings).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react'
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components'
import { useSettings } from '@wordpress/block-editor'
import { __ } from '@wordpress/i18n'

import {
	PHOTO_GRID_ATTRIBUTE_DEFAULT,
	PHOTO_GRID_DEFAULTS,
	type PhotoGridAttribute,
	type PhotoGridDefaults,
	type PhotoGridObjectFit,
} from './types'
import { normaliseAspectRatio } from './wrapper'

interface PhotoGridControlsProps {
	photoGrid: PhotoGridAttribute | null | undefined
	onChange: ( next: PhotoGridAttribute ) => void
}

const ASPECT_RATIO_PRESETS: ReadonlyArray< { label: string; value: string } > = [
	{ label: __( 'Inherit container' ), value: '' },
	{ label: '1:1', value: '1/1' },
	{ label: '4:3', value: '4/3' },
	{ label: '3:2', value: '3/2' },
	{ label: '16:9', value: '16/9' },
	{ label: '3:4', value: '3/4' },
	{ label: '9:16', value: '9/16' },
	{ label: __( 'Custom…' ), value: '__custom__' },
]

const OBJECT_POSITION_CELLS: ReadonlyArray< { label: string; value: string } > = [
	{ label: __( 'Top left' ),     value: '0% 0%' },
	{ label: __( 'Top center' ),   value: '50% 0%' },
	{ label: __( 'Top right' ),    value: '100% 0%' },
	{ label: __( 'Center left' ),  value: '0% 50%' },
	{ label: __( 'Center' ),       value: '50% 50%' },
	{ label: __( 'Center right' ), value: '100% 50%' },
	{ label: __( 'Bottom left' ),  value: '0% 100%' },
	{ label: __( 'Bottom center' ),value: '50% 100%' },
	{ label: __( 'Bottom right' ), value: '100% 100%' },
]

function useThemePhotoGridDefaults(): PhotoGridDefaults {
	// `useSettings` reads the dotted path from `__experimentalFeatures`
	// surfaced via `editorSettings`. Falls back to package-level
	// defaults when theme.json does not define them.
	const [
		enable,
		defaultAspectRatio,
		defaultObjectFit,
		defaultObjectPosition,
	] = ( useSettings as any )(
		'artisanpack.photoGrid.enable',
		'artisanpack.photoGrid.defaultAspectRatio',
		'artisanpack.photoGrid.defaultObjectFit',
		'artisanpack.photoGrid.defaultObjectPosition',
	) as ReadonlyArray< unknown >

	return {
		enable: typeof enable === 'boolean' ? enable : PHOTO_GRID_DEFAULTS.enable,
		defaultAspectRatio:
			typeof defaultAspectRatio === 'string'
				? defaultAspectRatio
				: defaultAspectRatio === null
					? null
					: PHOTO_GRID_DEFAULTS.defaultAspectRatio,
		defaultObjectFit:
			defaultObjectFit === 'contain' ? 'contain' : 'cover',
		defaultObjectPosition:
			typeof defaultObjectPosition === 'string' && defaultObjectPosition !== ''
				? defaultObjectPosition
				: PHOTO_GRID_DEFAULTS.defaultObjectPosition,
	}
}

function resolveValue(
	photoGrid: PhotoGridAttribute | null | undefined,
	defaults: PhotoGridDefaults,
): PhotoGridAttribute {
	return {
		enabled: photoGrid?.enabled === true,
		aspectRatio:
			photoGrid?.aspectRatio !== undefined
				? photoGrid.aspectRatio
				: defaults.defaultAspectRatio,
		objectFit:
			photoGrid?.objectFit === 'contain' || photoGrid?.objectFit === 'cover'
				? photoGrid.objectFit
				: defaults.defaultObjectFit,
		objectPosition:
			typeof photoGrid?.objectPosition === 'string' && photoGrid.objectPosition !== ''
				? photoGrid.objectPosition
				: defaults.defaultObjectPosition,
	}
}

function isPresetRatio( value: string | null ): boolean {
	if ( value === null ) {
		return true
	}
	return ASPECT_RATIO_PRESETS.some( ( p ) => p.value === value )
}

export function PhotoGridControls( {
	photoGrid,
	onChange,
}: PhotoGridControlsProps ): ReactElement | null {
	const defaults = useThemePhotoGridDefaults()
	if ( ! defaults.enable ) {
		return null
	}

	const value         = resolveValue( photoGrid, defaults )
	const aspectIsPreset = isPresetRatio( value.aspectRatio )
	const ratioDropdownValue = value.aspectRatio === null
		? ''
		: ( aspectIsPreset ? value.aspectRatio : '__custom__' )

	function emit( next: Partial< PhotoGridAttribute > ): void {
		onChange( { ...PHOTO_GRID_ATTRIBUTE_DEFAULT, ...value, ...next } )
	}

	function onAspectRatioChange( nextDropdown: string ): void {
		if ( nextDropdown === '' ) {
			emit( { aspectRatio: null } )
			return
		}
		if ( nextDropdown === '__custom__' ) {
			// Seed the custom field with the current value (if it was
			// already a custom ratio) or fall back to the theme default.
			const seed = aspectIsPreset
				? defaults.defaultAspectRatio ?? '1/1'
				: value.aspectRatio ?? '1/1'
			emit( { aspectRatio: seed } )
			return
		}
		emit( { aspectRatio: nextDropdown } )
	}

	function onCustomAspectChange( raw: string ): void {
		// Accept the raw string while typing; normalisation runs at
		// render time inside `getPhotoGridWrapperProps`. Empty string
		// reverts to "inherit container".
		emit( { aspectRatio: raw === '' ? null : raw } )
	}

	const customAspectInvalid =
		! aspectIsPreset
		&& value.aspectRatio !== null
		&& normaliseAspectRatio( value.aspectRatio ) === null

	return (
		<PanelBody title={ __( 'Photo Grid' ) } initialOpen={ false }>
			<ToggleControl
				/* @ts-expect-error - upstream prop */
				__nextHasNoMarginBottom
				label={ __( 'Enable Photo Grid' ) }
				help={ __(
					'Force every image inside this container onto a uniform aspect ratio.',
				) }
				checked={ value.enabled }
				onChange={ ( enabled: boolean ) => emit( { enabled } ) }
			/>
			{ value.enabled && (
				<>
					<SelectControl
						/* @ts-expect-error - upstream prop */
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Aspect ratio' ) }
						value={ ratioDropdownValue }
						options={ ASPECT_RATIO_PRESETS.map( ( p ) => ( {
							label: p.label,
							value: p.value,
						} ) ) }
						onChange={ onAspectRatioChange }
					/>
					{ ratioDropdownValue === '__custom__' && (
						<TextControl
							/* @ts-expect-error - upstream prop */
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Custom ratio (W/H)' ) }
							placeholder="16/9"
							value={ value.aspectRatio ?? '' }
							onChange={ onCustomAspectChange }
							help={
								customAspectInvalid
									? __(
										'Invalid ratio. Enter a positive W/H value (e.g. 21/9).',
									)
									: undefined
							}
						/>
					) }
					<ToggleGroupControl
						/* @ts-expect-error - upstream prop */
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						isBlock
						label={ __( 'Object fit' ) }
						value={ value.objectFit }
						onChange={ ( next: string | number | undefined ) =>
							emit( {
								objectFit:
									next === 'contain'
										? 'contain'
										: ( 'cover' as PhotoGridObjectFit ),
							} )
						}
					>
						<ToggleGroupControlOption value="cover" label={ __( 'Cover' ) } />
						<ToggleGroupControlOption value="contain" label={ __( 'Contain' ) } />
					</ToggleGroupControl>
					<div
						className="ap-photo-grid-position-picker"
						role="radiogroup"
						aria-label={ __( 'Object position' ) }
						style={ {
							display: 'grid',
							gridTemplateColumns: 'repeat(3, 1fr)',
							gap: '4px',
							marginBlock: '12px',
						} }
					>
						{ OBJECT_POSITION_CELLS.map( ( cell ) => {
							const selected = value.objectPosition === cell.value
							return (
								<button
									key={ cell.value }
									type="button"
									role="radio"
									aria-checked={ selected }
									aria-label={ cell.label }
									title={ cell.label }
									onClick={ () =>
										emit( { objectPosition: cell.value } )
									}
									style={ {
										aspectRatio: '1 / 1',
										border: selected
											? '2px solid var(--wp-admin-theme-color, #007cba)'
											: '1px solid #ddd',
										background: selected ? '#007cba22' : '#fff',
										cursor: 'pointer',
										padding: 0,
									} }
								/>
							)
						} ) }
					</div>
					<TextControl
						/* @ts-expect-error - upstream prop */
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Object position (x% y%)' ) }
						value={ value.objectPosition }
						placeholder="50% 50%"
						onChange={ ( raw: string ) =>
							emit( {
								objectPosition: raw === '' ? '50% 50%' : raw,
							} )
						}
					/>
				</>
			) }
		</PanelBody>
	)
}
