// Role-based screen access. The server issues roles + permissions (GET /mobile_api/me);
// the app shows exactly the screens those permissions allow, intersected with the admin's
// remote-config feature toggles. The API re-checks every request, so this is presentation only.
import type { Role, ScreenSpec } from "../domain/screens";

export interface AccessContext {
  mode: "guest" | "demo" | "live";
  /** Demonstration role (demo mode only). */
  role: Role;
  roles: string[];
  permissions: string[];
}

/** Server role codes that see every permitted screen regardless of individual grants. */
const superRoles = ["super_admin"];

export function hasPermission(ctx: AccessContext, permission: string): boolean {
  if (ctx.mode === "demo") return true;
  if (ctx.mode !== "live") return false;
  return ctx.roles.some((r) => superRoles.includes(r)) || ctx.permissions.includes(permission);
}
export function hasAny(ctx: AccessContext, permissions: string[]): boolean {
  return permissions.some((p) => hasPermission(ctx, p));
}

/** Any-of permission rules. Aligned with the native API scopes (Ha_api_keys::mobile_scopes). */
const byScreen: Record<string, string[]> = {
  assistant: ["ai.use"],
  "conversation-history": ["ai.use"],
  competencies: ["competencies.view"],
  readiness: ["readiness.view"],
  actions: ["action_plans.view"],
  "action-detail": ["action_plans.view"],
  certificates: ["certificates.view"],
  certificate: ["certificates.view"],
  progress: ["courses.view"],
  management: ["learners.view"],
  team: ["learners.view"],
  "learner-detail": ["learners.view"],
  gaps: ["gaps.view"],
  "assessor-queue": ["practicals.view"],
  practical: ["practicals.assess"],
  branding: ["branding.update"],
  reports: ["kpis.view"],
  assign: ["training_assignments.assign"],
  clients: ["organizations.view"],
  properties: ["properties.view"],
  users: ["users.view"],
  roles: ["system.configure"],
  content: ["courses.create", "courses.update", "knowledge.create", "knowledge.update", "knowledge.review", "knowledge.approve", "cms_pages.view"],
  versions: ["knowledge.review", "knowledge.approve", "courses.approve"],
  "version-detail": ["knowledge.review", "knowledge.approve", "courses.approve"],
  "course-editor": ["courses.create", "courses.update"],
  portfolio: ["analytics.view"],
  audit: ["audit_logs.view"],
  quiz: ["assessments.view"],
  "quiz-result": ["assessments.view"],
  "assessment-history": ["assessments.view"],
};
export const adminPermissions = [
  "users.view",
  "cms_pages.view",
  "courses.create",
  "knowledge.create",
  "settings.view",
  "system.configure",
];
const byModule: Record<string, string[]> = {
  Learning: ["courses.view"],
  Assessment: ["courses.view"],
  Knowledge: ["knowledge.view"],
  Assistant: ["ai.use"],
  Administration: adminPermissions,
};

/** Permissions a screen needs (any-of), or null when every signed-in user may open it. */
export function requiredPermissions(spec: Pick<ScreenSpec, "id" | "module">): string[] | null {
  return byScreen[spec.id] || byModule[spec.module] || null;
}

/** May this user open the screen? Demo mode keeps the demonstration role rules. */
export function canOpen(ctx: AccessContext, spec: Pick<ScreenSpec, "id" | "module" | "roles">): boolean {
  if (ctx.mode === "demo") return !spec.roles || spec.roles.includes(ctx.role);
  if (ctx.mode !== "live") return false;
  const need = requiredPermissions(spec);
  return !need || hasAny(ctx, need);
}

/** Display role used for greetings/badges only. */
export function workspaceRole(identity: { roles: string[]; permissions: string[] }): Role {
  if (identity.roles.some((r) => ["super_admin", "academy_admin", "altus_admin", "content_manager", "org_admin"].includes(r)))
    return "admin";
  if (identity.roles.includes("instructor")) return "instructor";
  if (identity.roles.includes("supervisor")) return "supervisor";
  if (identity.roles.some((r) => ["property_manager", "department_manager", "property_admin", "training_manager", "executive"].includes(r)) || identity.permissions.includes("kpis.view"))
    return "manager";
  if (identity.permissions.includes("learners.view")) return "supervisor";
  return "learner";
}
