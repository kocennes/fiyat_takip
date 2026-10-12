<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\ShowInvoiceRequest;
use App\Http\Requests\Quote\ShowQuoteRequest;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Services\Pdf\ImageFetcher;
use App\Utils\HtmlEngine;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Produces the editable Excel counterpart of the BISAVUNMA proforma PDF.
 *
 * The sheet follows the proforma design section by section (header, parties, product table,
 * totals, notes, terms/bank, signatures, footer) and has no logos. Below it sits the internal
 * Alış / Satış / Kâr summary, which only the Excel file carries.
 */
class ProformaExcelController extends BaseController
{
    private const NAVY = '1F3D66';
    private const BLUE = '2F70B7';
    private const SLATE = '52627A';
    private const LINE = 'DBE3ED';
    private const SOFT_BLUE = 'F5F8FC';
    private const SOFT_ORANGE = 'FFF9EF';
    private const SOFT_PURPLE = 'F7F4FF';
    private const SOFT_GREEN = 'EFFCF4';

    /** Approximate characters per line of the left (A:C), right (D:F) and full (A:F) blocks. */
    private const HALF_WIDTH = 60;
    private const FULL_WIDTH = 120;

    private const DEFAULT_CLIENT_DETAILS = [
        '$client.number',
        '$client.vat_number',
        '$client.address1',
        '$client.address2',
        '$client.city_state_postal',
        '$client.country',
        '$client.phone',
        '$contact.email',
    ];

    public function download(ShowInvoiceRequest $request, Invoice $invoice): StreamedResponse
    {
        return $this->downloadDocument($invoice);
    }

    public function downloadQuote(ShowQuoteRequest $request, Quote $quote): StreamedResponse
    {
        return $this->downloadDocument($quote);
    }

    private function downloadDocument(Invoice|Quote $document): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Proforma');
        $sheet->setShowGridlines(false);

        foreach (['A' => 13, 'B' => 20, 'C' => 30, 'D' => 17, 'E' => 13, 'F' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $company = $document->company;
        $client = $document->client;
        $values = $this->htmlValues($document);
        $text = fn (string $key, string $fallback = ''): string => $this->plain($values[$key] ?? '') ?: $fallback;

        $companyName = $text('$company.name', $company?->present()->name() ?: 'BISAVUNMA');
        $clientName = $text('$client.name', $client?->present()->name() ?: '-');
        $money = $this->moneyFormat($client);
        $publicNotes = $text('$entity.public_notes', $this->plain((string) $document->public_notes));

        // Header: company name on the left, document title and meta on the right.
        $this->put($sheet, 'A1:C1', $companyName, ['bold' => true, 'size' => 16, 'color' => self::NAVY]);
        $this->put($sheet, 'A2:C2', 'FİYAT TAKİP VE TEDARİK YÖNETİMİ', ['bold' => true, 'size' => 9, 'color' => self::SLATE]);
        $this->put($sheet, 'D1:F1', 'PROFORMA FATURA', ['bold' => true, 'size' => 18, 'color' => self::BLUE, 'align' => Alignment::HORIZONTAL_RIGHT]);
        $this->putLabeled($sheet, 'D2:F2', 'Proforma No: ', $text('$number', (string) $document->number), Alignment::HORIZONTAL_RIGHT);
        $this->putLabeled($sheet, 'D3:F3', 'Tarih: ', $text('$date', (string) $document->date), Alignment::HORIZONTAL_RIGHT);
        $this->putLabeled($sheet, 'D4:F4', 'Geçerlilik: ', $text('$due_date', (string) $document->due_date), Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getStyle('A4:F4')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NAVY);

        // Parties: seller (left) and customer (right) cards. The seller card keeps the PDF's
        // labels even when a value is empty.
        $sellerLines = array_values(array_filter([
            ['Adres: ', str_replace("\n", ', ', $text('$company.address'))],
            $text('$company.city_state_postal') !== '' ? ['', $text('$company.city_state_postal')] : null,
            ['Vergi No: ', $text('$company.vat_number')],
            ['Tel: ', $text('$company.phone')],
            ['E-posta: ', $text('$company.email')],
        ]));
        $clientLines = [];
        foreach ($this->clientDetailVariables($client) as $variable) {
            if ($variable !== '$client.name' && ($value = $text($variable)) !== '') {
                $clientLines[] = ['', str_replace("\n", ', ', $value)];
            }
        }

        $row = 6;
        $this->put($sheet, "A{$row}:C{$row}", 'SATICI / DÜZENLEYEN FİRMA', ['bold' => true, 'size' => 9, 'color' => self::BLUE]);
        $this->put($sheet, "D{$row}:F{$row}", 'ALICI / MÜŞTERİ KURUM', ['bold' => true, 'size' => 9, 'color' => '405066']);
        $this->put($sheet, 'A'.($row + 1).':C'.($row + 1), $companyName, ['bold' => true, 'size' => 11]);
        $this->put($sheet, 'D'.($row + 1).':F'.($row + 1), $clientName, ['bold' => true, 'size' => 11]);
        $lastPartyRow = $row + 1 + max(count($sellerLines), count($clientLines), 1);
        for ($line = 0, $current = $row + 2; $current <= $lastPartyRow; ++$line, ++$current) {
            $this->putLabeled($sheet, "A{$current}:C{$current}", $sellerLines[$line][0] ?? '', $sellerLines[$line][1] ?? '');
            $this->putLabeled($sheet, "D{$current}:F{$current}", $clientLines[$line][0] ?? '', $clientLines[$line][1] ?? '');
        }
        $sheet->getStyle("A{$row}:C{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD6E2');
        $sheet->getStyle("D{$row}:F{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD6E2');
        $this->box($sheet, "A{$row}:C{$lastPartyRow}", self::SOFT_BLUE, self::LINE, self::BLUE);
        $this->box($sheet, "D{$row}:F{$lastPartyRow}", self::SOFT_BLUE, self::LINE, self::SLATE);

        // Product table, same columns as the PDF.
        $headerRow = $lastPartyRow + 2;
        $sheet->fromArray(['GÖRSEL', 'ÖĞE', 'AÇIKLAMA', 'BİRİM FİYATI', 'MİKTAR', 'TUTAR'], null, "A{$headerRow}");
        $headerStyle = $sheet->getStyle("A{$headerRow}:F{$headerRow}");
        $headerStyle->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D{$headerRow}:F{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        $purchaseTotal = 0.0;
        $salesTotal = 0.0;
        $row = $headerRow + 1;
        $productImages = $this->productImagesFor($document);
        $temporaryImages = [];

        foreach ($document->line_items ?? [] as $item) {
            if ((string) ($item->type_id ?? '1') === '2') {
                continue; // tasks are not printed on the proforma either
            }

            $quantity = (float) ($item->quantity ?? 0);
            $salesUnit = (float) ($item->cost ?? 0);
            $lineSalesTotal = (float) ($item->line_total ?? ($quantity * $salesUnit));
            $productKey = (string) ($item->product_key ?? '');
            $description = $this->plain((string) ($item->notes ?? ''));

            $sheet->setCellValueExplicit("B{$row}", $productKey, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$row}", $description, DataType::TYPE_STRING);
            $sheet->fromArray([$salesUnit, $quantity, $lineSalesTotal], null, "D{$row}", true);
            $hasImage = $this->addProductImage($sheet, $productImages[$productKey] ?? '', "A{$row}", $temporaryImages, $company?->company_key);
            $sheet->getRowDimension($row)->setRowHeight(max($hasImage ? 50 : 20, 14 * $this->lineCount($description, 34)));

            if (($row - $headerRow) % 2 === 0) {
                $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $purchaseTotal += round($quantity * (float) ($item->product_cost ?? 0), 2);
            $salesTotal += $lineSalesTotal;
            ++$row;
        }

        $lastItemRow = max($headerRow, $row - 1);
        if ($lastItemRow > $headerRow) {
            $items = $sheet->getStyle('A'.($headerRow + 1).":F{$lastItemRow}");
            $items->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $items->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
            $items->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
            $sheet->getStyle('D'.($headerRow + 1).":F{$lastItemRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('D'.($headerRow + 1).":D{$lastItemRow}")->getNumberFormat()->setFormatCode($money);
            $sheet->getStyle('F'.($headerRow + 1).":F{$lastItemRow}")->getNumberFormat()->setFormatCode($money);
        }

        // Totals on the right, public notes on the left (as in the PDF).
        $calc = $document->calc();
        $totals = [['Ara Toplam', (float) $calc->getSubTotal()]];
        if ((float) $calc->getTotalDiscount() > 0) {
            $totals[] = ['İndirim', -1 * (float) $calc->getTotalDiscount()];
        }
        foreach ($calc->getTotalTaxMap() ?? [] as $tax) {
            $totals[] = [(string) ($tax['name'] ?? 'KDV'), (float) ($tax['total'] ?? 0)];
        }
        $totals[] = ['Toplam', (float) $document->amount];
        if ($document instanceof Invoice) {
            $totals[] = ['Ödenen', (float) $document->paid_to_date];
        }

        $totalsRow = $lastItemRow + 2;
        $lastTotalsRow = $totalsRow + count($totals) - 1;
        foreach ($totals as $offset => [$label, $value]) {
            $current = $totalsRow + $offset;
            $this->put($sheet, "D{$current}:E{$current}", $label, ['bold' => true, 'align' => Alignment::HORIZONTAL_RIGHT]);
            $sheet->setCellValue("F{$current}", $value);
            $sheet->getRowDimension($current)->setRowHeight(18);
        }
        $sheet->getStyle("F{$totalsRow}:F{$lastTotalsRow}")->getNumberFormat()->setFormatCode($money);
        $sheet->getStyle("F{$totalsRow}:F{$lastTotalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $grandTotalRow = $totalsRow + count($totals) - ($document instanceof Invoice ? 2 : 1);
        $grandTotal = $sheet->getStyle("D{$grandTotalRow}:F{$grandTotalRow}");
        $grandTotal->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::NAVY);
        $grandTotal->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9F5FF');
        $grandTotal->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NAVY);
        $grandTotal->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NAVY);

        $this->put($sheet, "A{$totalsRow}:C{$lastTotalsRow}", $publicNotes, ['wrap' => true, 'valign' => Alignment::VERTICAL_TOP]);
        $notesOverflow = $this->lineCount($publicNotes, self::HALF_WIDTH) - count($totals);
        if ($notesOverflow > 0) {
            $sheet->getRowDimension($lastTotalsRow)->setRowHeight(18 + 13 * $notesOverflow);
        }

        // Delivery / validity notice.
        $row = $lastTotalsRow + 2;
        $this->putLabeled($sheet, "A{$row}:F{$row}", 'Ürün Teslimat Bilgileri: ', $publicNotes);
        $this->fitHeight($sheet, $row, 'Ürün Teslimat Bilgileri: '.$publicNotes, self::FULL_WIDTH);
        $this->putLabeled($sheet, 'A'.($row + 1).':F'.($row + 1), 'Açıklama / Not: ', 'Bu proforma, belirtilen geçerlilik süresi boyunca geçerlidir.');
        $this->box($sheet, "A{$row}:F".($row + 1), self::SOFT_ORANGE, 'F4DDB8', 'F17A23');

        // Terms (left) and bank details (right).
        $row += 3;
        $terms = $text('$entity.terms', $this->plain((string) $document->terms));
        $this->put($sheet, "A{$row}:C{$row}", 'TİCARİ VE ÖDEME KOŞULLARI', ['bold' => true, 'size' => 9, 'color' => '315A95']);
        $this->put($sheet, "D{$row}:F{$row}", 'BANKA HESAP BİLGİLERİ', ['bold' => true, 'size' => 9, 'color' => '26734B']);
        $this->put($sheet, 'A'.($row + 1).':C'.($row + 2), $terms, ['wrap' => true, 'valign' => Alignment::VERTICAL_TOP]);
        $this->putLabeled($sheet, 'D'.($row + 1).':F'.($row + 1), 'Banka / IBAN: ', $text('$user_iban'));
        $this->putLabeled($sheet, 'D'.($row + 2).':F'.($row + 2), 'Hesap Sahibi: ', $companyName);
        $termsOverflow = $this->lineCount($terms, self::HALF_WIDTH) - 2;
        if ($termsOverflow > 0) {
            $sheet->getRowDimension($row + 2)->setRowHeight(15 + 13 * $termsOverflow);
        }
        $this->box($sheet, "A{$row}:C".($row + 2), self::SOFT_PURPLE, 'E0DAF4');
        $this->box($sheet, "D{$row}:F".($row + 2), self::SOFT_GREEN, 'BCEBD0');

        // Signature boxes.
        $row += 4;
        $this->put($sheet, "A{$row}:C{$row}", 'Düzenleyen Firma Kaşe / İmza', ['bold' => true, 'size' => 9, 'color' => '59677D', 'align' => Alignment::HORIZONTAL_CENTER]);
        $this->put($sheet, "D{$row}:F{$row}", 'Müşteri Onayı / Kaşe İmza', ['bold' => true, 'size' => 9, 'color' => '59677D', 'align' => Alignment::HORIZONTAL_CENTER]);
        $this->put($sheet, 'A'.($row + 1).':C'.($row + 1), $companyName, ['bold' => true, 'color' => self::NAVY, 'align' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_BOTTOM]);
        $this->put($sheet, 'D'.($row + 1).':F'.($row + 1), $clientName, ['bold' => true, 'color' => self::NAVY, 'align' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_BOTTOM]);
        $sheet->getRowDimension($row + 1)->setRowHeight(36);
        foreach (["A{$row}:C".($row + 1), "D{$row}:F".($row + 1)] as $range) {
            $sheet->getStyle($range)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setRGB('CCD6E3');
        }

        // Footer.
        $row += 3;
        $footer = implode(' | ', array_filter([$companyName, 'VKN: '.$text('$company.vat_number'), $text('$company.phone')]));
        $this->put($sheet, "A{$row}:F{$row}", $footer, ['size' => 8, 'color' => '74839A', 'align' => Alignment::HORIZONTAL_CENTER]);
        $sheet->getStyle("A{$row}:F{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        $lastPrintedRow = $row;

        // Excel-only internal summary: Alış | Satış | Kâr, side by side.
        $row += 2;
        $this->put($sheet, "D{$row}:F{$row}", 'ALIŞ / SATIŞ / KÂR-ZARAR (KDV HARİÇ)', ['bold' => true, 'size' => 9, 'color' => self::SLATE]);
        $sheet->fromArray(['Alış', 'Satış', 'Kâr / Zarar'], null, 'D'.($row + 1));
        $sheet->fromArray([$purchaseTotal, $salesTotal, round($salesTotal - $purchaseTotal, 2)], null, 'D'.($row + 2), true);
        $summaryHeader = $sheet->getStyle('D'.($row + 1).':F'.($row + 1));
        $summaryHeader->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $summaryHeader->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
        $summaryValues = $sheet->getStyle('D'.($row + 2).':F'.($row + 2));
        $summaryValues->getNumberFormat()->setFormatCode($money);
        $summaryValues->getFont()->setBold(true);
        $sheet->getStyle('F'.($row + 2))->getFont()->getColor()->setRGB($salesTotal - $purchaseTotal < 0 ? 'C0392B' : '26734B');
        $summary = $sheet->getStyle('D'.($row + 1).':F'.($row + 2));
        $summary->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $summary->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B8CBE0');
        $sheet->getRowDimension($row + 2)->setRowHeight(20);

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setPrintArea("A1:F{$lastPrintedRow}");
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.4)->setRight(0.4);

        $filename = 'Proforma-'.Str::slug((string) ($document->number ?: 'fiyat-teklifi')).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet, $temporaryImages): void {
            try {
                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                foreach ($temporaryImages as $temporaryImage) {
                    @unlink($temporaryImage);
                }

                $spreadsheet->disconnectWorksheets();
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * The same template values the PDF is rendered with, so both files show identical text.
     *
     * @return array<string, mixed>
     */
    private function htmlValues(Invoice|Quote $document): array
    {
        try {
            $invitation = $document->invitations()->first();

            return $invitation ? (new HtmlEngine($invitation))->makeValues() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, string> */
    private function clientDetailVariables($client): array
    {
        $pdfVariables = (array) ($client?->getMergedSettings()->pdf_variables ?? []);
        $variables = array_values(array_filter((array) ($pdfVariables['client_details'] ?? []), 'is_string'));

        return $variables ?: self::DEFAULT_CLIENT_DETAILS;
    }

    /** Excel number format with the document (client) currency, e.g. 1.234,50 ₺ or $1.234,50. */
    private function moneyFormat($client): string
    {
        $currency = $client?->currency();
        $symbol = str_replace('"', '', (string) ($currency->symbol ?? '₺')) ?: '₺';
        $precision = max(0, min(6, (int) ($currency->precision ?? 2)));
        $number = '#,##0'.($precision > 0 ? '.'.str_repeat('0', $precision) : '');

        return ($currency->code ?? 'TRY') === 'TRY' || ! empty($currency->swap_currency_symbol)
            ? "{$number} \"{$symbol}\";-{$number} \"{$symbol}\""
            : "\"{$symbol}\"{$number};-\"{$symbol}\"{$number}";
    }

    /** HTML template value → plain text (line breaks kept, blank placeholders dropped). */
    private function plain(mixed $value): string
    {
        $text = preg_replace('#<br\s*/?>|</p>|</div>#i', "\n", (string) $value) ?? '';
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_filter(array_map('trim', preg_split('/\R/u', str_replace("\u{00A0}", ' ', $text)) ?: []), 'strlen');

        return implode("\n", $lines);
    }

    private function lineCount(string $text, int $charactersPerLine): int
    {
        $count = 0;
        foreach (preg_split('/\R/u', $text) ?: [''] as $line) {
            $count += max(1, (int) ceil(mb_strlen($line) / $charactersPerLine));
        }

        return max(1, $count);
    }

    private function fitHeight(Worksheet $sheet, int $row, string $text, int $charactersPerLine): void
    {
        $lines = $this->lineCount($text, $charactersPerLine);
        if ($lines > 1) {
            $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(15 * $lines);
        }
    }

    /** @param array{bold?: bool, size?: int, color?: string, align?: string, valign?: string, wrap?: bool} $style */
    private function put(Worksheet $sheet, string $range, string $value, array $style = []): void
    {
        $sheet->mergeCells($range);
        $sheet->setCellValueExplicit(strtok($range, ':'), $value, DataType::TYPE_STRING);

        $cellStyle = $sheet->getStyle($range);
        $font = $cellStyle->getFont()->setBold($style['bold'] ?? false);
        if (isset($style['size'])) {
            $font->setSize($style['size']);
        }
        if (isset($style['color'])) {
            $font->getColor()->setRGB($style['color']);
        }
        $cellStyle->getAlignment()
            ->setHorizontal($style['align'] ?? Alignment::HORIZONTAL_LEFT)
            ->setVertical($style['valign'] ?? Alignment::VERTICAL_CENTER)
            ->setWrapText($style['wrap'] ?? false);
    }

    /** A merged cell with a bold label followed by a regular value. */
    private function putLabeled(Worksheet $sheet, string $range, string $label, string $value, string $align = Alignment::HORIZONTAL_LEFT): void
    {
        $sheet->mergeCells($range);
        $richText = new RichText();
        if ($label !== '') {
            $richText->createTextRun($label)->getFont()->setBold(true)->setName('Arial')->setSize(9)->getColor()->setRGB('344257');
        }
        $richText->createTextRun($value)->getFont()->setName('Arial')->setSize(9)->getColor()->setRGB('4E5D72');
        $sheet->setCellValue(strtok($range, ':'), $richText);
        $sheet->getStyle($range)->getAlignment()->setHorizontal($align)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** Card background with a thin outline and an optional thicker accent on the left edge. */
    private function box(Worksheet $sheet, string $range, string $fill, string $border, ?string $accent = null): void
    {
        $style = $sheet->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
        $style->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($border);
        $style->getAlignment()->setIndent(1);

        if ($accent) {
            [$start, $end] = explode(':', $range);
            $leftColumn = preg_replace('/\d+/', '', $start);
            $leftEdge = $start.':'.$leftColumn.preg_replace('/\D+/', '', $end);
            $sheet->getStyle($leftEdge)->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB($accent);
        }
    }

    /** @return array<string, string> */
    private function productImagesFor(Invoice|Quote $document): array
    {
        $keys = collect($document->line_items ?? [])
            ->pluck('product_key')
            ->filter()
            ->map(static fn ($key) => (string) $key)
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        return Product::query()
            ->where('company_id', $document->company_id)
            ->where('is_deleted', false)
            ->whereIn('product_key', $keys)
            ->pluck('product_image', 'product_key')
            ->filter()
            ->map(static fn ($image) => trim((string) $image))
            ->all();
    }

    /** @param array<int, string> $temporaryImages */
    private function addProductImage(Worksheet $sheet, string $imageUrl, string $coordinate, array &$temporaryImages, ?string $companyKey): bool
    {
        if ($imageUrl === '') {
            return false;
        }

        $dataUri = (new ImageFetcher($companyKey))->inline($imageUrl);
        if (! is_string($dataUri) || ! preg_match('#^data:image/(png|jpeg|jpg|gif);base64,([A-Za-z0-9+/=]+)$#i', $dataUri, $matches)) {
            return false;
        }

        $bytes = base64_decode($matches[2], true);
        if ($bytes === false) {
            return false;
        }

        $path = tempnam(sys_get_temp_dir(), 'bisavunma-product-');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            return false;
        }

        $temporaryImages[] = $path;

        (new Drawing())
            ->setName('Ürün Görseli')
            ->setDescription('Ürün Görseli')
            ->setPath($path)
            ->setHeight(42)
            ->setOffsetX(5)
            ->setOffsetY(4)
            ->setCoordinates($coordinate)
            ->setWorksheet($sheet);

        return true;
    }
}
