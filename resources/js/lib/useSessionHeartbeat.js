import { useEffect } from 'react';

const HEARTBEAT_MS = 4 * 60 * 1000;

export function useSessionHeartbeat(enabled) {
    useEffect(() => {
        if (!enabled) {
            return undefined;
        }

        let timer = null;

        const send = () => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            fetch(route('session.heartbeat'), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).catch(() => {});
        };

        const schedule = () => {
            if (timer) {
                clearInterval(timer);
            }
            timer = setInterval(send, HEARTBEAT_MS);
        };

        const onVisibility = () => {
            if (document.visibilityState === 'visible') {
                send();
                schedule();
            } else if (timer) {
                clearInterval(timer);
                timer = null;
            }
        };

        send();
        schedule();
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            if (timer) {
                clearInterval(timer);
            }
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [enabled]);
}
