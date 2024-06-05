<?php

namespace App\Http\Libraries;

class LineNotify2
{
     public function sendMessage(string $token, string $message)
     {
          $queryData = array('message' => $message);
          $queryData = http_build_query($queryData, '', '&');
          $headerOptions = array(
               'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                         . "Authorization: Bearer " . $token . "\r\n"
                         . "Content-Length: " . strlen($queryData) . "\r\n",
                    'content' => $queryData
               ),
          );
          $context = stream_context_create($headerOptions);
          $result = file_get_contents("https://notify-api.line.me/api/notify", FALSE, $context);
          $res = json_decode($result);
          return $res;
     }
}
