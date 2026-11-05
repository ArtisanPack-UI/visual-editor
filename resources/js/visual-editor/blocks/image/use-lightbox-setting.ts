/**
 * The theme's lightbox settings for Image and Gallery blocks (#1046).
 *
 * theme.json can set `lightbox` under `settings.blocks` for `artisanpack/image`
 * or `core/image`, and the first wins. `enabled` is the default for images with
 * no setting of their own. `allowEditing: false` hides the editor controls. With
 * no setting at all, authors can use the feature and it starts off.
 */

import { useSettings } from '@wordpress/block-editor';

export interface LightboxSetting {
    readonly allowEditing: boolean;
    readonly enabled: boolean;
}

export function useLightboxSetting(): LightboxSetting {
    const [forkSetting, coreSetting] = useSettings(
        'blocks.artisanpack/image.lightbox',
        'blocks.core/image.lightbox'
    ) as readonly [Partial<LightboxSetting> | undefined, Partial<LightboxSetting> | undefined];

    return {
        allowEditing: (forkSetting?.allowEditing ?? coreSetting?.allowEditing) !== false,
        enabled: (forkSetting?.enabled ?? coreSetting?.enabled) === true,
    };
}
