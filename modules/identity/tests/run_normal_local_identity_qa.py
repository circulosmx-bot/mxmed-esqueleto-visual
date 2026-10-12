#!/usr/bin/env python3
"""Isolated Valkey process; PHP owns/drops only randomly named disposable schemas.
Requires a local MySQL administrator (root, no password) and VALKEY_SERVER_BINARY.
Never connects the composition to the live mxmed database.
"""
import os
import pathlib
import socket
import subprocess
import tempfile
import time

binary = os.environ.get('VALKEY_SERVER_BINARY')
if not binary or not pathlib.Path(binary).is_file():
    raise SystemExit('VALKEY_SERVER_BINARY must reference an existing local Valkey binary')
with tempfile.TemporaryDirectory(prefix='mxmed-idlocal03a-', dir='/tmp') as directory:
    os.chmod(directory, 0o700)
    with socket.socket() as reservation:
        reservation.bind(('127.0.0.1', 0))
        port = reservation.getsockname()[1]
    with open(pathlib.Path(directory) / 'valkey.log', 'w') as log:
        process = subprocess.Popen([binary, '--bind', '127.0.0.1', '--port', str(port),
            '--protected-mode', 'yes', '--save', '', '--appendonly', 'no', '--dir', directory],
            stdout=log, stderr=subprocess.STDOUT)
        try:
            for _ in range(100):
                if process.poll() is not None:
                    raise RuntimeError('owned Valkey failed to start')
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.1):
                        break
                except OSError:
                    time.sleep(.05)
            else:
                raise RuntimeError('owned Valkey startup timeout')
            env = os.environ.copy()
            # Do not inherit application environment/secrets into QA.
            for key in list(env):
                if key.startswith(('MXMED_', 'SESSION_', 'DB_', 'AWS_')) or key == 'APP_ENV':
                    del env[key]
            env.update(IDLOCAL_QA_PORT=str(port), IDLOCAL_QA_DIR=directory)
            result = subprocess.run(['php', str(pathlib.Path(__file__).with_name('NormalLocalIdentityTest.php'))], env=env)
            raise SystemExit(result.returncode)
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
