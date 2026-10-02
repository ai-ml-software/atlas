import React, {
  createContext,
  useContext,
  useState,
  useEffect,
  useRef,
  ReactNode,
} from "react";
import { Platform, Alert } from "react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SecureStore from "expo-secure-store";
import { getLocales } from "expo-localization";
import NetInfo from "@react-native-community/netinfo";
import {
  Bi,
  Document,
  LocalPost,
  Answer,
  LiveIdentity,
} from "../domain/models";
import { Role } from "../domain/screens";
import { messages } from "../i18n/messages";
import { MobileApi, normalizeBase } from "../services/api";

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
  base: process.env.EXPO_PUBLIC_PLATFORM_URL || "https://altusgulf.com",
};
export const storageKey = "altus.mobile.local.v1";
const credentialKey = "altus.mobile.credential";
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
  connect: (base: string, key: string) => Promise<void>;
  logout: () => Promise<void>;
  notify: (key: string) => void;
  confirm: (key: string, action: () => void) => void;
  has: (permission: string) => boolean;
  reset: () => void;
}
const AppContext = createContext<Context | null>(null);
export function AppProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState(initial),
    [ready, setReady] = useState(false),
    [online, setOnline] = useState(true),
    [identity, setIdentity] = useState<LiveIdentity | null>(null),
    [api, setApi] = useState<MobileApi | null>(null);
  const storageChain = useRef(Promise.resolve());
  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const saved = await AsyncStorage.getItem(storageKey);
        const value = saved
          ? { ...initial, ...JSON.parse(saved) }
          : {
              ...initial,
              locale: getLocales()[0]?.languageCode === "ar" ? "ar" : "en",
            };
        // Live identity is never restored from local preferences. Native secrets live in SecureStore;
        // the browser intentionally requires reconnecting after reload.
        value.mode = value.mode === "live" ? "guest" : value.mode;
        if (active) setState(value);
        if (Platform.OS !== "web") {
          const secret = await SecureStore.getItemAsync(credentialKey);
          if (secret) {
            const parsed = JSON.parse(secret);
            const client = new MobileApi(
              normalizeBase(parsed.base),
              parsed.key,
              () => {
                setApi(null);
                setIdentity(null);
                setState((old) => ({
                  ...initial,
                  locale: old.locale,
                  dark: old.dark,
                  country: old.country,
                  base: old.base,
                }));
                void SecureStore.deleteItemAsync(credentialKey);
              },
            );
            try {
              const me = await client.me();
              if (active) {
                setApi(client);
                setIdentity(me);
                setState((old) => ({
                  ...old,
                  mode: "live",
                  name: me.name,
                  base: parsed.base,
                  property: String(me.profile?.property_name || "ALTUS Gulf"),
                  role: me.permissions.includes("users.manage")
                    ? "admin"
                    : me.permissions.includes("learners.view")
                      ? "manager"
                      : "learner",
                }));
              }
            } catch {
              await SecureStore.deleteItemAsync(credentialKey);
            }
          }
        }
      } catch {
        if (active) setState(initial);
      } finally {
        if (active) setReady(true);
      }
    })();
    const sub = NetInfo.addEventListener((n) =>
      setOnline(n.isConnected !== false),
    );
    return () => {
      active = false;
      sub();
    };
  }, []);
  useEffect(() => {
    if (ready) {
      // Live content/answers are never restored into a guest or demonstration session.
      // A future encrypted, account-bound offline store has a separate release contract.
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
  const rtl = [
    "ar",
    "ur",
    "fa",
    "he",
    "ps",
    "sd",
    "ug",
    "yi",
    "dv",
    "ckb",
  ].includes(state.locale);
  const t = (key: string) =>
    messages[key]?.[state.locale === "ar" ? "ar" : "en"] || key;
  const b = (value: Bi) => value[state.locale === "ar" ? "ar" : "en"];
  const patch = (value: Partial<LocalState>) =>
    setState((old) => ({ ...old, ...value }));
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
  const connect = async (base: string, key: string) => {
    const normalized = normalizeBase(base);
    const client = new MobileApi(normalized, key.trim());
    const me = await client.me();
    const authenticatedClient = new MobileApi(normalized, key.trim(), () => {
      void logout();
    });
    if (Platform.OS !== "web")
      await SecureStore.setItemAsync(
        credentialKey,
        JSON.stringify({ base: normalized, key: key.trim() }),
        { keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY },
      );
    setApi(authenticatedClient);
    setIdentity(me);
    setState((old) => ({
      ...initial,
      locale: old.locale,
      dark: old.dark,
      country: old.country,
      mode: "live",
      name: me.name,
      base: normalized,
      property: String(me.profile?.property_name || "ALTUS Gulf"),
      role: me.permissions.includes("users.manage")
        ? "admin"
        : me.permissions.includes("learners.view")
          ? "manager"
          : "learner",
    }));
  };
  const logout = async () => {
    if (Platform.OS !== "web") await SecureStore.deleteItemAsync(credentialKey);
    setApi(null);
    setIdentity(null);
    setState((old) => ({
      ...initial,
      locale: old.locale,
      dark: old.dark,
      country: old.country,
      base: old.base,
    }));
  };
  const has = (permission: string) =>
    state.mode === "demo" ||
    !!identity?.permissions.includes(permission) ||
    !!identity?.roles.includes("super_admin");
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
      if (Platform.OS !== "web")
        void SecureStore.deleteItemAsync(credentialKey).catch(() => {});
      setState((old) => ({
        ...initial,
        mode: "demo",
        locale: old.locale,
        dark: old.dark,
        country: old.country,
        name: old.name,
      }));
    },
    connect,
    logout,
    notify,
    confirm,
    has,
    reset: () =>
      setState({
        ...initial,
        mode: "demo",
        locale: state.locale,
        dark: state.dark,
      }),
  };
  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}
export const useApp = () => {
  const value = useContext(AppContext);
  if (!value) throw new Error("AppProvider is required");
  return value;
};
