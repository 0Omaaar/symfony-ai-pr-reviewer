const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

export type Subscription = {
  id: number;
  repoFullName: string;
  repoId: string;
  installationId: string;
  branch: string;
  isActive: boolean;
  activatedAt: string | null;
  deactivatedAt: string | null;
  createdAt: string | null;
};

type SubscriptionListResponse = {
  data: Subscription[];
  status: string;
  count: number;
};

type SubscriptionMutationResponse = {
  data: Subscription | null;
  status: string;
  error?: string;
};

type SubscriptionRepoResponse = {
  data: Subscription[];
  status: string;
};

export async function getSubscriptions(): Promise<SubscriptionListResponse> {
  const res = await fetch(`${apiBaseUrl}/api/subscriptions`, {
    credentials: "include",
  });

  if (!res.ok) {
    throw new Error(`Failed to fetch subscriptions (${res.status})`);
  }

  return res.json();
}

export async function activateSubscription(
  repoFullName: string,
  repoId: string,
  installationId: string,
  branch: string
): Promise<SubscriptionMutationResponse> {
  const res = await fetch(`${apiBaseUrl}/api/subscriptions`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ repoFullName, repoId, installationId, branch }),
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    throw new Error(data?.error ?? `Failed to activate subscription (${res.status})`);
  }

  return res.json();
}

export async function deactivateSubscription(
  repoFullName: string,
  branch: string
): Promise<SubscriptionMutationResponse> {
  const res = await fetch(
    `${apiBaseUrl}/api/subscriptions/${encodeURIComponent(repoFullName)}/${encodeURIComponent(branch)}`,
    {
      method: "DELETE",
      credentials: "include",
    }
  );

  if (!res.ok) {
    throw new Error(`Failed to deactivate subscription (${res.status})`);
  }

  return res.json();
}

export async function getRepoSubscriptions(
  repoFullName: string
): Promise<SubscriptionRepoResponse> {
  const res = await fetch(
    `${apiBaseUrl}/api/subscriptions/repo/${encodeURIComponent(repoFullName)}`,
    {
      credentials: "include",
    }
  );

  if (!res.ok) {
    throw new Error(`Failed to fetch repo subscriptions (${res.status})`);
  }

  return res.json();
}
