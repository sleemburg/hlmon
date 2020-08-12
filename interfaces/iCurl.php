<?php
/** -----------------------------------------------------------------------------
 * PDX-License-Identifier: GPL-2.0-or-later
 *
 * @author      Stephan Leemburg <stephan@it-functions.nl>
 * @copyright   Copyright (c) 2020, IT Functions
 * ------------------------------------------------------------------------------
 */

interface iCurl
{
    const GET       = 'GET';
    const PUT       = 'PUT';
    const POST      = 'POST';
    const PATCH     = 'PATCH';
    const DELETE    = 'DELETE';

    const HTTP      = 'http';
    const HTTPS     = 'https';

    const RESPONSE  = [ 'request' => [ 
                             'domain' => NULL
                            ,'scheme' => NULL
                            ,'uri' => NULL
                            ,'method' => NULL
                        ]
                        ,'reply_code' => NULL
                        ,'headers' => []
                        ,'body' => NULL
                    ];

    /* -----------------------------------------------------------------------
     * Required constants
     * -----------------------------------------------------------------------
     * - ENDPOINTS, like:

           const ENDPOINTS = [
           // version
                1 => [
            // method          endpoint
                         'products'     => '/api/rest/v1/products?size=9999'
                        ,'orders'       => '/api/rest/v1/orders?size=9999'
                        ,'order'        => '/api/rest/v1/orders/%d'
                        ,'orderrows'    => '/api/rest/v1/orders/%d/orderrows?size=9999'
                ],
              ];
      */

    /* -----------------------------------------------------------------------
     * Required implementations
     * -----------------------------------------------------------------------
     */
    
    /**
     * Returns headers for the curl request
     *
     * @sets   $this->headers (Array)
     * @return Boolean
     */
    public function setHeaders($method, $uri, $data=NULL);
    
    /* -----------------------------------------------------------------------
     * Optional implementations (override the tCurl default)
     * -----------------------------------------------------------------------
     */
    
    /**
     * Validates the context for curl calls
     *
     * @return Boolean : FALSE if invalid state
     */
    public function validateState();
}
