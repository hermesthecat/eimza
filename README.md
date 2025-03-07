# PDF İmzalama Uygulaması

Bu uygulama, Kolay İmza API'sini kullanarak PDF dosyalarını elektronik olarak imzalamanıza olanak sağlar.

## Özellikler

- PDF dosyası yükleme ve imzalama
- İki farklı imza formatı desteği (PadesBes ve PadesT)
- Özelleştirilebilir imza görünümü
- Modern ve kullanıcı dostu arayüz
- Dosya boyutu ve türü validasyonu
- Güvenli dosya işleme
- Hata yönetimi ve kullanıcı bildirimleri

## Gereksinimler

- PHP 7.4 veya üzeri
- Apache/Nginx web sunucusu
- Kolay İmza uygulaması (kurulu ve yapılandırılmış olmalı)
- write yetkisi olan uploads/ ve temp/ klasörleri

## Kurulum

1. Dosyaları web sunucunuza kopyalayın
2. uploads/ ve temp/ klasörlerinin yazma izinlerini ayarlayın:
   ```bash
   chmod 777 uploads temp
   ```
3. config.php dosyasında Kolay İmza yolunu kontrol edin:
   ```php
   define('KOLAY_IMZA_PATH', 'C:\\Program Files (x86)\\KolayImza\\AltiKare.KolayImza.exe');
   ```

## Kullanım

1. Ana sayfada "PDF Dosyası Seçin" butonuna tıklayın
2. İmzalamak istediğiniz PDF dosyasını seçin
3. İmza formatını seçin:
   - PadesBes: Basit elektronik imza
   - PadesT: Zaman damgalı imza
4. İmza görünüm ayarlarını istediğiniz gibi düzenleyin
5. "İmzala" butonuna tıklayın
6. İmzalama işlemi tamamlandığında, imzalı PDF otomatik olarak indirilecektir

## Güvenlik

- Dosya uzantısı ve MIME type kontrolleri
- Maksimum dosya boyutu sınırı (varsayılan: 10MB)
- Güvenli dosya adı temizleme
- XSS ve CSRF korumaları
- Hassas dizinlere erişim kontrolü
- Geçici dosyaların otomatik temizlenmesi

## Hata Ayıklama

Hata ayıklama modunu etkinleştirmek için config.php dosyasında:

```php
define('DEBUG_MODE', true);
```

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Detaylı bilgi için LICENSE dosyasını inceleyebilirsiniz.

## Katkıda Bulunma

1. Fork edin
2. Feature branch oluşturun (`git checkout -b feature/yeniOzellik`)
3. Değişikliklerinizi commit edin (`git commit -am 'Yeni özellik: XYZ'`)
4. Branch'inizi push edin (`git push origin feature/yeniOzellik`)
5. Pull Request oluşturun

## Güvenlik Bildirimleri

Güvenlik açıkları için lütfen issue oluşturmak yerine doğrudan iletişime geçin.