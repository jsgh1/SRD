<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: exportar.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM personas WHERE id = ?");
$stmt->execute([$id]);
$persona = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$persona) {
    header('Location: exportar.php');
    exit;
}

/* =========================
   Helpers
   ========================= */
function vpdf($valor) {
    if ($valor === null) return '—';
    $valor = (string)$valor;
    return trim($valor) !== '' ? htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') : '—';
}

function rutaAbsolutaDesdeBD($relativa) {
    if (!$relativa) return null;

    $relativa = trim((string)$relativa);
    if ($relativa === '') return null;

    $candidatos = [
        __DIR__ . '/..' . $relativa,                 // si viene "/uploads/..."
        __DIR__ . '/../' . ltrim($relativa, '/'),    // si viene "uploads/..." o "/uploads/..."
        __DIR__ . '/' . $relativa,                   // por si ya está relativo a este archivo
        __DIR__ . '/..' . '/' . ltrim($relativa, '/')
    ];

    foreach ($candidatos as $cand) {
        $rp = realpath($cand);
        if ($rp && file_exists($rp)) {
            return $rp;
        }
    }
    return null;
}

function dataUriDesdeRuta($absPath) {
    if (!$absPath || !file_exists($absPath)) return null;

    $mime = null;
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($absPath);
    }
    if (!$mime) {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg'=> 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp'=> 'image/webp',
            'svg' => 'image/svg+xml'
        ];
        $mime = $map[$ext] ?? 'application/octet-stream';
    }

    $contenido = @file_get_contents($absPath);
    if ($contenido === false) return null;

    $b64 = base64_encode($contenido);
    return "data:$mime;base64,$b64";
}

/* =========================
   Logo / Nombre sistema
   ========================= */
$nombreSistema = 'Sistema de Registro';
$logoDataUri = null;

try {
    $stmtCfg = $pdo->query("SELECT * FROM config_sistema ORDER BY id ASC LIMIT 1");
    $cfg = $stmtCfg->fetch(PDO::FETCH_ASSOC);
    if ($cfg) {
        if (!empty($cfg['nombre_sistema'])) {
            $nombreSistema = (string)$cfg['nombre_sistema'];
        }
        if (!empty($cfg['logo_sistema'])) {
            $logoAbs = rutaAbsolutaDesdeBD($cfg['logo_sistema']);
            $logoDataUri = dataUriDesdeRuta($logoAbs);
        }
    }
} catch (Exception $e) {
    // si falla, dejamos por defecto
}

/* =========================
   Fotos de persona
   ========================= */
$fotoPersonaUri   = dataUriDesdeRuta(rutaAbsolutaDesdeBD($persona['foto_persona']   ?? null));
$fotoDocumentoUri = dataUriDesdeRuta(rutaAbsolutaDesdeBD($persona['foto_documento'] ?? null));
$fotoPredioUri    = dataUriDesdeRuta(rutaAbsolutaDesdeBD($persona['foto_predio']    ?? null));

/* =========================
   Campos extra dinámicos
   ========================= */
$stmtCampos = $pdo->query("
    SELECT *
    FROM campos_extra_registro
    WHERE activo = 1
    ORDER BY grupo, orden, id
");
$campos_extra = $stmtCampos->fetchAll(PDO::FETCH_ASSOC);

$stmtExtra = $pdo->prepare("
    SELECT campo_id, valor
    FROM personas_campos_extra
    WHERE persona_id = ?
");
$stmtExtra->execute([$id]);
$extras_valores = $stmtExtra->fetchAll(PDO::FETCH_KEY_PAIR);

function filas_campos_extra_pdf(array $campos_extra, array $extras_valores, string $grupo) {
    $html = '';
    foreach ($campos_extra as $campo) {
        if (($campo['grupo'] ?? '') !== $grupo) continue;
        $cid = (int)($campo['id'] ?? 0);
        $valorExtra = $extras_valores[$cid] ?? null;

        $html .= '<tr>'
               .   '<td class="cell label">'. vpdf($campo['nombre_label'] ?? '') .'</td>'
               .   '<td class="cell value">'. vpdf($valorExtra) .'</td>'
               . '</tr>';
    }
    return $html;
}

/* =========================
   Nombre PDF
   ========================= */
$nombreBase = trim($persona['nombre_pdf'] ?? '');
if ($nombreBase === '') {
    $ndoc = trim((string)($persona['numero_documento'] ?? ''));
    $nombreBase = 'Ficha_' . ($ndoc !== '' ? $ndoc : ('registro_' . ($persona['id'] ?? $id)));
}
$nombreBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombreBase);
$filename = $nombreBase . '.pdf';

/* =========================
   Datos principales
   ========================= */
$nombreCompleto = trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? ''));
$docCompleto    = trim(($persona['tipo_documento'] ?? '') . ' ' . ($persona['numero_documento'] ?? ''));

$estado = (string)($persona['estado_registro'] ?? '');
$estadoClase = ($estado === 'Completado') ? 'chip-ok' : 'chip-warn';

/* =========================
   HTML
   ========================= */
$notaAdmin = (string)($persona['nota_admin'] ?? '');

$html = '
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    @page { margin: 22px 26px; }
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 11px;
      color: #111827;
      margin: 0;
      padding: 0;
    }

    .page-wrap { width: 100%; }

    .card {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 14px 14px;
      margin-bottom: 12px;
    }

    .topbar {
      width: 100%;
      border-collapse: collapse;
    }
    .topbar td {
      vertical-align: top;
    }

    .brand {
      width: 50px;
      vertical-align: middle;
    }
    .brand .logo {
      width: 38px;
      height: 38px;
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      overflow: hidden;
      text-align: center;
      line-height: 38px;
    }
    .brand .logo img {
      width: 38px;
      height: 38px;
    }
    .brand .logo-fallback {
      font-size: 10px;
      color: #6b7280;
    }

    .head-main { padding-left: 10px; }
    .sysname {
      font-size: 10px;
      color: #6b7280;
      margin: 0 0 4px 0;
    }
    .title {
      font-size: 18px;
      font-weight: 800;
      margin: 0 0 4px 0;
      color: #0f172a;
    }
    .subname {
      font-size: 12px;
      font-weight: 700;
      margin: 0 0 2px 0;
      color: #111827;
    }
    .subdoc {
      font-size: 10px;
      color: #6b7280;
      margin: 0;
    }

    /* ✅ AJUSTE del bloque derecho (lo de tu flecha) */
    .rightinfo{
      text-align: right;
      font-size: 10px;
      color: #6b7280;
      line-height: 1.45;
      padding-right: 10px;  /* separa del borde */
      padding-left: 10px;   /* separa del contenido de la izquierda */
    }

    .chip {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 10px;
      font-weight: 700;
      border: 1px solid #d1d5db;
      margin-top: 6px;
      color: #111827;
      background: #f9fafb;
    }
    .chip-ok {
      background: #dcfce7;
      border-color: #86efac;
    }
    .chip-warn {
      background: #fef9c3;
      border-color: #fde047;
    }

    .section-title {
      font-size: 12px;
      font-weight: 800;
      margin: 0 0 8px 0;
      color: #111827;
    }

    .table-data {
      width: 100%;
      border-collapse: collapse;
      border-top: 1px solid #edf2f7;
    }
    .table-data tr {
      border-bottom: 1px solid #edf2f7;
    }
    .cell {
      padding: 8px 8px;
      vertical-align: top;
      font-size: 10.5px;
    }
    .label {
      width: 36%;
      font-weight: 700;
      color: #111827;
    }
    .value {
      width: 64%;
      color: #111827;
    }

    .images-grid {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
    }
    .images-grid td {
      width: 33.33%;
      padding: 6px 6px;
      vertical-align: top;
    }
    .img-title {
      font-size: 10px;
      font-weight: 700;
      text-align: center;
      margin: 0 0 6px 0;
      color: #111827;
    }
    .img-box {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      height: 150px;
      padding: 8px;
      text-align: center;
    }
    .img-box-inner {
      display: table;
      width: 100%;
      height: 130px;
    }
    .img-box-inner .cellmid {
      display: table-cell;
      vertical-align: middle;
      text-align: center;
    }
    .img-box img {
      max-width: 100%;
      max-height: 130px;
      border-radius: 10px;
    }
    .noimg {
      font-size: 10px;
      color: #94a3b8;
      border: 1px dashed #cbd5e1;
      border-radius: 10px;
      padding: 10px;
      display: inline-block;
    }

    /* Nota: que rompa bien y si es larga pase a otra página */
    .nota-texto {
      font-size: 10.5px;
      color: #111827;
      line-height: 1.45;
      white-space: pre-wrap;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
  </style>
</head>
<body>
  <div class="page-wrap">

    <!-- HEADER -->
    <div class="card">
      <table class="topbar">
        <tr>
          <td class="brand">
            <div class="logo">';
              if ($logoDataUri) {
                  $html .= '<img src="'. $logoDataUri .'" alt="Logo">';
              } else {
                  $html .= '<span class="logo-fallback">LOGO</span>';
              }
$html .= '
            </div>
          </td>

          <td class="head-main">
            <p class="sysname">'. vpdf($nombreSistema) .'</p>
            <p class="title">Ficha de registro</p>
            <p class="subname">'. vpdf($nombreCompleto) .'</p>
            <p class="subdoc">'. vpdf($docCompleto) .'</p>
          </td>

          <!-- ✅ más ancho y padding para que no quede pegado -->
          <td class="rightinfo" style="width:240px;">
            <div>Fecha de registro: '. vpdf($persona['fecha_registro'] ?? null) .'</div>
            <div>ID interno: '. vpdf($persona['id'] ?? null) .'</div>
            <div class="chip '. $estadoClase .'">Estado: '. vpdf($estado) .'</div>
          </td>
        </tr>
      </table>
    </div>

    <!-- DATOS PERSONALES -->
    <div class="card">
      <p class="section-title">Datos personales</p>
      <table class="table-data">
        <tr><td class="cell label">Afiliado</td><td class="cell value">'. vpdf($persona['afiliado'] ?? null) .'</td></tr>
        <tr><td class="cell label">Zona</td><td class="cell value">'. vpdf($persona['zona'] ?? null) .'</td></tr>
        <tr><td class="cell label">Género</td><td class="cell value">'. vpdf($persona['genero'] ?? null) .'</td></tr>
        <tr><td class="cell label">Fecha de nacimiento</td><td class="cell value">'. vpdf($persona['fecha_nacimiento'] ?? null) .'</td></tr>
        '. filas_campos_extra_pdf($campos_extra, $extras_valores, 'persona') .'
      </table>
    </div>

    <!-- CONTACTO Y PREDIO -->
    <div class="card">
      <p class="section-title">Contacto y predio</p>
      <table class="table-data">
        <tr><td class="cell label">Teléfono</td><td class="cell value">'. vpdf($persona['telefono'] ?? null) .'</td></tr>
        <tr><td class="cell label">Correo electrónico</td><td class="cell value">'. vpdf($persona['correo_electronico'] ?? null) .'</td></tr>
        <tr><td class="cell label">Cargo</td><td class="cell value">'. vpdf($persona['cargo'] ?? null) .'</td></tr>
        <tr><td class="cell label">Nombre del predio</td><td class="cell value">'. vpdf($persona['nombre_predio'] ?? null) .'</td></tr>
        '. filas_campos_extra_pdf($campos_extra, $extras_valores, 'contacto') .'
        '. filas_campos_extra_pdf($campos_extra, $extras_valores, 'predio') .'
      </table>
    </div>

    <!-- IMÁGENES (subidas donde estaba la nota) -->
    <div class="card">
      <p class="section-title">Imágenes asociadas</p>

      <table class="images-grid">
        <tr>

          <td>
            <p class="img-title">Foto persona</p>
            <div class="img-box">
              <div class="img-box-inner"><div class="cellmid">';
                if ($fotoPersonaUri) {
                    $html .= '<img src="'. $fotoPersonaUri .'" alt="Foto persona">';
                } else {
                    $html .= '<span class="noimg">Sin imagen</span>';
                }
$html .= '
              </div></div>
            </div>
          </td>

          <td>
            <p class="img-title">Foto documento</p>
            <div class="img-box">
              <div class="img-box-inner"><div class="cellmid">';
                if ($fotoDocumentoUri) {
                    $html .= '<img src="'. $fotoDocumentoUri .'" alt="Foto documento">';
                } else {
                    $html .= '<span class="noimg">Sin imagen</span>';
                }
$html .= '
              </div></div>
            </div>
          </td>

          <td>
            <p class="img-title">Foto predio</p>
            <div class="img-box">
              <div class="img-box-inner"><div class="cellmid">';
                if ($fotoPredioUri) {
                    $html .= '<img src="'. $fotoPredioUri .'" alt="Foto predio">';
                } else {
                    $html .= '<span class="noimg">Sin imagen</span>';
                }
$html .= '
              </div></div>
            </div>
          </td>

        </tr>
      </table>
    </div>

    <!-- NOTA (abajo de imágenes, y larga rompe a otra página sin salirse) -->
    <div class="card">
      <p class="section-title">Nota del administrador</p>
      <div class="nota-texto">'. ($notaAdmin !== '' ? nl2br(htmlspecialchars($notaAdmin, ENT_QUOTES, 'UTF-8')) : '—') .'</div>
    </div>

  </div>
</body>
</html>
';

/* =========================
   Dompdf render + footer pages
   ========================= */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

/* Footer con paginación (sin deprecated get_font) */
$canvas = $dompdf->getCanvas();
$fontMetrics = $dompdf->getFontMetrics();
$font = $fontMetrics->getFont('DejaVu Sans', 'normal');
$canvas->page_text(440, 820, "Página {PAGE_NUM} / {PAGE_COUNT}", $font, 9, [107, 114, 128]);

$dompdf->stream($filename, ['Attachment' => true]);
exit;
