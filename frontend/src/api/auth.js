export async function fetchMe() {
    const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
    const res = await fetch(`${apiBaseUrl}/api/me`, {
        credentials: "include",
        cache: "no-store",
    });

    if (!res.ok) {
        throw new Error("Failed to fetch user info");
    }

    return await res.json();
}
