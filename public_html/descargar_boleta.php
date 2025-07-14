<?php
session_start();
require('fpdf.php');

// Validación de sesión
if (!isset($_SESSION['student']) || !isset($_SESSION['grade_table']) || !isset($_SESSION['student_info'])) {
    die("Datos insuficientes para generar la boleta.");
}

$student = $_SESSION['student'];
$grades = $_SESSION['grade_table'];
$info = $_SESSION['student_info'];

$nombre = $student['name'] ?? 'Desconocido';
$matricula = $info['matricula'] ?? 'Desconocida';
$grupo = $info['grupo'] ?? 'N/A';
$seccion = $info['seccion'] ?? 'N/A';
$ciclo = $info['ciclo'] ?? 'N/A';

// Ordenar columnas
$parciales = [];
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

// PDF
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
        $this->MultiCell(0, 5, utf8_decode("Este documento es solo de carácter informativo. No tiene validez oficial.\nInstituto Politécnico de la Frontera - https://politefalumnos.com"), 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// Datos del alumno
$pdf->Cell(0, 10, utf8_decode("Nombre del Alumno: ") . utf8_decode($nombre), 0, 1);
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

// Contenido
$pdf->SetFont('Arial', '', 11);
foreach ($grades as $materia => $calificaciones) {
    $yStart = $pdf->GetY();
    $xStart = $pdf->GetX();
    
    // Altura estimada de la celda de materia
    $pdf->MultiCell(110, 8, utf8_decode($materia), 1);
    $yEnd = $pdf->GetY();
    $altura = $yEnd - $yStart;

    // Regresar al inicio de la fila para imprimir calificaciones
    $pdf->SetXY($xStart + 110, $yStart);

    foreach ($parciales as $parcial) {
        $nota = $calificaciones[$parcial] ?? '-';
        $pdf->Cell(40, $altura, is_numeric($nota) ? number_format($nota, 1) : utf8_decode($nota), 1, 0, 'C');
    }
    $pdf->Ln($altura);
}

ob_clean(); // evitar errores por espacios o echo previos
$pdf->Output('I', 'boleta_calificaciones.pdf');
exit;
