<?php
namespace Tests\ApiCVDW;

use Tests\Support\ApiTester;
use Tests\Helper\CvdwHelper;
use Tests\ApiCVDW\Common;
use Codeception\Util\HttpCode;
use PHPUnit\Framework\Assert;

class PessoasContatosCest extends Common
{
    public function getPessoasContatos(ApiTester $I)
    {
        
        sleep(2);

        $bodyContent = ['pagina' => 1, 'registros' => 1];
        $responseContent = [
            'pagina' => 'integer',
            'registros' => 'integer',
            'total_de_registros' => 'integer',
            'total_de_paginas' => 'integer',
            'dados' => 'array'
        ];

        $I->sendGet('/pessoas/contatos', $bodyContent);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType($responseContent);

        $primeiraLinhaDados = $I->grabDataFromResponseByJsonPath('$.dados[0]');
        codecept_debug("Referência do primeiro item: " . $primeiraLinhaDados[0]['referencia']);
        if(is_array($primeiraLinhaDados[0])){
            $referencia_data = $I->grabDataFromResponseByJsonPath('$.dados[0].referencia_data');
            codecept_debug("Data do primeiro item: " . $referencia_data[0]);
            $I->validarFormatoDaData($referencia_data[0], 'Y-m-d H:i:s');
        }
        // Estrutura de 'dados[0]'
        /*
        $I->seeResponseMatchesJsonType([
            'referencia' => 'string',
            'idpessoa_int' => 'string|null',
            'idpessoa' => 'integer|null',
            'email' => 'string|null',
            'telefone' => 'string|null',
            'celular' => 'string|null',
            'referencia_nome' => 'string|null',
            'referencia_telefone' => 'string|null',
            'referencia_parentesco' => 'string|null',
            'cep_contato_relacionamento' => 'string|null',
            'endereco_contato_relacionamento' => 'string|null',
            'bairro_contato_relacionamento' => 'string|null',
            'numero_contato_relacionamento' => 'string|null',
            'complemento_contato_relacionamento' => 'string|null',
            'estado_contato_relacionamento' => 'string|null',
            'cidade_contato_relacionamento' => 'string|null',
            'pais_contato_relacionamento' => 'string|null',
            'telefone_contato_relacionamento' => 'string|null',
            'celular_contato_relacionamento' => 'string|null',
            'email_contato_relacionamento' => 'string|null',
            'nome_representante_pj' => 'string|null',
            'documento_representante_pj' => 'string|null',
            'cargo_pj' => 'string|null',
            'email_relacionamento_pj' => 'string|null',
            'telefone_relacionamento_pj' => 'string|null',
            'genero_representante' => 'string|null'
        ], '$.dados[0]');
        */

    }
}