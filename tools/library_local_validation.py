"""Capture local data invariants before/after library deployment. Never uses server config."""
from pathlib import Path
import argparse
import hashlib
import json
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
MYSQL = Path('C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe')


def connection():
    source = (ROOT / 'application/config/database.local.php').read_text(encoding='utf-8-sig')
    values = {}
    for key in ('hostname', 'username', 'database', 'password'):
        match = re.search(r"'" + key + r"'\s*=>\s*'([^']*)'", source)
        if not match: raise RuntimeError('Cannot identify local database setting: ' + key)
        values[key] = match.group(1)
    if values['hostname'] not in ('localhost', '127.0.0.1'): raise RuntimeError('Refusing a nonlocal database')
    if values['password']: raise RuntimeError('Use an authenticated local MySQL option file; password arguments are disabled')
    return values


def sql(query):
    cfg = connection()
    args = [str(MYSQL), '--host='+cfg['hostname'], '--user='+cfg['username'], '-N', '-B', '--default-character-set=utf8mb4', cfg['database'], '-e', query]
    return subprocess.run(args, check=True, capture_output=True).stdout.decode('utf-8')


def snapshot():
    queries = {
        'other_lms_courses': "SELECT * FROM course WHERE meta_keywords IS NULL OR meta_keywords NOT LIKE 'ha:dy-%' ORDER BY id",
        'other_academy_courses': "SELECT * FROM ha_course WHERE code NOT LIKE 'dy-%' ORDER BY id",
        'library_lesson_ids': "SELECT c.code,l.id,l.course_id,l.section_id,l.assessment_id,l.sort_order FROM ha_lesson l JOIN ha_course c ON c.id=l.course_id WHERE c.code LIKE 'dy-%' ORDER BY l.id",
        'library_course_visibility': "SELECT id,code,status,published_at FROM ha_course WHERE code LIKE 'dy-%' ORDER BY id",
        'library_lms_visibility': "SELECT id,status,meta_keywords FROM course WHERE meta_keywords LIKE 'ha:dy-%' ORDER BY id",
        'enrollments': 'SELECT * FROM ha_enrollment ORDER BY id',
        'progress': 'SELECT * FROM ha_lesson_progress ORDER BY id',
        'theory_attempts': 'SELECT * FROM ha_assessment_attempt ORDER BY id',
        'legacy_enrollments': 'SELECT * FROM enrol ORDER BY id',
    }
    result = {}
    for key, query in queries.items():
        try: result[key] = hashlib.sha256(sql(query).encode()).hexdigest()
        except subprocess.CalledProcessError:
            # Some deployments do not include every historical attempt table.
            table = query.split('FROM ')[1].split()[0]
            exists = sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='"+table+"'").strip()
            if exists != '0': raise
            result[key] = 'table-not-present'
    return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('mode', choices=['capture','compare','backup']); parser.add_argument('file')
    args = parser.parse_args(); dest = Path(args.file); dest.parent.mkdir(parents=True, exist_ok=True)
    if args.mode == 'backup':
        cfg = connection(); exe = MYSQL.with_name('mysqldump.exe')
        subprocess.run([str(exe),'--host='+cfg['hostname'],'--user='+cfg['username'],'--single-transaction','--skip-lock-tables','--no-tablespaces','--result-file='+str(dest.resolve()),cfg['database']],check=True,capture_output=True)
        print('Local database backup saved.')
    elif args.mode == 'capture':
        dest.write_text(json.dumps(snapshot(), indent=2)+'\n', encoding='utf-8'); print('Captured preservation checks.')
    else:
        before = json.loads(dest.read_text(encoding='utf-8')); after = snapshot()
        differences = [k for k in before if before[k] != after.get(k)]
        print(json.dumps({'preserved':not differences,'changed':differences}))
        if differences: raise SystemExit(1)
