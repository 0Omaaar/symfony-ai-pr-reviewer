const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

export async function deleteAccount() {
    const res = await fetch(`${apiBaseUrl}/api/account`, {
        method: "DELETE",
        credentials: "include",
    });

    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data?.error ?? `Request failed with status ${res.status}`);
    }

    return res.json();
}

export async function removeInstallation(installationId) {
    const res = await fetch(`${apiBaseUrl}/api/account/installations/${installationId}`, {
        method: "DELETE",
        credentials: "include",
    });

    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data?.error ?? `Request failed with status ${res.status}`);
    }

    return res.json();
}

export async function getNotificationPreferences() {
    const res = await fetch(`${apiBaseUrl}/api/account/notification-preferences`, {
        method: "GET",
        credentials: "include",
    });

    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data?.error ?? `Request failed with status ${res.status}`);
    }

    return res.json();
}

export async function updateNotificationPreferences(preferences) {
    const res = await fetch(`${apiBaseUrl}/api/account/notification-preferences`, {
        method: "PATCH",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(preferences),
    });

    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data?.error ?? `Request failed with status ${res.status}`);
    }

    return res.json();
}

export async function updateNotifications(enabled) {
    const res = await fetch(`${apiBaseUrl}/api/account/notifications`, {
        method: "PATCH",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email_notifications_enabled: enabled }),
    });

    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data?.error ?? `Request failed with status ${res.status}`);
    }

    return res.json();
}
