<?php

namespace App\Compatibility;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded = [];

    public function americanLogistic()
    {
        return $this->hasOne('App\Compatibility\Conveyor\AmericanLogistics', 'NIT','NIT');
    }
    public function guides()
    {
        return $this->hasMany('App\Compatibility\Conveyor\AmericanLogisticGuide', 'company_id');
    }
    public function asterisk()
    {
        return $this->hasOne('App\Compatibility\Asterisk\Asterisk', 'company_id','id');
    }
}
