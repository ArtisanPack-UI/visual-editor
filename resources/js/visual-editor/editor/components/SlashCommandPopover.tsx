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
            className="ve-slash-command-popover"
            aria-label="Slash command menu"
            data-testid="ve-slash-command-popover"
            onMouseDown={(event) => event.preventDefault()}
        >
            {blocks.length === 0 ? (
                <div
                    className="ve-slash-command-popover__empty"
                    data-testid="ve-slash-command-empty"
                >
                    No matching blocks
                </div>
            ) : (
                <ul className="ve-slash-command-popover__list">
                    {blocks.map((block, index) => {
                        const isSelected = index === selectedIndex;
                        return (
                            <li
                                key={block.name}
                                className={[
                                    've-slash-command-popover__item',
                                    isSelected
                                        ? 've-slash-command-popover__item--is-selected'
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' ')}
                                data-testid={`ve-slash-command-item-${block.name}`}
                                data-selected={isSelected || undefined}
                                onMouseEnter={() => onHoverIndex?.(index)}
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    onSelect(block);
                                }}
                            >
                                <span className="ve-slash-command-popover__item-title">
                                    {block.title}
                                </span>
                                {block.description ? (
                                    <span className="ve-slash-command-popover__item-description">
                                        {block.description}
                                    </span>
                                ) : null}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
