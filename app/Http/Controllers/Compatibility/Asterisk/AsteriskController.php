<?php

namespace App\Http\Controllers\Compatibility\Asterisk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Compatibility\Asterisk\AsteriskManager;
use Illuminate\Support\Facades\Crypt;
use GuzzleHttp\Client;

class AsteriskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    /**
     * Connect to an Asterisk server
     *
     * @param Request $request
     * @return AsteriskManager
     */
    private function asteriskConnect($request)
    {
        $token = $request->bearerToken();
        $user=$request->user()->company->asterisk;
        $pass=Crypt::decrypt($user->password);
        $asterisk=new AsteriskManager(['asmanager'=>[
            'server'=>$user->server_address,
            'username'=>$user->user,
            'secret'=>$pass
        ]]);
        $asterisk->connect();
        return $asterisk;
    }
    /**
     * Get queue info
     *
     * @param [type] $queue
     * @return json
     */
    public function queue(Request $request, $queue)
    {
        $asterisk=$this->asteriskConnect(request());
        $command="queue show 3000";
        $data= $this->processQueueData($asterisk->Command($command));
        return response()->json($data);
    }
    /**
     * Process queue data from Asterisk
     */
    private function processQueueData($result)
    {
        $exp=\explode(PHP_EOL,$result['data']);
        $last_index=array_key_last($exp);
        unset($exp[0]);
        unset($exp[1]);
        unset($exp[2]);
        unset($exp[$last_index]);
        unset($exp[$last_index-1]);
        unset($exp[$last_index-2]);
        $channels=[];
        foreach ($exp as $item) {
            $item=explode('(',\substr($item,6));
            $channel=substr($item[0], 0, -1);
            if (stripos($item[3],'pause') === false) {
                $pause=false;
                $status_explode=explode(')',$item[3]);
                $status=$status_explode[0];
            }else{
                $pause=true;
                $status_explode=explode(')',$item[4]);
                $status=$status_explode[0];
            }
            $channels[]=[
                'channel'=>$channel,
                'pause'=>$pause,
                'status'=>$status
            ];   
        }
        return $channels;
    }
    /**
     * Originate a new call in Asterisk
     *
     * @param Request $request
     * @return void
     */
    public function originate(Request $request)
    {
        $json_body=$request->all();
        $asterisk=$this->asteriskConnect(request());
        $result=$asterisk->Originate('PJSIP/'.$json_body['src'],$json_body['dst'], 'from-internal','1');
        return response()->json($result);
    }
    /**
     * Get call info from Asterisk
     *
     * @param Request $request
     * @return void
     */
    public function callInfo(Request $request)
    {
        $json_body=$request->all();
        $asterisk=$this->asteriskConnect(request());
        $result=$asterisk->ExtensionState('PJSIP/'.$json_body['channel'],'from-internal');
        return response()->json($result);
    }
    /**
     * Function to connect Asterisk using ARI
     *
     * @param Request $request
     * @return Asterisk
     */
    private function getARIConnectionVariables($request)
    {
        $token = $request->bearerToken();
        return $request->user()->company->asterisk;
    }
    /**
     * Function to bring the information of an active channel using AGI and ARI
     *
     * @param Request $request
     * @param [String] $src
     * @return void
     */
    public function detailedCallInfo(Request $request, $src)
    {
        $user=$this->getARIConnectionVariables(request());
        try {
            //AGI
            $asterisk=$this->asteriskConnect(request());
            $agi_result=$asterisk->ExtensionState('PJSIP/'.$src,'from-internal');
            //ARI
            $client = new Client(['base_uri' => $user->server_address.':'.$user->ari_port]);
            $response=$client->request('GET', '/ari/endpoints/PJSIP/'.$src, ['auth' => [$user->ari_user, Crypt::decrypt($user->ari_password)]]);
            $ari_response=json_decode($response->getBody()->getContents());
            //ARI channels
            $ari_channels=[];
            foreach ($ari_response->channel_ids as $channel_id) {
                $data=$this->getARIChannelDetail($client, $channel_id, $user->ari_user, $user->ari_password);
                $ari_channels[]=$data;
            }
            return response()->json(
                [
                    'agi'=>$agi_result,
                    'ari'=>$ari_response,
                    'ari_channels'=>$ari_channels,
                ]
            );
        } catch (\Throwable $th) {
            return response()->json($th);
        }        
    }
    /**
     * Function to fetch the information of a specific active channel using ARI
     *
     * @param GuzzleHttp\Client $client
     * @param [String] $channel_id
     * @param [String] $ari_user
     * @param [String] $ari_password
     * @return void
     */
    private function getARIChannelDetail($client, $channel_id, $ari_user, $ari_password)
    {
        try {
            $response=$client->request('GET', '/ari/channels/'.$channel_id, ['auth' => [$ari_user, Crypt::decrypt($ari_password)]]);
            return json_decode($response->getBody()->getContents());
        } catch (\Throwable $th) {
            return response()->json($th);
        }  
    }
}
