<?php declare(strict_types=1);

namespace Swag\SearchLogger\Storefront\Decorator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductSuggestRouteDecorator extends ProductSuggestRoute
{
    public function __construct(
        private readonly ProductSuggestRoute $inner,
        private readonly Connection $connection
    ) {
    }

    public function getDecorated(): ProductSuggestRoute
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

        if (is_string($term) && $term !== '') {
            $total = $response->getListingResult()->getTotal();

            try {
                $this->connection->insert('swag_search_log', [
                    'id' => Uuid::randomBytes(),
                    'term' => $term,
                    'result_count' => $total,
                    'sales_channel_id' => Uuid::fromHexToBytes($context->getSalesChannelId()),
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
                ]);
            } catch (\Throwable $e) {
                // Logging darf die Suggest-Suche nicht kaputt machen
            }
        }

        return $response;
    }
}
