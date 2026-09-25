// Minimal OpenAI-compatible chat server for the AI end-to-end tests.
// It answers deterministically from the system prompt, so assertions are stable.
import http from 'node:http';

const PORT = 8765;

function reply(system, user) {
  const s = system || '';
  if (/Rewrite the user's rough request as a precise brief/.test(s)) {
    return `BRIEF: Audience: hotel owners in Riyadh. Goal: explain pre-opening support. Request: ${user.slice(0, 120)}`;
  }
  if (/"meta_title"/.test(s)) {
    return JSON.stringify({ meta_title: 'Pre-opening hotel support in Riyadh | Altus', meta_description: 'How Altus Advisory prepares independent hotels in Riyadh for opening day: people, standards, systems and readiness evidence.' });
  }
  if (/"items": \[\{"q"/.test(s)) {
    return JSON.stringify({ heading: 'Frequently asked questions', items: [
      { q: 'What is pre-opening support?', a: 'A structured programme that prepares a hotel team, standards and systems before the first guest arrives.' },
      { q: 'How long does it take?', a: 'Typically twelve to sixteen weeks, depending on the property size and the recruitment timeline.' },
      { q: 'Who is it for?', a: 'Independent and branded hotels in Saudi Arabia that want an evidence-based opening.' },
    ] });
  }
  if (/"heading": string, "body"/.test(s)) {
    return '```json\n' + JSON.stringify({ heading: 'Pre-opening support in Riyadh', body: '<p>We prepare your team and standards before opening day.</p>', items: [{ title: 'People', text: 'Recruit and train' }] }) + '\n```';
  }
  if (/"questions"/.test(s)) {
    return JSON.stringify({ questions: [{ q: 'When do you greet a guest?', options: ['Within 10 seconds', 'After 5 minutes', 'Never', 'Only VIPs'], correct: 1, why: 'Standard.' }] });
  }
  if (/short applied lesson/.test(s)) {
    return '<h2>Welcoming the guest</h2><p>Greet every guest within ten seconds.</p>';
  }
  if (/Translate into Modern Standard Arabic/.test(s)) {
    return 'مرحبا بكم في الفندق';
  }
  return 'Improved text: ' + user.slice(0, 200);
}

const server = http.createServer((req, res) => {
  if (req.url === '/health') { res.writeHead(200); return res.end('ok'); }
  if (req.method === 'GET' && req.url.startsWith('/v1/models')) {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ data: [{ id: 'mock-writer' }, { id: 'mock-fast' }] }));
  }
  if (req.method === 'POST' && req.url.startsWith('/v1/chat/completions')) {
    let body = '';
    req.on('data', (c) => (body += c));
    req.on('end', () => {
      let msgs = [];
      try { msgs = JSON.parse(body).messages || []; } catch { /* ignore */ }
      const system = (msgs.find((m) => m.role === 'system') || {}).content || '';
      const user = [...msgs].reverse().find((m) => m.role === 'user')?.content || '';
      const content = reply(system, String(user));
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({
        id: 'mock-1', object: 'chat.completion', model: 'mock-writer',
        choices: [{ index: 0, finish_reason: 'stop', message: { role: 'assistant', content } }],
        usage: { prompt_tokens: 42, completion_tokens: 17, total_tokens: 59 },
      }));
    });
    return;
  }
  res.writeHead(404); res.end('not found');
});

server.listen(PORT, '127.0.0.1', () => console.log(`mock AI listening on ${PORT}`));
