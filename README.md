# BISAVUNMA Fiyat Takip

BISAVUNMA markalı fiyat takip, teklif/proforma, fatura ve tedarik yönetim
uygulaması. Yeni teklif ve faturalarda varsayılan KDV oranı %20'dir; kurumsal
proforma PDF tasarımı otomatik seçilir.

## Türkiye için hazır gelenler

- KDV %20 / %10 / %1 / %0 ve KDV istisnaları (13/f millî savunma, 11/1-a
  ihracat). İstisnalı kalemlerde proformaya yasal not otomatik eklenir.
- PDF ve Excel'de yazıyla tutar ("Yalnız: … Türk Lirası … Kuruş"), Vergi
  Dairesi, VKN / TCKN, IBAN, MERSİS ve Ticaret Sicil No.
- Türk müşteri ve tedarikçilerde VKN (10 hane) / TCKN (11 hane) kontrolü.
- TL, 1.234,56 TL yazımı, "KDV %20" gösterimi, GG.AA.YYYY tarih, haftanın
  pazartesi başlaması.
- Teklifler 30 gün geçerli; Türkçe teklif ve proforma şartları hazır gelir.
- Excel İndir: PDF ile aynı düzen; isteğe bağlı, kalem kalem kâr / zarar
  tablosu (TL veya USD, kur elle girilir).

## Müşteri portalı nedir?

Müşterilerinize gönderdiğiniz teklif ve proformaları görüntüleyebildikleri ayrı
bir ekrandır. Müşteri, e-postadaki kişiye özel bağlantıyla şifresiz girer; PDF
indirir, teklif şartlarını kabul ederek teklifi onaylar ya da reddeder. Onay
size bildirim olarak düşer.

Ne değildir: Çalışanların kullandığı yönetim paneli değildir. Müşteri yalnızca
kendisine gönderilen belgeleri görür. Alış fiyatlarını, kâr / zarar bilgisini,
iç notları ve başka müşterileri göremez. Online kart ödemesi kapalıdır; menüde
yalnızca müşterinin kaydı olan bölümler görünür. Alttaki "Gizlilik Politikası"
bağlantısında KVKK aydınlatma metni gösterilir.

Ayarlar: Sistem Ayarları > Müşteri Portalı.

## Canlı kurulum (bisavunma.com)

Şirket sunucusunda HTTPS ile yayına alma, yedekleme ve güncelleme adımları:
[docs/canli-kurulum.md](docs/canli-kurulum.md).

## Yerelde Docker ile başlatma

1. `.env.example` dosyasını `.env` olarak kopyalayın.
2. `.env` içindeki `APP_KEY`, veritabanı şifreleri ve ilk yönetici bilgilerini
   kendi değerlerinizle doldurun.
3. Uygulamayı başlatın:

   ```powershell
   docker compose up -d --build
   ```

4. Tarayıcıdan `http://localhost:8012` adresini açın.

İlk açılışta `IN_USER_EMAIL` ve `IN_PASSWORD` ile ana yönetici hesabı
oluşturulur. Bu değerler yalnızca yerel `.env` veya dağıtım sağlayıcısının gizli
ortam değişkenlerinde tutulur; Git'e gönderilmez.

## Render ücretsiz demo

Bu depo, Render Blueprint ile ücretsiz tanıtım kurulumu için `render.yaml`
içerir. Render panelinde Blueprint oluştururken aşağıdaki gizli değişkenleri
girmeniz gerekir:

- `APP_KEY`
- `APP_URL` (Render'ın size verdiği HTTPS adresi)
- `IN_USER_EMAIL`
- `IN_PASSWORD`

Ücretsiz planın yerel diski kalıcı değildir. Bu nedenle demo verileri yeniden
başlatma, uykuya alma veya yeniden dağıtım sonrasında silinebilir. Gerçek müşteri
verisi veya üretim kullanımı için kalıcı MySQL, Redis, dosya depolama ve yedekleme
kullanılmalıdır.

## Altyapı sürümü ve marka dosyaları

`backend/` klasörü, açık kaynaklı fatura altyapısının 5.13.47 sürümünün kaynak
kodunu ve BISAVUNMA değişikliklerini içerir. `Dockerfile` içindeki taban imaj da
aynı sürüme sabitlenmiştir; backend'in PHP kodu, Blade şablonları ve dil
dosyaları bu imajın üzerine kopyalanır. Sürüm yükseltirken kaynak kod ile imaj
etiketi birlikte güncellenmeli ve yeni gelen metinler Türkçeye çevrilmelidir.

Logo, ikon ve favicon dosyaları `frontend/public/brand/` klasöründedir.
`docker/branding/public/` altındaki görseller, imajdaki eski logo dosyalarının
yerine yayımlanır.

## Roller

- **Yönetici:** tüm kullanıcıların hareketlerini, kayıtları ve ayarları görür.
- **Normal kullanıcı:** yalnızca kendisine verilen kaydetme/görüntüleme
  izinleriyle çalışır.

Normal kullanıcı eklemek için ana yönetici hesabıyla `Ayarlar > Kullanıcı
Yönetimi > Yeni Kullanıcı` yolunu açın. Kullanıcının **Yönetici** anahtarını
kapalı bırakın; ardından İzinler sekmesinde sadece gerekli bölümlerde
`Görüntüle`, `Oluştur` ve/veya `Düzenle` yetkisini verin. Fiyat takip ekibi
için önerilen başlangıç kısıtı: Müşteriler, Ürünler, Teklifler ve Faturalar
üzerinde gerekli en az yetki; Dashboard, Raporlar, Ödemeler, Ayarlar ve
Kullanıcı Yönetimi kapalı. Kullanıcının erişimini sonradan kaldırmak için aynı
ekrandan hesabını arşivleyin; geçmiş kayıtları korunur.

## Referanslar

İleride hareket/banka eşleştirme modülü ele alınırsa bakılacak kısa kaynak notu:
[İşlem hareketleri](docs/references/transactions.md).

## Lisans

Uygulama, Elastic License 2.0 ile lisanslanmış bir açık kaynak faturalama
yazılımının değiştirilmiş sürümüdür. Lisans ve telif bildirimleri için
[NOTICE](NOTICE) dosyasına bakın.
