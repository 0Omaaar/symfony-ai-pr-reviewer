<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import {
  getWorkspaces,
  createWorkspace,
  updateWorkspace,
  deleteWorkspace,
  setWorkspaceRepositories,
  type Workspace,
  type WorkspaceRepoInput,
} from "@/api/workspaces";
import { getSubscriptions, type Subscription } from "@/api/subscriptions";

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";
const router = useRouter();

const workspaces = ref<Workspace[]>([]);
const isLoading = ref(true);
const loadError = ref("");

const showCreateForm = ref(false);
const createName = ref("");
const createDesc = ref("");
const createError = ref("");
const isCreating = ref(false);

const editingId = ref<number | null>(null);
const editName = ref("");
const editDesc = ref("");
const editError = ref("");
const isSavingEdit = ref(false);

const editingReposId = ref<number | null>(null);
const allRepos = ref<{ id: number; fullName: string; installationId: number }[]>([]);
const selectedRepoNames = ref<Set<string>>(new Set());
const isSavingRepos = ref(false);
const reposError = ref("");
const repoSearch = ref("");

const subscriptions = ref<Subscription[]>([]);

const editingWorkspace = computed(() =>
  workspaces.value.find((w) => w.id === editingId.value) ?? null
);

const editingReposWorkspace = computed(() =>
  workspaces.value.find((w) => w.id === editingReposId.value) ?? null
);

const filteredAllRepos = computed(() => {
  const q = repoSearch.value.trim().toLowerCase();
  if (!q) return allRepos.value;
  return allRepos.value.filter((r) => r.fullName.toLowerCase().includes(q));
});

function isMonitored(repoFullName: string): boolean {
  return subscriptions.value.some((s) => s.repoFullName === repoFullName && s.isActive);
}

async function load() {
  isLoading.value = true;
  loadError.value = "";
  try {
    const [wsRes, subRes] = await Promise.all([getWorkspaces(), getSubscriptions()]);
    workspaces.value = wsRes.data;
    subscriptions.value = subRes.data;
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : "Failed to load workspaces";
  } finally {
    isLoading.value = false;
  }
}

async function loadAllRepos() {
  try {
    const res = await fetch(`${apiBaseUrl}/api/github/repositories`, { credentials: "include" });
    if (!res.ok) return;
    const data = await res.json() as { repositories?: { id?: number; full_name?: string; installation_id?: number }[] };
    allRepos.value = (data.repositories ?? [])
      .filter((r) => typeof r.id === "number" && typeof r.full_name === "string")
      .map((r) => ({ id: r.id as number, fullName: r.full_name as string, installationId: (r.installation_id ?? 0) as number }));
  } catch { /* silent */ }
}

async function submitCreate() {
  const name = createName.value.trim();
  if (!name) { createError.value = "Name is required"; return; }
  isCreating.value = true;
  createError.value = "";
  try {
    const res = await createWorkspace(name, createDesc.value.trim() || undefined);
    workspaces.value.push(res.data);
    createName.value = "";
    createDesc.value = "";
    showCreateForm.value = false;
  } catch (e) {
    createError.value = e instanceof Error ? e.message : "Failed to create workspace";
  } finally {
    isCreating.value = false;
  }
}

function openEdit(ws: Workspace) {
  editingId.value = ws.id;
  editName.value = ws.name;
  editDesc.value = ws.description ?? "";
  editError.value = "";
}

function closeEdit() { editingId.value = null; }

async function submitEdit() {
  if (!editingId.value) return;
  const name = editName.value.trim();
  if (!name) { editError.value = "Name is required"; return; }
  isSavingEdit.value = true;
  editError.value = "";
  try {
    const res = await updateWorkspace(editingId.value, { name, description: editDesc.value.trim() || null });
    const idx = workspaces.value.findIndex((w) => w.id === editingId.value);
    if (idx !== -1) workspaces.value[idx] = res.data;
    closeEdit();
  } catch (e) {
    editError.value = e instanceof Error ? e.message : "Failed to update workspace";
  } finally {
    isSavingEdit.value = false;
  }
}

async function handleDelete(ws: Workspace) {
  if (!confirm(`Delete workspace "${ws.name}"? This cannot be undone.`)) return;
  try {
    await deleteWorkspace(ws.id);
    workspaces.value = workspaces.value.filter((w) => w.id !== ws.id);
  } catch (e) {
    alert(e instanceof Error ? e.message : "Failed to delete workspace");
  }
}

async function openRepoEditor(ws: Workspace) {
  editingReposId.value = ws.id;
  reposError.value = "";
  repoSearch.value = "";
  selectedRepoNames.value = new Set(ws.repositories.map((r) => r.repoFullName));
  if (allRepos.value.length === 0) await loadAllRepos();
}

function closeRepoEditor() { editingReposId.value = null; }

function toggleRepo(fullName: string) {
  const s = new Set(selectedRepoNames.value);
  if (s.has(fullName)) s.delete(fullName); else s.add(fullName);
  selectedRepoNames.value = s;
}

async function saveRepos() {
  if (!editingReposId.value) return;
  isSavingRepos.value = true;
  reposError.value = "";
  try {
    const repos: WorkspaceRepoInput[] = allRepos.value
      .filter((r) => selectedRepoNames.value.has(r.fullName))
      .map((r) => ({ repoFullName: r.fullName, repoId: String(r.id), installationId: String(r.installationId) }));
    const res = await setWorkspaceRepositories(editingReposId.value, repos);
    const idx = workspaces.value.findIndex((w) => w.id === editingReposId.value);
    if (idx !== -1) workspaces.value[idx] = res.data;
    closeRepoEditor();
  } catch (e) {
    reposError.value = e instanceof Error ? e.message : "Failed to save repositories";
  } finally {
    isSavingRepos.value = false;
  }
}

function openInDashboard(ws: Workspace) {
  void router.push({ name: "dashboard", query: { workspaceId: String(ws.id) } });
}

function openInTeamPRs(ws: Workspace) {
  void router.push({ name: "team-dashboard", query: { workspaceId: String(ws.id) } });
}

onMounted(load);
</script>
<template>
  <section class="workspaces-view">
    <header class="page-head">
      <div class="head-copy">
        <h1 class="title">Workspaces</h1>
        <p class="subtitle">Group repositories into named scopes to focus your dashboard views.</p>
      </div>
      <button class="btn-primary" @click="showCreateForm = !showCreateForm">
        {{ showCreateForm ? "Cancel" : "+ New workspace" }}
      </button>
    </header>

    <!-- Create form -->
    <div v-if="showCreateForm" class="create-card">
      <h2 class="card-title">New workspace</h2>
      <div class="form-row">
        <label class="form-label" for="ws-name">Name</label>
        <input id="ws-name" v-model="createName" class="form-input" placeholder="e.g. Backend team" maxlength="100" @keydown.enter="submitCreate" />
      </div>
      <div class="form-row">
        <label class="form-label" for="ws-desc">Description <span class="optional">(optional)</span></label>
        <input id="ws-desc" v-model="createDesc" class="form-input" placeholder="Short description" maxlength="255" @keydown.enter="submitCreate" />
      </div>
      <p v-if="createError" class="form-error">{{ createError }}</p>
      <div class="form-actions">
        <button class="btn-primary" :disabled="isCreating" @click="submitCreate">
          {{ isCreating ? "Creating…" : "Create workspace" }}
        </button>
        <button class="btn-ghost" @click="showCreateForm = false">Cancel</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="state-shell">
      <span class="loader"></span>
      <p>Loading workspaces…</p>
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="alert-error">{{ loadError }}</div>

    <!-- Empty state -->
    <div v-else-if="workspaces.length === 0 && !showCreateForm" class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="currentColor" width="32" height="32"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/></svg>
      </div>
      <h2 class="empty-title">No workspaces yet</h2>
      <p class="empty-desc">Workspaces let you focus on a subset of repositories in your dashboard and team PR views.</p>
      <button class="btn-primary" @click="showCreateForm = true">Create workspace</button>
    </div>

    <!-- Workspace cards -->
    <div v-else class="ws-grid">
      <div v-for="ws in workspaces" :key="ws.id" class="ws-card">
        <div class="ws-card-header">
          <div class="ws-card-title-row">
            <h2 class="ws-name">{{ ws.name }}</h2>
            <div class="ws-actions">
              <button class="icon-btn" title="Edit workspace" @click="openEdit(ws)">
                <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
              </button>
              <button class="icon-btn icon-btn-danger" title="Delete workspace" @click="handleDelete(ws)">
                <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
              </button>
            </div>
          </div>
          <p v-if="ws.description" class="ws-desc">{{ ws.description }}</p>
          <div class="ws-meta">
            <span class="ws-meta-chip">{{ ws.repositories.length }} {{ ws.repositories.length === 1 ? "repository" : "repositories" }}</span>
            <span class="ws-meta-chip monitored-chip">{{ ws.repositories.filter(r => isMonitored(r.repoFullName)).length }} monitored</span>
          </div>
        </div>

        <!-- Repo list -->
        <div v-if="ws.repositories.length > 0" class="ws-repo-list">
          <div v-for="repo in ws.repositories" :key="repo.id" class="ws-repo-row">
            <span class="ws-repo-name">{{ repo.repoFullName }}</span>
            <span v-if="isMonitored(repo.repoFullName)" class="badge-monitored">Monitored</span>
            <span v-else class="badge-unmonitored">Not monitored</span>
          </div>
        </div>
        <p v-else class="ws-no-repos">No repositories added yet.</p>

        <!-- Card actions -->
        <div class="ws-card-footer">
          <button class="btn-sm" @click="openRepoEditor(ws)">Edit repositories</button>
          <button class="btn-sm btn-sm-accent" @click="openInDashboard(ws)" title="Open in Dashboard">Dashboard</button>
          <button class="btn-sm btn-sm-accent" @click="openInTeamPRs(ws)" title="Open in Team PRs">Team PRs</button>
        </div>
      </div>
    </div>

    <!-- Edit workspace modal -->
    <div v-if="editingWorkspace" class="modal-backdrop" @click.self="closeEdit">
      <div class="modal">
        <h2 class="modal-title">Edit workspace</h2>
        <div class="form-row">
          <label class="form-label">Name</label>
          <input v-model="editName" class="form-input" maxlength="100" @keydown.enter="submitEdit" />
        </div>
        <div class="form-row">
          <label class="form-label">Description <span class="optional">(optional)</span></label>
          <input v-model="editDesc" class="form-input" maxlength="255" @keydown.enter="submitEdit" />
        </div>
        <p v-if="editError" class="form-error">{{ editError }}</p>
        <div class="form-actions">
          <button class="btn-primary" :disabled="isSavingEdit" @click="submitEdit">{{ isSavingEdit ? "Saving…" : "Save" }}</button>
          <button class="btn-ghost" @click="closeEdit">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Repo editor modal -->
    <div v-if="editingReposWorkspace" class="modal-backdrop" @click.self="closeRepoEditor">
      <div class="modal modal-wide">
        <h2 class="modal-title">Repositories in "{{ editingReposWorkspace.name }}"</h2>
        <p class="modal-hint">Select repositories to include. This does not change monitoring settings.</p>
        <input v-model="repoSearch" class="form-input" placeholder="Search repositories…" />
        <div class="repo-picker">
          <label
            v-for="repo in filteredAllRepos"
            :key="repo.id"
            class="repo-pick-row"
            :class="{ selected: selectedRepoNames.has(repo.fullName) }"
          >
            <input
              type="checkbox"
              :checked="selectedRepoNames.has(repo.fullName)"
              @change="toggleRepo(repo.fullName)"
            />
            <span class="repo-pick-name">{{ repo.fullName }}</span>
            <span v-if="isMonitored(repo.fullName)" class="badge-monitored">Monitored</span>
          </label>
          <p v-if="filteredAllRepos.length === 0" class="empty-pick">No repositories found.</p>
        </div>
        <p v-if="reposError" class="form-error">{{ reposError }}</p>
        <div class="form-actions">
          <button class="btn-primary" :disabled="isSavingRepos" @click="saveRepos">{{ isSavingRepos ? "Saving…" : "Save repositories" }}</button>
          <button class="btn-ghost" @click="closeRepoEditor">Cancel</button>
        </div>
      </div>
    </div>
  </section>
</template>
<style scoped>
.workspaces-view {
  display: grid;
  gap: 16px;
  max-width: 1200px;
  margin: 0 auto;
  padding-bottom: 24px;
}

.page-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 22px;
  border-radius: var(--radius-card);
  border: 1px solid var(--line);
  background: linear-gradient(135deg, var(--surface-gradient-a) 0%, var(--surface-gradient-b) 100%);
  box-shadow: var(--shadow-card);
}

.title { margin: 0; font-size: 1.7rem; font-weight: 800; color: var(--ink-strong); letter-spacing: -0.02em; }
.subtitle { margin: 6px 0 0; color: var(--ink-soft); font-size: 0.88rem; }

.btn-primary {
  padding: 9px 16px;
  border-radius: var(--radius-inner);
  border: none;
  background: var(--accent);
  color: var(--accent-foreground);
  font-weight: 700;
  font-size: 0.85rem;
  font-family: var(--font-sans);
  cursor: pointer;
  white-space: nowrap;
  transition: opacity 0.15s ease;
}
.btn-primary:hover:not(:disabled) { opacity: 0.88; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-ghost {
  padding: 9px 16px;
  border-radius: var(--radius-inner);
  border: 1px solid var(--line-strong);
  background: transparent;
  color: var(--ink-body);
  font-weight: 600;
  font-size: 0.85rem;
  font-family: var(--font-sans);
  cursor: pointer;
}
.btn-ghost:hover { background: var(--surface-soft); }

.btn-sm {
  padding: 6px 12px;
  border-radius: var(--radius-inner);
  border: 1px solid var(--line-strong);
  background: var(--surface);
  color: var(--ink-body);
  font-size: 0.78rem;
  font-weight: 600;
  font-family: var(--font-sans);
  cursor: pointer;
  transition: background 0.12s ease;
}
.btn-sm:hover { background: var(--surface-soft); }
.btn-sm-accent { background: var(--accent-light); color: var(--accent-hover); border-color: var(--accent-mid); }
.btn-sm-accent:hover { background: var(--accent-mid); }

.create-card {
  border: 1px solid var(--accent-mid);
  border-radius: var(--radius-card);
  background: var(--surface);
  padding: 20px 22px;
  box-shadow: var(--shadow-card);
  display: grid;
  gap: 12px;
}

.card-title { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ink-strong); }

.form-row { display: grid; gap: 5px; }
.form-label { font-size: 0.78rem; font-weight: 700; color: var(--ink-soft); }
.optional { font-weight: 500; color: var(--ink-faint); }
.form-input {
  border: 1px solid var(--line-strong);
  border-radius: var(--radius-inner);
  background: var(--surface-soft);
  color: var(--ink-strong);
  font-size: 0.88rem;
  font-family: var(--font-sans);
  padding: 8px 11px;
  outline: none;
  transition: border-color 0.15s ease;
}
.form-input:focus { border-color: var(--accent-mid); box-shadow: 0 0 0 3px var(--input-ring); }
.form-error { margin: 0; color: var(--tile-danger-ink); font-size: 0.82rem; font-weight: 600; }
.form-actions { display: flex; gap: 8px; }

.state-shell {
  min-height: 200px;
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 12px;
  color: var(--ink-soft);
  border: 1px solid var(--line);
  border-radius: var(--radius-card);
  background: var(--surface);
}
.loader {
  width: 28px; height: 28px; border-radius: 50%;
  border: 3px solid var(--line); border-top-color: var(--accent);
  animation: spin 0.75s linear infinite;
}

.alert-error {
  border: 1px solid var(--tile-danger-line);
  background: var(--tile-danger-bg);
  color: var(--tile-danger-ink);
  border-radius: var(--radius-inner);
  padding: 11px 14px;
  font-size: 0.88rem;
}

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 12px; padding: 56px 24px; text-align: center;
  border: 1px dashed var(--line-strong);
  border-radius: var(--radius-card);
  background: var(--surface);
}
.empty-icon {
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--surface-raised); border: 1px solid var(--line);
  color: var(--ink-faint); display: flex; align-items: center; justify-content: center;
}
.empty-title { margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--ink-strong); }
.empty-desc { margin: 0; font-size: 0.88rem; color: var(--ink-soft); max-width: 44ch; line-height: 1.6; }

.ws-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); }

.ws-card {
  border: 1px solid var(--line);
  border-radius: var(--radius-card);
  background: var(--surface);
  box-shadow: var(--shadow-card);
  display: flex; flex-direction: column;
  overflow: hidden;
}

.ws-card-header { padding: 16px 18px 12px; border-bottom: 1px solid var(--line); }

.ws-card-title-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
.ws-name { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ink-strong); }
.ws-desc { margin: 4px 0 8px; font-size: 0.82rem; color: var(--ink-soft); line-height: 1.5; }
.ws-meta { display: flex; gap: 6px; flex-wrap: wrap; }
.ws-meta-chip {
  display: inline-flex; align-items: center;
  border-radius: var(--radius-pill); padding: 2px 8px;
  font-size: 0.7rem; font-weight: 700;
  background: var(--surface-raised); color: var(--ink-soft); border: 1px solid var(--line);
}
.monitored-chip { background: var(--merged-bg); color: var(--merged-ink); border-color: var(--merged-line); }

.ws-actions { display: flex; gap: 4px; }
.icon-btn {
  width: 28px; height: 28px; border-radius: 8px;
  border: 1px solid var(--line); background: transparent;
  color: var(--ink-soft); cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background 0.12s ease, color 0.12s ease;
}
.icon-btn:hover { background: var(--surface-soft); color: var(--ink-body); }
.icon-btn-danger:hover { background: var(--tile-danger-bg); color: var(--tile-danger-ink); border-color: var(--tile-danger-line); }

.ws-repo-list { flex: 1; overflow-y: auto; max-height: 200px; }
.ws-repo-row {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 18px; border-bottom: 1px solid var(--line);
  font-size: 0.82rem;
}
.ws-repo-row:last-child { border-bottom: none; }
.ws-repo-name { flex: 1; color: var(--ink-body); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ws-no-repos { margin: 0; padding: 14px 18px; font-size: 0.82rem; color: var(--ink-faint); }

.badge-monitored {
  border-radius: var(--radius-pill); padding: 2px 7px;
  font-size: 0.68rem; font-weight: 700;
  background: var(--merged-bg); color: var(--merged-ink); border: 1px solid var(--merged-line);
  white-space: nowrap; flex-shrink: 0;
}
.badge-unmonitored {
  border-radius: var(--radius-pill); padding: 2px 7px;
  font-size: 0.68rem; font-weight: 700;
  background: var(--surface-raised); color: var(--ink-faint); border: 1px solid var(--line);
  white-space: nowrap; flex-shrink: 0;
}

.ws-card-footer {
  display: flex; gap: 6px; padding: 10px 14px;
  border-top: 1px solid var(--line); background: var(--surface-soft);
  flex-wrap: wrap;
}

/* Modal */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  z-index: 200; padding: 16px;
}
.modal {
  background: var(--surface); border: 1px solid var(--line);
  border-radius: var(--radius-card); box-shadow: var(--shadow-lg);
  padding: 24px; width: 100%; max-width: 440px;
  display: grid; gap: 14px; max-height: 90vh; overflow-y: auto;
}
.modal-wide { max-width: 560px; }
.modal-title { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--ink-strong); }
.modal-hint { margin: 0; font-size: 0.82rem; color: var(--ink-soft); }

.repo-picker {
  border: 1px solid var(--line); border-radius: var(--radius-inner);
  max-height: 280px; overflow-y: auto;
}
.repo-pick-row {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-bottom: 1px solid var(--line);
  cursor: pointer; transition: background 0.1s ease;
}
.repo-pick-row:last-child { border-bottom: none; }
.repo-pick-row:hover { background: var(--surface-soft); }
.repo-pick-row.selected { background: var(--accent-light); }
.repo-pick-name { flex: 1; font-size: 0.84rem; font-weight: 600; color: var(--ink-body); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.empty-pick { margin: 0; padding: 16px; text-align: center; color: var(--ink-faint); font-size: 0.84rem; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
