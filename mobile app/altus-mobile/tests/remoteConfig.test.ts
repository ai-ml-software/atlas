import { test } from "node:test";
import assert from "node:assert/strict";
import {
  compareVersions,
  updateStatus,
  featureEnabled,
  featureForScreen,
  fetchRemoteConfig,
  cacheMatchesServer,
  cacheServerId,
  isRemoteConfig,
  type RemoteConfig,
} from "../src/services/remoteConfig.ts";

const cfg = (over: Partial<RemoteConfig> = {}): RemoteConfig => ({
  version: 1,
  updated_at: null,
  api_base_url: "",
  branding: { app_name: "A", app_name_ar: "A", primary_color: "#111111", accent_color: "#222222", background_color: "#FFFFFF", logo_url: "", splash_url: "" },
  features: { learning: true, knowledge: true, assessments: true, assistant: false, qr: true, notifications: true, team: true, performance: true, demo: true },
  default_language: "en",
  support: { email: "", phone: "", url: "" },
  platforms: {
    ios: { min_version: "1.1.0", latest_version: "1.2.0", store_url: "" },
    android: { min_version: "1.0.0", latest_version: "1.0.0", store_url: "" },
  },
  maintenance: { enabled: false, message_en: "", message_ar: "" },
  ...over,
});

test("malformed nested configuration cannot replace a valid offline cache", () => {
  assert.equal(isRemoteConfig(cfg()), true);
  assert.equal(isRemoteConfig({ ...cfg(), branding: null }), false);
  assert.equal(isRemoteConfig({ ...cfg(), platforms: { ios: {} } }), false);
  assert.equal(isRemoteConfig({ ...cfg(), maintenance: { enabled: "yes" } }), false);
  assert.equal(isRemoteConfig({ ...cfg(), features: { learning: true } }), false);
});

test("offline configuration is bound to its server and rotated key prefix", () => {
  const setup = { base: "https://first.test", appKey: "altm_0123456789ab_" + "a".repeat(40) };
  const cache = { config: cfg(), etag: '"v1"', fetchedAt: Date.now(), serverId: cacheServerId(setup) };
  assert.equal(cacheMatchesServer(cache, setup), true);
  assert.equal(cacheMatchesServer(cache, { ...setup, base: "https://second.test" }), false);
  assert.equal(cacheMatchesServer(cache, { ...setup, appKey: "altm_abcdef012345_" + "a".repeat(40) }), false);
  assert.equal(cacheMatchesServer(cache, null), false);
  assert.equal(cache.serverId.includes("a".repeat(40)), false, "the secret is not cached in preferences");
});

test("compareVersions is numeric", () => {
  assert.equal(compareVersions("1.10.0", "1.9.9"), 1);
  assert.equal(compareVersions("1.0", "1.0.0"), 0);
  assert.equal(compareVersions("0.9", "1"), -1);
});

test("updateStatus per platform", () => {
  assert.equal(updateStatus(cfg(), "ios", "1.0.0"), "required");
  assert.equal(updateStatus(cfg(), "ios", "1.1.5"), "suggested");
  assert.equal(updateStatus(cfg(), "ios", "1.2.0"), "ok");
  assert.equal(updateStatus(cfg(), "android", "1.0.0"), "ok");
  assert.equal(updateStatus(cfg(), "web", "0.1"), "ok");
  assert.equal(updateStatus(null, "ios", "0.1"), "ok");
});

test("features default on and map to screens", () => {
  assert.equal(featureEnabled(null, "assistant"), true);
  assert.equal(featureEnabled(cfg(), "assistant"), false);
  assert.equal(featureForScreen("Learner", "home", "home"), null, "home is never hidden");
  assert.equal(featureForScreen("Learning", "course", "course"), "learning");
  assert.equal(featureForScreen("Assessment", "quiz", "quiz"), "assessments");
  assert.equal(featureForScreen("System", "qr", "qr"), "qr");
});


test("fetchRemoteConfig handles 200, 304, 401", async () => {
  const mk = (status: number, body: unknown, etag = '"e1"') =>
    (async () => new Response(status === 304 ? null : JSON.stringify(body), { status, headers: { ETag: etag } })) as unknown as typeof fetch;
  const ok = await fetchRemoteConfig("https://x.test", "k", null, mk(200, { success: true, data: cfg() }));
  assert.equal(ok.kind, "fresh");
  assert.equal(ok.kind === "fresh" && ok.etag, '"e1"');
  assert.equal((await fetchRemoteConfig("https://x.test", "k", '"e1"', mk(304, null))).kind, "unchanged");
  assert.equal((await fetchRemoteConfig("https://x.test", "k", null, mk(401, { success: false }))).kind, "unauthorized");
  assert.equal((await fetchRemoteConfig("https://x.test", "k", null, mk(200, { success: true, data: { bad: 1 } }))).kind, "error");
});
