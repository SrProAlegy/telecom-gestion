<?php

declare(strict_types=1);

function libro(array &$usuario, string $ruta): array
{
    $r = pedir($usuario, $ruta);
    verificar($r['status'] === 200 && str_starts_with($r['body'], "PK\x03\x04"), "Excel válido: $ruta");
    verificar(str_contains(
        implode('\n', $r['headers']),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ), 'Tipo de descarga XLSX');
    $file = tempnam(sys_get_temp_dir(), 'telcom-reader-');
    file_put_contents($file, $r['body']);
    $reader = new OpenSpout\Reader\XLSX\Reader();
    $result = [];
    try {
        $zip = new ZipArchive();
        verificar($zip->open($file, ZipArchive::CHECKCONS) === true, 'Integridad del contenedor Excel');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/sheet')) {
                $xml = new DOMDocument();
                verificar($xml->loadXML($zip->getFromIndex($i)), 'Hoja XML bien formada');
                verificar($xml->getElementsByTagName('f')->length === 0, 'Datos exportados sin fórmulas ejecutables');
                verificar($xml->getElementsByTagName('autoFilter')->length === 1, 'Filtro nativo de Excel');
                verificar(
                    $xml->getElementsByTagName('pane')->item(0)?->getAttribute('ySplit') === '1',
                    'Encabezado inmovilizado'
                );
            }
        }
        $zip->close();
        $reader->open($file);
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $result[$sheet->getName()] = $rows;
        }
    } finally {
        $reader->close();
        unlink($file);
    }
    return $result;
}
