import type { SVGAttributes } from 'react';

/**
 * The mark: six squares on a dot-matrix grid, reading as a bug mid-crawl. One
 * mark for the whole product — the landing header, the sidebar, the favicon —
 * so it stays a signature rather than three near-misses.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 16 16"
            xmlns="http://www.w3.org/2000/svg"
            fill="currentColor"
            {...props}
        >
            <rect x="2" y="2" width="3" height="3" />
            <rect x="7" y="2" width="3" height="3" />
            <rect x="11" y="6" width="3" height="3" />
            <rect x="6" y="6" width="3" height="3" />
            <rect x="2" y="11" width="3" height="3" />
            <rect x="7" y="11" width="3" height="3" />
        </svg>
    );
}
