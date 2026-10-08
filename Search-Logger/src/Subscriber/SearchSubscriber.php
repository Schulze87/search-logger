<?php declare(strict_types=1);

namespace Swag\SearchLogger\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SearchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductSearchCriteriaEvent::class => 'onSearchCriteria',
            ProductSearchResultEvent::class => 'onSearchResult',
            ProductSuggestCriteriaEvent::class => 'onSuggestCriteria',
        ];
    }

    private function getTermFromRequest(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $term = $request->query->get('search');
        if (is_string($term) && $term !== '') {
            return $term;
        }

        return null;
    }

    private function getSalesChannelId($event): ?string
    {
        $source = $event->getContext()->getSource();
        if ($source && method_exists($source, 'getSalesChannelId')) {
            return $source->getSalesChannelId();
        }
        return null;
    }

    public function onSearchCriteria(ProductSearchCriteriaEvent $event): void
    {
        // Nur loggen, damit wir sehen, dass das Event ankommt
        file_put_contents('/tmp/search_debug.log', 'CRITERIA: ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
    }

    public function onSuggestCriteria(ProductSuggestCriteriaEvent $event): void
    {
        file_put_contents('/tmp/search_debug.log', 'SUGGEST-CRITERIA: ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

        $term = $this->getTermFromRequest();
        if (!$term) {
            file_put_contents('/tmp/search_debug.log', 'SUGGEST: KEIN TERM' . PHP_EOL, FILE_APPEND);
            return;
        }

        $salesChannelId = $this->getSalesChannelId($event);

        $this->connection->insert('swag_search_log', [
            'id' => Uuid::randomBytes(),
            'term' => $term,
            'result_count' => 0,
            'sales_channel_id' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        file_put_contents('/tmp/search_debug.log', 'SUGGEST: GESCHRIEBEN ' . $term . PHP_EOL, FILE_APPEND);
    }

    public function onSearchResult(ProductSearchResultEvent $event): void
    {
        file_put_contents('/tmp/search_debug.log', 'RESULT: ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

        $term = $this->getTermFromRequest();
        if (!$term) {
            file_put_contents('/tmp/search_debug.log', 'RESULT: KEIN TERM' . PHP_EOL, FILE_APPEND);
            return;
        }

        $salesChannelId = $this->getSalesChannelId($event);

        $this->connection->insert('swag_search_log', [
            'id' => Uuid::randomBytes(),
            'term' => $term,
            'result_count' => $event->getResult()->getTotal(),
            'sales_channel_id' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        file_put_contents('/tmp/search_debug.log', 'RESULT: GESCHRIEBEN ' . $term . PHP_EOL, FILE_APPEND);
    }
}
