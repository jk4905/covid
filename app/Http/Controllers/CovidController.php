<?php

namespace App\Http\Controllers;

use App\Http\Requests\CovidItemRequest;
use App\Http\Requests\CovidProvinceRequest;
use App\Http\Requests\CovidRequest;
use App\Models\CovidData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use function MongoDB\BSON\toJSON;

class CovidController extends BaseController
{
    /**
     * @param CovidRequest $request
     * @return mixed
     */
    public function index(CovidRequest $request)
    {
        $params = $request->all();
        $redis = Redis::connection('default');
        $key = 'covid_list_data_' . $params['date'];
        $data = $redis->get($key);
        if (empty($data)) {
//        if (true) {
            $provinceList = CovidData::query()->where('country_code', $params['country_code'])->where('date', $params['date'])->where('province_code', '!=', '')->where('city', '')->orderByDesc('confirmed')->get();
            $data = [
                'list'               => [],
                'total_confirmed'    => 0,
                'total_cured'        => 0,
                'total_dead'         => 0,
                'increase_confirmed' => 0,
                'increase_dead'      => 0,
                'increase_cured'     => 0,
                'delta_confirmed'    => 0,
                'delta_cured'        => 0,
                'delta_dead'         => 0,
                'major_cities'       => [],
            ];
            if (!empty($provinceList->toArray())) {
                $provinceCodeArr = array_column($provinceList->toArray(), "province_code");
                $cityList = CovidData::query()->where('date', $params['date'])->whereIn('province_code', $provinceCodeArr)->where('city', '!=', '')->orderByDesc('risk')->orderByDesc('confirmed')->get();
                $cityMapping = [];
                if (!empty($cityList->toArray())) {
                    foreach ($cityList->toArray() as $cityItem) {
                        unset($cityItem['country']);
                        unset($cityItem['country_code']);
                        unset($cityItem['province']);
//                        $cityItem['predicted_str'] = $this->getPredictedStr($cityItem['predicted']);
                        $cityMapping[$cityItem['province_code']][] = $cityItem;
                    }
                }
                $list = $provinceList->map(function ($item, $key) use ($cityMapping) {
//                    $predictedStr = $this->getPredictedStr($item->predicted);
                    $provinceItem = $item->toArray();
                    if (empty($cityMapping[$item->province_code])) {
                        $provinceItem['city'] = [];
                        return $provinceItem;
                    }
                    $city = $cityMapping[$item->province_code];
                    $provinceItem['city'] = $city;
//                    $provinceItem['predicted_str'] = $predictedStr;
                    return $provinceItem;
                });

                $totalConfirmed = array_sum(array_column($list->toArray(), 'confirmed'));
                $totalCured = array_sum(array_column($list->toArray(), 'cured'));
                $totalDead = array_sum(array_column($list->toArray(), 'dead'));
                $data = [
                    'list'            => $list,
                    'total_confirmed' => $totalConfirmed,
                    'total_cured'     => $totalCured,
                    'total_dead'      => $totalDead
                ];
            }

            $yesterday = CovidData::query()->where('country_code', $params['country_code'])->where('date', date('Y-m-d', strtotime($params['date'] . ' -1 day')))->where('province_code', '')->where('city', '')->select(\DB::raw('sum(confirmed) confirmed, sum(cured) cured, sum(dead) dead'))->first();
            $theDayBeforeYesterday = CovidData::query()->where('country_code', $params['country_code'])->where('date', date('Y-m-d', strtotime($params['date'] . ' -2 days')))->where('province_code', '')->where('city', '')->select(\DB::raw('sum(confirmed) confirmed, sum(cured) cured, sum(dead) dead'))->first();

//        变化量
            if (!empty($yesterday) && !empty($theDayBeforeYesterday)) {
                $data['delta_confirmed'] = ($data['total_confirmed'] - $yesterday->confirmed) - ($yesterday->confirmed - $theDayBeforeYesterday->confirmed);
                $data['delta_cured'] = ($data['total_cured'] - $yesterday->cured) - ($yesterday->cured - $theDayBeforeYesterday->cured);
                $data['delta_dead'] = ($data['total_dead'] - $yesterday->dead) - ($yesterday->dead - $theDayBeforeYesterday->dead);
                $data['increase_confirmed'] = $data['total_confirmed'] - $yesterday->confirmed;
                $data['increase_cured'] = $data['total_cured'] - $yesterday->cured;
                $data['increase_dead'] = $data['total_dead'] - $yesterday->dead;
            }

            $data['major_cities'] = [];
//            主要城市风险评估 全国主要城市：上海、北京、广州、深圳、天津、杭州、武汉、南京、成都、沈阳
            $majorCities = CovidData::query()->where('date', $params['date'])->where(function ($query) {
                $query->where(function ($queryItem) {
                    $queryItem->whereIn('province_code', ['310000', '110000', '120000'])->where('city', '');
                })->orWhere(function ($queryItem) {
                    $queryItem->whereIn('code', ['440100', '440300', '510100', '330100', '420100', '320100', '210100']);
                });
            })->get();
            $majorCitiesYesterday = CovidData::query()->where('date', date('Y-m-d', strtotime($params['date'] . ' -1 day')))->where(function ($query) {
                $query->where(function ($queryItem) {
                    $queryItem->whereIn('province_code', ['310000', '110000', '120000'])->where('city', '');
                })->orWhere(function ($queryItem) {
                    $queryItem->whereIn('code', ['440100', '440300', '510100', '330100', '420100', '320100', '210100']);
                });
            })->get();
            if (!empty($majorCities)) {
                foreach ($majorCities as $majorCity) {
//                    预测值
                    $dataItem = [
                        'city' => empty($majorCity['city']) ? $majorCity['province'] : $majorCity['city'],
                        'risk' => $majorCity['risk']
                    ];
                    foreach ($majorCitiesYesterday as $majorCitiesYesterdayItem) {
                        if ($majorCitiesYesterdayItem['province_code'] == $majorCity['province_code'] && $majorCitiesYesterdayItem['code'] == $majorCity['code']) {
                            $dataItem['delta_confirmed'] = $majorCity['confirmed'] - $majorCitiesYesterdayItem['confirmed'];
                            $dataItem['delta_predicted'] = $majorCity['predicted'] - $majorCitiesYesterdayItem['predicted'];
                        }
                    }
                    $data['major_cities'][] = $dataItem;
                }
            }
            $redis->set($key, json_encode($data));
        } else {
            $data = json_decode($data, true);
        }
        return $this->success($data);
    }

    /**
     * @param CovidProvinceRequest $request
     * @return mixed
     */
    public function province(CovidProvinceRequest $request)
    {
        $params = $request->all();
        $redis = Redis::connection('default');
        $key = 'covid_list_city_data_' . $params['province_code'] . $params['date'];
        $data = $redis->get($key);
        if (empty($data)) {
//        if (true) {
            $cityList = CovidData::query()->where('date', $params['date'])->where('province_code', $params['province_code'])->where('city', '!=', '')->orderByDesc('risk')->orderByDesc('confirmed')->get();

            $cityListYesterday = CovidData::query()->where('date', date('Y-m-d', strtotime($params['date'] . ' -1 day')))->where('province_code', $params['province_code'])->where('city', '!=', '')->get();
            $predictedList = [];
            foreach ($cityList as $city) {
                $dataItem = [
                    'city'            => $city['city'],
                    'code'            => $city['code'],
                    'delta_confirmed' => 0,
                    'delta_predicted' => 0,
                ];
                foreach ($cityListYesterday as $cityListYesterdayItem) {
                    if ($cityListYesterdayItem['city'] == $city['city'] && $cityListYesterdayItem['code'] == $city['code']) {
                        $dataItem['delta_confirmed'] = $city['confirmed'] - $cityListYesterdayItem['confirmed'];
                        $dataItem['delta_predicted'] = $city['predicted'] - $cityListYesterdayItem['predicted'];
                    }
                }
                $predictedList[] = $dataItem;
            }

            $data = [
                'city_list'      => $cityList,
                'predicted_list' => $predictedList
            ];
            $redis->set($key, json_encode($data));
        } else {
            $data = json_decode($data, true);
        }

        return $this->success($data);
    }

    public function getPredictedStr($predicted)
    {
        if ($predicted < 300) {
            $predictedStr = '低';
        } elseif ($predicted < 600) {
            $predictedStr = '中';
        } else {
            $predictedStr = '高';
        }
        return $predictedStr;
    }
}
