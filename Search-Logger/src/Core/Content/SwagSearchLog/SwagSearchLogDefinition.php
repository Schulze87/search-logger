<?php declare(strict_types=1);

namespace Swag\SearchLogger\Core\Content\SwagSearchLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SwagSearchLogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'swag_search_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SwagSearchLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SwagSearchLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
            new StringField('term', 'term'),
            new IntField('result_count', 'resultCount'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
