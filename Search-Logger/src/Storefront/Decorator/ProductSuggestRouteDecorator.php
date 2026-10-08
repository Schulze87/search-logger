<?php declare(strict_types=1);

namespace Swag\SearchLogger\Storefront\Decorator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ResolvedCriteriaProductSuggestRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductSuggestRouteDecorator extends ResolvedCriteriaProductSuggestRoute
{
    public function __construct(
        private readonly ResolvedCriteriaProductSuggestRoute $inner,
        private readonly Connection $connection
    ) {
    }

    public function getDecorated(): ResolvedCriteriaProductSuggestRoute
    {
        return $this->inner;
    }

    public function load(
        Request $request,
        SalesChannelContext $context,
        Criteria $criteria
    ): ProductSuggestRouteResponse {
        $response = $this->inner->load($request, $context, $criteria);

        $term = $request->query->get('search');

        if (!is_string($term) || $term === '') {
            return $response;
        }

        $total = $response->getListingResult()->getTotal();
        $salesChannelId = $context->getSalesChannelId();

        try {
            $existingId = $this->connection->fetchOne(
                'SELECT id FROM swag_search_log
                 WHERE term = :term
                   AND sales_channel_id = :salesChannelId
                   AND created_at >= DATE_SUB(NOW(3), INTERVAL 2 SECOND)
                 ORDER BY created_at DESC
                 LIMIT 1',
                [
                    'term' => $term,
                    'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                ]
            );

            if ($existingId !== false && $existingId !== null) {
                $this->connection->update(
                    'swag_search_log',
                    [
                        'result_count' => $total,
                        'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
                    ],
                    ['id' => $existingId]
                );

                return $response;
            }

            $this->connection->insert('swag_search_log', [
                'id' => Uuid::randomBytes(),
                'term' => $term,
                'result_count' => $total,
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
            ]);
        } catch (\Throwable $e) {
            // Logging darf die Suggest-Suche nicht kaputt machen
        }

        return $response;
    }
}
