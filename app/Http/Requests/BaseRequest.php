<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BaseRequest extends FormRequest
{
    public function authorize()
    {
        //false代表权限验证不通过，返回403错误
        //true代表权限认证通过
        return true;
    }
}
