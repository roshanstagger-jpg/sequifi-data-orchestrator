<?php
declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveFieldsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'job_key_column' => ['required', 'string'],
            'watched_fields' => ['required', 'array', 'min:1'],
            'watched_fields.*' => ['required', 'string'],
        ];
    }
}
