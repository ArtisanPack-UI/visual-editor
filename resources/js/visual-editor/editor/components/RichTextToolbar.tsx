import {
    useCallback,
    useEffect,
    useRef,
    useState,
    useSyncExternalStore,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import { useStore } from 'zustand';
import type { Editor } from '@tiptap/react';
import {
    faBold,
    faItalic,
    faLink,
    faListOl,
    faListUl,
} from '@fortawesome/free-solid-svg-icons';
import { useEditorStore } from '../primitives';
import {
    getBlockEditor,
    subscribeBlockEditors,
} from '../blocks/shared/blockEditorRegistry';
import { getBlock } from '../registry';
import {
    HEADING_LEVELS,
    normalizeHeadingLevel,
} from '../blocks/heading';
import { normalizeOrdered } from '../blocks/list';
import { Icon } from './Icon';

export interface RichTextToolbarProps {
    className?: string;
}

export function RichTextToolbar({ className }: RichTextToolbarProps) {
    const store = useEditorStore();
    const selectedClientId = useStore(store, (state) => state.selection.clientId);
    const block = useStore(store, (state) => {
        if (state.selection.clientId === null) {
            return undefined;
        }
        return state.blocks.find((b) => b.clientId === state.selection.clientId);
    });

    const editor = useSyncExternalStore(
        subscribeBlockEditors,
        () => (selectedClientId ? getBlockEditor(selectedClientId) : null),
        () => null
    );

    if (!selectedClientId || !block || !editor) {
        return null;
    }

    // Show toolbar for any block that has a Tiptap editor registered
    // (i.e. it's a rich-text block). No hardcoded block name checks.

    const blockDefinition = getBlock(block.name);
    const levelSchema = blockDefinition?.attributes?.level;
    const orderedSchema = blockDefinition?.attributes?.ordered;
    const hasLevelAttribute = levelSchema !== undefined && levelSchema.type === 'number';
    const hasOrderedAttribute = orderedSchema !== undefined && orderedSchema.type === 'boolean';

    return (
        <div
            className={['ve-rich-text-toolbar', className].filter(Boolean).join(' ')}
            role="toolbar"
            aria-label="Text formatting"
            data-ve-rich-text-toolbar=""
        >
            {hasLevelAttribute ? (
                <HeadingLevelSwitcher
                    clientId={block.clientId}
                    level={normalizeHeadingLevel(block.attributes.level)}
                />
            ) : null}
            {hasOrderedAttribute ? (
                <ListTypeSwitcher
                    clientId={block.clientId}
                    ordered={normalizeOrdered(block.attributes.ordered)}
                />
            ) : null}
            <ToolbarButton
                label="Bold"
                isActive={editor.isActive('bold')}
                onClick={() => editor.chain().focus().toggleBold().run()}
                testId="ve-toolbar-bold"
            >
                <Icon icon={faBold} />
            </ToolbarButton>
            <ToolbarButton
                label="Italic"
                isActive={editor.isActive('italic')}
                onClick={() => editor.chain().focus().toggleItalic().run()}
                testId="ve-toolbar-italic"
            >
                <Icon icon={faItalic} />
            </ToolbarButton>
            <LinkControl editor={editor} />
        </div>
    );
}

interface HeadingLevelSwitcherProps {
    clientId: string;
    level: number;
}

function HeadingLevelSwitcher({ clientId, level }: HeadingLevelSwitcherProps) {
    const store = useEditorStore();

    return (
        <label className="ve-rich-text-toolbar__heading-level">
            <span className="ve-sr-only">Heading level</span>
            <select
                value={level}
                aria-label="Heading level"
                data-testid="ve-toolbar-heading-level"
                onChange={(event) => {
                    const nextLevel = normalizeHeadingLevel(Number(event.target.value));
                    store.getState().updateBlockAttributes(clientId, { level: nextLevel });
                }}
            >
                {HEADING_LEVELS.map((value) => (
                    <option key={value} value={value}>
                        H{value}
                    </option>
                ))}
            </select>
        </label>
    );
}

interface ListTypeSwitcherProps {
    clientId: string;
    ordered: boolean;
}

function ListTypeSwitcher({ clientId, ordered }: ListTypeSwitcherProps) {
    const store = useEditorStore();

    return (
        <div className="ve-rich-text-toolbar__list-type" role="group" aria-label="List type">
            <ToolbarButton
                label="Bulleted list"
                isActive={!ordered}
                onClick={() => {
                    store.getState().updateBlockAttributes(clientId, { ordered: false });
                }}
                testId="ve-toolbar-list-unordered"
            >
                <Icon icon={faListUl} />
            </ToolbarButton>
            <ToolbarButton
                label="Numbered list"
                isActive={ordered}
                onClick={() => {
                    store.getState().updateBlockAttributes(clientId, { ordered: true });
                }}
                testId="ve-toolbar-list-ordered"
            >
                <Icon icon={faListOl} />
            </ToolbarButton>
        </div>
    );
}

interface ToolbarButtonProps {
    label: string;
    isActive?: boolean;
    onClick: () => void;
    children: ReactNode;
    testId?: string;
}

function ToolbarButton({ label, isActive, onClick, children, testId }: ToolbarButtonProps) {
    return (
        <button
            type="button"
            className={[
                've-rich-text-toolbar__button',
                isActive ? 've-rich-text-toolbar__button--is-active' : null,
            ]
                .filter(Boolean)
                .join(' ')}
            aria-label={label}
            aria-pressed={isActive}
            data-testid={testId}
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

interface LinkControlProps {
    editor: Editor;
}

function LinkControl({ editor }: LinkControlProps) {
    const [open, setOpen] = useState(false);
    const [href, setHref] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
    const isActive = editor.isActive('link');

    const syncHref = useCallback(() => {
        const current = editor.getAttributes('link').href;
        setHref(typeof current === 'string' ? current : '');
    }, [editor]);

    useEffect(() => {
        if (!open) {
            return;
        }
        syncHref();
        const frame = requestAnimationFrame(() => inputRef.current?.focus());
        return () => cancelAnimationFrame(frame);
    }, [open, syncHref]);

    const applyLink = useCallback(() => {
        const trimmed = href.trim();
        if (trimmed.length === 0) {
            editor.chain().focus().unsetLink().run();
        } else {
            editor.chain().focus().extendMarkRange('link').setLink({ href: trimmed }).run();
        }
        setOpen(false);
    }, [editor, href]);

    const removeLink = useCallback(() => {
        editor.chain().focus().unsetLink().run();
        setHref('');
        setOpen(false);
    }, [editor]);

    const onKeyDown = useCallback(
        (event: KeyboardEvent<HTMLInputElement>) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyLink();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false);
            }
        },
        [applyLink]
    );

    return (
        <div className="ve-rich-text-toolbar__link" data-ve-link-control="">
            <ToolbarButton
                label={isActive ? 'Edit link' : 'Add link'}
                isActive={isActive}
                onClick={() => setOpen((value) => !value)}
                testId="ve-toolbar-link-toggle"
            >
                <Icon icon={faLink} />
            </ToolbarButton>
            {open ? (
                <div
                    className="ve-rich-text-toolbar__link-popover"
                    role="dialog"
                    aria-label="Link settings"
                    data-testid="ve-toolbar-link-popover"
                    onMouseDown={(event) => event.preventDefault()}
                >
                    <label className="ve-rich-text-toolbar__link-field">
                        <span className="ve-sr-only">URL</span>
                        <input
                            ref={inputRef}
                            type="url"
                            value={href}
                            placeholder="https://example.com"
                            aria-label="Link URL"
                            data-testid="ve-toolbar-link-input"
                            onChange={(event) => setHref(event.target.value)}
                            onKeyDown={onKeyDown}
                        />
                    </label>
                    <div className="ve-rich-text-toolbar__link-actions">
                        <button
                            type="button"
                            className="ve-rich-text-toolbar__button"
                            data-testid="ve-toolbar-link-apply"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={applyLink}
                        >
                            Apply
                        </button>
                        {isActive ? (
                            <button
                                type="button"
                                className="ve-rich-text-toolbar__button"
                                data-testid="ve-toolbar-link-remove"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={removeLink}
                            >
                                Remove
                            </button>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
