<?php declare(strict_types=1);

namespace Swag\SearchLogger\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['administration']])]
class SearchLogController extends AbstractController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route(path: '/api/_action/swag-search-log/list', name: 'api.action.swag_search_log.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) AS id, term, result_count, LOWER(HEX(sales_channel_id)) AS sales_channel_id, created_at
             FROM swag_search_log
             ORDER BY created_at DESC
             LIMIT 500'
        );

        return new JsonResponse(['data' => $rows]);
    }
}
