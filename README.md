# GeneratedInsights

**Automatically surfaces significant traffic changes as insight cards on your Matomo dashboard.**

> This plugin is a community contribution and is not officially supported by Matomo GmbH.
> It is provided under the GPL v3 license without any warranty of fitness for a particular purpose.

## Description

GeneratedInsights adds a dashboard widget that behaves like Google Analytics' "Insights" feature — without sending any data to external services. Each time the widget loads, it compares the current period to the previous equivalent period, detects meaningful movements in your key metrics, and displays the most significant ones as concise insight cards.

Everything is computed server-side using your existing Matomo data. No AI, no external API calls, no configuration.

### Features

- **Automatic period comparison** — current period vs. the previous equivalent period (this week vs. last week, this month vs. last month, etc.)
- **4 core KPI cards** — Visits, Users, Actions, and Bounce Rate, each with configurable significance thresholds
- **Top page trend** — highlights significant movement in your most visited page
- **Referral source trend** — highlights significant movement in your top referral domain
- **Smart scoring** — insights are ranked by magnitude × volume; only the top 6 are shown
- **Signed delta** — each card shows a `+X%` or `-X%` badge with green/red colouring
- **Graceful degradation** — if data is unavailable or an error occurs, the widget shows a clean empty state instead of crashing

### How significance is determined

| Metric | Min. relative change | Min. absolute change |
|---|---|---|
| Visits | 12% | 20 visits |
| Users | 12% | 15 users |
| Actions | 15% | 30 actions |
| Bounce rate | 12% | 3 percentage points |
| Top page / referrer | 20% | 10 visits |

A card is only shown when **both** thresholds are exceeded, avoiding noise from low-traffic periods.

## Requirements

- Matomo >= 5.0
- PHP >= 8.1

## Installation

### From the Matomo Marketplace

1. Go to **Administration → Marketplace**.
2. Search for **GeneratedInsights**.
3. Click **Install** and then **Activate**.

### Manual Installation

1. Download the latest release archive from the [GitHub repository](https://github.com/Chardonneaur/GeneratedInsights/releases).
2. Extract it into your `matomo/plugins/` directory so that the path `matomo/plugins/GeneratedInsights/plugin.json` exists.
3. Go to **Administration → Plugins** and activate **GeneratedInsights**.

## Usage

1. Open any Matomo dashboard.
2. Click **Add a widget**.
3. Under the **Visitors** category, select **Generated Insights**.
4. The widget will immediately display insight cards for the currently selected period and site.

The widget respects the date, period, site, and segment selected in the Matomo interface — no separate configuration needed.

## FAQ

**Does this send data to any external service?**
No. All computation happens server-side using your existing Matomo data. Nothing leaves your server.

**Why are there no insights for my site?**
Either no metric crossed both the relative and absolute significance thresholds, or the previous period has no data to compare against (e.g. the very first period after installation). Low-traffic sites will see fewer insights by design — this prevents false positives.

**Can I change the significance thresholds?**
Not through the UI in this version. The thresholds are defined in `InsightsEngine.php` and can be adjusted manually if needed.

**Does it work with segments?**
Yes. The widget passes the active segment to Matomo's API, so insights are computed within the segmented audience.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.

## License

GPL v3+. See [LICENSE](LICENSE) for details.
