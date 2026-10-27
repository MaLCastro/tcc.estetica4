
<?php
// ============================================================
// 1. INICIAR SESSÃO, CONEXÃO E VALIDAR ACESSO
// ============================================================

// Inicia a sessão da cliente.
session_start();

// Carrega a conexão PDO do projeto.
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

// Recupera os dados da cliente autenticada.
$cliente_id = (int) $_SESSION["usuario_id"];
$nome_cliente = $_SESSION["usuario_nome"] ?? "Cliente";

// Inicializa as listas e o estado de erro.
$proximos_agendamentos = [];
$historico_agendamentos = [];
$erro_agendamentos = false;

// Define o fuso horário para comparação dos atendimentos.
$fuso_horario = new DateTimeZone("America/Sao_Paulo");

// ============================================================
// 2. CRIAR TOKEN DE SEGURANÇA PARA CANCELAMENTO
// ============================================================

// Gera um token de sessão para validar os formulários.
if (empty($_SESSION["token_cancelamento"])) {
    $_SESSION["token_cancelamento"] = bin2hex(random_bytes(32));
}

// ============================================================
// 3. PROCESSAR O CANCELAMENTO DO AGENDAMENTO
// ============================================================

// Inicializa a mensagem de retorno.
$mensagem_agendamento = null;

// Processa somente os formulários enviados por POST.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["cancelar_agendamento"])
) {
    // Valida o token de segurança enviado pelo formulário.
    $token_recebido = $_POST["token_cancelamento"] ?? "";

    if (
        !is_string($token_recebido) ||
        !hash_equals($_SESSION["token_cancelamento"], $token_recebido)
    ) {
        $_SESSION["mensagem_agendamento"] = [
            "tipo" => "erro",
            "texto" => "Não foi possível validar a solicitação. Atualize a página e tente novamente."
        ];

        header("Location: meus_agendamentos.php");
        exit();
    }

    // Valida o identificador do agendamento recebido.
    $agendamento_id = filter_input(
        INPUT_POST,
        "agendamento_id",
        FILTER_VALIDATE_INT
    );

    if (!$agendamento_id || $agendamento_id < 1) {
        $_SESSION["mensagem_agendamento"] = [
            "tipo" => "erro",
            "texto" => "O agendamento informado é inválido."
        ];

        header("Location: meus_agendamentos.php");
        exit();
    }

    try {
        // Busca o agendamento somente se pertencer à cliente autenticada.
        $sql_validar = "
            SELECT
                id,
                data_agendamento,
                horario,
                status
            FROM agendamentos
            WHERE id = :id
              AND cliente_id = :cliente_id
            LIMIT 1
        ";

        $consulta_validar = $conexao->prepare($sql_validar);

        $consulta_validar->execute([
            ":id" => $agendamento_id,
            ":cliente_id" => $cliente_id
        ]);

        $registro = $consulta_validar->fetch(PDO::FETCH_ASSOC);

        // Impede a alteração de registros que não pertencem à cliente.
        if (!$registro) {
            $_SESSION["mensagem_agendamento"] = [
                "tipo" => "erro",
                "texto" => "Agendamento não encontrado."
            ];

            header("Location: meus_agendamentos.php");
            exit();
        }

        // Normaliza o status atual.
        $status_atual = strtolower(
            trim((string) $registro["status"])
        );

        // Só permite cancelar agendamentos que ainda podem ser cancelados.
        if (!in_array(
            $status_atual,
            ["pendente", "confirmado", "alterado"],
            true
        )) {
            $_SESSION["mensagem_agendamento"] = [
                "tipo" => "erro",
                "texto" => "Este agendamento não pode mais ser cancelado."
            ];

            header("Location: meus_agendamentos.php");
            exit();
        }

        // Monta a data e o horário completos do atendimento.
        $data_banco = (string) $registro["data_agendamento"];
        $horario_banco = substr(
            trim((string) $registro["horario"]),
            0,
            5
        );

        $data_hora_agendamento = DateTime::createFromFormat(
            "!Y-m-d H:i",
            $data_banco . " " . $horario_banco,
            $fuso_horario
        );

        // Confere se a data e o horário são válidos.
        $data_valida = (
            $data_hora_agendamento !== false &&
            $data_hora_agendamento->format("Y-m-d H:i") ===
                $data_banco . " " . $horario_banco
        );

        // Impede cancelar um atendimento que já começou ou passou.
        $agora = new DateTime("now", $fuso_horario);

        if (
            !$data_valida ||
            $data_hora_agendamento <= $agora
        ) {
            $_SESSION["mensagem_agendamento"] = [
                "tipo" => "erro",
                "texto" => "Não é possível cancelar um atendimento cujo horário já passou ou é inválido."
            ];

            header("Location: meus_agendamentos.php");
            exit();
        }

        // Atualiza somente o status do agendamento da cliente.
        $sql_cancelar = "
            UPDATE agendamentos
            SET status = 'cancelado'
            WHERE id = :id
              AND cliente_id = :cliente_id
              AND status IN ('pendente', 'confirmado', 'alterado')
        ";

        $consulta_cancelar = $conexao->prepare($sql_cancelar);

        $consulta_cancelar->execute([
            ":id" => $agendamento_id,
            ":cliente_id" => $cliente_id
        ]);

        // Informa se o banco confirmou a alteração.
        if ($consulta_cancelar->rowCount() > 0) {
            $_SESSION["mensagem_agendamento"] = [
                "tipo" => "sucesso",
                "texto" => "Agendamento cancelado com sucesso."
            ];
        } else {
            $_SESSION["mensagem_agendamento"] = [
                "tipo" => "erro",
                "texto" => "O agendamento não foi alterado. Atualize a página e tente novamente."
            ];
        }

    } catch (PDOException $erro) {
        // Registra o erro técnico sem expor detalhes ao usuário.
        error_log(
            "Erro ao cancelar agendamento: " .
            $erro->getMessage()
        );

        $_SESSION["mensagem_agendamento"] = [
            "tipo" => "erro",
            "texto" => "Ocorreu um erro ao cancelar o agendamento. Tente novamente."
        ];
    }

    // Redireciona para atualizar as listas e evitar novo envio do formulário.
    header("Location: meus_agendamentos.php");
    exit();
}

// Recupera a mensagem gerada pelo cancelamento.
$mensagem_agendamento = $_SESSION["mensagem_agendamento"] ?? null;
unset($_SESSION["mensagem_agendamento"]);

// ============================================================
// 4. BUSCAR OS AGENDAMENTOS DA CLIENTE
// ============================================================

// Define a data e o horário atuais.
$data_hora_atual = new DateTime("now", $fuso_horario);

try {
    // Busca os agendamentos e os dados de serviço e profissional.
    $sql_agendamentos = "
        SELECT
            a.id,
            a.data_agendamento AS data,
            a.horario,
            a.status,
            COALESCE(a.preco_final, s.preco) AS preco,
            s.nome_servico AS servico,
            u.nome AS profissional
        FROM agendamentos AS a
        LEFT JOIN servicos AS s
            ON s.id = a.servico_id
        LEFT JOIN usuarios AS u
            ON u.id = a.profissional_id
        WHERE a.cliente_id = :cliente_id
        ORDER BY a.data_agendamento ASC, a.horario ASC
    ";

    // Utiliza a conexão PDO definida em conexao.php.
    $consulta_agendamentos = $conexao->prepare($sql_agendamentos);

    // Busca somente os registros da cliente autenticada.
    $consulta_agendamentos->execute([
        ":cliente_id" => $cliente_id
    ]);

    // Recupera os registros retornados pelo banco.
    $agendamentos = $consulta_agendamentos->fetchAll(PDO::FETCH_ASSOC);

    // Separa os próximos atendimentos do histórico.
    foreach ($agendamentos as $agendamento) {
        $status_atual = strtolower(
            trim((string) ($agendamento["status"] ?? ""))
        );

        // Recupera a data e o horário para fazer a comparação.
        $data = trim((string) ($agendamento["data"] ?? ""));
        $horario = substr(
            trim((string) ($agendamento["horario"] ?? "")),
            0,
            5
        );

        // Cria a data completa do atendimento.
        $data_hora_agendamento = DateTime::createFromFormat(
            "!Y-m-d H:i",
            $data . " " . $horario,
            $fuso_horario
        );

        // Confere se o valor corresponde a uma data válida.
        $data_valida = (
            $data_hora_agendamento !== false &&
            $data_hora_agendamento->format("Y-m-d H:i") ===
                $data . " " . $horario
        );

        // Concluídos e cancelados permanecem no histórico.
        if (in_array(
            $status_atual,
            ["concluido", "cancelado"],
            true
        )) {
            $historico_agendamentos[] = $agendamento;
            continue;
        }

        // Atendimentos futuros aparecem na primeira seção.
        if (
            $data_valida &&
            $data_hora_agendamento >= $data_hora_atual
        ) {
            $proximos_agendamentos[] = $agendamento;
        } else {
            // Datas passadas ou inválidas ficam no histórico.
            $historico_agendamentos[] = $agendamento;
        }
    }

    // Ordena os próximos atendimentos do mais próximo ao mais distante.
    usort($proximos_agendamentos, function ($a, $b) {
        $data_a = ($a["data"] ?? "") . " " . ($a["horario"] ?? "");
        $data_b = ($b["data"] ?? "") . " " . ($b["horario"] ?? "");

        return strcmp($data_a, $data_b);
    });

    // Ordena o histórico do mais recente ao mais antigo.
    usort($historico_agendamentos, function ($a, $b) {
        $data_a = ($a["data"] ?? "") . " " . ($a["horario"] ?? "");
        $data_b = ($b["data"] ?? "") . " " . ($b["horario"] ?? "");

        return strcmp($data_b, $data_a);
    });

} catch (PDOException $erro) {
    // Registra a falha no log do servidor.
    error_log(
        "Erro ao carregar agendamentos da cliente: " .
        $erro->getMessage()
    );

    // Diferencia falha na consulta de uma lista vazia.
    $erro_agendamentos = true;
}

// ============================================================
// 5. FUNÇÕES DE FORMATAÇÃO E SEGURANÇA
// ============================================================

// Protege textos exibidos na página contra interpretação como HTML.
function escaparTexto($texto)
{
    return htmlspecialchars(
        (string) $texto,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Formata uma data no padrão brasileiro.
function formatarDataAgendamento($data)
{
    $data = (string) $data;

    // Interpreta a data no formato esperado pelo banco.
    $data_objeto = DateTime::createFromFormat("!Y-m-d", $data);

    // Impede apresentar datas inválidas.
    if (
        !$data_objeto ||
        $data_objeto->format("Y-m-d") !== $data
    ) {
        return "Data não informada";
    }

    return $data_objeto->format("d/m/Y");
}

// Converte o status em um rótulo e uma classe visual.
function apresentarStatus($status)
{
    $status = strtolower(trim((string) $status));

    // Rótulos dos status utilizados no sistema.
    $status_conhecidos = [
        "pendente" => "Pendente",
        "confirmado" => "Confirmado",
        "concluido" => "Concluído",
        "cancelado" => "Cancelado",
        "alterado" => "Alterado"
    ];

    // Classes CSS correspondentes aos status.
    $classes_status = [
        "pendente" => "status-pendente",
        "confirmado" => "status-confirmado",
        "concluido" => "status-concluido",
        "cancelado" => "status-cancelado",
        "alterado" => "status-alterado"
    ];

    return [
        "texto" => $status_conhecidos[$status] ?? "Em análise",
        "classe" => $classes_status[$status] ?? "status-indefinido"
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <!-- Configura os caracteres e a adaptação para celulares. -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define o título da aba do navegador. -->
    <title>Meus Agendamentos | TCC Estética</title>

    <!-- Carrega o CSS compartilhado pelo projeto. -->
    <link rel="stylesheet" href="style.css">
</head>

<body class="pagina-cliente pagina-meus-agendamentos">

    <!-- ====================================================== -->
    <!-- 6. CABEÇALHO E NAVEGAÇÃO DA CLIENTE -->
    <!-- ====================================================== -->

    <header class="cabecalho-cliente">
        <div class="logo-cliente">
            <h1>Estética</h1>
        </div>

        <!-- Mantém o menu padrão da área da cliente. -->
        <nav class="menu-cliente" aria-label="Navegação principal">
            <a href="painel_cliente.php">Início</a>
            <a href="servicos.php">Serviços</a>
            <a
                href="meus_agendamentos.php"
                class="ativo"
                aria-current="page"
            >Meus Agendamentos</a>
            <a href="perfil_cliente.php">Meu Perfil</a>
            <a href="logout.php" class="menu-sair">Sair</a>
        </nav>
    </header>

    <!-- ====================================================== -->
    <!-- 7. CONTEÚDO PRINCIPAL -->
    <!-- ====================================================== -->

    <main class="meus-agendamentos-container">

        <!-- Apresenta o resultado da última operação. -->
        <?php if ($mensagem_agendamento): ?>
            <div
                class="mensagem-agendamento <?= $mensagem_agendamento["tipo"] === "sucesso" ? "mensagem-sucesso" : "mensagem-erro" ?>"
                role="status"
            >
                <?= escaparTexto($mensagem_agendamento["texto"]) ?>
            </div>
        <?php endif; ?>

        <!-- Apresentação da página. -->
        <section class="agendamentos-introducao">
            <span class="agendamentos-etiqueta">ÁREA DA CLIENTE</span>

            <h2>Meus Agendamentos</h2>

            <p>
                Olá, <?= escaparTexto($nome_cliente) ?>!
                Consulte seus próximos atendimentos e confira
                o histórico dos serviços realizados.
            </p>
        </section>

        <?php if ($erro_agendamentos): ?>

            <!-- Apresenta uma mensagem se a consulta falhar. -->
            <div class="agendamentos-vazio" role="alert">
                <h4>Não foi possível carregar seus agendamentos</h4>
                <p>
                    Ocorreu um problema ao consultar os registros.
                    Atualize a página ou tente novamente mais tarde.
                </p>
            </div>

        <?php else: ?>

            <!-- ============================================== -->
            <!-- 8. PRÓXIMOS AGENDAMENTOS -->
            <!-- ============================================== -->

            <section
                class="secao-agendamentos"
                id="proximos-agendamentos"
            >
                <div class="agendamentos-titulo-secao">
                    <div>
                        <h3>Próximos agendamentos</h3>
                        <p>
                            Confira os detalhes dos seus próximos atendimentos.
                        </p>
                    </div>

                    <!-- Contador atualizado conforme os registros encontrados. -->
                    <span class="agendamentos-contador">
                        <?= count($proximos_agendamentos) ?>
                    </span>
                </div>

                <?php if (!empty($proximos_agendamentos)): ?>

                    <div class="lista-agendamentos">

                        <?php foreach ($proximos_agendamentos as $agendamento): ?>

                            <!-- Cartão individual do próximo atendimento. -->
                            <article class="cartao-agendamento">

                                <div class="agendamento-data">
                                    <span>Data do atendimento</span>

                                    <strong>
                                        <?= escaparTexto(
                                            formatarDataAgendamento(
                                                $agendamento["data"]
                                            )
                                        ) ?>
                                    </strong>

                                    <span class="agendamento-horario">
                                        <?= escaparTexto(
                                            $agendamento["horario"] ?? "--:--"
                                        ) ?>
                                    </span>
                                </div>

                                <div class="agendamento-detalhes">
                                    <h4>
                                        <?= escaparTexto(
                                            $agendamento["servico"] ?? "Serviço"
                                        ) ?>
                                    </h4>

                                    <p>
                                        <strong>Profissional:</strong>
                                        <?= escaparTexto(
                                            $agendamento["profissional"] ??
                                            "Não informada"
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Valor:</strong>

                                        <?php if ($agendamento["preco"] !== null): ?>
                                            R$
                                            <?= number_format(
                                                (float) $agendamento["preco"],
                                                2,
                                                ",",
                                                "."
                                            ) ?>
                                        <?php else: ?>
                                            A confirmar
                                        <?php endif; ?>
                                    </p>

                                    <!-- Exibe o status atual do atendimento. -->
                                    <?php
                                    $status = apresentarStatus(
                                        $agendamento["status"] ?? ""
                                    );
                                    ?>

                                    <span class="agendamento-status <?= escaparTexto($status["classe"]) ?>">
                                        <?= escaparTexto($status["texto"]) ?>
                                    </span>
                                </div>

                                <!-- Mostra o botão somente para status canceláveis. -->
                                <?php
                                $status_botao = strtolower(
                                    trim((string) ($agendamento["status"] ?? ""))
                                );
                                ?>

                                <?php if (in_array(
                                    $status_botao,
                                    ["pendente", "confirmado", "alterado"],
                                    true
                                )): ?>

                                    <div class="agendamento-acoes">
                                        <form
                                            method="POST"
                                            action="meus_agendamentos.php"
                                            onsubmit="return confirm('Tem certeza de que deseja cancelar este agendamento?');"
                                        >
                                            <!-- Identifica o agendamento selecionado. -->
                                            <input
                                                type="hidden"
                                                name="agendamento_id"
                                                value="<?= (int) $agendamento["id"] ?>"
                                            >

                                            <!-- Envia o token de segurança da sessão. -->
                                            <input
                                                type="hidden"
                                                name="token_cancelamento"
                                                value="<?= escaparTexto($_SESSION["token_cancelamento"]) ?>"
                                            >

                                            <!-- Envia a solicitação de cancelamento. -->
                                            <button
                                                type="submit"
                                                name="cancelar_agendamento"
                                                value="1"
                                                class="btn-cancelar-agendamento"
                                            >
                                                Cancelar agendamento
                                            </button>
                                        </form>
                                    </div>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <!-- Mensagem apresentada quando não há próximos atendimentos. -->
                    <div class="agendamentos-vazio">
                        <h4>Nenhum próximo agendamento</h4>

                        <p>
                            Quando você realizar um agendamento,
                            os detalhes aparecerão nesta seção.
                        </p>

                        <a href="servicos.php" class="agendamentos-botao">
                            Conhecer serviços
                        </a>
                    </div>

                <?php endif; ?>
            </section>

            <!-- ============================================== -->
            <!-- 9. HISTÓRICO DE ATENDIMENTOS -->
            <!-- ============================================== -->

            <section
                class="secao-agendamentos"
                id="historico-agendamentos"
            >
                <div class="agendamentos-titulo-secao">
                    <div>
                        <h3>Histórico de atendimentos</h3>
                        <p>
                            Consulte os atendimentos anteriores e seus status.
                        </p>
                    </div>

                    <!-- Contador dos registros do histórico. -->
                    <span class="agendamentos-contador">
                        <?= count($historico_agendamentos) ?>
                    </span>
                </div>

                <?php if (!empty($historico_agendamentos)): ?>

                    <div class="lista-agendamentos">

                        <?php foreach ($historico_agendamentos as $agendamento): ?>

                            <!-- Cartão individual do histórico. -->
                            <article class="cartao-agendamento cartao-historico">

                                <div class="agendamento-data">
                                    <span>Data do atendimento</span>

                                    <strong>
                                        <?= escaparTexto(
                                            formatarDataAgendamento(
                                                $agendamento["data"]
                                            )
                                        ) ?>
                                    </strong>

                                    <span class="agendamento-horario">
                                        <?= escaparTexto(
                                            $agendamento["horario"] ?? "--:--"
                                        ) ?>
                                    </span>
                                </div>

                                <div class="agendamento-detalhes">
                                    <h4>
                                        <?= escaparTexto(
                                            $agendamento["servico"] ?? "Serviço"
                                        ) ?>
                                    </h4>

                                    <p>
                                        <strong>Profissional:</strong>
                                        <?= escaparTexto(
                                            $agendamento["profissional"] ??
                                            "Não informada"
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Valor:</strong>

                                        <?php if ($agendamento["preco"] !== null): ?>
                                            R$
                                            <?= number_format(
                                                (float) $agendamento["preco"],
                                                2,
                                                ",",
                                                "."
                                            ) ?>
                                        <?php else: ?>
                                            A confirmar
                                        <?php endif; ?>
                                    </p>

                                    <!-- Exibe o status final ou atual do registro. -->
                                    <?php
                                    $status = apresentarStatus(
                                        $agendamento["status"] ?? ""
                                    );
                                    ?>

                                    <span class="agendamento-status <?= escaparTexto($status["classe"]) ?>">
                                        <?= escaparTexto($status["texto"]) ?>
                                    </span>
                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <!-- Mensagem apresentada quando o histórico está vazio. -->
                    <div class="agendamentos-vazio">
                        <h4>Nenhum registro no histórico</h4>

                        <p>
                            Seus atendimentos anteriores aparecerão aqui
                            quando houver registros correspondentes.
                        </p>
                    </div>

                <?php endif; ?>
            </section>

        <?php endif; ?>

    </main>

    <!-- Rodapé discreto da área da cliente. -->
    <footer class="rodape-agendamentos">
        <p>TCC Estética — Área da Cliente</p>
    </footer>

</body>
</html>
