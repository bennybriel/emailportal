<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserinfoRequest extends FormRequest
{
    public function authorize()
    {
        return true; // middleware handles security
    }

    public function rules()
    {
        return [
            'matricno' => 'required|string|min:2|max:50',
            'session' => 'required|string|min:2|max:50',
            'programme'  => 'required|string|min:2|max:50',
            'email'  => 'required|email',
            
        ];
    }

    public function messages()
    {
        return [
           
            'matricno.required'  => 'matricno address is required',
            'email.required'     => 'Email is required',
            'programme.required' => 'programme is required',
            'session.required'   => 'session is required'
        ];
    }

    /**
     * Override failed validation to return JSON with all errors
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray(); // get all error messages

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $errors
        ], 422));
    }
}
