# synology-ddns-cloudflare
A PHP module providing Cloudflare support to Synology's DDNS system. Easy to install, extend with other providers and it uses Cloudflare's API Token based access instead of the less secure Global API Key.

## Requirements
Have a Cloudflare API Token ready, or create one at https://dash.cloudflare.com/profile/api-tokens. This is NOT the account global Cloudflare API Key, but a token you can set per zone/domain. Be sure to give the token Zone/DNS/Edit permissions for the targeted zone/domain.

![Cloudflare token creation](/cloudflare_token_creation.png)

## How to use
If you haven't made manual changes to the file holding the Synology DDNS providers (/etc.defaults/ddns_provider.conf), you can download the shellscript and run it as admin/root to install Cloudflare as DDNS provider. If you have made changes to this file before, you're probably smart enough to figure out what this script does and how to adapt to your situation.

After the installation script has run, close any open DSM control panel and go to DSM > Control Panel > External Access > DDNS and add Cloudflare as DDNS provider. Fill in the following values:
* **Hostname** - please supply the hostname of the dns A record you want to update to your current IP.
* **Username/Email** - (optional) your Cloudflare Zone ID (keep blank if you don't know how to find it, the script will find it for you).
* **Password/Key** - please supply your Cloudflare API Token.

![Synology DDNS setup](/synology_ddns_setup.png)
