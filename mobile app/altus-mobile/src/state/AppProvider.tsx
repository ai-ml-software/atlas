import React, {
  createContext,
  useContext,
  useState,
  useEffect,
  useRef,
  ReactNode,
} from "react";
import { Platform, Alert, AppState } from "react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SecureStore from "expo-secure-store";
import { getLocales } from "expo-localization";
import NetInfo from "@react-native-community/netinfo";
import Constants from "expo-constants";
import {
  Bi,
  Document,
  LocalPost,
  Answer,
  LiveIdentity,
} from "../domain/models";
import { Role, byId } from "../domain/screens";
import { messages } from "../i18n/messages";
import {
  MobileApi,
  normalizeBase,
  signIn as apiSignIn,
  verifyTwoFactor,
  SignInResult,
} from "../services/api";
import { canOpen, workspaceRole } from "../services/access";
import {
  CachedConfig,
  FeatureName,
  RemoteConfig,
  featureEnabled,
  featureForScreen,
  fetchRemoteConfig,
  cacheMatchesServer,
  cacheServerId,
} from "../services/remoteConfig";

const extra = (Constants.expoConfig?.extra || {}) as {
  platformUrl?: string;
  appKey?: string;
};
// Baked in at build time (.env / eas.json). People never configure servers or keys.
export const defaultBase =
  process.env.EXPO_PUBLIC_PLATFORM_URL ||
  extra.platformUrl ||
  "https://altusgulf.com";
const configuredAppKey = process.env.EXPO_PUBLIC_ALTUS_APP_KEY || extra.appKey || "";
const appKey = /^altm_[a-f0-9]{12}_[A-Za-z0-9]{40}$/.test(configuredAppKey) ? configuredAppKey : "";
const configCacheKey = "altus.mobile.remote.v1";
const deviceLabel = `${Constants.deviceName || Platform.OS} (${Platform.OS})`;

export interface LocalState {
  locale: string;
  country: string;
  dark: boolean;
  largeText: boolean;
  reduceMotion: boolean;
  reminders: boolean;
  sopUpdates: boolean;
  name: string;
  role: Role;
  property: string;
  accent: string;
  consent: boolean;
  mode: "guest" | "demo" | "live";
  favorites: string[];
  recent: string[];
  searches: string[];
  acknowledged: string[];
  downloads: Document[];
  completed: string[];
  quizScores: number[];
  read: string[];
  tickets: LocalPost[];
  posts: LocalPost[];
  drafts: LocalPost[];
  assignments: LocalPost[];
  answers: Answer[];
  reviewStatus: string;
  base: string;
}
const initial: LocalState = {
  locale: "en",
  country: "SA",
  dark: false,
  largeText: false,
  reduceMotion: true,
  reminders: true,
  sopUpdates: true,
  name: "Ahmed",
  role: "learner",
  property: "ALTUS Demo Hotel · Riyadh",
  accent: "#C45B2F",
  consent: false,
  mode: "guest",
  favorites: [],
  recent: [],
  searches: [],
  acknowledged: [],
  downloads: [],
  completed: [],
  quizScores: [],
  read: [],
  tickets: [],
  posts: [],
  drafts: [],
  assignments: [],
  answers: [],
  reviewStatus: "Awaiting review",
  base: defaultBase,
};
export const storageKey = "altus.mobile.local.v1";
const credentialKey = "altus.mobile.credential";
/** Result of the password step: done, or a second factor is needed. */
export type SignInStep = { done: true } | { done: false; challenge: string; type: "email" | "authenticator" };
interface Context {
  state: LocalState;
  ready: boolean;
  online: boolean;
  identity: LiveIdentity | null;
  api: MobileApi | null;
  rtl: boolean;
  t: (key: string) => string;
  b: (value: Bi) => string;
  patch: (value: Partial<LocalState>) => void;
  update: (fn: (old: LocalState) => LocalState) => void;
  demo: () => void;
  signIn: (email: string, password: string) => Promise<SignInStep>;
  completeTwoFactor: (challenge: string, code: string) => Promise<SignInStep>;
  logout: () => Promise<void>;
  notify: (key: string) => void;
  confirm: (key: string, action: () => void) => void;
  has: (permission: string) => boolean;
  /** Role permissions ∩ remote feature toggles for a screen id. */
  can: (screenId: string) => boolean;
  reset: () => void;
  /** Remote config from /api/v1/mobile/config (cached offline); null until first load. */
  remote: RemoteConfig | null;
  feature: (name: FeatureName) => boolean;
  refreshConfig: () => Promise<void>;
}
const AppContext = createContext<Context | null>(null);
export function AppProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState(initial),
    [ready, setReady] = useState(false),
    [online, setOnline] = useState(true),
    [identity, setIdentity] = useState<LiveIdentity | null>(null),
    [api, setApi] = useState<MobileApi | null>(null),
    [remote, setRemote] = useState<RemoteConfig | null>(null);
  const storageChain = useRef(Promise.resolve());
  const etagRef = useRef<string | null>(null);
  const firstRun = useRef(false);
  const setup = { base: defaultBase, appKey };
  const applyConfig = (config: RemoteConfig, etag: string | null) => {
    etagRef.current = etag;
    setRemote(config);
    // When an admin moves the API, signed-out users follow it automatically.
    let moved = "";
    try {
      moved = config.api_base_url ? normalizeBase(config.api_base_url) : "";
    } catch {
      moved = "";
    }
    if (moved)
      setState((old) =>
        old.mode === "live" || old.base === moved ? old : { ...old, base: moved },
      );
    void AsyncStorage.setItem(
      configCacheKey,
      JSON.stringify({ config, etag, fetchedAt: Date.now(), serverId: cacheServerId(setup) } as CachedConfig),
    ).catch(() => {});
    if (firstRun.current && config.default_language !== "device") {
      firstRun.current = false;
      setState((old) => ({ ...old, locale: config.default_language }));
    }
  };
  /** Fetch with If-None-Match; keep the cached copy on any failure (offline-first). */
  const loadConfig = async () => {
    if (!appKey) return;
    const r = await fetchRemoteConfig(defaultBase, appKey, etagRef.current);
    if (r.kind === "fresh") applyConfig(r.config, r.etag);
  };
  const signedOut = (old: LocalState): LocalState => ({
    ...initial,
    locale: old.locale,
    dark: old.dark,
    country: old.country,
    base: old.base,
  });
  const dropSession = () => {
    setApi(null);
    setIdentity(null);
    setState(signedOut);
    if (Platform.OS !== "web") void SecureStore.deleteItemAsync(credentialKey).catch(() => {});
  };
  const enter = (client: MobileApi, me: LiveIdentity) => {
    setApi(client);
    setIdentity(me);
    setState((old) => ({
      ...signedOut(old),
      mode: "live",
      name: me.name,
      base: client.base,
      property: String(me.profile?.property_name || "ALTUS Gulf"),
      role: workspaceRole(me),
    }));
  };
  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const saved = await AsyncStorage.getItem(storageKey);
        firstRun.current = !saved;
        const value: LocalState = saved
          ? { ...initial, ...JSON.parse(saved) }
          : {
              ...initial,
              locale: getLocales()[0]?.languageCode === "ar" ? "ar" : "en",
            };
        // Live identity is never restored from local preferences; the session token lives in SecureStore.
        value.mode = value.mode === "live" ? "guest" : value.mode;
        try {
          value.base = normalizeBase(defaultBase);
        } catch {
          value.base = defaultBase;
        }
        if (active) setState(value);
        const cached = await AsyncStorage.getItem(configCacheKey);
        if (cached && active) {
          try {
            const parsed = JSON.parse(cached) as CachedConfig;
            if (cacheMatchesServer(parsed, setup)) applyConfig(parsed.config, parsed.etag);
          } catch { /* Ignore invalid or obsolete cache entries. */ }
        }
        if (active) void loadConfig();
        if (Platform.OS !== "web") {
          const secret = await SecureStore.getItemAsync(credentialKey);
          if (secret) {
            const parsed = JSON.parse(secret) as { base: string; key: string; kind?: string };
            if (parsed.kind !== "password-v2") {
              await SecureStore.deleteItemAsync(credentialKey);
              return;
            }
            const client = new MobileApi(normalizeBase(parsed.base), parsed.key, dropSession);
            try {
              const me = await client.me();
              if (active) enter(client, me);
            } catch (e) {
              // Offline: keep the token for the next launch; only a 401 signs the user out.
              if ((e as { status?: number }).status === 401) await SecureStore.deleteItemAsync(credentialKey);
            }
          }
        }
      } catch {
        if (active) setState(initial);
      } finally {
        if (active) setReady(true);
      }
    })();
    const sub = NetInfo.addEventListener((n) => setOnline(n.isConnected !== false));
    return () => {
      active = false;
      sub();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    if (!ready || !online) return;
    const refresh = () => { if (AppState.currentState === "active") void loadConfig(); };
    const timer = setInterval(refresh, 5 * 60 * 1000);
    const subscription = AppState.addEventListener("change", (status) => {
      if (status === "active") void loadConfig();
    });
    return () => { clearInterval(timer); subscription.remove(); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ready, online]);
  useEffect(() => {
    if (!api || !online) return;
    let active = true;
    let running = false;
    const refreshIdentity = async () => {
      if (running || AppState.currentState !== "active") return;
      running = true;
      try {
        const me = await api.me();
        if (active) {
          setIdentity(me);
          setState((old) => old.mode === "live" ? { ...old, name: me.name, role: workspaceRole(me), property: String(me.profile?.property_name || "ALTUS Gulf") } : old);
        }
      } catch { /* Requests enforce current permissions; a 401 drops the session. */ }
      finally { running = false; }
    };
    const timer = setInterval(() => void refreshIdentity(), 60000);
    const subscription = AppState.addEventListener("change", (status) => {
      if (status === "active") void refreshIdentity();
    });
    return () => { active = false; clearInterval(timer); subscription.remove(); };
  }, [api, online]);
  useEffect(() => {
    if (ready) {
      // Live content/answers are never restored into a guest or demonstration session.
      const persisted =
        state.mode === "live"
          ? {
              ...initial,
              locale: state.locale,
              dark: state.dark,
              country: state.country,
              largeText: state.largeText,
              reduceMotion: state.reduceMotion,
              reminders: state.reminders,
              sopUpdates: state.sopUpdates,
              base: state.base,
            }
          : state;
      storageChain.current = storageChain.current
        .then(() => AsyncStorage.setItem(storageKey, JSON.stringify(persisted)))
        .catch(() => {});
    }
  }, [state, ready]);
  const rtl = ["ar", "ur", "fa", "he", "ps", "sd", "ug", "yi", "dv", "ckb"].includes(state.locale);
  const t = (key: string) => messages[key]?.[state.locale === "ar" ? "ar" : "en"] || key;
  const b = (value: Bi) => value[state.locale === "ar" ? "ar" : "en"];
  const patch = (value: Partial<LocalState>) => setState((old) => ({ ...old, ...value }));
  const notify = (key: string) => {
    if (Platform.OS === "web") window.alert(t(key));
    else Alert.alert(t("notice"), t(key));
  };
  const confirm = (key: string, action: () => void) => {
    if (Platform.OS === "web") {
      if (window.confirm(t(key))) action();
    } else
      Alert.alert(t("notice"), t(key), [
        { text: t("cancel"), style: "cancel" },
        { text: t("continue"), style: "destructive", onPress: action },
      ]);
  };
  const finish = async (result: SignInResult): Promise<SignInStep> => {
    if (result.two_factor_required) return { done: false, challenge: result.challenge, type: result.challenge_type };
    const base = state.base;
    const client = new MobileApi(base, result.token, dropSession);
    const me = await client.me();
    if (Platform.OS !== "web")
      await SecureStore.setItemAsync(credentialKey, JSON.stringify({ base, key: result.token, kind: "password-v2" }), {
        keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
      });
    enter(client, me);
    return { done: true };
  };
  const signIn = async (email: string, password: string) =>
    finish(await apiSignIn(state.base, email.trim(), password, deviceLabel));
  const completeTwoFactor = async (challenge: string, code: string) => {
    return finish(await verifyTwoFactor(state.base, challenge, code.trim(), deviceLabel));
  };
  const logout = async () => {
    const current = api;
    dropSession();
    if (current) await current.logout().catch(() => {});
  };
  const access = {
    mode: state.mode,
    role: state.role,
    roles: identity?.roles || [],
    permissions: identity?.permissions || [],
  };
  const feature = (name: FeatureName) => featureEnabled(remote, name);
  const can = (screenId: string) => {
    const spec = byId[screenId];
    if (!spec || !canOpen(access, spec)) return false;
    const toggle = featureForScreen(spec.module, spec.id, spec.kind);
    return toggle === null || feature(toggle);
  };
  const value: Context = {
    state,
    ready,
    online,
    identity,
    api,
    rtl,
    t,
    b,
    patch,
    update: setState,
    demo: () => {
      setApi(null);
      setIdentity(null);
      if (Platform.OS !== "web") void SecureStore.deleteItemAsync(credentialKey).catch(() => {});
      setState((old) => ({
        ...initial,
        mode: "demo",
        locale: old.locale,
        dark: old.dark,
        country: old.country,
        name: old.name,
        base: old.base,
      }));
    },
    signIn,
    completeTwoFactor,
    logout,
    notify,
    confirm,
    has: (permission: string) =>
      state.mode === "demo" ||
      !!identity?.permissions.includes(permission) ||
      !!identity?.roles.includes("super_admin"),
    can,
    reset: () =>
      setState({
        ...initial,
        mode: "demo",
        locale: state.locale,
        dark: state.dark,
        base: state.base,
      }),
    remote,
    feature,
    refreshConfig: loadConfig,
  };
  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}
export const useApp = () => {
  const value = useContext(AppContext);
  if (!value) throw new Error("AppProvider is required");
  return value;
};
