<?php

namespace Manzano\CvdwCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

use Manzano\CvdwCli\Services\Objeto;
use Manzano\CvdwCli\Services\Log;
use Manzano\CvdwCli\Services\Console\CvdwSymfonyStyle;
use Manzano\CvdwCli\Services\Monitor\Eventos;
use Manzano\CvdwCli\Services\Ambientes;
use Manzano\CvdwCli\CvdwException;

#[AsCommand(
    name: 'executar',
    description: 'Configure o CVDW-CLI',
    hidden: false,
    aliases: ['Executar', 'execute', 'Execute']
)]
class Executar extends Command
{
    protected static $defaultName = 'executar';
    protected $dirLog = null;
    protected $arquivoLog = null;
    protected $logObjeto = false;
    protected InputInterface $input;
    protected OutputInterface $output;
    /**
     * @var string[]
     */
    public array $variaveisAmbiente = [];
    public bool $voltarProMenu = false;
    protected $eventosObj;
    protected $ambientesObj;
    protected $env = null;
    protected $evento = 'Executar';
    protected $qtd = 500;
    protected $apartir = null;
    
    const OPCAO_SAIR = 'Sair (CTRL+C)';

    protected function configure()
    {
        $this->setName('executar')
            ->setDescription('Executar o CVDW-CLI')
            ->addArgument('objeto', InputArgument::OPTIONAL, 'Qual objeto deseja executar')
            ->addOption(
                'ignorar-data-referencia', // Nome da opção
                'idr', // Atalho, pode ser NULL se não quiser um atalho
                InputOption::VALUE_NONE, // Modo: VALUE_REQUIRED, VALUE_OPTIONAL, VALUE_NONE
                'Ignorar a data de referência.',
            )
            ->addOption(
                'salvarlog', // Nome da opção
                'log', // Atalho, pode ser NULL se não quiser um atalho
                InputOption::VALUE_NONE, // Modo: VALUE_REQUIRED, VALUE_OPTIONAL, VALUE_NONE
                'Salvar Log da execução no diretorio de instalação.',
            )
            ->addOption(
                'setEnv', // Nome da opção
                'env', // Atalho, pode ser NULL se não quiser um atalho
                InputOption::VALUE_OPTIONAL, // Modo: VALUE_REQUIRED, VALUE_OPTIONAL, VALUE_NONE
                'Diz qual ENV usar. Exemplo: dev, homologacao, producao.',
            )->addOption(
                'setQtd', // Nome da opção
                'qtd', // Atalho, pode ser NULL se não quiser um atalho
                InputOption::VALUE_OPTIONAL, // Modo: VALUE_REQUIRED, VALUE_OPTIONAL, VALUE_NONE
                'Quantidade de dados retornada por cada requisicao.',
            )->addOption(
                'apartir', // Nome da opção
                'a', // Atalho, pode ser NULL se não quiser um atalho
                InputOption::VALUE_OPTIONAL, // Modo: VALUE_REQUIRED, VALUE_OPTIONAL, VALUE_NONE
                "Consultar a partir de uma data de referencia especifica.\n
                No formato: Y-m-d\TH:i:s ou Y-m-d.",
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->eventosObj = new Eventos();

        if ($input->getOption('setEnv')) {
            $this->env = $input->getOption('setEnv');
        }

        if ($input->getOption('setQtd')) {
            $this->qtd = $input->getOption('setQtd');
        }

        if($input->getOption('apartir')) {
            $this->apartir = trim($input->getOption('apartir'));
            if(!validarData($this->apartir)){
                throw new CvdwException('Data de referência informada ('.$this->apartir.') é inválida.');
            }
        }

        $this->ambientesObj = new Ambientes($this->env);
        $this->ambientesObj->retornarEnvs();

        if ($input->getOption('salvarlog')) {
            $this->arquivoLog = 'log_' . date('Y-m-d_H-i-s') . '.log';
            $this->logObjeto = new Log($this->arquivoLog);
            $this->logObjeto->criarArquivoLog();
        }
        $io = new CvdwSymfonyStyle($input, $output, $this->logObjeto);
        $this->limparTela();
        $this->validarConfiguracao($io);
        
        $this->input = $input;
        $this->output = $output;

        $inputObjeto = $input->getArgument('objeto');
        $inputDataReferencia = $input->getOption('ignorar-data-referencia');
        if ($inputObjeto) {
            $io->text(['Ambiente: ' . $_ENV['CV_URL'], '']);
            $this->executarObjeto($io, $inputObjeto, $inputDataReferencia);
            return Command::SUCCESS;
        }

        $io->title('Executando o CVDW-CLI');
        $ambienteAtivo = $this->ambientesObj->ambienteAtivo();
        $io->text('Ambiente ativo: ' . $ambienteAtivo);

        $this->eventosObj->registrarEvento($this->evento, 'Início');

        $this->variaveisAmbiente['executar'] = $io->choice('O que deseja fazer agora?', [
            'Listar todos os objetos disponíveis no CVDW-CLI',
            'Executar todos os objetos',
            'Executar um objeto especifico',
            $this::OPCAO_SAIR
        ]);

        if ($this->variaveisAmbiente['executar'] === $this::OPCAO_SAIR) {
            $io->text(['Até mais!', '']);
            return Command::SUCCESS;
        }
        $io->text(['Você escolheu: ' . $this->variaveisAmbiente['executar'], '']);
        $this->eventosObj->registrarEvento($this->evento, $this->variaveisAmbiente['executar']);
        $this->voltarProMenu = true;

        switch ($this->variaveisAmbiente['executar']) {
            case 'Listar todos os objetos disponíveis no CVDW-CLI':
                $this->exibirObjetos($io);
                break;
            case 'Executar todos os objetos':
                $this->executarObjeto($io, 'all', $inputDataReferencia);
                break;
            case 'Executar um objeto especifico':
                $this->executarObjetoOpcoes($io);
                break;
            default:
                $this->execute($this->input, $this->output);
                break;
        }

        return Command::SUCCESS;
    }

    protected function voltarProMenu($io)
    {
        if($this->voltarProMenu){
            if ($io->confirm('Vamos voltar pro menu anterior?', true)) {
                $this->limparTela();
                return $this->execute($this->input, $this->output);

            } else {
                return 0;
            }
        }
    }

    public function validarConfiguracao($io) : void
    {
        $envVars = $this->ambientesObj->getEnvEscope();
        // Listar todas as variáveis de $envVars e verificar se todas tem valor
        foreach ($envVars as $envVar => $value) {
            if (!isset($_ENV[$envVar]) || $_ENV[$envVar] == '') {
                $io->error('Configuração não encontrada, invalida ou incompleta. (' . $envVar . ')');
                $io->text(['Por favor use o comando "cvdw configurar" para configurar o CVDW-CLI.']);
                $io->text(['']);
                exit;
            }
        }
    }

    public function exibirObjetos($io)
    {
        
        $objetos = new Objeto($this->input, $this->output);
        $objetosArray = $objetos->retornarObjetos();
        $io->section('Objetos disponíveis: ');
        $table = new Table($this->output);
        $table->setHeaders(['Objeto', 'nome']);
        foreach ($objetosArray as $objeto => $dados) {
            $table->addRow([$objeto, $dados['nome']]);
        }
        $table->render();
        $this->voltarProMenu($io);
    }

    public function executarObjeto($io, $inputObjeto, $inputDataReferencia = false)
    {
        if($this->output->isDebug()) {
            $io->info('## Função: ' . __FUNCTION__);
        }

        if ($this->input->getOption('salvarlog')) {
            $io->text(['Salvando o Log em: ' . $this->arquivoLog]);
            $io->text(['']);
        }

        if ($inputObjeto) {
            if ($inputObjeto == 'all') {
                $io->text(['Executando todos os objetos...']);
                $objetos = new Objeto($this->input, $this->output);
                $objetosArray = $objetos->retornarObjetos();
            } else {
                $objetos = new Objeto($this->input, $this->output);
                $objetosArray = $objetos->retornarObjetos($inputObjeto);
                if (count($objetosArray) > 0) {
                    $io->text(['Executando objeto: ' . $inputObjeto]);
                } else {
                    $io->error('Objeto não encontrado.');
                    return Command::FAILURE;
                }
            }

            $objetoObj = new Objeto($this->input, $this->output);
            $cvdw = new \Manzano\CvdwCli\Services\Cvdw($this->input, $this->output);

            foreach ($objetosArray as $objeto => $dados) {
                $objeto = $objetoObj->retornarObjeto($objeto);
                $io->section($dados['nome']);
                $io->text('Executando objeto: ' . $dados['nome'] . '');
                $this->eventosObj->registrarEvento($this->evento, 'executar', $dados['nome']);
                
                $cvdw->processar($objeto, $this->qtd, $io, $this->apartir, $inputDataReferencia, $this->logObjeto);
            }
        } else {
            $io->error('Objeto não especificado.');
        }
        $this->voltarProMenu($io);
        return Command::SUCCESS;
    }

    public function executarObjetoOpcoes($io)
    {
        $objetos = new Objeto($this->input, $this->output);
        $objetosArray = $objetos->retornarObjetos();

        $objetosOpcoes = array();
        foreach ($objetosArray as $objeto => $dados) {
            $objetosOpcoes[] = $dados['nome'];
        }
        $objetosOpcoes[] =  $this::OPCAO_SAIR;
        $inputObjeto = $io->choice('Qual objeto deseja executar?', $objetosOpcoes);

        foreach ($objetosArray as $objeto => $dados) {
            if($dados['nome'] == $inputObjeto){
                $inputObjeto = $objeto;
            }
        }

        if($inputObjeto == $this::OPCAO_SAIR) {
            exit;
        }

        $this->limparTela();

        $this->executarObjeto($io, $inputObjeto);

    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {

        if ($input->mustSuggestOptionValuesFor('executar')) {
            // Sugestões para a opção 'role'
            $suggestions->suggestValues(['ROLE_USER', 'ROLE_ADMIN']);
        }
    }

    protected function limparTela() : void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Limpa a tela no Windows
            system('cls');
        } else {
            // Limpa a tela em sistemas Unix-like
            system('clear');
        }
    }
}
