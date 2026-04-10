const AUTH_CACHE_KEY = "auth.isAuthenticated";

export async function fetchMe() {
    const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
    const res = await fetch(`${apiBaseUrl}/api/me`, {
        credentials: "include",
        cache: "no-store",
    });

    if (res.status === 401) {
        setCachedAuth(false);
        return { authenticated: false };
    }

    if (!res.ok) {
        throw new Error("Failed to fetch user info");
    }

    const data = await res.json();
    setCachedAuth(Boolean(data?.authenticated));

    return data;
}

export function getCachedAuth() {
    const cached = localStorage.getItem(AUTH_CACHE_KEY);
    if (cached === "1") return true;
    if (cached === "0") return false;
    return null;
}

export function setCachedAuth(isAuthenticated) {
    localStorage.setItem(AUTH_CACHE_KEY, isAuthenticated ? "1" : "0");
}

export function clearCachedAuth() {
    localStorage.removeItem(AUTH_CACHE_KEY);
}
