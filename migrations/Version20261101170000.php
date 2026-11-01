<?php

declare(strict_types=1);

namespace DoctrineMigrations\Tasks;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add task_type value "ia" and seed statuses for context=ia (open/pending/closed).
 * Chat MCP conversations use Task.type=ia + TaskInteration messages.
 */
final class Version20261101170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tasks.task_type enum value ia and status rows context=ia';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `tasks` MODIFY COLUMN `task_type` ENUM('support','relationship','ia') CHARACTER SET utf8 NOT NULL DEFAULT 'support'");

        $this->addSql("INSERT INTO `status` (`status`, `real_status`, `visibility`, `notify`, `system`, `color`, `context`)
            SELECT 'open', 'open', 'public', 1, 1, '#ff9800', 'ia'
            FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `status` WHERE `context` = 'ia' AND `status` = 'open')");
        $this->addSql("INSERT INTO `status` (`status`, `real_status`, `visibility`, `notify`, `system`, `color`, `context`)
            SELECT 'pending', 'pending', 'public', 1, 1, '#2196f3', 'ia'
            FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `status` WHERE `context` = 'ia' AND `status` = 'pending')");
        $this->addSql("INSERT INTO `status` (`status`, `real_status`, `visibility`, `notify`, `system`, `color`, `context`)
            SELECT 'closed', 'closed', 'public', 0, 1, '#4caf50', 'ia'
            FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `status` WHERE `context` = 'ia' AND `status` = 'closed')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM `status` WHERE `context` = 'ia'");
        $this->addSql("ALTER TABLE `tasks` MODIFY COLUMN `task_type` ENUM('support','relationship') CHARACTER SET utf8 NOT NULL DEFAULT 'support'");
    }
}
