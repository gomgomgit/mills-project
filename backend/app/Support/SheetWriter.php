<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * SheetWriter — satu penulis untuk SEMUA ekspor tabel (laporan stasiun,
 * Laporan Manajemen, Data Browser): format `csv` menulis CSV langsung ke
 * php://output, format `excel` menulis file .xlsx SUNGGUHAN (Office Open
 * XML: zip berisi [Content_Types].xml, _rels, workbook, styles, satu
 * sheet).
 *
 * KENAPA ADA. Sampai 2026-10-04 setiap "Ekspor Excel" mengirim byte CSV
 * dengan nama file .xlsx dan content type spreadsheetml — Excel menolak
 * membukanya ("format atau ekstensi file tidak valid"). Tidak ada library
 * spreadsheet di proyek ini dan menambah dependensi dihindari, jadi file
 * xlsx minimal dibangun sendiri dengan ext-zip.
 *
 * Pemakaian di dalam callback streamDownload():
 *
 *     $sheet = SheetWriter::open($format);
 *     $sheet->row(['Header A', 'Header B']);   // baris pertama = header tebal
 *     $sheet->row([...]);
 *     $sheet->close();                          // xlsx: zip dibangun & dikirim di sini
 *
 * Isi sel: int/float dan string angka biasa ("12", "-3.5") menjadi sel
 * ANGKA; string angka berawalan nol ("007") dan angka > 15 digit tetap
 * TEKS supaya kode/nomor kartu tidak rusak; null/'' menjadi sel kosong;
 * selebihnya teks (inline string). Tanggal sengaja ditulis sebagai teks
 * apa adanya (mis. "2026-10-04").
 */
class SheetWriter
{
    public const FORMAT_CSV = 'csv';

    public const FORMAT_EXCEL = 'excel';

    public const XLSX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @var resource|null CSV: php://output. XLSX: file sementara isi <sheetData>. */
    private $handle;

    private string $format;

    private ?string $sheetDataPath = null;

    private int $rowNumber = 0;

    private int $maxColumns = 0;

    private bool $closed = false;

    /**
     * @param  string  $format  'excel' → xlsx; selain itu CSV.
     * @param  string  $target  tujuan keluaran (default php://output; tes boleh memakai path file).
     */
    public static function open(string $format, string $target = 'php://output'): self
    {
        return new self($format === self::FORMAT_EXCEL ? self::FORMAT_EXCEL : self::FORMAT_CSV, $target);
    }

    private function __construct(string $format, private string $target)
    {
        $this->format = $format;

        if ($format === self::FORMAT_CSV) {
            $this->handle = fopen($target, 'w');

            return;
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx-sheet-');
        if ($path === false) {
            throw new RuntimeException('Tidak bisa membuat file sementara untuk ekspor Excel.');
        }
        $this->sheetDataPath = $path;
        $this->handle = fopen($path, 'w');
    }

    /**
     * Tulis satu baris. Baris pertama yang ditulis dianggap header (tebal
     * di xlsx).
     *
     * @param  array<int|string, mixed>  $values
     */
    public function row(array $values): void
    {
        $values = array_values($values);

        if ($this->format === self::FORMAT_CSV) {
            // Explicit $separator/$enclosure/$escape — PHP 8.4 deprecates
            // relying on fputcsv()'s default $escape.
            fputcsv($this->handle, $values, ',', '"', '\\');

            return;
        }

        $this->rowNumber++;
        $this->maxColumns = max($this->maxColumns, count($values));
        $isHeader = $this->rowNumber === 1;

        $xml = '<row r="'.$this->rowNumber.'">';
        foreach ($values as $index => $value) {
            $xml .= $this->cell(self::columnLetter($index).$this->rowNumber, $value, $isHeader);
        }
        $xml .= '</row>';

        fwrite($this->handle, $xml);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        fclose($this->handle);

        if ($this->format === self::FORMAT_CSV) {
            return;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'xlsx-');
        if ($zipPath === false) {
            throw new RuntimeException('Tidak bisa membuat file sementara untuk ekspor Excel.');
        }

        try {
            $this->buildPackage($zipPath);

            $out = fopen($this->target, 'w');
            $in = fopen($zipPath, 'r');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        } finally {
            @unlink($zipPath);
            if ($this->sheetDataPath !== null) {
                @unlink($this->sheetDataPath);
            }
        }
    }

    private function buildPackage(string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat paket xlsx.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // Gaya 0 = normal, gaya 1 = tebal (header).
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');

        // Lembar kerja: header dibekukan (baris 1 tetap terlihat saat
        // menggulir) dan lebar kolom wajar supaya judul tidak terpotong.
        $sheetHead = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0">'
            .($this->rowNumber > 1 ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '')
            .'</sheetView></sheetViews>'
            .($this->maxColumns > 0 ? '<cols><col min="1" max="'.$this->maxColumns.'" width="18" customWidth="1"/></cols>' : '')
            .'<sheetData>';
        $sheetTail = '</sheetData></worksheet>';

        $sheetPath = tempnam(sys_get_temp_dir(), 'xlsx-ws-');
        $ws = fopen($sheetPath, 'w');
        fwrite($ws, $sheetHead);
        $data = fopen($this->sheetDataPath, 'r');
        stream_copy_to_stream($data, $ws);
        fclose($data);
        fwrite($ws, $sheetTail);
        fclose($ws);

        $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($sheetPath);
    }

    private function cell(string $ref, mixed $value, bool $bold): string
    {
        $style = $bold ? ' s="1"' : '';

        if ($value === null || $value === '') {
            return $bold ? '<c r="'.$ref.'"'.$style.'/>' : '';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '';
            if ($value === '') {
                return '';
            }
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i');
        }

        if (! $bold && self::isNumeric($value)) {
            return '<c r="'.$ref.'"><v>'.(is_float($value) ? self::floatText($value) : (string) $value).'</v></c>';
        }

        return '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'
            .self::escape((string) $value).'</t></is></c>';
    }

    private static function isNumeric(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return is_finite($value);
        }

        return is_string($value)
            && preg_match('/^-?(?:0|[1-9]\d{0,14})(?:\.\d{1,10})?$/', $value) === 1;
    }

    private static function floatText(float $value): string
    {
        $text = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    private static function escape(string $text): string
    {
        // Buang karakter kontrol yang tidak sah di XML 1.0.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function columnLetter(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
