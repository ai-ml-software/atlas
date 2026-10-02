import React from "react";
import { View } from "react-native";
import { T, Button, go } from "../components/ui";
import { useApp } from "../state/AppProvider";
export default function NotFound() {
  const { t } = useApp();
  return (
    <View style={{ padding: 30, gap: 20 }}>
      <T variant="title">{t("empty")}</T>
      <Button label={t("home")} onPress={() => go("home")} />
    </View>
  );
}
