<?php declare(strict_types=1);

namespace Swag\SearchLogger\Core\Content\SwagSearchLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SwagSearchLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $term;
    protected int $resultCount;

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): void
    {
        $this->term = $term;
    }

    public function getResultCount(): int
    {
        return $this->resultCount;
    }

    public function setResultCount(int $resultCount): void
    {
        $this->resultCount = $resultCount;
    }
}
