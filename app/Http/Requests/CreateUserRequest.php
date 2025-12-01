<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateUserRequest extends FormRequest
{
    public function authorize()
    {
        return true; // middleware handles security
    }

    public function rules()
    {
        return [
            'firstname' => 'required|string|min:2|max:50',
            'othername' => 'required|string|min:2|max:50',
            'lastname'  => 'required|string|min:2|max:50',
            'matricno'  => 'required|string|min:2|max:20',
            'domain'    => 'required|string|min:2|max:50',
            'password'  => 'required|string|min:2|max:50',
            'programme'  => 'required|string|min:2|max:50',
            'session'   => 'required|string|min:2|max:50',
        ];
    }

    public function messages()
    {
        return [
            'firstname.required' => 'First name is required',
            'othername.required' => 'Other name is required',
            'lastname.required'  => 'Last name is required',
            'matricno.required'  => 'matricno address is required',
            'password.required'  => 'Password is required',
            'programme.required' => 'programme is required',
            'session.required'   => 'session is required',
            'domain.required'    => 'Primary email domain is required (student.lautech.edu.ng or pgschool.lautech.edu.ng)',
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
