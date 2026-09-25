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

export function userId(email: string): number {
  return Number(one(`SELECT id FROM users WHERE email = '${email.replace(/'/g, "''")}'`));
}
