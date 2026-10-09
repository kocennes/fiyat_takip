# Fiyat Takip Uygulaması - Invoice Ninja Başlangıcı

Bu çalışma alanı, Invoice Ninja'nın `v5-stable` dalını temel alır. Yeni eklenen
**Proforma** tasarımı, verilen örnekteki tek sayfalık kurumsal düzeni teklif
çıktılarına uygular.

## Gereksinimler

- PHP 8.2 veya üzeri
- Composer 2
- MySQL 8 / MariaDB 10.6 veya üzeri
- Node.js 20 veya üzeri

## Yerel kurulum

1. `invoiceninja` klasöründe `.env.example` dosyasını `.env` olarak kopyalayın.
2. `.env` içindeki `APP_URL` ile `DB_*` ayarlarını kendi ortamınıza göre girin.
3. Bağımlılıkları yükleyin: `composer install` ve `npm install`.
4. Uygulama anahtarını üretin: `php artisan key:generate`.
5. Veritabanını kurun: `php artisan migrate --seed`.
6. Varlıkları derleyin: `npm run build`.
7. Uygulamayı başlatın: `php artisan serve`.

## Proforma tasarımını kullanma

1. Yönetici olarak giriş yapın ve uygulama dilini **Türkçe** seçin.
2. Ayarlar > Fatura Tasarımı alanından **Proforma** tasarımını varsayılan teklif
   tasarımı olarak seçin.
3. Teklif numara biçimini `PRF-{YEAR}-{COUNTER}` olarak ayarlayın.
4. Şirket bilgileri, vergi numarası ve banka bilgisini şirket ayarlarından girin.
   Banka/IBAN bilgisi için şirket özel alanı `company1` kullanılır. Proforma
   şablonu, uygulamanın yerleşik FiyatTakip logosunu kullanır.
5. Müşteriyi ve ürünleri ekleyin; teklif oluşturup PDF indirerek proformayı alın.

Mevcut bir kurulumda `php artisan migrate` komutu, Proforma tasarımını otomatik
olarak ekler. Yeni kurulumda tasarım, standart tasarımlarla birlikte seed edilir.

## Otomatik KDV ve toplamlar

Bu kurulumda proforma, teklif kaydının hesaplanmış tutarlarını PDF'ye taşır; PDF
şablonu hesap yapmaz. Böylece satır miktarı, birim fiyat, satır bazlı indirim,
KDV ve genel toplam aynı hesap motorundan gelir ve ekranda görünen tutarla PDF
arasında fark oluşmaz.

Türkiye seçilerek oluşturulan yeni şirketlerde TRY, İstanbul saat dilimi ve iki
hazır oran otomatik gelir: **KDV %20** ile **KDV (indirimli) %10**. İlk tekliften
önce Ayarlar > Vergi Ayarları bölümünde satır vergi oranlarının açık olduğunu
kontrol edin. Gerekirse burada farklı KDV oranı eklenir ve ürün kartına atanır.

Kullanım akışı: ürün kartında KDV oranını seçin, ürünü proforma teklifine ekleyin,
iskonto varsa girin ve PDF oluşturun. Sistem ara toplamı, KDV kalemini ve genel
toplamı otomatik hesaplar; proforma şablonu bunları sağ alttaki toplam kutusunda
oran adıyla birlikte gösterir.

## Fiyat takip kapsamı

Invoice Ninja ürün, tedarikçi, satın alma siparişi ve teklif yönetimini sunar;
ancak bağımsız bir **fiyat geçmişi** kaydı hazır gelmez. Sonraki geliştirme
aşamasında her ürün/tedarikçi için alış fiyatı, para birimi, tarih, kaynak ve
not alanlarını içeren manuel kayıt ekranı ile fiyat değişim raporu eklenmelidir.
Tedarikçi sitelerinden otomatik fiyat toplama isteniyorsa, her kaynak için
ayrı API veya izinli entegrasyon gereksinimi belirlenmelidir.
