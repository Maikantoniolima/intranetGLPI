-- Tabela de configuração
CREATE TABLE IF NOT EXISTS `glpi_intranet_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `banner` VARCHAR(255) DEFAULT NULL,
  `btn1_label` VARCHAR(100) DEFAULT 'Política de Segurança',
  `btn1_link`  VARCHAR(255) DEFAULT NULL,
  `btn2_label` VARCHAR(100) DEFAULT 'Portal RH',
  `btn2_link`  VARCHAR(255) DEFAULT NULL,
  `btn3_label` VARCHAR(100) DEFAULT 'Acesso Rápido',
  `btn3_link`  VARCHAR(255) DEFAULT NULL,
  `weather_city`    VARCHAR(100) DEFAULT 'Manaus,BR',
  `weather_api_key` VARCHAR(255) DEFAULT 'b6c027921aedca7db672aa1b19c3fd36',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de categorias de notícias
CREATE TABLE IF NOT EXISTS `glpi_intranet_news_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `date_creation` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de notícias (com categoria e banner)
CREATE TABLE IF NOT EXISTS `glpi_intranet_news` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `users_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `banner` VARCHAR(255) DEFAULT NULL,
  `date_publication` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_expiration`  TIMESTAMP NULL DEFAULT NULL,
  `date_creation`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pub` (`date_publication`),
  KEY `idx_users` (`users_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de fontes RSS (com id_categories)
CREATE TABLE IF NOT EXISTS `glpi_intranet_rss_sources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `site_tag`   VARCHAR(50)  NOT NULL,
  `feed_url`   VARCHAR(255) NOT NULL,
  `status`     ENUM('ativo','inativo') DEFAULT 'ativo',
  `last_fetch` TIMESTAMP NULL DEFAULT NULL,
  `id_categories` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_cat` (`id_categories`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
