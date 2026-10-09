<?php declare(strict_types=1);

namespace Swag\SearchLogger\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000004AddLanguageId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000004;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM swag_search_log');
        $hasLanguageId = false;

        foreach ($columns as $column) {
            if (($column['Field'] ?? '') === 'language_id') {
                $hasLanguageId = true;
                break;
            }
        }

        if (!$hasLanguageId) {
            $connection->executeStatement(
                'ALTER TABLE `swag_search_log` ADD COLUMN `language_id` BINARY(16) NULL AFTER `sales_channel_id`'
            );
        }

        // Index ersetzen: language_id vor created_at einfügen
        $indexes = $connection->fetchAllAssociative('SHOW INDEX FROM swag_search_log');
        $hasNewIndex = false;

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'idx_search_log_lookup') {
                // Prüfen, ob language_id bereits im Index enthalten ist
                if (($index['Column_name'] ?? '') === 'language_id') {
                    $hasNewIndex = true;
                }
            }
        }

        if (!$hasNewIndex) {
            foreach ($indexes as $index) {
                if (($index['Key_name'] ?? '') === 'idx_search_log_lookup' && $index['Seq_in_index'] == 1) {
                    $connection->executeStatement('DROP INDEX `idx_search_log_lookup` ON `swag_search_log`');
                    break;
                }
            }

            $connection->executeStatement(
                'CREATE INDEX `idx_search_log_lookup` ON `swag_search_log` (`term`, `sales_channel_id`, `language_id`, `created_at`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $indexes = $connection->fetchAllAssociative('SHOW INDEX FROM swag_search_log');
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'idx_search_log_lookup' && $index['Seq_in_index'] == 1) {
                $connection->executeStatement('DROP INDEX `idx_search_log_lookup` ON `swag_search_log`');
                break;
            }
        }

        $connection->executeStatement(
            'CREATE INDEX `idx_search_log_lookup` ON `swag_search_log` (`term`, `sales_channel_id`, `created_at`)'
        );

        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM swag_search_log');
        foreach ($columns as $column) {
            if (($column['Field'] ?? '') === 'language_id') {
                $connection->executeStatement('ALTER TABLE `swag_search_log` DROP COLUMN `language_id`');
                return;
            }
        }
    }
}
