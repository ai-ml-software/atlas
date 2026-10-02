import React, { useEffect } from "react";
import { Image, Linking, View } from "react-native";
import { useEvent } from "expo";
import { useVideoPlayer, VideoView } from "expo-video";
import { T, Card, Button, StateView } from "./ui";
import { useApp } from "../state/AppProvider";
export interface Media {
  type: string;
  url: string;
  title: string;
}
function Player({
  media,
  lessonId,
  position = 0,
}: {
  media: Media;
  lessonId: string;
  position?: number;
}) {
  const { api, t } = useApp();
  const player = useVideoPlayer(media.url, (p) => {
    p.timeUpdateEventInterval = 10;
    p.currentTime = position;
  });
  const { status, error } = useEvent(player, "statusChange", {
    status: player.status,
  });
  useEffect(() => {
    let previous = position;
    const subscription = player.addListener("timeUpdate", (event) => {
      const delta = Math.max(0, Math.min(10, event.currentTime - previous));
      previous = event.currentTime;
      if (api && delta > 0)
        void api
          .request("lesson_track", "POST", {
            id: Number(lessonId),
            seconds: Math.floor(delta),
            position: Math.floor(event.currentTime),
          })
          .catch(() => {});
    });
    return () => subscription.remove();
  }, [api, player, lessonId, position]);
  return (
    <View style={{ gap: 10 }}>
      {status === "error" ? (
        <StateView type="error" detail={error?.message || t("apiFailure")} />
      ) : (
        <VideoView
          player={player}
          nativeControls
          fullscreenOptions={{ enable: true }}
          style={{
            height: media.type === "audio" ? 100 : 220,
            width: "100%",
            borderRadius: 15,
          }}
        />
      )}
      {status === "loading" && <StateView type="loading" />}
    </View>
  );
}
export function LessonMedia({
  items,
  lessonId,
  position,
}: {
  items: Media[];
  lessonId: string;
  position?: number;
}) {
  return (
    <View style={{ gap: 16 }}>
      {items.map((item, i) => (
        <Card key={i}>
          <T variant="title">{item.title}</T>
          {item.type === "image" ? (
            <Image
              source={{ uri: item.url }}
              resizeMode="contain"
              style={{ width: "100%", height: 220 }}
            />
          ) : ["video", "audio"].includes(item.type) ? (
            <Player media={item} lessonId={lessonId} position={position} />
          ) : (
            <DocumentLink media={item} />
          )}
        </Card>
      ))}
    </View>
  );
}
function DocumentLink({ media }: { media: Media }) {
  const { t } = useApp();
  return (
    <Button
      label={t("resources")}
      secondary
      onPress={() => void Linking.openURL(media.url)}
    />
  );
}
