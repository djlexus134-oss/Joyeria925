import win32print

# Sustituye con el nombre exacto de tu impresora PPLA en el sistema
printer_name = "Argox OS-2140 PPLA" 

# Estructura del prefijo de texto de 15 dígitos en PPLA:
# 1  -> Orientación (1 para estándar)
# 2  -> Fuente interna (2 es una fuente estándar legible)
# 1  -> Multiplicador horizontal de tamaño
# 1  -> Multiplicador vertical de tamaño
# 000 -> Modo especial / Sub-fuente (por defecto 000)
# 0050 -> Posición Y (4 dígitos)
# 0100 -> Posición X (4 dígitos)

raw_data = b'\x02L\r' \
           b'121100000500100PRUEBA ARGOX PPLA OK\r' \
           b'121100001500100ING. EN SISTEMAS\r' \
           b'E\r'

try:
    hPrinter = win32print.OpenPrinter(printer_name)
    try:
        # Formato RAW indispensable para evitar que el driver altere los bytes de control
        hJob = win32print.StartDocPrinter(hPrinter, 1, ("Test PPLA", None, "RAW"))
        win32print.StartPagePrinter(hPrinter)
        win32print.WritePrinter(hPrinter, raw_data)
        win32print.EndPagePrinter(hPrinter)
        win32print.EndDocPrinter(hPrinter)
        print("Código PPLA enviado con éxito.")
    finally:
        win32print.ClosePrinter(hPrinter)
except Exception as e:
    print(f"Error al enviar datos: {e}")