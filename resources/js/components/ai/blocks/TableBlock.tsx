import type { AiBlock } from '@/types/ai-blocks';

export function TableBlock({
    block,
}: {
    block: Extract<AiBlock, { type: 'table' }>;
}) {
    return (
        <table className="w-full text-left text-sm" data-test="ai-table-block">
            <thead>
                <tr>
                    {block.columns.map((column) => (
                        <th
                            key={column}
                            scope="col"
                            className="border-b px-2 py-1 font-medium"
                        >
                            {column}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {block.rows.map((row, index) => (
                    <tr key={index}>
                        {row.map((cell, cellIndex) =>
                            cellIndex === 0 ? (
                                <th
                                    key={cellIndex}
                                    scope="row"
                                    className="px-2 py-1 font-normal"
                                >
                                    {cell}
                                </th>
                            ) : (
                                <td key={cellIndex} className="px-2 py-1">
                                    {cell}
                                </td>
                            ),
                        )}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
