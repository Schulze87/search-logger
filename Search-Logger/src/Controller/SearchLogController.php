<?php declare(strict_types=1);

namespace Swag\SearchLogger\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SearchLogController extends AbstractController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route(
        path: '/api/_action/swag-search-log/list',
        name: 'api.action.swag_search_log.list',
        methods: ['GET'],
        defaults: ['_routeScope' => ['administration']]
    )]
    public function list(Request $request): JsonResponse
    {
        $term = trim((string) $request->query->get('term', ''));
        $dateFrom = substr(trim((string) $request->query->get('dateFrom', '')), 0, 10);
        $dateTo = substr(trim((string) $request->query->get('dateTo', '')), 0, 10);
        $onlyZeroResults = $request->query->get('onlyZeroResults') === 'true';

        $sortBy = (string) $request->query->get('sortBy', 'last_searched');
        $sortDirection = strtoupper((string) $request->query->get('sortDirection', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $allowedSortFields = ['term', 'search_count', 'result_count', 'last_searched'];
        if (!in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'last_searched';
        }

        $where = [];
        $params = [];

        if ($term !== '') {
            $where[] = 'term LIKE :term';
            $params['term'] = '%' . $term . '%';
        }

        if ($dateFrom !== '') {
            $where[] = 'created_at >= :dateFrom';
            $params['dateFrom'] = $dateFrom . ' 00:00:00.000';
        }

        if ($dateTo !== '') {
            $where[] = 'created_at <= :dateTo';
            $params['dateTo'] = $dateTo . ' 23:59:59.999';
        }

        $sql = '
            SELECT
                term,
                COUNT(*) AS search_count,
                MAX(created_at) AS last_searched,
                MAX(result_count) AS result_count
            FROM swag_search_log
        ';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY term ';

        if ($onlyZeroResults) {
            $sql .= ' HAVING MAX(result_count) = 0 ';
        }

        $sql .= ' ORDER BY ' . $sortBy . ' ' . $sortDirection . ' ';
        $sql .= ' LIMIT 500 ';

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return new JsonResponse(['data' => $rows]);
    }

    #[Route(
        path: '/api/_action/swag-search-log/delete',
        name: 'api.action.swag_search_log.delete',
        methods: ['DELETE'],
        defaults: ['_routeScope' => ['administration']]
    )]
    public function delete(Request $request): JsonResponse
    {
        $term = trim((string) $request->query->get('term', ''));

        if ($term === '') {
            return new JsonResponse(['success' => false, 'message' => 'Kein Suchbegriff angegeben'], 400);
        }

        $deleted = $this->connection->delete('swag_search_log', [
            'term' => $term,
        ]);

        return new JsonResponse(['success' => true, 'deleted' => $deleted]);
    }
}
