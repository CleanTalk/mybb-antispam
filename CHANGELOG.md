# Changelog

## 1.7

- Separate the remote-call password from the public JavaScript value, accept SpamFireWall files only from CleanTalk, and cap remote-call delays at 10 seconds.
- Escape the SpamFireWall block page, stop printing request headers, and show the SFW test page only to an administrator.
- Stop JavaScript, SpamFireWall, cron, and remote calls when the plugin is switched off.
- Accept URL exclusions with or without the site domain, and send MyBB contact submissions as contact forms.
- Show the CleanTalk block reason in a separate message template without MyBB's permission and ban notice. Preserve AJAX errors.
- Add MyBB attribution to the access key registration link and a link to the review bonus.
- Update the bundled anti-spam core for PHP 8.1–8.4: handle optional values without deprecation warnings, preserve UTF-8 moderation messages, and handle additional API response fields.
- Use HTTP 1.1 for API requests and verify TLS certificates when downloading SpamFireWall data.
- Refresh access key help on activation while preserving existing settings.
- Update the README and screenshots for MyBB 1.8.41.
