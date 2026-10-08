<?php declare(strict_types=1);

namespace Swag\SearchLogger\Core\Content\SwagSearchLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SwagSearchLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SwagSearchLogEntity::class;
    }
}
