<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Atestado;
use App\Traits\ApiCnaeCid;
use App\Models\CnaeEmpresa;
use App\Models\Funcionario;
use App\Traits\Authenticate;
use Illuminate\Http\Request;
use App\Traits\ResponseMessage;
use App\Models\RelacaoAtestadoCid;
use App\Models\RelacaoUsuarioEmpresa;
use App\Models\RelacaoAtestadoOcorrencia;
use App\Http\Requests\Atestado\AtestadoCreateRequest;
use App\Http\Requests\Atestado\CreateFileFuncionarioRequest;

class AtestadoController extends Controller
{
    use Authenticate, ResponseMessage, ApiCnaeCid;

    private $atestado;
    private $funcionario;
    private $cnaeEmpresa;
    private $relUserEmpre;
    private $relAtestadoCid;

    public function __construct()
    {
        $this->atestado = new Atestado();
        $this->funcionario = new Funcionario();
        $this->cnaeEmpresa = new CnaeEmpresa();
        $this->relAtestadoCid = new RelacaoAtestadoCid();
        $this->relUserEmpre = new RelacaoUsuarioEmpresa();
        $this->relacaoAtestadoOcorrencia = new RelacaoAtestadoOcorrencia();
    }

    public function create(AtestadoCreateRequest $request)
    {
        $token = $this->decodeToken($request);

        $cids = array_unique($request->codigo_cid);

        $cnaes = $this->cnaeEmpresa
            ->getCompanyCnaes($token->com)
            ->get()
            ->pluck('codigo_cnae')
            ->toArray();

        if (count($cids) !== 0) {
            $responseCidExists = $this->findCids($cids);
        }

        if (isset($responseCidExists) && !$responseCidExists->collect()['message']['exists']) {
            return response()->json([
                "message" => "The given data was invalid.",
                "errors"=> [
                    "cids" => [$responseCidExists->collect()['message']['cnae']]
                ]], 422);
        }

        try {

            $responseApi = $this->existsRelationInGroup($cnaes, $cids);

            $ocurrence =  $this->setHasOcurrence($responseApi);

            $id_atestado = $this->atestado->create([
                'crm_medico' => $request->crm_medico,
                'id_funcionario' => $request->funcionario,
                'data_lancamento' => $request->data_atestado,
                'termino_de_descanso' => $request->data_termino,
                'ocorrencia' => $ocurrence,
                'id_usuario' => $token->sub
            ])->id_atestado;

            $this->createRalationShipWithCids($id_atestado, $cids);

            if ($ocurrence) {
                $this->createRelacaoAtestadoOcorrencia(
                    $id_atestado,
                    $responseApi->collect()['message']['relationship']
                );
            }
        } catch (Exception $e) {


            return $this->formateMenssageError(
                'Não foi possível concluir a ação',
                500
            );
        }

        return $this->formateMenssageSuccess('Atestado criado com sucesso', 201);
    }

    public function treatOccurrence($id_occurrence)
    {
        try {

            $this->atestado
                ->where('id_atestado', $id_occurrence)
                ->update([
                    'tratado' => 1
                ]);
        } catch (Exception $e) {

            return $this->formateMenssageError('Não foi possível realizar a ação', 500);
        }

        return $this->formateMenssageSuccess('Ação concluida com sucesso');
    }

    public function getAllCertificateCompany(
        Request $request,
        string $year,
        string $employee
    ) {
        $token = $this->decodeToken($request);

        $certificates = $this->funcionario
            ->getAllCertificateYearAndEmployee($token->com, $year, $employee)
            ->orderBy('a.tratado', 'ASC')
            ->select(
                'funcionarios.id_funcionario',
                'a.id_atestado',
                'funcionarios.nome',
                'a.tratado',
                'a.crm_medico',
                'a.data_lancamento',
                'a.termino_de_descanso',
            )
            ->get();

        $data = [];
        $count = 0;

        foreach($certificates as $certificate) {
            $data[$count]['id_funcionario'] = $certificate->id_funcionari; 
            $data[$count]['id_atestado'] = $certificate->id_atestado; 
            $data[$count]['nome'] = $certificate->nome; 
            $data[$count]['tratado'] = $certificate->tratado; 
            $data[$count]['crm_medico'] = $certificate->crm_medico; 
            $data[$count]['data_lacamento'] = $certificate->data_lancamento; 
            $data[$count]['termino_de_descanso'] = $certificate->termino_de_descanso; 

            $cids = $this->relAtestadoCid
                ->getRegister($certificate->id_atestado)
                ->select('codigo_cid');
                
              
            $data[$count]['cids'] = $cids->get()->pluck('codigo_cid');
            $count++;
        }


        return $this->formateMenssageSuccess($data);
    }

    public function listOcurrence(Request $request)
    {
        $token = $this->decodeToken($request);

        $ocurrences = $this->funcionario
            ->getUntreatedCertificates($token->com)
            ->select(
                'a.id_atestado',
                'funcionarios.id_funcionario',
                'funcionarios.nome',
                'funcionarios.cargo',
                'a.data_lancamento',
                'a.termino_de_descanso',
            )
            ->paginate(10);

        return $this->formateMenssageSuccess($ocurrences);
    }

    public function getInfoOcurrence($id_occurrence)
    {
        $details = $this->relacaoAtestadoOcorrencia
            ->getInfoOcurrence($id_occurrence)
            ->select(
                'codigo_cid',
                'codigo_cnae'
            )
            ->get();

        return $this->formateMenssageSuccess($details);
    }

    public function countOccurrence(Request $request)
    {
        $token = $this->decodeToken($request);

        $ocurrences = $this->funcionario
            ->getUntreatedCertificates($token->com)
            ->count();

        return $this->formateMenssageSuccess([
            "ocorrencias" => $ocurrences
        ]);
    }

    public function getReportFile(CreateFileFuncionarioRequest $request)
    {
        $token = $this->decodeToken($request);

        $beginDate   = $request->begin_date;
        $finalDate   = $request->final_date;
        $idOfCompany = $token->com;

        $data = $this->atestado
            ->getReportFromDates(
                $beginDate, 
                $finalDate, 
                $idOfCompany
            )
            ->get()
            ->toArray();

        array_unshift($data, [
            'numero de identificação', 
            'nome', 
            'dias', 
            'cids', 
            'cargo', 
            'setor', 
            'data de nascimento'
        ]);

        $currentDate = $this->currentDate('Y-m-d');

        $nameFile = "{$idOfCompany}_{$beginDate}_{$finalDate}_{$currentDate}.csv";

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');

            foreach($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $this->getHeadersCsv($nameFile));
    }

    private function createRelacaoAtestadoOcorrencia(int $id_atestado, array $items)
    {
        foreach ($items as $item) {

            $this->relacaoAtestadoOcorrencia->create([
                'id_atestado' => $id_atestado,
                'codigo_cid' => $item['codigo_cid'],
                'codigo_cnae' => $item['codigo_cnae']
            ]);
        }
    }

    private function setHasOcurrence($responseApi)
    {
        if (isset($responseApi->collect()['message']['total'])) {
            return $responseApi->collect()['message']['total'];
        }

        return 0;
    }

    private function createRalationShipWithCids(int $id_atestado, array $cids): void
    {
        foreach ($cids as $cid) {
            $this->relAtestadoCid->create([
                'id_atestado' => $id_atestado,
                'codigo_cid' => $cid
            ]);
        }
    }
}
