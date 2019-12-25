<?php
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://swoft.org/docs
 * @contact  group@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

function user_func(): string
{
    return 'hello';
}

function friends($data): array
{

    return $data;
}

function json_msg($array): string
{

    $data['code'] = 0;
    $data['msg'] =  "success";
    $data['data'] = $array;

    return json_encode($data,JSON_UNESCAPED_UNICODE );
}


