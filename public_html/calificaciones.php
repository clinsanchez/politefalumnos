<?php
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

// Verificar el estado del estudiante
$student_info = $odoo->search_read(
    'op.student',
    [['id', '=', $student_id]],
    ['fields' => ['state']]
);

$student_state = $student_info[0]['state'] ?? '';

// Mostrar mensaje si el estado es Baja temporal o Baja definitiva
if (in_array(strtolower($student_state), ['baja temporal', 'baja definitiva'])) {
    echo '
    <div class="container mt-5">
        <div class="alert alert-warning text-center p-4 shadow-lg" role="alert">
            <h4 class="alert-heading">Acceso restringido</h4>
            <p>Actualmente tu estado es <strong>' . htmlspecialchars($student_state) . '</strong>. Para visualizar tus calificaciones, es necesario regularizar tu situación académica y financiera.</p>
            <hr>
            <a href="dashboard.php" class="btn btn-warning">Regresar</a>
        </div>
    </div>';
    exit();
}

// Obtener calificaciones (Lógica PHP proporcionada por el usuario)
$grades_data_raw = $odoo->search_read(
    'op.student.grades',
    [['student_id', '=', $student_id]],
    ['fields' => ['grade', 'evaluation_to_faculties_id']]
);

$grade_table = [];

// Recolectar datos organizados por materia y parcial
foreach ($grades_data_raw as $grade) {
    if (isset($grade['evaluation_to_faculties_id'][0])) {
        $eval_id = $grade['evaluation_to_faculties_id'][0];

        $eval = $odoo->read(
            'op.evaluation.to.faculties',
            [$eval_id],
            ['subject_id', 'evaluation_schedule_id']
        );

        if (!empty($eval)) {
            $subject = $eval[0]['subject_id'][1] ?? 'Materia Desconocida'; 
            $schedule = $eval[0]['evaluation_schedule_id'][1] ?? 'Parcial Desconocido';
            $parcial_name_parts = explode("/", $schedule);
            //$parcial = trim($parcial_name_parts[0]);
            $raw_parcial = strtoupper(trim($parcial_name_parts[0]));
            if (strpos($raw_parcial, 'CALIFICACION FINAL') !== false) {
                $parcial = 'Calificación Final';
            } elseif (strpos($raw_parcial, 'PARCIAL 1') !== false) {
                $parcial = 'Parcial 1';
            } elseif (strpos($raw_parcial, 'PARCIAL 2') !== false) {
                $parcial = 'Parcial 2';
            } elseif (strpos($raw_parcial, 'PARCIAL 3') !== false) {
                $parcial = 'Parcial 3';
            } else {
                $parcial = $parcial_name_parts[0];
            }
            if (!empty($subject) && !empty($parcial) && $parcial !== 'Parcial Desconocido') { // Evitar 'Parcial Desconocido' como cabecera
                $grade_table[$subject][$parcial] = $grade['grade'];
            }
        }
    }
}

// Obtener lista única de parciales y ordenarlos
$parciales = [];
if (!empty($grade_table)) {
    foreach ($grade_table as $materia => $valores) {
        foreach ($valores as $parcial_key => $calificacion) { // Usar $parcial_key para evitar sobreescribir $parcial
            $parciales[$parcial_key] = true;
        }
    }
    $parciales = array_keys($parciales);
    // Lógica de ordenamiento de parciales (la que proporcionaste)
    usort($parciales, function($a, $b) {
        $orden = [
            'Parcial 1' => 1,
            'Parcial 2' => 2,
            'Parcial 3' => 3,
            'Calificación Final' => 4
        ];
        $a = trim($a);
        $b = trim($b);
        $pos_a = $orden[$a] ?? 99;
        $pos_b = $orden[$b] ?? 99;
        return $pos_a - $pos_b;
    });

    //sort($parciales); // O la lógica de usort si prefieres un orden específico
    // Ejemplo con usort (asegúrate que los nombres en $order coincidan con tus datos)
    /*
    usort($parciales, function($a, $b) { 
        $order = ['1er Parcial', 'Parcial 1', 'Primer Parcial', 
                  '2do Parcial', 'Parcial 2', 'Segundo Parcial', 
                  '3er Parcial', 'Parcial 3', 'Tercer Parcial', 
                  'Examen Final', 'Final', 
                  'Extraordinario', 'Título']; 
        
        $pos_a = array_search(trim($a), $order);
        $pos_b = array_search(trim($b), $order);

        if ($pos_a === false && $pos_b === false) return strcmp(trim($a), trim($b)); 
        if ($pos_a === false) return 1; 
        if ($pos_b === false) return -1; 

        return $pos_a - $pos_b;
    });
    */
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones - <?= htmlspecialchars($student_name) ?> - Politef</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css"> 
    <style>
        /* ESTILOS PROFESIONALES MEJORADOS */
        body {
            /* Estilos de fondo originales del usuario */
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif; /* Inter como principal, Segoe UI y Arial como fallback */
            background: url('Imagenes/dashboard.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            color: #343a40; /* Color de texto general */
            display: flex; /* Para sticky footer */
            flex-direction: column; /* Para sticky footer */
            min-height: 100vh; /* Para sticky footer */
        }

        /* Se eliminan .bg-container, .bg-image y .bg-container::before */

        .navbar {
            background-color: rgba(255, 255, 255, 0.97); /* Navbar semi-transparente para ver el fondo del body */
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
        
        /* Estilos para el .contenedor del usuario */
        .contenedor {
            /* Estilos originales del usuario para .contenedor */
            width: 95%;
            max-width: 1000px; /* Máximo ancho original */
            margin: 40px auto; /* Margen original */
            background: rgba(255, 255, 255, 0.97); /* Fondo original (ligeramente más opaco para mejor contraste) */
            padding: 30px; /* Padding original */
            border-radius: 16px; /* Bordes más redondeados (mejora) */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 3px 8px rgba(0,0,0,0.08); /* Sombra mejorada */
            border: 1px solid rgba(0,0,0,0.1); /* Borde sutil mejorado */
        }
        
        /* Estilo para el H2 del usuario */
        .contenedor h2 {
            text-align: center;
            color: #005A9C; /* Azul original del usuario #00796b, pero usando el azul corporativo sugerido */
            font-weight: 700; 
            font-size: 1.9rem; 
            margin-bottom: 2rem; 
            padding-bottom: 1rem;
            border-bottom: 2px solid #00796b; /* Color original del usuario para el borde */
            display: inline-block; 
            position: relative;
            left: 50%;
            transform: translateX(-50%);
        }

        /* Estilos para la tabla del usuario (table.table.table-bordered) */
        .table { 
            margin-top: 20px; /* Margen original */
            border: 1px solid #d1d9e0; 
            border-radius: 8px; 
            overflow: hidden;
            border-collapse: separate; /* Necesario para border-radius en tablas con bordes colapsados por Bootstrap */
            border-spacing: 0;
        }

        .table thead th {
            background: #00796b; /* Color de th original del usuario */
            color: white; /* Color de texto original */
            font-weight: 600; 
            padding: 12px; /* Padding original */
            border: 1px solid #006a5f; /* Borde similar al original, más oscuro que el fondo */
            text-align: center; 
            vertical-align: middle;
            font-size: 0.95rem;
        }
        .table tbody td {
            padding: 12px; /* Padding original */
            border: 1px solid #e0e5e9; /* Borde original, color más suave */
            text-align: center;
            vertical-align: middle;
            font-size: 0.9rem;
            color: #454f58;
        }
        /* Se mantiene el hover de Bootstrap por defecto si se usa .table-hover */
        /* Para filas alternas, si se desea manually sin .table-striped */
        .table tbody tr:nth-child(even) { 
             background-color: rgba(248, 250, 252, 0.5); /* Un color de fila alterna muy sutil */
        }
        .table.table-hover tbody tr:hover { /* Asegurar que el hover sea visible */
            background-color: rgba(0, 121, 107, 0.1); /* Hover con el color teal original, pero más suave */
        }
        
        .table tbody td:first-child { /* Materia */
            text-align: left !important; 
            font-weight: 500; 
            color: #004985; 
        }

        .grade-fail { /* Clase para calificaciones reprobatorias */
            color: red !important; /* Estilo original del usuario, con !important para asegurar */
            font-weight: bold !important; /* Estilo original del usuario, con !important para asegurar */
        }
        /* No se agrega .grade-pass a menos que se quiera específicamente */


        /* Estilo para el botón de regresar del usuario */
        .btn-regresar-wrapper { 
            text-align: center;
            margin-top: 2.5rem; 
        }
        .btn-regresar {
            /* Estilos originales del usuario para .btn-regresar */
            background-color: #f5b500; 
            color: white;
            padding: 10px 30px; 
            border: none;
            border-radius: 25px; /* Mejora: más redondeado */
            font-weight: bold; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center;
            transition: background-color 0.25s ease, transform 0.15s ease, box-shadow 0.25s ease;
            box-shadow: 0 4px 10px rgba(245, 181, 0, 0.25);
            font-size: 1rem;
        }
        .btn-regresar:hover {
            background-color: #d99c00; /* Hover original del usuario */
            color: white; /* Asegurar color en hover */
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(224, 168, 0, 0.35);
        }
        .btn-regresar .bi {
            margin-right: 8px; 
            font-size: 1.1rem;
        }
        
        .footer {
            text-align: center;
            padding: 1.8rem 0;
            font-size: 0.9em;
            background-color: rgba(44, 62, 80, 0.95); /* Footer semi-transparente */
            color: rgba(255, 255, 255, 0.85);
            margin-top: auto; /* Para sticky footer */
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

        /* Responsive adjustments (manteniendo los del CSS anterior que eran buenos) */
        @media (max-width: 992px) {
            .contenedor {
                width: 98%;
                padding: 1.5rem;
                margin: 20px auto; /* Reducir margen en pantallas medianas */
            }
            .contenedor h2 {
                font-size: 1.7rem;
            }
        }
        @media (max-width: 768px) {
            .contenedor h2 {
                font-size: 1.5rem;
                 left: 0; 
                 transform: none;
                 display: block; 
            }
            .table thead th, .table tbody td {
                font-size: 0.85rem;
                padding: 0.6rem 0.4rem;
            }
            .btn-regresar {
                width: 100%;
                padding: 12px 20px;
            }
        }
         @media (max-width: 576px) {
            body {
                 /* Forzar que el fondo cubra incluso si el contenido es corto en móviles y el body no se estira completamente */
                 /* Esto es un poco un hack, idealmente el contenido haría que el body se estire */
                background-attachment: scroll; /* Puede mejorar rendimiento en móviles y evitar cortes raros */
            }
            .contenedor {
                padding: 1rem;
                margin: 20px auto; /* Margen consistente */
                border-radius: 12px; /* Reducir un poco el redondeo en móviles */
            }
            .contenedor h2 {
                font-size: 1.35rem;
                margin-bottom: 1.5rem;
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
             .table thead th, .table tbody td {
                padding: 0.5rem 0.3rem; 
            }
            .btn-regresar {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body> <nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"> 
            <img src="Imagenes/logoPolitef.png" alt="Logo Politef">
        </a>
        <span class="navbar-text ms-auto">
            Alumno: <?= strtoupper(htmlspecialchars($student_name)) ?>
        </span>
    </div>
</nav>

<div class="contenedor">
    <h2>Calificaciones de <?= strtoupper(htmlspecialchars($student_name)); ?></h2>
    
    <?php if (!empty($grade_table) && !empty($parciales)): ?>
    <div class="table-responsive"> 
        <table class="table table-hover table-bordered"> <thead>
                <tr>
                    <th>Materia</th>
                    <?php foreach ($parciales as $parcial_col): ?>
                        <th><?= htmlspecialchars($parcial_col); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grade_table as $materia => $valores): ?>
                    <tr>
                        <td><?= htmlspecialchars($materia); ?></td>
                        <?php foreach ($parciales as $parcial_col): ?>
                            <?php
                                $calificacion_display = '-';
                                $cell_class = '';
                                if (isset($valores[$parcial_col])) {
                                    $cal = $valores[$parcial_col];

                                    if ($cal === false) { // Manejar explícitamente boolean false como 0
                                        $cal_numeric_for_comparison = 0.0;
                                        $calificacion_display = "0"; // Mostrar "0" en lugar de string vacío
                                    } elseif (is_numeric($cal)) {
                                        $cal_numeric_for_comparison = floatval($cal);
                                        $calificacion_display = htmlspecialchars($cal);
                                    } else {
                                        // No numérico y no false (ej. "N/A"), sin estilo especial de reprobado
                                        $cal_numeric_for_comparison = null; 
                                        $calificacion_display = htmlspecialchars($cal);
                                    }

                                    if ($cal_numeric_for_comparison !== null && $cal_numeric_for_comparison < 6.0) {
                                        $cell_class = 'grade-fail';
                                    }
                                }
                            ?>
                            <td class="<?= $cell_class ?>"><?= $calificacion_display ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-warning text-center mt-3 shadow-sm" role="alert"> 
        <i class="bi bi-exclamation-triangle-fill me-2"></i> Actualmente no tienes calificaciones registradas para mostrar.
    </div>
    <?php endif; ?>

    <div class="btn-regresar-wrapper text-center mt-4"> <form action="dashboard.php" style="display: inline;"> 
            <button type="submit" class="btn-regresar">
                <i class="bi bi-arrow-left-circle-fill"></i> Regresar
            </button>
        </form>
    </div>
</div>

<footer class="footer mt-auto"> 
    &copy; <?php echo date("Y"); ?> <a href="https://politefalumnos.com" target="_blank">Politef Alumnos</a>. Todos los derechos reservados.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>