/**
 * Navigation — edit component.
 *
 * Delegates to the registered `core/navigation` edit so the fork inherits the
 * upstream + V1 editor surface untouched (see
 * `../_shared/forked-entity-edit.tsx`). Phase I5 entity cluster (#413).
 */

import { createForkedEntityEdit } from '../_shared/forked-entity-edit';

export default createForkedEntityEdit( 'core/navigation' );
