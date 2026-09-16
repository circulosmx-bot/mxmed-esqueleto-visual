#!/usr/bin/env python3
"""Build the checked-in SAT enumeration snapshot from the official catCFDI.xsd.

Download the XSD from SAT separately, verify its origin, then pass its local path.
This script never makes a network request or chooses effective fiscal tax rates.
"""
import argparse
import gzip
import hashlib
import json
from pathlib import Path
import xml.etree.ElementTree as ET

URL = "https://www.sat.gob.mx/sitio_internet/cfd/catalogos/catCFDI.xsd"
GROUPS = (
    "c_FormaPago", "c_MetodoPago", "c_Moneda", "c_ClaveProdServ",
    "c_ClaveUnidad", "c_ObjetoImp", "c_Impuesto", "c_TipoFactor", "c_Exportacion",
)

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--verified-on", required=True)
    parser.add_argument("--last-modified", required=True)
    args = parser.parse_args()
    raw = args.source.read_bytes()
    root = ET.fromstring(raw)
    ns = {"xs": "http://www.w3.org/2001/XMLSchema"}
    groups = {}
    for name in GROUPS:
        node = root.find(f"xs:simpleType[@name='{name}']", ns)
        if node is None:
            raise SystemExit(f"Missing SAT group: {name}")
        values = [row.attrib["value"] for row in node.findall(".//xs:enumeration", ns)]
        if not values or len(values) != len(set(values)):
            raise SystemExit(f"Invalid SAT group: {name}")
        groups[name] = values
    catalog = {
        "source": "SAT catCFDI.xsd", "source_url": URL,
        "source_sha256": hashlib.sha256(raw).hexdigest(),
        "source_last_modified": args.last_modified,
        "verified_on": args.verified_on,
        "groups": groups,
    }
    payload = json.dumps(catalog, ensure_ascii=False, separators=(",", ":")).encode()
    with args.output.open("wb") as stream:
        with gzip.GzipFile(fileobj=stream, mode="wb", filename="", mtime=0) as zipped:
            zipped.write(payload)
    print({name: len(values) for name, values in groups.items()})

if __name__ == "__main__":
    main()
