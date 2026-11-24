<?php

declare(strict_types=1);

namespace Telcom\Soporte;

use DateTimeImmutable;
use DateTimeZone;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

final class Excel
{
    public function crear(array $hojas, array $criterios): string
    {
        $file = tempnam(sys_get_temp_dir(), 'telcom-excel-');
        if ($file === false) {
            throw new \RuntimeException('No se pudo preparar el archivo.');
        }
        chmod($file, 0600);
        $options = new Options();
        $options->setProperties(new Properties(title: 'Telcom Gestión', creator: 'Telcom Gestión'));
        $options->SHOULD_CREATE_NEW_SHEETS_AUTOMATICALLY = false;
        $writer = new Writer($options);
        try {
            $writer->openToFile($file);
            $criteriosRows = [];
            foreach ($criterios as $key => $value) {
                $criteriosRows[] = ['criterio' => $key, 'valor' => $value];
            }
            array_unshift($hojas, ['nombre' => 'Criterios', 'columnas' => ['criterio' => ['Criterio'],
                'valor' => ['Valor']], 'filas' => $criteriosRows]);
            foreach ($hojas as $index => $data) {
                $sheet = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($data['nombre']);
                $view = new SheetView();
                $view->setFreezeRow(2);
                $sheet->setSheetView($view);
                $header = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('17365D');
                $cells = [];
                foreach (array_values($data['columnas']) as $i => $column) {
                    $cells[] = new StringCell($column[0], null);
                    $sheet->setColumnWidth($i === 0 ? 24 : 30, $i + 1);
                }
                $writer->addRow(new Row($cells, $header));
                $line = 1;
                foreach ($data['filas'] as $row) {
                    if (++$line > 1048576) {
                        throw new ErrorOperacion(
                            'El resultado supera el límite de una hoja Excel. Reduce los filtros.',
                            422
                        );
                    }
                    $cells = [];
                    foreach ($data['columnas'] as $key => $column) {
                        $cells[] = $this->celda($row[$key] ?? null, $column[1] ?? 'text');
                    }
                    $writer->addRow(new Row($cells));
                }
                $sheet->setAutoFilter(new AutoFilter(0, 1, count($data['columnas']) - 1, max(1, $line)));
            }
            $writer->close();
            return $file;
        } catch (Throwable $e) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            unlink($file);
            throw $e;
        }
    }

    private function celda(mixed $value, string $type): \OpenSpout\Common\Entity\Cell
    {
        if ($value === null) {
            return new EmptyCell(null, null);
        }
        if ($type === 'date') {
            $date = (new DateTimeImmutable((string)$value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('America/Mexico_City'));
            return new DateTimeCell($date, (new Style())->setFormat('dd/mm/yyyy hh:mm'));
        }
        if ($type === 'int') {
            return new NumericCell((int)$value, (new Style())->setFormat('0'));
        }
        if ($type === 'money') {
            return new NumericCell((float)$value, (new Style())->setFormat('"$"#,##0.00'));
        }
        if ($type === 'active') {
            $value = (int)$value === 1 ? 'Activo' : 'Inactivo';
        }
        if (in_array($type, ['REP', 'V', 'EQ', 'PROD'], true)) {
            $value = $type . '-' . str_pad((string)$value, 4, '0', STR_PAD_LEFT);
        }
        if (preg_match_all('/./us', (string)$value) > 32767) {
            throw new ErrorOperacion(
                'Un texto supera el límite de celda de Excel. Reduce su longitud antes de exportar.',
                422
            );
        }
        // El tipo de texto explícito conserva ceros iniciales e impide fórmulas provenientes de datos.
        return new StringCell((string)$value, (new Style())->setShouldWrapText());
    }
}
