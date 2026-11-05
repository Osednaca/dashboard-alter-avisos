import type { ReactNode } from 'react';
import { cn } from '@/Utils/cn';

export interface Column<T> {
    key: string;
    header: ReactNode;
    className?: string;
    headerClassName?: string;
    cell: (row: T) => ReactNode;
    /** Hide this column from the mobile card layout. */
    mobileHidden?: boolean;
    /** Override the label used on the mobile card. */
    mobileLabel?: string;
}

function isActionColumn<T>(column: Column<T>): boolean {
    return column.key === 'actions' || column.header === '';
}

function labelOf<T>(column: Column<T>): string {
    if (column.mobileLabel) return column.mobileLabel;
    return typeof column.header === 'string' ? column.header : '';
}

/**
 * Responsive data table. From `md` up it renders a classic table; on phones it
 * becomes a list of cards so no horizontal scrolling is required. The first
 * column becomes the card title and action columns are pinned to the corner.
 */
export function DataTable<T>({
    columns,
    rows,
    keyExtractor,
    empty,
    onRowClick,
    className,
    mobileTitle,
}: {
    columns: Array<Column<T>>;
    rows: T[];
    keyExtractor: (row: T) => string | number;
    empty?: ReactNode;
    onRowClick?: (row: T) => void;
    className?: string;
    mobileTitle?: (row: T) => ReactNode;
}) {
    const titleColumn = columns[0];
    const actionColumns = columns.filter(isActionColumn);
    const detailColumns = columns.filter(
        (column) => column !== titleColumn && !isActionColumn(column) && !column.mobileHidden,
    );

    return (
        <div className={cn(className)}>
            {/* Mobile: cards */}
            <div className="space-y-3 md:hidden">
                {rows.length === 0 ? (
                    <div>{empty}</div>
                ) : (
                    rows.map((row) => (
                        <div
                            key={keyExtractor(row)}
                            onClick={onRowClick ? () => onRowClick(row) : undefined}
                            className={cn(
                                'rounded-card border border-line bg-card p-4',
                                onRowClick && 'cursor-pointer transition-colors active:bg-surface',
                            )}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    {mobileTitle ? mobileTitle(row) : titleColumn.cell(row)}
                                </div>
                                {actionColumns.length > 0 ? (
                                    <div
                                        className="flex max-w-full flex-wrap items-center gap-1"
                                        onClick={(event) => event.stopPropagation()}
                                    >
                                        {actionColumns.map((column) => (
                                            <span key={column.key}>{column.cell(row)}</span>
                                        ))}
                                    </div>
                                ) : null}
                            </div>

                            {detailColumns.length > 0 ? (
                                <dl className="mt-3 space-y-2 border-t border-line pt-3">
                                    {detailColumns.map((column) => {
                                        const label = labelOf(column);
                                        if (!label) return null;

                                        return (
                                            <div key={column.key} className="flex flex-wrap items-start justify-between gap-3">
                                                <dt className="shrink-0 text-[11px] uppercase tracking-wide text-faint">
                                                    {label}
                                                </dt>
                                                <dd className="min-w-0 max-w-full break-words text-right text-sm text-fg">
                                                    {column.cell(row)}
                                                </dd>
                                            </div>
                                        );
                                    })}
                                </dl>
                            ) : null}
                        </div>
                    ))
                )}
            </div>

            {/* Desktop / tablet: table */}
            <div className="hidden overflow-x-auto md:block">
                <table className="w-full min-w-[720px] border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-line">
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    className={cn(
                                        'px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-faint',
                                        column.headerClassName,
                                    )}
                                >
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length} className="px-3 py-12">
                                    {empty}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr
                                    key={keyExtractor(row)}
                                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                                    className={cn(
                                        'border-b border-line/70 transition-colors last:border-0 hover:bg-surface/60',
                                        onRowClick && 'cursor-pointer',
                                    )}
                                >
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className={cn('px-3 py-3 align-middle text-fg', column.className)}
                                            onClick={isActionColumn(column) ? (event) => event.stopPropagation() : undefined}
                                        >
                                            {column.cell(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
