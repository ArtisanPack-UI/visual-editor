/**
 * Navigation — save component.
 *
 * `core/navigation` persists its inner blocks in the saved markup, so the fork
 * delegates serialization to the registered `core/navigation` save to keep the
 * stored markup byte-identical (front-end output is produced server-side).
 * Phase I5 entity cluster (#413).
 */

import { createForkedEntitySave } from '../_shared/forked-entity-save';

export default createForkedEntitySave( 'core/navigation' );
