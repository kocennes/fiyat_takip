# Canlı kurulum: teklif.bisavunma.com

Bu rehber, BISAVUNMA Fiyat Takip uygulamasını şirket sunucusunda
`https://teklif.bisavunma.com` adresinde (alt alan adını değiştirebilirsiniz)
yayına almak içindir. Kurulum Docker ile yapılır; HTTPS sertifikası otomatik
alınır ve yenilenir.

## 1. Sunucu

- Ubuntu 24.04 LTS (veya güncel Debian), en az 2 vCPU, 4 GB RAM, 40 GB SSD.
- [Docker Engine ve Compose eklentisi](https://docs.docker.com/engine/install/ubuntu/).
- Güvenlik duvarında yalnızca 80 ve 443 numaralı portlar dışarıya açık olmalı;
  SSH (22) yalnızca şirket IP adreslerinden erişilebilir olmalı.
- **KVKK:** Teklif ve müşteri kayıtları kişisel veri içerir. Sunucuyu
  Türkiye'deki bir veri merkezinde tutmanız önerilir. Yurt dışında barındırma
  KVKK md. 9 kapsamında ek yükümlülük getirir.

## 2. DNS

bisavunma.com alan adının DNS yönetim panelinde, kullanacağınız alt alan adı
için sunucunun IP adresini gösteren bir **A kaydı** oluşturun. Örnek:

| Ad | Tür | Değer |
|----|-----|-------|
| `teklif` | A | sunucunun IP adresi |

Müşteri portalı kullanılacaksa adres internetten erişilebilir olmalıdır.
Let's Encrypt sertifikası da 80 ve 443 portlarının dışarıdan erişilebilir
olmasını gerektirir.

## 3. Kurulum

```bash
sudo mkdir -p /opt/fiyat-takip && sudo chown "$USER" /opt/fiyat-takip
git clone https://github.com/kocennes/fiyat_takip.git /opt/fiyat-takip
cd /opt/fiyat-takip

cp .env.production.example .env.production
echo "base64:$(openssl rand -base64 32)"   # çıkan değeri APP_KEY'e yazın
nano .env.production                        # "degistir" geçen her değeri doldurun

docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f app
```

İlk açılışta veritabanı tabloları, proforma tasarımı, Türkiye ayarları ve
`.env.production` dosyasındaki `IN_USER_EMAIL` / `IN_PASSWORD` ile ana
yönetici hesabı oluşturulur. Bu işlem birkaç dakika sürebilir. Ardından
`https://teklif.bisavunma.com` adresinden giriş yapın.

`APP_KEY` değerini kurulumdan sonra değiştirmeyin ve güvenli bir yerde saklayın.
Bu değer kaybolursa şifrelenmiş ayarlar okunamaz.

## 4. İlk girişten sonra yapılacaklar

Aşağıdaki ekranların hepsi **Sistem Ayarları** menüsündedir.

1. **Şirket Bilgileri > Ayrıntılar:** Tam ticaret unvanı, **Vergi Dairesi**,
   **VKN / TCKN**, web sitesi (`https://bisavunma.com`), e-posta ve telefon.
   Aynı sayfanın alt kısmında **Banka / IBAN**, **MERSİS No** ve
   **Ticaret Sicil No** alanları vardır. Bunlar proformanın banka kutusunda ve
   altbilgisinde yazdırılır (TTK md. 39).
2. **Şirket Bilgileri > Adres** ve **Logo** sekmeleri.
3. **Kullanıcı Yönetimi:** Çalışanları ekleyin ve yalnızca gerekli yetkileri
   verin. Her kullanıcı **Kullanıcı Bilgileri** ekranından **İki Adımlı
   Doğrulama**yı açmalıdır.
4. **E-posta Ayarları:** Bir test e-postası gönderin.
5. **Müşteri Portalı:** Alttaki "Gizlilik Politikası" bağlantısında gösterilen
   KVKK aydınlatma metnini hukuk biriminize veya KVKK sorumlunuza onaylatın;
   gerekirse düzenleyin.
6. **Vergi Ayarları:** KDV %20 / %10 / %1 / %0, *KDV İstisna 13/f* (millî
   savunma) ve *KDV İstisna 11/1-a* (ihracat) hazır gelir. İstisna oranı seçilen
   kalemlerde proformaya yasal not otomatik eklenir.
7. **Proforma / teklif şartları:** Varsayılan Türkçe şartları kontrol edin.

## Günlük kullanımda bilmeniz gerekenler

- Yeni teklif, kaydedildiği gün geçerlilik tarihini otomatik alır (30 gün).
- Türk müşterilerde VKN (10 hane) veya TCKN (11 hane) kontrol edilir; hatalı
  numarayla müşteri kaydedilemez. Yabancı müşterilerde bu kontrol yapılmaz.
- **Excel İndir** (teklif veya proforma ekranında, Kaydet düğmesinin yanındaki
  menüde): Dosya PDF ile aynı düzendedir. "Kâr / zarar tablosunu ekle"
  açıksa yazdırma alanının altına her kalem için satılan adet, alış, satış,
  kâr / zarar ve kâr oranı eklenir. Tablonun para birimini (TL veya USD) siz
  seçersiniz; **kur elle girilir**, internetten çekilmez. Müşteriye gönderilecek
  dosyada tabloyu kapatın.
- Ürün kartlarında ve belgelerin Ayarlar sekmesindeki döviz kurları da elle
  girilir.

## 5. Yedekleme

```bash
./scripts/backup.sh                # backups/ klasörüne veritabanı + dosya yedeği
```

Her gece otomatik yedek için `crontab -e` ile şu satırı ekleyin:

```
30 2 * * * cd /opt/fiyat-takip && ./scripts/backup.sh >> backups/backup.log 2>&1
```

Yedekler 14 gün saklanır (`KEEP_DAYS` ile değiştirilebilir). Yedekleri ayrıca
sunucu dışında bir yere (NAS, ikinci sunucu) kopyalayın.

Geri yükleme:

```bash
./scripts/restore.sh backups/db-<zaman>.sql.gz backups/storage-<zaman>.tar.gz
```

## 6. Güncelleme

```bash
cd /opt/fiyat-takip
./scripts/backup.sh
git pull
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

Açılışta yeni veritabanı değişiklikleri otomatik uygulanır.

## 7. Şirkette zaten bir ters vekil (reverse proxy) varsa

bisavunma.com sunucularında nginx, IIS veya bir yük dengeleyici HTTPS'i
zaten karşılıyorsa `docker-compose.prod.yml` içinde şu değişiklikleri yapın:

- `caddy` servisini kaldırın.
- `nginx` servisine `ports: ["127.0.0.1:8080:80"]` ekleyin.

Ardından mevcut ters vekili `http://127.0.0.1:8080` adresine yönlendirin.
Ters vekil `Host`, `X-Forwarded-For` ve `X-Forwarded-Proto: https`
başlıklarını iletmelidir.

## 8. Sorun giderme

| Belirti | Kontrol |
|---------|---------|
| Sertifika alınamıyor | DNS A kaydı doğru mu, 80/443 dışarıdan açık mı? `logs caddy` |
| 500 hatası | `logs app`; `APP_KEY` dolu mu? |
| E-posta gitmiyor | SMTP bilgileri; `logs app` içinde kuyruk hataları |
| Giriş sonrası sürekli yönlendirme | `APP_URL` https ile mi başlıyor, `TRUSTED_PROXIES=*` mi? |

Komutlarda `logs <servis>` kısaltması şunu ifade eder:
`docker compose --env-file .env.production -f docker-compose.prod.yml logs <servis>`

## 9. Yasal notlar

- Bu uygulama teklif ve **proforma** fatura üretir. Yasal fatura, GİB'e
  entegre e-Fatura / e-Arşiv Fatura yazılımınız veya özel entegratörünüz
  üzerinden düzenlenmelidir.
- Hazır gelen teklif şartları, KVKK metni ve KDV istisna notları taslaktır;
  mali müşaviriniz ve hukuk biriminizle gözden geçirin.
- Lisans ve telif bildirimleri için depo kökündeki `NOTICE` dosyasına bakın.
