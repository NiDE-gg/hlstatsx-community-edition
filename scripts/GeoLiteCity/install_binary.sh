#!/bin/bash
# HLstatsX Community Edition - Real-time player and clan rankings and statistics
# Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
# http://www.hlxcommunity.com
#
# HLstatsX Community Edition is a continuation of
# ELstatsNEO - Real-time player and clan rankings and statistics
# Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
# http://ovrsized.neo-soft.org/
#
# ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
# HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
# http://www.hlstatsx.com/
# Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)
#
# HLstatsX is an enhanced version of HLstats made by Simon Garner
# HLstats - Real-time player and clan rankings and statistics for Half-Life
# http://sourceforge.net/projects/hlstats/
# Copyright (C) 2001  Simon Garner
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
#
# For support and installation notes visit http://www.hlxcommunity.com

# The GeoLite2-City database is downloaded from MaxMind, which needs a license key
# (free GeoLite2 account). Set MAXMIND_LICENSE_KEY or, for Docker builds, pass the
# "maxmind_license_key" build secret (see scripts/Dockerfile). The repository no
# longer bundles a copy. Without a key, or if the download fails, the script ends
# without a database (exit 0, so an image build goes on): the daemon then runs
# without GeoIP and says so when it starts (UseGeoIPBinary).
LICENSE_KEY="${MAXMIND_LICENSE_KEY:-}"
if [ -z "$LICENSE_KEY" ] && [ -r /run/secrets/maxmind_license_key ]; then
  LICENSE_KEY=$(tr -d '[:space:]' < /run/secrets/maxmind_license_key)
fi

# ***** NOTHING TO CONFIGURE BELOW HERE *****

FILE=GeoLite2-City
FILE_EXT=.tar.gz

# Change to directory where installer is
cd "$(dirname "$0")" || exit 1

if [ -z "$LICENSE_KEY" ]; then
  echo "[!] No MaxMind license key set: no GeoIP database installed (players get no country or location)"
  exit 0
fi

echo "[>>] Downloading the current $FILE database"
if ! curl -fsSL -o "$FILE$FILE_EXT" "https://download.maxmind.com/app/geoip_download?edition_id=$FILE&license_key=$LICENSE_KEY&suffix=tar.gz"; then
  echo "[!] Download failed (check the license key): no GeoIP database installed"
  rm -f "$FILE$FILE_EXT"
  exit 0
fi

echo "[<<] Uncompressing $FILE$FILE_EXT"
tar -zxf $FILE$FILE_EXT || { echo "[!] Not a valid archive: no GeoIP database installed"; rm -f "$FILE$FILE_EXT"; exit 0; }

echo "[->] Moving $FILE.mmdb file to $PWD"
mv ./${FILE}_*/${FILE}.mmdb ./
rm -R ./${FILE}_*
rm $FILE$FILE_EXT

# The daemon only reads the database
chmod 644 GeoLite2-City.mmdb
echo "[✓] Done"
