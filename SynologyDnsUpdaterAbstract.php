<?php

abstract class SynologyDnsUpdater
{
    const DEBUG_ENABLED = false;
    
    private $username;
    private $password;
    private $hostname;
    private $ip;

    public function __construct($argc, $argv)
    {
        if ($argc === 5) {
            if ( ! $this->isValidHostname($argv[3])) {
                $this->returnResult(107);
            } elseif ( ! $this->isValidIp($argv[4])) {
                $this->returnResult(1);
            } else {
                $this->username = (string) $argv[1];
                $this->password = (string) $argv[2];
                $this->hostname = (string) $argv[3];
                $this->ip       = (string) $argv[4];
                $this->updateDns();
            }
        } else {
            $this->returnResult(1);
        }
    }
    
    protected function debug($message)
    {
        if (self::DEBUG_ENABLED)
        {
            echo "debug: {$message}\r\n";
        }
    }
    
    protected function getUsername()
    {
        return $this->username;
    }
    
    protected function getPassword()
    {
        return $this->password;
    }
    
    protected function getHostname()
    {
        return $this->hostname;
    }
    
    protected function getIp()
    {
        return $this->ip;
    }
    
    protected function returnResult($returnvalue)
    {
        /** Output as defined by Synology in /etc.defaults/ddns_provider.conf:
                good -  Update successfully.
                nochg - Update successfully but the IP address have not changed.
                nohost - The hostname specified does not exist in this user account.
                abuse - The hostname specified is blocked for update abuse.
                notfqdn - The hostname specified is not a fully-qualified domain name.
                badauth - Authenticate failed.
                911 - There is a problem or scheduled maintenance on provider side
                badagent - The user agent sent bad request(like HTTP method/parameters is not permitted)
                badresolv - Failed to connect to  because failed to resolve provider address.
                badconn - Failed to connect to provider because connection timeout.
        */
        switch($returnvalue) {
            case 0:
                echo 'good';
                break;
            case 1:
                echo 'badparam';
                break;
            case 100:
                echo '911';
                break;
            case 101:
                echo 'abuse';
                break;
            case 102:
                echo 'badagent';
                break;
            case 103:
                echo 'badauth';
                break;
            case 104:
                echo 'badconn';
                break;
            case 105:
                echo 'badresolv';
                break;
            case 106:
                echo 'nohost';
                break;
            case 107:
                echo 'notfqdn';
                break;
            case 200:
                echo 'nochg';
                break;
            default:
                echo 'unknown result';
        }
    }

    abstract protected function updateDns();
    
    // check the hostname contains '.'
    private function isValidHostname($hostname = '')
    {
        return strpos($hostname, '.') === false ? false : true;
    }
    
    // only for IPv4 format
    private function isValidIp($ip = '')
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? true : false;
    }
}

?>