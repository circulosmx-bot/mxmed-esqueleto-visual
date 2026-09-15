# IP01E correction — Formation field-label hierarchy

Removes only mx-professional-subheading from Certificaciones, Cursos,
Diplomados and Miembro de. They return to the existing form-label system,
matching Servicio principal 1–4. Registered-information and main section
headings remain unchanged. No CSS, controls, behavior or data changes.

Read-only QA at 1440×900, 1366×768, 820×1180 and 390×844 compares each pair's
computed font size, weight, color, line height, margins and padding: exact match.
All three main headings retain #06AEB8, 20px/700 text and 50px icons. No overflow
or runtime exceptions. Screenshots: /tmp/ip01e-correction/screenshots/.
Existing arrangement and spacing rules remain; smaller labels naturally reclaim
height. The pending tab trial remains excluded from the commit.
