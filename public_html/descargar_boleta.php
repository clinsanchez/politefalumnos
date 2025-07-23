<?php
session_start();
require('fpdf.php'); // Asegúrate que la ruta a fpdf.php sea correcta

// Validación de sesión para generar el PDF
if (!isset($_SESSION['student']) || !isset($_SESSION['grade_table']) || !isset($_SESSION['student_info'])) {
    die("No se pudo generar la boleta. La información no está disponible. Por favor, regresa a la página de calificaciones e inténtalo de nuevo.");
}

// Obtener datos de la sesión
$student_name = $_SESSION['student']['name'] ?? 'Desconocido';
$grades = $_SESSION['grade_table'];
$info = $_SESSION['student_info'];

$matricula = $info['matricula'] ?? 'Desconocida';
$grupo = $info['grupo'] ?? 'N/A';
$seccion = $info['seccion'] ?? 'N/A';
$ciclo = $info['ciclo'] ?? 'N/A';

// Ordenar las columnas de los parciales
$parciales = [];
if (!empty($grades)) {
    foreach ($grades as $materia => $datos) {
        foreach ($datos as $parcial => $cal) {
            $parciales[$parcial] = true;
        }
    }
    $parciales = array_keys($parciales);
    usort($parciales, function ($a, $b) {
        $orden = ['Parcial 1' => 1, 'Parcial 2' => 2, 'Parcial 3' => 3, 'Calificación Final' => 4];
        return ($orden[$a] ?? 99) - ($orden[$b] ?? 99);
    });
}

// Clase PDF personalizada para cabecera y pie de página
class PDF extends FPDF
{
    function Header()
    {
        $this->Image('Imagenes/logoPolitef.png', 10, 8, 30);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, utf8_decode('Papeleta de Calificaciones'), 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        $this->MultiCell(0, 5, utf8_decode("Este documento es solo de carácter informativo y no tiene validez oficial.\nInstituto Politécnico de la Frontera - https://politefalumnos.com"), 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// Info del alumno
$pdf->Cell(0, 10, utf8_decode("Nombre del Alumno: ") . utf8_decode($student_name), 0, 1);
$pdf->Cell(0, 10, utf8_decode("Matrícula: $matricula"), 0, 1);
$pdf->Cell(0, 10, utf8_decode("Grupo: $grupo    Sección: $seccion    Ciclo Escolar: $ciclo"), 0, 1);
$pdf->Ln(5);

// Encabezado de tabla
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(110, 10, utf8_decode('Materia'), 1, 0, 'C', true);
foreach ($parciales as $parcial) {
    $pdf->Cell(40, 10, utf8_decode($parcial), 1, 0, 'C', true);
}
$pdf->Ln();

// --- SECCIÓN CORREGIDA ---
// Contenido de la tabla
$pdf->SetFont('Arial', '', 10); // Un tamaño de fuente ligeramente menor para el contenido
foreach ($grades as $materia => $calificaciones) {
    // Guardar la posición Y inicial de la fila
    $y_inicial = $pdf->GetY();
    $x_inicial = $pdf->GetX();

    // Dibujar la celda de la materia (MultiCell) para que el texto se divida en varias líneas si es necesario
    $pdf->MultiCell(110, 8, utf8_decode($materia), 1, 'L');

    // Calcular la altura que ocupó la celda de la materia
    $altura_fila = $pdf->GetY() - $y_inicial;

    // Reposicionar el cursor para dibujar las celdas de las calificaciones
    $pdf->SetXY($x_inicial + 110, $y_inicial);

    // Dibujar las celdas de las calificaciones con la altura calculada
    foreach ($parciales as $parcial) {
        $nota = $calificaciones[$parcial] ?? '-';
        // El último parámetro de Cell es la altura de la celda
        $pdf->Cell(40, $altura_fila, is_numeric($nota) ? number_format($nota, 1) : utf8_decode($nota), 1, 0, 'C');
    }
    
    // Mover el cursor a la siguiente línea. MultiCell ya movió el cursor Y, por lo que un Ln() simple es suficiente.
    $pdf->Ln($altura_fila);
}
// --- FIN DE SECCIÓN CORREGIDA ---

ob_clean(); // Evitar errores por contenido previo
$pdf->Output('I', 'boleta_calificaciones.pdf');
exit;