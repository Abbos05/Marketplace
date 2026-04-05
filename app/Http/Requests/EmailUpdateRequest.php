<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmailUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'email' => ['required', 'email', 'max:60', 'unique:users,email,' . $this->user()->id],
        ];
    }
}