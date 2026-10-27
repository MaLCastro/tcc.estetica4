```php
<?php
// ============================================================
// 1. INICIAR A SESSÃO E VALIDAR O ACESSO
// ============================================================

// Inicia a sessão da profissional autenticada.
session_start();

// Carrega a conexão com o banco de dados.
require_once("conexao.php");

// Permite acesso somente para profissionais.
if (
    !isset($_SESSION["usuario_id"]) ||
    ($_SESSION["usuario_tipo"] ?? "") !== "profissional"
) {
    header("Location: login.php");
    exit();
}

// Gera o token de segurança dos formulários.
if (empty($_SESSION["csrf_agendamentos"])) {
    $_SESSION["csrf_agendamentos"] = bin2hex(random_bytes(32));
}

// Define o fuso horário utilizado pelo sistema.
date_default_timezone_set("America/Sao_Paulo");


// ============================================================
// 2. DEFINIR OS FILTROS
// ============================================================

// Obtém o mês solicitado ou utiliza o mês atual.
$mes = filter_input(INPUT_GET, "mes", FILTER_VALIDATE_INT);
$mes = ($mes !== false && $mes !== null) ? $mes : (int)date("m");
$mes = max(1, min(12, $mes));

// Obtém o ano solicitado ou utiliza o ano atual.
$ano = filter_input(INPUT_GET, "ano", FILTER_VALIDATE_INT);
$ano = ($ano !== false && $ano !== null) ? $ano : (int)date("Y");
$ano = max(2020, min((int)date("Y") + 1, $ano));

// Define os status aceitos pelo filtro.
$statusPermitidos = [
    "pendente",
    "concluido",
    "cancelado",
    "alterado"
];

// Obtém e valida o filtro de status.
$filtroStatus = $_GET["status"] ?? "";

if (!in_array($filtroStatus, $statusPermitidos, true)) {
    $filtroStatus = "";
}

// O filtro de hoje só fica ativo quando a URL contém hoje=1.
$somenteHoje = isset($_GET["hoje"]) && $_GET["hoje"] === "1";

// Guarda a data de hoje no fuso horário configurado.
$dataHoje = date("Y-m-d");

// Identifica a profissional autenticada.
$profissional_id = (int)$_SESSION["usuario_id"];

// Define o intervalo do mês selecionado.
$dataInicio = sprintf("%04d-%02d-01", $ano, $mes);
$dataFim = date("Y-m-t", strtotime($dataInicio));

// Prepara a navegação entre meses.
$dataMesAnterior = strtotime("-1 month", strtotime($dataInicio));
$dataProximoMes = strtotime("+1 month", strtotime($dataInicio));


// ============================================================
// 3. FUNÇÕES AUXILIARES
// ============================================================

// Formata os valores monetários no padrão brasileiro.
function formatarDinheiro($valor)
{
    if ($valor === null || $valor === "") {
        return "---";
    }

    return "R$ " . number_format((float)$valor, 2, ",", ".");
}

// Converte o status para uma descrição amigável.
function etiquetaStatus($status)
{
    return match ($status) {
        "pendente"  => "Pendente",
        "confirmado" => "Confirmado",
        "concluido" => "Concluído",
        "cancelado" => "Cancelado",
        "alterado"  => "Alterado",
        default     => ucfirst((string)$status)
    };
}


 // ============================================================
 // 4. CONSULTAR OS AGENDAMENTOS
 // ============================================================

 $erroConsulta = false;
 $atendimentos = [];
 
 // Inicializa os cartões de resumo.
 $resumo = [
     "total" => 0,
     "concluido" => 0,
     "pendente" => 0,
     "cancelado" => 0,
     "alterado" => 0,
     "confirmado" => 0
 ];
 
 // Consulta os agendamentos da profissional.
 try {
     // Usa LEFT JOIN para não ocultar agendamentos
     // caso falte algum registro relacionado de cliente ou serviço.
     $sql = "
         SELECT
             a.id,
             a.data_agendamento,
             a.horario,
             a.status,
             a.preco_final,
             u.nome AS nome_cliente,
             u.telefone AS telefone_cliente,
             s.nome_servico,
             s.tipo_servico
         FROM agendamentos a
         LEFT JOIN usuarios u
             ON u.id = a.cliente_id
         LEFT JOIN servicos s
             ON s.id = a.servico_id
         WHERE a.profissional_id = :profissional_id
     ";
 
     // Define a profissional autenticada como parâmetro.
     $parametros = [
         ":profissional_id" => (int) $profissional_id
     ];
 
     // Aplica o filtro de hoje somente quando solicitado.
     if ($somenteHoje) {
         $sql .= " AND a.data_agendamento = :data_hoje";
         $parametros[":data_hoje"] = $dataHoje;
     } else {
         // Caso contrário, consulta o mês selecionado.
         $sql .= "
             AND a.data_agendamento BETWEEN :data_inicio AND :data_fim
         ";
 
         $parametros[":data_inicio"] = $dataInicio;
         $parametros[":data_fim"] = $dataFim;
     }
 
     // Filtra pelo status escolhido, se houver.
     if ($filtroStatus !== "") {
         $sql .= " AND a.status = :status";
         $parametros[":status"] = $filtroStatus;
     }
 
     // Exibe primeiro os agendamentos mais recentes.
     $sql .= "
         ORDER BY
             a.data_agendamento DESC,
             a.horario DESC
     ";
 
     // Executa a consulta principal.
     $stmt = $conexao->prepare($sql);
     $stmt->execute($parametros);
     $atendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
 } catch (PDOException $e) {
     // Registra o erro da consulta principal.
     error_log("Erro ao consultar agendamentos: " . $e->getMessage());
     $erroConsulta = true;
 }
 
 // Consulta o resumo mensal separadamente.
 if (!$erroConsulta) {
     try {
         // Conta os agendamentos de cada status no mês.
         $sqlResumo = "
             SELECT
                 a.status,
                 COUNT(*) AS quantidade
             FROM agendamentos a
             WHERE
                 a.profissional_id = :profissional_id
                 AND a.data_agendamento BETWEEN :data_inicio AND :data_fim
             GROUP BY a.status
         ";
 
         $stmtResumo = $conexao->prepare($sqlResumo);
         $stmtResumo->execute([
             ":profissional_id" => (int) $profissional_id,
             ":data_inicio" => $dataInicio,
             ":data_fim" => $dataFim
         ]);
 
         // Soma as quantidades retornadas pelo banco.
         while ($linhaResumo = $stmtResumo->fetch(PDO::FETCH_ASSOC)) {
             $statusResumo = $linhaResumo["status"];
             $quantidade = (int) $linhaResumo["quantidade"];
 
             $resumo["total"] += $quantidade;
 
             // Soma apenas os status previstos nos cartões.
             if (array_key_exists($statusResumo, $resumo)) {
                 $resumo[$statusResumo] += $quantidade;
             }
         }
 
     } catch (PDOException $e) {
         // A falha no resumo não apaga a lista já carregada.
         error_log("Erro ao carregar resumo dos atendimentos: " . $e->getMessage());
     }
 }
 

// ============================================================
// 5. PREPARAR OS TEXTOS DA PÁGINA
// ============================================================

// Nomes dos meses em português.
$meses = [
    1 => "Janeiro",
    2 => "Fevereiro",
    3 => "Março",
    4 => "Abril",
    5 => "Maio",
    6 => "Junho",
    7 => "Julho",
    8 => "Agosto",
    9 => "Setembro",
    10 => "Outubro",
    11 => "Novembro",
    12 => "Dezembro"
];

// Mensagens de retorno das ações.
$resultado = $_GET["resultado"] ?? "";

$mensagens = [
    "concluido" => "Atendimento concluído com sucesso.",
    "cancelado" => "Agendamento cancelado com sucesso.",
    "nao_alterado" => "O agendamento não foi alterado. Ele pode já ter sido atualizado.",
    "erro" => "Não foi possível atualizar o agendamento. Tente novamente."
];

$mensagemResultado = $mensagens[$resultado] ?? "";
$sucesso = in_array($resultado, ["concluido", "cancelado"], true);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atendimentos | TCC Estética</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- ============================================================
     6. MENU LATERAL
     ============================================================ -->

<aside class="sidebar">
    <div class="logo">
        <h1>Estética</h1>
        <p>
            <?= htmlspecialchars(
                $_SESSION["especialidade"] ?? "Profissional",
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>
    </div>

    <nav class="menu">
        <a href="painel_profissional.php">Início</a>
        <a href="atendimentos.php" class="ativo">Atendimentos</a>
        <a href="minha_agenda.php">Minha Agenda</a>
        <a href="meus_servicos.php">Meus Serviços</a>
        <div class="separador"></div>
        <a href="meu_perfil.php">Meu Perfil</a>
    </nav>

    <a href="logout.php" class="btn-sair">Sair</a>
</aside>

<!-- ============================================================
     7. CONTEÚDO PRINCIPAL
     ============================================================ -->

<div class="conteudo-profissional">
<main class="pagina-atendimentos">

    <section class="cabecalho-pagina">
        <div>
            <h1>Atendimentos</h1>
            <p>
                Consulte os agendamentos do mês e acompanhe a situação
                de cada atendimento.
            </p>
        </div>
    </section>

    <!-- Mensagens de retorno das ações. -->
    <?php if ($mensagemResultado !== ""): ?>
        <div
            class="mensagem-retorno <?= $sucesso ? 'mensagem-sucesso' : 'mensagem-erro' ?>"
            role="status"
        >
            <?= htmlspecialchars($mensagemResultado, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <!-- Mensagem para erros na consulta ao banco. -->
    <?php if ($erroConsulta): ?>
        <div class="mensagem-erro" role="alert">
            Não foi possível carregar os atendimentos. Verifique o registro
            de erros do servidor para identificar a causa.
        </div>
    <?php endif; ?>

    <!-- ========================================================
         8. NAVEGAÇÃO ENTRE OS MESES
         ======================================================== -->

    <section class="navegacao-mes">
        <a
            class="btn-mes"
            href="atendimentos.php?mes=<?= date('n', $dataMesAnterior) ?>&ano=<?= date('Y', $dataMesAnterior) ?><?= $filtroStatus !== '' ? '&status=' . urlencode($filtroStatus) : '' ?>"
            aria-label="Consultar mês anterior"
        >
            &larr; Mês anterior
        </a>

        <h2>
            <?= htmlspecialchars($meses[$mes], ENT_QUOTES, "UTF-8") ?>
            de <?= $ano ?>
        </h2>

        <a
            class="btn-mes"
            href="atendimentos.php?mes=<?= date('n', $dataProximoMes) ?>&ano=<?= date('Y', $dataProximoMes) ?><?= $filtroStatus !== '' ? '&status=' . urlencode($filtroStatus) : '' ?>"
            aria-label="Consultar próximo mês"
        >
            Próximo mês &rarr;
        </a>
    </section>

    <!-- ========================================================
         9. RESUMO DO MÊS
         ======================================================== -->

    <section class="resumo-atendimentos">
        <article class="cartao-resumo">
            <span class="titulo-resumo">Total de atendimentos</span>
            <strong><?= $resumo["total"] ?></strong>
        </article>

        <article class="cartao-resumo">
            <span class="titulo-resumo">Concluídos</span>
            <strong><?= $resumo["concluido"] ?></strong>
        </article>

        <article class="cartao-resumo">
            <span class="titulo-resumo">Pendentes</span>
            <strong><?= $resumo["pendente"] ?></strong>
        </article>

        <article class="cartao-resumo">
            <span class="titulo-resumo">Cancelados</span>
            <strong><?= $resumo["cancelado"] ?></strong>
        </article>

        <article class="cartao-resumo">
            <span class="titulo-resumo">Alterados</span>
            <strong><?= $resumo["alterado"] ?></strong>
        </article>
    </section>

    <!-- ========================================================
         10. FILTROS
         ======================================================== -->

    <section class="filtros-atendimentos">
        <h2>Filtrar atendimentos</h2>

        <div class="botoes-filtro">
            <!-- Retira os filtros e mostra todos os registros do mês. -->
            <a
                href="atendimentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>"
                class="btn-filtro <?= ($filtroStatus === '' && !$somenteHoje) ? 'ativo' : '' ?>"
            >
                Todos
            </a>

            <a
                href="atendimentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>&status=pendente"
                class="btn-filtro <?= ($filtroStatus === 'pendente' && !$somenteHoje) ? 'ativo' : '' ?>"
            >
                Pendentes
            </a>

            <a
                href="atendimentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>&status=concluido"
                class="btn-filtro <?= ($filtroStatus === 'concluido' && !$somenteHoje) ? 'ativo' : '' ?>"
            >
                Concluídos
            </a>

            <a
                href="atendimentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>&status=cancelado"
                class="btn-filtro <?= ($filtroStatus === 'cancelado' && !$somenteHoje) ? 'ativo' : '' ?>"
            >
                Cancelados
            </a>

            <a
                href="atendimentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>&status=alterado"
                class="btn-filtro <?= ($filtroStatus === 'alterado' && !$somenteHoje) ? 'ativo' : '' ?>"
            >
                Alterados
            </a>

            <!-- Destaca o filtro de hoje somente quando estiver ativo. -->
            <a
                href="atendimentos.php?hoje=1<?= $filtroStatus !== '' ? '&status=' . urlencode($filtroStatus) : '' ?>"
                class="btn-ver-hoje <?= $somenteHoje ? 'ativo' : '' ?>"
                aria-pressed="<?= $somenteHoje ? 'true' : 'false' ?>"
            >
                Ver agendamentos de hoje
            </a>
        </div>
    </section>

    <!-- ========================================================
         11. LISTA DE ATENDIMENTOS
         ======================================================== -->

    <section class="tabela-atendimentos">
        <div class="titulo-tabela">
            <h2>
                <?= $somenteHoje ? "Agendamentos de hoje" : "Lista de atendimentos" ?>
            </h2>

            <p>
                <?= count($atendimentos) ?>
                registro(s) encontrado(s)
                <?= $somenteHoje
                    ? "para hoje."
                    : "para " . htmlspecialchars($meses[$mes], ENT_QUOTES, "UTF-8") . " de " . $ano . "." ?>
            </p>
        </div>

        <div class="tabela-responsiva">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Cliente</th>
                        <th>Serviço</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($atendimentos)): ?>
                        <?php foreach ($atendimentos as $atendimento): ?>
                            <tr>
                                <td>
                                    <?= date(
                                        "d/m/Y",
                                        strtotime($atendimento["data_agendamento"])
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        substr((string)$atendimento["horario"], 0, 5),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento["nome_cliente"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </strong>

                                    <?php if (!empty($atendimento["telefone_cliente"])): ?>
                                        <small class="telefone-cliente">
                                            <?= htmlspecialchars(
                                                $atendimento["telefone_cliente"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $atendimento["nome_servico"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </strong>

                                    <?php if (!empty($atendimento["tipo_servico"])): ?>
                                        <small class="tipo-servico">
                                            <?= htmlspecialchars(
                                                ucfirst($atendimento["tipo_servico"]),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= formatarDinheiro($atendimento["preco_final"]) ?>
                                </td>

                                <td>
                                    <span class="status-atendimento status-<?= htmlspecialchars(
                                        $atendimento["status"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>">
                                        <?= htmlspecialchars(
                                            etiquetaStatus($atendimento["status"]),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>
                                </td>

                                <td class="acoes-atendimento">
                                    <?php if ($atendimento["status"] === "pendente"): ?>

                                        <!-- Formulário para concluir o atendimento. -->
                                        <form
                                            action="atualizar_status_agendamento.php"
                                            method="POST"
                                            onsubmit="return confirm('Deseja marcar este atendimento como concluído?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    $_SESSION["csrf_agendamentos"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="agendamento_id"
                                                value="<?= (int)$atendimento["id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="novo_status"
                                                value="concluido"
                                            >

                                            <input
                                                type="hidden"
                                                name="origem"
                                                value="atendimentos.php"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-concluir-agendamento"
                                            >
                                                Concluir
                                            </button>
                                        </form>

                                        <!-- Formulário para cancelar o agendamento. -->
                                        <form
                                            action="atualizar_status_agendamento.php"
                                            method="POST"
                                            onsubmit="return confirm('Deseja realmente cancelar este agendamento?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    $_SESSION["csrf_agendamentos"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="agendamento_id"
                                                value="<?= (int)$atendimento["id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="novo_status"
                                                value="cancelado"
                                            >

                                            <input
                                                type="hidden"
                                                name="origem"
                                                value="atendimentos.php"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-cancelar-agendamento"
                                            >
                                                Cancelar
                                            </button>
                                        </form>

                                    <?php else: ?>
                                        <span class="sem-acoes">
                                            Sem ações disponíveis
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="sem-atendimentos">
                                <?= $somenteHoje
                                    ? "Nenhum agendamento encontrado para hoje e para o filtro selecionado."
                                    : "Nenhum atendimento encontrado para este mês e filtro." ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>
</div>

</body>
</html>
```