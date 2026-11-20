<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Business\Pdf;

use LukaLtaApi\Exception\BusinessPdfRenderException;
use LukaLtaApi\Value\Business\PdfDocument;
use Mpdf\Mpdf;
use Throwable;

class BusinessPdfRenderer
{
    private const string TEMP_DIR = '/tmp/mpdf';
    private const string TEMPLATE_PATH = __DIR__ . '/templates/document.php';
    private const string FONT = 'dejavusans';
    private const string WATERMARK_TEXT = 'ENTWURF';
    private const string LOGO_VAR_NAME = 'logo';

    /** Seitenränder in mm (DIN 5008: links 25, rechts 20) */
    private const int MARGIN_LEFT = 25;
    private const int MARGIN_RIGHT = 20;
    private const int MARGIN_TOP = 15;

    /** Platz für die Fußzeile in mm */
    private const int MARGIN_BOTTOM = 32;
    private const int MARGIN_FOOTER = 8;

    /** Transparenz des Wasserzeichens */
    private const float WATERMARK_ALPHA = 0.12;

    /** @return string PDF-Bytes */
    public function render(PdfDocument $document): string
    {
        try {
            $mpdf = $this->createMpdf($document);
            $mpdf->SetHTMLFooter($this->renderFooter($document->getIssuer()));
            $mpdf->WriteHTML($this->renderTemplate($document));

            return $mpdf->Output('', 'S');
        } catch (Throwable $exception) {
            throw new BusinessPdfRenderException('Failed to render PDF', $exception);
        }
    }

    private function createMpdf(PdfDocument $document): Mpdf
    {
        if (!is_dir(self::TEMP_DIR)) {
            mkdir(self::TEMP_DIR, 0775, true);
        }

        $mpdf = new Mpdf([
            'tempDir' => self::TEMP_DIR,
            'format' => 'A4',
            'default_font' => self::FONT,
            'margin_left' => self::MARGIN_LEFT,
            'margin_right' => self::MARGIN_RIGHT,
            'margin_top' => self::MARGIN_TOP,
            'margin_bottom' => self::MARGIN_BOTTOM,
            'margin_footer' => self::MARGIN_FOOTER,
        ]);
        $mpdf->SetTitle(trim($document->getTitle() . ' ' . ($document->getNumber() ?? '')));
        $mpdf->showImageErrors = false;

        if ($document->isDraft()) {
            $mpdf->SetWatermarkText(self::WATERMARK_TEXT, self::WATERMARK_ALPHA);
            $mpdf->showWatermarkText = true;
        }

        $logoBytes = $document->getLogoBytes();
        if ($logoBytes !== null) {
            // Logo nur als In-Memory-Variable, nie als Datei oder URL
            $mpdf->imageVars[self::LOGO_VAR_NAME] = $logoBytes;
        }

        return $mpdf;
    }

    private function renderTemplate(PdfDocument $document): string
    {
        $escape = static fn (?string $value): string => htmlspecialchars(
            $value ?? '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $multiline = static fn (?string $value): string => nl2br($escape($value), false);
        $logoVar = self::LOGO_VAR_NAME;

        ob_start();
        try {
            (static function () use ($document, $escape, $multiline, $logoVar): void {
                require self::TEMPLATE_PATH;
            })();

            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /** @param array<string, mixed> $issuer */
    private function renderFooter(array $issuer): string
    {
        $escape = static fn (mixed $value): string => htmlspecialchars(
            is_string($value) ? $value : '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $addressLines = is_array($issuer['addressLines'] ?? null) ? $issuer['addressLines'] : [];
        $address = implode(', ', array_map($escape, $addressLines));

        $bank = $escape($issuer['bankName'] ?? null);
        $bic = $issuer['bic'] ?? null;
        $bankLines = [$bank, 'IBAN ' . $escape($issuer['iban'] ?? null)];
        if (is_string($bic) && $bic !== '') {
            $bankLines[] = 'BIC ' . $escape($bic);
        }

        $company = '<b>' . $escape($issuer['companyName'] ?? null) . '</b><br>' . $address;
        $tax = 'Steuernummer<br>' . $escape($issuer['taxNumber'] ?? null);

        return '<div style="border-top: 0.3mm solid #999999; font-size: 7pt; color: #444444; padding-top: 2mm;">'
            . '<table width="100%" style="font-size: 7pt; color: #444444;"><tr>'
            . '<td width="38%" valign="top">' . $company . '</td>'
            . '<td width="22%" valign="top">' . $tax . '</td>'
            . '<td width="40%" valign="top">' . implode('<br>', $bankLines) . '</td>'
            . '</tr></table>'
            . '<div style="text-align: right;">Seite {PAGENO} / {nbpg}</div>'
            . '</div>';
    }
}
