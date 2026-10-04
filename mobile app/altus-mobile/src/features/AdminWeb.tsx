import React from "react";
import { View, Linking } from "react-native";
import { useApp } from "../state/AppProvider";
import { T, Card, Button, Icon, ListRow, go, useTheme, styles } from "../components/ui";
import { byId, ScreenSpec } from "../domain/screens";

const consolePaths: Record<string, string> = {
  clients: "hkp/admin/crud/organizations", properties: "hkp/admin/crud/properties",
  users: "hkp/admin/users", roles: "hkp/admin/system/roles", content: "hkp/admin/content",
  versions: "hkp/admin/content", "version-detail": "hkp/admin/content",
  portfolio: "hkp/admin", audit: "hkp/admin/audit", "course-editor": "hkp/cms/modules",
};

/** Administration for permitted roles: governed tools live in the secure web console. */
export function AdminWeb({ spec }: { spec: ScreenSpec }) {
  const { t, state, can, has } = useApp();
  const c = useTheme();
  const path = consolePaths[spec.id] || (has("organizations.view") || has("analytics.view") ? "hkp/admin" : "hkp/cms/modules");
  return (
    <View style={styles.stack}>
      <Card>
        <Icon name="shield-checkmark-outline" size={30} color={c.accent} />
        <T>{t("adminWebBody")}</T>
      </Card>
      {spec.id === "admin" && Object.keys(consolePaths)
        .filter((id) => id !== "version-detail" && can(id))
        .map((id) => <ListRow key={id} title={state.locale === "ar" ? byId[id].ar : byId[id].title} onPress={() => go(id)} />)}
      <Button
        label={t("openAdminConsole")}
        icon="open-outline"
        onPress={() => void Linking.openURL(`${state.base}/${path}`)}
      />
    </View>
  );
}
