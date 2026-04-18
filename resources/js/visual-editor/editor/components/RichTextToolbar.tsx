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
import { Button, Input, Select } from '@artisanpack-ui/react/form';
import {
    faBold,
    faItalic,
    faLink,
    faListOl,
    faListUl,
    faStrikethrough,
    faUnderline,
    faAlignLeft,
    faAlignCenter,
    faAlignRight,
    faEllipsisVertical,
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
import { createClientId, type Block } from '../store';
import { PARAGRAPH_BLOCK_NAME } from '../blocks/paragraph';
import { Icon } from './Icon';

function findBlockInTree(blocks: Block[], clientId: string): Block | undefined {
    for (const block of blocks) {
        if (block.clientId === clientId) return block;
        const found = findBlockInTree(block.innerBlocks, clientId);
        if (found) return found;
    }
    return undefined;
}

function cloneBlockWithNewIds(block: Block): Block {
    return {
        ...block,
        clientId: createClientId(),
        innerBlocks: block.innerBlocks.map(cloneBlockWithNewIds),
    };
}

interface BlockLocation {
    parentClientId: string | null;
    index: number;
    block: Block;
}

function findBlockLocation(
    blocks: Block[],
    clientId: string,
    parentClientId: string | null = null
): BlockLocation | null {
    for (let i = 0; i < blocks.length; i++) {
        if (blocks[i].clientId === clientId) {
            return { parentClientId, index: i, block: blocks[i] };
        }
        const nested = findBlockLocation(
            blocks[i].innerBlocks,
            clientId,
            blocks[i].clientId
        );
        if (nested) {
            return nested;
        }
    }
    return null;
}

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
        return findBlockInTree(state.blocks, state.selection.clientId);
    });

    const editor = useSyncExternalStore(
        subscribeBlockEditors,
        () => (selectedClientId ? getBlockEditor(selectedClientId) : null),
        () => null
    );

    if (!selectedClientId || !block || !editor) {
        return null;
    }

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

            <ToolbarDivider />

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
            <ToolbarButton
                label="Underline"
                isActive={editor.isActive('underline')}
                onClick={() => editor.chain().focus().toggleUnderline().run()}
                testId="ve-toolbar-underline"
            >
                <Icon icon={faUnderline} />
            </ToolbarButton>
            <ToolbarButton
                label="Strikethrough"
                isActive={editor.isActive('strike')}
                onClick={() => editor.chain().focus().toggleStrike().run()}
                testId="ve-toolbar-strikethrough"
            >
                <Icon icon={faStrikethrough} />
            </ToolbarButton>
            <LinkControl editor={editor} />

            <ToolbarDivider />

            <TextAlignControls editor={editor} />

            <ToolbarDivider />

            <MoreOptionsMenu clientId={block.clientId} />
        </div>
    );
}

function ToolbarDivider() {
    return <span className="ve-rich-text-toolbar__divider" aria-hidden="true" />;
}

interface TextAlignControlsProps {
    editor: Editor;
}

function TextAlignControls({ editor }: TextAlignControlsProps) {
    return (
        <div className="ve-rich-text-toolbar__align-group" role="group" aria-label="Text alignment">
            <ToolbarButton
                label="Align left"
                isActive={editor.isActive({ textAlign: 'left' })}
                onClick={() => editor.chain().focus().setTextAlign('left').run()}
                testId="ve-toolbar-align-left"
            >
                <Icon icon={faAlignLeft} />
            </ToolbarButton>
            <ToolbarButton
                label="Align center"
                isActive={editor.isActive({ textAlign: 'center' })}
                onClick={() => editor.chain().focus().setTextAlign('center').run()}
                testId="ve-toolbar-align-center"
            >
                <Icon icon={faAlignCenter} />
            </ToolbarButton>
            <ToolbarButton
                label="Align right"
                isActive={editor.isActive({ textAlign: 'right' })}
                onClick={() => editor.chain().focus().setTextAlign('right').run()}
                testId="ve-toolbar-align-right"
            >
                <Icon icon={faAlignRight} />
            </ToolbarButton>
        </div>
    );
}

interface MoreOptionsMenuProps {
    clientId: string;
}

function MoreOptionsMenu({ clientId }: MoreOptionsMenuProps) {
    const [open, setOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);
    const store = useEditorStore();

    useEffect(() => {
        if (!open) {
            return;
        }

        function handleClickOutside(event: MouseEvent) {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        function handleEscape(event: globalThis.KeyboardEvent) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEscape);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [open]);

    const handleCopy = useCallback(async () => {
        const editor = getBlockEditor(clientId);
        const text = editor?.state.doc.textContent ?? '';

        if (text && navigator.clipboard) {
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                // Clipboard API may be blocked; silent fallback
            }
        }

        setOpen(false);
    }, [clientId]);

    const handleCut = useCallback(async () => {
        const editor = getBlockEditor(clientId);
        const text = editor?.state.doc.textContent ?? '';

        if (text && navigator.clipboard) {
            try {
                await navigator.clipboard.writeText(text);
                if (editor) {
                    editor.chain().selectAll().deleteSelection().run();
                }
            } catch {
                // Clipboard API may be blocked; silent fallback
            }
        }

        setOpen(false);
    }, [clientId]);

    const handleDuplicate = useCallback(() => {
        const state = store.getState();
        const location = findBlockLocation(state.blocks, clientId);

        if (!location) {
            return;
        }

        const duplicate = cloneBlockWithNewIds(structuredClone(location.block));

        state.insertBlock(duplicate, {
            parentClientId: location.parentClientId,
            index: location.index + 1,
        });
        state.select(duplicate.clientId);
        setOpen(false);
    }, [store, clientId]);

    const handleAddBefore = useCallback(() => {
        const state = store.getState();
        const location = findBlockLocation(state.blocks, clientId);

        if (!location) {
            return;
        }

        const newBlock: Block = {
            clientId: createClientId(),
            name: PARAGRAPH_BLOCK_NAME,
            attributes: { content: '<p></p>' },
            innerBlocks: [],
        };

        state.insertBlock(newBlock, {
            parentClientId: location.parentClientId,
            index: location.index,
        });
        state.select(newBlock.clientId, 'start');
        setOpen(false);
    }, [store, clientId]);

    const handleAddAfter = useCallback(() => {
        const state = store.getState();
        const location = findBlockLocation(state.blocks, clientId);

        if (!location) {
            return;
        }

        const newBlock: Block = {
            clientId: createClientId(),
            name: PARAGRAPH_BLOCK_NAME,
            attributes: { content: '<p></p>' },
            innerBlocks: [],
        };

        state.insertBlock(newBlock, {
            parentClientId: location.parentClientId,
            index: location.index + 1,
        });
        state.select(newBlock.clientId, 'start');
        setOpen(false);
    }, [store, clientId]);

    const handleDelete = useCallback(() => {
        store.getState().removeBlock(clientId);
        setOpen(false);
    }, [store, clientId]);

    return (
        <div className="ve-rich-text-toolbar__more" ref={menuRef}>
            <ToolbarButton
                label="More options"
                onClick={() => setOpen((v) => !v)}
                testId="ve-toolbar-more-toggle"
            >
                <Icon icon={faEllipsisVertical} />
            </ToolbarButton>
            {open ? (
                <ul
                    className="ve-rich-text-toolbar__more-menu menu bg-base-100 rounded-box shadow-lg border border-base-300"
                    role="menu"
                    aria-label="Block options"
                    data-testid="ve-toolbar-more-menu"
                >
                    <MoreMenuItem label="Copy" shortcut="⌘C" onClick={handleCopy} />
                    <MoreMenuItem label="Cut" shortcut="⌘X" onClick={handleCut} />
                    <MoreMenuItem label="Duplicate" shortcut="⇧⌘D" onClick={handleDuplicate} />
                    <MoreMenuDivider />
                    <MoreMenuItem label="Add before" shortcut="⌥⌘T" onClick={handleAddBefore} />
                    <MoreMenuItem label="Add after" shortcut="⌥⌘Y" onClick={handleAddAfter} />
                    <MoreMenuDivider />
                    <MoreMenuItem label="Copy styles" onClick={() => setOpen(false)} disabled />
                    <MoreMenuItem label="Paste styles" onClick={() => setOpen(false)} disabled />
                    <MoreMenuDivider />
                    <MoreMenuItem label="Group" onClick={() => setOpen(false)} disabled />
                    <MoreMenuItem label="Lock" onClick={() => setOpen(false)} disabled />
                    <MoreMenuItem label="Rename" onClick={() => setOpen(false)} disabled />
                    <MoreMenuItem label="Hide" onClick={() => setOpen(false)} disabled />
                    <MoreMenuItem label="Create pattern" onClick={() => setOpen(false)} disabled />
                    <MoreMenuDivider />
                    <MoreMenuItem
                        label="Delete"
                        shortcut="⌃⌥Z"
                        onClick={handleDelete}
                        destructive
                    />
                </ul>
            ) : null}
        </div>
    );
}

interface MoreMenuItemProps {
    label: string;
    shortcut?: string;
    onClick: () => void;
    disabled?: boolean;
    destructive?: boolean;
}

function MoreMenuItem({ label, shortcut, onClick, disabled, destructive }: MoreMenuItemProps) {
    return (
        <li
            role="none"
            className={destructive ? 've-more-menu__item--destructive' : undefined}
        >
            <button
                type="button"
                role="menuitem"
                className="flex w-full items-center justify-between"
                onMouseDown={(event) => event.preventDefault()}
                onClick={disabled ? undefined : onClick}
                disabled={disabled}
                data-testid={`ve-more-menu-${label.toLowerCase().replace(/\s+/g, '-')}`}
            >
                <span className="ve-more-menu__item-label">{label}</span>
                {shortcut ? (
                    <span className="ve-more-menu__item-shortcut">{shortcut}</span>
                ) : null}
            </button>
        </li>
    );
}

function MoreMenuDivider() {
    return <li className="ve-more-menu__divider" role="separator" />;
}

interface HeadingLevelSwitcherProps {
    clientId: string;
    level: number;
}

function HeadingLevelSwitcher({ clientId, level }: HeadingLevelSwitcherProps) {
    const store = useEditorStore();

    return (
        <Select
            value={level}
            aria-label="Heading level"
            data-testid="ve-toolbar-heading-level"
            className="select-sm"
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
        </Select>
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
    onClick?: () => void;
    children: ReactNode;
    testId?: string;
    [key: `aria-${string}`]: unknown;
    [key: `data-${string}`]: unknown;
}

function ToolbarButton({
    label,
    isActive,
    onClick,
    children,
    testId,
    ...rest
}: ToolbarButtonProps) {
    return (
        <Button
            size="sm"
            color={isActive ? 'primary' : 'ghost'}
            className="btn-square"
            aria-label={label}
            aria-pressed={isActive}
            data-testid={testId}
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
            icon={children}
            {...rest}
        />
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
                    className="ve-rich-text-toolbar__link-popover bg-base-100 rounded-box shadow-lg border border-base-300 p-3"
                    role="dialog"
                    aria-label="Link settings"
                    data-testid="ve-toolbar-link-popover"
                    onMouseDown={(event) => event.preventDefault()}
                >
                    <Input
                        ref={inputRef}
                        type="url"
                        value={href}
                        placeholder="https://example.com"
                        aria-label="Link URL"
                        data-testid="ve-toolbar-link-input"
                        onChange={(event) => setHref(event.target.value)}
                        onKeyDown={onKeyDown}
                    />
                    <div className="ve-rich-text-toolbar__link-actions">
                        <Button
                            size="sm"
                            data-testid="ve-toolbar-link-apply"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={applyLink}
                            label="Apply"
                        />
                        {isActive ? (
                            <Button
                                size="sm"
                                color="ghost"
                                data-testid="ve-toolbar-link-remove"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={removeLink}
                                label="Remove"
                            />
                        ) : null}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
