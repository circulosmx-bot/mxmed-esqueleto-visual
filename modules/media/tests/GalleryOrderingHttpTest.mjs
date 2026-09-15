import assert from 'node:assert/strict';
import {execFileSync, spawn} from 'node:child_process';
import {mkdtemp, mkdir} from 'node:fs/promises';

const root = process.cwd();
const port = Number(process.env.GALLERY_ORDER_HTTP_PORT || 18146);
const base = `http://127.0.0.1:${port}`;
const fixtureRoot = await mkdtemp('/tmp/mxmed-mr5-gal01-http-');
await mkdir(fixtureRoot + '/private');
await mkdir(fixtureRoot + '/public');
const environment = {
  ...process.env,
  MR5_FIXTURE_ROOT: fixtureRoot,
  MXMED_DB_HOST: '127.0.0.1',
  MXMED_DB_PORT: '3309',
  MXMED_DB_NAME: 'mxmed',
  MXMED_DB_USER: 'root',
  MXMED_DB_PASS: '',
  MXMED_PUBLIC_MEDIA_ROOT: fixtureRoot + '/public',
  MXMED_PRIVATE_MEDIA_ROOT: fixtureRoot + '/private',
};
const php = code => execFileSync('php', ['-r', code], {cwd: root, env: environment, encoding: 'utf8'}).trim();
const fixture = JSON.parse(php(`require 'modules/media/tests/GalleryReviewFixture.php';$a=mr10Doctor();mr10SeedPublic($a,4);$b=mr10Doctor();mr10SeedPublic($b,2);$p=mr5Pdo();foreach([$a,$b] as $d)$p->prepare("UPDATE profiles_doctors SET profile_status='active',is_public_candidate=1,professional_license='GAL01-QA',specialty_primary='Medicina general' WHERE doctor_id=?")->execute([$d]);echo json_encode(['doctor'=>$a,'other'=>$b]);`));
const session = doctor => php(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'gal01-owner','doctor_id'=>'${doctor}'];echo session_id();session_write_close();`);
const sessions = {owner: session(fixture.doctor), other: session(fixture.other)};
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', root], {cwd: root, env: environment, stdio: ['ignore', 'ignore', 'pipe']});
let serverErrors = '';
server.stderr.on('data', chunk => serverErrors += chunk);

const call = async (sessionId, method = 'GET', body = null, token = '', suffix = '') => {
  const response = await fetch(base + '/api/media/gallery.php' + suffix, {
    method,
    body: body === null ? null : JSON.stringify(body),
    headers: {
      ...(sessionId ? {Cookie: `PHPSESSID=${sessionId}`} : {}),
      ...(token ? {'X-Gallery-CSRF': token} : {}),
      ...(body === null ? {} : {'Content-Type': 'application/json'}),
    },
  });
  return {status: response.status, body: await response.json()};
};

try {
  for (let attempt = 0; attempt < 100; attempt += 1) {
    try { await fetch(base + '/api/media/gallery.php'); break; }
    catch { await new Promise(resolve => setTimeout(resolve, 50)); }
  }

  assert.equal((await call('')).status, 401, 'authentication required');
  const ownerGet = await call(sessions.owner);
  assert.equal(ownerGet.status, 200);
  const initial = ownerGet.body.data.images.map(image => image.media_id);
  assert.equal(initial.length, 4);
  assert.deepEqual(ownerGet.body.data.images.map(image => Number(image.display_order)), [1, 2, 3, 4]);
  const token = ownerGet.body.data.csrf_token;

  assert.equal((await call(sessions.owner, 'PATCH', {media_ids: [...initial].reverse()})).status, 403, 'CSRF required');
  assert.equal((await call(sessions.owner, 'PATCH', {media_ids: [...initial].reverse()}, 'forged')).status, 403, 'forged CSRF denied');
  assert.equal((await call(sessions.owner, 'PATCH', {media_ids: [initial[0], initial[0], ...initial.slice(2)]}, token)).status, 400, 'duplicates rejected');
  assert.equal((await call(sessions.owner, 'PATCH', {media_ids: initial.slice(0, 3)}, token)).status, 409, 'partial order rejected');

  const otherGet = await call(sessions.other);
  assert.equal(otherGet.status, 200);
  const foreign = [...initial];
  foreign[0] = otherGet.body.data.images[0].media_id;
  assert.equal((await call(sessions.owner, 'PATCH', {media_ids: foreign}, token)).status, 409, 'cross physician media denied');
  assert.deepEqual((await call(sessions.owner)).body.data.images.map(image => image.media_id), initial, 'invalid writes preserve owner order');
  assert.deepEqual((await call(sessions.other)).body.data.images.map(image => image.media_id), otherGet.body.data.images.map(image => image.media_id), 'foreign gallery unchanged');

  const reversed = [...initial].reverse();
  const saved = await call(sessions.owner, 'PATCH', {media_ids: reversed}, token);
  assert.equal(saved.status, 200, JSON.stringify(saved.body));
  assert.deepEqual(saved.body.data.images.map(image => image.media_id), reversed);
  assert.deepEqual(saved.body.data.images.map(image => Number(image.display_order)), [1, 2, 3, 4]);
  assert.deepEqual((await call(sessions.owner)).body.data.images.map(image => image.media_id), reversed, 'reload preserves canonical order');

  const publicOrder = JSON.parse(php(`require 'api/_lib/db.php';require 'modules/profiles/repositories/PublicProfileRepository.php';$r=new Profiles\\Repositories\\PublicProfileRepository(mxmed_pdo());echo json_encode(array_column($r->resolvePublicDoctorProfile('${fixture.doctor}')['gallery'],'media_id'));`));
  assert.deepEqual(publicOrder, reversed, 'public profile read uses canonical order');

  const restored = await call(sessions.owner, 'PATCH', {media_ids: initial}, token);
  assert.equal(restored.status, 200);
  assert.deepEqual(restored.body.data.images.map(image => image.media_id), initial);
  assert.ok(!serverErrors.includes('Fatal error'), serverErrors);
  console.log('GAL01_GALLERY_ORDER_HTTP=PASS: auth; CSRF; exact owner set; cross-physician denial; save; reload; public read; restore');
} finally {
  server.kill('SIGTERM');
  for (const id of Object.values(sessions)) php(`session_id('${id}');session_start();session_destroy();`);
}
