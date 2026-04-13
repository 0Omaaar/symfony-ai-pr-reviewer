const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const TOKEN_KEY = "admin_token";

export function getAdminToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setAdminToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearAdminToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

export function isAdminAuthenticated(): boolean {
  return getAdminToken() !== null;
}

function adminHeaders(): HeadersInit {
  const token = getAdminToken();
  return {
    "Content-Type": "application/json",
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

async function adminFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const res = await fetch(`${apiBaseUrl}${path}`, {
    ...init,
    headers: { ...adminHeaders(), ...(init.headers ?? {}) },
  });
  if (res.status === 401) {
    clearAdminToken();
    window.location.href = "/admin/login";
  }
  return res;
}

export async function adminLogin(email: string, password: string): Promise<{ token: string }> {
  const res = await fetch(`${apiBaseUrl}/api/admin/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email, password }),
  });
  if (!res.ok) throw new Error("Invalid credentials");
  return res.json();
}

export async function adminMe(): Promise<{ authenticated: boolean; role: string }> {
  const res = await adminFetch("/api/admin/auth/me");
  if (!res.ok) throw new Error("Not authenticated");
  return res.json();
}

export async function fetchAdminStats(): Promise<unknown> {
  const res = await adminFetch("/api/admin/stats");
  if (!res.ok) throw new Error("Failed to fetch stats");
  return res.json();
}

export async function fetchAdminUsers(params: {
  page?: number;
  pageSize?: number;
  search?: string;
  status?: string;
}): Promise<unknown> {
  const q = new URLSearchParams();
  if (params.page) q.set("page", String(params.page));
  if (params.pageSize) q.set("pageSize", String(params.pageSize));
  if (params.search) q.set("search", params.search);
  if (params.status) q.set("status", params.status);
  const res = await adminFetch(`/api/admin/users?${q}`);
  if (!res.ok) throw new Error("Failed to fetch users");
  return res.json();
}

export async function fetchAdminUser(id: number): Promise<unknown> {
  const res = await adminFetch(`/api/admin/users/${id}`);
  if (!res.ok) throw new Error("Failed to fetch user");
  return res.json();
}

export async function suspendAdminUser(id: number): Promise<unknown> {
  const res = await adminFetch(`/api/admin/users/${id}/suspend`, { method: "PATCH" });
  if (!res.ok) throw new Error("Failed to toggle suspension");
  return res.json();
}

export async function deleteAdminUser(id: number): Promise<void> {
  const res = await adminFetch(`/api/admin/users/${id}`, { method: "DELETE" });
  if (!res.ok) throw new Error("Failed to delete user");
}

export async function fetchAdminRepos(params: {
  page?: number;
  pageSize?: number;
  search?: string;
}): Promise<unknown> {
  const q = new URLSearchParams();
  if (params.page) q.set("page", String(params.page));
  if (params.pageSize) q.set("pageSize", String(params.pageSize));
  if (params.search) q.set("search", params.search);
  const res = await adminFetch(`/api/admin/repos?${q}`);
  if (!res.ok) throw new Error("Failed to fetch repos");
  return res.json();
}

export async function disconnectAdminRepo(id: number): Promise<void> {
  const res = await adminFetch(`/api/admin/repos/${id}`, { method: "DELETE" });
  if (!res.ok) throw new Error("Failed to disconnect repo");
}

export async function fetchAdminPullRequests(params: {
  page?: number;
  pageSize?: number;
  search?: string;
  date_from?: string;
  date_to?: string;
}): Promise<unknown> {
  const q = new URLSearchParams();
  if (params.page) q.set("page", String(params.page));
  if (params.pageSize) q.set("pageSize", String(params.pageSize));
  if (params.search) q.set("search", params.search);
  if (params.date_from) q.set("date_from", params.date_from);
  if (params.date_to) q.set("date_to", params.date_to);
  const res = await adminFetch(`/api/admin/pull-requests?${q}`);
  if (!res.ok) throw new Error("Failed to fetch pull requests");
  return res.json();
}

export async function fetchAdminNotifications(params: {
  page?: number;
  pageSize?: number;
}): Promise<unknown> {
  const q = new URLSearchParams();
  if (params.page) q.set("page", String(params.page));
  if (params.pageSize) q.set("pageSize", String(params.pageSize));
  const res = await adminFetch(`/api/admin/notifications?${q}`);
  if (!res.ok) throw new Error("Failed to fetch notifications");
  return res.json();
}

export async function fetchAdminLogs(params: {
  page?: number;
  pageSize?: number;
  search?: string;
}): Promise<unknown> {
  const q = new URLSearchParams();
  if (params.page) q.set("page", String(params.page));
  if (params.pageSize) q.set("pageSize", String(params.pageSize));
  if (params.search) q.set("search", params.search);
  const res = await adminFetch(`/api/admin/logs?${q}`);
  if (!res.ok) throw new Error("Failed to fetch logs");
  return res.json();
}

export function getAdminLogsExportUrl(): string {
  const token = getAdminToken();
  return `${apiBaseUrl}/api/admin/logs/export?token=${token ?? ""}`;
}

export async function fetchAdminSettings(): Promise<unknown> {
  const res = await adminFetch("/api/admin/settings");
  if (!res.ok) throw new Error("Failed to fetch settings");
  return res.json();
}

export async function updateAdminSettings(settings: Record<string, unknown>): Promise<unknown> {
  const res = await adminFetch("/api/admin/settings", {
    method: "PATCH",
    body: JSON.stringify(settings),
  });
  if (!res.ok) throw new Error("Failed to update settings");
  return res.json();
}

export async function clearWebhookEvents(): Promise<unknown> {
  const res = await adminFetch("/api/admin/settings/danger/clear-webhook-events", { method: "DELETE" });
  if (!res.ok) throw new Error("Failed to clear webhook events");
  return res.json();
}
