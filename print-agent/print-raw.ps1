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

# Trabajos atascados en el spooler (error/GDI/RAW a medias) reintentan y corrompen
# el USB: la Epson imprime "?" (error de recepcion) y enciende el LED "!".
try {
    $printer = Get-Printer -Name $PrinterName -ErrorAction Stop
    if ($printer.PrinterStatus -match 'Offline|Error|PaperJam|DoorOpen|NotAvailable') {
        Write-Error ("Impresora en estado no listo: {0}" -f $printer.PrinterStatus)
        exit 1
    }
    $jobs = @(Get-PrintJob -PrinterName $PrinterName -ErrorAction SilentlyContinue)
    foreach ($job in $jobs) {
        try {
            Remove-PrintJob -InputObject $job -ErrorAction SilentlyContinue
        } catch {
            # ignorar
        }
    }
    if ($jobs.Count -gt 0) {
        Write-Output ("[print-raw] Se limpiaron {0} trabajo(s) previos en la cola de Windows." -f $jobs.Count)
    }
} catch {
    Write-Error ("No se encontro la impresora '{0}': {1}" -f $PrinterName, $_.Exception.Message)
    exit 1
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
