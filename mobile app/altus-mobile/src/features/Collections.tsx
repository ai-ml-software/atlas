import React, { useState } from "react";
import { View, Linking, Share } from "react-native";
import { useApp } from "../state/AppProvider";
import {
  T,
  Card,
  Button,
  ListRow,
  Badge,
  Icon,
  Search,
  go,
  useTheme,
  styles,
  StateView,
} from "../components/ui";
import { documents, people, domains, notifications } from "../domain/demo";
import { ScreenSpec } from "../domain/screens";
import { JsonRecord } from "../domain/models";
import { useResource } from "../services/useResource";
import { LiveDetail } from "./LiveDetail";

export function Collection({ spec }: { spec: ScreenSpec }) {
  const { state, t, b, update, api, notify } = useApp();
  const c = useTheme();
  const [query, setQuery] = useState("");
  const resource = useResource<JsonRecord[]>(
    spec.source ? `${spec.source}?locale=${state.locale}` : undefined,
  );
  const header = (
    <>
      <Search value={query} onChange={setQuery} />
    </>
  );
  const rows = (
    items: { title: string; subtitle?: string; id?: string; target?: string }[],
  ) =>
    items
      .filter((x) =>
        (x.title + " " + x.subtitle)
          .toLowerCase()
          .includes(query.toLowerCase()),
      )
      .map((x, i) => (
        <ListRow
          key={x.id || i}
          title={x.title}
          subtitle={x.subtitle}
          onPress={x.target ? () => go(x.target!, x.id) : undefined}
        />
      ));
  if (spec.id === "downloads")
    return (
      <View style={styles.stack}>
        <Card>
          <Icon name="cloud-download-outline" size={30} color={c.accent} />
          <T variant="number">{state.downloads.length}</T>
          <T>{t("storage")}</T>
        </Card>
        <T variant="small" style={{ color: c.muted }}>
          {t("downloadNotice")}
        </T>
        {state.downloads.length ? (
          rows(
            state.downloads.map((d) => ({
              id: d.id,
              title: b(d.title),
              subtitle: `${d.code} · ${t("version")} ${d.version}`,
              target: "download-detail",
            })),
          )
        ) : (
          <StateView detail={t("storageEmpty")} />
        )}
      </View>
    );
  if (spec.id === "support")
    return (
      <View style={styles.stack}>
        <Card>
          <Icon name="chatbubbles-outline" size={31} color={c.accent} />
          <T variant="title">{t("support")}</T>
          <T>{t("introBody")}</T>
        </Card>
        <Button label={t("newTicket")} onPress={() => go("new-ticket")} />
        {rows([
          { title: t("help"), target: "faq" },
          { title: t("history"), target: "tickets" },
          { title: t("issue"), target: "report-issue" },
          { title: t("securityNote"), target: "security" },
        ])}
      </View>
    );
  if (spec.id === "faq")
    return (
      <View style={styles.stack}>
        {[1, 2, 3].map((i) => (
          <Card key={i}>
            <T variant="title">{t("faq" + i)}</T>
            <T style={{ color: c.muted }}>{t("faq" + i + "body")}</T>
          </Card>
        ))}
        <Button label={t("newTicket")} onPress={() => go("new-ticket")} />
      </View>
    );
  if (spec.id === "tickets" || spec.id === "discussion") {
    const items = spec.id === "tickets" ? state.tickets : state.posts;
    return (
      <View style={styles.stack}>
        <Button
          label={t(spec.id === "tickets" ? "newTicket" : "newDiscussion")}
          onPress={() =>
            go(spec.id === "tickets" ? "new-ticket" : "new-discussion")
          }
        />
        {state.mode === "live" ? (
          <StateView detail={t("liveUnavailable")} />
        ) : items.length ? (
          rows(
            items.map((p) => ({
              id: p.id,
              title: p.title,
              subtitle: `${p.status} · ${p.createdAt.slice(0, 10)}`,
              target:
                spec.id === "tickets" ? "ticket-detail" : "discussion-detail",
            })),
          )
        ) : (
          <StateView detail={t("historyEmpty")} />
        )}
      </View>
    );
  }
  if (spec.id === "saved-searches")
    return (
      <View style={styles.stack}>
        {state.searches.length ? (
          rows(state.searches.map((x) => ({ title: x, target: "search" })))
        ) : (
          <StateView />
        )}
      </View>
    );
  if (spec.id === "conversation-history")
    return (
      <View style={styles.stack}>
        {state.answers.length ? (
          state.answers.map((a, i) => (
            <Card key={i}>
              <T style={{ fontWeight: "600" }}>{a.question}</T>
              <T>{a.text}</T>
              {a.sourceId && (
                <Button
                  label={t("source")}
                  secondary
                  onPress={() => go("sop", a.sourceId)}
                />
              )}
            </Card>
          ))
        ) : (
          <StateView />
        )}
      </View>
    );
  if (spec.id === "assessment-history")
    return (
      <View style={styles.stack}>
        {state.mode === "live" ? (
          <StateView detail={t("liveUnavailable")} />
        ) : state.quizScores.length ? (
          rows(
            state.quizScores.map((s, i) => ({
              title: `${t("score")}: ${s}%`,
              subtitle: `${t("history")} ${i + 1}`,
              target: "quiz-result",
            })),
          )
        ) : (
          <StateView />
        )}
      </View>
    );
  if (spec.id === "acknowledgments")
    return (
      <View style={styles.stack}>
        {state.acknowledged.length ? (
          rows(
            documents
              .filter((d) => state.acknowledged.includes(d.id))
              .map((d) => ({
                id: d.id,
                title: b(d.title),
                subtitle: `${t("acknowledged")} · ${d.version}`,
                target: "sop",
              })),
          )
        ) : (
          <StateView />
        )}
      </View>
    );
  if (spec.id === "categories")
    return (
      <View style={styles.stack}>
        {header}
        {rows(domains.map((d) => ({ title: b(d), target: "catalog" })))}
      </View>
    );
  if (spec.id === "calendar" || spec.id === "announcements")
    return (
      <View style={styles.stack}>
        {state.mode === "live" ? (
          <StateView detail={t("liveUnavailable")} />
        ) : (
          <>
            <Card>
              <Badge label={t("demo")} />
              <T variant="display" style={{ fontSize: 27 }}>
                {t("eventTitle")}
              </T>
              <T>{t("eventBody")}</T>
              <Button label={t("viewAll")} onPress={() => go("event")} />
            </Card>
            <ListRow
              title={t("notifications")}
              onPress={() => go("notifications")}
            />
          </>
        )}
      </View>
    );
  if (state.mode === "live") {
    if (!spec.source) return <StateView detail={t("liveUnavailable")} />;
    if (resource.loading) return <StateView type="loading" />;
    if (resource.error)
      return (
        <StateView
          type={resource.error.status === 403 ? "restricted" : "error"}
          detail={resource.error.message}
          onRetry={resource.retry}
        />
      );
    const data = resource.data || [];
    return (
      <View style={styles.stack}>
        {header}
        {data.length ? (
          rows(
            data.map((d) => ({
              id: String(d.id || d.verification_code || ""),
              title: String(
                d.title ||
                  d.title_en ||
                  d[
                    state.locale === "ar"
                      ? "subject_title_ar"
                      : "subject_title_en"
                  ] ||
                  d.name ||
                  `${d.first_name || ""} ${d.last_name || ""}`.trim() ||
                  "",
              ),
              subtitle: String(d.status || d.readiness || d.code || ""),
              target:
                spec.id === "team"
                  ? "learner-detail"
                  : spec.id === "certificates"
                    ? "certificate"
                    : spec.id === "notifications"
                      ? "notification-detail"
                      : spec.id === "actions"
                        ? "action-detail"
                        : undefined,
            })),
          )
        ) : (
          <StateView />
        )}
        {spec.id === "notifications" && (
          <Button
            label={t("markRead")}
            onPress={async () => {
              try {
                await api?.request("notifications_read", "POST", {});
                resource.retry();
              } catch {
                notify("apiFailure");
              }
            }}
          />
        )}
      </View>
    );
  }
  let items: {
    title: string;
    subtitle?: string;
    id?: string;
    target?: string;
  }[] = [];
  switch (spec.id) {
    case "team":
    case "users":
    case "assessor-queue":
      items = people.map((p) => ({
        id: p.id,
        title: b(p.title),
        subtitle: b(p.subtitle) + " · " + b(p.status!),
        target: spec.id === "assessor-queue" ? "practical" : "learner-detail",
      }));
      break;
    case "notifications":
      items = notifications.map((n) => ({
        id: n.id,
        title: b(n.title),
        subtitle:
          b(n.subtitle) +
          " · " +
          t(state.read.includes(n.id) ? "read" : "unread"),
        target: "notification-detail",
      }));
      break;
    case "certificates":
      items = [
        {
          title: t("certificateTitle"),
          subtitle: t("demoCertificate"),
          target: "certificate",
        },
      ];
      break;
    case "resources":
      items = documents.slice(0, 2).map((d) => ({
        id: d.id,
        title: b(d.title),
        subtitle: d.code,
        target: "sop",
      }));
      break;
    case "actions":
    case "gaps":
      items = [
        {
          title:
            state.locale === "ar"
              ? "التدرّب على استعادة رضا الضيف"
              : "Practice service recovery",
          subtitle:
            state.locale === "ar"
              ? "أولوية مرتفعة · مراجعة عملية مطلوبة"
              : "High priority · Practical review required",
          target: "action-detail",
        },
        {
          title:
            state.locale === "ar"
              ? "ثقة في التواصل"
              : "Communication confidence",
          subtitle: t("needsAttention"),
          target: "competencies",
        },
      ];
      break;
    case "clients":
      items = [
        {
          title: "ALTUS Demo Client",
          subtitle: t("demoNotice"),
          target: "properties",
        },
      ];
      break;
    case "properties":
      items = [
        {
          title: state.property,
          subtitle: t("demoNotice"),
          target: "branding",
        },
        {
          title:
            state.locale === "ar"
              ? "منشأة جدة التجريبية"
              : "Demonstration property · Jeddah",
          subtitle: t("demoNotice"),
          target: "branding",
        },
      ];
      break;
    case "roles":
      items = [
        {
          title: t("learner"),
          subtitle:
            state.locale === "ar"
              ? "التعلّم والمعرفة الشخصية"
              : "Own learning and operational knowledge",
          target: "role",
        },
        { title: t("supervisor"), subtitle: t("team"), target: "role" },
        { title: t("manager"), subtitle: t("reports"), target: "role" },
        {
          title: t("instructor"),
          subtitle: t("contentGovernance"),
          target: "role",
        },
        { title: t("platformAdmin"), subtitle: t("admin"), target: "role" },
      ];
      break;
    case "content":
      items = [
        ...state.drafts.map((d) => ({
          id: d.id,
          title: d.title,
          subtitle: t("courseDraft"),
          target: "version-detail",
        })),
        ...documents.map((d) => ({
          id: d.id,
          title: b(d.title),
          subtitle: `${d.code} · ${t("published")}`,
          target: "sop",
        })),
      ];
      break;
    case "versions":
      items = [
        {
          title: b(documents[0].title),
          subtitle: `2.2 · ${state.reviewStatus}`,
          target: "version-detail",
        },
        {
          title: b(documents[2].title),
          subtitle: `3.0 · ${t("published")}`,
          target: "sop",
          id: "lost-found",
        },
      ];
      break;
    case "audit":
      items = [
        { title: t("contentGovernance"), subtitle: t("demoScope") },
        ...state.drafts.map((d) => ({
          title: d.title,
          subtitle: `${t("courseDraft")} · ${d.createdAt.slice(0, 10)}`,
        })),
        ...state.assignments.map((a) => ({
          title: a.title,
          subtitle: t("assign"),
        })),
      ];
      break;
    default:
      items = documents.map((d) => ({
        id: d.id,
        title: b(d.title),
        subtitle: d.code,
        target: "sop",
      }));
  }
  return (
    <View style={styles.stack}>
      {header}
      {items.length ? rows(items) : <StateView />}
      {spec.id === "notifications" && (
        <Button
          label={t("markRead")}
          onPress={() =>
            update((s) => ({ ...s, read: notifications.map((n) => n.id) }))
          }
        />
      )}
      {spec.id === "content" && (
        <Button
          label={
            state.locale === "ar" ? "إنشاء مسودة دورة" : "Create course draft"
          }
          onPress={() => go("course-editor")}
        />
      )}
      {spec.id === "team" && (
        <>
          <Button label={t("assign")} onPress={() => go("assign")} />
          {state.assignments.map((a) => (
            <Card key={a.id}>
              <T>{a.title}</T>
              <T variant="small">{a.body}</T>
            </Card>
          ))}
        </>
      )}
    </View>
  );
}
export function Detail({ spec, id }: { spec: ScreenSpec; id?: string }) {
  const { state, t, b, update, notify, confirm } = useApp();
  const c = useTheme();
  if (
    state.mode === "live" &&
    [
      "certificate",
      "learner-detail",
      "notification-detail",
      "action-detail",
    ].includes(spec.id)
  )
    return <LiveDetail kind={spec.id} id={id} />;
  if (["privacy", "terms", "about"].includes(spec.id))
    return (
      <View style={styles.stack}>
        <Card>
          <Icon
            name={
              spec.id === "about"
                ? "business-outline"
                : "shield-checkmark-outline"
            }
            size={36}
            color={c.accent}
          />
          <T style={{ fontSize: 17, lineHeight: 29 }}>
            {t(
              spec.id === "about"
                ? "aboutBody"
                : spec.id === "privacy"
                  ? "privacyDescription"
                  : "consentBody",
            )}
          </T>
        </Card>
        <T>{t("policyNote")}</T>
        <Button
          label={t("openWebsite")}
          secondary
          onPress={() =>
            void Linking.openURL(
              `${state.base}/${state.locale === "ar" ? "ar" : "en"}/${spec.id === "about" ? "knowledge-performance" : spec.id}`,
            )
          }
        />
        <T variant="small" style={{ color: c.muted }}>
          {t("appVersion")}
        </T>
      </View>
    );
  if (spec.id === "transcript")
    return (
      <Card>
        <T style={{ fontSize: 17, lineHeight: 31 }}>{t("readLesson")}</T>
      </Card>
    );
  if (spec.id === "ticket-detail" || spec.id === "discussion-detail") {
    const post = (
      spec.id === "ticket-detail" ? state.tickets : state.posts
    ).find((p) => p.id === id);
    if (!post) return <StateView />;
    return (
      <View style={styles.stack}>
        <Badge label={post.status} />
        <T variant="display" style={{ fontSize: 30 }}>
          {post.title}
        </T>
        <T style={{ lineHeight: 28 }}>{post.body}</T>
        <T variant="small">
          {new Intl.DateTimeFormat(state.locale === "ar" ? "ar-SA" : "en-GB", {
            dateStyle: "medium",
            timeStyle: "short",
          }).format(new Date(post.createdAt))}
        </T>
        {post.attachment && (
          <Badge label={`${t("attachment")}: ${post.attachment}`} />
        )}
        <T>{t("demoScope")}</T>
        <Button
          label={t("share")}
          secondary
          onPress={() =>
            void Share.share({
              message: `${post.title}\n${post.body}\n${t("demoNotice")}`,
            })
          }
        />
      </View>
    );
  }
  if (spec.id === "event")
    return (
      <View style={styles.stack}>
        <Badge label={t("demo")} />
        <T variant="display" style={{ fontSize: 32 }}>
          {t("eventTitle")}
        </T>
        <T style={{ fontSize: 17, lineHeight: 30 }}>{t("eventBody")}</T>
        <Button label={t("scan")} onPress={() => go("qr-attendance")} />
      </View>
    );
  if (spec.id === "notification-detail") {
    const n = notifications.find((x) => x.id === id) || notifications[0];
    return (
      <View style={styles.stack}>
        <T variant="title">{b(n.title)}</T>
        <T>{b(n.subtitle)}</T>
        <Button
          label={t("continue")}
          onPress={() => {
            update((s) => ({ ...s, read: [...new Set([...s.read, n.id])] }));
            go(
              n.destination || "home",
              n.destination === "sop" ? "lost-found" : "guest-service",
            );
          }}
        />
      </View>
    );
  }
  if (spec.id === "certificate")
    return (
      <View style={styles.stack}>
        <Card style={{ borderColor: "#D9C6A3", padding: 30 }}>
          <Icon name="ribbon-outline" size={45} color={c.accent} />
          <T variant="label">ALTUS GULF</T>
          <T variant="display" style={{ fontSize: 32 }}>
            {t("certificateTitle")}
          </T>
          <T style={{ lineHeight: 28 }}>{t("certificateBody")}</T>
          <Badge label={t("demoCertificate")} />
        </Card>
        <Button
          label={t("share")}
          secondary
          onPress={() =>
            void Share.share({
              message: `ALTUS · ${t("certificateTitle")}\n${t("demoCertificate")}`,
            })
          }
        />
        <Button label={t("verify")} onPress={() => go("certificate-verify")} />
      </View>
    );
  if (spec.id === "learner-detail") {
    const p = people.find((x) => x.id === id) || people[0];
    return (
      <View style={styles.stack}>
        <Card>
          <T variant="display" style={{ fontSize: 30 }}>
            {b(p.title)}
          </T>
          <T>{b(p.subtitle)}</T>
          <Badge label={b(p.status!)} />
        </Card>
        <ListRow title={t("learningPlan")} onPress={() => go("learning")} />
        <ListRow title={t("competencies")} onPress={() => go("competencies")} />
        <ListRow title={t("certificates")} onPress={() => go("certificates")} />
        <Button label={t("assign")} onPress={() => go("assign", id)} />
      </View>
    );
  }
  if (spec.id === "version-detail")
    return (
      <View style={styles.stack}>
        <Badge label={state.reviewStatus} />
        <Card>
          <T variant="title">{t("contentGovernance")}</T>
          <T style={{ lineHeight: 28 }}>{t("reviewBody")}</T>
        </Card>
        <T variant="small" style={{ color: c.muted }}>
          {t("demoApproval")}
        </T>
        <Button
          label={t("approve")}
          onPress={() =>
            confirm("demoApproval", () => {
              update((s) => ({ ...s, reviewStatus: t("published") }));
              notify("saved");
            })
          }
        />
        <Button
          label={t("reject")}
          secondary
          onPress={() => {
            update((s) => ({ ...s, reviewStatus: t("needsAttention") }));
            notify("saved");
          }}
        />
      </View>
    );
  return (
    <View style={styles.stack}>
      <Card>
        <T variant="title">{t("actions")}</T>
        <T>{t("readinessNote")}</T>
        <Badge label={t("needsAttention")} />
      </Card>
      <ListRow
        title={t("continueLearning")}
        onPress={() => go("course", "guest-service")}
      />
      <ListRow title={t("issue")} onPress={() => go("report-issue")} />
    </View>
  );
}
