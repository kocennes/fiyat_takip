# İşlem hareketleri için kaynak notu

Kaynak: [Transactions user guide](https://invoiceninja.github.io/docs/user-guide/transactions)

Bu not, ileride fiyat takip ekranına banka/ödeme hareketleri eklenirse başvuru
amacıyla tutulur. Kaynak dokümanın tam metni depoya kopyalanmadı.

Özet akış:

- Hareketler banka eşitlemesi, CSV içe aktarma veya manuel kayıtla oluşur.
- Her hareket eşleşmemiş, eşleşmiş ya da dönüştürülmüş durumda tutulur.
- Para girişi fatura/ödeme ile; para çıkışı tedarikçi/gider kaydıyla ilişkilendirilir.
- CSV alanları tarih, açıklama ve tutar olarak eşlenir.
- Tekrarlanan hareketler kurallarla; diğerleri yönetici onayıyla işlenir.
