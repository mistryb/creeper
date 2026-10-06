import { useState } from 'react';
import type { LandscapeReport, LandscapePoint } from '@/types';

/** The plot's drawing area, in viewBox units. */
const WIDTH = 560;
const HEIGHT = 360;
const PAD = { top: 16, right: 24, bottom: 44, left: 52 };

const plotWidth = WIDTH - PAD.left - PAD.right;
const plotHeight = HEIGHT - PAD.top - PAD.bottom;

const toX = (value: number) => PAD.left + (value / 10) * plotWidth;
const toY = (value: number) => PAD.top + plotHeight - (value / 10) * plotHeight;

/**
 * Every company on the two axes that best separate this market. You are a
 * filled ribbon mark and competitors hollow ink ones, so the difference is
 * carried by shape as well as colour; every point is labelled directly, and
 * hovering one gives its coordinates.
 */
export function PositioningMap({
    map,
}: {
    map: NonNullable<LandscapeReport['map']>;
}) {
    const [hovered, setHovered] = useState<LandscapePoint | null>(null);

    // Draw yourself last, so your mark sits on top of any overlap.
    const points = [...map.points].sort(
        (a, b) => Number(a.is_you) - Number(b.is_you),
    );

    return (
        <figure className="space-y-3">
            <div className="flex flex-wrap items-center gap-4 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <svg aria-hidden width="10" height="10">
                        <circle cx="5" cy="5" r="4" className="fill-ribbon" />
                    </svg>
                    You
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <svg aria-hidden width="10" height="10">
                        <circle
                            cx="5"
                            cy="5"
                            r="3.5"
                            className="fill-card stroke-ink"
                            strokeWidth="1.5"
                        />
                    </svg>
                    Competitors
                </span>
            </div>

            <div className="relative">
                <svg
                    viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                    className="h-auto w-full"
                    role="img"
                    aria-label={`Positioning map: ${map.x_axis} across, ${map.y_axis} up`}
                >
                    {[0, 5, 10].map((tick) => (
                        <g key={tick} className="stroke-rule">
                            <line
                                x1={toX(tick)}
                                x2={toX(tick)}
                                y1={PAD.top}
                                y2={PAD.top + plotHeight}
                                strokeDasharray={tick === 5 ? '3 3' : undefined}
                            />
                            <line
                                x1={PAD.left}
                                x2={PAD.left + plotWidth}
                                y1={toY(tick)}
                                y2={toY(tick)}
                                strokeDasharray={tick === 5 ? '3 3' : undefined}
                            />
                        </g>
                    ))}

                    <text
                        x={PAD.left + plotWidth / 2}
                        y={HEIGHT - 10}
                        textAnchor="middle"
                        className="fill-muted-foreground font-mono text-[11px] tracking-[0.06em] uppercase"
                    >
                        {map.x_axis} →
                    </text>
                    <text
                        transform={`translate(16 ${PAD.top + plotHeight / 2}) rotate(-90)`}
                        textAnchor="middle"
                        className="fill-muted-foreground font-mono text-[11px] tracking-[0.06em] uppercase"
                    >
                        {map.y_axis} →
                    </text>
                    <text
                        x={PAD.left}
                        y={PAD.top + plotHeight + 16}
                        textAnchor="middle"
                        className="fill-muted-foreground font-mono text-[10px]"
                    >
                        low
                    </text>
                    <text
                        x={PAD.left + plotWidth}
                        y={PAD.top + plotHeight + 16}
                        textAnchor="middle"
                        className="fill-muted-foreground font-mono text-[10px]"
                    >
                        high
                    </text>

                    {points.map((point) => {
                        const x = toX(point.x);
                        const y = toY(point.y);
                        // Labels go right of the mark, unless that would run
                        // off the plot.
                        const labelLeft = point.x > 7.5;

                        return (
                            <g
                                key={point.subject}
                                onMouseEnter={() => setHovered(point)}
                                onMouseLeave={() => setHovered(null)}
                                onFocus={() => setHovered(point)}
                                onBlur={() => setHovered(null)}
                                tabIndex={0}
                                className="cursor-default outline-none"
                            >
                                <title>{`${point.name}: ${map.x_axis} ${point.x}/10, ${map.y_axis} ${point.y}/10`}</title>
                                {/* A generous, invisible hit target. */}
                                <circle
                                    cx={x}
                                    cy={y}
                                    r={14}
                                    fill="transparent"
                                />
                                <circle
                                    cx={x}
                                    cy={y}
                                    r={point.is_you ? 7 : 5.5}
                                    className={
                                        point.is_you
                                            ? 'fill-ribbon stroke-card'
                                            : 'fill-card stroke-ink'
                                    }
                                    strokeWidth={2}
                                />
                                <text
                                    x={labelLeft ? x - 11 : x + 11}
                                    y={y + 4}
                                    textAnchor={labelLeft ? 'end' : 'start'}
                                    className={
                                        point.is_you
                                            ? 'fill-ink text-[12px] font-semibold'
                                            : 'fill-ink-soft text-[12px]'
                                    }
                                >
                                    {point.name}
                                </text>
                            </g>
                        );
                    })}
                </svg>

                {hovered && (
                    <div
                        className="pointer-events-none absolute z-10 border border-ink bg-card px-2.5 py-1.5 text-xs shadow-sm"
                        style={{
                            left: `${(toX(hovered.x) / WIDTH) * 100}%`,
                            top: `${(toY(hovered.y) / HEIGHT) * 100}%`,
                            transform: 'translate(-50%, calc(-100% - 12px))',
                        }}
                    >
                        <p className="font-medium">{hovered.name}</p>
                        <p className="font-mono text-[0.6875rem] text-muted-foreground tabular-nums">
                            {map.x_axis} {hovered.x}/10 · {map.y_axis}{' '}
                            {hovered.y}/10
                        </p>
                    </div>
                )}
            </div>

            <figcaption className="text-xs text-muted-foreground">
                Placed by the model from everything Creeper knows. A judgement,
                not a measurement — the table above has the evidence.
            </figcaption>
        </figure>
    );
}
