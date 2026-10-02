import React, { useState } from "react";
import { View, Pressable, Share } from "react-native";
import { useApp } from "../state/AppProvider";
import {
  T,
  Card,
  Button,
  Row,
  Section,
  Badge,
  Search,
  ListRow,
  Icon,
  go,
  useTheme,
  styles,
  StateView,
  Field,
} from "../components/ui";
import { documents, courses } from "../domain/demo";
import { ScreenSpec } from "../domain/screens";
import { JsonRecord, Answer, Document, bi } from "../domain/models";
import { useResource } from "../services/useResource";

export function Knowledge({ spec }: { spec: ScreenSpec }) {
  const { state, t, b, update } = useApp();
  const c = useTheme();
  const [query, setQuery] = useState(""),
    [filter, setFilter] = useState("all");
  const endpoint = `${spec.id === "search" ? "search" : "knowledge"}?locale=${state.locale}&q=${encodeURIComponent(query)}`;
  const live = useResource<JsonRecord[]>(
    state.mode === "live" ? endpoint : undefined,
  );
  const list = documents.filter(
    (d) =>
      (b(d.title) + " " + d.code + " " + b(d.purpose))
        .toLowerCase()
        .includes(query.toLowerCase()) &&
      (filter === "all" || d.type === filter) &&
      (spec.id !== "favorites" || state.favorites.includes(d.id)) &&
      (spec.id !== "recent" || state.recent.includes(d.id)) &&
      (spec.id !== "policies" || d.type === "policy") &&
      (spec.id !== "checklists" || d.type === "checklist"),
  );
  return (
    <View style={styles.stack}>
      <Search value={query} onChange={setQuery} />
      {spec.id === "search" && !!query && (
        <Button
          label={t("saveSearch")}
          secondary
          onPress={() =>
            update((s) => ({
              ...s,
              searches: [...new Set([query, ...s.searches])].slice(0, 20),
            }))
          }
        />
      )}
      <Row style={{ flexWrap: "wrap", gap: 7 }}>
        {[
          ["all", t("all")],
          ["sop", t("sops")],
          ["policy", state.locale === "ar" ? "السياسات" : "Policies"],
          ["checklist", state.locale === "ar" ? "قوائم التحقق" : "Checklists"],
        ].map(([id, label]) => (
          <Pressable
            key={id}
            onPress={() => setFilter(id)}
            accessibilityRole="button"
            accessibilityState={{ selected: filter === id }}
            style={{
              padding: 12,
              minHeight: 44,
              borderRadius: 23,
              backgroundColor: filter === id ? c.text : c.surface,
              borderWidth: 1,
              borderColor: c.border,
            }}
          >
            <T variant="small" style={{ color: filter === id ? c.bg : c.text }}>
              {label}
            </T>
          </Pressable>
        ))}
      </Row>
      {state.mode === "live" ? (
        live.loading ? (
          <StateView type="loading" />
        ) : live.error ? (
          <StateView
            type="error"
            detail={live.error.message}
            onRetry={live.retry}
          />
        ) : live.data?.length ? (
          live.data
            .filter((x) => filter === "all" || x.item_type === filter)
            .map((d, i) => (
              <Card key={String(d.id || i)}>
                <Badge label={String(d.code || d.source_type || "")} />
                <T variant="title">{String(d.title || "")}</T>
                <T>{String(d.summary || d.excerpt || "")}</T>
                <Button
                  label={t("read")}
                  secondary
                  onPress={() =>
                    go(
                      d.source_type === "lesson" ? "lesson" : "sop",
                      String(d.source_id || d.id),
                    )
                  }
                />
              </Card>
            ))
        ) : (
          <StateView />
        )
      ) : list.length ? (
        list.map((d) => (
          <Pressable
            key={d.id}
            accessibilityRole="button"
            onPress={() => {
              update((s) => ({
                ...s,
                recent: [d.id, ...s.recent.filter((x) => x !== d.id)].slice(
                  0,
                  20,
                ),
              }));
              go("sop", d.id);
            }}
          >
            <Card>
              <Row style={{ justifyContent: "space-between" }}>
                <Badge label={d.code} />
                <Icon name="document-text-outline" size={24} color={c.accent} />
              </Row>
              <T variant="title" style={{ fontSize: 23 }}>
                {b(d.title)}
              </T>
              <T variant="small" style={{ color: c.muted }}>
                {b(d.category)} · {t("version")} {d.version}
              </T>
              <Row>
                <Badge label={t("published")} green />
                <T
                  variant="small"
                  style={{ marginLeft: "auto", color: c.muted }}
                >
                  {t("read")} →
                </T>
              </Row>
            </Card>
          </Pressable>
        ))
      ) : (
        <StateView />
      )}
      {state.mode === "demo" &&
        ["search", "favorites", "recent"].includes(spec.id) &&
        courses
          .filter(
            (x) =>
              (spec.id !== "favorites" || state.favorites.includes(x.id)) &&
              (spec.id !== "recent" || state.recent.includes(x.id)) &&
              b(x.title).toLowerCase().includes(query.toLowerCase()),
          )
          .map((x) => (
            <ListRow
              key={x.id}
              title={b(x.title)}
              subtitle={t("learn")}
              onPress={() => go("course", x.id)}
            />
          ))}
      <ListRow title={t("favorites")} onPress={() => go("favorites")} />
      <ListRow title={t("recent")} onPress={() => go("recent")} />
      <ListRow title={t("downloads")} onPress={() => go("downloads")} />
    </View>
  );
}
type LiveSop = {
  id: number;
  title: string;
  code: string;
  version: string;
  effective_date: string;
  owner?: string;
  sections: Record<string, string>;
  acknowledged: unknown;
};
export function Sop({
  id,
  offline = false,
}: {
  id?: string;
  offline?: boolean;
}) {
  const { t, b, state, update, api, notify, confirm } = useApp();
  const c = useTheme();
  const live = useResource<LiveSop>(
    state.mode === "live" && !offline
      ? `sop?id=${encodeURIComponent(id || "")}&locale=${state.locale}`
      : undefined,
  );
  const demo = state.mode === "live" && !offline ? undefined : offline
    ? state.downloads.find((d) => d.id === id)
    : documents.find((x) => x.id === id) || documents[0];
  const [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  if (live.loading) return <StateView type="loading" />;
  if (live.error)
    return (
      <StateView
        type="error"
        detail={live.error.message}
        onRetry={live.retry}
      />
    );
  const doc = state.mode === "live" && !offline ? live.data : null;
  if (!doc && !demo) return <StateView />;
  const title = doc?.title || (demo ? b(demo.title) : "");
  const code = doc?.code || demo?.code || "";
  const favorite = state.favorites.includes(id || "complaint");
  const ack =
    state.acknowledged.includes(id || "complaint") || !!doc?.acknowledged;
  const version = doc?.version || demo?.version || "";
  const documentDate = doc ? doc.effective_date : demo?.date;
  const saveOffline = () => {
    let saved: Document | undefined = demo;
    if (doc) {
      saved = {
        id: String(doc.id),
        title: bi(doc.title, doc.title),
        category: bi(doc.code, doc.code),
        code: doc.code,
        version: doc.version,
        date: doc.effective_date,
        type: "sop",
        purpose: bi(doc.sections.purpose || "", doc.sections.purpose || ""),
        steps: [bi(doc.sections.procedure || "", doc.sections.procedure || "")],
        safety: bi(
          doc.sections.safety_notes || "",
          doc.sections.safety_notes || "",
        ),
      };
    }
    if (saved) {
      update((s) => ({
        ...s,
        downloads: [...s.downloads.filter((x) => x.id !== saved!.id), saved!],
      }));
      notify("downloadConfirm");
    }
  };
  return (
    <View style={styles.stack}>
      <Row>
        <Badge label={code} />
        <Badge label={offline ? t("downloaded") : t("published")} green />
      </Row>
      <T variant="display" style={{ fontSize: 32 }}>
        {title}
      </T>
      <Card>
        <Row style={{ justifyContent: "space-between" }}>
          <T variant="small">
            {t("version")} {version}
          </T>
          {!!documentDate && !Number.isNaN(Date.parse(documentDate)) && <T variant="small">
            {new Intl.DateTimeFormat(
              state.locale === "ar" ? "ar-SA" : "en-GB",
              { dateStyle: "medium" },
            ).format(new Date(documentDate))}
          </T>}
        </Row>
        {(state.mode === "demo" || !!doc?.owner) && <T variant="small" style={{ color: c.muted }}>
          {t("owner")}: {doc ? doc.owner : t("ownerName")}
        </T>}
      </Card>
      <Section title={t("purpose")} />
      <T style={{ fontSize: 16, lineHeight: 27 }}>
        {doc?.sections.purpose || (demo ? b(demo.purpose) : "")}
      </T>
      <Section title={t("procedure")} />
      {doc ? (
        <Card>
          <T style={{ fontSize: 16, lineHeight: 29 }}>
            {doc.sections.procedure || Object.values(doc.sections).join("\n\n")}
          </T>
        </Card>
      ) : (
        demo?.steps.map((step, i) => (
          <Card key={i}>
            <Row style={{ alignItems: "flex-start" }}>
              <T variant="number" style={{ color: c.accent, fontSize: 23 }}>
                {String(i + 1).padStart(2, "0")}
              </T>
              <T style={{ flex: 1, fontSize: 16, lineHeight: 27 }}>{b(step)}</T>
            </Row>
          </Card>
        ))
      )}
      <Card style={{ backgroundColor: c.soft }}>
        <Row>
          <Icon name="shield-checkmark-outline" color={c.accent} />
          <T style={{ fontWeight: "600" }}>{t("safety")}</T>
        </Row>
        <T>{doc?.sections.safety_notes || (demo ? b(demo.safety) : "")}</T>
      </Card>
      {!!error && <T style={{ color: c.accent }}>{error}</T>}
      <Button
        label={t(ack ? "acknowledged" : "acknowledge")}
        disabled={ack || offline}
        busy={busy}
        onPress={async () => {
          if (state.mode === "live" && api) {
            setBusy(true);
            try {
              await api.request("acknowledge", "POST", { id: Number(id) });
              live.retry();
            } catch (e) {
              setError(e instanceof Error ? e.message : t("apiFailure"));
            } finally {
              setBusy(false);
            }
          } else
            update((s) => ({
              ...s,
              acknowledged: [
                ...new Set([...s.acknowledged, id || "complaint"]),
              ],
            }));
        }}
      />
      <Button
        label={t(favorite ? "removeFavorite" : "bookmark")}
        secondary
        onPress={() =>
          update((s) => ({
            ...s,
            favorites: favorite
              ? s.favorites.filter((x) => x !== id)
              : [...s.favorites, id || "complaint"],
          }))
        }
      />
      <Button
        label={t(offline ? "removeDownload" : "download")}
        secondary
        onPress={() =>
          offline
            ? confirm("deleteConfirm", () => {
                update((s) => ({
                  ...s,
                  downloads: s.downloads.filter((x) => x.id !== id),
                }));
                go("downloads");
              })
            : saveOffline()
        }
      />
      <Button
        label={t("share")}
        secondary
        onPress={() =>
          void Share.share({
            message: `ALTUS · ${title} · ${code} · ${t("version")} ${version}\n${state.mode === "demo" ? t("demoNotice") : t("live")}`,
          })
        }
      />
      <T variant="small" style={{ color: c.muted }}>
        {t("downloadNotice")}
      </T>
    </View>
  );
}
export function Assistant() {
  const { t, state, update, api, b } = useApp();
  const c = useTheme();
  const [question, setQuestion] = useState(""),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const ask = async (q: string) => {
    if (!q.trim() || busy) return;
    setError("");
    setBusy(true);
    let answer: Answer = {
      question: q,
      text: t("noAnswer"),
      time: new Date().toISOString(),
    };
    try {
      if (state.mode === "live" && api) {
        const result = await api.request<JsonRecord>(
          "assistant?locale=" + state.locale,
          "POST",
          { question: q },
        );
        answer.text = String(result.answer || result.message || t("noAnswer"));
        answer.sources = Array.isArray(result.sources)
          ? result.sources.map((source: JsonRecord) => ({
              ref: String(source.ref || ""),
              title: String(source.title || ""),
              version: String(source.version || source.version_label || ""),
              type: String(source.source_type || "knowledge"),
              id: String(source.source_id || ""),
            }))
          : [];
      } else {
        const found = documents.find(
          (d) =>
            ((q.toLowerCase().includes("complaint") || q.includes("شكوى")) &&
              d.id === "complaint") ||
            ((q.toLowerCase().includes("room") || q.includes("غرفة")) &&
              d.id === "room-entry") ||
            ((q.toLowerCase().includes("lost") || q.includes("مفقود")) &&
              d.id === "lost-found"),
        );
        if (found) {
          answer.text = found.steps.map((step) => b(step)).join("\n\n");
          answer.sourceId = found.id;
        }
      }
      update((s) => ({ ...s, answers: [...s.answers, answer].slice(-25) }));
      setQuestion("");
    } catch (e) {
      setError(e instanceof Error ? e.message : t("apiFailure"));
    } finally {
      setBusy(false);
    }
  };
  return (
    <View style={styles.stack}>
      <Card style={{ backgroundColor: c.soft }}>
        <Icon name="sparkles-outline" size={32} color={c.accent} />
        <T variant="title">{t("askTitle")}</T>
        <T style={{ color: c.muted }}>{t("aiNote")}</T>
      </Card>
      {state.answers.slice(-5).map((a, i) => (
        <View key={i} style={{ gap: 10 }}>
          <View
            style={{
              alignSelf: "flex-end",
              maxWidth: "92%",
              padding: 14,
              borderRadius: 15,
              backgroundColor: c.text,
            }}
          >
            <T style={{ color: c.bg }}>{a.question}</T>
          </View>
          <Card>
            <T style={{ lineHeight: 25 }}>{a.text}</T>
            {a.sourceId && (
              <ListRow
                title={`${t("source")}: ${documents.find((x) => x.id === a.sourceId)?.code}`}
                subtitle={b(documents.find((x) => x.id === a.sourceId)!.title)}
                onPress={() => go("sop", a.sourceId)}
              />
            )}
            {a.sources?.map((source) => (
              <ListRow
                key={`${source.ref}-${source.id}`}
                title={`${source.ref} · ${source.title}`}
                subtitle={`${t("source")}${source.version ? ` · ${t("version")} ${source.version}` : ""}`}
                onPress={() =>
                  go(source.type === "lesson" ? "lesson" : "sop", source.id)
                }
              />
            ))}
          </Card>
        </View>
      ))}
      {state.answers.length === 0 && (
        <Button
          label={t("suggestion")}
          secondary
          onPress={() => void ask(t("suggestion"))}
        />
      )}
      <Field
        label={t("ask")}
        value={question}
        onChangeText={setQuestion}
        multiline
        style={{ minHeight: 90 }}
      />
      {!!error && <T style={{ color: c.accent }}>{error}</T>}
      <Button
        label={t("submit")}
        disabled={!question.trim()}
        busy={busy}
        onPress={() => void ask(question)}
        icon="arrow-up"
      />
      <T variant="small" style={{ color: c.muted }}>
        {t(state.mode === "demo" ? "demoAi" : "aiNote")}
      </T>
      <ListRow
        title={t("history")}
        onPress={() => go("conversation-history")}
      />
    </View>
  );
}
