<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUserRenderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_public' => ['boolean'],
        ];
    }

    /**
     * Automated submissions fill the hidden field or post the form right after loading it.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (filled($this->input('website')) || ! $this->filledByPerson()) {
                    $validator->errors()->add('form', 'The form could not be submitted. Please reload the page and try again.');
                }
            },
        ];
    }

    private function filledByPerson(): bool
    {
        try {
            $openedAt = (int) decrypt((string) $this->input('form_token'));
        } catch (DecryptException) {
            return false;
        }

        $elapsed = now()->timestamp - $openedAt;

        return $elapsed >= config('user-renders.min_form_seconds') && $elapsed <= 86400;
    }
}
