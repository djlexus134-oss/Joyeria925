param(

    [Parameter(Mandatory = $true)]

    [string]$PrinterName,

    [Parameter(Mandatory = $false)]

    [string]$FilePath,

    [Parameter(Mandatory = $false)]

    [ValidateSet('None', 'First', 'Middle', 'Last', 'ResumeOnly')]

    [string]$UsbBatch = 'None'

)



function Resume-LabelSiblings([string[]]$Names) {

    if (-not $Names -or $Names.Count -eq 0) { return }

    foreach ($n in $Names) {

        try {

            $w = Get-CimInstance -ClassName Win32_Printer -Filter ("Name='" + $n.Replace("'", "''") + "'") -ErrorAction SilentlyContinue

            if ($null -ne $w) {

                $null = Invoke-CimMethod -InputObject $w -MethodName Resume -ErrorAction SilentlyContinue

            }

        } catch {

            # ignorar

        }

    }

}



if ($UsbBatch -eq 'ResumeOnly') {

    $rp = Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue

    if (-not $rp) { exit 0 }

    $p = [string]$rp.PortName

    $sibs = @(Get-Printer -ErrorAction SilentlyContinue | Where-Object { $_.PortName -eq $p })

    $toResume = @($sibs | Where-Object { $_.Name -ne $PrinterName } | ForEach-Object { $_.Name })

    Resume-LabelSiblings $toResume

    exit 0

}



if (-not $FilePath -or -not (Test-Path -LiteralPath $FilePath)) {

    Write-Error "Archivo no encontrado: $FilePath"

    exit 1

}



$bytes = [System.IO.File]::ReadAllBytes($FilePath)

if ($bytes.Length -eq 0) {

    Write-Error "Archivo vacio."

    exit 1

}



Add-Type -TypeDefinition @"

using System;

using System.IO;

using System.Runtime.InteropServices;



public class RawPortHelper

{

    public static string NormalizePortPath(string portName)

    {

        if (string.IsNullOrWhiteSpace(portName)) return null;

        string trimmed = portName.Trim();

        if (trimmed.StartsWith(@"\\.\", StringComparison.Ordinal)) return trimmed;

        if (trimmed.StartsWith(@"\\.", StringComparison.Ordinal)) return trimmed;

        return @"\\.\" + trimmed;

    }



    public static bool SendBytesToPort(string portName, byte[] bytes)

    {

        string path = NormalizePortPath(portName);

        if (path == null || bytes == null || bytes.Length == 0) return false;

        try

        {

            using (FileStream fs = new FileStream(path, FileMode.Open, FileAccess.Write, FileShare.ReadWrite))

            {

                fs.Write(bytes, 0, bytes.Length);

                fs.Flush();

                return true;

            }

        }

        catch

        {

            return false;

        }

    }

}



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



    public static bool SendBytesToPrinter(string printerName, byte[] bytes)

    {

        IntPtr hPrinter;

        if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero))

        {

            return false;

        }



        DOCINFOA di = new DOCINFOA();

        di.pDocName = "Joyeria Etiqueta";

        di.pDataType = "RAW";



        if (!StartDocPrinter(hPrinter, 1, di))

        {

            ClosePrinter(hPrinter);

            return false;

        }



        if (!StartPagePrinter(hPrinter))

        {

            EndDocPrinter(hPrinter);

            ClosePrinter(hPrinter);

            return false;

        }



        IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);

        Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);

        int dwWritten = 0;

        bool ok = WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out dwWritten);

        Marshal.FreeCoTaskMem(pUnmanagedBytes);



        EndPagePrinter(hPrinter);

        EndDocPrinter(hPrinter);

        ClosePrinter(hPrinter);

        return ok;

    }

}

"@



$printer = Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue

$portName = $null

if ($printer) {

    $portName = [string]$printer.PortName

}



$siblings = @()

$pausedSiblingsForResume = @()

if ($portName) {

    $siblings = @(Get-Printer -ErrorAction SilentlyContinue | Where-Object { $_.PortName -eq $portName })

    $stuckSummaries = @()

    foreach ($p in $siblings) {

        $bad = @(Get-PrintJob -PrinterName $p.Name -ErrorAction SilentlyContinue | Where-Object {

            [string]$_.JobStatus -match 'Retained|Deleting|Error'

        })

        foreach ($j in $bad) {

            $stuckSummaries += @{

                printer = $p.Name

                id      = $j.Id

                status  = [string]$j.JobStatus

            }

        }

    }

    if ($stuckSummaries.Count -gt 0) {

        Write-Warning ("Hay trabajos atascados en el mismo puerto " + $portName + ". Puede bloquear la salida RAW. Detalle: " + (($stuckSummaries | ForEach-Object { $_.printer + '#' + $_.id + '=' + $_.status }) -join '; '))

    }

}



function Resume-UsbPeersFromBatchState {

    if (-not $portName -or ($portName -notmatch '^USB')) { return }

    if (-not $siblings -or $siblings.Count -le 1) { return }

    if ($pausedSiblingsForResume.Count -gt 0) {

        Resume-LabelSiblings $pausedSiblingsForResume

        return

    }

    $peerNames = @($siblings | Where-Object { $_.Name -ne $PrinterName } | ForEach-Object { $_.Name })

    Resume-LabelSiblings $peerNames

}



function Invoke-PostPrintSiblingResume([int]$resumeMs) {

    if (-not $doResumeSiblings) { return }

    Start-Sleep -Milliseconds $resumeMs

    if ($UsbBatch -eq 'Last') {

        $resumeNames = @($siblings | Where-Object { $_.Name -ne $PrinterName } | ForEach-Object { $_.Name })

        Resume-LabelSiblings $resumeNames

    } else {

        Resume-LabelSiblings $pausedSiblingsForResume

    }

}



# UsbBatch: None=pausa+imprime+resume; First/Middle/Last=lote (pausa solo en First, resume solo en Last).

# JOYERIA_LABEL_SKIP_USB_SIBLING=1 evita WMI Pause/Resume si solo usas una cola hacia la Argox.

$siblingMgmtEnabled = ($env:JOYERIA_LABEL_SKIP_USB_SIBLING -ne '1')

$doPauseSiblings = ($siblingMgmtEnabled -and $portName -match '^USB' -and $siblings.Count -gt 1 -and $UsbBatch -in @('None', 'First'))

$doResumeSiblings = ($siblingMgmtEnabled -and $portName -match '^USB' -and $siblings.Count -gt 1 -and $UsbBatch -in @('None', 'Last'))



if ($doPauseSiblings) {

    foreach ($p in $siblings) {

        if ($p.Name -eq $PrinterName) { continue }

        try {

            $w = Get-CimInstance -ClassName Win32_Printer -Filter ("Name='" + $p.Name.Replace("'", "''") + "'") -ErrorAction Stop

            if ($null -ne $w) {

                $null = Invoke-CimMethod -InputObject $w -MethodName Pause -ErrorAction Stop

                $pausedSiblingsForResume += $p.Name

            }

        } catch {

            # ignorar

        }

    }

}



$tryDirectPort = $false

if ($portName -and $portName -match '^(COM|LPT)') {

    $tryDirectPort = $true

}

if ($portName -and $portName -match '^USB' -and $env:JOYERIA_LABEL_DIRECT_USB -eq '1') {

    $tryDirectPort = $true

}



if ($tryDirectPort) {

    if ([RawPortHelper]::SendBytesToPort($portName, $bytes)) {

        Write-Output ("OK:port:" + $portName + ":" + $bytes.Length)

        $resumeMsPort = 2500

        if ($env:JOYERIA_RESUME_DELAY_MS -match '^\d+$') { $resumeMsPort = [int]$env:JOYERIA_RESUME_DELAY_MS }

        Invoke-PostPrintSiblingResume $resumeMsPort

        exit 0

    }

}



$ok = [RawPrinterHelper]::SendBytesToPrinter($PrinterName, $bytes)

if (-not $ok) {

    $detail = "puerto=" + $(if ($portName) { $portName } else { '?' })

    Write-Error ("No se pudo enviar PPLA a " + $PrinterName + " (" + $detail + ").")

    Resume-UsbPeersFromBatchState

    exit 1

}



Start-Sleep -Milliseconds 400

$errJobs = @(Get-PrintJob -PrinterName $PrinterName -ErrorAction SilentlyContinue | Where-Object {

    $_.Document -eq 'Joyeria Etiqueta' -and ($_.JobStatus -match 'Error')

})

if ($errJobs.Count -gt 0) {

    Write-Error ("La cola de impresion reporto Error para " + $PrinterName + ". Revisa driver Argox PPLA y opcion imprimir directamente.")

    Resume-UsbPeersFromBatchState

    exit 1

}



Write-Output ("OK:spooler:" + $PrinterName + ":" + $bytes.Length)

$resumeMs = 2500

if ($env:JOYERIA_RESUME_DELAY_MS -match '^\d+$') {

    $resumeMs = [int]$env:JOYERIA_RESUME_DELAY_MS

}

Invoke-PostPrintSiblingResume $resumeMs

exit 0


