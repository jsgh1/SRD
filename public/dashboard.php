<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;


$stmt = $pdo->query("SELECT COUNT(*) AS total FROM personas");
$total_personas = (int)$stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) AS total FROM personas WHERE DATE(fecha_registro) = CURDATE()");
$personas_hoy = (int)$stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COUNT(*) AS total 
    FROM personas 
    WHERE YEARWEEK(fecha_registro, 1) = YEARWEEK(CURDATE(), 1)
");
$personas_semana = (int)$stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COUNT(*) AS total 
    FROM personas 
    WHERE YEAR(fecha_registro) = YEAR(CURDATE()) 
      AND MONTH(fecha_registro) = MONTH(CURDATE())
");
$personas_mes = (int)$stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT DATE(fecha_registro) AS fecha, COUNT(*) AS total
    FROM personas
    WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(fecha_registro)
    ORDER BY fecha
");

$labels_dias = [];
$valores_dias = [];

$fechas_map = [];
while ($row = $stmt->fetch()) {
    $fechas_map[$row['fecha']] = (int)$row['total'];
}

for ($i = 6; $i >= 0; $i--) {
    $fecha = (new DateTime("-$i days"))->format('Y-m-d');
    $labels_dias[] = (new DateTime($fecha))->format('d/m');
    $valores_dias[] = $fechas_map[$fecha] ?? 0;
}

$stmt = $pdo->query("
    SELECT genero, COUNT(*) AS total
    FROM personas
    GROUP BY genero
");
$labels_genero = [];
$valores_genero = [];
while ($row = $stmt->fetch()) {
    $labels_genero[] = $row['genero'] ?: 'Sin especificar';
    $valores_genero[] = (int)$row['total'];
}

$stmt = $pdo->query("
    SELECT tipo_documento, COUNT(*) AS total
    FROM personas
    GROUP BY tipo_documento
");
$labels_doc = [];
$valores_doc = [];
while ($row = $stmt->fetch()) {
    $labels_doc[] = $row['tipo_documento'];
    $valores_doc[] = (int)$row['total'];
}

$stmt = $pdo->query("
    SELECT id, nombres, apellidos, tipo_documento, numero_documento, fecha_registro, estado_registro
    FROM personas
    ORDER BY fecha_registro DESC
    LIMIT 10
");
$ultimos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel principal - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="<?php echo $body_class; ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <h1>Panel principal</h1>
      <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_nombre'] ?? 'Administrador'); ?>.</p>

      <section class="cards-metricas">
        <div class="card-metrica">
          <h3>Total personas</h3>
          <p class="numero"><?php echo $total_personas; ?></p>
        </div>
        <div class="card-metrica">
          <h3>Registradas hoy</h3>
          <p class="numero"><?php echo $personas_hoy; ?></p>
        </div>
        <div class="card-metrica">
          <h3>Esta semana</h3>
          <p class="numero"><?php echo $personas_semana; ?></p>
        </div>
        <div class="card-metrica">
          <h3>Este mes</h3>
          <p class="numero"><?php echo $personas_mes; ?></p>
        </div>
      </section>

      <section class="graficos-grid">
        <div class="grafico-card">
          <h3>Registros últimos 7 días</h3>
          <canvas id="chartDias"></canvas>
        </div>
        <div class="grafico-card">
          <h3>Distribución por género</h3>
          <canvas id="chartGenero"></canvas>
        </div>
        <div class="grafico-card">
          <h3>Distribución por tipo de documento</h3>
          <canvas id="chartDocumento"></canvas>
        </div>
      </section>

      <section class="tabla-recientes">
        <h3>Últimos registros</h3>
        <?php if (count($ultimos) === 0): ?>
          <p>No hay registros aún.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Tipo doc</th>
                <th>N° documento</th>
                <th>Fecha registro</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ultimos as $fila): ?>
                <tr>
                  <td><?php echo htmlspecialchars($fila['nombres'] . ' ' . $fila['apellidos']); ?></td>
                  <td><?php echo htmlspecialchars($fila['tipo_documento']); ?></td>
                  <td><?php echo htmlspecialchars($fila['numero_documento']); ?></td>
                  <td><?php echo htmlspecialchars($fila['fecha_registro']); ?></td>
                  <td><?php echo htmlspecialchars($fila['estado_registro']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
    // Datos enviados desde PHP
    const labelsDias   = <?php echo json_encode($labels_dias); ?>;
    const valoresDias  = <?php echo json_encode($valores_dias); ?>;

    const labelsGenero  = <?php echo json_encode($labels_genero); ?>;
    const valoresGenero = <?php echo json_encode($valores_genero); ?>;

    const labelsDoc  = <?php echo json_encode($labels_doc); ?>;
    const valoresDoc = <?php echo json_encode($valores_doc); ?>;

    const ctxDias = document.getElementById('chartDias').getContext('2d');
    new Chart(ctxDias, {
      type: 'line',
      data: {
        labels: labelsDias,
        datasets: [{
          label: 'Registros',
          data: valoresDias,
          borderWidth: 2,
          fill: false
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

    const ctxGenero = document.getElementById('chartGenero').getContext('2d');
    new Chart(ctxGenero, {
      type: 'pie',
      data: {
        labels: labelsGenero,
        datasets: [{
          data: valoresGenero
        }]
      },
      options: {
        responsive: true
      }
    });
    
    const ctxDoc = document.getElementById('chartDocumento').getContext('2d');
    new Chart(ctxDoc, {
      type: 'pie',
      data: {
        labels: labelsDoc,
        datasets: [{
          data: valoresDoc
        }]
      },
      options: {
        responsive: true
      }
    });
  </script>
</body>
</html>
