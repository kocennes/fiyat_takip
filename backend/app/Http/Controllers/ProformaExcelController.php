<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\ShowInvoiceRequest;
use App\Http\Requests\Quote\ShowQuoteRequest;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Services\Pdf\ImageFetcher;
use App\Utils\Helpers;
use App\Utils\Number;
use App\Utils\HtmlEngine;
use App\Utils\TurkishKdv;
use Illuminate\Http\Request;
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
 * totals, notes, terms/bank, signatures, footer) and has no logos. Below the printed area sits
 * the internal profit table (one row per line item: quantity, purchase, sale, profit, margin),
 * which only the Excel file carries.
 *
 * Query parameters (all optional):
 * - profit=0             leave the internal profit table out (e.g. for a file sent to the client);
 * - currency=TRY|USD|…   currency of the profit table: the document currency (default), the
 *                        company currency or USD;
 * - document_rate=…      1 unit of the document currency in company currency (defaults to the
 *                        document's own exchange rate);
 * - usd_rate=…           1 USD in company currency, needed for a USD table of a non-USD document.
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
    private const HALF_WIDTH = 58;
    private const FULL_WIDTH = 112;

    private const DEFAULT_CLIENT_DETAILS = [
        '$client.number',
        '$client.id_number',
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
        return $this->downloadDocument($invoice, $request);
    }

    public function downloadQuote(ShowQuoteRequest $request, Quote $quote): StreamedResponse
    {
        return $this->downloadDocument($quote, $request);
    }

    private function downloadDocument(Invoice|Quote $document, Request $request): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Proforma');
        $sheet->setShowGridlines(false);

        foreach (['A' => 12, 'B' => 18, 'C' => 28, 'D' => 15, 'E' => 9, 'F' => 9, 'G' => 16] as $column => $width) {
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
        $this->put($sheet, 'D1:G1', 'PROFORMA FATURA', ['bold' => true, 'size' => 18, 'color' => self::BLUE, 'align' => Alignment::HORIZONTAL_RIGHT]);
        $this->putLabeled($sheet, 'D2:G2', 'Proforma No: ', $text('$number', (string) $document->number), Alignment::HORIZONTAL_RIGHT);
        $this->putLabeled($sheet, 'D3:G3', 'Tarih: ', $text('$date', (string) $document->date), Alignment::HORIZONTAL_RIGHT);
        $this->putLabeled($sheet, 'D4:G4', 'Geçerlilik: ', $text('$due_date', (string) $document->due_date), Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getStyle('A4:G4')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NAVY);

        // Parties: seller (left) and customer (right) cards. The seller card keeps the PDF's
        // labels even when a value is empty.
        $sellerLines = array_values(array_filter([
            ['Adres: ', trim($text('$company.address1').' '.$text('$company.address2'))],
            ['', trim($text('$company.city_state_postal').' '.$text('$company.country'))],
            ['Vergi Dairesi: ', $text('$company.id_number')],
            ['VKN: ', $text('$company.vat_number')],
            ['Tel: ', $text('$company.phone')],
            ['E-posta: ', $text('$company.email')],
        ]));
        // The tax office and tax number carry their labels, as in the PDF.
        $clientLabels = [
            '$client.id_number' => ctrans('texts.id_number').': ',
            '$client.vat_number' => ctrans('texts.vat_number').': ',
        ];
        $clientLines = [];
        foreach ($this->clientDetailVariables($client) as $variable) {
            if ($variable !== '$client.name' && ($value = $text($variable)) !== '') {
                $clientLines[] = [$clientLabels[$variable] ?? '', str_replace("\n", ', ', $value)];
            }
        }

        $row = 6;
        $this->put($sheet, "A{$row}:C{$row}", 'SATICI / DÜZENLEYEN FİRMA', ['bold' => true, 'size' => 9, 'color' => self::BLUE]);
        $this->put($sheet, "D{$row}:G{$row}", 'ALICI / MÜŞTERİ KURUM', ['bold' => true, 'size' => 9, 'color' => '405066']);
        $this->put($sheet, 'A'.($row + 1).':C'.($row + 1), $companyName, ['bold' => true, 'size' => 11]);
        $this->put($sheet, 'D'.($row + 1).':G'.($row + 1), $clientName, ['bold' => true, 'size' => 11]);
        $lastPartyRow = $row + 1 + max(count($sellerLines), count($clientLines), 1);
        for ($line = 0, $current = $row + 2; $current <= $lastPartyRow; ++$line, ++$current) {
            $this->putLabeled($sheet, "A{$current}:C{$current}", $sellerLines[$line][0] ?? '', $sellerLines[$line][1] ?? '');
            $this->putLabeled($sheet, "D{$current}:G{$current}", $clientLines[$line][0] ?? '', $clientLines[$line][1] ?? '');
        }
        $sheet->getStyle("A{$row}:C{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD6E2');
        $sheet->getStyle("D{$row}:G{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD6E2');
        $this->box($sheet, "A{$row}:C{$lastPartyRow}", self::SOFT_BLUE, self::LINE, self::BLUE);
        $this->box($sheet, "D{$row}:G{$lastPartyRow}", self::SOFT_BLUE, self::LINE, self::SLATE);

        // Product table, same columns as the PDF.
        $headerRow = $lastPartyRow + 2;
        $sheet->fromArray(['GÖRSEL', 'KALEM', 'AÇIKLAMA', 'BİRİM FİYAT', 'MİKTAR', 'VERGİ', 'TUTAR'], null, "A{$headerRow}");
        $headerStyle = $sheet->getStyle("A{$headerRow}:G{$headerRow}");
        $headerStyle->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D{$headerRow}:G{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        $profitLines = [];
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
            $sheet->fromArray([$salesUnit, $quantity], null, "D{$row}", true);
            $sheet->setCellValueExplicit("F{$row}", $this->taxLabel($item, $client), DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $lineSalesTotal);
            $hasImage = $this->addProductImage($sheet, $productImages[$productKey] ?? '', "A{$row}", $temporaryImages, $company?->company_key);
            $sheet->getRowDimension($row)->setRowHeight(max($hasImage ? 50 : 20, 14 * $this->lineCount($description, 34)));

            if (($row - $headerRow) % 2 === 0) {
                $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $profitLines[] = [
                'name' => $productKey !== '' ? $productKey : Str::limit(strtok($description, "\n") ?: '-', 60),
                'quantity' => $quantity,
                'purchase_unit' => (float) ($item->product_cost ?? 0),
                'sales_total' => $lineSalesTotal,
            ];
            ++$row;
        }

        $lastItemRow = max($headerRow, $row - 1);
        if ($lastItemRow > $headerRow) {
            $items = $sheet->getStyle('A'.($headerRow + 1).":G{$lastItemRow}");
            $items->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $items->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
            $items->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
            $sheet->getStyle('D'.($headerRow + 1).":G{$lastItemRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('D'.($headerRow + 1).":D{$lastItemRow}")->getNumberFormat()->setFormatCode($money);
            $sheet->getStyle('G'.($headerRow + 1).":G{$lastItemRow}")->getNumberFormat()->setFormatCode($money);
        }

        // Totals on the right, public notes on the left (as in the PDF).
        $calc = $document->calc();
        $totals = [['Ara Toplam', (float) $calc->getSubTotal()]];
        if ((float) $calc->getTotalDiscount() > 0) {
            $totals[] = ['İndirim', -1 * (float) $calc->getTotalDiscount()];
        }
        // Line-item KDV (per rate) first, then any document-level taxes, as the PDF lists them.
        foreach ([...collect($calc->getTaxMap() ?? [])->all(), ...collect($calc->getTotalTaxMap() ?? [])->all()] as $tax) {
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
            $this->put($sheet, "D{$current}:F{$current}", $label, ['bold' => true, 'align' => Alignment::HORIZONTAL_RIGHT]);
            $sheet->setCellValue("G{$current}", $value);
            $sheet->getRowDimension($current)->setRowHeight(18);
        }
        $sheet->getStyle("G{$totalsRow}:G{$lastTotalsRow}")->getNumberFormat()->setFormatCode($money);
        $sheet->getStyle("G{$totalsRow}:G{$lastTotalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $grandTotalRow = $totalsRow + count($totals) - ($document instanceof Invoice ? 2 : 1);
        $grandTotal = $sheet->getStyle("D{$grandTotalRow}:G{$grandTotalRow}");
        $grandTotal->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::NAVY);
        $grandTotal->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9F5FF');
        $grandTotal->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NAVY);
        $grandTotal->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NAVY);

        $this->put($sheet, "A{$totalsRow}:C{$lastTotalsRow}", $publicNotes, ['wrap' => true, 'valign' => Alignment::VERTICAL_TOP]);
        $notesOverflow = $this->lineCount($publicNotes, self::HALF_WIDTH) - count($totals);
        if ($notesOverflow > 0) {
            $sheet->getRowDimension($lastTotalsRow)->setRowHeight(18 + 13 * $notesOverflow);
        }

        // Amount in words ("Yalnız …").
        $row = $lastTotalsRow + 2;
        $this->putLabeled($sheet, "A{$row}:G{$row}", ctrans('texts.bis_amount_in_words').': ', $text('$amount_in_words'));
        $this->box($sheet, "A{$row}:G{$row}", self::SOFT_BLUE, self::LINE);

        // Delivery / validity notice, then the legal notes of any KDV exemption.
        $row += 2;
        $this->putLabeled($sheet, "A{$row}:G{$row}", 'Ürün Teslimat Bilgileri: ', $publicNotes);
        $this->fitHeight($sheet, $row, 'Ürün Teslimat Bilgileri: '.$publicNotes, self::FULL_WIDTH);
        $this->putLabeled($sheet, 'A'.($row + 1).':G'.($row + 1), 'Açıklama / Not: ', 'Bu proforma, belirtilen geçerlilik süresi boyunca geçerlidir.');
        $lastNoticeRow = $row + 1;
        foreach (TurkishKdv::exemptionNotes($document->line_items ?? []) as $note) {
            ++$lastNoticeRow;
            $this->putLabeled($sheet, "A{$lastNoticeRow}:G{$lastNoticeRow}", ctrans('texts.bis_tax_exemption').': ', $note);
            $this->fitHeight($sheet, $lastNoticeRow, ctrans('texts.bis_tax_exemption').': '.$note, self::FULL_WIDTH);
        }
        $this->box($sheet, "A{$row}:G{$lastNoticeRow}", self::SOFT_ORANGE, 'F4DDB8', 'F17A23');

        // Terms (left) and bank details (right).
        $row = $lastNoticeRow + 2;
        $terms = $text('$entity.terms', $this->plain((string) $document->terms));
        $this->put($sheet, "A{$row}:C{$row}", 'TİCARİ VE ÖDEME KOŞULLARI', ['bold' => true, 'size' => 9, 'color' => '315A95']);
        $this->put($sheet, "D{$row}:G{$row}", 'BANKA HESAP BİLGİLERİ', ['bold' => true, 'size' => 9, 'color' => '26734B']);
        $this->put($sheet, 'A'.($row + 1).':C'.($row + 2), $terms, ['wrap' => true, 'valign' => Alignment::VERTICAL_TOP]);
        $this->putLabeled($sheet, 'D'.($row + 1).':G'.($row + 1), 'Banka / IBAN: ', $text('$user_iban'));
        $this->putLabeled($sheet, 'D'.($row + 2).':G'.($row + 2), 'Hesap Sahibi: ', $companyName);
        $termsOverflow = $this->lineCount($terms, self::HALF_WIDTH) - 2;
        if ($termsOverflow > 0) {
            $sheet->getRowDimension($row + 2)->setRowHeight(15 + 13 * $termsOverflow);
        }
        $this->box($sheet, "A{$row}:C".($row + 2), self::SOFT_PURPLE, 'E0DAF4');
        $this->box($sheet, "D{$row}:G".($row + 2), self::SOFT_GREEN, 'BCEBD0');

        // Signature boxes.
        $row += 4;
        $this->put($sheet, "A{$row}:C{$row}", 'Düzenleyen Firma Kaşe / İmza', ['bold' => true, 'size' => 9, 'color' => '59677D', 'align' => Alignment::HORIZONTAL_CENTER]);
        $this->put($sheet, "D{$row}:G{$row}", 'Müşteri Onayı / Kaşe İmza', ['bold' => true, 'size' => 9, 'color' => '59677D', 'align' => Alignment::HORIZONTAL_CENTER]);
        $this->put($sheet, 'A'.($row + 1).':C'.($row + 1), $companyName, ['bold' => true, 'color' => self::NAVY, 'align' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_BOTTOM]);
        $this->put($sheet, 'D'.($row + 1).':G'.($row + 1), $clientName, ['bold' => true, 'color' => self::NAVY, 'align' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_BOTTOM]);
        $sheet->getRowDimension($row + 1)->setRowHeight(36);
        foreach (["A{$row}:C".($row + 1), "D{$row}:G".($row + 1)] as $range) {
            $sheet->getStyle($range)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setRGB('CCD6E3');
        }

        // Footer.
        $row += 3;
        $helpers = new Helpers();
        $footer = implode(' | ', array_filter([
            $companyName,
            'Vergi Dairesi: '.$text('$company.id_number'),
            'VKN: '.$text('$company.vat_number'),
            ($label = $helpers->makeCustomField($company?->custom_fields, 'company2')) ? $label.': '.$text('$company2') : null,
            ($label = $helpers->makeCustomField($company?->custom_fields, 'company3')) ? $label.': '.$text('$company3') : null,
            $text('$company.website'),
            $text('$company.phone'),
        ]));
        $this->put($sheet, "A{$row}:G{$row}", $footer, ['size' => 8, 'color' => '74839A', 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => true]);
        $sheet->getRowDimension($row)->setRowHeight(12 * $this->lineCount($footer, (int) (self::FULL_WIDTH * 1.25)) + 4);
        $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        $lastPrintedRow = $row;

        // Excel-only internal profit table, below the printed area.
        if ($request->boolean('profit', true)) {
            $this->profitTable($sheet, $row + 3, $profitLines, $document, $request);
        }

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setPrintArea("A1:G{$lastPrintedRow}");
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

    /**
     * Profit per line item (Ürün, Satılan adet, Birim alış, Birim satış, Toplam alış, Toplam satış,
     * Kâr / Zarar, Kâr oranı) with a total row, in the currency chosen for the download.
     * All amounts are KDV hariç; the margin is profit / sale, as on the product card.
     *
     * @param array<int, array{name: string, quantity: float, purchase_unit: float, sales_total: float}> $lines
     */
    private function profitTable(Worksheet $sheet, int $row, array $lines, Invoice|Quote $document, Request $request): void
    {
        [$currency, $convert, $notes] = $this->profitCurrency($document, $request);
        $money = $this->moneyFormatFor($currency);

        foreach (['H' => 16, 'I' => 16, 'J' => 16, 'K' => 12] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $this->put($sheet, "A{$row}:K{$row}", 'KÂR / ZARAR TABLOSU — ŞİRKET İÇİ ('.$this->currencyLabel($currency).', KDV HARİÇ)', ['bold' => true, 'size' => 11, 'color' => self::NAVY]);
        $sheet->getStyle("A{$row}:K{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NAVY);
        $sheet->getRowDimension($row)->setRowHeight(22);

        foreach ($notes as $note) {
            ++$row;
            $this->put($sheet, "A{$row}:K{$row}", $note, ['size' => 9, 'color' => self::SLATE]);
        }

        $header = $row + 2;
        $this->put($sheet, "A{$header}:C{$header}", 'ÜRÜN', ['bold' => true]);
        $sheet->setCellValue("D{$header}", 'SATILAN ADET');
        $this->put($sheet, "E{$header}:F{$header}", 'BİRİM ALIŞ', ['bold' => true]);
        $sheet->fromArray(['BİRİM SATIŞ', 'TOPLAM ALIŞ', 'TOPLAM SATIŞ', 'KÂR / ZARAR', 'KÂR ORANI'], null, "G{$header}");
        $headerStyle = $sheet->getStyle("A{$header}:K{$header}");
        $headerStyle->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("D{$header}:K{$header}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($header)->setRowHeight(24);

        $row = $header;
        $totals = ['quantity' => 0.0, 'purchase' => 0.0, 'sales' => 0.0];

        foreach ($lines as $index => $line) {
            ++$row;
            $quantity = $line['quantity'];
            $purchaseUnit = $convert($line['purchase_unit']);
            $purchase = round($quantity * $purchaseUnit, 2);
            $sales = round($convert($line['sales_total']), 2);
            $salesUnit = $quantity != 0.0 ? $sales / $quantity : 0.0;

            $this->put($sheet, "A{$row}:C{$row}", $line['name'], ['wrap' => true]);
            $sheet->setCellValue("D{$row}", $quantity);
            $sheet->mergeCells("E{$row}:F{$row}");
            $sheet->setCellValue("E{$row}", $purchaseUnit);
            $sheet->fromArray([$salesUnit, $purchase, $sales, $sales - $purchase, $this->margin($sales, $purchase)], null, "G{$row}", true);
            $this->colourProfit($sheet, $row, $sales - $purchase);

            if ($index % 2 === 1) {
                $sheet->getStyle("A{$row}:K{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $totals['quantity'] += $quantity;
            $totals['purchase'] += $purchase;
            $totals['sales'] += $sales;
        }

        // A document-level discount lowers the sale of the whole document, not of one line.
        $discount = round($convert($this->documentDiscount($document)), 2);

        if ($discount > 0) {
            ++$row;
            $this->put($sheet, "A{$row}:C{$row}", 'Belge indirimi', ['color' => self::SLATE]);
            $sheet->fromArray([-1 * $discount, -1 * $discount], null, "I{$row}", true);
            $totals['sales'] -= $discount;
        }

        ++$row;
        $profit = $totals['sales'] - $totals['purchase'];
        $this->put($sheet, "A{$row}:C{$row}", 'TOPLAM', ['bold' => true, 'color' => self::NAVY]);
        $sheet->setCellValue("D{$row}", $totals['quantity']);
        $sheet->mergeCells("E{$row}:F{$row}");
        $sheet->fromArray([$totals['purchase'], $totals['sales'], $profit, $this->margin($totals['sales'], $totals['purchase'])], null, "H{$row}", true);
        $totalStyle = $sheet->getStyle("A{$row}:K{$row}");
        $totalStyle->getFont()->setBold(true);
        $totalStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9F5FF');
        $totalStyle->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NAVY);
        $this->colourProfit($sheet, $row, $profit);

        $body = 'A'.($header + 1).":K{$row}";
        $sheet->getStyle($body)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($body)->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        $sheet->getStyle("A{$header}:K{$row}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B8CBE0');
        $sheet->getStyle('D'.($header + 1).":K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('E'.($header + 1).":J{$row}")->getNumberFormat()->setFormatCode($money);
        $sheet->getStyle('K'.($header + 1).":K{$row}")->getNumberFormat()->setFormatCode('0.0%');
    }

    /**
     * Currency of the profit table, the converter from document-currency amounts into it and the
     * notes printed above the table (rates used, or why the document currency is used instead).
     *
     * @return array{0: Currency|null, 1: callable(float): float, 2: array<int, string>}
     */
    private function profitCurrency(Invoice|Quote $document, Request $request): array
    {
        $currencies = app('currencies');
        $documentCurrency = $document->client?->currency();
        $companyCurrency = $currencies->firstWhere('id', (string) ($document->company?->settings->currency_id ?? '')) ?? $currencies->firstWhere('code', 'TRY');
        $usd = $currencies->firstWhere('code', 'USD');

        $documentCode = $documentCurrency->code ?? ($companyCurrency->code ?? 'TRY');
        $companyCode = $companyCurrency->code ?? 'TRY';
        $target = strtoupper((string) $request->query('currency', $documentCode));

        $notes = ['Yalnızca şirket içi kullanım içindir; müşteriye gönderilecek dosyada bu tablo bulunmamalıdır. Kâr oranı satış tutarı üzerinden hesaplanır.'];

        if (! in_array($target, [$documentCode, $companyCode, 'USD'], true) || $target === $documentCode) {
            return [$documentCurrency, fn (float $amount): float => $amount, $notes];
        }

        // 1 unit of the document currency in the company currency.
        $documentRate = (float) $request->query('document_rate', 0);
        if ($documentRate <= 0 && $documentCode !== $companyCode && (float) $document->exchange_rate > 0 && abs((float) $document->exchange_rate - 1) > 0.000001) {
            $documentRate = 1 / (float) $document->exchange_rate;
        }
        if ($documentCode === $companyCode) {
            $documentRate = 1.0;
        }

        // 1 USD in the company currency.
        $usdRate = (float) $request->query('usd_rate', 0);
        if ($usdRate <= 0 && $documentCode === 'USD') {
            $usdRate = $documentRate;
        }

        $needsUsd = $target === 'USD' && $companyCode !== 'USD';

        if ($documentRate <= 0 || ($needsUsd && $usdRate <= 0)) {
            $notes[] = 'Seçilen para birimi için kur girilmediğinden tablo belge para birimiyle ('.$this->currencyLabel($documentCurrency).') gösterilmiştir.';

            return [$documentCurrency, fn (float $amount): float => $amount, $notes];
        }

        $companyLabel = $this->currencyLabel($companyCurrency);

        if ($documentCode !== $companyCode && $documentCode !== 'USD') {
            $notes[] = 'Belge kuru: 1 '.$documentCode.' = '.$this->rate($documentRate).' '.$companyLabel;
        }

        if ($needsUsd || $documentCode === 'USD') {
            $notes[] = 'Kur: 1 USD = '.$this->rate($needsUsd ? $usdRate : $documentRate).' '.$companyLabel;
        }

        $convert = $needsUsd
            ? fn (float $amount): float => $amount * $documentRate / $usdRate
            : fn (float $amount): float => $amount * $documentRate;

        return [$needsUsd ? $usd : $companyCurrency, $convert, $notes];
    }

    /** Document-level discount in the document currency (line discounts are already in the lines). */
    private function documentDiscount(Invoice|Quote $document): float
    {
        $discount = (float) ($document->discount ?? 0);

        if ($discount <= 0) {
            return 0.0;
        }

        if ($document->is_amount_discount) {
            return $discount;
        }

        $subtotal = array_sum(array_map(
            fn ($item) => (string) ($item->type_id ?? '1') === '2' ? 0 : (float) ($item->line_total ?? 0),
            (array) ($document->line_items ?? [])
        ));

        return $subtotal * $discount / 100;
    }

    /** The line's tax rates as the PDF prints them, e.g. "%20" ("%20 / %1" with two taxes). */
    private function taxLabel(object $item, $client): string
    {
        $labels = [];

        foreach ([1, 2, 3] as $slot) {
            $name = trim((string) ($item->{"tax_name{$slot}"} ?? ''));
            $rate = (float) ($item->{"tax_rate{$slot}"} ?? 0);

            if ($name !== '' || $rate != 0.0) {
                $labels[] = Number::percentLabel(rtrim(rtrim(number_format($rate, 3, ',', '.'), '0'), ','), $client);
            }
        }

        return implode(' / ', $labels);
    }

    private function margin(float $sales, float $purchase): float
    {
        return $sales != 0.0 ? ($sales - $purchase) / $sales : 0.0;
    }

    private function colourProfit(Worksheet $sheet, int $row, float $profit): void
    {
        $sheet->getStyle("J{$row}:K{$row}")->getFont()->setBold(true)->getColor()->setRGB($profit < 0 ? 'C0392B' : '26734B');
    }

    private function currencyLabel(?Currency $currency): string
    {
        $code = $currency->code ?? 'TRY';

        return $code === 'TRY' ? 'TL' : $code;
    }

    private function rate(float $rate): string
    {
        return number_format($rate, 4, ',', '.');
    }

    /** Excel number format with the document (client) currency, e.g. 1.234,50 ₺ or $1.234,50. */
    private function moneyFormat($client): string
    {
        return $this->moneyFormatFor($client?->currency());
    }

    /** Excel number format for a currency, e.g. 1.234,50 ₺ or $1.234,50. */
    private function moneyFormatFor(?Currency $currency): string
    {
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
            $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setWrapText(true);
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
