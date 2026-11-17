/** How far one arrow press moves a column edge, and how narrow it may get. */
const RESIZE_STEP_PX = 16;
export const MINIMUM_WIDTH_PX = 80;

/**
 * The handle on a column's trailing edge.
 *
 * It is reachable by keyboard as well as by pointer, because a column that can
 * only be widened by dragging is a column someone who does not use a mouse
 * cannot read. The arrow keys move the same edge the drag does.
 */
export function ColumnResizer({
    id,
    width,
    onResize,
}: {
    id: string;
    width: number | null;
    onResize: (id: string, to: number) => void;
}) {
    return (
        <span
            role="separator"
            aria-label={`Resize column ${id}`}
            aria-orientation="vertical"
            aria-valuenow={width ?? MINIMUM_WIDTH_PX}
            aria-valuemin={MINIMUM_WIDTH_PX}
            tabIndex={0}
            data-test={`resize-${id}`}
            className="absolute inset-y-0 right-0 w-2 cursor-col-resize touch-none select-none hover:bg-border focus-visible:bg-ring"
            onKeyDown={(event) => {
                const step =
                    event.key === 'ArrowLeft'
                        ? -RESIZE_STEP_PX
                        : event.key === 'ArrowRight'
                          ? RESIZE_STEP_PX
                          : 0;

                if (step === 0) {
                    return;
                }

                event.preventDefault();

                onResize(id, (width ?? measure(event.currentTarget)) + step);
            }}
            onPointerDown={(event) => {
                const element = event.currentTarget;
                const from = width ?? measure(element);
                const startedAt = event.clientX;

                element.setPointerCapture(event.pointerId);

                const move = (moved: PointerEvent) =>
                    onResize(id, from + moved.clientX - startedAt);

                const stop = () => {
                    element.removeEventListener('pointermove', move);
                    element.removeEventListener('pointerup', stop);
                };

                element.addEventListener('pointermove', move);
                element.addEventListener('pointerup', stop);
            }}
        />
    );
}

/** The rendered width of the column a handle sits in. */
function measure(handle: HTMLElement): number {
    return (
        handle.parentElement?.getBoundingClientRect().width ?? MINIMUM_WIDTH_PX
    );
}
