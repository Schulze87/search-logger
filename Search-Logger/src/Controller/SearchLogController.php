<?php declare(strict_types=1);

namespace Swag\SearchLogger\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SearchLogController extends AbstractController
{
    private const CONFIG_PATH = __DIR__ . '/../Resources/config/search_logger_mapping.php';

    /** @var array<string, string> */
    private array $languageMap = [];

    /** @var array<string, string> */
    private array $salesChannelMap = [];

    public function __construct(private readonly Connection $connection)
    {
        $this->loadMapping();
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
                MAX(result_count) AS result_count,
                LOWER(HEX(sales_channel_id)) AS sales_channel_id,
                LOWER(HEX(language_id)) AS language_id
            FROM swag_search_log
        ';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY term, sales_channel_id, language_id ';
        $sql .= ' HAVING 1=1 ';

        if ($onlyZeroResults) {
            $sql .= ' AND MAX(result_count) = 0 ';
        }

        $sql .= ' ORDER BY ' . $sortBy . ' ' . $sortDirection . ' ';
        $sql .= ' LIMIT 500 ';

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        foreach ($rows as &$row) {
            $row['sales_channel_name'] = $this->resolveName($this->salesChannelMap, $row['sales_channel_id'] ?? '');
            $row['language_name'] = $this->resolveName($this->languageMap, $row['language_id'] ?? '');
        }
        unset($row);

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

    private function loadMapping(): void
    {
        if (!is_file(self::CONFIG_PATH)) {
            return;
        }

        $config = require self::CONFIG_PATH;
        if (!is_array($config)) {
            return;
        }

        foreach ($config['sales_channels'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = $entry['id'] ?? null;
            $name = $entry['name'] ?? null;
            if (is_string($id) && $id !== '' && is_string($name) && $name !== '') {
                $this->salesChannelMap[strtolower($id)] = $name;
            }
        }

        foreach ($config['languages'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = $entry['id'] ?? null;
            $name = $entry['name'] ?? null;
            if (is_string($id) && $id !== '' && is_string($name) && $name !== '') {
                $this->languageMap[strtolower($id)] = $name;
            }
        }
    }

    /**
     * @param array<string, string> $map
     */
    private function resolveName(array $map, string $id): string
    {
        if ($id === '') {
            return 'Unbekannt';
        }

        return $map[strtolower($id)] ?? 'Unbekannt';
    }
}
