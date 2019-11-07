<?php

function gc_mailblaze_general_test(){
    $params = [
        'general' =>[
            'name' => 'Test 1',
            'description' => 'Test Description 1'
        ],
    ];

    $subscribers = [
        ['email' => 'guy+mb@wowfingers.com', 'fname' => 'Guy'],
        ['email' =>'greencarrots+mb@gmail.com', 'lname' => 'Morel']
    ];

    //$results = gc_mailblaze_lists_create($params);
    $results = gc_mailblaze_subscriber_lists_create($params, $subscribers);

    //debug($params);
    debug($results);
    debug($results['data']->list_uid);

}
