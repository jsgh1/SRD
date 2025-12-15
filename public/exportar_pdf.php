<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('America/Bogota');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: exportar.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM personas WHERE id = ?");
$stmt->execute([$id]);
$persona = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$persona) { header('Location: exportar.php'); exit; }

function vpdf($valor) {
    return ($valor !== null && $valor !== '') ? htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8') : '—';
}
function rutaImagenAbsoluta($relativa) {
    if (!$relativa) return null;
    $ruta = realpath(__DIR__ . '/..' . $relativa);
    if ($ruta && file_exists($ruta)) return $ruta;
    return null;
}

$rutaFotoPersona   = rutaImagenAbsoluta($persona['foto_persona']   ?? null);
$rutaFotoDocumento = rutaImagenAbsoluta($persona['foto_documento'] ?? null);
$rutaFotoPredio    = rutaImagenAbsoluta($persona['foto_predio']    ?? null);

/** Config sistema */
$cfg = $pdo->query("SELECT nombre_sistema, logo_sistema FROM config_sistema LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$logoSistemaAbs = rutaImagenAbsoluta($cfg['logo_sistema'] ?? null);

$nombreCompleto = trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? ''));
$docCompleto    = trim(($persona['tipo_documento'] ?? '') . ' ' . ($persona['numero_documento'] ?? ''));

/** Nombre archivo */
$nombreBase = trim($persona['nombre_pdf'] ?? '');
if ($nombreBase === '') $nombreBase = 'Ficha_' . ($persona['numero_documento'] ?: ('registro_' . $persona['id']));
$nombreBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombreBase);
$filename = $nombreBase . '.pdf';

/** ==========================
 *  CAMPOS DINÁMICOS (extras)
 * ========================== */
$camposExtra = [];
$valoresExtra = []; // [campo_id] => valor

try {
    $stmtCE = $pdo->query("
      SELECT id, grupo, nombre_label
      FROM campos_extra_registro
      WHERE activo = 1
      ORDER BY grupo, orden, id
    ");
    $camposExtra = $stmtCE->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $camposExtra = [];
}

if (!empty($camposExtra)) {
    $idsExtra = array_map(fn($x) => (int)$x['id'], $camposExtra);
    $in = implode(',', array_fill(0, count($idsExtra), '?'));

    try {
        $stmtVE = $pdo->prepare("
          SELECT campo_id, valor
          FROM personas_campos_extra
          WHERE persona_id = ?
            AND campo_id IN ($in)
        ");
        $stmtVE->execute(array_merge([$id], $idsExtra));
        while ($r = $stmtVE->fetch(PDO::FETCH_ASSOC)) {
            $valoresExtra[(int)$r['campo_id']] = $r['valor'];
        }
    } catch (Exception $e) {
        $valoresExtra = [];
    }
}

function filasExtrasHtml($camposExtra, $valoresExtra, $grupo) {
    $out = '';
    foreach ($camposExtra as $c) {
        if (($c['grupo'] ?? '') !== $grupo) continue;
        $cid = (int)($c['id'] ?? 0);
        if ($cid <= 0) continue;
        $label = $c['nombre_label'] ?? 'Campo';
        $val = $valoresExtra[$cid] ?? null;
        $out .= '<tr><td class="k">'.vpdf($label).'</td><td>'.vpdf($val).'</td></tr>';
    }
    return $out;
}

$extrasPersona  = filasExtrasHtml($camposExtra, $valoresExtra, 'persona');
$extrasContacto = filasExtrasHtml($camposExtra, $valoresExtra, 'contacto');
$extrasPredio   = filasExtrasHtml($camposExtra, $valoresExtra, 'predio');

/** Logo */
$logoHtml = '';
if ($logoSistemaAbs) {
  $logoHtml = '<div class="logo"><img src="file://' . $logoSistemaAbs . '" alt="Logo"></div>';
}

$imgBox = function($title, $rutaAbs) {
  if (!$rutaAbs) {
    return '
      <div class="img-card">
        <div class="img-title">'.vpdf($title).'</div>
        <div class="img-box img-empty">Sin imagen</div>
      </div>';
  }
  return '
    <div class="img-card">
      <div class="img-title">'.vpdf($title).'</div>
      <div class="img-box">
        <img src="file://'.$rutaAbs.'" alt="'.vpdf($title).'">
      </div>
    </div>';
};

$html = '
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color:#111827; }
  .page { padding: 18px; }

  .card { border:1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; }
  .header { display: table; width:100%; }
  .left { display: table-cell; width:70%; vertical-align: middle; }
  .right { display: table-cell; width:30%; vertical-align: middle; text-align: right; padding-right: 16px; }

  .brand { display: table; width:100%; margin-bottom: 6px; }
  .logo { display: table-cell; width: 46px; vertical-align: middle; }
  .logo img {
    width: 42px; height: 42px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    object-fit: contain;
    object-position: center;
  }
  .brandtext { display: table-cell; vertical-align: middle; padding-left: 10px; }
  .sys { font-size: 10px; color:#6b7280; margin:0; }
  .title { font-size: 16px; font-weight: 800; margin:2px 0 4px; }
  .subtitle { font-size: 12px; font-weight: 700; margin:0 0 2px; }
  .subtext { font-size: 10px; color:#6b7280; margin:0; }

  .chip {
    display:inline-block; padding: 6px 10px; border-radius: 999px;
    font-size: 10px; font-weight: 700;
    border:1px solid #e5e7eb; background:#f9fafb;
  }
  .ok { background:#dcfce7; border-color:#bbf7d0; }
  .pending { background:#fef9c3; border-color:#fde68a; }

  .section-title { font-size: 12px; font-weight: 800; margin: 0 0 8px; }
  .table { width:100%; border-collapse: collapse; }
  .table td { padding: 7px 8px; border-top: 1px solid #eef2f7; font-size: 10px; }
  .table .k { width:35%; font-weight: 700; }

  .imgs { display: table; width:100%; table-layout: fixed; }
  .img-card { display: table-cell; padding: 0 6px; }
  .img-title { text-align:center; font-size: 10px; font-weight: 800; margin-bottom: 6px; }
  .img-box {
    border:1px solid #e5e7eb; border-radius: 12px;
    height: 150px; text-align:center; vertical-align: middle;
    background:#f9fafb;
    overflow:hidden;
  }
  .img-box img {
    width: 100%; height: 150px;
    object-fit: cover;
    object-position: center;
    display:block;
  }
  .img-empty { line-height: 150px; color:#6b7280; font-size: 10px; }

  .nota {
    font-size: 10px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
  }
</style>
</head>
<body>
  <div class="page">

    <div class="card">
      <div class="header">
        <div class="left">
          <div class="brand">
            '.$logoHtml.'
            <div class="brandtext">
              <p class="sys">'.vpdf($cfg['nombre_sistema'] ?? 'Sistema de Registro').'</p>
              <p class="title">Ficha de registro</p>
              <p class="subtitle">'.vpdf($nombreCompleto).'</p>
              <p class="subtext">'.vpdf($docCompleto).'</p>
            </div>
          </div>
        </div>
        <div class="right">
          <p class="subtext">Fecha de registro: '.vpdf($persona['fecha_registro']).'</p>
          <p class="subtext">ID interno: '.vpdf($persona['id']).'</p>
          <span class="chip '.((($persona['estado_registro'] ?? '') === 'Completado') ? 'ok' : 'pending').'">
            Estado: '.vpdf($persona['estado_registro']).'
          </span>
        </div>
      </div>
    </div>

    <div class="card">
      <p class="section-title">Datos personales</p>
      <table class="table">
        <tr><td class="k">Afiliado</td><td>'.vpdf($persona['afiliado']).'</td></tr>
        <tr><td class="k">Zona</td><td>'.vpdf($persona['zona']).'</td></tr>
        <tr><td class="k">Género</td><td>'.vpdf($persona['genero']).'</td></tr>
        <tr><td class="k">Fecha de nacimiento</td><td>'.vpdf($persona['fecha_nacimiento']).'</td></tr>
        '.$extrasPersona.'
      </table>
    </div>

    <div class="card">
      <p class="section-title">Contacto y predio</p>
      <table class="table">
        <tr><td class="k">Teléfono</td><td>'.vpdf($persona['telefono']).'</td></tr>
        <tr><td class="k">Correo electrónico</td><td>'.vpdf($persona['correo_electronico']).'</td></tr>
        <tr><td class="k">Cargo</td><td>'.vpdf($persona['cargo']).'</td></tr>
        <tr><td class="k">Nombre del predio</td><td>'.vpdf($persona['nombre_predio']).'</td></tr>
        '.$extrasContacto.'
        '.$extrasPredio.'
      </table>
    </div>

    <div class="card">
      <p class="section-title">Imágenes asociadas</p>
      <div class="imgs">
        '.$imgBox('Foto persona', $rutaFotoPersona).'
        '.$imgBox('Foto documento', $rutaFotoDocumento).'
        '.$imgBox('Foto predio', $rutaFotoPredio).'
      </div>
    </div>

    <div class="card">
      <p class="section-title">Nota del administrador</p>
      <div class="nota">'.vpdf($persona['nota_admin']).'</div>
    </div>

  </div>
</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/..'));

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream($filename, ['Attachment' => true]);
exit;
