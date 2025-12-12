# Uso: ./tools/backup.ps1
# Crea carpeta backup/YYYYMMDD-HHMM y copia archivos críticos.
$ts = Get-Date -Format "yyyyMMdd-HHmm"
$dest = Join-Path -Path "backup" -ChildPath $ts
New-Item -ItemType Directory -Force -Path $dest | Out-Null
$files = @(
  "index.html",
  "assets/css/style.css",
  "assets/js/core/navigation.js",
  "assets/js/app.js"
)
foreach($f in $files){
  if(Test-Path $f){
    $target = Join-Path $dest $f
    $dir = Split-Path $target
    if(!(Test-Path $dir)){ New-Item -ItemType Directory -Force -Path $dir | Out-Null }
    Copy-Item $f $target -Force
  }
}
Write-Output "Backup creado en $dest"
