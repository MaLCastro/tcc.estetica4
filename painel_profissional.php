<?php
// Inicia a sessão do navegador para reconhecer a profissional que está acessando,
// mantendo seus dados de login disponíveis em todas as páginas do sistema.
session_start();

// Cria um token de segurança para validar ações enviadas por formulários.
if (empty($_SESSION["csrf_agendamentos"])) {
    $_SESSION["csrf_agendamentos"] = bin2hex(random_bytes(32));
}

// Inclui o arquivo de conexão com o banco de dados, que contém as configurações
// e instruções para conectar ao MySQL utilizando PDO de forma segura.
require_once("conexao.php");

/* ==============================================================================
   VERIFICAÇÃO DE SEGURANÇA — ACESSO EXCLUSIVO A PROFISSIONAIS
   ------------------------------------------------------------------------------
   Esta seção garante que apenas profissionais logadas consigam acessar esta página.
   Se alguém não estiver logada ou não for profissional, será enviada para o login.
   ============================================================================== */

// Verifica se existe um identificador de usuário na sessão (se a pessoa está logada).
// Se não existir, redireciona imediatamente para a página de login.
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

// Verifica se o tipo de usuário é exatamente "profissional". Se for cliente
// ou qualquer outro tipo, não tem permissão e é enviada para o login.
if (!isset($_SESSION["usuario_tipo"]) || $_SESSION["usuario_tipo"] !== "profissional") {
    header("Location: login.php");
    exit();
}

/* ==============================================================================
   PREPARAR DADOS DA PROFISSIONAL
   ------------------------------------------------------------------------------
   Recupera os dados básicos da profissional logada a partir da sessão,
   antes de consultar o banco para garantir que tudo esteja atualizado.
   ============================================================================== */

// Armazena o número de identificação única da profissional logada no sistema.
$id_profissional = $_SESSION["usuario_id"];

// Armazena o nome dela. Se por acaso o nome não estiver disponível na sessão,
// usa a palavra "Profissional" como valor padrão para não aparecer vazio.
$nome_profissional = $_SESSION["usuario_nome"] ?? "Profissional";

// Tenta capturar a especialidade da sessão. Se não estiver definida,
// mostra "Não informada" como aviso. Esta linha será substituída logo abaixo
// pelos dados mais recentes vindos diretamente do banco de dados.
$especialidade_profissional = $_SESSION["especialidade"] ?? "Não informada";

/* ==============================================================================
   BUSCAR DADOS ATUALIZADOS DIRETAMENTE DO BANCO DE DADOS
   ------------------------------------------------------------------------------
   Consulta o banco de dados para buscar as informações mais recentes da
   profissional, garantindo que o que aparece na tela esteja sempre correto
   e atualizado, mesmo que a sessão tenha ficado aberta por muito tempo.
   ============================================================================== */

// Inicia um bloco de proteção. Se qualquer erro acontecer durante a consulta,
// o sistema registra o erro no log em vez de travar a página na frente do usuário.
try {
    // Monta a consulta SQL que busca os dados da profissional.
    // Seleciona nome, e-mail, telefone, tipo e especialidade da tabela de usuários,
    // procurando pelo número de identificação e limitando a 1 resultado.
    $sql = "SELECT id, nome, email, telefone, tipo_usuario, especialidade
            FROM usuarios
            WHERE id = :id
            LIMIT 1";

    // Prepara a consulta para ser executada, medida de segurança contra injeção de código.
    $consulta = $conexao->prepare($sql);

    // Substitui o texto :id pelo número real da profissional logada.
    // PDO::PARAM_INT garante que o valor seja tratado como número inteiro.
    $consulta->bindValue(":id", $id_profissional, PDO::PARAM_INT);

    // Executa a consulta no banco de dados com os valores substituídos.
    $consulta->execute();

    // Transforma o resultado em um vetor organizado onde cada informação
    // pode ser acessada pelo nome da coluna (ex: $profissional["nome"]).
    $profissional = $consulta->fetch(PDO::FETCH_ASSOC);

    // Se a consulta não encontrou nenhum registro (ex: conta foi excluída),
    // encerra a sessão e manda a pessoa fazer login novamente.
    if (!$profissional) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Atualiza as variáveis com os dados MAIS RECENTES vindos do banco de dados.
    $nome_profissional = $profissional["nome"];
    $email_profissional = $profissional["email"];
    $telefone_profissional = $profissional["telefone"];

    // Atualiza a especialidade. Se vier vazia do banco, mantém o valor padrão.
    $especialidade_profissional = $profissional["especialidade"] ?? $especialidade_profissional;

    // Atualiza a sessão com os dados mais recentes, para que as outras páginas
    // também passem a usar as informações corretas sem precisar consultar de novo.
    $_SESSION["usuario_nome"] = $nome_profissional;
    $_SESSION["especialidade"] = $especialidade_profissional;

// Se acontecer qualquer erro na consulta (ex: conexão perdida, tabela alterada),
// registra o erro no log do servidor e continua a página com os dados da sessão.
} catch (PDOException $erro) {
    error_log("Erro dados profissional: " . $erro->getMessage());
}

/* ==============================================================================
   DEFINIR DATA ATUAL E FORMATAR PARA EXIBIÇÃO
   ------------------------------------------------------------------------------
   Prepara a data de hoje em diferentes formatos: um para usar em consultas
   no banco e outro para mostrar de forma amigável na tela.
   ============================================================================== */

// Define o fuso horário como Brasil, garantindo que a data e hora estejam corretas.
date_default_timezone_set("America/Sao_Paulo");

// Data atual no formato do banco de dados (Ano-Mês-Dia), usada nas comparações.
$data_atual_sql = date("Y-m-d");

// Data no formato brasileiro (Dia/Mês/Ano), usada para exibir na tela.
$data_br = date("d/m/Y");

// Obtém o nome do dia da semana em inglês, que será traduzido logo abaixo.
$dia_atual = date("l");

// Lista de tradução dos dias da semana do inglês para o português.
$dias_semana = [
    "Sunday" => "Domingo",
    "Monday" => "Segunda-feira",
    "Tuesday" => "Terça-feira",
    "Wednesday" => "Quarta-feira",
    "Thursday" => "Quinta-feira",
    "Friday" => "Sexta-feira",
    "Saturday" => "Sábado"
];

// Substitui o nome em inglês pelo equivalente em português. Se por acaso
// o nome não estiver na lista, mantém o texto original.
$dia_atual = $dias_semana[$dia_atual] ?? $dia_atual;

/* ==============================================================================
   BUSCAR AGENDAMENTOS DE HOJE E CALCULAR RESUMOS
   ------------------------------------------------------------------------------
   Consulta todos os agendamentos da profissional para o dia de hoje,
   conta quantos estão pendentes, quantos já foram concluídos, calcula o
   valor total dos serviços realizados e identifica o próximo atendimento.
   ============================================================================== */

// Inicia o contador total de agendamentos do dia com valor zero.
$agendamentos_hoje = 0;

// Inicia o contador de agendamentos que ainda vão acontecer com valor zero.
$agendamentos_pendentes = 0;

// Inicia o contador de atendimentos já realizados com valor zero.
$atendimentos_concluidos = 0;

// Inicia o acumulador de valor total dos serviços concluídos com valor zero.
$valor_dia = 0;

// Variável que guardará os dados do próximo atendimento. Começa vazia.
$proximo_atendimento = null;

// Inicia bloco de proteção contra erros na consulta.
try {
    // Monta a consulta que busca os agendamentos de hoje da profissional.
    // Junta dados do cliente (nome, telefone, e-mail) e do serviço escolhido.
    // Filtra SOMENTE os agendamentos da profissional logada e do dia de hoje.
    // Ordena do horário mais cedo para o mais tarde.
    $sql_ag = "SELECT a.horario, a.status, a.servico_id,
                      c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email,
                      s.nome_servico, a.preco_final AS preco
                 FROM agendamentos a
                 LEFT JOIN usuarios c ON a.cliente_id = c.id
                 LEFT JOIN servicos s ON a.servico_id = s.id
                WHERE a.profissional_id = :profissional_id
                  AND DATE(a.data_agendamento) = :data_hoje
             ORDER BY a.horario ASC";

    // Prepara a consulta para execução segura.
    $stmt_ag = $conexao->prepare($sql_ag);

    // Substitui o identificador da profissional pelo valor real.
    $stmt_ag->bindValue(":profissional_id", $id_profissional, PDO::PARAM_INT);

    // Substitui a data pela data de hoje no formato do banco.
    $stmt_ag->bindValue(":data_hoje", $data_atual_sql);

    // Executa a consulta com os valores já substituídos.
    $stmt_ag->execute();

    // Transforma todos os resultados em uma lista organizada para percorrer.
    $agendamentos = $stmt_ag->fetchAll(PDO::FETCH_ASSOC);

    // Percorre cada agendamento encontrado para contabilizar e classificar.
    foreach ($agendamentos as $ag) {
        // Soma mais um no total de agendamentos do dia.
        $agendamentos_hoje++;

        // Se o status for "pendente", soma no contador de pendentes
        // e guarda o PRIMEIRO encontrado como sendo o próximo atendimento.
        if ($ag["status"] === "pendente") {
            $agendamentos_pendentes++;
            if (!$proximo_atendimento) {
                $proximo_atendimento = $ag;
            }
        }

        // Se o status for "concluído", soma no contador de realizados
        // e adiciona o valor do serviço ao total do dia.
        if ($ag["status"] === "concluido") {
            $atendimentos_concluidos++;
            $valor_dia += (float)$ag["preco"];
        }
    }

// Se der erro na consulta, registra no log e continua a página com valores zerados.
} catch (PDOException $e) {
    error_log("Erro agendamentos: " . $e->getMessage());
}

/* ==============================================================================
   BUSCAR O PRÓXIMO AGENDAMENTO FUTURO
   ------------------------------------------------------------------------------
   Esta consulta é separada dos indicadores de hoje para que o painel encontre
   o próximo atendimento pendente mesmo quando ele estiver marcado para outro dia.
   ============================================================================== */

// Guarda a data e a hora atuais para filtrar atendimentos que ainda vão acontecer.
$data_hora_atual = date("H:i:s");

// Inicia uma nova consulta protegida para localizar o atendimento mais próximo.
try {
    // Busca o primeiro agendamento pendente a partir de hoje.
    // Para hoje, considera apenas horários iguais ou posteriores à hora atual.
    // Para os próximos dias, considera todos os horários e ordena por data e hora.
    $sql_proximo = "SELECT a.id AS agendamento_id,
                           DATE(a.data_agendamento) AS data_agendamento,
                           a.horario, a.status, a.servico_id,
                           c.nome AS cliente_nome,
                           c.telefone AS cliente_telefone,
                           c.email AS cliente_email,
                           s.nome_servico, a.preco_final AS preco
                      FROM agendamentos a
                      LEFT JOIN usuarios c ON a.cliente_id = c.id
                      LEFT JOIN servicos s ON a.servico_id = s.id
                     WHERE a.profissional_id = :profissional_id
                       AND a.status = 'pendente'
                       AND DATE(a.data_agendamento) >= :data_hoje
                       AND (
                            DATE(a.data_agendamento) > :data_hoje_futura
                            OR a.horario >= :hora_atual
                       )
                  ORDER BY DATE(a.data_agendamento) ASC, a.horario ASC
                     LIMIT 1";

    // Prepara a consulta para evitar inserir valores diretamente no SQL.
    $stmt_proximo = $conexao->prepare($sql_proximo);

    // Informa a profissional autenticada e os limites de data e horário.
    $stmt_proximo->bindValue(":profissional_id", $id_profissional, PDO::PARAM_INT);
    $stmt_proximo->bindValue(":data_hoje", $data_atual_sql);
    $stmt_proximo->bindValue(":data_hoje_futura", $data_atual_sql);
    $stmt_proximo->bindValue(":hora_atual", $data_hora_atual);

    // Executa e recupera somente o atendimento futuro mais próximo.
    $stmt_proximo->execute();
    $proximo_atendimento = $stmt_proximo->fetch(PDO::FETCH_ASSOC) ?: null;

} catch (PDOException $e) {
    // Registra falhas no log sem interromper a exibição do painel.
    error_log("Erro ao buscar próximo agendamento: " . $e->getMessage());
    $proximo_atendimento = null;
}

/* ==============================================================================
   CARREGAR HORÁRIOS DE TRABALHO DA PROFISSIONAL
   ------------------------------------------------------------------------------
   Busca os horários padrão de atendimento que a profissional cadastrou.
   Se não houver cadastro, usa horários padrão como sugestão.
   ============================================================================== */

// Define horários padrão como sugestão inicial, caso não haja cadastro salvo.
$inicio_padrao = "08:00";
$ultimo_inicio = "17:00";
$fim_expediente = "19:00";
$inicio_almoco = "12:00";
$fim_almoco = "13:00";

// Inicia bloco de proteção contra erros na consulta.
try {
    // Consulta os horários cadastrados da profissional.
    $sql_hr = "SELECT horario_inicio, ultimo_horario,
                      inicio_almoco, fim_almoco
                 FROM horarios
                WHERE profissional_id = :pid
                LIMIT 1";

    // Prepara e substitui o identificador da profissional.
    $stmt_hr = $conexao->prepare($sql_hr);
    $stmt_hr->bindValue(":pid", $id_profissional, PDO::PARAM_INT);
    $stmt_hr->execute();

    // Busca o resultado da consulta.
    $hr = $stmt_hr->fetch(PDO::FETCH_ASSOC);

    // Se encontrou horários cadastrados, substitui os valores padrão pelos salvos.
    if ($hr) {
        $inicio_padrao = $hr["horario_inicio"] ?? $inicio_padrao;
        $ultimo_inicio = $hr["ultimo_horario"] ?? $ultimo_inicio;
        // Calcula o horário de encerramento substituindo o número inicial por 9
        // (ex: se começa às 08, encerra às 19).
        $fim_expediente = $hr["horario_inicio"] ? substr_replace($hr["horario_inicio"], '9', 0, 2) : $fim_expediente;
        $inicio_almoco = $hr["inicio_almoco"] ?? $inicio_almoco;
        $fim_almoco = $hr["fim_almoco"] ?? $fim_almoco;
    }

// Se der erro na consulta, mantém os horários padrão e registra o problema.
} catch (PDOException $e) {
    error_log("Erro horários: " . $e->getMessage());
}

/* ==============================================================================
   PREPARAR NOME EXIBIDO NA TELA
   ------------------------------------------------------------------------------
   Extrai apenas o PRIMEIRO nome da profissional para a saudação ficar mais
   amigável e não ocupar muito espaço na tela.
   ============================================================================== */

// Separa o nome completo nos espaços e pega o primeiro pedaço.
$primeiro_nome = explode(" ", trim($nome_profissional))[0];

// Variável reservada para uso futuro (contagem de serviços cadastrados).
// Ainda não está sendo utilizada nesta versão da página.
$total_servicos = 0;
?>

<!-- ==============================================================================
     ESTRUTURA VISUAL DA PÁGINA — CÓDIGO HTML
     ============================================================================== -->

<!-- Declara que este documento segue o padrão HTML5 -->
<!DOCTYPE html>

<!-- Define o idioma da página como Português do Brasil -->
<html lang="pt-BR">
<head>
    <!-- Define a codificação de caracteres para aceitar acentuação e símbolos brasileiros -->
    <meta charset="UTF-8">

    <!-- Faz o site se adaptar corretamente a diferentes tamanhos de tela -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define o nome que aparece na aba do navegador -->
    <title>Painel Profissional | TCC Estética</title>

    <!-- Conecta o arquivo de estilos que define cores, fontes e formato visual -->
    <link rel="stylesheet" href="style.css">
<style>
/* Botões novos da área do próximo atendimento, seguindo a paleta do sistema. */
.btn-concluir-agendamento {
    border: 0;
    border-radius: 9px;
    padding: 11px 17px;
    background: #704B42;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}
.btn-concluir-agendamento:hover { background: #5A3434; }
.btn-ver-atendimentos {
    color: #8F4054;
    font-weight: 600;
    text-decoration: none;
}
.btn-ver-atendimentos:hover { text-decoration: underline; }
</style>
</head>
<body>

<!-- ==============================================================================
     MENU LATERAL FIXO — NAVEGAÇÃO ENTRE AS PÁGINAS
     ============================================================================== -->
<aside class="sidebar">
    <!-- Área que mostra o nome do sistema e a especialidade da profissional -->
    <div class="logo">
        <h1>Estética</h1>
        <!-- Exibe a especialidade salva no cadastro ou "Profissional" se não definida -->
        <?php $especialidade = htmlspecialchars($_SESSION["especialidade"] ?? "Profissional"); ?>
        <p><?= $especialidade ?></p>
    </div>

    <!-- Links de navegação entre as páginas do painel -->
    <!-- A classe "ativo" é adicionada automaticamente na página que está aberta -->
    <nav class="menu">
        <a href="painel_profissional.php" class="<?= basename($_SERVER['PHP_SELF']) === 'painel_profissional.php' ? 'ativo' : '' ?>">Início</a>
        <a href="atendimentos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'atendimentos.php' ? 'ativo' : '' ?>">Atendimentos</a>
        <a href="minha_agenda.php" class="<?= basename($_SERVER['PHP_SELF']) === 'minha_agenda.php' ? 'ativo' : '' ?>">Minha Agenda</a>
        <a href="meus_servicos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'meus_servicos.php' ? 'ativo' : '' ?>">Meus Serviços</a>
        <div class="separador"></div>
        <a href="meu_perfil.php" class="<?= basename($_SERVER['PHP_SELF']) === 'meu_perfil.php' ? 'ativo' : '' ?>">Meu Perfil</a>
    </nav>

    <!-- Botão para encerrar a sessão e sair do sistema -->
    <a href="logout.php" class="btn-sair">Sair</a>
</aside>

<!-- Área principal de conteúdo da página -->
<main class="main">
    <!-- Cabeçalho com saudação personalizada e data atual -->
    <section class="topo">
        <div>
            <!-- Saudação usando apenas o primeiro nome da profissional -->
            <h2>Olá, <?php echo htmlspecialchars($primeiro_nome); ?>! </h2>
            <p>Aqui você pode acompanhar e administrar sua rotina profissional.</p>
        </div>
        <!-- Mostra o nome do dia e a data no formato brasileiro -->
        <div class="data"><?php echo $dia_atual; ?>, <?php echo $data_br; ?></div>
    </section>

    <!-- ======================================================================
         QUADROS RESUMOS — NÚMEROS DO DIA
         ====================================================================== -->
    <section class="cards">
        <!-- Quadro com total de agendamentos previstos para hoje -->
        <div class="card">
            <div class="titulo">Agendamentos de hoje</div>
            <div class="numero"><?php echo $agendamentos_hoje; ?></div>
            <div class="descricao">atendimentos previstos</div>
        </div>
        <!-- Quadro com quantos ainda estão aguardando atendimento -->
        <div class="card">
            <div class="titulo">Pendentes</div>
            <div class="numero"><?php echo $agendamentos_pendentes; ?></div>
            <div class="descricao">aguardando atendimento</div>
        </div>
        <!-- Quadro com quantos já foram concluídos hoje -->
        <div class="card">
            <div class="titulo">Atendimentos concluídos</div>
            <div class="numero"><?php echo $atendimentos_concluidos; ?></div>
            <div class="descricao">realizados hoje</div>
        </div>
        <!-- Quadro com valor total em dinheiro dos serviços concluídos -->
        <div class="card">
            <div class="titulo">Valor do dia</div>
            <div class="numero">R$ <?php echo number_format($valor_dia, 2, ',', '.'); ?></div>
            <div class="descricao">serviços concluídos</div>
        </div>
    </section>

    <!-- ======================================================================
         ÁREA DE INFORMAÇÕES: PRÓXIMO ATENDIMENTO E HORÁRIOS
         ====================================================================== -->
    <section class="grid">
        <!-- Caixa com os dados do próximo atendimento a ser realizado -->
        <div class="box">
            <h3>Próximo atendimento</h3>
            <div class="proximo">
                <!-- Se houver atendimento pendente, mostra os dados da cliente -->
                <?php if ($proximo_atendimento): ?>
                    <!-- Mostra a data do próximo atendimento, inclusive quando for em outro dia. -->
                    <div class="informacao">
                        Data: <?php echo date("d/m/Y", strtotime($proximo_atendimento["data_agendamento"])); ?>
                    </div>
                    <div class="horario"><?php echo htmlspecialchars($proximo_atendimento["horario"]); ?></div>
                    <div class="cliente"><?php echo htmlspecialchars($proximo_atendimento["cliente_nome"] ?? "Cliente não identificada"); ?></div>
                    <div class="informacao">Serviço: <?php echo htmlspecialchars($proximo_atendimento["nome_servico"]); ?></div>
                    <div class="informacao">Telefone: <?php echo htmlspecialchars($proximo_atendimento["cliente_telefone"] ?? '--'); ?></div>
                    <div class="informacao">E-mail: <?php echo htmlspecialchars($proximo_atendimento["cliente_email"] ?? '--'); ?></div>
                    <div><span class="status">Próximo atendimento</span></div>

                    <!-- Permite registrar a conclusão do atendimento atual e carregar o próximo. -->
                    <form action="atualizar_status_agendamento.php" method="POST"
                          onsubmit="return confirm('Confirma que este atendimento foi realizado?');"
                          style="margin-top: 16px;">
                        <input type="hidden" name="csrf_token"
                               value="<?php echo htmlspecialchars($_SESSION['csrf_agendamentos']); ?>">
                        <input type="hidden" name="agendamento_id"
                               value="<?php echo (int)$proximo_atendimento['agendamento_id']; ?>">
                        <input type="hidden" name="novo_status" value="concluido">
                        <input type="hidden" name="origem" value="painel_profissional.php">
                        <button type="submit" class="btn-concluir-agendamento">
                            Concluir atendimento
                        </button>
                    </form>

                    <!-- Atalho para consultar todos os agendamentos e seus status. -->
                    <a href="atendimentos.php" class="btn-ver-atendimentos"
                       style="display:inline-block; margin-top:10px;">
                        Ver todos os atendimentos
                    </a>
                <!-- Se não houver atendimento, mostra mensagem explicativa -->
                <?php else: ?>
                    <div class="horario">--</div>
                    <div class="cliente">Nenhum atendimento agendado</div>
                    <div class="informacao">Quando houver um agendamento, os dados da cliente aparecerão aqui.</div>
                    <div class="informacao">Serviço: --</div>
                    <div class="informacao">Telefone: --</div>
                    <div class="informacao">E-mail: --</div>
                    <div><span class="status">Sem atendimento</span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Caixa com os horários padrão de trabalho da profissional -->
        <div class="box">
            <h3>Minha rotina</h3>
            <div class="horario-item"><strong>Início</strong> <span><?php echo $inicio_padrao; ?></span></div>
            <div class="horario-item"><strong>Último início</strong> <span><?php echo $ultimo_inicio; ?></span></div>
            <div class="horario-item"><strong>Encerramento</strong> <span><?php echo $fim_expediente; ?></span></div>
            <div class="horario-item"><strong>Almoço</strong> <span><?php echo $inicio_almoco; ?> - <?php echo $fim_almoco; ?></span></div>
            <div class="horario-item"><strong>Folgas</strong> <span>Definidas em Minha Agenda</span></div>
        </div>
    </section>

    <!-- ======================================================================
         ATALHOS RÁPIDOS PARA AS PRINCIPAIS FUNÇÕES
         ====================================================================== -->
    <section class="box atalhos-box">
        <h3>Gerenciar minha rotina</h3>
        <div class="atalhos">
            <!-- Link direto para cadastrar e alterar serviços -->
            <a href="meus_servicos.php" class="atalho">
                <div class="icone"></div>
                <strong>Meus Serviços</strong>
                <span>Altere preços, duração e disponibilidade.</span>
            </a>
            <!-- Link direto para configurar horários de trabalho -->
            <a href="minha_agenda.php?aba=horarios" class="atalho">
                <div class="icone"></div>
                <strong>Meus Horários</strong>
                <span>Configure dias e horários de trabalho.</span>
            </a>
            <!-- Link direto para registrar folgas e feriados -->
            <a href="minha_agenda.php?aba=bloqueios" class="atalho">
                <div class="icone"></div>
                <strong>Folgas e Bloqueios</strong>
                <span>Bloqueie datas e períodos.</span>
            </a>
            <!-- Link direto para visualizar a agenda completa -->
            <a href="minha_agenda.php" class="atalho">
                <div class="icone"></div>
                <strong>Minha Agenda</strong>
                <span>Visualize agendamentos, horários e folgas.</span>
            </a>
            <!-- Link direto para consultar histórico -->
            <a href="atendimentos.php" class="atalho">
                <div class="icone"></div>
                <strong>Histórico</strong>
                <span>Consulte atendimentos realizados.</span>
            </a>
            <!-- Link direto para alterar dados cadastrais -->
            <a href="meu_perfil.php" class="atalho">
                <div class="icone"></div>
                <strong>Meu Perfil</strong>
                <span>Atualize seus dados.</span>
            </a>
        </div>
    </section>

    <!-- Aviso explicativo sobre de onde vêm os horários exibidos -->
    <div class="aviso">
        <strong>Configuração inicial:</strong>
        Os horários estão sendo carregados da tabela <em>horarios</em> quando disponíveis;
        caso contrário, usam o padrão 08:00 às 19:00 com almoço 12:00–13:00.<br>
        <em>Altere seus horários e folgas diretamente em Minha Agenda → aba Meus Horários.</em>
    </div>
</main>
</body>
</html>