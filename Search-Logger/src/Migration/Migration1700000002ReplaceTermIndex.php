<?php declare(strict_types=1);

namespace Swag\SearchLogger\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000002ReplaceTermIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000002;
    }

    public function update(Connection $connection): void
    {
        $indexes = $connection->fetchAllAssociative('SHOW INDEX FROM swag_search_log');

        $hasOldIndex = false;
        $hasNewIndex = false;

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'idx.term') {
                $hasOldIndex = true;
            }
            if (($index['Key_name'] ?? '') === 'idx_search_log_lookup') {
                $hasNewIndex = true;
            }
        }

        if ($hasOldIndex) {
            $connection->executeStatement('DROP INDEX `idx.term` ON `swag_search_log`');
        }

        if (!$hasNewIndex) {
            $connection->executeStatement(
                'CREATE INDEX `idx_search_log_lookup` ON `swag_search_log` (`term`, `sales_channel_id`, `created_at`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nichts tun - der Index bleibt
    }
}
