#!/bin/sh
# synology-ddns-cloudflare.sh

CLOUDFLARE_SCRIPT1_DST='/usr/syno/bin/ddns/SynologyDnsUpdaterAbstract.php'
CLOUDFLARE_SCRIPT2_DST='/usr/syno/bin/ddns/CloudflareDnsUpdater.php'
CLOUDFLARE_SCRIPT1_SRC='https://raw.githubusercontent.com/thomazz-nl/synology-ddns-cloudflare/master/SynologyDnsUpdaterAbstract.php'
CLOUDFLARE_SCRIPT2_SRC='https://raw.githubusercontent.com/thomazz-nl/synology-ddns-cloudflare/master/CloudflareDnsUpdater.php'
NOW=$(date +%F)
DDNS_CONFIG='/etc.defaults/ddns_provider.conf'
DDNS_CONFIG_BAK="/etc.defaults/ddns_provider-$NOW.conf.bak"

if [ "$EUID" -eq 0 ]; then
    wget "$CLOUDFLARE_SCRIPT1_SRC" -O "$CLOUDFLARE_SCRIPT1_DST"
    wget "$CLOUDFLARE_SCRIPT2_SRC" -O "$CLOUDFLARE_SCRIPT2_DST"
    chmod 0755 "$CLOUDFLARE_SCRIPT1_DST"
    chmod 0755 "$CLOUDFLARE_SCRIPT2_DST"

    if ! grep -Eq '^\[Cloudflare\]$' "$DDNS_CONFIG"; then
        echo "Status: Cloudflare not yet present in $DDNS_CONFIG"
        
        if [ ! -f "$DDNS_CONFIG_BAK" ] && cp -nav "$DDNS_CONFIG" "$DDNS_CONFIG_BAK"; then
            echo '[Cloudflare]' >> "$DDNS_CONFIG"
            echo "        modulepath=$CLOUDFLARE_SCRIPT2_DST"  >> "$DDNS_CONFIG"
            echo '        queryurl=https://www.cloudflare.com/' >> "$DDNS_CONFIG"
            echo 'Operation succeeded: please go to DSM > Control Panel > External Access > DDNS and add Cloudflare as DDNS provider.'
            exit 0
        else
            echo "Operation failed: could not create unique backup of $DDNS_CONFIG"
            exit 1
        fi
    else
        echo 'Operation failed: Cloudflare config section already defined.'
        exit 1
    fi
else
    echo 'Operation failed: please run as root.'
    exit 1
fi
