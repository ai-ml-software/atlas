<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Third-party video sources for lessons, with the attribution and the
 * verification record that using somebody else's video obliges.
 *
 * ha_lesson already carries video_source and video_url, which is enough to
 * play a video and not nearly enough to be responsible about one. A hosted
 * video that belongs to another channel brings three problems the schema has
 * to hold, not a spreadsheet:
 *
 *  - Credit. The channel and the video's own URL travel with the lesson so
 *    the learner can see whose work they are watching, the same obligation
 *    the photography already meets through ha_media.
 *  - Rot. A third-party video can be deleted, set private or age-gated at any
 *    time, and the site finds out when a learner hits a black box. verified_at
 *    and status exist so a scheduled re-check can mark a source dead and the
 *    lesson can fall back to its written content.
 *  - Licence. youtube_license records what the uploader declared, so a later
 *    review can tell a Creative Commons video apart from a standard one.
 *
 * Nothing here caches video content. It records where a video is, who made
 * it and whether it still resolves.
 */
class Migration_Add_lesson_video_sources extends Ha_migration {

    public function up() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_lesson_video_source (
                id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                lesson_id         INT UNSIGNED NOT NULL,
                provider          ENUM('youtube','vimeo','dailymotion','url','upload') NOT NULL DEFAULT 'youtube',
                video_id          VARCHAR(64) NOT NULL,
                watch_url         VARCHAR(500) NOT NULL,
                embed_url         VARCHAR(500) NOT NULL,
                title             VARCHAR(255) NULL,
                author_name       VARCHAR(190) NULL,
                author_url        VARCHAR(500) NULL,
                thumbnail_url     VARCHAR(500) NULL,
                duration_seconds  INT UNSIGNED NOT NULL DEFAULT 0,
                youtube_license   VARCHAR(60) NULL,
                is_owned          TINYINT(1) NOT NULL DEFAULT 0,
                status            ENUM('live','unavailable','unchecked') NOT NULL DEFAULT 'unchecked',
                last_checked_at   DATETIME NULL,
                last_error        VARCHAR(255) NULL,
                created_at        DATETIME NOT NULL,
                updated_at        DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ha_lesson_video_lesson (lesson_id),
                KEY ix_ha_lesson_video_status (status),
                KEY ix_ha_lesson_video_provider (provider, video_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down() {
        $this->db->query("DROP TABLE IF EXISTS ha_lesson_video_source");
    }
}
