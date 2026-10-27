
<?php

// ============================================================
// INICIAR SESSÃO E CONEXÃO
// ============================================================

// Inicia a sessão para reconhecer a cliente logada.
session_start();

// Inclui a conexão com o banco de dados.
require_once("conexao.php");

// Define o fuso horário brasileiro.
date_default_timezone_set("America/Sao_Paulo");


// ============================================================
// VERIFICAÇÃO DE SEGURANÇA
// ============================================================

// Permite o acesso somente a usuários autenticados.
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

// Permite o acesso somente às clientes.
if (
    !isset($_SESSION["usuario_tipo"]) ||
    $_SESSION["usuario_tipo"] !== "cliente"
) {
    header("Location: login.php");
    exit();
}


// ============================================================
// DADOS DA CLIENTE
// ============================================================

// Recupera o ID e o nome da cliente.
$cliente_id = (int) $_SESSION["usuario_id"];
$nome_cliente = $_SESSION["usuario_nome"] ?? "Cliente";

// Separa o primeiro nome para a saudação.
$partes_nome = explode(" ", trim($nome_cliente));
$primeiro_nome = $partes_nome[0] ?? "Cliente";


// ============================================================
// DEFINIR A SEÇÃO ATUAL
// ============================================================

// Define a seção exibida no painel.
$secao = $_GET["secao"] ?? "inicio";

// Lista as seções permitidas.
$secoes_permitidas = [
    "inicio",
    "servicos",
    "agendamentos",
    "fidelidade"
];

// Impede o acesso a seções inexistentes.
if (!in_array($secao, $secoes_permitidas, true)) {
    $secao = "inicio";
}


// ============================================================
// CATEGORIAS DOS SERVIÇOS
// ============================================================

// Recupera a categoria selecionada.
$categoria = $_GET["categoria"] ?? "";

// Define as categorias aceitas.
$categorias_permitidas = [
    "nail",
    "lash",
    "eyebrow"
];

// Valida a categoria.
if (!in_array($categoria, $categorias_permitidas, true)) {
    $categoria = "";
}

// Traduz as especialidades para exibição.
$nomes_categorias = [
    "nail" => "Unhas",
    "lash" => "Cílios",
    "eyebrow" => "Sobrancelhas"
];


// ============================================================
// BUSCAR DADOS ATUAIS DA CLIENTE
// ============================================================

// Define valores iniciais para evitar variáveis inexistentes.
$cliente = [
    "nome" => $nome_cliente,
    "email" => "",
    "telefone" => ""
];

try {

    // Busca os dados da cliente no banco.
    $sql_cliente = "SELECT nome, email, telefone
                    FROM usuarios
                    WHERE id = :id
                      AND tipo_usuario = 'cliente'
                    LIMIT 1";

    $stmt_cliente = $conexao->prepare($sql_cliente);

    $stmt_cliente->bindValue(
        ":id",
        $cliente_id,
        PDO::PARAM_INT
    );

    $stmt_cliente->execute();

    $dados_cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    // Atualiza os dados se a cliente foi encontrada.
    if ($dados_cliente) {

        $cliente = $dados_cliente;

        $_SESSION["usuario_nome"] = $cliente["nome"];

        $partes_nome = explode(" ", trim($cliente["nome"]));
        $primeiro_nome = $partes_nome[0] ?? "Cliente";
    }

} catch (PDOException $erro) {

    // Registra o erro sem exibir informações técnicas na página.
    error_log(
        "Erro ao buscar dados da cliente: " .
        $erro->getMessage()
    );
}


// ============================================================
// BUSCAR O PRÓXIMO AGENDAMENTO
// ============================================================

// Começa sem um próximo agendamento.
$proximo_agendamento = null;

try {

    // Busca o próximo atendimento pendente para esta cliente.
    // A data utilizada é data_agendamento, conforme o banco atual.
    $sql_proximo = "SELECT
                        a.id,
                        a.data_agendamento,
                        a.horario,
                        a.status,
                        s.nome_servico,
                        s.preco,
                        s.duracao,
                        u.nome AS profissional_nome,
                        u.especialidade
                    FROM agendamentos a

                    LEFT JOIN servicos s
                        ON a.servico_id = s.id

                    LEFT JOIN usuarios u
                        ON a.profissional_id = u.id

                    WHERE a.cliente_id = :cliente_id
                      AND a.data_agendamento >= CURDATE()
                      AND a.status = 'pendente'

                    ORDER BY
                        a.data_agendamento ASC,
                        a.horario ASC

                    LIMIT 1";

    $stmt_proximo = $conexao->prepare($sql_proximo);

    $stmt_proximo->bindValue(
        ":cliente_id",
        $cliente_id,
        PDO::PARAM_INT
    );

    $stmt_proximo->execute();

    $proximo_agendamento = $stmt_proximo->fetch(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $erro) {

    // Registra falhas na consulta do próximo atendimento.
    error_log(
        "Erro ao buscar próximo agendamento: " .
        $erro->getMessage()
    );
}


// ============================================================
// BUSCAR TODOS OS AGENDAMENTOS DA CLIENTE
// ============================================================

// Inicializa a lista de agendamentos.
$agendamentos_cliente = [];

try {

    // Recupera os agendamentos e os dados relacionados.
    $sql_agendamentos = "SELECT
                            a.id,
                            a.data_agendamento,
                            a.horario,
                            a.status,
                            s.nome_servico,
                            s.preco,
                            s.duracao,
                            u.nome AS profissional_nome,
                            u.especialidade
                         FROM agendamentos a

                         LEFT JOIN servicos s
                            ON a.servico_id = s.id

                         LEFT JOIN usuarios u
                            ON a.profissional_id = u.id

                         WHERE a.cliente_id = :cliente_id

                         ORDER BY
                            a.data_agendamento DESC,
                            a.horario DESC";

    $stmt_agendamentos = $conexao->prepare(
        $sql_agendamentos
    );

    $stmt_agendamentos->bindValue(
        ":cliente_id",
        $cliente_id,
        PDO::PARAM_INT
    );

    $stmt_agendamentos->execute();

    $agendamentos_cliente = $stmt_agendamentos->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $erro) {

    // Registra falhas na consulta do histórico.
    error_log(
        "Erro ao buscar agendamentos da cliente: " .
        $erro->getMessage()
    );
}


// ============================================================
// BUSCAR PROGRESSO DO PROGRAMA DE FIDELIDADE
// ============================================================

// Inicializa a lista de profissionais e contagens.
$fidelidade_profissionais = [];

try {

    // Conta os atendimentos concluídos da cliente com cada profissional.
    // Os atendimentos pendentes e cancelados não são contabilizados.
    $sql_fidelidade = "SELECT
                            u.id AS profissional_id,
                            u.nome AS profissional_nome,
                            u.especialidade,
                            COUNT(a.id) AS total_atendimentos

                       FROM usuarios u

                       LEFT JOIN agendamentos a
                           ON a.profissional_id = u.id
                           AND a.cliente_id = :cliente_id
                           AND a.status = 'concluido'

                       WHERE u.tipo_usuario = 'profissional'
                         AND u.status = 'ativo'

                       GROUP BY
                           u.id,
                           u.nome,
                           u.especialidade

                       ORDER BY u.nome ASC";

    $stmt_fidelidade = $conexao->prepare(
        $sql_fidelidade
    );

    $stmt_fidelidade->bindValue(
        ":cliente_id",
        $cliente_id,
        PDO::PARAM_INT
    );

    $stmt_fidelidade->execute();

    $fidelidade_profissionais = $stmt_fidelidade->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $erro) {

    // Registra falhas na consulta de fidelidade.
    error_log(
        "Erro ao buscar fidelidade da cliente: " .
        $erro->getMessage()
    );
}


// ============================================================
// BUSCAR SERVIÇOS DISPONÍVEIS
// ============================================================

// Inicializa a lista de serviços.
$servicos = [];

try {

    // Relaciona serviços, vinculações e profissionais.
    $sql_servicos = "SELECT
                        s.id,
                        s.nome_servico,
                        s.tipo_servico,
                        s.descricao,
                        s.preco,
                        s.duracao,
                        s.status,

                        u.id AS profissional_id,
                        u.nome AS profissional_nome,
                        u.especialidade AS profissional_especialidade

                     FROM servicos s

                     INNER JOIN vinculacao v
                        ON v.servico_id = s.id

                     INNER JOIN usuarios u
                        ON u.id = v.profissional_id

                     WHERE s.status = 'ativo'
                       AND u.tipo_usuario = 'profissional'
                       AND u.status = 'ativo'

                     ORDER BY
                        s.tipo_servico ASC,
                        s.nome_servico ASC";

    $stmt_servicos = $conexao->prepare($sql_servicos);

    $stmt_servicos->execute();

    $servicos = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $erro) {

    // Registra falhas na consulta dos serviços.
    error_log(
        "Erro ao buscar serviços: " .
        $erro->getMessage()
    );
}


// ============================================================
// FILTRAR SERVIÇOS POR CATEGORIA
// ============================================================

// Inicializa a lista filtrada.
$servicos_categoria = [];

// Filtra somente quando existe uma categoria selecionada.
if ($categoria !== "") {

    foreach ($servicos as $servico) {

        // Mantém apenas serviços da especialidade escolhida.
        if (
            $servico["profissional_especialidade"] === $categoria
        ) {
            $servicos_categoria[] = $servico;
        }
    }
}


// ============================================================
// DATA ATUAL
// ============================================================

// Formata a data para exibição.
$data_atual = date("d/m/Y");

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <!-- Define a codificação dos caracteres. -->
    <meta charset="UTF-8">

    <!-- Permite a adaptação da página a celulares e tablets. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define o título da página. -->
    <title>Painel do Cliente | TCC Estética</title>

    <!-- Carrega o arquivo de estilos principal. -->
    <link rel="stylesheet" href="style.css">

</head>

<body class="pagina-cliente">


<!-- ============================================================
     CABEÇALHO DO PAINEL
     ============================================================ -->

<header class="cabecalho-cliente">

    <div class="logo-cliente">
        <h1>Estética</h1>
    </div>

    <!-- Menu de navegação da cliente. -->
    <nav class="menu-cliente">

        <a
            href="painel_cliente.php"
            class="<?= $secao === 'inicio' ? 'ativo' : '' ?>"
        >
            Início
        </a>

        <a href="servicos.php">
            Serviços
        </a>

        <a href="meus_agendamentos.php">
            Meus Agendamentos
        </a>

        <a href="perfil_cliente.php">
            Meu Perfil
        </a>

        <a href="logout.php" class="menu-sair">
            Sair
        </a>

    </nav>

</header>


<!-- ============================================================
     CONTEÚDO PRINCIPAL
     ============================================================ -->

<main class="conteudo-cliente">


<?php if ($secao === "inicio"): ?>

    <!-- ========================================================
         SAUDAÇÃO E DATA
         ======================================================== -->

    <section class="topo-cliente">

        <div>

            <h2>
                Bem-vinda,
                <?= htmlspecialchars($primeiro_nome, ENT_QUOTES, "UTF-8") ?>!
            </h2>

            <p>
                Aqui você pode acompanhar seus atendimentos,
                conhecer nossos procedimentos e encontrar horários disponíveis.
            </p>

        </div>

        <div class="data-cliente">
            <?= htmlspecialchars($data_atual, ENT_QUOTES, "UTF-8") ?>
        </div>

    </section>


    <!-- ========================================================
         CARDS PRINCIPAIS
         ======================================================== -->

    <section class="cards-cliente">


        <!-- ====================================================
             CARD DE FIDELIDADE
             ==================================================== -->

        <a
            href="painel_cliente.php?secao=fidelidade"
            class="card-cliente card-fidelidade card-clicavel"
        >

            <span class="card-etiqueta">
                BENEFÍCIO
            </span>

            <h3>
                Programa de Fidelidade
            </h3>

            <p>
                A cada 10 atendimentos realizados com uma profissional,
                você ganha 15% de desconto no próximo procedimento,
                conforme as regras do programa.
            </p>

            <span class="card-detalhe">
                Clique para acompanhar seu progresso com cada profissional.
            </span>

        </a>


        <!-- ====================================================
             CARD DO PRÓXIMO ATENDIMENTO
             ==================================================== -->

        <a
            href="meus_agendamentos.php"
            class="card-cliente card-atendimento card-clicavel"
        >

            <span class="card-etiqueta">
                SEU ATENDIMENTO
            </span>

            <?php if ($proximo_agendamento): ?>

                <h3>
                    Próximo atendimento
                </h3>

                <p class="destaque-card">
                    <?= htmlspecialchars(
                        $proximo_agendamento["nome_servico"] ?? "Serviço",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </p>

                <p>
                    <strong>Data:</strong>

                    <?= !empty($proximo_agendamento["data_agendamento"])
                        ? date(
                            "d/m/Y",
                            strtotime($proximo_agendamento["data_agendamento"])
                        )
                        : "--" ?>
                </p>

                <p>
                    <strong>Horário:</strong>

                    <?= htmlspecialchars(
                        $proximo_agendamento["horario"] ?? "--",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </p>

                <p>
                    <strong>Profissional:</strong>

                    <?= htmlspecialchars(
                        $proximo_agendamento["profissional_nome"] ?? "--",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </p>

                <span class="link-card">
                    Ver meus agendamentos
                </span>

            <?php else: ?>

                <h3>
                    Nenhum horário marcado
                </h3>

                <p>
                    Você ainda não possui um atendimento futuro
                    com status pendente.
                </p>

                <span class="link-card">
                    Conhecer nossos serviços
                </span>

            <?php endif; ?>

        </a>


        <!-- ====================================================
             CARD DE AVISOS
             ==================================================== -->

        <article class="card-cliente card-aviso">

            <span class="card-etiqueta">
                AVISO
            </span>

            <h3>
                Informações importantes
            </h3>

            <p>
                Fique atenta às informações sobre seus atendimentos,
                horários e procedimentos.
            </p>

            <p class="card-detalhe">
                Os avisos das profissionais serão exibidos aqui
                quando o mural estiver implementado.
            </p>

        </article>

    </section>


    <!-- ========================================================
         ACESSO RÁPIDO
         ======================================================== -->

    <section class="acesso-rapido">

        <div class="titulo-secao">

            <span>ACESSO RÁPIDO</span>

            <h2>
                O que você deseja fazer?
            </h2>

        </div>

        <div class="atalhos-cliente">

            <a
                href="servicos.php"
                class="atalho-cliente"
            >

                <h3>
                    Conhecer os serviços
                </h3>

                <p>
                    Veja unhas, cílios e sobrancelhas
                    e conheça nossos procedimentos.
                </p>

            </a>

            <a
                href="meus_agendamentos.php"
                class="atalho-cliente"
            >

                <h3>
                    Meus agendamentos
                </h3>

                <p>
                    Consulte seus horários e acompanhe seus atendimentos.
                </p>

            </a>

        </div>

    </section>


<?php elseif ($secao === "fidelidade"): ?>

    <!-- ========================================================
         DETALHES DA FIDELIDADE
         ======================================================== -->

    <section class="topo-secao-cliente">

        <div>

            <span class="etiqueta-cliente">
                BENEFÍCIOS
            </span>

            <h2>
                Minha fidelidade
            </h2>

            <p>
                Acompanhe seus atendimentos concluídos com cada profissional.
            </p>

        </div>

    </section>


    <!-- Lista dos progressos de fidelidade. -->
    <section class="lista-fidelidade">

        <?php if (count($fidelidade_profissionais) > 0): ?>

            <?php foreach ($fidelidade_profissionais as $profissional): ?>

                <?php

                // Recupera o total de atendimentos concluídos.
                $total = (int) $profissional["total_atendimentos"];

                // Calcula o progresso dentro do ciclo atual de dez.
                $progresso = $total % 10;

                // Calcula a porcentagem preenchida na barra.
                $porcentagem = ($progresso / 10) * 100;

                // Define o nome da especialidade.
                $nome_especialidade =
                    $nomes_categorias[$profissional["especialidade"]]
                    ?? "Estética";

                ?>

                <article class="card-cliente card-progresso-fidelidade">

                    <span class="card-etiqueta">
                        <?= htmlspecialchars(
                            $nome_especialidade,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </span>

                    <h3>
                        <?= htmlspecialchars(
                            $profissional["profissional_nome"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </h3>

                    <p>
                        <strong>
                            <?= $progresso ?>/10 atendimentos
                        </strong>
                        concluídos no ciclo atual.
                    </p>

                    <div
                        class="barra-fidelidade"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="10"
                        aria-valuenow="<?= $progresso ?>"
                    >
                        <div
                            class="barra-fidelidade-progresso"
                            style="width: <?= $porcentagem ?>%;"
                        ></div>
                    </div>

                    <?php if ($total > 0 && $progresso === 0): ?>

                        <p>
                            Você completou um ciclo de dez atendimentos!
                        </p>

                    <?php else: ?>

                        <p>
                            Faltam <?= 10 - $progresso ?>
                            atendimento(s) para completar o ciclo.
                        </p>

                    <?php endif; ?>

                    <p class="card-detalhe">
                        Total de atendimentos concluídos com esta profissional:
                        <?= $total ?>.
                    </p>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="sem-agendamentos">

                <h3>
                    Nenhuma profissional ativa encontrada
                </h3>

                <p>
                    Não foi possível encontrar profissionais ativas
                    para exibir sua fidelidade.
                </p>

            </div>

        <?php endif; ?>

        <a
            href="painel_cliente.php"
            class="btn-principal-cliente"
        >
            Voltar ao painel
        </a>

    </section>


<?php elseif ($secao === "servicos"): ?>

    <!-- ========================================================
         TELA DE SERVIÇOS
         ======================================================== -->

    <section class="topo-secao-cliente">

        <div>

            <span class="etiqueta-cliente">
                PROCEDIMENTOS
            </span>

            <h2>
                Conheça nossos serviços
            </h2>

            <p>
                Escolha uma categoria para conhecer os procedimentos
                e as profissionais responsáveis.
            </p>

        </div>

    </section>


    <!-- Categorias disponíveis. -->
    <section class="categorias-servicos">

        <a
            href="servicos.php?categoria=nail"
            class="categoria-servico <?= $categoria === 'nail' ? 'ativo' : '' ?>"
        >

            <span>UNHAS</span>

            <h3>Nail Designer</h3>

            <p>
                Conheça os procedimentos para unhas.
            </p>

        </a>

        <a
            href="servicos.php?categoria=lash"
            class="categoria-servico <?= $categoria === 'lash' ? 'ativo' : '' ?>"
        >

            <span>CÍLIOS</span>

            <h3>Lash Designer</h3>

            <p>
                Conheça nossas opções para cílios.
            </p>

        </a>

        <a
            href="servicos.php?categoria=eyebrow"
            class="categoria-servico <?= $categoria === 'eyebrow' ? 'ativo' : '' ?>"
        >

            <span>SOBRANCELHAS</span>

            <h3>Designer de Sobrancelhas</h3>

            <p>
                Veja os procedimentos para sobrancelhas.
            </p>

        </a>

    </section>


    <?php if ($categoria !== ""): ?>

        <?php
        // Identifica a profissional do primeiro serviço da lista.
        $profissional_categoria =
            $servicos_categoria[0]["profissional_nome"] ?? null;
        ?>

        <section class="categoria-conteudo">

            <div class="categoria-cabecalho">

                <div>

                    <span class="etiqueta-cliente">
                        <?= htmlspecialchars(
                            strtoupper($nomes_categorias[$categoria]),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </span>

                    <h2>
                        Procedimentos disponíveis
                    </h2>

                </div>

                <?php if ($profissional_categoria): ?>

                    <div class="profissional-responsavel">

                        <span>
                            PROFISSIONAL RESPONSÁVEL
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                $profissional_categoria,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </strong>

                    </div>

                <?php endif; ?>

            </div>


            <?php if (count($servicos_categoria) > 0): ?>

                <div class="grade-servicos-cliente">

                    <?php foreach ($servicos_categoria as $servico): ?>

                        <article class="servico-card">

                            <div class="servico-card-conteudo">

                                <span class="servico-tipo">
                                    <?= htmlspecialchars(
                                        ucfirst($servico["tipo_servico"]),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </span>

                                <h3>
                                    <?= htmlspecialchars(
                                        $servico["nome_servico"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </h3>

                                <p class="servico-descricao">
                                    <?= htmlspecialchars(
                                        $servico["descricao"]
                                            ?? "Procedimento realizado com cuidado e atenção.",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </p>

                                <div class="servico-informacao">

                                    <strong>Duração</strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $servico["duracao"] ?? "--",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>

                                </div>

                                <div class="servico-informacao">

                                    <strong>Valor</strong>

                                    <span class="servico-preco">
                                        R$
                                        <?= number_format(
                                            (float) $servico["preco"],
                                            2,
                                            ",",
                                            "."
                                        ) ?>
                                    </span>

                                </div>

                                <div class="servico-profissional">

                                    <span>Profissional</span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $servico["profissional_nome"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </strong>

                                </div>

                                <a
                                    href="agenda.php?servico_id=<?= (int) $servico["id"] ?>"
                                    class="btn-horarios"
                                >
                                    Ver horários disponíveis
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="sem-servicos">

                    <h3>
                        Nenhum procedimento disponível
                    </h3>

                    <p>
                        Ainda não existem procedimentos cadastrados
                        para esta categoria.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    <?php else: ?>

        <section class="mensagem-servicos">

            <h2>
                Escolha uma categoria
            </h2>

            <p>
                Selecione entre unhas, cílios ou sobrancelhas
                para visualizar os procedimentos disponíveis.
            </p>

        </section>

    <?php endif; ?>


<?php elseif ($secao === "agendamentos"): ?>

    <!-- ========================================================
         TELA DE AGENDAMENTOS
         ======================================================== -->

    <section class="topo-secao-cliente">

        <div>

            <span class="etiqueta-cliente">
                MINHA AGENDA
            </span>

            <h2>
                Meus Agendamentos
            </h2>

            <p>
                Consulte seus atendimentos anteriores e futuros.
            </p>

        </div>

    </section>


    <!-- Lista dos agendamentos da cliente. -->
    <section class="lista-agendamentos">

        <?php if (count($agendamentos_cliente) > 0): ?>

            <?php foreach ($agendamentos_cliente as $agendamento): ?>

                <article class="agendamento-card">

                    <div class="agendamento-data">

                        <span>DATA</span>

                        <strong>
                            <?= !empty($agendamento["data_agendamento"])
                                ? date(
                                    "d/m/Y",
                                    strtotime($agendamento["data_agendamento"])
                                )
                                : "--" ?>
                        </strong>

                        <small>
                            <?= htmlspecialchars(
                                $agendamento["horario"] ?? "--",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </small>

                    </div>


                    <div class="agendamento-informacoes">

                        <span class="card-etiqueta">
                            PROCEDIMENTO
                        </span>

                        <h3>
                            <?= htmlspecialchars(
                                $agendamento["nome_servico"] ?? "--",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </h3>

                        <p>
                            <strong>Profissional:</strong>

                            <?= htmlspecialchars(
                                $agendamento["profissional_nome"] ?? "--",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </p>

                        <p>
                            <strong>Duração:</strong>

                            <?= htmlspecialchars(
                                $agendamento["duracao"] ?? "--",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </p>

                    </div>


                    <div class="agendamento-status">

                        <?php

                        // Define a classe CSS conforme o status.
                        $classe_status = "status-pendente";

                        if ($agendamento["status"] === "concluido") {
                            $classe_status = "status-concluido";
                        } elseif ($agendamento["status"] === "cancelado") {
                            $classe_status = "status-cancelado";
                        } elseif ($agendamento["status"] === "alterado") {
                            $classe_status = "status-alterado";
                        }

                        ?>

                        <span class="<?= $classe_status ?>">
                            <?= htmlspecialchars(
                                ucfirst($agendamento["status"] ?? "pendente"),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </span>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="sem-agendamentos">

                <h3>
                    Você ainda não possui agendamentos
                </h3>

                <p>
                    Conheça nossos serviços e escolha um procedimento
                    para encontrar um horário disponível.
                </p>

                <a
                    href="servicos.php"
                    class="btn-principal-cliente"
                >
                    Conhecer serviços
                </a>

            </div>

        <?php endif; ?>

    </section>

<?php endif; ?>


</main>


<!-- ============================================================
     RODAPÉ
     ============================================================ -->

<footer class="rodape-cliente">

    <p>
        TCC Estética — Área da Cliente
    </p>

</footer>


</body>
</html>
