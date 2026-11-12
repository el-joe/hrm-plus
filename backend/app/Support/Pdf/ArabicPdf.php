<?php

namespace App\Support\Pdf;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * mPDF set up for Arabic and English documents (extracted from the Phase 7b payslip so the
 * reports centre uses the same setup): Cairo TTF (resources/fonts/cairo, OTL shaping, kashida),
 * RTL / LTR directionality, temp dir under storage/framework/mpdf.
 *
 * Arabic short vowels (harakat) are removed because the Cairo font's mark filtering sets are not
 * supported by mPDF; when Cairo still cannot shape a glyph sequence, mPDF's bundled Arabic font
 * (xbriyaz) is used instead. Templates must declare `font-family: cairo` on body.
 */
class ArabicPdf
{
    /**
     * @param  array{title?: string, author?: string, orientation?: 'P'|'L', margins?: array{top?: int, bottom?: int, left?: int, right?: int}, footer?: string}  $options
     */
    public function render(string $html, string $dir, array $options = []): string
    {
        $html = (string) preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $html);
        $tempDir = storage_path('framework/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        try {
            return $this->write($html, $dir, 'cairo', $tempDir, $options);
        } catch (MpdfException) {
            return $this->write(str_replace('font-family: cairo', 'font-family: xbriyaz', $html), $dir, 'xbriyaz', $tempDir, $options);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function write(string $html, string $dir, string $font, string $tempDir, array $options): string
    {
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];
        $margins = $options['margins'] ?? [];
        $orientation = $options['orientation'] ?? 'P';

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'L' ? 'A4-L' : 'A4',
            'orientation' => $orientation,
            'tempDir' => $tempDir,
            'fontDir' => [...$fontDirs, resource_path('fonts/cairo')],
            'fontdata' => $fontData + ['cairo' => ['R' => 'Cairo-400.ttf', 'B' => 'Cairo-700.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]],
            'default_font' => $font,
            'directionality' => $dir,
            'margin_top' => $margins['top'] ?? 14, 'margin_bottom' => $margins['bottom'] ?? 16,
            'margin_left' => $margins['left'] ?? 14, 'margin_right' => $margins['right'] ?? 14,
        ]);
        $mpdf->SetTitle($options['title'] ?? config('app.name'));
        $mpdf->SetAuthor($options['author'] ?? config('app.name'));

        if (isset($options['footer'])) {
            $mpdf->SetHTMLFooter($options['footer']);
        }

        // Large report tables exceed PCRE's default backtrack limit inside mPDF's parser.
        $limit = (int) ini_get('pcre.backtrack_limit');

        if (strlen($html) * 2 > $limit) {
            ini_set('pcre.backtrack_limit', (string) (strlen($html) * 2));
        }

        try {
            $mpdf->WriteHTML($html);
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }

        return $mpdf->Output('', 'S');
    }
}
