import React, { useState } from "react";
import { View, Switch, Share, Pressable, Linking } from "react-native";
import * as DocumentPicker from "expo-document-picker";
import { router } from "expo-router";
import { useApp } from "../state/AppProvider";
import {
  T,
  Card,
  Button,
  Field,
  Row,
  Section,
  ListRow,
  Bar,
  Badge,
  Icon,
  go,
  useTheme,
  styles,
  StateView,
} from "../components/ui";
import { courses, people, domains } from "../domain/demo";
import { ScreenSpec } from "../domain/screens";
import { JsonRecord, Course, LocalPost, bi } from "../domain/models";
import { useResource } from "../services/useResource";
import { LocalePicker } from "./Onboarding";

/** Workspace screens for managers, executives, trainers and admins — only those the role permits. */
const roleScreens = [
  ["management", "people-outline", "management"],
  ["team", "person-add-outline", "team"],
  ["reports", "stats-chart-outline", "reports"],
  ["gaps", "alert-circle-outline", "gaps"],
  ["assign", "send-outline", "assign"],
  ["competencies", "ribbon-outline", "competencies"],
  ["readiness", "checkmark-done-outline", "readiness"],
  ["admin", "shield-checkmark-outline", "admin"],
] as const;
export function RoleShortcuts() {
  const { t, can } = useApp();
  const visible = roleScreens.filter(([id]) => can(id));
  if (!visible.length) return null;
  return (
    <View style={{ gap: 4 }}>
      <Section title={t("yourWorkspace")} />
      {visible.map(([id, icon, label]) => (
        <ListRow key={id} icon={icon} title={t(label)} onPress={() => go(id)} />
      ))}
    </View>
  );
}
export function Profile() {
  const { state, t, identity, confirm, logout, can } = useApp();
  const c = useTheme();
  return (
    <View style={styles.stack}>
      <Card>
        <Row>
          <View
            style={{
              width: 63,
              height: 63,
              borderRadius: 32,
              backgroundColor: c.soft,
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <T variant="number">{state.name[0]}</T>
          </View>
          <View style={{ flex: 1, gap: 4 }}>
            <T variant="title">{state.name}</T>
            <T variant="small" style={{ color: c.muted }}>
              {identity?.email || state.property}
            </T>
            <Badge label={t(state.mode === "demo" ? "demo" : "live")} />
          </View>
        </Row>
      </Card>
      {[
        ["edit-profile", "name"],
        ["progress", "progress"],
        ["certificates", "certificates"],
        ["settings", "settings"],
        ["downloads", "downloads"],
        ["favorites", "favorites"],
        ["notifications", "notifications"],
        ["notification-settings", "trainingReminders"],
        ["calendar", "calendar"],
        ["discussion", "discussion"],
        ["support", "support"],
        ["security", "securityNote"],
        ["about", "about"],
      ]
        .filter(([id]) => can(id))
        .map(([id, label]) => (
          <ListRow key={id} title={t(label)} onPress={() => go(id)} />
        ))}
      {state.mode === "demo" && (
        <ListRow title={t("changeRole")} onPress={() => go("role")} />
      )}
      <RoleShortcuts />
      <Button
        label={t("signOut")}
        secondary
        onPress={() =>
          confirm("confirmSignOut", () => {
            void logout().then(() => router.replace("/sign-in"));
          })
        }
      />
      <T variant="small" style={{ color: c.muted }}>
        {t("appVersion")}
      </T>
    </View>
  );
}
export function Settings({ spec }: { spec: ScreenSpec }) {
  const { state, patch, t, confirm, reset, remote } = useApp();
  const c = useTheme();
  const [showCountry, setShowCountry] = useState(false);
  const support = remote?.support;
  const contacts = support
    ? ([
        ["mail-outline", support.email, `mailto:${support.email}`],
        ["call-outline", support.phone, `tel:${support.phone.replace(/[^+0-9]/g, "")}`],
        ["globe-outline", support.url, support.url],
      ] as const).filter(([, v]) => !!v)
    : [];
  const preferences =
    spec.id === "notification-settings"
      ? (["reminders", "sopUpdates"] as const)
      : (["dark", "largeText", "reduceMotion"] as const);
  const labels = {
    dark: "darkMode",
    largeText: "largeText",
    reduceMotion: "reduceMotion",
    reminders: "trainingReminders",
    sopUpdates: "sopUpdates",
  };
  return (
    <View style={styles.stack}>
      {spec.id === "settings" && contacts.length > 0 && (
        <View style={{ gap: 4 }}>
          <T variant="label" style={{ color: c.muted }}>
            {t("support")}
          </T>
          {contacts.map(([icon, label, href]) => (
            <ListRow
              key={href}
              icon={icon}
              title={label}
              onPress={() => void Linking.openURL(href)}
            />
          ))}
        </View>
      )}
      {preferences.map((pref) => (
        <Row
          key={pref}
          style={{
            justifyContent: "space-between",
            paddingVertical: 15,
            borderBottomWidth: 1,
            borderBottomColor: c.border,
          }}
        >
          <T style={{ flex: 1 }}>{t(labels[pref])}</T>
          <Switch
            accessibilityLabel={t(labels[pref])}
            value={state[pref]}
            onValueChange={(value) => patch({ [pref]: value })}
            trackColor={{ false: c.border, true: "#2E7D5A" }}
          />
        </Row>
      ))}
      {spec.id === "settings" && (
        <>
          <ListRow
            title={t("language")}
            subtitle={state.locale.toUpperCase()}
            onPress={() => go("language")}
          />
          <ListRow
            title={t("country")}
            subtitle={state.country}
            onPress={() => setShowCountry(!showCountry)}
          />
          {showCountry && <LocalePicker country />}
          <ListRow title={t("privacy")} onPress={() => go("privacy")} />
          <ListRow title={t("terms")} onPress={() => go("terms")} />
          <ListRow title={t("securityNote")} onPress={() => go("security")} />
          <ListRow
            title={t("deleteAccountNote")}
            onPress={() => go("delete-account")}
          />
          {state.mode === "demo" && (
            <Button
              label={t("restore")}
              secondary
              onPress={() => confirm("confirmReset", reset)}
            />
          )}
        </>
      )}
      <T variant="small" style={{ color: c.muted }}>
        {t("demoScope")}
      </T>
    </View>
  );
}
export function Dashboard({ spec }: { spec: ScreenSpec }) {
  const { state, t, b, can } = useApp();
  const c = useTheme();
  const resource = useResource<JsonRecord | JsonRecord[]>(
    spec.source ? `${spec.source}?locale=${state.locale}` : undefined,
  );
  const manage = ["management", "reports", "gaps"].includes(spec.id),
    central = ["admin", "portfolio"].includes(spec.id);
  const links = central
    ? [
        ["clients", "Client organizations", "العملاء والمؤسسات"],
        ["properties", "Properties & brands", "المنشآت والعلامات"],
        ["users", "Users & access", "المستخدمون والصلاحيات"],
        ["roles", "Roles & permissions", "الأدوار والصلاحيات"],
        ["content", "Content governance", "حوكمة المحتوى"],
        ["versions", "Versions & approvals", "الإصدارات والموافقات"],
        ["portfolio", "Portfolio analytics", "تحليلات المحفظة"],
        ["audit", "Audit activity", "نشاط التدقيق"],
        ["support", t("support"), t("support")],
      ]
    : manage
      ? [
          ["team", t("team"), t("team")],
          ["assign", t("assign"), t("assign")],
          ["reports", t("reports"), t("reports")],
          ["gaps", t("gaps"), t("gaps")],
          ["assessor-queue", "Practical assessment", "التقييم العملي"],
          ["branding", "Property branding", "علامة المنشأة"],
          ["certificate-verify", "Verify certificates", "تحقق من الشهادات"],
        ]
      : [
          ["learning", t("learningPlan"), t("learningPlan")],
          ["actions", t("actions"), t("actions")],
          ["progress", t("progress"), t("progress")],
        ];
  return (
    <View style={styles.stack}>
      <Badge label={t(state.mode === "demo" ? "teamSample" : "live")} />
      {state.mode === "demo" ? (
        <>
          <Card style={{ padding: 24 }}>
            <T variant="label" style={{ color: c.muted }}>
              {t(
                spec.id === "competencies"
                  ? "competencies"
                  : spec.id === "readiness"
                    ? "readiness"
                    : "overview",
              )}
            </T>
            <T variant="number" style={{ fontSize: 57, color: c.accent }}>
              {central ? "3" : spec.id === "readiness" ? "2 / 4" : "76%"}
            </T>
            <Bar value={76} />
            <T>{t("readinessNote")}</T>
          </Card>
          <Row>
            <Card style={{ flex: 1 }}>
              <T variant="number" style={{ color: c.green }}>
                1
              </T>
              <T variant="small">{t("ready")}</T>
            </Card>
            <Card style={{ flex: 1 }}>
              <T variant="number" style={{ color: c.accent }}>
                1
              </T>
              <T variant="small">{t("needsAttention")}</T>
            </Card>
          </Row>
          {spec.id === "competencies" &&
            domains.slice(0, 5).map((d, i) => (
              <Card key={d.en}>
                <Row style={{ justifyContent: "space-between" }}>
                  <T>{b(d)}</T>
                  <T>{[82, 64, 91, 72, 58][i]}%</T>
                </Row>
                <Bar value={[82, 64, 91, 72, 58][i]} />
              </Card>
            ))}
        </>
      ) : resource.loading ? (
        <StateView type="loading" />
      ) : resource.error ? (
        <StateView
          type="error"
          detail={resource.error.message}
          onRetry={resource.retry}
        />
      ) : resource.data ? (
        <LiveData data={resource.data} />
      ) : (
        <StateView detail={t("liveUnavailable")} />
      )}
      <Section title={t("yourNextStep")} />
      {links.filter(([id]) => can(id)).map(([id, en, ar]) => (
        <ListRow
          key={id}
          title={state.locale === "ar" ? ar : en}
          onPress={() => go(id)}
        />
      ))}
      {manage && (
        <Button
          label={t("export")}
          secondary
          onPress={() =>
            void Share.share({
              message:
                state.mode === "demo"
                  ? t("reportBody")
                  : JSON.stringify(resource.data, null, 2),
            })
          }
        />
      )}
    </View>
  );
}
export function LiveData({ data }: { data: JsonRecord | JsonRecord[] }) {
  const c = useTheme();
  const raw = Array.isArray(data)
    ? data
    : Object.entries(data).flatMap(([key, value]) =>
        Array.isArray(value)
          ? value.map((x) =>
              typeof x === "object" && x ? x : { title: String(x) },
            )
          : [{ title: key, value }],
      );
  return (
    <View style={styles.stack}>
      {raw.map((item, i) => (
        <Card key={String(item.id || item.code || i)}>
          {Object.entries(item)
            .filter(
              ([, v]) => v !== null && v !== undefined && typeof v !== "object",
            )
            .map(([key, value]) => (
              <Row key={key} style={{ alignItems: "flex-start" }}>
                <T variant="small" style={{ color: c.muted, flex: 1 }}>
                  {key.replace(/_/g, " ")}
                </T>
                <T variant="small" style={{ flex: 1 }}>
                  {String(value)}
                </T>
              </Row>
            ))}
        </Card>
      ))}
    </View>
  );
}
export function Form({ spec }: { spec: ScreenSpec }) {
  const { state, t, b, update, patch, demo, api, notify, confirm } = useApp();
  const c = useTheme();
  const [title, setTitle] = useState(
      ["edit-profile", "first-setup"].includes(spec.id)
        ? state.name
        : spec.id === "branding"
          ? state.property
          : "",
    ),
    [body, setBody] = useState(""),
    [due, setDue] = useState("2026-10-30"),
    [selectedPerson, setSelectedPerson] = useState("employee-1"),
    [selectedCourse, setSelectedCourse] = useState("guest-service"),
    [attachment, setAttachment] = useState<string | undefined>(),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const livePeople = useResource<JsonRecord[]>(
    state.mode === "live" && spec.id === "assign" ? "people" : undefined,
  );
  const liveCourses = useResource<Course[]>(
    state.mode === "live" && spec.id === "assign" ? "courses" : undefined,
  );
  const personal = ["first-setup", "edit-profile"].includes(spec.id);
  const save = async () => {
    if (
      !title.trim() ||
      (!personal &&
        spec.id !== "branding" &&
        spec.id !== "assign" &&
        !body.trim())
    ) {
      setError(t("invalid"));
      return;
    }
    if (
      spec.id === "assign" &&
      (!/^\d{4}-\d{2}-\d{2}$/.test(due) ||
        Number.isNaN(Date.parse(due)) ||
        Date.parse(due) < Date.now())
    ) {
      setError(t("dateInvalid"));
      return;
    }
    if (
      state.mode === "live" &&
      spec.id === "assign" &&
      (!/^\d+$/.test(selectedPerson) || !/^\d+$/.test(selectedCourse))
    ) {
      setError(t("invalid"));
      return;
    }
    setError("");
    setBusy(true);
    try {
      if (state.mode === "live" && spec.id !== "branding") {
        if (spec.id === "assign" && api) {
          await api.request("assign", "POST", {
            title,
            user_id: Number(selectedPerson),
            course_id: Number(selectedCourse),
            due_at: due,
          });
          notify("saved");
          go("team");
          return;
        }
        setError(t("liveUnavailable"));
        return;
      }
      if (personal) {
        if (spec.id === "first-setup") demo();
        patch({ name: title.trim() });
        if (spec.next) go(spec.next);
        else {
          notify("saved");
          go("profile");
        }
        return;
      }
      if (spec.id === "branding") {
        patch({ property: title.trim() });
        notify("saved");
        return;
      }
      const item: LocalPost = {
        id: `${spec.id}-${Date.now()}`,
        title: title.trim(),
        body: body.trim(),
        createdAt: new Date().toISOString(),
        status: "Open",
        attachment,
      };
      if (spec.id === "assign") {
        item.body = `${selectedPerson} · ${selectedCourse} · ${due}`;
        update((s) => ({ ...s, assignments: [item, ...s.assignments] }));
        notify("created");
        go("team");
      } else if (spec.id === "course-editor") {
        item.status = "Draft";
        update((s) => ({ ...s, drafts: [item, ...s.drafts] }));
        notify("courseSaved");
        go("content");
      } else if (["new-discussion", "practical"].includes(spec.id)) {
        update((s) => ({ ...s, posts: [item, ...s.posts] }));
        notify("created");
        go("discussion");
      } else {
        update((s) => ({ ...s, tickets: [item, ...s.tickets] }));
        notify("created");
        go("ticket-detail", item.id);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : t("apiFailure"));
    } finally {
      setBusy(false);
    }
  };
  const file = async () => {
    try {
      const r = await DocumentPicker.getDocumentAsync({
        type: ["image/*", "application/pdf"],
        copyToCacheDirectory: true,
      });
      if (!r.canceled) {
        if ((r.assets[0].size || 0) > 10 * 1024 * 1024) {
          notify("attachmentTooLarge");
          return;
        }
        setAttachment(r.assets[0].name);
      }
    } catch {
      notify("apiFailure");
    }
  };
  return (
    <View style={styles.stack}>
      {spec.id === "delete-account" && (
        <Card>
          <T>{t("deleteAccountNote")}</T>
        </Card>
      )}
      <Field
        label={t(
          personal ? "name" : spec.id === "branding" ? "previewName" : "title",
        )}
        value={title}
        onChangeText={setTitle}
        maxLength={190}
      />
      {!personal && spec.id !== "branding" && spec.id !== "assign" && (
        <Field
          label={t("details")}
          value={body}
          onChangeText={setBody}
          multiline
          style={{ minHeight: 140, textAlignVertical: "top" }}
          maxLength={4000}
        />
      )}
      {spec.id === "assign" && (
        <>
          <Section title={t("selectPerson")} />
          {(state.mode === "live"
            ? (livePeople.data || []).map((p) => ({
                id: String(p.id),
                title: bi(
                  `${p.first_name} ${p.last_name}`,
                  `${p.first_name} ${p.last_name}`,
                ),
              }))
            : people
          ).map((p) => (
            <ListRow
              key={p.id}
              title={b(p.title)}
              onPress={() => setSelectedPerson(p.id)}
              trailing={
                <Icon
                  name={
                    selectedPerson === p.id
                      ? "checkmark-circle"
                      : "ellipse-outline"
                  }
                />
              }
            />
          ))}
          <Section title={t("selectCourse")} />
          {(state.mode === "live" ? liveCourses.data || [] : courses).map(
            (course) => (
              <ListRow
                key={course.id}
                title={b(course.title)}
                onPress={() => setSelectedCourse(course.id)}
                trailing={
                  <Icon
                    name={
                      selectedCourse === course.id
                        ? "checkmark-circle"
                        : "ellipse-outline"
                    }
                  />
                }
              />
            ),
          )}
          {(livePeople.error || liveCourses.error) && (
            <StateView type="error" />
          )}
          <Field
            label={t("dueDate")}
            value={due}
            onChangeText={setDue}
            keyboardType="numbers-and-punctuation"
          />
        </>
      )}
      {spec.id === "branding" && (
        <>
          <Card style={{ borderColor: state.accent }}>
            <Badge label={t("preview")} />
            <T variant="display">{title}</T>
            <T>{t("aboutBody")}</T>
            <Button label={t("continue")} onPress={() => go("home")} />
          </Card>
          <Row style={{ flexWrap: "wrap" }}>
            {[
              ["#C45B2F", "copper"],
              ["#2E7D5A", "emerald"],
              ["#2A2F35", "charcoal"],
            ].map(([color, label]) => (
              <Pressable
                key={color}
                accessibilityRole="button"
                accessibilityLabel={t(label)}
                onPress={() => patch({ accent: color })}
                style={{
                  padding: 13,
                  borderWidth: state.accent === color ? 3 : 1,
                  borderColor: color,
                  borderRadius: 12,
                  minHeight: 48,
                }}
              >
                <T variant="small">{t(label)}</T>
              </Pressable>
            ))}
          </Row>
        </>
      )}
      {["new-ticket", "report-issue", "practical"].includes(spec.id) && (
        <>
          <Button label={t("attach")} secondary onPress={() => void file()} />
          {attachment && <Badge label={`${t("attachment")}: ${attachment}`} />}
        </>
      )}
      {!!error && <T style={{ color: c.accent }}>{error}</T>}
      <Button
        label={t(spec.id === "assign" ? "assign" : "save")}
        onPress={() =>
          spec.id === "delete-account"
            ? confirm("deleteAccountNote", () => void save())
            : void save()
        }
        busy={busy}
      />
      <T variant="small" style={{ color: c.muted }}>
        {t(state.mode === "live" ? "sourceUnavailable" : "demoScope")}
      </T>
    </View>
  );
}
