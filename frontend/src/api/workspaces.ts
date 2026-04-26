const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

export type WorkspaceRepo = {
  id: number;
  repoFullName: string;
  repoId: string;
  installationId: string;
  createdAt: string | null;
};

export type Workspace = {
  id: number;
  name: string;
  description: string | null;
  repositories: WorkspaceRepo[];
  createdAt: string | null;
  updatedAt: string | null;
};

type WorkspaceListResponse = { data: Workspace[]; status: string };
type WorkspaceResponse = { data: Workspace; status: string };

export async function getWorkspaces(): Promise<WorkspaceListResponse> {
  const res = await fetch(`${apiBaseUrl}/api/workspaces`, { credentials: "include" });
  if (!res.ok) throw new Error(`Failed to fetch workspaces (${res.status})`);
  return res.json();
}

export async function getWorkspace(id: number): Promise<WorkspaceResponse> {
  const res = await fetch(`${apiBaseUrl}/api/workspaces/${id}`, { credentials: "include" });
  if (!res.ok) throw new Error(`Failed to fetch workspace (${res.status})`);
  return res.json();
}

export async function createWorkspace(name: string, description?: string): Promise<WorkspaceResponse> {
  const res = await fetch(`${apiBaseUrl}/api/workspaces`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name, description: description ?? null }),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error((body as { error?: string }).error ?? `Failed to create workspace (${res.status})`);
  }
  return res.json();
}

export async function updateWorkspace(id: number, patch: { name?: string; description?: string | null }): Promise<WorkspaceResponse> {
  const res = await fetch(`${apiBaseUrl}/api/workspaces/${id}`, {
    method: "PATCH",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(patch),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error((body as { error?: string }).error ?? `Failed to update workspace (${res.status})`);
  }
  return res.json();
}

export async function deleteWorkspace(id: number): Promise<void> {
  const res = await fetch(`${apiBaseUrl}/api/workspaces/${id}`, {
    method: "DELETE",
    credentials: "include",
  });
  if (!res.ok) throw new Error(`Failed to delete workspace (${res.status})`);
}

export type WorkspaceRepoInput = {
  repoFullName: string;
  repoId: string;
  installationId: string;
};

export async function setWorkspaceRepositories(id: number, repositories: WorkspaceRepoInput[]): Promise<WorkspaceResponse> {
  const res = await fetch(`${apiBaseUrl}/api/workspaces/${id}/repositories`, {
    method: "PUT",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ repositories }),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error((body as { error?: string }).error ?? `Failed to update workspace repositories (${res.status})`);
  }
  return res.json();
}
