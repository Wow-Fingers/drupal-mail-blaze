<?php
/**
 * Mother class
 * Keeping it simple for now: all the methods are under one roof
 * https://www.mailblaze.com/support/article-view/360025314571
 */

class MailBlaze {

    // not to leave the scope of this class
    private $baseurl;
    private $public_key;
    private $defaults = [];
    private $debug = false;

    public $allowed_params = [
        'Lists' => [
            'url' => '/lists',
            'vars'=> [
                'general' =>[
                    'name',
                    'description'
                ],
                'defaults' => [
                    'from_name',
                    'reply_to',
                    'from_email',
                ],
                'company' => [
                    'name',
                    'country_id',
                    'address_1',
                    'city',
                    'zip_code'
                ]
            ]
        ],
        'Subscribers' => [
            'url' => '/lists/%s/subscribers',
            'vars' => [
                'EMAIL',  // why these have to be uppercase all of a susan? So say the docs
                'FNAME',
                'LNAME',
                '*' // not sure how to tackle custom tags if we implement a filter..
            ]
            // vars are actually for lists, so not sure if needed here..
        ]
    ];

    public function __construct($config, $defaults=[], $debug=false){
        $this->baseurl = $config['baseurl'];
        $this->public_key = $config['public_key'];

        $this->defaults = $defaults;
        $this->debug = $debug;
    }

    // allow some semblance of default + override
    public function merge_params($args, $defaults=[]){

        $params = array_replace_recursive($defaults, $args);

        // filter unknowns: later

        return $params;
    }

    public function get_connection_params($type, $args=[], $defaults=false, $url_parameters=[]){
        // get fuckt
        if( !isset($this->allowed_params[$type]) ){
            return false;
        }

        $params = $this->allowed_params[$type];
        $params['values'] = $args;

        // reset else?
        if( $defaults !== false ){
            $params['values'] = $this->merge_params($args, $defaults);
        }

        // replaces an array of placeholders, mapping to the position in array
        if( !empty($url_parameters) ){
            $params['url'] = vsprintf($params['url'], $url_parameters);
        }

        return $params;
    }

    // -- create simple.
    public function create($type, $parameters, $defaults=null, $url_parameters=[]){

        // gahd - need to er-think this defaults thing
        if( is_null($defaults) ){
            $defaults = $this->defaults;
        }

        // merge in defaults.
        $connect_params = $this->get_connection_params($type, $parameters, $defaults, $url_parameters);

        // for developers
        if( !isset($connect_params['url']) ){
            die('No set parameters for this connection');
        }

        // call
        return $this->gc_mailblaze_call($connect_params['url'], 'POST', $connect_params['values']);
    }

    // -- create hybrid (eg: create a list first, then attache some items)
    public function createListsSubscribers($parameters, $subscribers){
        // create a list:
        $list = $this->create('Lists', $parameters);

        if( !$list['success'] || !isset($list['data']->list_uid) ){
            return $list;
        }

        $added_count = 0;

        // else create subscribers: really sucks that it's 1by1
        foreach( $subscribers as $individual ){
            // sucks but api requires ucase keys.
            $individual = array_change_key_case($individual, CASE_UPPER);

            $added = $this->create('Subscribers', $individual, false, [$list['data']->list_uid]);

            if( $added['success'] ){
                $added_count ++;
            }
        }

        return [
            'success' => $added_count,
            'msg' => $added_count.' subscribers were added to the list.',
            'data' => []
        ];
    }

    // reach out to the API and get a response: I realize this is all Drupal.. fk.
    // we could refactor to make it agnostic by using CURL
    function gc_mailblaze_call($endpoint, $type='GET', $params =[]){
        $uri = $this->baseurl.$endpoint;

        $options = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => $this->public_key
            ],
        ];

        switch($type){
            case 'GET' :
                url($uri, array('query' => $params));
                break;
            case 'POST' :
                $options['method'] = 'POST';
                $options['data'] = http_build_query($params);
                break;
        }

        if( $this->debug ){
            debug($uri);
            debug($options);
        }

        $results = drupal_http_request($uri, $options);

        if( $this->debug ){
            debug(array_keys((array)$results));
            debug($results);
        }

        $response = [
            'success' => false,
            'msg' => 'Could not connect to Mail Blaze API',
            'data' => []
        ];

        /*
        * returns
        object (
          0 => 'request',
          1 => 'data',
          2 => 'protocol',
          3 => 'status_message',
          4 => 'headers',
          5 => 'code',
        */

        if( isset($results->data) ){
            $data = json_decode($results->data);
            $code = isset($results->code) ? $results->code: 'code not set';

            // variety of success
            if( in_array($code, [200, 201]) ){
                $response['success'] = true;
                $response['msg'] = 'Response get';

                if( $this->debug ){
                    debug($results->data);
                    debug(json_decode($results->data));
                }

                // what if there is no result string?
                if( is_object($data) || is_string($data) ){
                    $response['data'] = $data;
                }
            }
            else{
                $response['msg'] .= '. Failed with code:'.$code.'. '.$results->status_message;
            }
        }

        return $response;
    }
}
