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
        $sortBy = (string) $request->query->get('sortBy', 'created_at');
        $sortDirection = strtoupper((string) $request->query->get('sortDirection', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $allowedSortFields = ['term', 'result_count', 'created_at', 'search_count'];
        if (!in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'created_at';
        }

        $sql = '
            SELECT
                term,
                COUNT(*) AS search_count,
                MAX(created_at) AS last_searched,
                MAX(result_count) AS result_count
            FROM swag_search_log
        ';

        $params = [];

        if ($term !== '') {
            $sql .= ' WHERE term LIKE :term ';
            $params['term'] = '%' . $term . '%';
        }

        $sql .= ' GROUP BY term ';
        $sql .= ' ORDER BY ' . $sortBy . ' ' . $sortDirection . ' ';
        $sql .= ' LIMIT 500 ';

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return new JsonResponse(['data' => $rows]);
    }
}
