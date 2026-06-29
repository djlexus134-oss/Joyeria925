"""
Envia bytes PPLA/ZPL a una impresora Windows en modo RAW (win32print).
Mismo enfoque que test.py en la raiz del proyecto.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path


def list_printer_names() -> list[str]:
    import win32print

    flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
    return [entry[2] for entry in win32print.EnumPrinters(flags)]


def send_raw(printer_name: str, data: bytes, doc_name: str = "Joyeria Etiqueta") -> None:
    import win32print

    if not data:
        raise ValueError("Payload vacio")

    h_printer = win32print.OpenPrinter(printer_name)
    try:
        job = win32print.StartDocPrinter(h_printer, 1, (doc_name, None, "RAW"))
        try:
            win32print.StartPagePrinter(h_printer)
            win32print.WritePrinter(h_printer, data)
            win32print.EndPagePrinter(h_printer)
        finally:
            win32print.EndDocPrinter(h_printer)
    finally:
        win32print.ClosePrinter(h_printer)


def main() -> int:
    parser = argparse.ArgumentParser(description="Impresion RAW PPLA/ZPL via win32print")
    parser.add_argument("--printer", nargs="+", help="Nombre exacto de la impresora en Windows (puede tener espacios)")
    parser.add_argument("--file", help="Archivo .bin con comandos RAW")
    parser.add_argument("--list-printers", action="store_true", help="Lista impresoras y sale")
    args = parser.parse_args()

    if args.list_printers:
        for name in list_printer_names():
            print(name)
        return 0

    if not args.printer or not args.file:
        print("ERROR: --printer y --file son obligatorios", file=sys.stderr)
        return 2

    # Re-ensamblar nombre por si Windows partio el argumento en los espacios.
    printer_name = " ".join(args.printer).strip()

    file_path = Path(args.file)
    if not file_path.is_file():
        print(f"ERROR: archivo no encontrado: {file_path}", file=sys.stderr)
        return 1

    data = file_path.read_bytes()
    if not data:
        print("ERROR: archivo vacio", file=sys.stderr)
        return 1

    try:
        send_raw(printer_name, data)
        print(f"OK:win32print:{printer_name}:{len(data)}")
        return 0
    except Exception as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
