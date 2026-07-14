<?php

declare(strict_types=1);

namespace DoctrineMigrations\Tasks;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Baseline schema for tasks module from s.controleonline.com";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('CREATE TABLE IF NOT EXISTS `task_interations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) CHARACTER SET utf8 NOT NULL,
  `visibility` enum(\'private\',\'public\') CHARACTER SET utf8 NOT NULL DEFAULT \'private\',
  `body` longtext CHARACTER SET utf8,
  `registered_by_id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `task_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT \'0\',
  `notified` tinyint(1) NOT NULL DEFAULT \'0\',
  PRIMARY KEY (`id`),
  KEY `task_interations_ibfk_1` (`registered_by_id`),
  KEY `task_interations_ibfk_2` (`file_id`),
  KEY `task_interations_ibfk_3` (`task_id`),
  CONSTRAINT `task_interations_ibfk_1` FOREIGN KEY (`registered_by_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `task_interations_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `task_interations_ibfk_3` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_type` enum(\'support\',\'relationship\') CHARACTER SET utf8 NOT NULL DEFAULT \'support\',
  `name` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `registered_by_id` int(11) NOT NULL,
  `task_for_id` int(11) DEFAULT NULL,
  `provider_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `task_status_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `reason_id` int(11) DEFAULT NULL,
  `criticality_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `alter_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `announce` longtext CHARACTER SET utf8,
  PRIMARY KEY (`id`),
  KEY `registered_by_id` (`registered_by_id`),
  KEY `task_for_id` (`task_for_id`),
  KEY `provider_id` (`provider_id`),
  KEY `client_id` (`client_id`),
  KEY `order_id` (`order_id`),
  KEY `category_id` (`category_id`),
  KEY `reason_id` (`reason_id`),
  KEY `criticality_id` (`criticality_id`),
  KEY `tasks_ibfk_5` (`task_status_id`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`registered_by_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`task_for_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`provider_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_ibfk_4` FOREIGN KEY (`client_id`) REFERENCES `people` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_ibfk_5` FOREIGN KEY (`task_status_id`) REFERENCES `status` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `tasks_ibfk_6` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_ibfk_7` FOREIGN KEY (`reason_id`) REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_ibfk_8` FOREIGN KEY (`criticality_id`) REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tasks_ibfk_9` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE IF EXISTS `tasks`');
        $this->addSql('DROP TABLE IF EXISTS `task_interations`');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }
}
