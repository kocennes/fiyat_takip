# BISAVUNMA Fiyat Takip

BISAVUNMA markalı fiyat takip, teklif/proforma, fatura ve tedarik yönetim
uygulaması. Yeni teklif ve faturalarda varsayılan KDV oranı %20'dir; kurumsal
proforma PDF tasarımı otomatik seçilir.

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

## Üçüncü taraf bileşenleri

Uygulamanın altyapısında açık kaynaklı üçüncü taraf bileşenleri kullanılır.
Bu bileşenlerin lisans, telif ve kaynak bildirimleri kendi kaynak dizinlerinde
korunur.
