
<?php
// ============================================================
// 1. INICIAR SESSÃO E VALIDAR O ACESSO
// ============================================================

session_start();
require_once("conexao.php");

// Define o fuso horário utilizado pelo sistema.
date_default_timezone_set("America/Sao_Paulo");

// Permite acesso somente para clientes autenticadas.
if (
    !isset($_SESSION["usuario_id"]) ||
    ($_SESSION["usuario_tipo"] ?? "") !== "cliente"
) {
    header("Location: login.php");
    exit();
}

$cliente_id = (int) $_SESSION["usuario_id"];
$editar = isset($_GET["editar"]) && $_GET["editar"] === "1";

// ============================================================
// 2. INICIALIZAR MENSAGENS E TOKEN DE SEGURANÇA
// ============================================================

$erro = "";
$sucesso = "";

// Cria um token para proteger o formulário contra envios indevidos.
if (empty($_SESSION["token_perfil"])) {
    $_SESSION["token_perfil"] = bin2hex(random_bytes(32));
}

// ============================================================
// 3. FUNÇÃO PARA PROTEGER OS TEXTOS EXIBIDOS
// ============================================================

function escaparPerfil($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// ============================================================
// 4. PROCESSAR A ATUALIZAÇÃO DOS DADOS PESSOAIS
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Mantém o formulário aberto quando ocorre um erro.
    $editar = true;

    try {
        // Valida o token de segurança enviado pelo formulário.
        $token_recebido = $_POST["token_perfil"] ?? "";

        if (
            !is_string($token_recebido) ||
            !hash_equals($_SESSION["token_perfil"], $token_recebido)
        ) {
            throw new Exception(
                "Não foi possível validar o formulário. Atualize a página e tente novamente."
            );
        }

        // Recebe somente os campos pessoais que podem ser alterados.
        $nome = trim($_POST["nome"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $telefone = trim($_POST["telefone"] ?? "");

        // Valida os campos obrigatórios.
        if ($nome === "" || $email === "") {
            throw new Exception("Preencha seu nome e seu e-mail.");
        }

        // Valida o formato do e-mail.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Informe um endereço de e-mail válido.");
        }

        // Confere o tamanho dos campos antes de atualizar o banco.
        if (
            mb_strlen($nome) > 150 ||
            mb_strlen($email) > 254 ||
            mb_strlen($telefone) > 30
        ) {
            throw new Exception(
                "Um ou mais campos ultrapassaram o tamanho permitido."
            );
        }

        // Confere se o e-mail já pertence a outra conta.
        $consulta_email = $conexao->prepare("
            SELECT id
            FROM usuarios
            WHERE email = :email
              AND id <> :id
            LIMIT 1
        ");

        $consulta_email->execute([
            ":email" => $email,
            ":id" => $cliente_id
        ]);

        if ($consulta_email->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception(
                "Este e-mail já está cadastrado em outra conta."
            );
        }

        // Atualiza somente os dados pessoais da cliente autenticada.
        $atualizacao = $conexao->prepare("
            UPDATE usuarios
            SET nome = :nome,
                email = :email,
                telefone = :telefone
            WHERE id = :id
              AND tipo_usuario = 'cliente'
        ");

        $atualizacao->execute([
            ":nome" => $nome,
            ":email" => $email,
            ":telefone" => $telefone,
            ":id" => $cliente_id
        ]);

        // Atualiza o nome da cliente armazenado na sessão.
        $_SESSION["usuario_nome"] = $nome;

        // Renova o token depois do envio válido.
        $_SESSION["token_perfil"] = bin2hex(random_bytes(32));

        // Usa redirecionamento para evitar reenviar o formulário.
        $_SESSION["mensagem_perfil"] = [
            "tipo" => "sucesso",
            "texto" => "Seus dados foram atualizados com sucesso."
        ];

        header("Location: perfil_cliente.php");
        exit();

    } catch (PDOException $erro_banco) {
        // Registra os detalhes técnicos sem exibi-los à cliente.
        error_log(
            "Erro ao atualizar perfil da cliente: " .
            $erro_banco->getMessage()
        );

        $erro = "Não foi possível atualizar seus dados. Confira as informações e tente novamente.";

    } catch (Exception $erro_formulario) {
        // Apresenta os erros de validação de maneira compreensível.
        $erro = $erro_formulario->getMessage();
    }
}

// Recupera mensagens após o redirecionamento.
if (isset($_SESSION["mensagem_perfil"])) {
    $mensagem = $_SESSION["mensagem_perfil"];

    if (($mensagem["tipo"] ?? "") === "sucesso") {
        $sucesso = $mensagem["texto"];
    }

    unset($_SESSION["mensagem_perfil"]);
}

// ============================================================
// 5. CARREGAR OS DADOS DA CLIENTE
// ============================================================

try {
    // Busca somente os dados da cliente autenticada.
    $consulta_cliente = $conexao->prepare("
        SELECT nome, email, telefone, data_cadastro
        FROM usuarios
        WHERE id = :id
          AND tipo_usuario = 'cliente'
        LIMIT 1
    ");

    $consulta_cliente->execute([":id" => $cliente_id]);
    $cliente = $consulta_cliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        throw new Exception("Não foi possível localizar sua conta.");
    }

} catch (Exception $erro_carregamento) {
    error_log(
        "Erro ao carregar perfil da cliente: " .
        $erro_carregamento->getMessage()
    );

    http_response_code(500);
    exit("Não foi possível carregar seu perfil. Tente novamente mais tarde.");
}

// ============================================================
// 6. CARREGAR O RESUMO DE ATIVIDADES
// ============================================================

$total_concluidos = 0;
$total_proximos = 0;
$valor_concluidos = 0.0;

try {
    // Conta os atendimentos já concluídos e soma seus valores.
    $consulta_resumo = $conexao->prepare("
        SELECT
            COUNT(*) AS total_concluidos,
            COALESCE(
                SUM(COALESCE(a.preco_final, s.preco, 0)),
                0
            ) AS valor_concluidos
        FROM agendamentos AS a
        LEFT JOIN servicos AS s
            ON s.id = a.servico_id
        WHERE a.cliente_id = :cliente_id
          AND LOWER(TRIM(a.status)) = 'concluido'
    ");

    $consulta_resumo->execute([
        ":cliente_id" => $cliente_id
    ]);

    $resumo = $consulta_resumo->fetch(PDO::FETCH_ASSOC);

    $total_concluidos = (int) ($resumo["total_concluidos"] ?? 0);
    $valor_concluidos = (float) ($resumo["valor_concluidos"] ?? 0);

    // Conta os atendimentos futuros que ainda não foram cancelados ou concluídos.
    $consulta_proximos = $conexao->prepare("
        SELECT COUNT(*)
        FROM agendamentos AS a
        WHERE a.cliente_id = :cliente_id
          AND LOWER(TRIM(a.status)) IN ('pendente', 'confirmado', 'alterado')
          AND TIMESTAMP(a.data_agendamento, a.horario) >= NOW()
    ");

    $consulta_proximos->execute([
        ":cliente_id" => $cliente_id
    ]);

    $total_proximos = (int) $consulta_proximos->fetchColumn();

} catch (PDOException $erro_banco) {
    // Registra a falha e mantém os indicadores em zero.
    error_log(
        "Erro ao carregar resumo do perfil da cliente: " .
        $erro_banco->getMessage()
    );
}

// ============================================================
// 7. PREPARAR OS DADOS DE APRESENTAÇÃO
// ============================================================

// Utiliza a primeira letra do nome como avatar provisório.
$nome_exibicao = trim((string) $cliente["nome"]);
$inicial = mb_strtoupper(mb_substr($nome_exibicao, 0, 1, "UTF-8"), "UTF-8");

// Formata a data de cadastro para o padrão brasileiro.
$data_cadastro_exibicao = "Não informada";

if (!empty($cliente["data_cadastro"])) {
    try {
        $data_cadastro_objeto = new DateTime($cliente["data_cadastro"]);
        $data_cadastro_exibicao = $data_cadastro_objeto->format("d/m/Y");
    } catch (Exception $erro_data) {
        // Mantém o texto alternativo se a data não puder ser interpretada.
        $data_cadastro_exibicao = "Não informada";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <!-- Configura os caracteres e a adaptação para celulares. -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meu Perfil | TCC Estética</title>

    <!-- Carrega os estilos compartilhados do sistema. -->
    <link rel="stylesheet" href="style.css">
</head>

<body class="pagina-cliente pagina-perfil-cliente">

    <!-- ====================================================== -->
    <!-- 8. CABEÇALHO E MENU DA CLIENTE -->
    <!-- ====================================================== -->

    <header class="cabecalho-cliente">
        <div class="logo-cliente">
            <h1>Estética</h1>
        </div>

        <!-- Mantém o menu padrão das páginas da cliente. -->
        <nav class="menu-cliente" aria-label="Navegação principal">
            <a href="painel_cliente.php">Início</a>
            <a href="servicos.php">Serviços</a>
            <a href="meus_agendamentos.php">Meus Agendamentos</a>
            <a href="perfil_cliente.php" class="ativo" aria-current="page">Meu Perfil</a>
            <a href="logout.php" class="menu-sair">Sair</a>
        </nav>
    </header>

    <!-- ====================================================== -->
    <!-- 9. CONTEÚDO PRINCIPAL DO PERFIL -->
    <!-- ====================================================== -->

    <main class="perfil-container">

        <!-- Cabeçalho da página. -->
        <section class="perfil-introducao">
            <span class="perfil-etiqueta">MINHA CONTA</span>
            <h2>Meu Perfil</h2>
            <p>Consulte seus dados pessoais e acompanhe sua atividade no sistema.</p>
        </section>

        <!-- Apresenta mensagens de sucesso ou erro. -->
        <?php if ($sucesso !== ""): ?>
            <div class="perfil-mensagem perfil-sucesso" role="status">
                <?= escaparPerfil($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ""): ?>
            <div class="perfil-mensagem perfil-erro" role="alert">
                <?= escaparPerfil($erro) ?>
            </div>
        <?php endif; ?>

        <!-- Cartão de identificação da cliente. -->
        <section class="perfil-cartao perfil-identificacao">
            <div class="perfil-foto" aria-hidden="true">
                <span><?= escaparPerfil($inicial) ?></span>
            </div>

            <div class="perfil-identificacao-texto">
                <h3><?= escaparPerfil($cliente["nome"]) ?></h3>
                <p><?= escaparPerfil($cliente["email"]) ?></p>
                <span class="perfil-tipo-conta">Cliente</span>
            </div>
        </section>

        <!-- ================================================== -->
        <!-- 10. DADOS PESSOAIS: VISUALIZAÇÃO OU EDIÇÃO -->
        <!-- ================================================== -->

        <section class="perfil-cartao perfil-dados-pessoais">

            <div class="perfil-titulo-secao">
                <div>
                    <h3>Dados pessoais</h3>
                    <p>As informações utilizadas na sua conta.</p>
                </div>

                <?php if (!$editar): ?>
                    <!-- Abre o formulário de edição. -->
                    <a href="perfil_cliente.php?editar=1" class="perfil-botao">
                        Alterar informações pessoais
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($editar): ?>

                <!-- Formulário restrito aos dados pessoais. -->
                <form
                    method="POST"
                    action="perfil_cliente.php"
                    class="perfil-formulario"
                >
                    <input
                        type="hidden"
                        name="token_perfil"
                        value="<?= escaparPerfil($_SESSION["token_perfil"]) ?>"
                    >

                    <div class="perfil-campo">
                        <label for="nome">Nome completo</label>
                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            maxlength="150"
                            value="<?= escaparPerfil($cliente["nome"]) ?>"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="perfil-campo">
                        <label for="email">E-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="254"
                            value="<?= escaparPerfil($cliente["email"]) ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="perfil-campo">
                        <label for="telefone">Telefone</label>
                        <input
                            type="tel"
                            id="telefone"
                            name="telefone"
                            maxlength="30"
                            value="<?= escaparPerfil($cliente["telefone"] ?? "") ?>"
                            autocomplete="tel"
                        >
                    </div>

                    <div class="perfil-campo">
                        <label for="data_cadastro">Data de cadastro</label>
                        <input
                            type="text"
                            id="data_cadastro"
                            value="<?= escaparPerfil($data_cadastro_exibicao) ?>"
                            readonly
                        >
                    </div>

                    <!-- Botões para salvar ou abandonar a edição. -->
                    <div class="perfil-acoes">
                        <button type="submit" class="perfil-botao">
                            Salvar alterações
                        </button>

                        <a
                            href="perfil_cliente.php"
                            class="perfil-botao-secundario"
                        >
                            Cancelar edição
                        </a>
                    </div>
                </form>

            <?php else: ?>

                <!-- Exibe os dados sem campos editáveis. -->
                <div class="perfil-dados-grade">

                    <div class="perfil-dado">
                        <span>Nome completo</span>
                        <strong><?= escaparPerfil($cliente["nome"]) ?></strong>
                    </div>

                    <div class="perfil-dado">
                        <span>E-mail</span>
                        <strong><?= escaparPerfil($cliente["email"]) ?></strong>
                    </div>

                    <div class="perfil-dado">
                        <span>Telefone</span>
                        <strong>
                            <?= escaparPerfil(
                                !empty($cliente["telefone"])
                                    ? $cliente["telefone"]
                                    : "Não informado"
                            ) ?>
                        </strong>
                    </div>

                    <div class="perfil-dado">
                        <span>Data de cadastro</span>
                        <strong><?= escaparPerfil($data_cadastro_exibicao) ?></strong>
                    </div>

                </div>

            <?php endif; ?>

        </section>

        <!-- ================================================== -->
        <!-- 11. RESUMO DA ATIVIDADE DA CLIENTE -->
        <!-- ================================================== -->

        <section class="perfil-cartao perfil-resumo-atividades">

            <div class="perfil-titulo-secao">
                <h3>Minha atividade</h3>
                <p>Um resumo dos seus atendimentos registrados no sistema.</p>
            </div>

            <!-- Indicadores calculados a partir dos agendamentos. -->
            <div class="perfil-indicadores">

                <article class="perfil-indicador">
                    <span>Próximos atendimentos</span>
                    <strong><?= $total_proximos ?></strong>
                    <p>Agendamentos futuros ativos.</p>
                </article>

                <article class="perfil-indicador">
                    <span>Atendimentos concluídos</span>
                    <strong><?= $total_concluidos ?></strong>
                    <p>Serviços marcados como concluídos.</p>
                </article>

                <article class="perfil-indicador">
                    <span>Valor dos serviços concluídos</span>
                    <strong>
                        R$ <?= number_format(
                            $valor_concluidos,
                            2,
                            ",",
                            "."
                        ) ?>
                    </strong>
                    <p>Soma dos valores registrados como concluídos.</p>
                </article>

            </div>

            <!-- Atalho para consultar os detalhes dos registros. -->
            <div class="perfil-acoes">
                <a
                    href="meus_agendamentos.php"
                    class="perfil-botao"
                >
                    Ver meus agendamentos
                </a>

                <a
                    href="painel_cliente.php"
                    class="perfil-botao-secundario"
                >
                    Voltar ao início
                </a>
            </div>

        </section>

    </main>

    <!-- Rodapé padrão da área da cliente. -->
    <footer class="rodape-agendamentos">
        <p>TCC Estética — Área da Cliente</p>
    </footer>

</body>
</html>
