<?php

namespace App\Http\Controllers\Compatibility\Conveyor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Compatibility\Conveyor\AmericanLogisticsSoap;

class AmericanLogisticGuideController extends Controller
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
        /*  $name, $street, $origin_city, $destination_city,
            $COD, $phone, $weight, $pieces, $volume, $contains, $department,
            $transport_means, $payment_method, $declared_value, $value_to_raise,
            $day_delivery, $increment, $bill
        */
        $request = request();
        $token = $request->bearerToken();
        $user=$request->user()->company->americanLogistic;

        return response()->json(['token' => $user], 200);   
        if (count($request->json()->all())) {
            $postbody = $request->json()->all();
            if (isset($postbody['name']) && isset($postbody['street']) && isset($postbody['origin_city']) && 
                isset($postbody['destination_city']) && isset($postbody['COD']) && isset($postbody['phone']) && 
                isset($postbody['weight']) && isset($postbody['pieces']) && isset($postbody['volume']) && 
                isset($postbody['contains']) && isset($postbody['department']) && isset($postbody['transport_means']) &&
                isset($postbody['payment_method']) && isset($postbody['declared_value']) && isset($postbody['value_to_raise']) &&
                isset($postbody['day_delivery']) && isset($postbody['increment']) && isset($postbody['bill'])
                ) {
                    if ($postbody['COD']) {
                        $value_raised_ceiled= ceil($postbody['value_to_raise']);
                        $service="COD";
                        $value_to_raise="RECAUDAR $$value_raised_ceiled";
                        $div=  str_replace(".", "", $value_raised_ceiled);
                        $vrec=$value_raised_ceiled;
                        $x=$div/50000;
                        $ceil=  ceil($x);
                        $ref_1="-VUELTAS ".($ceil*50)."M";
                        //--
                        return response()->json(['sucess' => 'COD'], 200);   
                    } else {
                        $this->_service="INDUSTRIAL";
                        $this->_value_to_raise="SIN RECAUDO";
                        $this->_vrec=0;
                        $this->_ref_1="";
                        //--
                        return response()->json(['sucess' => ($request->json()->all())], 200);   
                    }
            } else {
                return response()->json(['error' => 'The request is not complete, one or all fields are missing'], 400);   
            }
        }else{
            return response()->json(['error' => 'The request does not contain a JSON body'], 400);   
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $al= new AmericanLogisticsSoap();
        $response=$al->geTreaceability($id);
        return response()->json(['data' => $response], 200);
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
    private function setArrayToCreateGuide($_ws_user, $_ws_password, $_name, $_street, $_origin_city, 
        $_destination_city, $_department, $_service, $_phone, $_weight, $_pieces, $_volume, $_value_to_raise, 
        $_transport_means, $_payment_method, $_declared_value, $_day_delivery, $_vrec, $_remission, 
        $_bill_number, $_ref_1, $_code_p)
    {
        return [
            'usu' => $_ws_user,
            'pwd' => $_ws_password,
            'nomb' => $_name,
            'dire' => $_street,
            'ciuo' => $_origin_city,
            'ciud' => $_destination_city,
            'depa' => $_department,
            'serv' => $_service,
            'tele' => $_phone,
            'kilo' => $_weight,
            'piez' => $_pieces,
            'volu' => $_volume,
            'cont' => $_value_to_raise,
            'tran' => $_transport_means,
            'mpag' => $_payment_method,
            'vdec' => $_declared_value,
            'empr' => $_day_delivery,
            'vrec' => $_vrec,
            'remi' => $_remission,
            'fact' => $_bill_number,
            'obse' => $_ref_1,
            'cpos' => $_code_p,
        ];
    }
}
