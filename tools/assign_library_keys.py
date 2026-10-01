"""Freeze curriculum identity keys so moving lessons never moves learner history."""
from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
manifest = json.loads((root / 'application/seeds/library_support/manifest.json').read_text(encoding='utf-8'))
codes = sorted({s['course_code'] for s in manifest['sources']})
for code in codes:
    path = root / 'application/seeds/library' / (code.removeprefix('dy-') + '.json')
    course = json.loads(path.read_text(encoding='utf-8-sig'))
    seen, position = set(), 0
    for ci, chapter in enumerate(course['locales']['en']['chapters']):
        chapter.setdefault('source_key', f'{code}:section-{ci+1:03}')
        if chapter['source_key'] in seen: raise ValueError('Duplicate section identity')
        seen.add(chapter['source_key'])
        for lesson in chapter['lessons']:
            position += 1
            lesson.setdefault('source_key', f'{code}:lesson-{position:03}')
            if lesson['source_key'] in seen: raise ValueError('Duplicate lesson identity')
            seen.add(lesson['source_key'])
            for qi, question in enumerate(lesson['quiz']['questions']):
                question.setdefault('source_key', f'{lesson["source_key"]}:q{qi+1:03}')
                if question['source_key'] in seen: raise ValueError('Duplicate question identity')
                seen.add(question['source_key'])
    path.write_text(json.dumps(course, ensure_ascii=False, indent=2)+'\n', encoding='utf-8')
print(f'Frozen identities in {len(codes)} course bundles.')
