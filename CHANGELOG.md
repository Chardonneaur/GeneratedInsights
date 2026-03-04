# Changelog

## 0.1.0 — 2026-03-04

Initial release.

- Dashboard widget surfacing significant period-over-period changes as insight cards
- 4 core KPI insights: Visits, Users, Actions, Bounce Rate
- Top page trend insight (Actions.getPageUrls)
- Referral source trend insight (Referrers.getWebsites)
- Dual-threshold significance filter (relative % + absolute minimum) to suppress noise
- Score-based ranking — top 6 insights shown, ordered by magnitude × volume
- Segment-aware: respects the active segment from the Matomo interface
- Graceful error handling: widget shows empty state on failure, never crashes the dashboard
