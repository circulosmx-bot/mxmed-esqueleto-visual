#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Standalone loopback HTML navigation. No browser automation, cookies or HTTP commands."""
import argparse, datetime, fcntl, html, http.server, importlib.util, json, os, pathlib, ssl, subprocess, sys, time
from urllib.parse import urlsplit, quote
from zoneinfo import ZoneInfo
ROOT=pathlib.Path(__file__).resolve().parents[1]
STATE=pathlib.Path.home()/'Library/Application Support/MXMED/interface-hub02'
URL='https://127.0.0.1:57452/'
PORT=57452
spec=importlib.util.spec_from_file_location('hub01',ROOT/'scripts/interface-hub01.py');hub=importlib.util.module_from_spec(spec);spec.loader.exec_module(hub)
hub.BRANCH='feature/mxmed-dev-session-persist01'

def private_state():
    if STATE.is_symlink():raise RuntimeError('private_state_required')
    STATE.mkdir(mode=0o700,exist_ok=True)
    if STATE.stat().st_uid!=os.getuid() or STATE.stat().st_mode&0o777!=0o700:raise RuntimeError('private_state_required')

def configuration():
    p=STATE/'configuration.json'
    if p.is_symlink() or p.stat().st_mode&0o077:raise RuntimeError('private_configuration_required')
    return json.loads(p.read_text())

def render(config):
    origin=None;expiry=None
    try:_,_,origin,expiry,_=hub.load(config['owned'])
    except (OSError,RuntimeError,ValueError,KeyError,subprocess.SubprocessError):pass
    physician=hub.medical_ready()
    routes={'director':origin+'/public/identity/acceso.php?next='+quote('/internal/media-review/index.php?section=equipo',safe='') if origin else None,'advisor':origin+'/public/identity/acceso.php?next='+quote('/internal/staff-workspace/index.php',safe='') if origin else None,'physician':hub.MEDICAL+'/private-app.php' if physician else None}
    messages={'director':'Usa un perfil Safari exclusivo Director. Inicia sesión con qa_director@example.test.',
      'advisor':'Usa otro perfil Safari exclusivo Asesor. Inicia sesión con full_grants_closure@example.test.',
      'physician':'Abre desde el perfil Safari donde ya iniciaste sesión como colaborador. Este enlace conserva esa sesión y su selección.',
      'operator':'Existe; falta un contexto operador independiente verificado.',
      'public':'No disponible: publicación del perfil de referencia deshabilitada.',
      'provider':'Existe; faltan sesión y relación organizacional verificadas.'}
    cards=[]
    for key,title in hub.TITLES.items():
        link=routes.get(key);status=('Disponible · contexto de desarrollo preparado · autenticación por verificar' if key in ['director','advisor'] else 'Disponible · autenticación por verificar') if link else 'No disponible o bloqueado'
        action='<a href="'+html.escape(link,quote=True)+'">Abrir '+{'director':'Director','advisor':'Asesor','physician':'panel médico'}[key]+'</a>' if link else '<span aria-disabled="true">Sin acceso preparado</span>'
        cards.append('<li><h2>'+title+'</h2><p>'+status+'</p><p>'+messages[key]+'</p>'+action+'</li>')
    expires=datetime.datetime.fromtimestamp(expiry,ZoneInfo('America/Mexico_City')).isoformat() if expiry else 'Preview staff no disponible; requiere recuperación local verificada.'
    command='python3 '+str(ROOT/'scripts/interface-hub02.py')+' open'
    return ('<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>México Médico · Interfaces</title><style>body{font:16px/1.5 system-ui;color:#173d35;background:#f5faf9;margin:auto;padding:20px;max-width:1100px}h1{font-size:24px}h2{font-size:16px}ul{list-style:none;padding:0;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}li{background:white;border:1px solid #b9d2ca;border-radius:8px;padding:16px}a{color:#006b7c}li>a{display:inline-block;padding:8px 12px;border:1px solid;border-radius:6px}code{overflow-wrap:anywhere}aside{padding:12px;border:1px solid #b9d2ca} @media(max-width:720px){ul{grid-template-columns:1fr}}</style></head><body><h1>Interfaces · México Médico</h1><p>Desarrollo local · navegación normal · seis interfaces</p><aside><strong>Conserva separadas tus cuentas</strong><p>Mantén tu perfil Safari médico actual. Para Director y Asesor, crea dos perfiles distintos en Safari → Configuración → Perfiles y abre este hub en el perfil correspondiente. Dos pestañas o ventanas del mismo perfil no separan las cuentas staff.</p><p>El hub verifica disponibilidad, no tu autenticación. La aplicación verifica tu sesión y tus permisos al abrirla. Una indicación de contexto preparado no significa que exista una sesión autenticada. Identity conserva un límite de 1 hora de inactividad y 12 horas absolutas; cerrar el navegador puede descartar las cookies actuales. Reiniciar este hub no renueva ni elimina sesiones.</p><p>Los enlaces abren en esta pestaña; no cambian de perfil Safari ni autentican otra cuenta. Usa Atrás o este marcador para regresar. <a href="https://support.apple.com/en-ie/105100" rel="noreferrer">Guía oficial de perfiles Safari</a>. El aislamiento simultáneo de perfiles Safari requiere verificación manual en este equipo.</p></aside><ul>'+''.join(cards)+'</ul><p>URL vigente del hub: <a href="'+URL+'">'+URL+'</a></p><p>Vigencia del preview staff: '+html.escape(expires)+'</p><p>Si cierras Safari, vuelve a abrir este marcador. El servidor no depende del navegador. Para reiniciar el hub:</p><code>'+html.escape(command)+'</code><p>Las contraseñas y MFA sintéticos se obtienen con el launcher existente; nunca se muestran aquí. No uses cuentas staff para entrar al panel médico.</p></body></html>').encode()

class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if urlsplit(self.path).path not in ['/','/index.html','/development/interfaces.php']:self.send_error(404);return
        body=render(self.server.config)
        self.send_response(200);self.send_header('Content-Type','text/html; charset=utf-8');self.send_header('Content-Length',str(len(body)));self.send_header('Cache-Control','no-store');self.send_header('Referrer-Policy','no-referrer');self.send_header('X-Content-Type-Options','nosniff');self.send_header('Content-Security-Policy',"default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");self.end_headers();self.wfile.write(body)
    def log_message(self,*args):pass

def running():
    p=STATE/'server.lock'
    if not p.exists():return False
    if p.is_symlink():raise RuntimeError('private_lock_required')
    with p.open('a+') as f:
        try:fcntl.flock(f,fcntl.LOCK_EX|fcntl.LOCK_NB);return False
        except BlockingIOError:return True

def main():
    os.umask(0o077);p=argparse.ArgumentParser();p.add_argument('action',choices=['start','open','run','stop','status']);p.add_argument('--owned');a=p.parse_args();private_state()
    if a.owned:
        owned,*_=hub.load(a.owned)
        hub.write(STATE/'configuration.json',{'owned':str(owned)})
    config=configuration()
    if a.action=='status':print(json.dumps({'running':running(),'url':URL}));return
    if a.action=='stop':(STATE/'stop').touch(mode=0o600);print('Cierre solicitado sólo al servidor hub02.');return
    if a.action in ['start','open']:
        if not running():
            with (STATE/'server.log').open('ab') as log:subprocess.Popen([sys.executable,__file__,'run'],stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
            for _ in range(50):
                if running():break
                time.sleep(.1)
            if not running():raise RuntimeError('hub_start_failed_port_may_be_occupied_no_process_was_killed')
        if a.action=='open':subprocess.run(['open','-a','Safari',URL],check=True)
        print(URL);return
    if (STATE/'server.lock').is_symlink():raise RuntimeError('private_lock_required')
    lock=(STATE/'server.lock').open('a+')
    try:fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
    except BlockingIOError:return
    server=http.server.ThreadingHTTPServer(('127.0.0.1',PORT),Handler);server.config=config;server.timeout=.5
    tls=pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-tls'
    ctx=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);ctx.load_cert_chain(tls/'localhost.crt',tls/'localhost.key');server.socket=ctx.wrap_socket(server.socket,server_side=True)
    marker=STATE/'stop'
    if marker.is_symlink():raise RuntimeError('private_marker_required')
    if marker.exists():marker.unlink()
    try:
        while not marker.exists():server.handle_request()
    finally:server.server_close()

if __name__=='__main__':main()
