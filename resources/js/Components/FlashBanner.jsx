import { usePage } from '@inertiajs/react';
import { useAutoDismissFlash, FLASH_DISMISS_MS } from '@/lib/useAutoDismissFlash';

/**
 * Глобальный flash из Inertia (success / error) с автоскрытием.
 */
export default function FlashBanner({
    classNamePrefix = 'main-flash',
    durationMs = FLASH_DISMISS_MS,
    flash: flashProp = null,
}) {
    const pageFlash = usePage().props.flash ?? {};
    const flash = flashProp ?? pageFlash;
    const { banner, dismiss } = useAutoDismissFlash(flash, durationMs);

    if (!banner) {
        return null;
    }

    const variant = banner.isError ? `${classNamePrefix}--error` : `${classNamePrefix}--success`;

    return (
        <div
            className={`${classNamePrefix} ${variant}`}
            role="alert"
            onClick={dismiss}
        >
            {banner.text}
        </div>
    );
}
