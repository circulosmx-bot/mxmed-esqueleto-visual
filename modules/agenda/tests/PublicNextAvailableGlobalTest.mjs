import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source = fs.readFileSync(new URL('../../../assets/js/public-profile-next-available.js', import.meta.url), 'utf8');
const scope = {window: {}, Intl, Date, URLSearchParams, DOMException, fetch};
vm.runInNewContext(source, scope);
const slot = (date, time = '09:00', end = '09:30') => ({start_at: `${date} ${time}:00`, end_at: `${date} ${end}:00`});
const friday = ['18:30', '19:00', '19:30'].map((time, i) => slot('2026-09-11', time, ['19:00', '19:30', '20:00'][i]));
const dataset = {
  2: [...friday, slot('2026-09-14'), slot('2026-09-15'), slot('2026-09-16'), slot('2026-09-17')],
  3: [slot('2026-09-12'), slot('2026-09-12'), slot('2026-09-19'), slot('2026-09-26'), slot('2026-10-03')],
  4: [slot('2026-09-12'), slot('2026-12-09'), slot('2026-12-10')], // tie in a different office; last slot outside 90 days
  99: [slot('2026-09-11', '08:00')]
};
const requests = [];
const fetcher = async url => {
  const query = new URL(url, 'http://localhost').searchParams;
  const office = query.get('consultorio_id');
  requests.push({office, from: query.get('start_date')});
  const values = dataset[office].filter(s => s.start_at.slice(0, 10) >= query.get('start_date'));
  const dates = [...new Set(values.map(s => s.start_at.slice(0, 10)))].slice(0, 3);
  return {ok: true, json: async () => ({ok: true, meta: {consultorio_id_used: office}, data: {
    days: dates.map(date => ({date, slots: values.filter(s => s.start_at.startsWith(date))}))
  }})};
};
const search = scope.window.MxmedPublicGlobalAvailability({doctorId: '1', consultorios: {'2': 'Star Médica', '3': 'MAC Norte', '4': 'Otra sede'}, bookingUrl: '/booking', fetcher, now: new Date('2026-09-11T06:00:00Z')});
const pages = [];
for (let offset = 0; ; offset += 3) {
  const page = await search.page(offset);
  pages.push(page);
  if (!page.hasNext) break;
}
const all = pages.flatMap(p => Array.from(p.slots));
assert.deepEqual(all.slice(0, 3).map(s => s.start_at), friday.map(s => s.start_at));
assert.equal(all[3].consultorio_id, '3');
assert.equal(all[3].start_at, '2026-09-12 09:00:00');
assert.equal(all[3].end_at, '2026-09-12 09:30:00');
assert.equal(all[3].consultorio_name, 'MAC Norte');
assert.equal(all[4].consultorio_id, '4');
assert.ok(all.findIndex(s => s.start_at.startsWith('2026-09-14')) > 3);
assert.ok(all.every((s, i) => i === 0 || s.start_at >= all[i - 1].start_at));
assert.equal(new Set(all.map(s => `${s.doctor_id}|${s.consultorio_id}|${s.start_at}`)).size, all.length);
assert.ok(all.every(s => s.start_at < '2026-12-10'));
assert.ok(all.some(s => s.start_at.startsWith('2026-12-09')));
assert.ok(!requests.some(r => r.office === '99'));
assert.ok(requests.some(r => r.office === '2' && r.from > '2026-09-11'));
assert.equal(JSON.stringify(await search.page(0)), JSON.stringify(pages[0]));
assert.equal(JSON.stringify(await search.page(3)), JSON.stringify(pages[1]));
const empty = scope.window.MxmedPublicGlobalAvailability({doctorId: '1', consultorios: {}, fetcher});
assert.equal((await empty.page(0)).slots.length, 0);
const aborted = new AbortController(); aborted.abort();
await assert.rejects(search.page(999, aborted.signal), {name: 'AbortError'});
console.log('PASS: multi-office merge, Friday/Saturday/Monday, cross-office pages, previous/next, ties, deduplication, refill, fixed 90-day horizon, eligibility set, empty, abort');
