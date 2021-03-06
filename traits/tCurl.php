<?php
/** -----------------------------------------------------------------------------
 * @package     Curl trait
 * @author      Stephan Leemburg <stephan@it-functions.nl>
 * @copyright   Copyright (c) 2020, IT Functions
 * ------------------------------------------------------------------------------
 * When using this trait, also implement the Http and iCurl interfaces
 *
 * with:
 *   require_once PATH_TO.'/interfaces/Http.php';
 *   require_once PATH_TO.'/interfaces/iCurl.php';
 * 
 * where:
 *   PATH_TO is your path to the interfaces directory
 *
 * and
 *    implements Http, iCurl
 *
 * then
 *    use tCurl;
 * ------------------------------------------------------------------------------
 */

if (!function_exists('hrtime')) {
    function hrtime(bool $get_as_number = FALSE)
    {
        if ($get_as_number) // convert to nanoseconds
            return (int) (microtime(TRUE) * 1e+6 );

        $m = microtime(TRUE);
        $int = $m > 0 ? floor($m) : ceil($m);
        $dec = (int) (($m - $int) * 1e+8) / 1;
        return [ $dec, $int ];
    }
}

trait tCurl
{
    private $domain     = NULL;
    private $apiVersion = NULL;
    private $debugCurl  = FALSE;
    private $lastError  = NULL;
    private $headers    = NULL;
    private $response   = self::RESPONSE;
    private $lastCode   = NULL;
    private $curl_info  = [];

    // performance timers
    private $time_start             = 0;
    private $time_end_validate      = 0;
    private $time_end_initialize    = 0;
    private $time_end_call          = 0;
    private $time_end               = 0;

    private $schemes = [ self::HTTP, self::HTTPS ];

    /**
     * Sets the api version to use in calls
     *
     * @param  Int $apiVersion
     * @throws Exception
     */
    public function setApiVersion(int $apiVersion)
    {
        if (!is_int($apiVersion)
        || (int)$apiVersion <= 0
        || (int)$apiVersion > count(self::ENDPOINTS))
            throw new Exception(__CLASS__.'::'.__FUNCTION__
                .': invalid api version provided ('.$apiVersion.')');

        $this->apiVersion = $apiVersion;
    }

    /**
     * Returns the api version to use in calls
     *
     * @return Int | NULL
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Sets curl debug on or off
     *
     * @param Boolean
     */
    public function setDebugCurl($on=TRUE)
    {
        $this->debugCurl = $on ? TRUE : FALSE;
    }

    /**
     * Returns curl debug state
     *
     * @return Boolean
     */
    public function getDebugCurl()
    {
        return $this->debugCurl;
    }

    /**
     * Sets the endpoint FQDN
     *
     * @param String
     */
    public function setDomain(string $domain)
    {
        $domain = strtolower(trim($domain));
        if (substr($domain, -1, 1) == '/')
            $domain = substr($domain, 0, -1);

        if ($domain != '')
            $this->domain = $domain;
    }

    /**
     * Returns the endpoint FQDN
     *
     * @return String
     */
    public function getDomain()
    {
        return $this->domain;
    }

    public function getResponseHeader($name)
    {
        $name = strtolower(trim($name));
        if (is_array($this->response['headers'] ?? NULL))
        {
            foreach ($this->response['headers'] as $header => $value)
            {
                if (strtolower($header) == $name)
                    return $value;
            }
        }
        return NULL;
    }

    /**
     * Returns the last ResponseCode
     *
     * @return Integer or False if no responseCode yet
     */
    public function getResponseCode()
    {
        return $this->lastCode ?? FALSE;
    }

    /**
     * Returns the last error
     *
     * @return String
     */
    public function getError()
    {
        return $this->lastError;
    }

    /**
     * Validates the context for curl calls
     *
     * @return Boolean
     */
    public function validateState()
    {
        return    $this->domain !== NULL;
    }

    /**
     * substitutes variable placeholders (like {id}) with actual values
     *
     * @return String with substituted variables
     */
    public function substitute(string $in, array $vars)
    {
        $out = $in;

        foreach ($vars as $var => $val)
            $out = str_replace('{'.$var.'}', $val, $out);

        return $out;
    }

    public function getInfo()
    {
        return $this->curl_info;
    }

    public function getPerformance()
    {
        if ($this->time_start === 0)
            return [];

        if ($this->time_end_validate === 0)
            $this->time_end_validate = $this->time_start;

        if ($this->time_end_initialize === 0)
            $this->time_end_initialize = $this->time_end_validate;

        if ($this->time_end_call === 0)
            $this->time_end_call = $this->time_end_initialize;

        if ($this->time_end === 0)
            $this->time_end = $this->time_end_call;

        return [ 
            'total_run_time' => ($this->time_end - $this->time_start) / 1e+6,
            'total_validation_time' => ($this->time_end_validate - $this->time_start) / 1e+6,
            'total_initialization_time' => ($this->time_end_initialize - $this->time_end_validate) / 1e+6,
            'total_execution_time' => ($this->time_end_call - $this->time_end_initialize) / 1e+6,
            'total_output_time' => ($this->time_end - $this->time_end_call) / 1e+6
            ];
    }

    /* -----------------------------------------------------------------------
     * Private methods
     * -----------------------------------------------------------------------
     */

    /**
     * Validates the scheme for curl calls
     *
     * @param String
     * @return Boolean
     */
    private function validateScheme($scheme)
    {
        $req = strtolower($scheme);
        foreach ($this->schemes as $s)
            if ($s == $req)
                return TRUE;
        return FALSE;
    }
    /**
     * Sets the last error
     *
     * @param String
     */
    protected function setError($err)
    {
        $method = debug_backtrace()[1]['function'];
        $this->lastError = $method.' : '.$err;
    }

    private function curl(string $method, string $scheme, string $uri, $data=NULL)
    {
        $this->time_start = hrtime(TRUE);
        $this->time_end_validate = 0;
        $this->time_end_initialize = 0;
        $this->time_end_call = 0;
        $this->time_end = 0;

        $this->response = self::RESPONSE;

        if (!$this->validateState())
        {
            $this->setError('invalid state');
            return FALSE;
        }

        if (!$this->validateScheme($scheme))
        {
            $this->setError('invalid scheme: ['.$scheme.']');
            return FALSE;
        }

        $this->time_end_validate = hrtime(TRUE);

        // remove scheme://domain from uri
        foreach ($this->schemes as $s)
        {
            $base = $s.'://'.$this->domain;
            $len = strlen($base);
            if (substr($uri, 0, $len) == $base)
                $uri = substr($uri, $len);
        }
        if (substr($uri, 0, 1) != '/')
            $uri = '/'.$uri;

        $defaults = [
            CURLOPT_URL => $scheme.'://'.$this->domain.$uri,
            CURLOPT_HEADER => TRUE,
            CURLOPT_FAILONERROR => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_TIMEOUT => 10
            ];

        switch ($method)
        {
        case self::GET:
            $defaults[CURLOPT_HTTPGET] = TRUE;

            $sep = (strpos($uri, '?') === FALSE ? '?' : '&');
            if ($data !== NULL && is_array($data))
            {
                $first = TRUE;
                foreach ($data as $key=>$val)
                {
                    $uri .= $sep;
                    $uri .= $key.'='.$val;
                    if ($first)
                    {
                        $sep = '&';
                        $first = FALSE;
                    }
                }
                $defaults[CURLOPT_URL] = $scheme.'://'
                                .$this->domain.$uri;
            }
            break;

        case self::POST:
            $defaults[CURLOPT_POST] = TRUE;
            $defaults[CURLOPT_POSTFIELDS] = $data;
            break;

        case self::PUT:
        case self::PATCH:
            $defaults[CURLOPT_CUSTOMREQUEST] = $method;
            $defaults[CURLOPT_POSTFIELDS] = $data;

            if ($this->debugCurl)
            {
                if (is_string($data))
                    echo "Data: {$data}".PHP_EOL;
                else
                {
                    echo 'Data: ';
                    print_r($data).PHP_EOL;
                }
            }
            break;

        case self::DELETE:
            $defaults[CURLOPT_CUSTOMREQUEST] = $method;
            break;

        case self::HEAD:
            $defaults[CURLOPT_NOBODY] = TRUE;
            break;
        }

        $h = curl_init();

        if ($this->debugCurl)
        {
            ob_start();

            curl_setopt($h, CURLOPT_VERBOSE, true);
            curl_setopt($h, CURLOPT_STDERR, STDERR);
        }
        curl_setopt_array($h, $defaults);

        if ($this->setHeaders($method, $uri, $data) !== FALSE
        &&  is_array($this->headers))
            curl_setopt($h, CURLOPT_HTTPHEADER, $this->headers);

        $this->time_end_initialize = hrtime(TRUE);

        $r = curl_exec($h);
        $this->curl_info = curl_getinfo($h);
        $this->lastCode = $code = (int)($this->curl_info['http_code'] ?? 501);
        curl_close($h);

        $this->time_end_call = hrtime(TRUE);

        $this->response['request']['domain'] = $this->getDomain();
        $this->response['request']['send_headers'] = $this->headers;
        $this->response['request']['method'] = $method;
        $this->response['request']['scheme'] = $scheme;
        $this->response['request']['uri'] = $uri;

        $this->response['reply_code'] = $code;

        $headers = true;
        foreach (explode("\n", $r) as $row)
        {
            $row = trim($row);

            if ($headers && $row == '')
            {
                $headers = false;
                continue;
            }

            if ($headers)
            {
                $a = explode(':', $row, 2);

                $this->response['headers'][$a[0]] = (count($a) > 1
                                    ? $a[1] : '');
            }
            else
                $this->response['body'] .= $row;
        }

        if ($this->debugCurl)
        {
            echo ob_get_clean().PHP_EOL;

            if ($data !== NULL)
            {
                echo 'Data:'.PHP_EOL;
                print_r($data);
            }
            echo 'Response:'.PHP_EOL;
            print_r($this->response);
        }

        $this->time_end = hrtime(TRUE);

        switch ($method)
        {
        case self::GET:
        case self::DELETE:
            return ($code === self::HTTP_OK
                ? $this->response['body']
                : FALSE);

        case self::PUT:
        case self::POST:
        case self::PATCH:
            return ($code === self::HTTP_OK
                 || $code === self::HTTP_CREATED
                 || $code === self::HTTP_ACCEPTED
                 || $code === self::HTTP_NO_CONTENT
                ? $this->response['body']
                : FALSE);
        }
    }
}
