## HLstatsX Community Edition

### 📌 Requirements

- **PHP:** version 8.3 or higher required, at least **8.3.36, 8.4.27 or 8.5.12** (see [PHP security update](#warning-php-security-update-october-2026)).
- **MySQL / MariaDB:** version 5.7 or higher required (recommended 8.0).
---
HLstatsX Community Edition is an open-source project licensed
under GNU General Public License v2 and is a real-time stats
and ranking for Source engine based games. HLstatsX Community
Edition uses a Perl daemon to parse the log streamed from the
game server. The data is stored in a MySQL Database and has
a PHP frontend.


#### :loudspeaker: Important changes
| Date  | Description | Additional information |
| ------------- | ------------- | ------------- |
| 07.01.2020  | [#45](https://github.com/NomisCZ/hlstatsx-community-edition/issues/45) GeoIP2 Linux script updated, GeoLite2 MaxMind database (GDPR and CCPA) | https://blog.maxmind.com/2019/12/18/significant-changes-to-accessing-and-using-geolite2-databases/ |

> Date format: DD.MM.YYYY
---

### :warning: PHP security update (October 2026)

PHP 8.5.12, 8.4.27 and 8.3.36 (released 21.10.2026) fix two critical remote code execution bugs, CVE-2026-109618 and CVE-2026-109619 ([advisory](https://www.php.net/archive/2026.php#2026-10-21-5)). They are triggered while PHP parses the request, before any HLstats code runs, so no change to HLstats can block them: **update PHP** on the web server (distribution packages, or the [downloads page](https://www.php.net/downloads.php)), then restart PHP-FPM or Apache. The admin panel shows a warning while the running PHP is older than these releases. Distributions that backport fixes keep an older version number (Ubuntu 24.04 ships 8.3.6, for example): there, check your vendor's security notice instead.

Until you can update, these settings reduce the risk without fixing it. HLstats needs no file uploads and no upload progress, so they don't affect it. Set them in `php.ini`, the PHP-FPM pool or the Apache configuration (not with `.user.ini` or `ini_set()`), then restart every PHP worker:

```ini
; PHP-FPM pool syntax; in php.ini use the plain names (memory_limit = -1, ...)
php_admin_value[memory_limit] = -1
php_admin_value[post_max_size] = 8M
php_admin_value[upload_max_filesize] = 2M
php_admin_flag[session.upload_progress.enabled] = off
; PHP 8.5 only
php_admin_flag[fatal_error_backtraces] = off
```

---

### :lock: Signed server logs (`sv_logsecret`)

The daemon identifies a game server by the source address of its UDP log packets, which can be spoofed. Source engine servers can sign their log lines instead:

1. On the game server, set `sv_logsecret <number>` (for example in `server.cfg`, before `logaddress_add`).
2. In the admin panel, open *Game Settings* → *Edit Servers* → *Server Details* and set **LogSecret** to the same number.
3. Reload or restart the daemon.

The daemon then drops every line claiming to come from that server that is not signed with the secret. Leave LogSecret empty to keep the old behaviour. GoldSrc servers can't sign their logs.

### :book: Documentation
* https://github.com/NomisCZ/hlstatsx-community-edition/wiki 🚧 Wiki - work in progress 🚧
### :speech_balloon: Help
*  https://forums.alliedmods.net/forumdisplay.php?f=156
---
