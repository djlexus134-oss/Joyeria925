$root = 'D:\PrograWEB\src\Joyeria'
$utf8 = New-Object System.Text.UTF8Encoding $false
$o = [char]0x00F3
$a = [char]0x00E1
$e = [char]0x00E9
$i = [char]0x00ED
$u = [char]0x00FA
$n = [char]0x00F1
$A = [char]0x00C1

function Txt($b, $c, $x) { [string]$b + [string]$c + [string]$x }

$repl = @(
    @('Cerrar sesion', (Txt 'Cerrar sesi' $o 'n'))
    @('Inicia sesion', (Txt 'Inicia sesi' $o 'n'))
    @('iniciar sesion', (Txt 'iniciar sesi' $o 'n'))
    @('Plateria El Angel', (Txt 'Plater' $i (Txt 'a El ' $A 'ngel')))
    @('acompaana', (Txt 'acompa' $n 'a'))
    @('Tu carrito esta vacio', (Txt 'Tu carrito est' $a (Txt ' vac' $i 'o')))
    @('Explorar el catalogo', (Txt 'Explorar el cat' $a 'logo'))
    @('>Catalogo<', (Txt '>Cat' $a 'logo<'))
    @('podras recoger', (Txt 'podr' $a 's recoger'))
    @('identificacion oficial', (Txt 'identificaci' $o 'n oficial'))
    @('numero de orden', (Txt 'n' $u 'mero de orden'))
    @('No realizamos envios', (Txt 'No realizamos env' $i 'os'))
    @('debere presentar', (Txt 'deber' $e ' presentar'))
    @('Sin permiso de lectura del modulo.', (Txt 'Sin permiso de lectura del m' $o 'dulo.'))
    @('entra a un modulo permitido.', (Txt 'entra a un m' $o 'dulo permitido.'))
    @('acceder a este modulo.', (Txt 'acceder a este m' $o 'dulo.'))
    @('Ir a modulo permitido', (Txt 'Ir a m' $o 'dulo permitido'))
    @('No se encontro', (Txt 'No se encontr' $o ''))
    @('<th>Descripcion</th>', (Txt '<th>Descripci' $o 'n</th>'))
    @('<th>Codigo</th>', (Txt '<th>C' $o 'digo</th>'))
    @('<th>Telefono</th>', (Txt '<th>Tel' $e 'fono</th>'))
    @('<th>Direccion</th>', (Txt '<th>Direcci' $o 'n</th>'))
    @('<th>Credito</th>', (Txt '<th>Cr' $e 'dito</th>'))
    @('<th>Codigo pieza</th>', (Txt '<th>C' $o 'digo pieza</th>'))
    @('Descripcion:', (Txt 'Descripci' $o 'n:'))
    @('Credito a favor', (Txt 'Cr' $e 'dito a favor'))
    @('Contrasena temporal', (Txt 'Contrase' $n 'a temporal'))
    @('Contrasena:', (Txt 'Contrase' $n 'a:'))
    @('Fecha de operacion', (Txt 'Fecha de operaci' $o 'n'))
    @('Fecha operacion', (Txt 'Fecha operaci' $o 'n'))
    @('Guardar configuracion', (Txt 'Guardar configuraci' $o 'n'))
    @('Centro de configuracion', (Txt 'Centro de configuraci' $o 'n'))
    @('Modo de impresion', (Txt 'Modo de impresi' $o 'n'))
    @('Modo de devolucion', (Txt 'Modo de devoluci' $o 'n'))
    @('Registrar devolucion', (Txt 'Registrar devoluci' $o 'n'))
    @('Codigo pieza', (Txt 'C' $o 'digo pieza'))
    @('Codigo (barras', (Txt 'C' $o 'digo (barras'))
    @('Codigo:', (Txt 'C' $o 'digo:'))
    @('Codigo de barras. Plano', (Txt 'C' $o 'digo de barras. Plano'))
    @('Telefono:', (Txt 'Tel' $e 'fono:'))
    @('Informacion Personal', (Txt 'Informaci' $o 'n personal'))
    @('Informacion Base', (Txt 'Informaci' $o 'n base'))
    @('Configuracion Comercial', (Txt 'Configuraci' $o 'n comercial'))
    @('Empleado en sesion', (Txt 'Empleado en sesi' $o 'n'))
    @('Escanear con camara', (Txt 'Escanear con c' $a 'mara'))
    @('escaner no esta', (Txt 'esc' $a (Txt 'ner no est' $a '')))
    @('en esta pagina', (Txt 'en esta p' $a 'gina'))
    @('Vuelve a iniciar sesion', (Txt 'Vuelve a iniciar sesi' $o 'n'))
    @('Aun no hay', (Txt 'A' $u 'n no hay'))
    @('Aun no tienes', (Txt 'A' $u 'n no tienes'))
    @('Aun no recibimos', (Txt 'A' $u 'n no recibimos'))
    @('Numero de orden', (Txt 'N' $u 'mero de orden'))
    @('Linea a reemplazar', (Txt 'L' $i 'nea a reemplazar'))
    @('Parametros del arqueo', (Txt 'Par' $a 'metros del arqueo'))
    @('Buscar por nombre, correo, telefono o direccion', (Txt 'Buscar por nombre, correo, tel' $e (Txt 'fono o direcci' $o 'n')))
    @('related-col">Descripcion</th>', (Txt 'related-col">Descripci' $o 'n</th>'))
    @('name-col">Descripcion</th>', (Txt 'name-col">Descripci' $o 'n</th>'))
    @('Escribe o escanea un codigo.', (Txt 'Escribe o escanea un c' $o 'digo.'))
    @('confirmacion. Revisa', (Txt 'confirmaci' $o 'n. Revisa'))
    @('mas tarde.', (Txt 'm' $a 's tarde.'))
    @('compras en linea.', (Txt 'compras en l' $i 'nea.'))
    @('<strong>Categoria:</strong>', (Txt '<strong>Categor' $i 'a:</strong>'))
    @('encolado para impresion.', (Txt 'encolado para impresi' $o 'n.'))
    @('Camara no disponible', (Txt 'C' $a 'mara no disponible'))
    @('en tu telefono.', (Txt 'en tu tel' $e 'fono.'))
)

$files = Get-ChildItem (Join-Path $root 'admin\views') -Recurse -Filter '*.php' -File
$files += Get-ChildItem (Join-Path $root 'user') -Recurse -Filter '*.php' -File -EA SilentlyContinue
$files += Get-ChildItem (Join-Path $root 'includes') -Recurse -Filter '*.php' -File -EA SilentlyContinue
foreach ($f in @('index.php','catalogo.php')) {
    $p = Join-Path $root $f
    if (Test-Path $p) { $files += Get-Item $p }
}

$changed = 0
foreach ($file in $files) {
    $text = [System.IO.File]::ReadAllText($file.FullName, $utf8)
    $orig = $text
    foreach ($pair in $repl) { $text = $text.Replace($pair[0], $pair[1]) }
    if ($text -ne $orig) {
        [System.IO.File]::WriteAllText($file.FullName, $text, $utf8)
        $changed++
    }
}
Write-Host "OK: $changed archivos"
