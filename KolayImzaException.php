<?php
/**
 * KolayImza Hata Yönetimi
 * @author A. Kerem Gök
 */

class KolayImzaException extends Exception {
    const HATA_VERITABANI_BAGLANTI = 1001;
    const HATA_VERITABANI_SORGU = 1002;
    const HATA_PDF_URL = 2001;
    const HATA_IMZA_YANIT = 2002;
    const HATA_GRUP_OLUSTURMA = 3001;
    const HATA_BELGE_EKLEME = 3002;
    const HATA_GECERSIZ_DURUM = 4001;
    const HATA_GECERSIZ_ID = 4002;
    
    private $hataDetaylari;
    private $logDosyasi = 'kolayimza_hatalar.log';
    
    public function __construct($message, $code = 0, $hataDetaylari = [], Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->hataDetaylari = $hataDetaylari;
        $this->logHata();
    }
    
    /**
     * Hata detaylarını döndürür
     * @return array
     */
    public function getHataDetaylari() {
        return $this->hataDetaylari;
    }
    
    /**
     * Hatayı loga kaydeder
     */
    private function logHata() {
        $hataMetni = sprintf(
            "[%s] Hata Kodu: %d, Mesaj: %s, Detaylar: %s\n",
            date('Y-m-d H:i:s'),
            $this->code,
            $this->message,
            json_encode($this->hataDetaylari, JSON_UNESCAPED_UNICODE)
        );
        
        error_log($hataMetni, 3, $this->logDosyasi);
    }
    
    /**
     * Hata mesajını kullanıcı dostu formatta döndürür
     * @return string
     */
    public function getKullaniciMesaji() {
        $mesajlar = [
            self::HATA_VERITABANI_BAGLANTI => 'Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyiniz.',
            self::HATA_VERITABANI_SORGU => 'Veritabanı işlemi sırasında bir hata oluştu.',
            self::HATA_PDF_URL => 'PDF dosyasına erişilemiyor. Lütfen URL\'i kontrol ediniz.',
            self::HATA_IMZA_YANIT => 'İmza işlemi sırasında bir hata oluştu. Lütfen tekrar deneyiniz.',
            self::HATA_GRUP_OLUSTURMA => 'İmza grubu oluşturulurken bir hata oluştu.',
            self::HATA_BELGE_EKLEME => 'Belge eklenirken bir hata oluştu.',
            self::HATA_GECERSIZ_DURUM => 'Geçersiz işlem durumu.',
            self::HATA_GECERSIZ_ID => 'Geçersiz kayıt numarası.',
        ];
        
        return $mesajlar[$this->code] ?? 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.';
    }
} 