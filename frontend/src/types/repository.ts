export type Repository = {
    id: number;
    provider: "github" | "gitlab";
    fullName: string;
    defaultBranch?: string | null;
    installationId?: number | null;
};
