<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy - core identity, RBAC and organization structure.
 * Plan sections 2, 5, 37, 38, 45.
 */
class Migration_Create_core_tables extends Ha_migration {

    private $tables = array('ha_role_permission', 'ha_permission', 'ha_user_role', 'ha_role',
        'ha_profile', 'ha_job_role', 'ha_department', 'ha_property', 'ha_organization');

    public function up() {
        $e = $this->engine;

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_organization (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            legal_name VARCHAR(190) NULL,
            registration_no VARCHAR(80) NULL,
            country VARCHAR(80) NOT NULL DEFAULT 'Saudi Arabia',
            city VARCHAR(120) NULL,
            contact_name VARCHAR(190) NULL,
            contact_email VARCHAR(190) NULL,
            contact_phone VARCHAR(60) NULL,
            logo VARCHAR(255) NULL,
            locale ENUM('en','ar') NOT NULL DEFAULT 'en',
            timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Riyadh',
            status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_org_slug (slug),
            KEY ix_ha_org_status (status)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_property (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            brand VARCHAR(120) NULL,
            property_type ENUM('hotel','resort','serviced_apartment','restaurant','cafe','catering','other') NOT NULL DEFAULT 'hotel',
            city VARCHAR(120) NOT NULL,
            region VARCHAR(120) NULL,
            country VARCHAR(80) NOT NULL DEFAULT 'Saudi Arabia',
            room_count INT UNSIGNED NOT NULL DEFAULT 0,
            operational_status ENUM('operational','pre_opening','renovation','closed') NOT NULL DEFAULT 'operational',
            contact_email VARCHAR(190) NULL,
            contact_phone VARCHAR(60) NULL,
            manager_user_id INT UNSIGNED NULL,
            status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_property_slug (slug),
            KEY ix_ha_property_org (organization_id),
            KEY ix_ha_property_city (city),
            CONSTRAINT fk_ha_property_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_department (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NOT NULL,
            property_id INT UNSIGNED NULL,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            head_user_id INT UNSIGNED NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_dept_org (organization_id),
            KEY ix_ha_dept_property (property_id),
            KEY ix_ha_dept_code (code),
            CONSTRAINT fk_ha_dept_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_dept_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_job_role (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            code VARCHAR(60) NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            level ENUM('entry','associate','senior','supervisor','assistant_manager','manager','director') NOT NULL DEFAULT 'entry',
            description_en TEXT NULL,
            description_ar TEXT NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_job_org (organization_id),
            KEY ix_ha_job_dept (department_id),
            UNIQUE KEY uq_ha_job_code (code),
            CONSTRAINT fk_ha_job_dept FOREIGN KEY (department_id) REFERENCES ha_department (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_role (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(120) NOT NULL,
            name_ar VARCHAR(120) NOT NULL,
            description_en VARCHAR(255) NULL,
            description_ar VARCHAR(255) NULL,
            scope ENUM('system','organization','property','department','self') NOT NULL DEFAULT 'self',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_role_code (code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_permission (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(120) NOT NULL,
            module VARCHAR(60) NOT NULL,
            label_en VARCHAR(190) NOT NULL,
            label_ar VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_perm_code (code),
            KEY ix_ha_perm_module (module)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_role_permission (
            role_id INT UNSIGNED NOT NULL,
            permission_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            CONSTRAINT fk_ha_rp_role FOREIGN KEY (role_id) REFERENCES ha_role (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_rp_perm FOREIGN KEY (permission_id) REFERENCES ha_permission (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_user_role (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            role_id INT UNSIGNED NOT NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_user_role (user_id, role_id, organization_id, property_id, department_id),
            KEY ix_ha_ur_user (user_id),
            CONSTRAINT fk_ha_ur_role FOREIGN KEY (role_id) REFERENCES ha_role (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_profile (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            employee_no VARCHAR(60) NULL,
            job_title_en VARCHAR(190) NULL,
            job_title_ar VARCHAR(190) NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            job_role_id INT UNSIGNED NULL,
            manager_user_id INT UNSIGNED NULL,
            locale ENUM('en','ar') NOT NULL DEFAULT 'en',
            timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Riyadh',
            hire_date DATE NULL,
            mobile VARCHAR(60) NULL,
            status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_profile_user (user_id),
            UNIQUE KEY uq_ha_profile_employee (organization_id, employee_no),
            KEY ix_ha_profile_property (property_id),
            KEY ix_ha_profile_dept (department_id),
            KEY ix_ha_profile_manager (manager_user_id),
            CONSTRAINT fk_ha_profile_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_profile_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE SET NULL,
            CONSTRAINT fk_ha_profile_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE SET NULL,
            CONSTRAINT fk_ha_profile_dept FOREIGN KEY (department_id) REFERENCES ha_department (id) ON DELETE SET NULL,
            CONSTRAINT fk_ha_profile_job FOREIGN KEY (job_role_id) REFERENCES ha_job_role (id) ON DELETE SET NULL
        )" . $e);
    }

    public function down() {
        $this->drop($this->tables);
    }
}
