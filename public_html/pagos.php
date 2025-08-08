<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'odoo_client.php';

if (!isset($_SESSION['student'])) {
    header("Location: index.php");
    exit();
}

$student = $_SESSION['student'];
$student_id = $student['id'];
$student_name = $student['name'];

$odoo = new OdooClient();

function get_amount_residual($reference) {
    // Asegúrate que la URL sea accesible desde tu servidor Hostinger
    // Considera tiempos de espera y manejo de errores más robusto si esta llamada es crítica y puede fallar.
    $url = "http://31.220.31.26:5000/residual?reference=" . urlencode($reference);
    $context_options = [
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
        "http" => [
            "timeout" => 5 // Tiempo de espera en segundos
        ]
    ];
    $context = stream_context_create($context_options);
    $response = @file_get_contents($url, false, $context);

    if ($response === FALSE) {
        // Log error or handle more gracefully
        // error_log("Error fetching residual amount for reference: $reference from URL: $url");
        return null;
    }
    $data = json_decode($response, true);
    return isset($data['amount_residual']) ? $data['amount_residual'] : null;
}

$fees = $odoo->search_read(
    'op.student.fees.details',
    [['student_id', '=', $student_id]],
    ['fields' => ['date', 'invoice_description', 'amount', 'discount', 'invoice_id']]
);

$invoice_ids = [];
if (is_array($fees)) {
    foreach ($fees as $fee) {
        if (is_array($fee['invoice_id']) && isset($fee['invoice_id'][0])) {
            $invoice_ids[] = $fee['invoice_id'][0];
        }
    }
}


$invoice_data = [];
if (!empty($invoice_ids)) {
    $invoices = $odoo->read(
        'account.move',
        $invoice_ids,
        ['id', 'payment_state', 'before_payment_reference']
    );
    if(is_array($invoices)){
        foreach ($invoices as $inv) {
            if (isset($inv['payment_state']) && $inv['payment_state'] === 'not_paid' && isset($inv['id'])) {
                 // Asegurarse que before_payment_reference exista
                $invoice_data[$inv['id']] = $inv['before_payment_reference'] ?? '';
            }
        }
    }
}

$_SESSION['fees_data'] = [];
if (is_array($fees)) {
    foreach ($fees as $fee) {
        $inv_id = (is_array($fee['invoice_id']) && isset($fee['invoice_id'][0])) ? $fee['invoice_id'][0] : null;
        
        // Verificar que $inv_id no sea null y exista en $invoice_data
        if ($inv_id !== null && isset($invoice_data[$inv_id])) {
            $reference_value = $invoice_data[$inv_id];
            $residual = null;
            if(!empty($reference_value)){ // Solo llamar si hay una referencia
                $residual = get_amount_residual($reference_value);
            }
            
            $_SESSION['fees_data'][] = [
                'fecha' => $fee['date'] ?? 'N/A',
                // iconv para asegurar UTF-8, puede ser innecesario si Odoo ya sirve UTF-8
                'concepto' => isset($fee['invoice_description']) ? iconv('UTF-8', 'UTF-8//IGNORE', $fee['invoice_description']) : 'Sin descripción',
                'referencia' => $reference_value,
                'importe' => $residual ?? ($fee['amount'] ?? 0.0) // Usar amount si residual es null
            ];
        }
    }
}


// LÓGICA USANDO amount_residual DESDE account.move
try {
    $invoices = $odoo->read(
        'account.move',
        $invoice_ids,
        ['id', 'payment_state', 'before_payment_reference', 'invoice_line_ids', 'invoice_date', 'amount_residual']
    );

    $_SESSION['fees_data'] = [];

    if (is_array($invoices)) {
        foreach ($invoices as $inv) {
            if (
                isset($inv['payment_state']) &&
                in_array($inv['payment_state'], ['not_paid', 'partial']) &&
                isset($inv['id']) &&
                isset($inv['amount_residual']) &&
                floatval($inv['amount_residual']) > 0
            ) {
                $invoice_id = $inv['id'];
                $line_ids = $inv['invoice_line_ids'] ?? [];
                $reference = $inv['before_payment_reference'] ?? '';
                $fecha = $inv['invoice_date'] ?? 'N/A';
                $residual = floatval($inv['amount_residual']);

                // Opcionalmente obtener conceptos desde las líneas
                $conceptos = [];
                if (is_array($line_ids) && count($line_ids) > 0) {
                    $lines = $odoo->read(
                        'account.move.line',
                        $line_ids,
                        ['name']
                    );
                    if (is_array($lines)) {
                        foreach ($lines as $line) {
                            if (!empty($line['name'])) {
                                $conceptos[] = $line['name'];
                            }
                        }
                    }
                }

                $_SESSION['fees_data'][] = [
                    'fecha'     => $fecha,
                    'concepto'  => implode('<br>', $conceptos),
                    'importe'   => $residual,
                    'referencia' => $reference
                ];
            }
        }
    } else {
        error_log("No se pudieron obtener las facturas con invoice_ids: " . json_encode($invoice_ids));
    }
} catch (Exception $e) {
    error_log("ERROR AL PROCESAR FACTURAS (amount_residual): " . $e->getMessage());
    $_SESSION['fees_data'] = [];
}

$student_details = $odoo->read('op.student', [$student_id], ['name', 'gr_no']);
$det = $student_details[0] ?? []; // Default a array vacío si no hay detalles

$admission = $odoo->search_read(
    'op.admission',
    [['student_id', '=', $student_id]],
    ['fields' => ['course_id', 'batch_id'], 'limit' => 1]
);
$ad = $admission[0] ?? []; // Default a array vacío

$_SESSION['student_info'] = [ // Usado en descargar_pdf.php
    'name' => $det['name'] ?? '',
    'matricula' => $det['gr_no'] ?? '',
    'grupo' => (isset($ad['batch_id']) && is_array($ad['batch_id'])) ? ($ad['batch_id'][1] ?? '') : '',
    'ciclo' => '', // Este campo parece no usarse o no obtenerse
    'seccion' => (isset($ad['course_id']) && is_array($ad['course_id'])) ? ($ad['course_id'][1] ?? '') : ''
];

// Última actualización
$ultima_actualizacion = "Fecha no disponible";
try {
    $actualizacion = $odoo->search_read(
        'load.payment.bank.state.model',
        [['state', '=', 'Nuevo']], // Asumiendo que 'Nuevo' es el estado correcto
        ['fields' => ['date'], 'limit' => 1, 'order' => 'write_date desc'] // write_date podría ser más preciso que 'date'
    );

    if (!empty($actualizacion) && isset($actualizacion[0]['date']) && $actualizacion[0]['date']) {
        $dt = new DateTime($actualizacion[0]['date']);
        $meses = [
            '01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
            '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
            '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
        ];
        $mes_num = $dt->format('m');
        $mes_nombre = $meses[$mes_num] ?? $mes_num; // Fallback al número del mes
        $ultima_actualizacion = $dt->format("j") . " de $mes_nombre de " . $dt->format("Y");
    }
} catch (Exception $e) {
    // Log error: error_log("Error al obtener fecha de actualización: " . $e->getMessage());
    $ultima_actualizacion = "Error al obtener fecha";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos Pendientes - <?= htmlspecialchars($student_name) ?> - Politef</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css">
    <style>
        /* ESTILOS PROFESIONALES MEJORADOS */
        body {
            /* Estilos de fondo originales del usuario */
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: url('Imagenes/dashboard.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            color: #343a40;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .navbar {
            background-color: rgba(255, 255, 255, 0.97);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding-top: 0.8rem;
            padding-bottom: 0.8rem;
            position: sticky;
            top: 0;
            z-index: 1030;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .navbar-brand img {
            height: 48px;
            transition: transform 0.3s ease;
        }
        .navbar-brand img:hover {
            transform: scale(1.05);
        }

        .navbar-text {
            color: #003366 !important;
            font-weight: 600;
            font-size: 1.05rem;
        }

        /* Estilos para .card-container del usuario, adaptado */
        .card-container {
            /* Estilos originales del usuario */
            background-color: rgba(255, 255, 255, 0.98); /* Ligeramente más opaco */
            border-radius: 16px; /* Más redondeado */
            padding: 2rem 2.5rem; /* Más padding */
            margin-top: 2.5rem; /* Margen superior ajustado */
            margin-bottom: 2.5rem; /* Añadido margen inferior */
            box-shadow: 0 12px 35px rgba(0, 50, 100, 0.1), 0 3px 8px rgba(0,0,0,0.06); /* Sombra mejorada */
            border: 1px solid rgba(0,0,0,0.08); /* Borde sutil */
        }
        
        .card-container h2 {
            text-align: center;
            color: #005A9C; /* Azul corporativo */
            font-weight: 700;
            font-size: 1.9rem;
            margin-bottom: 1rem; /* Espacio reducido antes de "Última actualización" */
        }

        .last-update {
            /* Estilos originales del usuario */
            font-size: 0.9rem; 
            color: #5a6268; /* Gris más oscuro */
            text-align: right;
            margin-bottom: 1.5rem; /* Más espacio después de la fecha */
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed #ced4da; /* Línea separadora sutil */
        }
        .last-update .bi {
            color: #00796b; /* Icono con color temático */
        }

        /* --- INICIO: CÓDIGO CSS AÑADIDO --- */
        .clabe-info-box {
            background-color: #f0f7ff;
            border-left: 5px solid #005a9c;
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
            border-radius: 8px;
            text-align: center;
        }
        .clabe-info-box p {
            margin: 0;
            font-size: 1rem;
            color: #333;
        }
        .clabe-info-box .clabe-number {
            font-weight: 600;
            font-size: 1.2rem;
            color: #003366;
            margin-top: 5px;
            display: block;
            font-family: monospace;
        }
        /* --- FIN: CÓDIGO CSS AÑADIDO --- */

        /* Estilos para la tabla */
        .table {
            margin-top: 1rem; /* Espacio sobre la tabla */
            border: 1px solid #d1d9e0;
            border-radius: 8px;
            overflow: hidden; 
            border-collapse: separate;
            border-spacing: 0;
        }
        .table thead.table-dark th { /* Estilo original para thead */
            background-color: #2c3e50; /* Un azul oscuro/gris profesional */
            color: white;
            border-color: #34495e; /* Borde más oscuro para th */
            font-weight: 600;
            padding: 0.9rem 0.75rem;
            text-align: center;
            vertical-align: middle;
            font-size: 0.95rem;
        }
        .table tbody td {
            padding: 0.85rem 0.75rem;
            border: 1px solid #e0e5e9;
            text-align: center;
            vertical-align: middle;
            font-size: 0.9rem;
            color: #454f58;
        }
        .table tbody tr:nth-child(even) {
             background-color: rgba(248, 250, 252, 0.6);
        }
        .table.table-hover tbody tr:hover {
            background-color: rgba(0, 121, 107, 0.08); 
        }
        .table tbody td:nth-child(2) { /* Descripción/Concepto */
            text-align: left;
        }
        .table tbody td:nth-child(3) { /* Importe */
            font-weight: 500;
            color: #198754; /* Verde para importes */
        }
         .table tbody td:nth-child(4) { /* Referencia */
            /* font-family: 'Courier New', Courier, monospace; -- Se quita para mejor legibilidad general */
            font-size: 0.95rem; /* Aumenta tamaño de fuente */
            color: #343a40; /* Color más oscuro para mejor contraste */
            word-break: break-all; /* Para referencias largas */
        }

        /* Estilos para botones (manteniendo .btn-custom si se usa, o aplicando a los existentes) */
        .actions-toolbar { /* Contenedor para los botones inferiores */
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Para que se apilen en pantallas pequeñas */
            gap: 1rem; /* Espacio entre botones */
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }

        .btn-outline-secondary { /* Botón Regresar */
            border-color: #6c757d;
            color: #6c757d;
            padding: 0.6rem 1.2rem;
            border-radius: 20px; /* Más redondeado */
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-outline-secondary:hover {
            background-color: #5a6268;
            color: white;
            border-color: #545b62;
        }
        .btn-outline-secondary .bi {
            margin-right: 0.4rem;
        }

        .btn-danger { /* Botón Descargar Papeleta */
            background-color: #c82333; /* Rojo más oscuro y profesional */
            border-color: #bd2130;
            padding: 0.6rem 1.2rem;
            border-radius: 20px; /* Más redondeado */
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            background-color: #a71d2a;
            border-color: #941a25;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(200, 35, 51, 0.2);
        }
        .btn-danger .bi {
            margin-right: 0.5rem;
        }
        
        .footer {
            text-align: center;
            padding: 1.8rem 0;
            font-size: 0.9em;
            background-color: rgba(44, 62, 80, 0.95);
            color: rgba(255, 255, 255, 0.85);
            margin-top: auto;
            width: 100%;
        }
         .footer a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
        }
        .footer a:hover {
            text-decoration: underline;
            color: #f5b500;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-container h2 {
                font-size: 1.6rem;
            }
            .table thead.table-dark th, .table tbody td {
                font-size: 0.85rem;
                padding: 0.6rem 0.4rem;
            }
             .table tbody td:nth-child(4) { /* Referencia en responsive */
                font-size: 0.9rem; /* Ajustar si es necesario para pantallas pequeñas */
            }
            .actions-toolbar {
                flex-direction: column; /* Apilar botones verticalmente */
            }
            .actions-toolbar .btn {
                width: 100%; /* Botones a ancho completo */
            }
        }
         @media (max-width: 576px) {
            .card-container {
                padding: 1.5rem;
                margin-top: 1.5rem;
                margin-bottom: 1.5rem;
            }
            .card-container h2 {
                font-size: 1.4rem;
            }
            .navbar-brand img {
                height: 40px; 
            }
            .navbar-text {
                font-size: 0.9rem;
            }
            .table {
                font-size: 0.78rem; 
            }
            .table thead.table-dark th, .table tbody td {
                padding: 0.5rem 0.3rem; 
            }
             .table tbody td:nth-child(4) { /* Referencia en responsive más pequeño */
                font-size: 0.85rem; 
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"> 
            <img src="Imagenes/logoPolitef.png" alt="Logo Politef">
        </a>
        <span class="navbar-text ms-auto">
            Alumno: <?= strtoupper(htmlspecialchars($student_name)) ?>
        </span>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8 card-container">
            <h2 class="text-center mb-3">Pagos pendientes de <?= htmlspecialchars($student_name) ?></h2>
            <p class="last-update">
                <i class="bi bi-clock-history"></i> Última actualización: <strong><?= htmlspecialchars($ultima_actualizacion) ?></strong>
            </p>

            <div class="clabe-info-box">
                <p>Cuenta a nombre de: POLITECNICO DE LA FRONTERA</p>
                <span class="clabe-number">CLABE: 002164460100681188</span>
            </div>
            <?php
            $hay_pagos = false;
            // Verificar si $_SESSION['fees_data'] existe y es un array antes de iterar
            if (isset($_SESSION['fees_data']) && is_array($_SESSION['fees_data']) && !empty($_SESSION['fees_data'])) {
                foreach ($_SESSION['fees_data'] as $row):
                    if (!$hay_pagos) {
                        // Se usa la clase .table-dark para el thead como en tu código original
                        echo '<div class="table-responsive"><table class="table table-hover table-bordered"><thead class="table-dark"><tr><th>Fecha Limite</th><th>Pagos a Cubrir</th><th>Importe</th><th>Concepto de Pago</th></tr></thead><tbody>';
                        $hay_pagos = true;
                    }
            ?>
            <tr>
                <td><?= htmlspecialchars($row['fecha'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['concepto'] ?? 'N/A') ?></td>
                <td>$<?= number_format(floatval($row['importe'] ?? 0), 2) ?></td>
                <td><?= htmlspecialchars($row['referencia'] ?? 'N/A') ?></td>
            </tr>
            <?php 
                endforeach;
            } // Fin del if que verifica $_SESSION['fees_data']

            if ($hay_pagos) {
                echo '</tbody></table></div>';
            } else {
                // Mensaje si no hay pagos, usando clases de alerta de Bootstrap para mejor visibilidad
                echo "<div class='alert alert-info text-center mt-3 shadow-sm' role='alert'><i class='bi bi-info-circle-fill me-2'></i>No hay pagos pendientes registrados en este momento.</div>";
            }
            ?>
            <div class="actions-toolbar"> <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle-fill"></i> Regresar al Dashboard
                </a>
                <?php if ($hay_pagos): // Mostrar botón de descarga solo si hay pagos ?>
                <a href="descargar_pdf.php?id=<?= htmlspecialchars($student_id) ?>" class="btn btn-danger" target="_blank">
                    <i class="bi bi-file-earmark-pdf-fill"></i> Descargar Papeleta de Pagos
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<footer class="footer mt-auto"> 
    &copy; <?php echo date("Y"); ?> <a href="https://politefalumnos.com" target="_blank">Politef Alumnos</a>. Todos los derechos reservados.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>