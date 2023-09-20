<?php

namespace App\Utils;



class PropReportsApiClient
{


    /**
     * PropReports API Client v.2.3
     */

    private $url;
    private $username = null;
    private $password = null;
    private $curlHandle;
    private $token;
    private $isTokenEphemeral;
    private $tokenAcquisitionTime;
    private $lastError = null;
    private $totalPages = null;

    function __construct($host, $token = null)
    {
        $method = $host == '127.0.0.1' ? 'http' : 'https';
        $this->url = "$method://$host";
        $this->token = $token;
        $this->isTokenEphemeral = false;
        $this->curlHandle = curl_init($this->url . '/api.php');
        curl_setopt($this->curlHandle, CURLOPT_POST, true);
        curl_setopt($this->curlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($this->curlHandle, CURLOPT_ENCODING, 'identity');
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $this->curlHandle,
            CURLOPT_HTTPHEADER,
            array(
                'Content-type: multipart/form-data',
                'Connection: Keep-Alive',
                'Keep-Alive: 300'
            )
        );
    }

    function setCredentials($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->isTokenEphemeral = true;
    }

    function getToken()
    {
        return $this->token;
    }

    function getUrl()
    {
        return $this->url;
    }

    function getUsername()
    {
        return $this->username;
    }

    function getLastError()
    {
        return $this->lastError;
    }

    // private function writeFile($curlHandle, $data)
    // {
    //     $len = fwrite($this->currentFileHandle, $data);
    //     return $len;
    // }

    private function beginsWith($str, $sub)
    {
        return substr($str, 0, strlen($sub)) === $sub;
    }

    private function curlCallWithRetry($maxRetries, $initialWait, $exponent)
    {
        $response = curl_exec($this->curlHandle);
        if ($response === false) {
            $errorNumber = curl_errno($this->curlHandle);
            $this->lastError = 'Error Code ' . $errorNumber . ': ' . curl_error($this->curlHandle);
            return false;
        }
        $returnCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
        if ($returnCode == 200) {
            return $response;
        }
        if ($returnCode != 429 || $maxRetries <= 0) {
            $this->lastError = 'Return Code ' . $returnCode . ': ' . $response;
            return false;
        }
        usleep($initialWait * 1E6);
        return $this->curlCallWithRetry($maxRetries - 1, $initialWait * $exponent, $exponent);
    }

    private function makeRequest($action, $parameters = array(), $fh = null)
    {
        $this->totalPages = null;
        $request['action'] = $action;
        if ($this->token) {
            $request['token'] = $this->token;
        }
        foreach ($parameters as $key => $value) {
            $request[$key] = $value;
        }
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $request);
        $response = $this->curlCallWithRetry(10, 1, 1.5);
        if ($response != false) {
            if (isset($parameters['page'])) {
                $lastLineBreakIndex = strrpos($response, "\n", -1);
                if ($lastLineBreakIndex !== false) {
                    $lastLine = substr($response, $lastLineBreakIndex + 1);
                    if ($this->beginsWith($lastLine, 'Page ')) {
                        $currentAndTotal = explode('/', substr($lastLine, 4));
                        if (count($currentAndTotal) == 2 && is_numeric($currentAndTotal[1])) {
                            $this->totalPages = (int) $currentAndTotal[1];
                            $response = substr($response, 0, -1 * (strlen($lastLine) + 1));
                        }
                    }
                }
            }
            if (!is_null($fh)) {
                fwrite($fh, $response);
            }
        }
        return $response;
    }

    private function loginIfNeeded()
    {
        if ($this->token && (!$this->isTokenEphemeral || (time() - $this->tokenAcquisitionTime) < 6000) /* RPT-3759 */) {
            return true;
        }
        if (is_null($this->username) && is_null($this->password)) {
            return false;
        }
        $this->token = $this->makeRequest('login', array('user' => $this->username, 'password' => $this->password));
        if ($this->token) {
            $this->tokenAcquisitionTime = time();
            return true;
        }
        return false;
    }

    public function logout()
    {
        if ($this->token) {
            $this->makeRequest('logout');
            $this->token = null;
        }
    }

    public function accounts($page = 1)
    {
        if ($this->loginIfNeeded()) {
            return $this->makeRequest('accounts', array('page' => $page));
        } else {
            return false;
        }
    }


    public function groups()
    {
        if ($this->loginIfNeeded()) {
            return $this->makeRequest('groups', array());
        } else {
            return false;
        }
    }


    private function makeAccountIds($accountIdOrIds)
    {
        return is_array($accountIdOrIds) ? join(',', $accountIdOrIds) : $accountIdOrIds;
    }

    public function fills($fh, $accountIdOrIds, $startDate, $endDate, $page = null)
    {
        if ($this->loginIfNeeded()) {
            if ($page != null)
                return $this->makeRequest('fills', array(
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'accountId' => $this->makeAccountIds($accountIdOrIds),
                    'page' => $page
                ), $fh);
            else
                return $this->makeRequest('fills', array(
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'accountId' => $this->makeAccountIds($accountIdOrIds)
                ), $fh);
        } else {
            return false;
        }
    }

    public function positions($fh, $accountIdOrIds, $startDate, $endDate, $page = null)
    {
        if ($this->loginIfNeeded()) {
            if ($page != null)
                return $this->makeRequest('positions', array(
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'accountId' => $this->makeAccountIds($accountIdOrIds),
                    'page' => $page
                ), $fh);
            else
                return $this->makeRequest('positions', array(
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'accountId' => $this->makeAccountIds($accountIdOrIds)
                ), $fh);
        } else {
            return false;
        }
    }

    public function adjustments($fh, $accountIdOrIds, $startDate, $endDate, $page = null)
    {
        if ($this->loginIfNeeded()) {
            if ($page != null)
                return $this->makeRequest('adjustments', array(
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'accountId' => $this->makeAccountIds($accountIdOrIds),
                    'page' => $page
                ), $fh);
            else
                return $this->makeRequest('adjustments', array(
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'accountId' => $this->makeAccountIds($accountIdOrIds)
                ), $fh);
        } else {
            return false;
        }
    }

    public function reports($fh, $accountIdOrIds, $startDate, $endDate)
    {
        if ($this->loginIfNeeded()) {
            return $this->makeRequest('report', array(
                'startDate' => $startDate,
                'endDate' => $endDate,
                'type' => 'summaryByDate',
                'accountId' => $this->makeAccountIds($accountIdOrIds)
            ), $fh);
        } else {
            return false;
        }
    }

    public function version()
    {
        if ($this->loginIfNeeded()) {
            return $this->makeRequest('version');
        } else {
            return false;
        }
    }

    public function getTotalPages()
    {
        return $this->totalPages;
    }
}