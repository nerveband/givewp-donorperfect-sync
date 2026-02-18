# Changelog

All notable changes to the GiveWP2DP plugin will be documented here.

## [1.1.0] - 2026-02-18

### Changed
- Renamed plugin from "GiveWP to DonorPerfect Sync" to **GiveWP2DP**
- Renamed GitHub repo from `givewp-donorperfect-sync` to `givewp2dp`
- Updated all references and auto-updater to use new repo name

## [1.0.2] - 2026-02-18

### Added
- Info tooltips throughout Settings, Backfill, Match Report, and Sync Log tabs explaining every field in plain language
- Hover tooltips on Dashboard stat cards (no icons, just hover to learn more)
- Help banners on key pages reassuring users that their data is safe
- Better description for "Sync a Single Donation" with guidance on where to find donation IDs
- Changelog tab in the plugin admin

### Changed
- Renamed "Pledges Created" to "Recurring Groups" on Dashboard with explanation that DP pledges are just a grouping mechanism, not a fundraising goal
- Clarified recurring donation documentation to explain that GiveWP subscriptions are open-ended with no fulfillment tracking
- Status banner now says "Your donor data is safe" when sync is off

## [1.0.1] - 2026-02-18

### Added
- Documentation tab with full usage guide, settings reference, and recommended first steps
- "Test API Connection" button in Settings (lightweight SELECT query)
- "Validate Codes" button in Settings (checks GL code, campaign, ONETIME, RECURRING in DPCODES)

### Fixed
- Auto-updater moved outside `is_admin()` gate so WordPress update cron can detect new releases

### Changed
- Renamed admin menu from "DP Sync" to "Give2DP"

## [1.0.0] - 2026-02-18

### Added
- Initial release
- Real-time sync of GiveWP donations to DonorPerfect (disabled by default)
- Donor matching by email address
- Recurring donation support via DP pledges with renewal linking
- Historical backfill with preview (dry run) mode
- Donor match report (read-only)
- Sync log with status filtering and pagination
- Configurable GL code, campaign, solicit code, and gateway mapping
- GitHub-based auto-updater from releases
