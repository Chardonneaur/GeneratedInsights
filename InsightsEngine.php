<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\GeneratedInsights;

use Piwik\API\Request;
use Piwik\DataTable;
use Piwik\Period\Factory;
use Piwik\Period\Month;
use Piwik\Period\Range;

class InsightsEngine
{
    public function generate(int $idSite, string $period, string $date, $segment = false): array
    {
        [$previousDate] = Range::getLastDate($date, $period);

        if (empty($previousDate)) {
            return [
                'insights' => [],
                'currentPrettyDate' => $this->getPrettyDate($period, $date),
                'previousPrettyDate' => null,
                'hasComparison' => false,
                'errorMessage' => null,
            ];
        }

        $params = [
            'idSite' => $idSite,
            'period' => $period,
            'segment' => $segment,
            'format_metrics' => 0,
        ];

        $current = $this->safeApiArray('VisitsSummary.get', array_merge($params, ['date' => $date]));
        $previous = $this->safeApiArray('VisitsSummary.get', array_merge($params, ['date' => $previousDate]));

        $insights = [];

        $definitions = [
            'nb_visits' => ['label' => 'Visits', 'minPercent' => 0.12, 'minAbsolute' => 20, 'invert' => false],
            'nb_users' => ['label' => 'Users', 'minPercent' => 0.12, 'minAbsolute' => 15, 'invert' => false],
            'nb_actions' => ['label' => 'Actions', 'minPercent' => 0.15, 'minAbsolute' => 30, 'invert' => false],
            'bounce_rate' => ['label' => 'Bounce rate', 'minPercent' => 0.12, 'minAbsolute' => 3, 'invert' => true],
        ];

        foreach ($definitions as $metric => $definition) {
            $insight = $this->buildMetricInsight(
                $definition['label'],
                (float) ($current[$metric] ?? 0),
                (float) ($previous[$metric] ?? 0),
                $definition['minPercent'],
                $definition['minAbsolute'],
                (bool) $definition['invert']
            );

            if ($insight) {
                $insights[] = $insight;
            }
        }

        $pageInsight = $this->buildTopEntityInsight(
            'Actions.getPageUrls',
            'Top page trend',
            $params,
            $date,
            $previousDate,
            ['nb_visits', 'nb_hits', 'nb_pageviews']
        );

        if ($pageInsight) {
            $insights[] = $pageInsight;
        }

        $sourceInsight = $this->buildTopEntityInsight(
            'Referrers.getWebsites',
            'Referral source trend',
            $params,
            $date,
            $previousDate,
            ['nb_visits']
        );

        if ($sourceInsight) {
            $insights[] = $sourceInsight;
        }

        usort($insights, static function (array $a, array $b) {
            return $b['score'] <=> $a['score'];
        });

        return [
            'insights' => array_slice($insights, 0, 6),
            'currentPrettyDate' => $this->getPrettyDate($period, $date),
            'previousPrettyDate' => $this->getPrettyDate($period, $previousDate),
            'hasComparison' => true,
            'errorMessage' => null,
        ];
    }

    private function buildMetricInsight(
        string $label,
        float $current,
        float $previous,
        float $minPercent,
        float $minAbsolute,
        bool $invertDirection
    ): ?array {
        $delta = $current - $previous;
        $relative = $previous > 0 ? $delta / $previous : null;

        $relativeMagnitude = $relative === null ? ($current > 0 ? 1.0 : 0.0) : abs($relative);
        if (abs($delta) < $minAbsolute || $relativeMagnitude < $minPercent) {
            return null;
        }

        $isUp = $delta > 0;
        $direction = $isUp ? 'up' : 'down';

        $isPositive = $invertDirection ? !$isUp : $isUp;
        $tone = $isPositive ? 'positive' : 'negative';

        $relativeText = $relative === null
            ? 'new'
            : sprintf('%s%.1f%%', $delta > 0 ? '+' : '', $relative * 100);

        return [
            'title' => $label,
            'summary' => sprintf('%s changed from %s to %s', $label, $this->formatNumber($previous), $this->formatNumber($current)),
            'delta' => $relativeText,
            'direction' => $direction,
            'tone' => $tone,
            'score' => $relativeMagnitude * max(abs($current), abs($previous), 1),
        ];
    }

    private function buildTopEntityInsight(
        string $method,
        string $title,
        array $baseParams,
        string $date,
        string $previousDate,
        array $metricCandidates
    ): ?array {
        $reportParams = array_merge($baseParams, [
            'flat' => 1,
            'filter_limit' => 10,
            'expanded' => 0,
        ]);

        $currentTable = $this->safeApiDataTable($method, array_merge($reportParams, ['date' => $date]));
        $previousTable = $this->safeApiDataTable($method, array_merge($reportParams, ['date' => $previousDate]));

        if (!$currentTable || !$previousTable) {
            return null;
        }

        $metric = $this->detectMetric($currentTable, $metricCandidates);
        if (!$metric) {
            return null;
        }

        $currentTop = $this->extractTopRow($currentTable, $metric);
        $previousTop = $this->extractTopRow($previousTable, $metric);

        if (!$currentTop || !$previousTop) {
            return null;
        }

        $delta = $currentTop['value'] - $previousTop['value'];
        $relative = $previousTop['value'] > 0 ? $delta / $previousTop['value'] : null;
        $relativeMagnitude = $relative === null ? ($currentTop['value'] > 0 ? 1.0 : 0.0) : abs($relative);

        if ($relativeMagnitude < 0.2 || abs($delta) < 10) {
            return null;
        }

        $isUp = $delta > 0;

        return [
            'title' => $title,
            'summary' => sprintf(
                '"%s" moved from %s to %s',
                $currentTop['label'],
                $this->formatNumber($previousTop['value']),
                $this->formatNumber($currentTop['value'])
            ),
            'delta' => $relative === null ? 'new' : sprintf('%s%.1f%%', $delta > 0 ? '+' : '', $relative * 100),
            'direction' => $isUp ? 'up' : 'down',
            'tone' => $isUp ? 'positive' : 'negative',
            'score' => $relativeMagnitude * max($currentTop['value'], $previousTop['value'], 1),
        ];
    }

    private function safeApiArray(string $method, array $params): array
    {
        $response = Request::processRequest($method, $params, $default = []);
        return is_array($response) ? $response : [];
    }

    private function safeApiDataTable(string $method, array $params): ?DataTable
    {
        $response = Request::processRequest($method, $params, $default = []);
        return $response instanceof DataTable ? $response : null;
    }

    private function detectMetric(DataTable $table, array $metricCandidates): ?string
    {
        $firstRow = $table->getFirstRow();
        if (!$firstRow) {
            return null;
        }

        foreach ($metricCandidates as $metric) {
            if ($firstRow->getColumn($metric) !== false && $firstRow->getColumn($metric) !== null) {
                return $metric;
            }
        }

        return null;
    }

    private function extractTopRow(DataTable $table, string $metric): ?array
    {
        foreach ($table->getRows() as $row) {
            $label = (string) $row->getColumn('label');
            $value = (float) ($row->getColumn($metric) ?? 0);

            if ($label === '' || $value <= 0) {
                continue;
            }

            return [
                'label' => $label,
                'value' => $value,
            ];
        }

        return null;
    }

    private function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return number_format((float) round($value));
        }

        return number_format($value, 1);
    }

    private function getPrettyDate(string $period, string $date): string
    {
        $periodObj = Factory::build($period, $date);

        if ($periodObj instanceof Month) {
            return $periodObj->getLocalizedLongString();
        }

        return $periodObj->getPrettyString();
    }
}
