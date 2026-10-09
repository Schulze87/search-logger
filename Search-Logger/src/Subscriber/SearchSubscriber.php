<?php declare(strict_types=1);

namespace Swag\SearchLogger\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
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
        // Nicht benötigt - Term wird im Result-Handler geholt
    }

    public function onSearchResult(ProductSearchResultEvent $event): void
    {
        $term = $this->getTermFromRequest();
        if (!$term) {
            return;
        }

        $salesChannelId = $this->getSalesChannelId($event);

        try {
            $this->connection->insert('swag_search_log', [
                'id' => Uuid::randomBytes(),
                'term' => $term,
                'result_count' => $event->getResult()->getTotal(),
                'sales_channel_id' => $salesChannelId ? Uuid::fromHexToBytes($salesChannelId) : null,
                'language_id' => Uuid::fromHexToBytes($event->getContext()->getLanguageId()),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
            ]);
        } catch (\Throwable $e) {
            // Logging darf die Suche nicht kaputt machen
        }
    }
}
