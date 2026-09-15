# IP01F follow-up — Inline credential summary

Existing credential rows, titles and values now flow inline in their shared
header container instead of a two-column grid. Adjacent rows use a compact slash
separator. Removes the repeated display prefix `Cédula` before license numbers;
labels and governed values are unchanged. Institution and canonical status
content remain supported by the same renderer. No duplicated credential DOM.

Read-only QA at 1440×900,1366×768,820×1180,390×844 passes. Desktop screenshots
show a single line near the heading; mobile wraps as normal text. Computed font
size, weight, color and line height of labels/values match the previous runtime.
The heading's style is unchanged. No overflow or runtime exceptions, no data
writes. Artifacts: /tmp/ip01f-inline/screenshots and qa.log.
Pending tab trial remains excluded and untouched.
