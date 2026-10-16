#!/usr/bin/env python3
"""Compatibility entry: OP-INT04 intentionally replaces the legacy Operators UI.
Agenda, shell and unrelated clinical logic remain under the current preservation contract.
"""
import pathlib,runpy
runpy.run_path(str(pathlib.Path(__file__).with_name('op-int04-contract-qa.py')),run_name='__main__')
