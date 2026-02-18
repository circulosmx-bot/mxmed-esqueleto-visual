# Local Dev Servers

## Problema
`php -S` no soporta bien llamadas HTTP recursivas al mismo puerto.
Esto puede causar bloqueo (deadlock) y errores tipo `HTTP 0`.

## Solución
Correr API y UI en puertos distintos.
La UI debe usar `MXMED_API_BASE` para apuntar al puerto de API.

## Inicio (copy/paste)
### 1) API en 8091
```bash
cd /Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual
php -S 127.0.0.1:8091 -t .
```

### 2) UI en 8092 (apuntando a API 8091)
```bash
cd /Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual
MXMED_API_BASE=http://127.0.0.1:8091 php -S 127.0.0.1:8092 -t .
```

## Stop (copy/paste)
```bash
lsof -tiTCP:8091 -sTCP:LISTEN | xargs -r kill
lsof -tiTCP:8092 -sTCP:LISTEN | xargs -r kill
```

## Preflight antes de QA
Confirma que ambos puertos estén en `LISTEN` (con mensaje si están DOWN):

```bash
lsof -nP -iTCP:8091 -sTCP:LISTEN >/dev/null && echo "8091 UP" || echo "8091 DOWN"
lsof -nP -iTCP:8092 -sTCP:LISTEN >/dev/null && echo "8092 UP" || echo "8092 DOWN"
```

Si están DOWN, inicia de nuevo con logs:

```bash
cd /Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual
truncate -s 0 /tmp/mxmed_8091.log /tmp/mxmed_8092.log
nohup php -S 127.0.0.1:8091 -t . >/tmp/mxmed_8091.log 2>&1 &
nohup env MXMED_API_BASE=http://127.0.0.1:8091 php -S 127.0.0.1:8092 -t . >/tmp/mxmed_8092.log 2>&1 &
```

Nota operativa: mantener una terminal dedicada abierta para servidores y otra para ejecutar QA.

## Checklist de verificación
```bash
lsof -nP -iTCP:8091 -sTCP:LISTEN
lsof -nP -iTCP:8092 -sTCP:LISTEN
curl -sS http://127.0.0.1:8091/api/clinical/index.php/health
curl -sS "http://127.0.0.1:8092/modules/clinical/ui/historial.php?patient_id=demo&embed=1"
```

Si usas logs:
```bash
tail -n 100 /tmp/mxmed-8091.log
tail -n 100 /tmp/mxmed-8092.log
```

## Nota
El override `MXMED_API_BASE` ya existe en:
- `modules/clinical/ui/document.php`
- `modules/clinical/ui/encounter.php`
- `modules/clinical/ui/historial.php`
