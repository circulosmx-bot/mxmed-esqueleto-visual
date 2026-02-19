#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$ROOT_DIR/.tmp/dev-servers"
API_PID_FILE="$STATE_DIR/api_8091.pid"
UI_PID_FILE="$STATE_DIR/ui_8092.pid"

API_HOST="127.0.0.1"
API_PORT="8091"
UI_HOST="127.0.0.1"
UI_PORT="8092"

API_LOG="/tmp/mxmed_api_8091.log"
UI_LOG="/tmp/mxmed_ui_8092.log"
UI_API_BASE="http://127.0.0.1:8091"

mkdir -p "$STATE_DIR"

port_pid() {
  local port="$1"
  lsof -tiTCP:"$port" -sTCP:LISTEN 2>/dev/null | head -n 1
}

pid_alive() {
  local pid="$1"
  [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null
}

read_pid_file() {
  local file="$1"
  if [ -f "$file" ]; then
    tr -d '[:space:]' < "$file"
  fi
}

write_pid_file() {
  local file="$1"
  local pid="$2"
  printf '%s\n' "$pid" > "$file"
}

remove_pid_file() {
  local file="$1"
  rm -f "$file"
}

start_api() {
  local existing_pid
  existing_pid="$(port_pid "$API_PORT")"
  if pid_alive "$existing_pid"; then
    write_pid_file "$API_PID_FILE" "$existing_pid"
    echo "API already running (pid=$existing_pid) on $API_HOST:$API_PORT"
    return
  fi

  nohup bash -lc "echo -ne '\033]0;MXMED-API-8091\a'; exec php -S '$API_HOST:$API_PORT' -t '$ROOT_DIR'" > "$API_LOG" 2>&1 &
  local pid=$!
  sleep 1

  if pid_alive "$pid"; then
    write_pid_file "$API_PID_FILE" "$pid"
    echo "API started (pid=$pid)"
  else
    echo "Failed to start API. Check log: $API_LOG"
  fi
}

start_ui() {
  local existing_pid
  existing_pid="$(port_pid "$UI_PORT")"
  if pid_alive "$existing_pid"; then
    write_pid_file "$UI_PID_FILE" "$existing_pid"
    echo "UI already running (pid=$existing_pid) on $UI_HOST:$UI_PORT"
    return
  fi

  nohup bash -lc "echo -ne '\033]0;MXMED-UI-8092\a'; exec env MXMED_API_BASE='$UI_API_BASE' php -S '$UI_HOST:$UI_PORT' -t '$ROOT_DIR'" > "$UI_LOG" 2>&1 &
  local pid=$!
  sleep 1

  if pid_alive "$pid"; then
    write_pid_file "$UI_PID_FILE" "$pid"
    echo "UI started (pid=$pid)"
  else
    echo "Failed to start UI. Check log: $UI_LOG"
  fi
}

stop_by_pid_file() {
  local file="$1"
  local label="$2"
  local pid
  pid="$(read_pid_file "$file")"
  if pid_alive "$pid"; then
    kill "$pid" 2>/dev/null || true
    sleep 1
    if pid_alive "$pid"; then
      kill -9 "$pid" 2>/dev/null || true
    fi
    echo "Stopped $label by pid file (pid=$pid)"
  fi
  remove_pid_file "$file"
}

stop_by_port() {
  local port="$1"
  local label="$2"
  local pid
  pid="$(port_pid "$port")"
  if pid_alive "$pid"; then
    kill "$pid" 2>/dev/null || true
    sleep 1
    if pid_alive "$pid"; then
      kill -9 "$pid" 2>/dev/null || true
    fi
    echo "Stopped $label by port $port (pid=$pid)"
  fi
}

do_start() {
  start_api
  start_ui
  echo "API URL: http://$API_HOST:$API_PORT"
  echo "UI URL: http://$UI_HOST:$UI_PORT"
  echo "API log: $API_LOG"
  echo "UI log: $UI_LOG"
}

do_stop() {
  stop_by_pid_file "$API_PID_FILE" "API"
  stop_by_pid_file "$UI_PID_FILE" "UI"
  stop_by_port "$API_PORT" "API"
  stop_by_port "$UI_PORT" "UI"
}

do_status() {
  local api_listen ui_listen api_pid_file ui_pid_file api_pid_port ui_pid_port
  api_pid_file="$(read_pid_file "$API_PID_FILE")"
  ui_pid_file="$(read_pid_file "$UI_PID_FILE")"
  api_pid_port="$(port_pid "$API_PORT")"
  ui_pid_port="$(port_pid "$UI_PORT")"

  if pid_alive "$api_pid_port"; then
    api_listen="LISTEN"
  else
    api_listen="DOWN"
  fi

  if pid_alive "$ui_pid_port"; then
    ui_listen="LISTEN"
  else
    ui_listen="DOWN"
  fi

  echo "API  $API_HOST:$API_PORT => $api_listen | pid(file)=${api_pid_file:-none} | pid(port)=${api_pid_port:-none}"
  echo "UI   $UI_HOST:$UI_PORT => $ui_listen | pid(file)=${ui_pid_file:-none} | pid(port)=${ui_pid_port:-none}"
}

do_logs() {
  echo "===== $API_LOG (last 80) ====="
  tail -n 80 "$API_LOG" 2>/dev/null || echo "(no log yet)"
  echo "===== $UI_LOG (last 80) ====="
  tail -n 80 "$UI_LOG" 2>/dev/null || echo "(no log yet)"
}

do_start_tabs() {
  if [ "$(uname -s)" != "Darwin" ] || ! command -v osascript >/dev/null 2>&1; then
    echo "start-tabs is only available on macOS with osascript."
    echo "Use: ./scripts/dev-servers.sh start"
    exit 1
  fi

  if pid_alive "$(port_pid "$API_PORT")" || pid_alive "$(port_pid "$UI_PORT")"; then
    echo "Detected running servers on 8091/8092."
    echo "start-tabs will not stop running servers automatically."
    echo "Use: ./scripts/dev-servers.sh stop  or  ./scripts/dev-servers.sh restart"
    exit 1
  fi

  local osa_output=""
  local osa_status=0
  osa_output="$(
    osascript - "$ROOT_DIR" "$API_HOST" "$API_PORT" "$UI_HOST" "$UI_PORT" "$UI_API_BASE" 2>&1 <<'OSA'
on run argv
  set rootDir to item 1 of argv
  set apiHost to item 2 of argv
  set apiPort to item 3 of argv
  set uiHost to item 4 of argv
  set uiPort to item 5 of argv
  set uiApiBase to item 6 of argv

  set apiCmd to "cd " & quoted form of rootDir & "; printf '\\033]0;MXMED-API-8091\\a'; php -S " & apiHost & ":" & apiPort & " -t ."
  set uiCmd to "cd " & quoted form of rootDir & "; printf '\\033]0;MXMED-UI-8092\\a'; MXMED_API_BASE=" & quoted form of uiApiBase & " php -S " & uiHost & ":" & uiPort & " -t ."

  tell application "Terminal"
    activate
    if (count of windows) = 0 then
      do script ""
    end if
    do script apiCmd in front window
    do script uiCmd
  end tell
end run
OSA
  )"
  osa_status=$?

  if [ "$osa_status" -ne 0 ]; then
    echo "Failed to open Terminal tabs with osascript."
    echo "$osa_output"
    exit 1
  fi

  echo "Opened Terminal tabs:"
  echo "- MXMED-API-8091 -> http://$API_HOST:$API_PORT"
  echo "- MXMED-UI-8092 -> http://$UI_HOST:$UI_PORT (MXMED_API_BASE=$UI_API_BASE)"
}

case "${1:-}" in
  start)
    do_start
    ;;
  start-tabs)
    do_start_tabs
    ;;
  stop)
    do_stop
    ;;
  status)
    do_status
    ;;
  logs)
    do_logs
    ;;
  restart)
    do_stop
    do_start
    ;;
  *)
    echo "Usage: ./scripts/dev-servers.sh {start|start-tabs|stop|status|logs|restart}"
    exit 1
    ;;
esac
