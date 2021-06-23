<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RelacaoUsuarioEmpresa extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_relacao_usuario_empresa';

    protected $fillable = [
        'id_empresa',
        'id_usuario'
    ];

    public function getUserEmpre($id_user)
    {
       return $this->where('id_usuario', $id_user)->value('id_empresa');
    }

    public function consultUser($id_company)
    {
        return $this->where('id_empresa', $id_company)->get('id_usuario');
    }

    public function getReUserEmpre($id_user)
    {
        return $this->where('id_usuario', $id_user)->get();
    }

    public function getRelationShip($id_user, $id_company)
    {
        return $this->where('id_usuario', $id_user)
            ->where('id_empresa', $id_company);
    }

}
