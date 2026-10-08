<?php declare(strict_types=1);

namespace Swag\SearchLogger\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchSubscriber implements EventSubscriberInterface
{
    private array $searchTerms = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductSearchCriteriaEvent::class => 'onSearchCriteria',
            ProductSearchResultEvent::class => 'onSearchResult',
        ];
    }

    public function onSearchCriteria(ProductSearchCriteriaEvent $event): void
    {
        $term = $event->getCriteria()->getTerm();
        if ($term) {
            $this->searchTerms[] = $term;
        }
    }

    public function onSearchResult(ProductSearchResultEvent $event): void
    {
        $term = array_pop($this->searchTerms);
        if (!$term) {
            return;
        }

        $this->connection->insert('swag_search_log', [
            'id' => Uuid::randomBytes(),
            'term' => $term,
            'result_count' => $event->getResult()->getTotal(),
            'sales_channel_id' => Uuid::fromHexToBytes($event->getContext()->getSalesChannelId()),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);
    }
}
