import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'enku-theme';

/** Light/dark toggle persisted in localStorage (best effort). */
export function useTheme() {
    const [dark, setDark] = useState<boolean>(() => {
        if (typeof document === 'undefined') return false;
        return document.documentElement.classList.contains('dark');
    });

    useEffect(() => {
        document.documentElement.classList.toggle('dark', dark);

        try {
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        } catch {
            // Storage can be unavailable (private mode); the toggle still works for this session.
        }
    }, [dark]);

    const toggle = useCallback(() => setDark((value) => !value), []);

    return { dark, toggle };
}
