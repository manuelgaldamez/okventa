<?php
require __DIR__ . '/ticket/vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

/* CONFIG LOG */
function log_debug($mensaje) {
    // file_put_contents(__DIR__ . "/log_ticket.txt", date("Y-m-d H:i:s") . " - " . $mensaje . PHP_EOL, FILE_APPEND);
}

/* Forzar JSON limpio */
ob_start();
header('Content-Type: application/json');

try {

    log_debug("===== NUEVA PETICION =====");

    /* Leer body */
    $inputRaw = file_get_contents("php://input");
    log_debug("RAW INPUT: " . $inputRaw);

    $input = json_decode($inputRaw);

    if (!$input) {
        throw new Exception("JSON inválido");
    }

    $nombre_impresora = $input->nombreImpresora ?? null;
    $operaciones = $input->operaciones ?? [];

    log_debug("Impresora: " . $nombre_impresora);
    log_debug("Operaciones: " . count($operaciones));

    if (!$nombre_impresora) {
        throw new Exception("No viene nombre de impresora");
    }

    /* Conectar impresora */
    $connector = new WindowsPrintConnector($nombre_impresora);
    $printer = new Printer($connector);

    log_debug("Conectado a impresora");

    foreach ($operaciones as $op) {
        $nombre = $op->nombre ?? "";
        $args = $op->argumentos ?? [];

        log_debug("Ejecutando: " . $nombre);

        switch ($nombre) {

            case "Iniciar":
                break;

            case "EstablecerAlineacion":
                $printer->setJustification($args[0]);
                break;

            case "Feed":
                $printer->feed($args[0]);
                break;

            case "EscribirTexto":
                $printer->text($args[0]);
                break;

            case "EstablecerEnfatizado":
                $printer->setEmphasis($args[0]);
                break;

            case "CorteParcial":
                $printer->cut();
                break;

            case "Pulso":
                $printer->pulse();
                break;

            case "ImprimirImagenEnBase64":
                try {
                    $img = base64_decode($args[0]);
                    $tmp = tempnam(sys_get_temp_dir(), 'logo') . ".png";
                    file_put_contents($tmp, $img);

                    $logo = EscposImage::load($tmp, false);
                    $printer->bitImage($logo);

                    unlink($tmp);
                } catch (Exception $e) {
                    log_debug("Error imagen: " . $e->getMessage());
                }
                break;

            default:
                log_debug("Operacion no soportada: " . $nombre);
                break;
        }
    }

    $printer->close();

    log_debug("IMPRESION OK");

    ob_clean();
    echo json_encode([
        "success" => true
    ]);

} catch (Exception $e) {

    log_debug("ERROR: " . $e->getMessage());

    ob_clean();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}