import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import path from 'node:path';

/**
 * Tiny MySQL helper for test fixtures. It only ever talks to the LOCAL database
 * named in application/config/database.local.php and refuses to run otherwise,
 * because application/config/database.php points at production.
 */
const APP = path.resolve(__dirname, '..', '..');
const LOCAL_CFG = path.join(APP, 'application', 'config', 'database.local.php');
const MYSQL = process.env.HKP_MYSQL || 'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysql.exe';

export function localDatabase(): string {
  if (!existsSync(LOCAL_CFG)) {
    throw new Error('database.local.php is missing: refusing to run e2e tests (database.php is production).');
  }
  const src = readFileSync(LOCAL_CFG, 'utf8');
  const host = /'hostname'\s*=>\s*'([^']+)'/.exec(src)?.[1];
  const db = /^\s*'database'\s*=>\s*'([^']+)'/m.exec(src)?.[1];
  if (!db || !host || !/^(127\.0\.0\.1|localhost)$/.test(host)) {
    throw new Error(`Refusing to run: database.local.php host "${host}" is not local.`);
  }
  if (process.env.HKP_TEST_DATABASE) {
    if (process.env.HKP_TEST_DATABASE !== 'atlas_hospitality_test'
        || process.env.HKP_BASE_URL !== 'http://127.0.0.1:8099/') {
      throw new Error('The isolated database requires the dedicated loopback Playwright server.');
    }
    return 'atlas_hospitality_test';
  }
  return db;
}

/** Runs SQL and returns rows as arrays of strings (tab separated output). */
export function sql(query: string): string[][] {
  const out = execFileSync(MYSQL, ['-uroot', '-N', '-B', '--default-character-set=utf8mb4', localDatabase(), '-e', query], { encoding: 'utf8' });
  return out.split(/\r?\n/).filter(Boolean).map((l) => l.split('\t'));
}

export function one(query: string): string | undefined {
  return sql(query)[0]?.[0];
}

/**
 * Copies the matching rows aside and returns a function that puts them back exactly,
 * so a spec that publishes to shared live content leaves the local database as it found it.
 *   const restore = preserve([['ha_page_translation', 'page_id=6'], ['ha_page_section', 'page_id=6']]);
 *   try { ... } finally { restore(); }
 */
export function preserve(rows: [table: string, where: string][]): () => void {
  const tag = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
  const copies = rows.map(([table, where], i) => {
    if (!/^[a-z_]+$/.test(table)) throw new Error(`Unsafe table name ${table}`);
    const copy = `zz_e2e_keep_${tag}_${i}`;
    sql(`CREATE TABLE ${copy} AS SELECT * FROM ${table} WHERE ${where}`);
    return { table, where, copy };
  });
  return () => {
    sql(copies.map(({ table, where, copy }) => `DELETE FROM ${table} WHERE ${where}; INSERT INTO ${table} SELECT * FROM ${copy}; DROP TABLE ${copy};`).join(' '));
  };
}

/** preserve() for a CMS page: translations, sections and private live draft, plus the page row's publication fields. */
export function keepPage(id: number): () => void {
  const fields = sql(`SELECT studio_enabled, IFNULL(published_at,'NULL'), updated_at FROM ha_page WHERE id=${id}`)[0];
  const rows = preserve([['ha_page_translation', `page_id=${id}`], ['ha_page_section', `page_id=${id}`], ['ha_website_draft', `page_id=${id}`]]);
  return () => {
    rows();
    const published = fields[1] === 'NULL' ? 'NULL' : `'${fields[1]}'`;
    sql(`UPDATE ha_page SET studio_enabled=${Number(fields[0])}, published_at=${published}, updated_at='${fields[2]}' WHERE id=${id}`);
  };
}

/**
 * Environment for a CLI worker (php index.php publisher_cli ...) that must see the same
 * database as the site under test: the isolated test DB on the loopback router, otherwise
 * the local DB from database.local.php (the CLI's default).
 */
export function workerEnv(): NodeJS.ProcessEnv {
  const env = { ...process.env };
  if (localDatabase() === 'atlas_hospitality_test') env.ALTUS_PUBLISHER_TEST_DB = 'atlas_hospitality_test';
  else delete env.ALTUS_PUBLISHER_TEST_DB;
  return env;
}

export function userId(email: string): number {
  return Number(one(`SELECT id FROM users WHERE email = '${email.replace(/'/g, "''")}'`));
}
