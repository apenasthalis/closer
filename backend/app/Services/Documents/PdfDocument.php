<?php

namespace App\Services\Documents;

class PdfDocument
{
    private const PAGE_WIDTH = 595.0;

    private const PAGE_HEIGHT = 842.0;

    private const MARGIN_LEFT = 50.0;

    private const MARGIN_TOP = 800.0;

    private const CONTENT_WIDTH = 495.0;

    private array $pages = [];

    private string $current = '';

    private float $y;

    public function __construct()
    {
        $this->y = self::MARGIN_TOP;
    }

    public function title(string $text, string $subtitle = ''): self
    {
        $this->ensureSpace(40);
        $this->lineAt($text, 20, true, self::MARGIN_LEFT);
        $this->y -= 28;

        if ($subtitle !== '') {
            $this->lineAt($subtitle, 10, false, self::MARGIN_LEFT, '0.45');
            $this->y -= 22;
        }

        $this->divider();

        return $this;
    }

    public function heading(string $text): self
    {
        $this->ensureSpace(30);
        $this->y -= 6;
        $this->lineAt($text, 13, true, self::MARGIN_LEFT);
        $this->y -= 20;

        return $this;
    }

    public function text(string $text, int $size = 10, bool $bold = false, float $indent = 0): self
    {
        $maxChars = max(20, (int) floor((self::CONTENT_WIDTH - $indent) / ($size * 0.52)));
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $blocks = explode("\n", $text);

        foreach ($blocks as $block) {
            $lines = $block === '' ? [''] : explode("\n", wordwrap($block, $maxChars, "\n", true));

            foreach ($lines as $line) {
                $this->ensureSpace($size * 1.5);
                $this->lineAt($line, $size, $bold, self::MARGIN_LEFT + $indent);
                $this->y -= $size * 1.45;
            }
        }

        return $this;
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, float>  $widths
     */
    public function row(array $values, array $widths, int $size = 9, bool $bold = false): self
    {
        $this->ensureSpace($size * 2.2);
        $x = self::MARGIN_LEFT;

        foreach ($values as $index => $value) {
            $width = $widths[$index] ?? 100;
            $maxChars = max(4, (int) floor($width / ($size * 0.52)));
            $line = mb_substr((string) $value, 0, $maxChars);
            $this->lineAt($line, $size, $bold, $x);
            $x += $width;
        }

        $this->y -= $size * 1.7;

        return $this;
    }

    public function divider(): self
    {
        $this->ensureSpace(12);
        $this->current .= sprintf(
            "0.75 w 0.82 0.82 0.82 RG %.2f %.2f m %.2f %.2f l S 0 0 0 RG\n",
            self::MARGIN_LEFT,
            $this->y,
            self::PAGE_WIDTH - 50,
            $this->y
        );
        $this->y -= 12;

        return $this;
    }

    public function spacer(float $height = 8): self
    {
        $this->y -= $height;

        return $this;
    }

    public function newPage(): self
    {
        if (trim($this->current) !== '') {
            $this->pages[] = $this->current;
        }

        $this->current = '';
        $this->y = self::MARGIN_TOP;

        return $this;
    }

    public function output(): string
    {
        if (trim($this->current) !== '') {
            $this->pages[] = $this->current;
            $this->current = '';
        }

        if ($this->pages === []) {
            $this->pages[] = '';
        }

        $totalPages = count($this->pages);

        foreach ($this->pages as $index => &$content) {
            $footer = sprintf('Closer OS — página %d de %d', $index + 1, $totalPages);
            $content .= $this->rawTextLine($footer, 8, false, self::MARGIN_LEFT, 30, '0.5');
        }

        unset($content);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $pageObjectNumbers = [];

        for ($i = 0; $i < $totalPages; $i++) {
            $pageObjectNumbers[] = 5 + ($i * 2);
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn ($number) => $number.' 0 R', $pageObjectNumbers)).'] /Count '.$totalPages.' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $index => $content) {
            $pageNumber = 5 + ($index * 2);
            $contentNumber = $pageNumber + 1;
            $stream = $content;
            $objects[$pageNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentNumber.' 0 R >>';
            $objects[$contentNumber] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));

        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObject; $i++) {
            if (isset($offsets[$i])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
            } else {
                $pdf .= "0000000000 65535 f \n";
            }
        }

        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->y - $needed < 50) {
            $this->newPage();
        }
    }

    private function lineAt(string $text, float $size, bool $bold, float $x, string $gray = '0'): void
    {
        $this->current .= $this->rawTextLine($text, $size, $bold, $x, $this->y, $gray);
    }

    private function rawTextLine(string $text, float $size, bool $bold, float $x, float $y, string $gray = '0'): string
    {
        $font = $bold ? 'F2' : 'F1';

        return sprintf(
            "BT %.2f %.2f %.2f rg /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $gray,
            $gray,
            $gray,
            $font,
            $size,
            $x,
            $y,
            $this->escape($text)
        );
    }

    private function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        if ($converted === false) {
            $converted = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        }

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $converted);
    }
}
