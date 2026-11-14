<?php

declare(strict_types=1);

namespace DoctrineMigrations\Tasks;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Add status on task_interations for async IA chat tracking. */
final class Version20261102220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'task_interations.status for pending/processing/done/error (MCP chat async)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `task_interations` ADD COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'done' AFTER `visibility`");
        $this->addSql("UPDATE `task_interations` SET `status` = 'done' WHERE `status` = '' OR `status` IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `task_interations` DROP COLUMN `status`');
    }
}
