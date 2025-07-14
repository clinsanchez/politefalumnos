<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('ODOO_URL', $_ENV['ODOO_URL']);
define('ODOO_DB', $_ENV['ODOO_DB']);
define('ODOO_USER', $_ENV['ODOO_USER']);
define('ODOO_PASS', $_ENV['ODOO_PASS']);
?>
