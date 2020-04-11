<?php

namespace App\Compatibility\Conveyor;
use SoapClient;


class AmericanLogisticsSoap
{
    private $client=null;
    private $context=null;
    public function __construct()
    {
        $this->context = stream_context_create(
            [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
            ]
        ]);
    }
    private function setClient($url_complement)
    {
        try {
            $wsdl = "https://www.americansiscore.com/al-ws/$url_complement?wsdl";
            $this->client =  new \SoapClient($wsdl, array(
                'stream_context' => $this->context, 'trace' => true)
            );
        }catch (Exception $e) { 
            dd($e->getMessage());
        }
    }
    public function geTreaceability($guide)
    {
        $this->setClient('server_wst.php');
        try {
            $response=$this->client->__soapCall(
                'getXML',
                [
                    'id'=>'3004848113'
                ],
    
            );
            $simple = simplexml_load_string(utf8_encode(base64_decode($response)));
            $xml_result= json_decode( json_encode($simple) , 1);
            return $xml_result['miembro'];
        } catch (Exception $e) { 
            dd($e->getMessage());
        }
        
    }
}