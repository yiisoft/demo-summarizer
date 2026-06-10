<?php

declare(strict_types=1);

namespace App\Document\Migration;

use Yiisoft\Db\Constant\ReferentialAction;
use Yiisoft\Db\Constraint\ForeignKey;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

/**
 * Creates and reverts the document workflow database tables.
 */
final class M250604000000CreateDocumentTables implements RevertibleMigrationInterface
{
    private const DOCUMENTS = 'documents';
    private const PROCESSING_EVENTS = 'processing_events';
    private const DOCUMENTS_STATUS_INDEX = 'idx_documents_status';
    private const PROCESSING_EVENTS_DOCUMENT_INDEX = 'idx_processing_events_document';
    private const PROCESSING_EVENTS_DOCUMENT_FOREIGN_KEY = 'fk_processing_events_document';

    /**
     * Applies document workflow schema changes.
     *
     * @param MigrationBuilder $b Migration builder.
     */
    public function up(MigrationBuilder $b): void
    {
        $column = $b->columnBuilder();

        $b->createTable(self::DOCUMENTS, [
            'id' => $column::primaryKey(),
            'original_name' => $column::text()->notNull(),
            'storage_key' => $column::text()->notNull(),
            'mime_type' => $column::string(255)->notNull(),
            'extension' => $column::string(32)->notNull(),
            'byte_size' => $column::integer()->notNull(),
            'status' => $column::string(32)->notNull(),
            'progress' => $column::integer()->notNull()->defaultValue(0),
            'lease_until' => $column::datetime(),
            'markdown_key' => $column::text(),
            'summary' => $column::text(),
            'error' => $column::text(),
            'retry_count' => $column::integer()->notNull()->defaultValue(0),
            'created_at' => $column::datetime()->notNull(),
            'updated_at' => $column::datetime()->notNull(),
        ]);

        $b->createTable(self::PROCESSING_EVENTS, [
            'id' => $column::primaryKey(),
            'document_id' => $column::integer()
                ->notNull()
                ->reference(new ForeignKey(
                    self::PROCESSING_EVENTS_DOCUMENT_FOREIGN_KEY,
                    ['document_id'],
                    foreignTableName: self::DOCUMENTS,
                    foreignColumnNames: ['id'],
                    onDelete: ReferentialAction::CASCADE,
                )),
            'event_type' => $column::string(64)->notNull(),
            'message' => $column::text()->notNull(),
            'progress' => $column::integer()->notNull()->defaultValue(0),
            'created_at' => $column::datetime()->notNull(),
        ]);

        $b->createIndex(self::DOCUMENTS, self::DOCUMENTS_STATUS_INDEX, 'status');
        $b->createIndex(self::PROCESSING_EVENTS, self::PROCESSING_EVENTS_DOCUMENT_INDEX, ['document_id', 'id']);
    }

    /**
     * Reverts document workflow schema changes.
     *
     * @param MigrationBuilder $b Migration builder.
     */
    public function down(MigrationBuilder $b): void
    {
        $b->dropIndex(self::PROCESSING_EVENTS, self::PROCESSING_EVENTS_DOCUMENT_INDEX);
        $b->dropIndex(self::DOCUMENTS, self::DOCUMENTS_STATUS_INDEX);
        $b->dropTable(self::PROCESSING_EVENTS);
        $b->dropTable(self::DOCUMENTS);
    }
}
