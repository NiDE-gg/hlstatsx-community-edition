## HLstatsX Community Edition

### 📌 Requirements

- **PHP:** version 8.3 or higher required.
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
