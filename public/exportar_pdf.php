<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: exportar.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM personas WHERE id = ?");
$stmt->execute([$id]);
$persona = $stmt->fetch();

if (!$persona) {
    header('Location: exportar.php');
    exit;
}

// Función segura para valores
function vpdf($valor) {
    return $valor !== null && $valor !== '' ? htmlspecialchars($valor) : '—';
}

// Resolver rutas de imágenes (si existen) a ruta ABSOLUTA del servidor
function rutaImagenAbsoluta(?string $relativa): ?string {
    if (!$relativa) return null;

    $baseDir = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $ruta = null;

    // Caso típico: en BD se guarda algo como "/uploads/xxx/archivo.jpg"
    if (strpos($relativa, '/uploads/') === 0) {
        $ruta = $baseDir . $relativa; // /var/www + /uploads/...
    }
    // Por compatibilidad: "../uploads/..."
    elseif (strpos($relativa, '../uploads/') === 0) {
        $ruta = realpath(__DIR__ . '/' . $relativa);
    }
    // Otra cosa: intentamos tal cual
    else {
        $ruta = realpath($relativa);
    }

    if ($ruta && file_exists($ruta)) {
        return $ruta;
    }

    return null;
}

$rutaFotoPersona   = rutaImagenAbsoluta($persona['foto_persona']   ?? null);
$rutaFotoDocumento = rutaImagenAbsoluta($persona['foto_documento'] ?? null);
$rutaFotoPredio    = rutaImagenAbsoluta($persona['foto_predio']    ?? null);

// Nombre de archivo PDF
$nombreBase = trim($persona['nombre_pdf'] ?? '');
if ($nombreBase === '') {
    $nombreBase = 'Ficha_' . ($persona['numero_documento'] ?: 'registro_' . $persona['id']);
}
// Limpieza básica para nombre de archivo
$nombreBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombreBase);
$filename = $nombreBase . '.pdf';

// Construir HTML
$nombreCompleto = trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? ''));
$docCompleto = trim(($persona['tipo_documento'] ?? '') . ' ' . ($persona['numero_documento'] ?? ''));

$html = '
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 11px;
      color: #111827;
    }
    .ficha-container {
      padding: 10px 14px;
    }
    .ficha-header {
      display: table;
      width: 100%;
      margin-bottom: 10px;
    }
    .ficha-header-left {
      display: table-cell;
      vertical-align: middle;
      width: 70%;
    }
    .ficha-header-right {
      display: table-cell;
      vertical-align: middle;
      width: 30%;
      text-align: right;
    }
    .ficha-titulo {
      font-size: 16px;
      font-weight: bold;
      margin: 0 0 4px;
    }
    .ficha-subtitulo {
      font-size: 11px;
      margin: 0 0 2px;
    }
    .ficha-subtexto {
      font-size: 10px;
      color: #4b5563;
      margin: 0;
    }
    .chip-estado {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 8px;
      font-size: 9px;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
    }
    .chip-completado {
      background: #dcfce7;
      border-color: #bbf7d0;
    }
    .chip-pendiente {
      background: #fef9c3;
      border-color: #fef08a;
    }
    .seccion {
      margin-bottom: 8px;
    }
    .seccion-titulo {
      font-size: 12px;
      font-weight: bold;
      border-bottom: 1px solid #e5e7eb;
      margin: 0 0 4px;
      padding-bottom: 2px;
    }
    .datos-grid {
      width: 100%;
      border-collapse: collapse;
      margin-top: 2px;
    }
    .datos-grid td {
      padding: 2px 4px;
      vertical-align: top;
    }
    .label {
      font-weight: bold;
      width: 34%;
      font-size: 10px;
    }
    .valor {
      width: 66%;
      font-size: 10px;
    }
    .nota {
      font-size: 10px;
      margin-top: 4px;
    }
    .nota strong {
      font-size: 11px;
    }
    .fotos-grid {
      width: 100%;
      border-collapse: collapse;
      margin-top: 4px;
    }
    .fotos-grid td {
      width: 33%;
      text-align: center;
      font-size: 9px;
      padding: 2px;
    }
    .fotos-grid img {
      max-width: 100%;
      max-height: 160px;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <div class="ficha-container">
    <div class="ficha-header">
      <div class="ficha-header-left">
        <p class="ficha-titulo">Ficha de registro</p>
        <p class="ficha-subtitulo">' . vpdf($nombreCompleto) . '</p>
        <p class="ficha-subtexto">' . vpdf($docCompleto) . '</p>
      </div>
      <div class="ficha-header-right">
        <p class="ficha-subtexto">Fecha de registro: ' . vpdf($persona['fecha_registro']) . '</p>
        <p class="ficha-subtexto">ID interno: ' . vpdf($persona['id']) . '</p>
        <p class="chip-estado ' . ( ($persona["estado_registro"] ?? "") === "Completado" ? "chip-completado" : "chip-pendiente") . '">
          Estado: ' . vpdf($persona['estado_registro']) . '
        </p>
      </div>
    </div>

    <div class="seccion">
      <p class="seccion-titulo">Datos personales</p>
      <table class="datos-grid">
        <tr>
          <td class="label">Afiliado</td>
          <td class="valor">' . vpdf($persona['afiliado']) . '</td>
        </tr>
        <tr>
          <td class="label">Zona</td>
          <td class="valor">' . vpdf($persona['zona']) . '</td>
        </tr>
        <tr>
          <td class="label">Género</td>
          <td class="valor">' . vpdf($persona['genero']) . '</td>
        </tr>
        <tr>
          <td class="label">Fecha de nacimiento</td>
          <td class="valor">' . vpdf($persona['fecha_nacimiento']) . '</td>
        </tr>
      </table>
    </div>

    <div class="seccion">
      <p class="seccion-titulo">Contacto y predio</p>
      <table class="datos-grid">
        <tr>
          <td class="label">Teléfono</td>
          <td class="valor">' . vpdf($persona['telefono']) . '</td>
        </tr>
        <tr>
          <td class="label">Correo electrónico</td>
          <td class="valor">' . vpdf($persona['correo_electronico']) . '</td>
        </tr>
        <tr>
          <td class="label">Cargo</td>
          <td class="valor">' . vpdf($persona['cargo']) . '</td>
        </tr>
        <tr>
          <td class="label">Nombre del predio</td>
          <td class="valor">' . vpdf($persona['nombre_predio']) . '</td>
        </tr>
      </table>
    </div>

    <div class="seccion">
      <p class="seccion-titulo">Nota del administrador</p>
      <p class="nota"><strong>Observaciones:</strong><br>' . nl2br(vpdf($persona['nota_admin'])) . '</p>
    </div>';

    // Sección de fotos si hay al menos una
    if ($rutaFotoPersona || $rutaFotoDocumento || $rutaFotoPredio) {
        $html .= '
        <div class="seccion">
          <p class="seccion-titulo">Imágenes asociadas</p>
          <table class="fotos-grid">
            <tr>';
        if ($rutaFotoPersona) {
            $html .= '
              <td>
                <p>Foto persona</p>
                <img src="file://' . $rutaFotoPersona . '" alt="Foto persona">
              </td>';
        } else {
            $html .= '<td></td>';
        }
        if ($rutaFotoDocumento) {
            $html .= '
              <td>
                <p>Foto documento</p>
                <img src="file://' . $rutaFotoDocumento . '" alt="Foto documento">
              </td>';
        } else {
            $html .= '<td></td>';
        }
        if ($rutaFotoPredio) {
            $html .= '
              <td>
                <p>Foto predio</p>
                <img src="file://' . $rutaFotoPredio . '" alt="Foto predio">
              </td>';
        } else {
            $html .= '<td></td>';
        }

        $html .= '
            </tr>
          </table>
        </div>';
    }

$html .= '
  </div>
</body>
</html>
';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($filename, ['Attachment' => true]);
exit;
