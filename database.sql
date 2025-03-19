-- İmza tablosu
CREATE TABLE signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    signature_format VARCHAR(50) NOT NULL,
    certificate_name VARCHAR(255),
    certificate_issuer VARCHAR(255),
    certificate_serial_number VARCHAR(100),
    signature_date DATETIME,
    signature_location VARCHAR(255),
    signature_reason VARCHAR(255),
    pdf_signature_pos_x INT,
    pdf_signature_pos_y INT,
    pdf_signature_width INT,
    pdf_signature_height INT,
    signature_data TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT,
    signed_pdf_path VARCHAR(255),
    signature_chain JSON,
    required_signatures INT DEFAULT 1,
    completed_signatures INT DEFAULT 0,
    next_signer VARCHAR(255),
    signature_deadline DATETIME,
    signature_groups JSON,
    current_group INT DEFAULT 1,
    group_signatures JSON,
    group_status JSON,
    signature_type ENUM('chain', 'parallel', 'mixed') DEFAULT 'chain',
    -- Temel indeksler
    INDEX idx_filename (filename),
    INDEX idx_certificate_serial (certificate_serial_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_next_signer (next_signer),
    INDEX idx_current_group (current_group),
    
    -- Birleşik indeksler (çoklu sorgular için optimizasyon)
    INDEX idx_status_created_at (status, created_at),
    INDEX idx_status_current_group (status, current_group),
    INDEX idx_certificate_status (certificate_serial_number, status),
    INDEX idx_filename_status (filename, status),
    
    -- JSON indeksleri
    INDEX idx_signature_groups ((CAST(signature_groups AS CHAR(100)))),
    INDEX idx_group_status ((CAST(group_status AS CHAR(100)))),
    
    -- İmza süreçleri için özel indeksler
    INDEX idx_signature_deadline (signature_deadline),
    INDEX idx_signature_type_status (signature_type, status),
    INDEX idx_completed_signatures (completed_signatures),
    INDEX idx_required_signatures (required_signatures)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- JSON fonksiyonları için özel indeksler
ALTER TABLE signatures ADD INDEX idx_json_extract_current_group_signers ((JSON_EXTRACT(signature_groups, CONCAT('$[', current_group - 1, '].signers'))));

-- Users tablosu
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    tckn VARCHAR(11) NOT NULL UNIQUE,
    email VARCHAR(255),
    role ENUM('admin', 'user') DEFAULT 'user',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;