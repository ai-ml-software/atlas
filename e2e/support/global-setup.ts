import { localDatabase, sql } from './db';

/**
 * Before the run:
 *  - prove we are on a local database (throws otherwise);
 *  - clear the legacy LMS device lists so repeated runs never hit the
 *    5-device limit (which asks for an emailed code that is not sent locally);
 *  - point the e2e mock AI provider at the mock server and enable it for the run.
 */
export default async function globalSetup() {
  const db = localDatabase();
  console.log(`[e2e] database: ${db}`);
  sql(`UPDATE users SET sessions='[]' WHERE sessions IS NOT NULL AND sessions <> '[]'`);
  sql(`INSERT INTO ha_ai_provider (slug, enabled, base_url, settings_json, created_at, updated_at)
       SELECT 'e2e_mock', 1, 'http://127.0.0.1:8765/v1', '{"name":"E2E Mock","api_style":"openai"}', NOW(), NOW()
       FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM ha_ai_provider WHERE slug='e2e_mock')`);
  sql(`UPDATE ha_ai_provider SET enabled=1, base_url='http://127.0.0.1:8765/v1' WHERE slug='e2e_mock'`);
  // Fixture certificate for verification / revocation flows (removed in teardown).
  sql(`DELETE FROM ha_certificate WHERE certificate_no LIKE 'E2E-%'`);
  sql(`INSERT INTO ha_certificate (certificate_no, verification_code, user_id, organization_id, property_id,
         subject_title_en, subject_title_ar, recipient_name_en, recipient_name_ar, issued_at, expires_at, status, locale, created_at, updated_at)
       SELECT 'E2E-FOA-2026-000001', 'E2E-VERIFY-0001', u.id, p.organization_id, p.property_id,
         'Front Office Agent', 'موظف مكتب أمامي', CONCAT(u.first_name,' ',u.last_name), CONCAT(u.first_name,' ',u.last_name),
         NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 'issued', 'en', NOW(), NOW()
       FROM users u JOIN ha_profile p ON p.user_id=u.id WHERE u.email='demo.learner2@altusdemo.sa'`);
  for (const m of ['mock-writer', 'mock-fast']) {
    sql(`INSERT INTO ha_ai_model (provider_slug, model_id, label, capabilities, is_available, fetched_at)
         SELECT 'e2e_mock', '${m}', '${m}', 'chat', 1, NOW() FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM ha_ai_model WHERE provider_slug='e2e_mock' AND model_id='${m}')`);
  }
}
