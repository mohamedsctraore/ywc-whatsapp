<?php

namespace App\Yantrana\Components\Auth\Requests;

use App\Yantrana\Base\BaseRequest;

class RegisterRequest extends BaseRequest
{
    /**
     * Secure form
     *------------------------------------------------------------------------ */
    protected $securedForm = true;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'email' => 'required|string|email|unique:users,email' . (getAppSettings('disallow_disposable_emails') ? '|indisposable' : ''),
            'password' => 'required|string|confirmed|min:8',
            'username' => 'required|string|unique:users|alpha_dash|min:2|max:45|unique:users,username',
            'vendor_title' => 'required|string|min:2|max:100',
            'first_name' => 'required|string|min:1|max:45',
            'last_name' => 'required|string|min:1|max:45',
        ];

        if (getAppSettings('user_terms') or getAppSettings('vendor_terms') or getAppSettings('privacy_policy')) {
            $rules['terms_and_conditions'] = 'accepted';
        }

        return $rules;
    }
}
