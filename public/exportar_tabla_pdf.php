<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('America/Bogota');

function h($v) {
    return ($v !== null && $v !== '') ? htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') : '—';
}

function rutaImagenAbsoluta($relativa) {
    if (!$relativa) return null;
    $relativa = trim($relativa);
    if ($relativa === '') return null;

    $candidate = __DIR__ . '/..' . (strpos($relativa, '/') === 0 ? $relativa : '/' . $relativa);
    $ruta = realpath($candidate);
    if ($ruta && file_exists($ruta)) return $ruta;

    $ruta2 = realpath(__DIR__ . '/' . $relativa);
    if ($ruta2 && file_exists($ruta2)) return $ruta2;

    return null;
}

function imgDataUri($absPath) {
    if (!$absPath || !file_exists($absPath)) return null;

    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = null;

    if ($ext === 'png') $mime = 'image/png';
    elseif ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
    elseif ($ext === 'gif') $mime = 'image/gif';
    elseif ($ext === 'svg') $mime = 'image/svg+xml';

    $data = @file_get_contents($absPath);
    if ($data === false) return null;

    return 'data:' . ($mime ?: 'application/octet-stream') . ';base64,' . base64_encode($data);
}

/** Encabezado */
$cfgSys = $pdo->query("
  SELECT nombre_sistema, logo_sistema,
         export_logo_tabla, export_h1, export_h2, export_h3
  FROM config_sistema
  ORDER BY id ASC
  LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$cfgExp = null;
try {
    $cfgExp = $pdo->query("
      SELECT logo, h1, h2, h3
      FROM config_exportar_tabla
      ORDER BY id ASC
      LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cfgExp = null;
}

$H1 = '';
$H2 = '';
$H3 = '';
$logoRel = '';

if ($cfgExp && (
    trim($cfgExp['h1'] ?? '') !== '' ||
    trim($cfgExp['h2'] ?? '') !== '' ||
    trim($cfgExp['h3'] ?? '') !== '' ||
    trim($cfgExp['logo'] ?? '') !== ''
)) {
    $H1 = trim($cfgExp['h1'] ?? '');
    $H2 = trim($cfgExp['h2'] ?? '');
    $H3 = trim($cfgExp['h3'] ?? '');
    $logoRel = trim($cfgExp['logo'] ?? '');
}

if ($H1 === '' && $H2 === '' && $H3 === '' && $logoRel === '') {
    $H1 = trim($cfgSys['export_h1'] ?? '');
    $H2 = trim($cfgSys['export_h2'] ?? '');
    $H3 = trim($cfgSys['export_h3'] ?? '');
    $logoRel = trim($cfgSys['export_logo_tabla'] ?? '');
}

if ($H1 === '') $H1 = trim($cfgSys['nombre_sistema'] ?? 'Sistema de Registro');
if ($logoRel === '') $logoRel = trim($cfgSys['logo_sistema'] ?? '');

$logoAbs  = rutaImagenAbsoluta($logoRel);
$logoData = imgDataUri($logoAbs);

$now  = new DateTime('now', new DateTimeZone('America/Bogota'));
$mes  = $now->format('m');
$dia  = $now->format('d');
$anio = $now->format('Y');

/** Filtros */
$q      = trim($_REQUEST['q'] ?? ($_REQUEST['search'] ?? ''));
$estado = trim($_REQUEST['estado'] ?? '');
if ($estado === '') $estado = 'Todos';

/** Nombre archivo */
$nombreArchivo = trim($_REQUEST['nombre_archivo'] ?? ($_REQUEST['nombre_pdf_tabla'] ?? ''));
if ($nombreArchivo === '') $nombreArchivo = 'Tabla_' . $now->format('Y-m-d');
$nombreArchivo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombreArchivo) . '.pdf';

/** Campos seleccionados */
$campos = $_REQUEST['campos'] ?? [];
if (!is_array($campos)) $campos = [];
$campos = array_values(array_unique(array_filter($campos, fn($x) => is_string($x) && trim($x) !== '')));
$campos = array_slice($campos, 0, 5);

/** Permitidos */
$stmtAllowed = $pdo->query("
  SELECT nombre_campo, etiqueta
  FROM campos_filtros_exportar
  WHERE activo = 1
  ORDER BY orden, id
");
$allowed = [];
foreach ($stmtAllowed->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $allowed[$r['nombre_campo']] = $r['etiqueta'];
}

$campos = array_values(array_filter($campos, fn($c) => isset($allowed[$c])));

$alias = [
    'correo' => 'correo_electronico',
    'email'  => 'correo_electronico',
    'estado' => 'estado_registro',
    'estado_del_registro' => 'estado_registro',
    'celular' => 'telefono',
    'predio' => 'nombre_predio',
    'nombre_del_predio' => 'nombre_predio',
];

$extraIds = [];
$colsSelect = ['id','nombres','apellidos','tipo_documento','numero_documento','fecha_registro'];

foreach ($campos as $c) {
    if (strpos($c, 'extra_') === 0) {
        $idExtra = (int)substr($c, 6);
        if ($idExtra > 0) $extraIds[] = $idExtra;
    } else {
        $col = $alias[$c] ?? $c;
        if (!in_array($col, $colsSelect, true)) $colsSelect[] = $col;
    }
}
$extraIds = array_values(array_unique($extraIds));

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(nombres LIKE ? OR apellidos LIKE ? OR numero_documento LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($estado !== '' && $estado !== 'Todos') {
    $where[] = "estado_registro = ?";
    $params[] = $estado;
}

$sql = "SELECT " . implode(',', array_map(fn($c) => "`$c`", $colsSelect)) . " FROM personas";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY fecha_registro DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$extrasMap = [];
if ($extraIds && $personas) {
    $ids = array_column($personas, 'id');
    $inIds   = implode(',', array_fill(0, count($ids), '?'));
    $inExtra = implode(',', array_fill(0, count($extraIds), '?'));

    $stmtEx = $pdo->prepare("
        SELECT persona_id, campo_id, valor
        FROM personas_campos_extra
        WHERE persona_id IN ($inIds)
          AND campo_id IN ($inExtra)
    ");
    $stmtEx->execute(array_merge($ids, $extraIds));

    while ($x = $stmtEx->fetch(PDO::FETCH_ASSOC)) {
        $extrasMap[(int)$x['persona_id']][(int)$x['campo_id']] = $x['valor'];
    }
}

$colsHeader = '';
foreach ($campos as $c) {
    $colsHeader .= '<th>' . h($allowed[$c]) . '</th>';
}

$rowsHtml = '';
$i = 1;

foreach ($personas as $p) {
    $fullName = trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''));
    $tipoDoc  = $p['tipo_documento'] ?? '';
    $numDoc   = $p['numero_documento'] ?? '';

    $cells = '';
    foreach ($campos as $c) {
        if (strpos($c, 'extra_') === 0) {
            $idExtra = (int)substr($c, 6);
            $val = $extrasMap[(int)$p['id']][$idExtra] ?? null;
            $cells .= '<td>' . h($val) . '</td>';
            continue;
        }

        $col = $alias[$c] ?? $c;
        $val = $p[$col] ?? null;
        $cells .= '<td>' . h($val) . '</td>';
    }

    $rowsHtml .= '
      <tr>
        <td class="c-num">' . $i . '</td>
        <td>' . h($fullName) . '</td>
        <td>' . h($tipoDoc) . '</td>
        <td>' . h($numDoc) . '</td>
        ' . $cells . '
        <td class="c-firma"></td>
      </tr>';

    $i++;
}

$logoHtml = '';
if ($logoData) {
    $logoHtml = '<img class="logo" src="' . $logoData . '" alt="Logo">';
}

$html = '
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 14px 12px 26px 12px; }

  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color:#111827; }
  .wrap { padding: 0; }

  .top { text-align:center; margin-bottom: 10px; }
  .logo {
    width: 68px; height: 68px;
    border-radius: 999px;
    object-fit: cover;
    display:block; margin: 0 auto 6px;
  }

  .h1 { font-size: 13px; font-weight: 700; margin: 0; }
  .h2 { font-size: 10.5px; margin: 2px 0 0; }
  .meta { font-size: 9.5px; margin-top: 4px; }
  .h3 { font-size: 10.5px; font-weight: 700; margin: 6px 0 10px; }

  /* Tabla más compacta para que siempre quepa FIRMA */
  table { width:100%; border-collapse: collapse; table-layout: fixed; }
  th, td {
    border: 1px solid #374151;
    padding: 4px 4px;
    vertical-align: top;
    word-break: break-word;
    overflow-wrap: anywhere;
  }
  th { background:#f3f4f6; font-weight:700; font-size: 8.5px; text-align:center; }
  td { font-size: 8.5px; }

  .c-num { width: 20px; text-align:center; }
  .c-firma { width: 70px; }

  /* ✅ Firmas bonitas (como antes) */
  .sign-table{
    width:100%;
    margin-top: 22px;
    border-collapse: collapse;
  }
  .sign-table td{
    border: none;
    padding: 0;
    text-align:center;
    width:50%;
  }
  .sign-line{
    width: 280px;
    height: 14px;
    margin: 0 auto 6px;
    border-bottom: 1px solid #111827;
  }
  .sign-label{
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .3px;
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    ' . $logoHtml . '
    <p class="h1">' . h($H1) . '</p>
    ' . ($H2 !== '' ? '<p class="h2">' . h($H2) . '</p>' : '') . '
    <p class="meta">MES: ' . h($mes) . ' &nbsp;&nbsp; DÍA: ' . h($dia) . ' &nbsp;&nbsp; AÑO: ' . h($anio) . '</p>
    ' . ($H3 !== '' ? '<p class="h3">' . h($H3) . '</p>' : '') . '
  </div>

  <table>
    <thead>
      <tr>
        <th class="c-num">N°</th>
        <th>Nombre y apellidos</th>
        <th>Tipo doc</th>
        <th>N° documento</th>
        ' . $colsHeader . '
        <th class="c-firma">Firma</th>
      </tr>
    </thead>
    <tbody>
      ' . $rowsHtml . '
    </tbody>
  </table>

  <table class="sign-table">
    <tr>
      <td>
        <div class="sign-line"></div>
        <div class="sign-label">PRESIDENTE</div>
      </td>
      <td>
        <div class="sign-line"></div>
        <div class="sign-label">SECRETARIO</div>
      </td>
    </tr>
  </table>

</div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/..'));

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("DejaVu Sans", "normal");
$canvas->page_text(520, 820, "Página {PAGE_NUM} / {PAGE_COUNT}", $font, 9, [107,114,128]);

$dompdf->stream($nombreArchivo, ['Attachment' => true]);
exit;
