param(
    [Parameter(Mandatory = $true)]
    [string]$PrinterName,
    [Parameter(Mandatory = $true)]
    [string]$FilePath
)

if (-not (Test-Path -LiteralPath $FilePath)) {
    Write-Error "Archivo no encontrado: $FilePath"
    exit 1
}

$bytes = [System.IO.File]::ReadAllBytes($FilePath)
if ($bytes.Length -eq 0) {
    Write-Error "Archivo vacio."
    exit 1
}

# El Spooler es obligatorio para imprimir (RAW incluido). Si esta detenido,
# lo arrancamos; sin el, WritePrinter siempre falla.
try {
    $spooler = Get-Service -Name Spooler -ErrorAction Stop
    if ($spooler.Status -ne 'Running') {
        Start-Service Spooler -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }
} catch {
    # Si no podemos consultarlo, seguimos: WritePrinter dara el error real.
}

# Limpieza best-effort de trabajos atascados (reintentos corrompen el USB y
# la Epson imprime "?" / enciende el LED "!"). NO abortamos si falla la consulta:
# dejamos que WritePrinter sea la prueba real de impresion.
try {
    $jobs = @(Get-PrintJob -PrinterName $PrinterName -ErrorAction Stop)
    foreach ($job in $jobs) {
        try { Remove-PrintJob -InputObject $job -ErrorAction SilentlyContinue } catch { }
    }
    if ($jobs.Count -gt 0) {
        Write-Output ("[print-raw] Se limpiaron {0} trabajo(s) previos en la cola." -f $jobs.Count)
    }
} catch {
    Write-Output ("[print-raw] Aviso: no se pudo consultar la cola ({0}). Continuo con envio RAW." -f $_.Exception.Message)
}

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public class RawPrinterHelper
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
    public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOCINFOA di);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static string LastErrorDetail = "";

    public static bool SendBytesToPrinter(string printerName, byte[] bytes)
    {
        LastErrorDetail = "";
        IntPtr hPrinter;
        if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero))
        {
            LastErrorDetail = "OpenPrinter Win32=" + Marshal.GetLastWin32Error();
            return false;
        }

        DOCINFOA di = new DOCINFOA();
        di.pDocName = "Joyeria Ticket";
        di.pDataType = "RAW";

        if (!StartDocPrinter(hPrinter, 1, di))
        {
            LastErrorDetail = "StartDocPrinter Win32=" + Marshal.GetLastWin32Error();
            ClosePrinter(hPrinter);
            return false;
        }

        if (!StartPagePrinter(hPrinter))
        {
            LastErrorDetail = "StartPagePrinter Win32=" + Marshal.GetLastWin32Error();
            EndDocPrinter(hPrinter);
            ClosePrinter(hPrinter);
            return false;
        }

        // WritePrinter puede escribir parcial (buffer USB/spooler lleno).
        IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
        try
        {
            Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);
            int offset = 0;
            while (offset < bytes.Length)
            {
                IntPtr chunkPtr = IntPtr.Add(pUnmanagedBytes, offset);
                int remaining = bytes.Length - offset;
                int dwWritten = 0;
                bool ok = WritePrinter(hPrinter, chunkPtr, remaining, out dwWritten);
                if (!ok || dwWritten <= 0)
                {
                    LastErrorDetail = "WritePrinter parcial offset=" + offset + " Win32=" + Marshal.GetLastWin32Error();
                    EndPagePrinter(hPrinter);
                    EndDocPrinter(hPrinter);
                    ClosePrinter(hPrinter);
                    return false;
                }
                offset += dwWritten;
            }
        }
        finally
        {
            Marshal.FreeCoTaskMem(pUnmanagedBytes);
        }

        EndPagePrinter(hPrinter);
        EndDocPrinter(hPrinter);
        ClosePrinter(hPrinter);
        return true;
    }
}
"@

$ok = [RawPrinterHelper]::SendBytesToPrinter($PrinterName, $bytes)
if (-not $ok) {
    $detail = [RawPrinterHelper]::LastErrorDetail
    Write-Error ("No se pudo enviar datos RAW a la impresora: {0}. {1}" -f $PrinterName, $detail)
    exit 1
}

exit 0
