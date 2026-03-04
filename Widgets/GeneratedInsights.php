<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\GeneratedInsights\Widgets;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\GeneratedInsights\InsightsEngine;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class GeneratedInsights extends Widget
{
    private InsightsEngine $engine;

    public function __construct(InsightsEngine $engine)
    {
        $this->engine = $engine;
    }

    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('General_Visitors');
        $config->setName('GeneratedInsights_WidgetTitle');
        $config->setOrder(7);

        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (!$idSite || !Piwik::isUserHasViewAccess($idSite)) {
            $config->disable();
        }
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $date = Common::getRequestVar('date', 'today', 'string');
        $period = Common::getRequestVar('period', 'day', 'string');
        $segment = Common::getRequestVar('segment', false);

        try {
            $viewData = $this->engine->generate($idSite, $period, $date, $segment);
        } catch (\Throwable $e) {
            $viewData = [
                'insights' => [],
                'currentPrettyDate' => $date,
                'previousPrettyDate' => null,
                'hasComparison' => false,
                'errorMessage' => Piwik::translate('GeneratedInsights_ErrorLoadingInsights'),
            ];
        }

        return $this->renderTemplate('generatedInsights', $viewData);
    }
}
