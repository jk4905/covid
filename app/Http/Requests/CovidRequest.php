<?php

namespace App\Http\Requests;

class CovidRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        switch ($this->method()) {
            case 'GET':
            {
                return [
                    'country_code'  => ['required','string','nullable'],
                    'date'          => ['required','date_format:Y-m-d'],
                    'province_code' => ['nullable','string'],
                    'city'          => ['nullable','string']
                ];
            }
            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
            default:
            {
                return [];
            }
        }
    }

    public function attributes()
    {
        return [
            'country_code'  => '国家码',
            'province_code' => '省份码',
            'date'          => '日期',
            'city'          => '城市',
        ];
    }

    public function messages()
    {
        return [
            'country_code'=>'国家码必须填写',
            'province_code'=>'省份码必须填写'
        ];
    }
}
