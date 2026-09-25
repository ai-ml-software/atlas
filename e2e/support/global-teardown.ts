import { sql } from './db';

/**
 * After the run: switch the mock AI provider off again, remove the records the
 * suite created (all prefixed "E2E"/"e2e-"), and clear device lists.
 */
export default async function globalTeardown() {
  sql(`UPDATE ha_ai_provider SET enabled=0 WHERE slug='e2e_mock'`);
  // Pages created by the CMS tests
  sql(`DELETE s FROM ha_page_section s JOIN ha_page p ON p.id=s.page_id WHERE p.code LIKE 'e2e-%'`);
  sql(`DELETE r FROM ha_page_revision r JOIN ha_page p ON p.id=r.page_id WHERE p.code LIKE 'e2e-%'`);
  sql(`DELETE m FROM ha_seo_metadata m JOIN ha_page p ON p.id=m.entity_id AND m.entity_type='page' WHERE p.code LIKE 'e2e-%'`);
  sql(`DELETE t FROM ha_page_translation t JOIN ha_page p ON p.id=t.page_id WHERE p.code LIKE 'e2e-%'`);
  sql(`DELETE FROM ha_page WHERE code LIKE 'e2e-%'`);
  // Modules created by the CMS tests are archived (never hard-deleted: they may hold progress)
  sql(`UPDATE ha_course c JOIN ha_course_translation t ON t.course_id=c.id AND t.locale='en'
       SET c.status='archived' WHERE t.title LIKE 'E2E %'`);
  sql(`DELETE FROM ha_certificate WHERE certificate_no LIKE 'E2E-%'`);
  sql(`UPDATE users SET sessions='[]' WHERE sessions IS NOT NULL AND sessions <> '[]'`);
}
