import type { InserterBlock } from '../inserter';

export interface SlashCommandPopoverProps {
    blocks: readonly InserterBlock[];
    selectedIndex: number;
    onSelect: (block: InserterBlock) => void;
    onHoverIndex?: (index: number) => void;
}

export function SlashCommandPopover({
    blocks,
    selectedIndex,
    onSelect,
    onHoverIndex,
}: SlashCommandPopoverProps) {
    return (
        <div
            className="ve-slash-command-popover rounded-box border border-base-300 bg-base-100 shadow-lg"
            aria-label="Slash command menu"
            data-testid="ve-slash-command-popover"
            onMouseDown={(event) => event.preventDefault()}
        >
            {blocks.length === 0 ? (
                <div
                    className="ve-slash-command-popover__empty p-3 text-sm text-base-content/70"
                    data-testid="ve-slash-command-empty"
                >
                    No matching blocks
                </div>
            ) : (
                <ul className="menu menu-sm p-0">
                    {blocks.map((block, index) => {
                        const isSelected = index === selectedIndex;
                        return (
                            <li
                                key={block.name}
                                data-testid={`ve-slash-command-item-${block.name}`}
                                data-selected={isSelected || undefined}
                                onMouseEnter={() => onHoverIndex?.(index)}
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    onSelect(block);
                                }}
                            >
                                <a className={isSelected ? 'active' : undefined}>
                                    <div className="flex flex-col items-start">
                                        <span className="font-semibold">{block.title}</span>
                                        {block.description ? (
                                            <span className="text-xs opacity-70">
                                                {block.description}
                                            </span>
                                        ) : null}
                                    </div>
                                </a>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
