/**
 * Barrel export for the H6 minimal inspector sidebars (#431).
 *
 * H7's UI rescope wires these into the `inspector-outlet` based on
 * the active section. They are intentionally importable in isolation
 * so the host's editor shell can consume them piecewise.
 */

export { TemplateSidebar } from './template-sidebar';
export { TemplatePartSidebar } from './template-part-sidebar';
export { PatternSidebar } from './pattern-sidebar';
export { GlobalStylesSidebar } from './global-styles-sidebar';
export { MenuSidebar } from './menu-sidebar';

export type {
    TemplateSidebarRecord,
    TemplatePartSidebarRecord,
    PatternSidebarRecord,
    GlobalStylesSidebarRecord,
    MenuSidebarRecord,
} from './types';
