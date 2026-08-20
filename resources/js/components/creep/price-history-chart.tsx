import { useMemo } from 'react';
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatDateTime, formatPrice } from '@/lib/format';
import type { ProductSnapshot } from '@/types';

type Point = {
    at: number;
    price: number;
};

/**
 * Round the axis outwards to a sensible step, so the ticks read as prices
 * rather than as whatever the data happened to touch. Also gives a steady
 * price room to sit in the middle instead of pinning it to the top.
 */
function niceDomain(min: number, max: number): [number, number] {
    const padded = Math.max((max - min) * 0.15, 100);
    const low = min - padded;
    const high = max + padded;
    const step = Math.pow(10, Math.floor(Math.log10(high - low || 1)));

    return [
        Math.max(0, Math.floor(low / step) * step),
        Math.ceil(high / step) * step,
    ];
}

/**
 * One series, so no legend — the card title names it. Colour comes from a
 * theme token whose dark step is separately chosen, both validated for
 * contrast against their own surface.
 */
export function PriceHistoryChart({
    snapshots,
    currency,
}: {
    snapshots: ProductSnapshot[];
    currency: string | null;
}) {
    const points = useMemo<Point[]>(
        () =>
            snapshots
                .filter((snapshot) => snapshot.price_amount !== null)
                .map((snapshot) => ({
                    at: new Date(snapshot.captured_at).getTime(),
                    price: snapshot.price_amount as number,
                })),
        [snapshots],
    );

    if (points.length < 2) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg border border-dashed border-border">
                <p className="max-w-xs text-center text-sm text-muted-foreground">
                    A price history appears once Creeper has seen this product
                    more than once.
                </p>
            </div>
        );
    }

    const prices = points.map((point) => point.price);
    const [low, high] = niceDomain(Math.min(...prices), Math.max(...prices));

    return (
        <figure className="space-y-2">
            <div className="h-64 w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={points}
                        margin={{ top: 8, right: 16, bottom: 0, left: 8 }}
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--border)"
                            strokeDasharray="3 3"
                        />
                        <XAxis
                            dataKey="at"
                            type="number"
                            scale="time"
                            domain={['dataMin', 'dataMax']}
                            tickFormatter={(value: number) =>
                                new Intl.DateTimeFormat(undefined, {
                                    month: 'short',
                                    day: 'numeric',
                                }).format(new Date(value))
                            }
                            tickLine={false}
                            axisLine={false}
                            minTickGap={32}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 12,
                            }}
                        />
                        <YAxis
                            domain={[low, high]}
                            tickFormatter={(value: number) =>
                                formatPrice(value, currency)
                            }
                            tickLine={false}
                            axisLine={false}
                            width={72}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 12,
                            }}
                        />
                        <Tooltip
                            cursor={{
                                stroke: 'var(--muted-foreground)',
                                strokeWidth: 1,
                                strokeDasharray: '3 3',
                            }}
                            content={({ active, payload }) => {
                                if (!active || !payload?.length) {
                                    return null;
                                }

                                const point = payload[0].payload as Point;

                                return (
                                    <div className="rounded-lg border border-border bg-popover px-3 py-2 shadow-sm">
                                        <p className="text-sm font-medium text-popover-foreground">
                                            {formatPrice(point.price, currency)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDateTime(
                                                new Date(
                                                    point.at,
                                                ).toISOString(),
                                            )}
                                        </p>
                                    </div>
                                );
                            }}
                        />
                        <Line
                            type="monotone"
                            dataKey="price"
                            stroke="var(--creep-chart-line)"
                            strokeWidth={2}
                            dot={false}
                            activeDot={{ r: 5, strokeWidth: 2 }}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>

            <figcaption className="sr-only">
                Price history for this product.
            </figcaption>

            {/* The same numbers, reachable without reading the plot. */}
            <table className="sr-only">
                <caption>Price history</caption>
                <thead>
                    <tr>
                        <th scope="col">Captured</th>
                        <th scope="col">Price</th>
                    </tr>
                </thead>
                <tbody>
                    {points.map((point) => (
                        <tr key={point.at}>
                            <td>
                                {formatDateTime(
                                    new Date(point.at).toISOString(),
                                )}
                            </td>
                            <td>{formatPrice(point.price, currency)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
