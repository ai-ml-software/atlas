import { test } from "node:test";
import assert from "node:assert/strict";
import { canOpen, workspaceRole, type AccessContext } from "../src/services/access.ts";

const live = (roles: string[], permissions: string[]): AccessContext => ({ mode: "live", role: "learner", roles, permissions });
const screen = (id: string, module: string, roles?: ("learner" | "supervisor" | "manager" | "instructor" | "admin")[]) => ({ id, module, roles });

test("server roles map to display workspaces", () => {
  assert.equal(workspaceRole({ roles: ["super_admin"], permissions: [] }), "admin");
  assert.equal(workspaceRole({ roles: ["academy_admin"], permissions: ["learners.view"] }), "admin");
  assert.equal(workspaceRole({ roles: ["instructor"], permissions: ["learners.view"] }), "instructor");
  assert.equal(workspaceRole({ roles: ["property_manager"], permissions: ["learners.view"] }), "manager");
  assert.equal(workspaceRole({ roles: ["department_manager"], permissions: [] }), "manager");
  assert.equal(workspaceRole({ roles: ["auditor"], permissions: ["learners.view"] }), "supervisor");
  assert.equal(workspaceRole({ roles: ["learner"], permissions: ["courses.view"] }), "learner");
});

test("learners see learning and knowledge but not management or admin", () => {
  const ctx = live(["learner"], ["courses.view", "knowledge.view", "ai.use", "competencies.view", "readiness.view"]);
  assert.equal(canOpen(ctx, screen("learning", "Learning")), true);
  assert.equal(canOpen(ctx, screen("knowledge", "Knowledge")), true);
  assert.equal(canOpen(ctx, screen("assistant", "Assistant")), true);
  assert.equal(canOpen(ctx, screen("readiness", "Performance")), true);
  assert.equal(canOpen(ctx, screen("profile", "Personal")), true);
  assert.equal(canOpen(ctx, screen("team", "Management", ["manager"])), false);
  assert.equal(canOpen(ctx, screen("reports", "Management", ["manager"])), false);
  assert.equal(canOpen(ctx, screen("admin", "Administration", ["admin"])), false);
  assert.equal(canOpen(ctx, screen("content", "Administration", ["admin"])), false);
});

test("each performance and administrative screen checks its own grant", () => {
  const narrow = live(["content_manager"], ["competencies.view", "courses.create"]);
  for (const id of ["readiness", "actions", "certificates"])
    assert.equal(canOpen(narrow, screen(id, "Performance")), false, id);
  for (const id of ["users", "clients", "roles", "audit"])
    assert.equal(canOpen(narrow, screen(id, "Administration")), false, id);
  assert.equal(canOpen(narrow, screen("course-editor", "Administration")), true);
  const supervisor = live(["supervisor"], ["learners.view", "gaps.view", "practicals.view"]);
  assert.equal(canOpen(supervisor, screen("gaps", "Management")), true);
  assert.equal(canOpen(supervisor, screen("practical", "Management")), false);
  assert.equal(workspaceRole({ roles: ["supervisor"], permissions: ["kpis.view"] }), "supervisor");
});

test("managers, executives, trainers and admins follow their grants", () => {
  const manager = live(["property_manager"], ["learners.view", "kpis.view", "training_assignments.assign"]);
  assert.equal(canOpen(manager, screen("team", "Management")), true);
  assert.equal(canOpen(manager, screen("reports", "Management")), true);
  assert.equal(canOpen(manager, screen("assign", "Management")), true);
  assert.equal(canOpen(manager, screen("learning", "Learning")), false, "no courses.view grant");
  const trainer = live(["instructor"], ["learners.view", "courses.create"]);
  assert.equal(canOpen(trainer, screen("assign", "Management")), false);
  assert.equal(canOpen(trainer, screen("admin", "Administration")), true);
  const root = live(["super_admin"], []);
  for (const id of ["team", "reports", "assign", "admin", "assistant"])
    assert.equal(canOpen(root, screen(id, id === "admin" ? "Administration" : "Management")), true, id);
});

test("guests open nothing and demo follows the demonstration role", () => {
  assert.equal(canOpen({ mode: "guest", role: "learner", roles: [], permissions: [] }, screen("home", "Learner")), false);
  const demo: AccessContext = { mode: "demo", role: "learner", roles: [], permissions: [] };
  assert.equal(canOpen(demo, screen("home", "Learner")), true);
  assert.equal(canOpen(demo, screen("team", "Management", ["manager"])), false);
  assert.equal(canOpen({ ...demo, role: "manager" }, screen("team", "Management", ["manager"])), true);
});
