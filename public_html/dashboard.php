<?php
session_start();
require_once 'ripcord.php'; // Assuming ripcord.php is for Odoo or other backend logic

// Redirect to login if student session is not set
if (!isset($_SESSION['student'])) {
    header("Location: index.php");
    exit();
}

// Retrieve student data from session
$student = $_SESSION['student'];
$student_id = $student['id'];
$student_name = $student['name'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Alumno Politef</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Segoe+UI:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Base styles */
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            color: #333;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        /* Background container for image and overlay */
        .bg-container {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-image {
            /* Ensure 'Imagenes/dashboard.jpg' is the correct path */
            background-image: url('Imagenes/dashboard.jpg'); 
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
            background-size: cover;
            height: 100%;
            width: 100%;
            /* Se elimina filter: blur(2px); y transform: scale(1.03); para una imagen nítida */
        }

        /* Optional overlay for better contrast */
        /* Este overlay se mantiene, ya que ayuda a la legibilidad de la tarjeta sobre el fondo */
        .bg-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.25); /* Ajustado ligeramente para buen contraste sin ser muy oscuro */
            z-index: 1; 
        }

        /* Navbar styling */
        .navbar {
            background-color: rgba(255, 255, 255, 0.97); 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); 
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            position: sticky; /* Navbar fija al hacer scroll */
            top: 0;
            z-index: 1030; /* Asegura que esté sobre otros elementos */
        }

        .navbar-brand img {
            height: 45px; 
            border-radius: 4px; 
        }

        .navbar-text {
            color: #2a3a4a !important; 
            font-weight: 600; 
        }
        
        /* Main content area */
        .main-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 70px); /* Ajustar según la altura real del navbar */
            padding: 2rem 1rem; 
            position: relative; 
            z-index: 2; /* Contenido sobre el overlay del fondo */
        }

        /* Dashboard card styling */
        .dashboard-card {
            background-color: rgba(255, 255, 255, 0.98); 
            padding: 2.5rem; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); 
            text-align: center;
            max-width: 480px; 
            width: 100%;
            border: 1px solid #e0e0e0; 
        }
        
        .card-title-custom {
            font-size: 1.75rem; 
            font-weight: 700;
            color: #004985; 
            margin-bottom: 2rem;
        }

        /* Button styling */
        .dashboard-btn-link {
            text-decoration: none; 
            display: block; 
            margin-bottom: 1rem; 
        }
        
        .dashboard-btn, .logout-btn {
            color: white;
            padding: 14px 22px; 
            border: none;
            border-radius: 10px; 
            width: 100%; 
            transition: all 0.3s ease;
            font-size: 1.05rem; 
            font-weight: 500; 
            display: flex; 
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .dashboard-btn .bi, .logout-btn .bi {
            margin-right: 10px; 
            font-size: 1.2rem; 
        }

        /* Specific color for main action buttons */
        .btn-calificaciones { background-color: #00796b; } 
        .btn-calificaciones:hover { background-color: #005a4f; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 121, 107, 0.3); }

        .btn-pagos { background-color: #0288d1; } 
        .btn-pagos:hover { background-color: #016ca8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(2, 136, 209, 0.3); }
        
        .btn-plataforma { background-color: #388e3c; } 
        .btn-plataforma:hover { background-color: #2a6e2f; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(56, 142, 60, 0.3); }

        /* Logout button styling */
        .logout-btn {
            background-color: #f57c00; 
            margin-top: 1rem; 
        }
        .logout-btn:hover {
            background-color: #d86d00; 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 124, 0, 0.3);
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 1.5rem 0; /* Ajustado para consistencia */
            font-size: 0.9em;
            background-color: rgba(44, 62, 80, 0.95); /* Consistente con calificaciones.php */
            color: rgba(255, 255, 255, 0.85);
            position: relative; 
            z-index: 2;
            width: 100%;
            margin-top: auto; /* Para empujar el footer hacia abajo si el contenido es corto */
        }
         .footer a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
        }
        .footer a:hover {
            text-decoration: underline;
            color: #f5b500; /* Color de hover consistente */
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .dashboard-card {
                padding: 1.5rem;
            }
            .card-title-custom {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
            .dashboard-btn, .logout-btn {
                padding: 12px 18px;
                font-size: 0.95rem;
            }
             .navbar-brand img {
                height: 35px; 
            }
            .navbar-text {
                font-size: 0.9rem;
            }
            .main-content {
                 min-height: calc(100vh - 60px - 50px); /* navbar_height - footer_height approx */
            }
        }

    </style>
</head>
<body class="d-flex flex-column"> <!-- d-flex y flex-column para sticky footer -->

<div class="bg-container">
    <div class="bg-image"></div>
</div>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"> <!-- Enlace a dashboard.php -->
            <img src="Imagenes/logoPolitef.png" alt="Logo Politef">
        </a>
        <span class="navbar-text ms-auto">
            Bienvenido(a) <?= strtoupper(htmlspecialchars($student_name)) ?>
        </span>
    </div>
</nav>

<div class="main-content">
    <div class="dashboard-card">
        <h2 class="card-title-custom">Portal Alumno</h2> 
        
        <a href="calificaciones.php" class="dashboard-btn-link">
            <button class="dashboard-btn btn-calificaciones"><i class="bi bi-clipboard-data"></i> Calificaciones</button>
        </a>
        <a href="pagos.php" class="dashboard-btn-link">
            <button class="dashboard-btn btn-pagos"><i class="bi bi-cash-coin"></i> Pagos Pendientes</button> 
        </a>
        <a href="http://plataforma.politefjrz.com/login/index.php" target="_blank" class="dashboard-btn-link">
            <button class="dashboard-btn btn-plataforma"><i class="bi bi-laptop"></i> Plataforma Digital</button>
        </a>
        
        <form method="post" action="logout.php" class="mt-3">
            <button class="logout-btn" type="submit"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</button>
        </form>
    </div>
</div>

<footer class="footer">
    &copy; <?php echo date("Y"); ?> <a href="https://politefalumnos.com" target="_blank">Politef Alumnos</a>. Todos los derechos reservados.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>