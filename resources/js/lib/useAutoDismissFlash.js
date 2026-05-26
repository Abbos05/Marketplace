import { useEffect, useState } from 'react';

export const FLASH_DISMISS_MS = 5000;

/**
 * @param {{ success?: string, error?: string } | null | undefined} flash
 */
export function useAutoDismissFlash(flash, durationMs = FLASH_DISMISS_MS) {
    const [banner, setBanner] = useState(null);

    useEffect(() => {
        const text = flash?.error || flash?.success || flash?.info;
        if (!text) {
            setBanner(null);
            return;
        }
        setBanner({
            text,
            isError: Boolean(flash?.error),
        });
    }, [flash?.success, flash?.error, flash?.info]);

    useEffect(() => {
        if (!banner) {
            return undefined;
        }
        const timer = setTimeout(() => setBanner(null), durationMs);
        return () => clearTimeout(timer);
    }, [banner, durationMs]);

    const dismiss = () => setBanner(null);

    return { banner, dismiss };
}
