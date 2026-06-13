/**
 * Custom keyframe editor (#489).
 *
 * Site Editor → Styles → Animations sub-page that lets developers
 * author named `@keyframes` blocks and persist them into the Global
 * Styles JSON. The editor authors keyframes as a list of stops; each
 * stop edits a small allow-listed set of transform / opacity / filter
 * properties.
 *
 * The component is fully controlled — it never reads the canonical
 * store directly. The Site Editor host wires `value` + `onChange` to
 * `styles.custom.artisanpack.keyframes`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { CustomKeyframe, CustomKeyframeStop } from './types';

const ALLOWED_PROPERTIES: ( keyof CustomKeyframeStop )[] = [
	'transform',
	'opacity',
	'filter',
	'color',
	'background-color',
	'box-shadow',
];

const NAME_PATTERN = /^[a-z][a-z0-9_-]*$/i;
// Matches the backend `KeyframeRegistry::validateOne()` rule: 0–100
// with an optional `%` suffix. Surfacing the error inline prevents
// the save → reject → fix cycle the user would otherwise hit.
const STOP_AT_PATTERN = /^(0|[1-9]\d?|100)(%)?$/;

export interface CustomKeyframeEditorProps {
	value: CustomKeyframe[];
	onChange: ( next: CustomKeyframe[] ) => void;
	/** Names the editor must not allow because they collide with built-ins. */
	reservedNames: string[];
}

function emptyStop( at: string ): CustomKeyframeStop {
	return { at };
}

function patchKeyframe(
	list: CustomKeyframe[],
	index: number,
	patch: Partial<CustomKeyframe>,
): CustomKeyframe[] {
	const next = list.slice();
	next[ index ] = { ...next[ index ], ...patch };
	return next;
}

export function CustomKeyframeEditor( {
	value,
	onChange,
	reservedNames,
}: CustomKeyframeEditorProps ): JSX.Element {
	const reserved = new Set( reservedNames.map( ( n ) => n.toLowerCase() ) );

	function addKeyframe(): void {
		onChange( [
			...value,
			{
				name:  '',
				stops: [ emptyStop( '0%' ), emptyStop( '100%' ) ],
			},
		] );
	}

	function removeKeyframe( index: number ): void {
		onChange( value.filter( ( _, i ) => i !== index ) );
	}

	function setName( index: number, name: string ): void {
		onChange( patchKeyframe( value, index, { name } ) );
	}

	function setStop( index: number, stopIndex: number, patch: Partial<CustomKeyframeStop> ): void {
		const next = value.slice();
		const stops = next[ index ].stops.slice();
		stops[ stopIndex ] = { ...stops[ stopIndex ], ...patch };
		next[ index ] = { ...next[ index ], stops };
		onChange( next );
	}

	function addStop( index: number ): void {
		const next = value.slice();
		const stops = next[ index ].stops.slice();
		stops.splice( stops.length - 1, 0, emptyStop( '50%' ) );
		next[ index ] = { ...next[ index ], stops };
		onChange( next );
	}

	function removeStop( index: number, stopIndex: number ): void {
		const next = value.slice();
		if ( next[ index ].stops.length <= 2 ) {
			return;
		}
		const stops = next[ index ].stops.filter( ( _, i ) => i !== stopIndex );
		next[ index ] = { ...next[ index ], stops };
		onChange( next );
	}

	function nameError( name: string, index: number ): string | null {
		if ( '' === name ) {
			return __( 'Required.', 'visual-editor' );
		}
		if ( ! NAME_PATTERN.test( name ) ) {
			return __( 'Use letters, numbers, hyphens, or underscores.', 'visual-editor' );
		}
		if ( reserved.has( name.toLowerCase() ) ) {
			return __( 'This name is reserved by a built-in.', 'visual-editor' );
		}
		const duplicate = value.findIndex(
			( kf, i ) => i !== index && kf.name.toLowerCase() === name.toLowerCase()
		);
		if ( -1 !== duplicate ) {
			return __( 'Already used by another keyframe.', 'visual-editor' );
		}
		return null;
	}

	function stopAtError( at: string ): string | null {
		const trimmed = at.trim();
		if ( '' === trimmed ) {
			return __( 'Required.', 'visual-editor' );
		}
		if ( ! STOP_AT_PATTERN.test( trimmed ) ) {
			return __( 'Use a percentage from 0 to 100 (e.g. 50 or 50%).', 'visual-editor' );
		}
		return null;
	}

	return (
		<div className="ap-custom-keyframes">
			<p>
				{ __(
					'Author named @keyframes blocks. They become available in the entrance and continuous dropdowns.',
					'visual-editor'
				) }
			</p>

			{ value.map( ( keyframe, index ) => {
				const error = nameError( keyframe.name, index );
				return (
					<div key={ index } className="ap-custom-keyframes__row">
						<TextControl
							label={ __( 'Name', 'visual-editor' ) }
							value={ keyframe.name }
							onChange={ ( next: string ) => setName( index, next ) }
							help={ error ?? undefined }
						/>

						{ keyframe.stops.map( ( stop, stopIndex ) => (
							<fieldset key={ stopIndex } className="ap-custom-keyframes__stop">
								<legend>
									{ __( 'Stop', 'visual-editor' ) } { stopIndex + 1 }
								</legend>

								<TextControl
									label={ __( 'At', 'visual-editor' ) }
									value={ stop.at }
									onChange={ ( next: string ) => setStop( index, stopIndex, { at: next } ) }
									help={ stopAtError( stop.at ) ?? undefined }
								/>

								{ ALLOWED_PROPERTIES.map( ( property ) => (
									<TextControl
										key={ property }
										label={ property }
										value={ stop[ property ] ?? '' }
										onChange={ ( next: string ) =>
											setStop( index, stopIndex, { [ property ]: '' === next ? undefined : next } )
										}
									/>
								) ) }

								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => removeStop( index, stopIndex ) }
									disabled={ keyframe.stops.length <= 2 }
								>
									{ __( 'Remove stop', 'visual-editor' ) }
								</Button>
							</fieldset>
						) ) }

						<Button variant="secondary" onClick={ () => addStop( index ) }>
							{ __( '+ Add stop', 'visual-editor' ) }
						</Button>

						<Button variant="tertiary" isDestructive onClick={ () => removeKeyframe( index ) }>
							{ __( 'Delete keyframe', 'visual-editor' ) }
						</Button>
					</div>
				);
			} ) }

			<Button variant="primary" onClick={ addKeyframe }>
				{ __( '+ Add custom keyframe', 'visual-editor' ) }
			</Button>
		</div>
	);
}
