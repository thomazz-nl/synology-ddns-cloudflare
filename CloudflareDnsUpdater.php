#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php

require('SynologyDnsUpdaterAbstract.php');

/*  DDNS auto updater for Synology NAS
    Based on Cloudflare API v4 - https://api.cloudflare.com/
*/
class CloudflareDnsUpdater extends SynologyDnsUpdater
{
    const API_URL = 'https://api.cloudflare.com';

    private $zoneId;
    private $zoneToken;

    protected function updateDns()
    {
        $this->zoneId       = $this->getUsername();  // Map Synology "username" input field to Cloudflare's Zone ID
        $this->zoneToken    = $this->getPassword();  // Map Synology "password" input field to Cloudflare's API Token (not the risky global API key!)

        if (empty($this->zoneId)) {
            $this->debug('missing Zone ID');
            $this->zoneId = $this->findZoneId($this->getHostname());
        }

        if (empty($this->zoneId)) {
            $this->debug('still missing Zone ID');
            $this->returnResult(106);
        } else {
            $this->debug('got Zone ID');
            $getDnsRecordsResponse = $this->getDnsRecords($this->zoneId, true);

            if (!empty($getDnsRecordsResponse)) {
                if ($getDnsRecordsResponse['success'] && !empty($getDnsRecordsResponse['result'])) {
                    $this->debug('dns records found');

                    // This script processes the first/only DNS record for a given hostname and recordtype (A).
                    // Multiple records with different values can exist (e.g. for Round Robin DNS loadbalancing), but is beyond the scope/goal of this script (DDNS registration).
                    // Handling multiple records would require more than the supplied 4 parameters to differentiate between the records, forcing the script to save values/state, while the aim is to work stateless.
                    $dnsRecordId        = $getDnsRecordsResponse['result'][0]['id'];
                    $dnsRecordValue     = $getDnsRecordsResponse['result'][0]['content'];

                    if ($dnsRecordValue === $this->getIp()) {
                        $this->debug('ip has not changed');
                        $this->returnResult(200);
                    } else {
                        $this->debug('ip has changed');
                        $data = [
                            'content' => $this->getIp()
                        ];
                        $patchDnsRecordResponse = $this->patchDnsRecord($this->zoneId, $dnsRecordId, $data);

                        if (!empty($patchDnsRecordResponse)) {
                            if ($patchDnsRecordResponse['success'] && !empty($patchDnsRecordResponse['result'])) {
                                $this->debug('succesfully updated dns record');
                                $this->returnResult(0);
                            } else {
                                $this->debug('failed to updated dns record');
                                $this->returnResult(1);
                            }
                        }
                    }
                } else {
                    $this->debug('no dns records found');
                    $this->returnResult(106);
                }
            }
        }
    }

    private function requestCloudflareApi($method, $path, $data = [])
    {
        $curlHandle = curl_init();
        $options    = array(
            CURLOPT_FAILONERROR => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->zoneToken, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => self::API_URL . $path,
            CURLOPT_VERBOSE => false
        );
        $processedResponse = json_decode('{}', true);

        switch($method) {
            case 'GET':
                $options[CURLOPT_HTTPGET]       = true;
                break;
            case 'POST':
                $options[CURLOPT_POST]          = true;
                $options[CURLOPT_HTTPGET]       = false;
                $options[CURLOPT_POSTFIELDS]    = json_encode($data);
                break;
            case 'PATCH':
            case 'PUT':
                $options[CURLOPT_POST]          = false;
                $options[CURLOPT_HTTPGET]       = false;
                $options[CURLOPT_CUSTOMREQUEST] = $method;
                $options[CURLOPT_POSTFIELDS]    = json_encode($data);
                break;
            default:
                $options[CURLOPT_POST]          = false;
                $options[CURLOPT_HTTPGET]       = false;
                $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($curlHandle, $options);
        $this->debug("calling {$method} {$options[CURLOPT_URL]}");
        
        $response           = curl_exec($curlHandle);
        $curlErrorNumber    = curl_errno($curlHandle);
        $httpStatusCode     = curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);

        curl_close($curlHandle);

        if ($curlErrorNumber === CURLE_OK) {
            $processedResponse = json_decode($response, true);
        } elseif ($curlErrorNumber === CURLE_HTTP_RETURNED_ERROR) {
            switch ($httpStatusCode) {
                case 400:   // Bad Request - request was invalid
                    $this->returnResult(102);
                    break;
                case 401:   // Unauthorized	- user does not have permission
                    $this->returnResult(103);
                    break;
                case 403:	// Forbidden - request not authenticated
                    $this->returnResult(103);
                    break;
                case 405:	// Method Not Allowed - incorrect HTTP method provided
                    $this->returnResult(102);
                    break;
                case 429:	// Too many requests - client is rate limited
                    $this->returnResult(101);
                    break;
                case 415:	// Unsupported Media Type - response is not valid JSON
                default:
                    $this->returnResult(1);
            }
        } elseif ($curlErrorNumber === CURLE_COULDNT_RESOLVE_HOST) {
            $this->returnResult(105);
        } elseif ($curlErrorNumber === CURLE_OPERATION_TIMEDOUT || $curlErrorNumber === CURLE_COULDNT_CONNECT) {
            $this->returnResult(104);
        } else {
            $this->returnResult(1);
        }

        $this->debug("curl error number = {$curlErrorNumber}");
        $this->debug("http status code = {$httpStatusCode}");
        
        /*if (self::DEBUG_ENABLED) {
            var_dump($processedResponse);
        }*/

        return $processedResponse;
    }

    private function findZoneId($hostname)
    {
        $zoneId             = '';
        $hostnameParts      = array_reverse(explode('.', $hostname));
        $hostnamePartsCount = count($hostnameParts);

        // Try getting Zone ID for given hostname.
        for ($i=1, $possibleBaseDomainname=$hostnameParts[0]; $i < $hostnamePartsCount; $i++) {
            // Trying basedomain.tld first, and expand when required. For example: co.uk > basedomain.co.uk
            $possibleBaseDomainname = "{$hostnameParts[$i]}.{$possibleBaseDomainname}";
            
            $this->debug("trying to obtain zone details for {$possibleBaseDomainname}");
            
            $getZoneResponse = $this->getZone($possibleBaseDomainname);

            if (empty($getZoneResponse)) {
                break;
            } else if ($getZoneResponse['success'] && !empty($getZoneResponse['result'])) {
                $zoneId = $getZoneResponse['result'][0]['id'];
                $this->debug("Zone ID found - {$zoneId}");
                break;
            }
        }

        return $zoneId;
    }

    private function getDnsRecords($zoneId)
    {
        return $this->requestCloudflareApi('GET', "/client/v4/zones/{$zoneId}/dns_records?type=A&name={$this->getHostname()}");
    }

    private function getZone($domainname)
    {
        return $this->requestCloudflareApi('GET', "/client/v4/zones?name={$domainname}&status=active");
    }

    private function isValidToken()
    {
        $validTokenResponse = $this->requestCloudflareApi('GET', '/client/v4/user/tokens/verify');

        if(!empty($validTokenResponse) && $validTokenResponse['success'] && $validTokenResponse['result']['status'] == 'active') {
            return true;
        } else {
            return false;
        }
    }

    private function patchDnsRecord($zoneId, $dnsRecordId, $data)
    {
        return $this->requestCloudflareApi('PATCH', "/client/v4/zones/{$zoneId}/dns_records/{$dnsRecordId}", $data);
    }
}

$myCfUpdater = new CloudflareDnsUpdater($argc, $argv);

?>