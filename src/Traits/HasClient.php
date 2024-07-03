<?php

namespace Hak\Payments\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait HasClient
{
     public function send(string $url, string $path, string $data)
     {
          $client = new Client([
               'base_uri' => $this->getBaseUrl($url),
               'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
               ]
          ]);

          try {
               $response = $client->request('POST', $path, [
                    'body' => $data
               ]);

               if($response->getStatusCode() == 200) {
                    $resData = $response->getBody()->getContents();

                    return json_decode($resData, true);
               } else {
                    return 'Error: ' . $response->getStatusCode();
               }

          } catch (GuzzleException $e) {
               return $e->getMessage();
          }
     }

     private function getBaseUrl(string $url)
     {
          if (preg_match('/\/4\.3$/', $url)) {
               // Append the trailing slash
               $url .= '/';
           }
           return $url;
     }
}