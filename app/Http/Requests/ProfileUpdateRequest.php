<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['string', 'max:45'],
            'profession' => ['nullable', 'string', 'max:45'],
            'country' => ['nullable', 'string', 'max:45'],
            'city' => ['nullable', 'string', 'max:45'],
            'description' => ['nullable', 'string', 'max:1000'],
            'img' => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg', 'max:2048'],
        ];
    }
}