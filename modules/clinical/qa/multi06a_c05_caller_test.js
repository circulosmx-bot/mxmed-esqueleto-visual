"use strict";

const fs = require("fs");
const vm = require("vm");
const source = fs.readFileSync("assets/js/app.js", "utf8");
const start = source.indexOf("function mxmedCreateClinicalCommandKeyRegistry(options = {})");
const end = source.indexOf("// M6_CALLER01_COMMAND_KEY_HELPER_END", start);
if (start < 0 || end < 0) throw new Error("accepted command-key helper missing");
const helper = source.slice(start, end);
const context = { Date, Math, Promise };
vm.createContext(context);
vm.runInContext(`${helper}; this.createRegistry = mxmedCreateClinicalCommandKeyRegistry;`, context);

let sequence = 0;
const registry = context.createRegistry({ uuidFactory: () => `stable-${++sequence}` });
const observed = [];
async function failedRetry(scope, fingerprint) {
  try {
    await registry.run(scope, fingerprint, () => ({ logical: fingerprint }), ({ key, command }) => {
      observed.push({ key, command });
      throw new Error("lost response");
    });
  } catch (error) {
    if (error.message !== "lost response") throw error;
  }
}

(async () => {
  await failedRetry("c05-order-create:enc:1:lab_order", "order-fingerprint");
  await failedRetry("c05-order-create:enc:1:lab_order", "order-fingerprint");
  if (observed[0].key !== observed[1].key) throw new Error("Q04 order retry changed key");

  await failedRetry("c05-result:enc:1:order-1:lab_result", "result-fingerprint");
  await failedRetry("c05-result:enc:1:order-1:lab_result", "result-fingerprint");
  if (observed[2].key !== observed[3].key) throw new Error("Q15 result retry changed key");

  await failedRetry("c05-result:enc:1:order-1:lab_result", "changed-result-fingerprint");
  if (observed[4].key === observed[3].key) throw new Error("changed result reused semantic key");

  console.log("MULTI06A_C05_CALLER_QA=PASS Q04,Q15 stable retry keys and changed semantics");
})().catch((error) => {
  console.error(error.stack || error);
  process.exitCode = 1;
});
