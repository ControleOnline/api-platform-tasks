<?php

declare(strict_types=1);

namespace DoctrineMigrations\Tasks;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add task_type value `ia` and seed status context=ia (open/pending/closed).
 */
final class Version20261101180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow tasks.task_type=ia and seed status rows for context=ia';
    }

    public function up(Schema $schema): void
    {
        // Expand ENUM to include ia (MySQL). Ignore if already varchar.
        $this->addSql("ALTER TABLE tasks MODIFY COLUMN task_type VARCHAR(50) NOT NULL DEFAULT 'support'");
        // AI chats may not have a distinct client — align with entity (nullable)
        $this->addSql("ALTER TABLE tasks MODIFY COLUMN client_id INT NULL DEFAULT NULL");

        // Seed statuses for AI chat tasks (idempotent)
        $this->addSql("INSERT IGNORE INTO status (status, context, color, real_status) VALUES
            ('open', 'ia', '#4CAF50', 'open'),
            ('pending', 'ia', '#FF9800', 'pending'),
            ('closed', 'ia', '#9E9E9E', 'closed')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM status WHERE context = 'ia'");
        // Leave column as VARCHAR — safer than shrinking ENUM with live data
    }
}
