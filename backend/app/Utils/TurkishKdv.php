<?php

/**
 * BISAVUNMA Fiyat Takip - KDV exemptions.
 */

namespace App\Utils;

/**
 * KDV exemptions (istisnalar) offered as 0% line taxes.
 *
 * A line item taxed with one of these rates prints the matching legal note on
 * the proforma PDF and Excel, so the document states why no KDV is charged.
 */
class TurkishKdv
{
    /**
     * Legal note per exemption. The 0% tax rates themselves ("KDV İstisna 13/f",
     * "KDV İstisna 11/1-a") come from TurkishDocumentDefaults::TAX_RATES. The
     * article code in the tax name identifies the exemption, so a renamed rate
     * keeps its note as long as the code stays in the name.
     */
    public const EXEMPTIONS = [
        [
            'code' => '13/f',
            'note' => "3065 sayılı KDV Kanunu'nun 13/f maddesi kapsamında (millî savunma ve iç güvenlik ihtiyaçlarına yönelik teslim ve hizmetler) KDV'den istisnadır.",
        ],
        [
            'code' => '11/1-a',
            'note' => "3065 sayılı KDV Kanunu'nun 11/1-a maddesi kapsamında ihracat istisnası uygulanmıştır.",
        ],
    ];

    /**
     * Legal notes for the exemptions used by the line items, in a stable order.
     *
     * @param  iterable<mixed> $line_items
     * @return array<int, string>
     */
    public static function exemptionNotes(iterable $line_items): array
    {
        $notes = [];

        foreach ($line_items as $item) {
            $item = (object) $item;

            foreach ([1, 2, 3] as $slot) {
                $name = (string) ($item->{"tax_name{$slot}"} ?? '');
                $rate = (float) ($item->{"tax_rate{$slot}"} ?? 0);

                if ($name === '' || abs($rate) > 0.000001) {
                    continue;
                }

                foreach (self::EXEMPTIONS as $exemption) {
                    if (mb_stripos($name, $exemption['code']) !== false) {
                        $notes[$exemption['code']] = $exemption['note'];
                    }
                }
            }
        }

        return array_values(array_filter(
            array_map(fn (array $exemption) => $notes[$exemption['code']] ?? null, self::EXEMPTIONS)
        ));
    }

    /**
     * The exemption notes as proforma markup, or an empty string.
     *
     * @param  iterable<mixed> $line_items
     */
    public static function exemptionNotesHtml(iterable $line_items): string
    {
        return implode('', array_map(
            fn (string $note) => '<p><strong>' . e(ctrans('texts.bis_tax_exemption')) . ':</strong> ' . e($note) . '</p>',
            self::exemptionNotes($line_items)
        ));
    }
}
