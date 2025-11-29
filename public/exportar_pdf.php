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

$html = '
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    h1 { text-align: center; margin-bottom: 20px; }
    p { margin: 4px 0; }
    .label { font-weight: bold; }
    .bloque { margin-bottom: 12px; }
  </style>
</head>
<body>
  <h1>Ficha de registro</h1>

  <div class="bloque">
    <p><span class="label">Nombre:</span> ' . htmlspecialchars($persona['nombres'] . ' ' . $persona['apellidos']) . '</p>
    <p><span class="label">Documento:</span> ' . htmlspecialchars($persona['tipo_documento'] . ' ' . $persona['numero_documento']) . '</p>
    <p><span class="label">Afiliado:</span> ' . htmlspecialchars($persona['afiliado']) . '</p>
    <p><span class="label">Zona:</span> ' . htmlspecialchars($persona['zona']) . '</p>
    <p><span class="label">Género:</span> ' . htmlspecialchars($persona['genero']) . '</p>
    <p><span class="label">Fecha de nacimiento:</span> ' . htmlspecialchars($persona['fecha_nacimiento']) . '</p>
    <p><span class="label">Teléfono:</span> ' . htmlspecialchars($persona['telefono']) . '</p>
    <p><span class="label">Cargo:</span> ' . htmlspecialchars($persona['cargo']) . '</p>
    <p><span class="label">Nombre predio:</span> ' . htmlspecialchars($persona['nombre_predio']) . '</p>
    <p><span class="label">Correo electrónico:</span> ' . htmlspecialchars($persona['correo_electronico']) . '</p>
    <p><span class="label">Estado registro:</span> ' . htmlspecialchars($persona['estado_registro']) . '</p>
    <p><span class="label">Fecha registro:</span> ' . htmlspecialchars($persona['fecha_registro']) . '</p>
  </div>

  <div class="bloque">
    <p class="label">Nota del administrador:</p>
    <p>' . nl2br(htmlspecialchars($persona['nota_admin'])) . '</p>
  </div>
</body>
</html>
';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'registro_' . $persona['id'] . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
