import { useEffect, useState } from "react";

type SetValue<T> = T | ((val: T) => T);

function useLocalStorage<T>(
    key: string,
    initialValue: T,
): [T, (value: SetValue<T>) => void] {
    // State to store our value
    const [storedValue, setStoredValue] = useState<T>(() => {
        if (typeof window === "undefined") {
            return initialValue; // Fallback for SSR
        }

        try {
            const item = window.localStorage.getItem(key);
            return item ? JSON.parse(item) : initialValue;
        } catch (error) {
            console.error("Error reading from localStorage", error);
            return initialValue;
        }
    });

    // Effect to update local storage when the state changes
    useEffect(() => {
        if (typeof window === "undefined") {
            return; // Do nothing during SSR
        }

        try {
            const valueToStore =
                typeof storedValue === "function"
                    ? (storedValue as (val: T) => T)(storedValue)
                    : storedValue;

            window.localStorage.setItem(key, JSON.stringify(valueToStore));
        } catch (error) {
            console.error("Error writing to localStorage", error);
        }
    }, [key, storedValue]);

    return [storedValue, setStoredValue];
}

export default useLocalStorage;
