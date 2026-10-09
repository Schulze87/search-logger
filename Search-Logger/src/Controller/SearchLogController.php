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
        $salesChannelFilter = strtolower(trim((string) $request->query->get('salesChannelId', '')));
        $languageFilter = strtolower(trim((string) $request->query->get('languageId', '')));

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

        if ($salesChannelFilter !== '') {
            $where[] = 'sales_channel_id = :salesChannelId';
            $params['salesChannelId'] = hex2bin($salesChannelFilter);
        }

        if ($languageFilter !== '') {
            $where[] = 'language_id = :languageId';
            $params['languageId'] = hex2bin($languageFilter);
        }

        $sql = '
            SELECT
                term,
                COUNT(*) AS search_count,
                MAX(created_at) AS last_searched,
                MAX(result_count) AS result_count,
                MAX(sales_channel_id) AS sales_channel_id_raw,
                MAX(language_id) AS language_id_raw
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
            $salesChannelHex = isset($row['sales_channel_id_raw']) && $row['sales_channel_id_raw'] !== null
                ? bin2hex($row['sales_channel_id_raw'])
                : '';

            $languageHex = isset($row['language_id_raw']) && $row['language_id_raw'] !== null
                ? bin2hex($row['language_id_raw'])
                : '';

            $row['sales_channel_id'] = $salesChannelHex;
            $row['language_id'] = $languageHex;

            $row['sales_channel_name'] = $this->resolveName($this->salesChannelMap, $salesChannelHex);
            $row['language_name'] = $this->resolveName($this->languageMap, $languageHex);

            unset($row['sales_channel_id_raw'], $row['language_id_raw']);
        }
        unset($row);

        return new JsonResponse(['data' => $rows]);
    }

    #[Route(
        path: '/api/_action/swag-search-log/filter-options',
        name: 'api.action.swag_search_log.filter_options',
        methods: ['GET'],
        defaults: ['_routeScope' => ['administration']]
    )]
    public function filterOptions(): JsonResponse
    {
        return new JsonResponse([
            'salesChannels' => array_map(
                static fn (string $id, string $name): array => ['id' => $id, 'name' => $name],
                array_keys($this->salesChannelMap),
                array_values($this->salesChannelMap)
            ),
            'languages' => array_map(
                static fn (string $id, string $name): array => ['id' => $id, 'name' => $name],
                array_keys($this->languageMap),
                array_values($this->languageMap)
            ),
        ]);
    }

    #[Route(
        path: '/api/_action/swag-search-log/delete',
        name: 'api.action.swag_search_log.delete',
        methods: ['DELETE'],
        defaults: ['_routeScope' => ['administration']]
    )]
    public function delete(Request $request): JsonResponse
    {
        $terms = $this->extractTerms($request);

        if (empty($terms)) {
            return new JsonResponse(['success' => false, 'message' => 'Keine Suchbegriffe angegeben'], 400);
        }

        $deleted = $this->connection->executeStatement(
            'DELETE FROM swag_search_log WHERE term IN (:terms)',
            ['terms' => $terms],
            ['terms' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        return new JsonResponse([
            'success' => true,
            'deleted' => $deleted,
            'terms' => count($terms),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function extractTerms(Request $request): array
    {
        // Einzelner Begriff (alte Route)
        $term = trim((string) $request->query->get('term', ''));
        if ($term !== '') {
            return [$term];
        }

        // Mehrere Begriffe (neue Route)
        $terms = $request->query->all('terms');
        if (!is_array($terms)) {
            return [];
        }

        $result = [];
        foreach ($terms as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $result[] = trim($entry);
            }
        }

        return array_values(array_unique($result));
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
