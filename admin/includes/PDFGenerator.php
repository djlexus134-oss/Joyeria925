<?php
/**
 * PDFGenerator.php - Generador de PDFs para contratos de empleados
 * 
 * Utiliza mPDF para generar documentos PDF de contratos
 * Incluye templates profesionales personalizables
 */

use Mpdf\Mpdf;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/contrato_laboral_config.php';

class PDFGenerator
{
    private $mpdf;
    private $outputPath;

    /**
     * Constructor
     * 
     * @param string $outputPath Ruta donde se guardarán los PDFs
     * @throws Exception Si la ruta no existe o no tiene permisos
     */
    public function __construct(string $outputPath = '')
    {
        if (empty($outputPath)) {
            $outputPath = __DIR__ . '/../../uploads/contratos/';
        }

        // Normalizar ruta (convertir barras)
        $outputPath = str_replace('\\', '/', $outputPath);
        
        // Crear directorio si no existe
        if (!is_dir($outputPath)) {
            @mkdir($outputPath, 0755, true);
        }

        // Verificar si el directorio existe y es escribible
        if (!is_dir($outputPath)) {
            throw new Exception("No se pudo crear el directorio: $outputPath");
        }
        
        if (!is_writable($outputPath)) {
            // Intentar cambiar permisos
            @chmod($outputPath, 0755);
            if (!is_writable($outputPath)) {
                throw new Exception("Directorio no escribible: $outputPath");
            }
        }

        $this->outputPath = rtrim($outputPath, '/') . '/';

        // Configurar mPDF con configuración mínima que funciona
        // Evitar ConfigVariables y FontVariables que intenta cargar DejaVu
        $this->mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 15,
            'margin_header' => 10,
            'margin_footer' => 10,
        ]);

        // Información del documento
        $this->mpdf->SetAuthor('Sistema Joyería');
        $this->mpdf->SetCreator('Sistema Joyería');
        $this->mpdf->SetTitle('Contrato de Empleado');
        $this->configurarPiePagina();
    }

    private function configurarPiePagina(): void
    {
        $this->mpdf->SetHTMLFooter(
            '<p style="text-align:right;font-size:9pt;color:#333;">Página | {PAGENO}</p>'
        );
    }

    /**
     * Reinicializa la instancia de mPDF
     * Útil después de generar un PDF para limpiar el estado
     */
    private function reinicializarMpdf() {
        try {
            // Configuración mínima que funciona sin intentar cargar fuentes personalizadas
            $this->mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 15,
                'margin_header' => 10,
                'margin_footer' => 10,
            ]);

            $this->mpdf->SetAuthor('Sistema Joyería');
            $this->mpdf->SetCreator('Sistema Joyería');
            $this->mpdf->SetTitle('Contrato de Empleado');
            $this->configurarPiePagina();
        } catch (Exception $e) {
            error_log("Error reinicializando mPDF: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Normaliza datos del empleado mapeando campos variantes
     * Maneja variaciones como: email/correo, phone/telefono, etc.
     */
    private function normalizarDatosEmpleado(array $empleado): array
    {
        // Mapeo de campos alternativos
        $mapeos = [
            'correo' => ['email', 'correo', 'empleado_correo'],
            'telefono' => ['telefono', 'phone', 'celular', 'movil', 'empleado_telefono'],
            'nombre' => ['nombre', 'first_name', 'empleado_nombre'],
            'primer_apellido' => ['primer_apellido', 'apellido', 'last_name', 'empleado_primer_apellido'],
            'segundo_apellido' => ['segundo_apellido', 'middle_name', 'empleado_segundo_apellido'],
            'nombre_puesto' => ['nombre_puesto', 'empleado_puesto', 'puesto'],
            'curp' => ['curp'],
            'salario' => ['salario'],
            'nacionalidad' => ['nacionalidad', 'nom_pais'],
        ];
        
        // Crear array normalizado
        $normalizado = [];
        foreach ($mapeos as $campoNormalizado => $variantes) {
            foreach ($variantes as $variante) {
                if (isset($empleado[$variante]) && !empty($empleado[$variante])) {
                    $normalizado[$campoNormalizado] = $empleado[$variante];
                    break; // Usar el primero encontrado
                }
            }
        }
        
        // Preservar otros campos que no son variantes
        foreach ($empleado as $key => $value) {
            if (!isset($normalizado[$key])) {
                $normalizado[$key] = $value;
            }
        }
        
        error_log("PDFGenerator - Datos normalizados: " . json_encode($normalizado));
        return $normalizado;
    }

    /**
     * Genera PDF de contrato de empleado
     * 
     * @param array $empleado Datos del empleado
     * @param array $contrato Datos del contrato
     * @param array $empresa Datos de la empresa (opcional)
     * @return array ['success' => bool, 'file' => string, 'path' => string, 'message' => string]
     */
    public function generarContratoEmpleado(array $empleado, array $contrato, array $empresa = []): array
    {
        try {
            if (empty($empresa)) {
                $empresa = joyeria_config_contrato_laboral();
            }

            $empleado = $this->normalizarDatosEmpleado($empleado);
            $this->normalizarDatosContrato($contrato, $empleado);

            $required = ['nombre', 'primer_apellido', 'curp', 'nombre_puesto'];
            foreach ($required as $field) {
                if (empty($empleado[$field])) {
                    $msg = "Datos de empleado incompletos: $field. Recibido: " . json_encode($empleado);
                    error_log("PDFGenerator - Validación fallida: $msg");
                    return ['success' => false, 'message' => "Datos de empleado incompletos: $field"];
                }
            }

            if (empty($empleado['salario']) || (float) $empleado['salario'] <= 0) {
                return ['success' => false, 'message' => 'Datos de empleado incompletos: salario'];
            }

            if (empty($contrato['tipo_contrato']) || empty($contrato['fecha_inicio'])) {
                $msg = "Datos de contrato incompletos. Recibido: " . json_encode($contrato);
                error_log("PDFGenerator - Validación fallida: $msg");
                return ['success' => false, 'message' => 'Datos de contrato incompletos'];
            }

            // Generar HTML del contrato
            $html = $this->templateContratoEmpleado($empleado, $contrato, $empresa);

            // Escribir HTML a PDF
            $this->mpdf->WriteHTML($html);

            // Generar nombre de archivo único
            $nombreArchivo = $this->generarNombreArchivo($empleado, $contrato);
            $rutaCompleta = $this->outputPath . $nombreArchivo;

            error_log("PDFGenerator - Guardando PDF en: $rutaCompleta");

            // Guardar PDF
            $this->mpdf->Output($rutaCompleta, \Mpdf\Output\Destination::FILE);

            // Reinicializar mPDF para limpiar el estado
            try {
                $this->reinicializarMpdf();
            } catch (Exception $e) {
                error_log("Advertencia: no se pudo reinicializar mPDF: " . $e->getMessage());
            }

            // Verificar que el archivo se creó
            if (!file_exists($rutaCompleta)) {
                error_log("PDFGenerator - Archivo NO creado en: $rutaCompleta");
                return ['success' => false, 'message' => 'Error: archivo no se guardó en el servidor'];
            }

            // Verificar que tiene contenido
            $fileSize = filesize($rutaCompleta);
            if ($fileSize === false || $fileSize < 100) {
                error_log("PDFGenerator - Archivo vacío o muy pequeño: $fileSize bytes");
                return ['success' => false, 'message' => "Archivo creado pero está vacío ($fileSize bytes)"];
            }

            error_log("PDFGenerator - PDF creado exitosamente: $nombreArchivo ($fileSize bytes)");

            return [
                'success' => true,
                'file' => $nombreArchivo,
                'path' => $rutaCompleta,
                'url' => 'uploads/contratos/' . $nombreArchivo,
                'message' => 'PDF generado exitosamente'
            ];

        } catch (Exception $e) {
            $errorMsg = "Error en PDFGenerator::generarContratoEmpleado - " . $e->getMessage() . "\nStack: " . $e->getTraceAsString();
            error_log($errorMsg);
            return [
                'success' => false,
                'message' => 'Error al generar PDF: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Genera nombre único para el archivo
     */
    private function generarNombreArchivo(array $empleado, array $contrato): string
    {
        $apellido = str_replace(' ', '_', $empleado['primer_apellido']);
        $nombre = str_replace(' ', '_', $empleado['nombre']);
        $tipo = str_replace(' ', '', $contrato['tipo_contrato']);
        $fecha = date('Ymd_His');

        return "{$apellido}_{$nombre}_{$tipo}_{$fecha}.pdf";
    }

    private function normalizarDatosContrato(array &$contrato, array &$empleado): void
    {
        if (empty($contrato['fecha_inicio']) && !empty($contrato['fecha_registro'])) {
            $contrato['fecha_inicio'] = $contrato['fecha_registro'];
        }
        if (empty($empleado['nombre_puesto']) && !empty($contrato['empleado_puesto'])) {
            $empleado['nombre_puesto'] = $contrato['empleado_puesto'];
        }
    }

    private function nombreCompletoEmpleado(array $empleado): string
    {
        $partes = array_filter([
            trim((string) ($empleado['nombre'] ?? '')),
            trim((string) ($empleado['primer_apellido'] ?? '')),
            trim((string) ($empleado['segundo_apellido'] ?? '')),
        ]);
        return implode(' ', $partes);
    }

    private function formatearFechaEspanol(?string $fecha): string
    {
        if (empty($fecha)) {
            return '';
        }
        $ts = strtotime($fecha);
        if ($ts === false) {
            return '';
        }
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        $dia = (int) date('j', $ts);
        $mes = (int) date('n', $ts);
        $anio = date('Y', $ts);
        return $dia . ' de ' . ($meses[$mes] ?? '') . ' de ' . $anio;
    }

    private function calcularSueldoDiario($salario): string
    {
        $monto = round(((float) $salario) / 30, 2);
        return number_format($monto, 2, '.', ',');
    }

    private function textoPeriodoContrato(?string $fechaInicio, ?string $fechaFin): string
    {
        if (empty($fechaInicio) || empty($fechaFin)) {
            return 'Por el tiempo que dure la relación laboral conforme a la Ley Federal del Trabajo.';
        }
        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
        $diff = $inicio->diff($fin);
        $partes = [];
        if ($diff->y > 0) {
            $partes[] = $diff->y . ' ' . ($diff->y === 1 ? 'año' : 'años');
        }
        if ($diff->m > 0) {
            $partes[] = $diff->m . ' ' . ($diff->m === 1 ? 'mes' : 'meses');
        }
        if ($diff->d > 0 && empty($partes)) {
            $partes[] = $diff->d . ' ' . ($diff->d === 1 ? 'día' : 'días');
        }
        $duracion = !empty($partes) ? implode(' y ', $partes) : 'el periodo convenido';
        return 'En un periodo de ' . $duracion . ', hasta el día ' . $this->formatearFechaEspanol($fechaFin) . '.';
    }

    private function tituloContratoLegal(string $tipoContrato): string
    {
        $mapa = [
            'Tiempo Determinado' => 'CONTRATO INDIVIDUAL DE TRABAJO POR TIEMPO DETERMINADO',
            'Indeterminado' => 'CONTRATO INDIVIDUAL DE TRABAJO POR TIEMPO INDETERMINADO',
            'Obra Determinada' => 'CONTRATO INDIVIDUAL DE TRABAJO POR OBRA DETERMINADA',
            'Periodo de Prueba' => 'CONTRATO INDIVIDUAL DE TRABAJO POR PERIODO DE PRUEBA',
            'Capacitacion Inicial' => 'CONTRATO INDIVIDUAL DE TRABAJO PARA CAPACITACIÓN INICIAL',
        ];
        return $mapa[$tipoContrato] ?? 'CONTRATO INDIVIDUAL DE TRABAJO';
    }

    private function clausulaDuracionContrato(string $tipoContrato, string $textoPeriodo): string
    {
        if ($tipoContrato === 'Indeterminado') {
            return 'El presente contrato se celebra por tiempo indeterminado y no podrá modificarse, suspenderse, rescindirse o terminarse, sino en los casos y condiciones especificadas en la Ley Federal del Trabajo.';
        }
        return 'El presente contrato se celebra por tiempo determinado y no podrá modificarse, suspenderse, rescindirse o terminarse, sino en los casos y condiciones especificadas en la Ley Federal de Trabajo, específicamente en los artículos del 42 al 45, 47,51, 53 y 57.<br><br>' . $textoPeriodo;
    }

    /**
     * Template HTML del contrato (formato legal)
     */
    private function templateContratoEmpleado(array $empleado, array $contrato, array $empresa): string
    {
        $nombreEmpleado = htmlspecialchars($this->nombreCompletoEmpleado($empleado));
        $nombreEmpleadoFormal = htmlspecialchars('El C. ' . $this->nombreCompletoEmpleado($empleado));
        $puesto = htmlspecialchars(strtolower((string) ($empleado['nombre_puesto'] ?? '')));
        $curp = htmlspecialchars(strtoupper((string) ($empleado['curp'] ?? '')));
        $nacionalidad = htmlspecialchars(
            !empty($empleado['nacionalidad']) ? (string) $empleado['nacionalidad'] : ($empresa['nacionalidad_default'] ?? 'Mexicana')
        );

        $ciudad = htmlspecialchars($empresa['ciudad'] ?? 'Celaya, Gto.');
        $domicilio = htmlspecialchars($empresa['domicilio_fuente_trabajo'] ?? '');
        $patron = htmlspecialchars($empresa['nombre_patron'] ?? '');
        $tribunal = htmlspecialchars($empresa['tribunal_ciudad'] ?? 'Guanajuato, Guanajuato');
        $jornadaHoras = (int) ($empresa['jornada_horas_semanales'] ?? 48);

        $tipoContrato = (string) ($contrato['tipo_contrato'] ?? 'Tiempo Determinado');
        $titulo = htmlspecialchars($this->tituloContratoLegal($tipoContrato));
        $fechaContrato = $this->formatearFechaEspanol($contrato['fecha_inicio'] ?? date('Y-m-d'));
        $textoPeriodo = $this->textoPeriodoContrato(
            $contrato['fecha_inicio'] ?? null,
            $contrato['fecha_fin'] ?? null
        );
        $clausula2 = $this->clausulaDuracionContrato($tipoContrato, $textoPeriodo);
        $sueldoDiario = htmlspecialchars($this->calcularSueldoDiario($empleado['salario'] ?? 0));
        $sueldoDiarioTexto = '$' . $sueldoDiario;

        $estiloParrafo = 'text-align:justify;line-height:1.55;font-size:11pt;margin:0 0 10px 0;';
        $estiloTitulo = 'text-align:center;font-size:12pt;font-weight:bold;margin:0 0 14px 0;letter-spacing:0.5px;';
        $estiloSeccion = 'text-align:center;font-weight:bold;margin:18px 0 10px 0;letter-spacing:3px;font-size:11pt;';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:serif;color:#000;">

<p style="{$estiloTitulo}">{$titulo}</p>

<p style="{$estiloParrafo}">
En la ciudad de {$ciudad}. El día {$fechaContrato} comparecieron por una parte el C. {$patron} en su carácter de patrón de la fuente de trabajo ubicada en {$domicilio}, y por la otra {$nombreEmpleado} quien comparece por su propio derecho, habiendo celebrado un {$titulo} señalando para tal efecto las siguientes;
</p>

<p style="{$estiloSeccion}">D E C L A R A C I O N E S:</p>

<p style="{$estiloParrafo}">
<strong>PRIMERA.</strong> Declara la Parte Patronal que se encuentra debidamente registrada ante las autoridades fiscales y que tiene la necesidad de contratar a una persona para que ocupe el puesto de {$puesto}. En lo sucesivo se denominará a la primera de las mencionadas como “PATRÓN”.
</p>

<p style="{$estiloParrafo}">
<strong>SEGUNDA.</strong> {$nombreEmpleadoFormal} acepta llevar a cabo las actividades encomendadas en el puesto antes asignado, manifestando tener la capacidad y conocimientos necesarios para desempeñarlas; por otro lado, bajo protesta de decir verdad expresa que son suyos los siguientes datos:<br>
a) Nacionalidad: {$nacionalidad}.<br>
b) C.U.R.P.: {$curp}<br>
En lo sucesivo se denominará como “EMPLEADO” o “TRABAJADOR”
</p>

<p style="{$estiloParrafo}">
<strong>TERCERA.</strong> Los contratantes se reconocen expresamente la personalidad con que se ostentan para todos los efectos legales a que hubiere lugar, y manifiestan que libre y responsablemente se sujetan a las condiciones de trabajo que se enuncian en los términos siguientes:
</p>

<p style="{$estiloSeccion}">C L Á U S U L A S:</p>

<p style="{$estiloParrafo}">
<strong>1.-</strong> La parte patronal el C. {$patron} contrata los servicios del (a) EMPLEADO (A) en el puesto de {$puesto}, y el (a) EMPLEADO (A) se obliga a ejecutar las actividades encomendadas dentro de la jornada ordinaria de trabajo, exclusivamente en el domicilio ubicado en {$domicilio}.
</p>

<p style="{$estiloParrafo}">
<strong>2.-</strong> {$clausula2}
</p>

<p style="{$estiloParrafo}">
<strong>3.-</strong> El EMPLEADO (A) percibirá con sueldo diario la cantidad de {$sueldoDiarioTexto}, el sueldo será pagado quincenalmente, a dicho pago se sumará la parte relativa al día de Descanso semanal o Séptimos Días y la parte correspondiente a la Prima Dominical.
</p>

<p style="{$estiloParrafo}">
<strong>4.-</strong> Así mismo, el trabajador (a) tendrá derecho a disfrutar los planes de previsión social vigentes en la fuente de trabajo, así como los premios de asistencia y puntualidad que en su momento se determinen.
</p>

<p style="{$estiloParrafo}">
<strong>5.-</strong> El trabajador (a) manifiesta su conformidad con recibir su salario en moneda de curso legal, o en caso con cheque o tarjeta electrónica bancaria.
</p>

<p style="{$estiloParrafo}">
<strong>6.-</strong> La duración de la jornada de trabajo, será de {$jornadaHoras} horas semanales, sin que en ningún momento exista la posibilidad de prorrogarse dicha jornada sino es mediante el requerimiento y previa autorización por escrito de la parte patronal.
</p>

<p style="{$estiloParrafo}">
<strong>7.-</strong> El empleado (a) tendrá un día de reposo, con goce de sueldo, por cada seis días laborados, siempre y cuando se observe la jornada establecida en la cláusula anterior; así mismo, tendrá derecho al pago de los días de descanso obligatorios establecidos en la Ley Federal de Trabajo y que se encuentren comprometidos dentro del lapso del presente Contrato, en caso de laborarlos se pagaran triple.
</p>

<p style="{$estiloParrafo}">
<strong>8.-</strong> El empleado se compromete a sujetarse a los cursos y/o seminarios de Capacitación y Adiestramiento que se le asignen por la Comisión Mixta respectiva y que sean implementados por la parte patronal.
</p>

<p style="{$estiloParrafo}">
<strong>9.-</strong> La parte patronal se compromete a observar las medidas de Seguridad e Higiene que resulten aplicables, de acuerdo con lo dispuesto por el Reglamento General de Seguridad e Higiene y Medio Ambiente en el Trabajo, así como las Normas Oficiales Mexicanas correspondientes.
</p>

<p style="{$estiloParrafo}">
<strong>10.-</strong> La intensidad y calidad del trabajo serán de tal naturaleza que se obtengan la mayor eficiencia, calidad y productividad posible, sujetándose los trabajadores estrictamente a las normas de calidad, seguridad y eficiencia que determine “EL PATRÓN”
</p>

<p style="{$estiloParrafo}">
<strong>11.-</strong> El trabajador (a) manifiesta aceptar ser grabado con cámaras de seguridad y a que sirva como prueba el video en cualquier tribunal.
</p>

<p style="{$estiloParrafo}">
<strong>12.-</strong> El trabajador (a) se obliga a no presentar sus servicios en un puesto igual al que es contratado en este acto, durante el tiempo que labore para el patrón.
</p>

<p style="{$estiloParrafo}">
<strong>13.-</strong> El incumplimiento de lo anterior, implica una falta de probidad que dará lugar a la recisión de Contrato sin responsabilidad para el PATRON.
</p>

<p style="{$estiloParrafo}">
<strong>14.-</strong> Ambas partes están de acuerdo en que, para la interpretación de este contrato, se someten a los Tribunales de Trabajo de la Ciudad de {$tribunal}, señalando expresamente que en lo no establecido se regulara por las disposiciones de la Ley Federal del Trabajo.
</p>

<p style="{$estiloParrafo}">
Leído que les fue a ambas partes el presente contrato se hacen conocedores y sabedores del contenido del mismo y, en consecuencia, de las obligaciones que contraen firmándolo en la ciudad, en la fecha antes indicada, por triplicado y recibiendo el empleado su ejemplar correspondiente
</p>

<p style="margin-top:48px;font-size:11pt;"><strong>Nombre y firma:</strong></p>

</body>
</html>
HTML;

    }

    /**
     * Elimina un archivo PDF
     */
    public static function eliminarPDF(string $rutaArchivo): bool
    {
        try {
            if (file_exists($rutaArchivo) && is_file($rutaArchivo)) {
                return @unlink($rutaArchivo);
            }
            return false;
        } catch (Exception $e) {
            error_log("Error al eliminar PDF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el contenido de un PDF para visualizar
     */
    public static function mostrarPDF(string $rutaArchivo): bool
    {
        try {
            if (!file_exists($rutaArchivo) || !is_readable($rutaArchivo)) {
                return false;
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($rutaArchivo) . '"');
            header('Content-Length: ' . filesize($rutaArchivo));
            readfile($rutaArchivo);
            return true;

        } catch (Exception $e) {
            error_log("Error al mostrar PDF: " . $e->getMessage());
            return false;
        }
    }
}
