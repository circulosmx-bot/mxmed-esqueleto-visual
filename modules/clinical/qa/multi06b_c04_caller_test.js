"use strict";

const fs = require("fs");
const vm = require("vm");

const source = fs.readFileSync("assets/js/app.js", "utf8");
const start = source.indexOf("function mxmedCreateClinicalCommandKeyRegistry(options = {})");
const end = source.indexOf("// M6_CALLER01_COMMAND_KEY_HELPER_END", start);
if (start < 0 || end < 0) throw new Error("accepted command-key helper missing");

const context = { Date, Math, Promise };
vm.createContext(context);
vm.runInContext(`${source.slice(start, end)}; this.createRegistry = mxmedCreateClinicalCommandKeyRegistry;`, context);

let sequence = 0;
const registry = context.createRegistry({ uuidFactory: () => `c04-stable-${++sequence}` });
const observed = [];

async function failedRetry(fingerprint) {
  try {
    await registry.run(
      "c04-encounter-upload:enc:7:p_patient:pdf",
      fingerprint,
      () => ({ patientId: "p_patient", encounterKey: "enc:7" }),
      ({ key, command }) => {
        observed.push({ key, command });
        throw new Error("lost response");
      }
    );
  } catch (error) {
    if (error.message !== "lost response") throw error;
  }
}

(async () => {
  await failedRetry("same-document-fingerprint");
  await failedRetry("same-document-fingerprint");
  if (observed[0].key !== observed[1].key) throw new Error("C04 exact retry changed key");

  await failedRetry("changed-binary-fingerprint");
  if (observed[2].key === observed[1].key) throw new Error("C04 changed binary reused key");
  if (observed.some((entry) => entry.command.patientId !== "p_patient" || entry.command.encounterKey !== "enc:7")) {
    throw new Error("C04 command context changed");
  }

  console.log("MULTI06B_C04_CALLER_QA=PASS stable retry key,changed binary key,context preservation");
})().catch((error) => {
  console.error(error.stack || error);
  process.exitCode = 1;
});
