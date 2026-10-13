<?php

/**
 * BISAVUNMA Fiyat Takip - Turkish document and client portal defaults.
 */

namespace App\DataMapper\CompanyDefaults;

/**
 * Defaults every BISAVUNMA company starts with: quote validity, Turkish quote
 * and proforma terms, the company fields Turkish commercial documents carry
 * (IBAN, MERSİS, ticaret sicil) and the client portal set up for online
 * quote approval with the KVKK notice.
 *
 * New companies get them through CompanySettings::defaults() and
 * CountryDefaults; existing companies through a migration that only fills
 * values which are still empty.
 */
class TurkishDocumentDefaults
{
    /**
     * Tax rates of a Turkish company: KDV 20 / 10 / 1 / 0 and the KDV
     * exemptions of TurkishKdv::EXEMPTIONS (0%, with their legal note).
     */
    public const TAX_RATES = [
        ['name' => 'KDV', 'rate' => 20], // genel oran
        ['name' => 'KDV', 'rate' => 10], // indirimli oran
        ['name' => 'KDV', 'rate' => 1],  // indirimli oran
        ['name' => 'KDV', 'rate' => 0],
        ['name' => 'KDV İstisna 13/f', 'rate' => 0],   // millî savunma ve iç güvenlik
        ['name' => 'KDV İstisna 11/1-a', 'rate' => 0], // ihracat
    ];

    /** Quotes are valid for 30 days from their date. */
    public const VALID_UNTIL_DAYS = '30';

    /** Week starts on Monday. */
    public const FIRST_DAY_OF_WEEK = '1';

    /** Amounts are typed the Turkish way: 1.234,56 (comma as the decimal separator). */
    public const USE_COMMA_AS_DECIMAL_PLACE = true;

    /**
     * Company custom fields printed on the proforma: company1 is the bank
     * account ($user_iban), company2 and company3 go to the footer. Format:
     * "label|type", as the custom field settings store it.
     */
    public const COMPANY_CUSTOM_FIELDS = [
        'company1' => 'Banka / IBAN|single_line_text',
        'company2' => 'MERSİS No|single_line_text',
        'company3' => 'Ticaret Sicil No|single_line_text',
    ];

    public const QUOTE_TERMS = "1. Fiyatlara KDV dahil değildir; KDV ayrıca gösterilmiştir.\n"
        . "2. Bu teklif, belirtilen geçerlilik tarihine kadar geçerlidir.\n"
        . "3. Döviz cinsinden fiyatlarda ödeme, ödeme günündeki T.C. Merkez Bankası döviz satış kuru üzerinden Türk lirası olarak yapılabilir.\n"
        . "4. Teslim süresi, teslim yeri ve ödeme vadesi sipariş onayında ayrıca teyit edilir.\n"
        . "5. Sipariş, teklifin yazılı olarak (imzalı teklif, sipariş formu veya e-posta) onaylanmasıyla kesinleşir.";

    public const INVOICE_TERMS = "1. Fiyatlara KDV dahil değildir; KDV ayrıca gösterilmiştir.\n"
        . "2. Bu proforma fatura bilgi amaçlıdır; yasal fatura, teslimat veya hizmetin tamamlanmasıyla e-Fatura / e-Arşiv Fatura olarak ayrıca düzenlenir.\n"
        . "3. Ödemeler, banka hesap bilgilerinde belirtilen IBAN'a açıklama kısmına proforma numarası yazılarak yapılmalıdır.\n"
        . "4. Döviz cinsinden tutarlarda ödeme günündeki T.C. Merkez Bankası döviz satış kuru esas alınır.";

    /**
     * KVKK (6698 sayılı Kanun md. 10) aydınlatma metni shown in the client
     * portal footer as "Gizlilik Politikası". Have it reviewed by the
     * company's legal / KVKK officer and adjust it under
     * Ayarlar > Müşteri Portalı.
     */
    public const KVKK_NOTICE = '<h3>Kişisel Verilerin Korunması Hakkında Aydınlatma Metni</h3>'
        . '<p>Bu metin, 6698 sayılı Kişisel Verilerin Korunması Kanunu’nun (“KVKK”) 10. maddesi uyarınca, veri sorumlusu sıfatıyla BISAVUNMA tarafından müşteri portalını kullanan müşteri yetkililerini bilgilendirmek amacıyla hazırlanmıştır.</p>'
        . '<p><strong>İşlenen kişisel veriler:</strong> Ad, soyad, unvan, kurum bilgisi, e-posta adresi, telefon numarası, adres, vergi kimlik bilgileri, teklif, sipariş ve ödeme kayıtları ile portal kullanımına ilişkin işlem güvenliği verileri (IP adresi, oturum ve erişim kayıtları).</p>'
        . '<p><strong>İşleme amaçları:</strong> Tekliflerin ve proforma faturaların hazırlanması, iletilmesi ve onay süreçlerinin yürütülmesi; sözleşme ve sipariş süreçlerinin yönetilmesi; finans ve muhasebe işlerinin yürütülmesi; mevzuattan doğan yükümlülüklerin yerine getirilmesi ve bilgi güvenliğinin sağlanması.</p>'
        . '<p><strong>Hukuki sebepler:</strong> KVKK md. 5/2-c (bir sözleşmenin kurulması veya ifası), md. 5/2-ç (hukuki yükümlülüğün yerine getirilmesi) ve md. 5/2-f (veri sorumlusunun meşru menfaati).</p>'
        . '<p><strong>Aktarım:</strong> Kişisel veriler; yasal yükümlülükler kapsamında yetkili kamu kurum ve kuruluşlarına, finans ve muhasebe süreçleri için mali müşavir ve bankalara, hizmetin sunulması için gerekli barındırma ve e-posta hizmet sağlayıcılarına KVKK md. 8 ve 9’a uygun olarak aktarılabilir.</p>'
        . '<p><strong>Toplama yöntemi:</strong> Kişisel veriler e-posta, telefon, sözleşmeler ve bu portal aracılığıyla elektronik ortamda toplanır.</p>'
        . '<p><strong>Haklarınız:</strong> KVKK md. 11 uyarınca kişisel verilerinizin işlenip işlenmediğini öğrenme, işlenmişse bilgi talep etme, işlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme, aktarıldığı üçüncü kişileri bilme, eksik veya yanlış işlenmişse düzeltilmesini ve KVKK md. 7 çerçevesinde silinmesini veya yok edilmesini isteme, bu işlemlerin aktarılan üçüncü kişilere bildirilmesini isteme, otomatik sistemlerle analiz sonucu aleyhinize bir sonuca itiraz etme ve kanuna aykırı işleme nedeniyle zarara uğramanız hâlinde zararın giderilmesini talep etme haklarına sahipsiniz.</p>'
        . '<p><strong>Başvuru:</strong> Taleplerinizi, Veri Sorumlusuna Başvuru Usul ve Esasları Hakkında Tebliğ’e uygun olarak bisavunma.com adresinde yer alan iletişim kanalları üzerinden BISAVUNMA’ya iletebilirsiniz.</p>';

    /**
     * Company settings filled in when they are still empty.
     *
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return [
            'valid_until' => self::VALID_UNTIL_DAYS,
            'quote_terms' => self::QUOTE_TERMS,
            'invoice_terms' => self::INVOICE_TERMS,
            'client_portal_privacy_policy' => self::KVKK_NOTICE,
        ];
    }

    /**
     * Client portal: clients open the quotes and proformas sent to them,
     * download the PDF and approve or reject quotes after accepting the quote
     * terms. Online card payments, uploads, tasks and self-registration are
     * off.
     *
     * @return array<string, mixed>
     */
    public static function portalSettings(): array
    {
        return [
            'enable_client_portal' => true,
            'enable_client_portal_dashboard' => false,
            'enable_client_portal_tasks' => false,
            'client_portal_enable_uploads' => false,
            'client_initiated_payments' => false,
            'show_accept_quote_terms' => true,
        ];
    }

}
