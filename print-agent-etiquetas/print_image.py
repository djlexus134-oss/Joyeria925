"""
Imprime un PNG en una impresora de Windows usando el driver nativo (GDI).
Estrategia tipo Gemarun: el driver Argox convierte la imagen a PPLA y
maneja la calibracion de gap, asi evitamos peleas con la sintaxis PPLA.

Uso:
    py -3 print_image.py --printer "Argox OS-2140 PPLA" --file etiqueta.png
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path


def list_printer_names() -> list[str]:
    import win32print

    flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
    return [entry[2] for entry in win32print.EnumPrinters(flags)]


def _log(msg: str) -> None:
    """Logging a stderr para diagnostico (no contamina stdout que el agente parsea)."""
    print(f"[print_image] {msg}", file=sys.stderr, flush=True)


def print_png(printer_name: str, png_path: Path, doc_name: str = "Joyeria Etiqueta") -> None:
    """
    Abre el PNG con PIL, crea un DC sobre la impresora y dibuja la imagen
    ocupando la pagina fisica completa (tamano configurado en el driver).
    """
    from PIL import Image, ImageWin
    import win32print
    import win32ui

    if not png_path.is_file():
        raise FileNotFoundError(str(png_path))

    _log(f"abriendo PNG {png_path}")
    img = Image.open(png_path)
    if img.mode != "RGB":
        img = img.convert("RGB")
    _log(f"PNG {img.width}x{img.height} mode={img.mode}")

    _log(f"creando DC para '{printer_name}'")
    h_printer_dc = win32ui.CreateDC()
    h_printer_dc.CreatePrinterDC(printer_name)
    try:
        page_w = h_printer_dc.GetDeviceCaps(8)   # HORZRES
        page_h = h_printer_dc.GetDeviceCaps(10)  # VERTRES
        dpi_x = h_printer_dc.GetDeviceCaps(88)   # LOGPIXELSX
        dpi_y = h_printer_dc.GetDeviceCaps(90)   # LOGPIXELSY
        _log(f"page={page_w}x{page_h} dots, dpi={dpi_x}x{dpi_y}")

        if page_w <= 0 or page_h <= 0:
            page_w = img.width
            page_h = img.height
            _log(f"usando tamano del PNG como fallback: {page_w}x{page_h}")

        # El PNG ya viene a 203 dpi (ej. 60x10 mm = 480x80). No estirar al tamano
        # del driver si este esta mal (807x1218 = varias etiquetas de alto).
        draw_w = img.width
        draw_h = img.height
        if page_w > 0 and page_h > 0:
            ratio_page = page_w / max(page_h, 1)
            ratio_img = img.width / max(img.height, 1)
            page_much_larger = page_h > draw_h * 2 or page_w > draw_w * 2
            aspect_ok = abs(ratio_page - ratio_img) < 0.35
            if aspect_ok and not page_much_larger:
                draw_w = page_w
                draw_h = page_h
                _log(f"ajuste a pagina del driver: {draw_w}x{draw_h}")
            else:
                _log(
                    f"driver page={page_w}x{page_h} no coincide con PNG; "
                    f"imprimiendo 1:1 en {draw_w}x{draw_h} (revisa driver 60x10 mm)"
                )

        _log("StartDoc")
        h_printer_dc.StartDoc(doc_name)
        try:
            _log("StartPage")
            h_printer_dc.StartPage()
            try:
                _log("dibujando DIB")
                dib = ImageWin.Dib(img)
                dib.draw(h_printer_dc.GetHandleOutput(), (0, 0, draw_w, draw_h))
                _log("DIB OK")
            finally:
                _log("EndPage")
                h_printer_dc.EndPage()
        finally:
            _log("EndDoc")
            h_printer_dc.EndDoc()
    finally:
        _log("DeleteDC")
        h_printer_dc.DeleteDC()


def main() -> int:
    parser = argparse.ArgumentParser(description="Impresion de PNG via driver GDI (win32print)")
    parser.add_argument("--printer", nargs="+", help="Nombre exacto de la impresora en Windows (puede tener espacios)")
    parser.add_argument("--file", help="Archivo .png a imprimir")
    parser.add_argument("--list-printers", action="store_true", help="Lista impresoras y sale")
    args = parser.parse_args()

    if args.list_printers:
        for name in list_printer_names():
            print(name)
        return 0

    if not args.printer or not args.file:
        print("ERROR: --printer y --file son obligatorios", file=sys.stderr)
        return 2

    # --printer acepta multiples tokens y los re-ensambla. Asi sobrevivimos al
    # caso en que Windows/argparse no respete las comillas del nombre con espacios.
    printer_name = " ".join(args.printer).strip()
    _log(f"argv = {sys.argv}")
    _log(f"printer resuelta = '{printer_name}'")

    png_path = Path(args.file)
    if not png_path.is_file():
        print(f"ERROR: archivo no encontrado: {png_path}", file=sys.stderr)
        return 1

    # Validar que la impresora exista (para dar mensaje claro si esta mal escrita).
    available = list_printer_names()
    if printer_name not in available:
        # Busqueda case-insensitive y sin acentos por si acaso.
        norm = printer_name.casefold()
        match = next((p for p in available if p.casefold() == norm), None)
        if match is None:
            print(f"ERROR: impresora '{printer_name}' no encontrada. Disponibles: {available}", file=sys.stderr)
            return 1
        printer_name = match

    try:
        print_png(printer_name, png_path)
        print(f"OK:gdi:{printer_name}:{png_path.stat().st_size}")
        return 0
    except Exception as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
