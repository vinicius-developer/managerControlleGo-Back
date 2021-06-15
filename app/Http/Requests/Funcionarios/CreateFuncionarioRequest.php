<?php

namespace App\Http\Requests\Funcionarios;

use Illuminate\Foundation\Http\FormRequest;

class CreateFuncionarioRequest extends FormRequest
{
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
        return [
            'nome_funcionario' => ['required', 'max:100'],
            'empresa' => ['exists:empresas,id_empresa']
        ];
    }

    public function messages()
    {
        return [
            'nome_funcionario.required' => 'É necessário informar o campo nome',
            'nome_funcionario.max' => 'Este nome é muito longo',
            'empresa.exists' => 'Essa empresa não está cadastrada em nosso sistema'
        ];
    }
}
