# Step 2 R2 Director visual fixture

`step2_vitals_r2_review.js` is injected only by the local Director router, after
`plan02br2_review_context()` verifies loopback, the disposable database and cohort.
It is not loaded by `index.html` or product API entrypoints.

The guarded router serves `/__director_review_step2_r2.js` from this file and injects
that script into the head only for `review_patient=plan02ux`,
`review_encounter=open`, and `review_step2_visual=r2`.

Review URL parameters:

- `review_step2_visual=r2`: four synthetic previous values and direct opening of Step 2.
- `review_current_values=0|1|4|8|9`: synthetic current-value composition, default empty.

The fixture substitutes only scoped GET responses in the browser. It blocks
clinical and agenda writes, except existing patient identity resolution. It never
inserts observations or patient history. The previous examples use supported
measurement types (blood pressure, temperature, weight, oxygen saturation).
Normal URLs retain canonical measurement behavior.

Run `step2_vitals_r2_browser.py` against port 18143 with the guarded router installed.
It compares the desktop shell with accepted commit
`14495871120d5b35ec890cd317728aeb1d08089c`, checks chip counts and responsive fit,
and captures WebKit screenshots. Functional gates remain
`step2_vitals_r1_logic.py` and `step2_vitals_r1_disposable_gate.sh`.
