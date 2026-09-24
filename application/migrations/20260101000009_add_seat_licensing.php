<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * B2B seat licensing: how a hotel group buys training.
 *
 * The consumer model already in the Academy LMS sells one course to one
 * person through a cart. That is the wrong shape for the customer this
 * academy is built around. A hotel group does not buy a course, it buys the
 * right to put a number of its staff through a catalogue for a period, and
 * what it is actually paying for is the ability to prove afterwards that the
 * staff were trained.
 *
 * So the unit of sale is a seat, not a course:
 *
 *  - ha_license      what an organisation bought: which catalogue, how many
 *                    seats, over what term, at what price.
 *  - ha_license_seat  which person is occupying a seat right now. Seats are
 *                    reclaimable, because hospitality turnover is high and a
 *                    group that loses a seat permanently every time somebody
 *                    resigns will not renew.
 *  - ha_license_scope which courses or programmes the licence covers, so a
 *                    group can buy housekeeping for one property without
 *                    buying the whole catalogue.
 *
 * Seat counts are enforced in application code, not by a constraint: the
 * useful behaviour when a group runs out of seats is a clear message and an
 * upsell, not a database error.
 */
class Migration_Add_seat_licensing extends Ha_migration {

    public function up() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_license (
                id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
                reference          VARCHAR(40) NOT NULL,
                organization_id    INT UNSIGNED NOT NULL,
                plan               ENUM('department','property','group','enterprise') NOT NULL DEFAULT 'property',
                seats_purchased    INT UNSIGNED NOT NULL DEFAULT 0,
                starts_on          DATE NOT NULL,
                ends_on            DATE NOT NULL,
                price_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                price_currency     CHAR(3) NOT NULL DEFAULT 'SAR',
                billing_period     ENUM('annual','monthly','one_off') NOT NULL DEFAULT 'annual',
                purchase_order_ref VARCHAR(80) NULL,
                status             ENUM('draft','active','suspended','expired','cancelled') NOT NULL DEFAULT 'draft',
                notes              TEXT NULL,
                created_at         DATETIME NOT NULL,
                updated_at         DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ha_license_reference (reference),
                KEY ix_ha_license_org (organization_id, status),
                KEY ix_ha_license_term (starts_on, ends_on)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_license_seat (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                license_id    INT UNSIGNED NOT NULL,
                user_id       INT UNSIGNED NULL,
                property_id   INT UNSIGNED NULL,
                department_id INT UNSIGNED NULL,
                assigned_at   DATETIME NULL,
                released_at   DATETIME NULL,
                status        ENUM('open','assigned','released') NOT NULL DEFAULT 'open',
                created_at    DATETIME NOT NULL,
                updated_at    DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ix_ha_license_seat_license (license_id, status),
                KEY ix_ha_license_seat_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_license_scope (
                id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                license_id   INT UNSIGNED NOT NULL,
                scope_type   ENUM('catalogue','program','course','department') NOT NULL DEFAULT 'catalogue',
                scope_id     INT UNSIGNED NULL,
                created_at   DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ix_ha_license_scope_license (license_id),
                KEY ix_ha_license_scope_target (scope_type, scope_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down() {
        $this->db->query("DROP TABLE IF EXISTS ha_license_scope");
        $this->db->query("DROP TABLE IF EXISTS ha_license_seat");
        $this->db->query("DROP TABLE IF EXISTS ha_license");
    }
}
