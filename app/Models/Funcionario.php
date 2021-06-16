<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Funcionario extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $primaryKey = 'id_funcionario';

    protected $fillable = [
        'nome',
        'id_empresa',
        'id_usuario'
    ];

    public function getFuncId($id)
    {
        return $this->where('id_funcionario', $id)->get();
    }
}
