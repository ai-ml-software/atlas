import React, { useState } from "react";
import { Platform, View, Linking } from "react-native";
import { CameraView, useCameraPermissions } from "expo-camera";
import { useApp } from "../state/AppProvider";
import {
  T,
  Card,
  Button,
  Field,
  Icon,
  useTheme,
  styles,
} from "../components/ui";
export function QR() {
  const { state, t, notify } = useApp();
  const c = useTheme();
  const [permission, request] = useCameraPermissions(),
    [scanning, setScanning] = useState(false),
    [code, setCode] = useState("");
  const accept = (value: string) => {
    try {
      if (/^https?:/.test(value)) {
        const url = new URL(value);
        const expected = new URL(state.base);
        if (
          url.origin !== expected.origin ||
          !/^\/(?:en\/|ar\/)?verify(?:\/|$)/.test(url.pathname)
        )
          throw new Error("untrusted");
        setCode(url.pathname.split("/").at(-1) || "");
      } else if (/^[A-Za-z0-9_-]{4,100}$/.test(value)) setCode(value);
      else throw new Error("invalid");
      setScanning(false);
    } catch {
      notify("scanHint");
      setScanning(false);
    }
  };
  return (
    <View style={styles.stack}>
      <Card>
        <Icon name="qr-code-outline" size={80} color={c.accent} />
        <T>{t("scanHint")}</T>
        <T variant="small">{t("verifyNotice")}</T>
      </Card>
      {scanning && Platform.OS !== "web" && permission?.granted && (
        <CameraView
          style={{ height: 280, borderRadius: 18 }}
          barcodeScannerSettings={{ barcodeTypes: ["qr"] }}
          onBarcodeScanned={({ data }) => accept(data)}
        />
      )}
      <Field
        label={t("code")}
        value={code}
        onChangeText={setCode}
        autoCapitalize="none"
      />
      <Button
        label={t("scan")}
        secondary
        onPress={async () => {
          if (Platform.OS === "web") {
            notify("permission");
            return;
          }
          const p = permission?.granted ? permission : await request();
          if (p.granted) setScanning(true);
          else notify("permission");
        }}
      />
      <Button
        label={t("openVerification")}
        disabled={!code.trim()}
        onPress={() => {
          if (!/^[A-Za-z0-9_-]{4,100}$/.test(code)) {
            notify("invalid");
            return;
          }
          void Linking.openURL(
            `${state.base}/${state.locale === "ar" ? "ar" : "en"}/verify/${encodeURIComponent(code)}`,
          );
        }}
      />
      <Button
        label={t("openSettings")}
        secondary
        onPress={() =>
          Platform.OS === "web"
            ? notify("permission")
            : void Linking.openSettings()
        }
      />
    </View>
  );
}
