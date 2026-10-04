import React, { ReactNode, useState } from "react";
import { View, Linking, Platform, Image } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import Constants from "expo-constants";
import { useApp } from "../state/AppProvider";
import { T, Button, Icon, Logo, useTheme } from "../components/ui";
import { updateStatus } from "../services/remoteConfig";

export const appVersion = Constants.expoConfig?.version || "1.0.0";

/**
 * Applies admin remote config before any screen: maintenance mode and forced updates
 * replace the app; a suggested update shows a dismissible banner.
 */
export function RemoteGate({ children }: { children: ReactNode }) {
  const { remote, state, t, refreshConfig } = useApp();
  const c = useTheme();
  const [dismissed, setDismissed] = useState(false);
  const [busy, setBusy] = useState(false);
  if (!remote) return <>{children}</>;
  const ar = state.locale === "ar";
  const status = updateStatus(remote, Platform.OS, appVersion);
  const store =
    Platform.OS === "ios" || Platform.OS === "android"
      ? remote.platforms[Platform.OS].store_url
      : "";
  const retry = async () => {
    setBusy(true);
    try {
      await refreshConfig();
    } finally {
      setBusy(false);
    }
  };
  const blocker = (icon: "construct-outline" | "cloud-download-outline", title: string, body: string, action?: ReactNode) => (
    <SafeAreaView style={{ flex: 1, backgroundColor: c.bg }}>
      <View
        style={{ flex: 1, padding: 28, gap: 18, justifyContent: "center", direction: ar ? "rtl" : "ltr" }}
        accessibilityLiveRegion="polite"
      >
        {remote.branding.splash_url ? (
          <Image
            source={{ uri: remote.branding.splash_url }}
            style={{ width: 120, height: 120, resizeMode: "contain", alignSelf: "center" }}
            accessibilityIgnoresInvertColors
          />
        ) : (
          <Logo light={state.dark} />
        )}
        <Icon name={icon} size={44} color={c.accent} />
        <T variant="title">{title}</T>
        <T style={{ color: c.muted }}>{body}</T>
        {action}
        <Button label={t("retry")} secondary busy={busy} onPress={retry} />
      </View>
    </SafeAreaView>
  );
  if (remote.maintenance.enabled)
    return blocker(
      "construct-outline",
      t("maintenanceTitle"),
      (ar ? remote.maintenance.message_ar : remote.maintenance.message_en) ||
        remote.maintenance.message_en ||
        remote.maintenance.message_ar,
    );
  if (status === "required")
    return blocker(
      "cloud-download-outline",
      t("updateRequiredTitle"),
      t("updateRequiredBody"),
      store ? <Button label={t("updateNow")} onPress={() => void Linking.openURL(store)} /> : null,
    );
  return (
    <>
      {children}
      {status === "suggested" && !dismissed && (
        <SafeAreaView edges={["bottom"]} style={{ position: "absolute", left: 12, right: 12, bottom: 70 }}>
          <View
            style={{ backgroundColor: c.surface, borderColor: c.border, borderWidth: 1, borderRadius: 14, padding: 14, gap: 10 }}
            accessibilityRole="alert"
          >
            <T>{t("updateSuggested")}</T>
            <View style={{ flexDirection: ar ? "row-reverse" : "row", gap: 10 }}>
              {!!store && (
                <View style={{ flex: 1 }}>
                  <Button label={t("updateNow")} onPress={() => void Linking.openURL(store)} />
                </View>
              )}
              <View style={{ flex: 1 }}>
                <Button label={t("later")} secondary onPress={() => setDismissed(true)} />
              </View>
            </View>
          </View>
        </SafeAreaView>
      )}
    </>
  );
}
