# Install and Upgrade

Installing and upgrading your themes, add-ons and languages on your site just got easier with Install & Upgrade. Simplify your upgrades and installations with just a few clicks and reduce the management of updates by automatically checking for updates with our tool. Start simplifying your processes now.
​
## Administrator Features
- Install and upgrade add-ons, themes, and languages on your site
- Check for updates for all your add-ons, themes, and languages automatically
- Connect your ThemeHouse and XenForo account within the admin control panel so you are in sync with any updates that come up
- Install and upgrade add-ons directly from their providers or via their download URLs
- Install or upgrade third-party add-ons as well since we allow third-party add-on creators to write their own handlers so that you are able to add their profiles
- Easily view what ThemeHouse (using your API key) or 3rd-Party add-ons, themes, and languages you want to install or upgrade

## CLI tooling

Various tools for handling XF2 installs from the CLI

## addon:update-pending

Scans for add-ons which can be upgraded, and then sorts them by dependencies and 'require-soft' for informed soft-dependencies.

## addon:install or addon:upgrade

A single entry point which will install or upgrade an add-on.
Supports a `--bulk` flag which defers some long running rebuild tasks till the next time `addon:bulk-finish` is called. 

## addon:uninstall

Supports a `--bulk` flag which defers some long running rebuild tasks till the next time `addon:bulk-finish` is called. 

## addon:bulk-finish

Runs deferred tasks, and ensues add-ons are not stuck with the `is_processing` flag set.