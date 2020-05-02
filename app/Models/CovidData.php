<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CovidData extends Model
{
    protected $appends = ['risk_str'];
    protected $hidden = ['id', 'date', 'created_at', 'updated_at'];

    public function getRiskStrAttribute()
    {
        if ($this->risk < 300) {
            $RiskStr = '低';
        } elseif ($this->risk < 600) {
            $RiskStr = '中';
        } else {
            $RiskStr = '高';
        }
        return $RiskStr;
    }

    public function getProvinceAttribute()
    {
        if ($province = $this->attributes['province']) {
            $province = preg_replace('[省|市|自治区|回族|壮族|维吾尔|特别行政区]', '', $province);
        }
        return $province;
    }
}
