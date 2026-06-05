/**
 * Separator — useDeprecatedOpacity hook.
 *
 * Ported from `@wordpress/block-library/src/separator/use-deprecated-opacity.js`
 * (v9.43.0). Behavior unchanged — typed as TypeScript.
 */

import { useEffect, useState } from '@wordpress/element';
import { usePrevious } from '@wordpress/compose';

type SetAttributes = (attrs: { opacity: string }) => void;

export default function useDeprecatedOpacity(
    opacity: string | undefined,
    currentColor: string | undefined,
    setAttributes: SetAttributes
): void {
    const [deprecatedOpacityWithNoColor, setDeprecatedOpacityWithNoColor] =
        useState(false);
    const previousColor = usePrevious(currentColor);

    useEffect(() => {
        if (opacity === 'css' && !currentColor && !previousColor) {
            setDeprecatedOpacityWithNoColor(true);
        }
    }, [currentColor, previousColor, opacity]);

    useEffect(() => {
        if (
            opacity === 'css' &&
            ((deprecatedOpacityWithNoColor && currentColor) ||
                (previousColor && currentColor !== previousColor))
        ) {
            setAttributes({ opacity: 'alpha-channel' });
            setDeprecatedOpacityWithNoColor(false);
        }
        // Upstream intentionally omits `opacity`, `setAttributes`,
        // `setDeprecatedOpacityWithNoColor` from this effect's deps so the
        // alpha-channel reset only fires when the user *actually* changes
        // the color via the picker — re-running on the gated `opacity`
        // flip would loop. Preserve that behavior here.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [deprecatedOpacityWithNoColor, currentColor, previousColor]);
}
