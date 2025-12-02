CREATE DATABASE IF NOT EXISTS catalog_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS checkout_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS email_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'catalog_user'@'%' IDENTIFIED BY 'catalog_password';
CREATE USER IF NOT EXISTS 'checkout_user'@'%' IDENTIFIED BY 'checkout_password';
CREATE USER IF NOT EXISTS 'email_user'@'%' IDENTIFIED BY 'email_password';

GRANT ALL PRIVILEGES ON catalog_db.* TO 'catalog_user'@'%';
GRANT ALL PRIVILEGES ON checkout_db.* TO 'checkout_user'@'%';
GRANT ALL PRIVILEGES ON email_db.* TO 'email_user'@'%';

FLUSH PRIVILEGES;
