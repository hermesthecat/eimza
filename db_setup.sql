-- KolayImza veritabanı kurulumu
CREATE DATABASE IF NOT EXISTS kolayimza_db CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE kolayimza_db;
-- İmza kayıtları tablosu
CREATE TABLE IF NOT EXISTS imza_kayitlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    belge_url VARCHAR(255) NOT NULL,
    imzalayan_ad VARCHAR(100),
    sertifika_sahibi VARCHAR(100),
    sertifika_kurumu VARCHAR(100),
    imza_zamani DATETIME,
    imza_data TEXT,
    durum ENUM('bekliyor', 'imzalandi', 'hata') DEFAULT 'bekliyor',
    olusturma_zamani TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_zamani TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_turkish_ci;
-- İmza geçmişi tablosu
CREATE TABLE IF NOT EXISTS imza_gecmisi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imza_id INT NOT NULL,
    islem_tipi ENUM(
        'olusturuldu',
        'imzalandi',
        'hata',
        'iptal_edildi'
    ) NOT NULL,
    aciklama TEXT,
    ip_adresi VARCHAR(45),
    kullanici_bilgisi TEXT,
    olusturma_zamani TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imza_id) REFERENCES imza_kayitlari(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_turkish_ci;
-- Toplu imza grupları tablosu
CREATE TABLE IF NOT EXISTS imza_gruplari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grup_adi VARCHAR(100),
    aciklama TEXT,
    durum ENUM(
        'bekliyor',
        'tamamlandi',
        'kismen_tamamlandi',
        'hata'
    ) DEFAULT 'bekliyor',
    olusturma_zamani TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_zamani TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_turkish_ci;
-- İmza grubu belgeleri tablosu
CREATE TABLE IF NOT EXISTS imza_grubu_belgeleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grup_id INT NOT NULL,
    imza_id INT NOT NULL,
    sira_no INT NOT NULL DEFAULT 0,
    FOREIGN KEY (grup_id) REFERENCES imza_gruplari(id) ON DELETE CASCADE,
    FOREIGN KEY (imza_id) REFERENCES imza_kayitlari(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_turkish_ci;
CREATE TABLE IF NOT EXISTS `toplu_islem_gecmisi` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `islem_tipi` enum('tekli', 'coklu', 'grup') NOT NULL,
    `belge_sayisi` int(11) NOT NULL DEFAULT 0,
    `basarili_sayisi` int(11) NOT NULL DEFAULT 0,
    `hatali_sayisi` int(11) NOT NULL DEFAULT 0,
    `baslama_zamani` datetime NOT NULL,
    `bitis_zamani` datetime DEFAULT NULL,
    `durum` enum('bekliyor', 'devam_ediyor', 'tamamlandi', 'hata') NOT NULL DEFAULT 'bekliyor',
    `hata_mesaji` text DEFAULT NULL,
    `ip_adresi` varchar(45) DEFAULT NULL,
    `olusturma_zamani` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_islem_tipi` (`islem_tipi`),
    KEY `idx_durum` (`durum`),
    KEY `idx_olusturma_zamani` (`olusturma_zamani`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;