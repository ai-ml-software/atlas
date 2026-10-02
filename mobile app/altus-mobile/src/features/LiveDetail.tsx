import React from "react";
import { View, Linking, Share } from "react-native";
import { useApp } from "../state/AppProvider";
import { useResource } from "../services/useResource";
import { JsonRecord } from "../domain/models";
import {
  T,
  Card,
  Button,
  Badge,
  StateView,
  go,
  styles,
} from "../components/ui";
import { LiveData } from "./Workspaces";

export function LiveDetail({ kind, id }: { kind: string; id?: string }) {
  const { t, state, api } = useApp();
  const endpoint =
    kind === "certificate"
      ? "certificates"
      : kind === "notification-detail"
        ? "notifications"
        : kind === "action-detail"
          ? "actions"
          : "people";
  const rows = useResource<JsonRecord[]>(`${endpoint}?locale=${state.locale}`);
  const readiness = useResource<JsonRecord>(
    kind === "learner-detail" && /^\d+$/.test(id || "")
      ? `readiness?user_id=${id}`
      : undefined,
  );
  if (rows.loading) return <StateView type="loading" />;
  if (rows.error)
    return (
      <StateView
        type="error"
        detail={rows.error.message}
        onRetry={rows.retry}
      />
    );
  const record = rows.data?.find(
    (x) => String(x.id || x.verification_code || "") === id,
  );
  if (!record) return <StateView />;
  const title =
    kind === "certificate"
      ? record[
          state.locale === "ar" ? "subject_title_ar" : "subject_title_en"
        ] || record.subject_title_en
      : record.title ||
        record.title_en ||
        `${record.first_name || ""} ${record.last_name || ""}`;
  return (
    <View style={styles.stack}>
      <T variant="display" style={{ fontSize: 30 }}>
        {String(title || "")}
      </T>
      {!!record.status && <Badge label={String(record.status)} />}
      <LiveData data={record} />
      {kind === "learner-detail" && (
        <>
          <T variant="title">{t("readiness")}</T>
          {readiness.loading ? (
            <StateView type="loading" />
          ) : readiness.error ? (
            <StateView
              type="error"
              detail={readiness.error.message}
              onRetry={readiness.retry}
            />
          ) : readiness.data ? (
            <LiveData data={readiness.data} />
          ) : (
            <StateView />
          )}
          <Button label={t("assign")} onPress={() => go("assign", id)} />
        </>
      )}
      {kind === "certificate" && typeof record.verify_url === "string" && (
        <>
          <Card>
            <T>{String(record.certificate_no || "")}</T>
            <T>{t("verify")}</T>
          </Card>
          <Button
            label={t("verify")}
            onPress={() => void Linking.openURL(record.verify_url as string)}
          />
          <Button
            secondary
            label={t("share")}
            onPress={() =>
              void Share.share({ message: `${title}\n${record.verify_url}` })
            }
          />
        </>
      )}
      {kind === "notification-detail" && (
        <Button
          label={t("markRead")}
          onPress={async () => {
            try {
              await api?.request("notifications_read", "POST", {
                id: Number(id),
              });
              rows.retry();
            } catch {
              rows.retry();
            }
          }}
        />
      )}
    </View>
  );
}
