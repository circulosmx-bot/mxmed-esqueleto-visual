'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../../..');
const appSource = fs.readFileSync(path.join(root, 'assets/js/app.js'), 'utf8');
const startMarker = '// M6_CALLER01_COMMAND_KEY_HELPER_START';
const endMarker = '// M6_CALLER01_COMMAND_KEY_HELPER_END';
const start = appSource.indexOf(startMarker);
const end = appSource.indexOf(endMarker, start);
if(start < 0 || end < 0) throw new Error('FAIL: command key helper markers missing');

const helperSource = appSource.slice(start + startMarker.length, end);
const sandbox = { Map, Promise, Error, Date, Math };
vm.createContext(sandbox);
vm.runInContext(`${helperSource}\nthis.createRegistry = mxmedCreateClinicalCommandKeyRegistry;`, sandbox);

let passed = 0;
const check = (condition, name)=> {
  if(!condition) throw new Error(`FAIL: ${name}`);
  passed += 1;
  console.log(`PASS: ${name}`);
};

(async ()=> {
  let sequence = 0;
  const registry = sandbox.createRegistry({ uuidFactory: ()=> `uuid-${++sequence}` });

  const attempts = [];
  const firstCommandFactory = ()=> ({ status: 'open', encounter_dt: '2026-09-19 10:00:00' });
  try{
    await registry.run('encounter-start:p_1', 'START:p_1', firstCommandFactory, ({ key, command })=> {
      attempts.push({ key, command });
      throw new Error('lost response');
    });
  }catch(error){
    check(error.message === 'lost response', 'lost response is exposed to caller');
  }

  const retryResult = await registry.run(
    'encounter-start:p_1',
    'START:p_1',
    ()=> ({ status: 'open', encounter_dt: '2099-01-01 00:00:00' }),
    ({ key, command })=> {
      attempts.push({ key, command });
      return { encounter_key: 'enc_retry' };
    }
  );
  check(attempts[0].key === attempts[1].key, 'same logical retry reuses idempotency key');
  check(attempts[1].command.encounter_dt === '2026-09-19 10:00:00', 'lost-response retry reuses original command payload');
  check(retryResult.encounter_key === 'enc_retry', 'retry returns authoritative result');

  let releaseInflight;
  let executions = 0;
  const inflightExecutor = ()=> {
    executions += 1;
    return new Promise((resolve)=> { releaseInflight = resolve; });
  };
  const firstInflight = registry.run('encounter-start:p_2', 'START:p_2', ()=> ({ status: 'open' }), inflightExecutor);
  const secondInflight = registry.run('encounter-start:p_2', 'START:p_2', ()=> ({ status: 'open' }), inflightExecutor);
  await Promise.resolve();
  check(firstInflight === secondInflight, 'double click shares one in-flight command promise');
  check(executions === 1, 'double click executes one request');
  releaseInflight({ encounter_key: 'enc_inflight' });
  await firstInflight;

  let newCommandKey = '';
  await registry.run('encounter-start:p_1', 'START:p_1', ()=> ({ status: 'open' }), ({ key })=> {
    newCommandKey = key;
    return { encounter_key: 'enc_new' };
  });
  check(newCommandKey !== attempts[1].key, 'new later command receives a new idempotency key');

  const documentKeys = [];
  try{
    await registry.run('encounter-document:enc_1:prescription', '{"dose":1}', ()=> ({ dose: 1 }), ({ key })=> {
      documentKeys.push(key);
      throw new Error('timeout');
    });
  }catch(_){ }
  await registry.run('encounter-document:enc_1:prescription', '{"dose":2}', ()=> ({ dose: 2 }), ({ key })=> {
    documentKeys.push(key);
    return { document_id: 2 };
  });
  check(documentKeys[0] !== documentKeys[1], 'modified document content receives a new key');
  check(documentKeys.every((key)=> /^mxmed-[A-Za-z0-9._:-]+$/.test(key)), 'generated keys satisfy canonical token format');

  console.log(`M6_CALLER01_COMMAND_KEY_PURE_TESTS_PASSED=${passed}`);
})().catch((error)=> {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
