<?php
// ============================================================
// INICIAR SESSÃO, CONEXÃO E VALIDAR ACESSO
// ============================================================
session_start();
require_once("conexao.php");

// Permite o acesso somente para clientes autenticadas.
if (!isset($_SESSION["usuario_id"]) || ($_SESSION["usuario_tipo"] ?? "") !== "cliente") {
    header("Location: login.php");
    exit();
}

// Recebe e valida os identificadores enviados pela página de serviços.
$servico_id = filter_input(INPUT_GET, "servico_id", FILTER_VALIDATE_INT);
$profissional_id = filter_input(INPUT_GET, "profissional_id", FILTER_VALIDATE_INT);
$data_recebida = $_GET["data"] ?? date("Y-m-d");

date_default_timezone_set("America/Sao_Paulo");
$data_hoje = date("Y-m-d");
$erro = "";
$servico = null;
$profissional = null;
$horarios_dia = [];
$status_dia = "";

// ============================================================
// FUNÇÕES AUXILIARES DE FORMATAÇÃO E NAVEGAÇÃO
// ============================================================

// Protege textos exibidos no HTML contra interpretação de código.
function escapar($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

// Converte durações cadastradas em minutos, aceitando formatos comuns.
function converterDuracaoMinutos($duracao)
{
    $texto = trim((string)$duracao);

    if (ctype_digit($texto)) {
        return (int)$texto;
    }

    if (preg_match('/^(\d+)\s*h(?:\s*(\d+)\s*(?:min)?)?$/u', mb_strtolower($texto), $partes)) {
        return ((int)$partes[1] * 60) + (isset($partes[2]) ? (int)$partes[2] : 0);
    }

    if (preg_match('/^(\d+)\s*min$/u', mb_strtolower($texto), $partes)) {
        return (int)$partes[1];
    }

    if (preg_match('/^(\d+):(\d+)$/', $texto, $partes)) {
        return ((int)$partes[1] * 60) + (int)$partes[2];
    }

    return 0;
}

// Apresenta a duração em um formato simples para a cliente.
function formatarDuracaoAmigavel($minutos)
{
    $horas = intdiv((int)$minutos, 60);
    $minutos_restantes = (int)$minutos % 60;

    if ($horas > 0 && $minutos_restantes > 0) {
        return "{$horas}h{$minutos_restantes}min";
    }

    if ($horas > 0) {
        return "{$horas}h";
    }

    return "{$minutos_restantes}min";
}

// Monta os links mantendo o serviço, a profissional e a data escolhida.
function urlAgenda($servico_id, $profissional_id, $data)
{
    return "agenda.php?" . http_build_query([
        "servico_id" => $servico_id,
        "profissional_id" => $profissional_id,
        "data" => $data
    ]);
}

// Valida datas recebidas pela URL para impedir datas inexistentes.
function dataValida($valor)
{
    if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        return false;
    }

    $data = DateTime::createFromFormat("!Y-m-d", $valor);
    return $data && $data->format("Y-m-d") === $valor;
}

// ============================================================
// VALIDAR A DATA E DEFINIR O MÊS DO CALENDÁRIO
// ============================================================

// Se a data recebida for inválida, utiliza a data atual.
if (!dataValida($data_recebida)) {
    $data_recebida = $data_hoje;
}

// Não permite abrir uma data selecionada anterior ao dia atual.
$data_selecionada = ($data_recebida < $data_hoje) ? $data_hoje : $data_recebida;
$data_obj_selecionada = new DateTime($data_selecionada);
$mes_obj = new DateTime($data_obj_selecionada->format("Y-m-01"));
$mes_anterior_obj = (clone $mes_obj)->modify("-1 month");
$mes_seguinte_obj = (clone $mes_obj)->modify("+1 month");
$mes = (int)$mes_obj->format("m");
$ano = (int)$mes_obj->format("Y");

$nome_mes = [
    1 => "Janeiro", 2 => "Fevereiro", 3 => "Março", 4 => "Abril",
    5 => "Maio", 6 => "Junho", 7 => "Julho", 8 => "Agosto",
    9 => "Setembro", 10 => "Outubro", 11 => "Novembro", 12 => "Dezembro"
];

// ============================================================
// CARREGAR O SERVIÇO E OS DADOS DA PROFISSIONAL
// ============================================================

if (!$servico_id || !$profissional_id) {
    $erro = "Serviço ou profissional não informado.";
} else {
    try {
        // Usa a vinculação para confirmar que o serviço pertence à profissional.
        $consulta = $conexao->prepare("
            SELECT
                s.id,
                s.nome_servico,
                s.duracao,
                s.preco,
                v.duracao_customizada,
                v.preco_customizado,
                u.nome AS profissional_nome
            FROM servicos s
            INNER JOIN vinculacao v
                ON v.servico_id = s.id
               AND v.profissional_id = :pid
            INNER JOIN usuarios u ON u.id = v.profissional_id
            WHERE s.id = :sid
              AND s.status = 'ativo'
              AND u.status = 'ativo'
            LIMIT 1
        ");
        $consulta->execute([":sid" => $servico_id, ":pid" => $profissional_id]);
        $servico = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$servico) {
            $erro = "Serviço indisponível para esta profissional.";
        } else {
            // Prioriza preço e duração personalizados, quando cadastrados.
            $duracao_texto = !empty($servico["duracao_customizada"])
                ? $servico["duracao_customizada"]
                : $servico["duracao"];
            $duracao_servico = converterDuracaoMinutos($duracao_texto);
            $duracao_exibicao = formatarDuracaoAmigavel($duracao_servico);
            $preco_final = ($servico["preco_customizado"] !== null && $servico["preco_customizado"] !== "")
                ? (float)$servico["preco_customizado"]
                : (float)$servico["preco"];
            $profissional = $servico["profissional_nome"];

            if ($duracao_servico <= 0) {
                $erro = "A duração deste serviço precisa ser conferida pela profissional.";
            }
        }
    } catch (Throwable $e) {
        error_log("Erro ao carregar serviço na agenda: " . $e->getMessage());
        $erro = "Não foi possível carregar os dados do serviço. Tente novamente.";
    }
}

// ============================================================
// MONTAR AS CÉLULAS DO CALENDÁRIO MENSAL
// ============================================================

$dias_calendario = [];

if (!$erro && $servico) {
    // Cria espaços antes do primeiro dia para alinhar domingo a sábado.
    $primeiro_dia_mes = new DateTime(sprintf("%04d-%02d-01", $ano, $mes));
    $dia_semana_inicio = (int)$primeiro_dia_mes->format("w");
    $quantidade_dias = (int)$primeiro_dia_mes->format("t");

    for ($i = 0; $i < $dia_semana_inicio; $i++) {
        $dias_calendario[] = null;
    }

    for ($dia = 1; $dia <= $quantidade_dias; $dia++) {
        $dias_calendario[] = $dia;
    }

    // Completa a última semana para manter a grade alinhada.
    while (count($dias_calendario) % 7 !== 0) {
        $dias_calendario[] = null;
    }

    // Consulta os horários somente para a data selecionada.
    $dia_semana_selecionado = (int)$data_obj_selecionada->format("w");

    try {
        // Verifica se a profissional liberou a data para agendamentos.
        $consulta = $conexao->prepare("
            SELECT COUNT(*)
            FROM liberacao_agenda
            WHERE profissional_id = :pid
              AND :data_inicio >= data_inicio
              AND :data_fim <= data_fim
        ");
        $consulta->execute([
            ":pid" => $profissional_id,
            ":data_inicio" => $data_selecionada,
            ":data_fim" => $data_selecionada
        ]);
        $liberada = (int)$consulta->fetchColumn() > 0;

        // Carrega a jornada configurada para o dia da semana selecionado.
        $consulta = $conexao->prepare("
            SELECT trabalha, horario_inicio, ultimo_horario, inicio_almoco, fim_almoco
            FROM horarios
            WHERE profissional_id = :pid AND dia_semana = :dia
            LIMIT 1
        ");
        $consulta->execute([
            ":pid" => $profissional_id,
            ":dia" => $dia_semana_selecionado
        ]);
        $jornada = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($data_selecionada < $data_hoje) {
            $status_dia = "Escolha uma data de hoje em diante.";
        } elseif (!$liberada) {
            $status_dia = "Esta data ainda não foi liberada para agendamentos.";
        } elseif (!$jornada || (int)$jornada["trabalha"] !== 1) {
            $status_dia = "A profissional não atende neste dia.";
        } elseif (empty($jornada["horario_inicio"]) || empty($jornada["ultimo_horario"])) {
            $status_dia = "Os horários de atendimento ainda não foram configurados.";
        } else {
            // Gera opções respeitando a duração do serviço.
            $inicio_jornada = new DateTime($data_selecionada . " " . $jornada["horario_inicio"]);
            $fim_jornada = new DateTime($data_selecionada . " " . $jornada["ultimo_horario"]);
            $passo_minutos = 30;
            $agora = new DateTime();
            $inicio_almoco = !empty($jornada["inicio_almoco"])
                ? new DateTime($data_selecionada . " " . $jornada["inicio_almoco"])
                : null;
            $fim_almoco = !empty($jornada["fim_almoco"])
                ? new DateTime($data_selecionada . " " . $jornada["fim_almoco"])
                : null;

            // Busca todos os agendamentos não cancelados da profissional nesse dia.
            $consulta = $conexao->prepare("
                SELECT a.horario, s.duracao
                FROM agendamentos a
                LEFT JOIN servicos s ON s.id = a.servico_id
                WHERE a.profissional_id = :pid
                  AND a.data_agendamento = :data
                  AND a.status <> 'cancelado'
            ");
            $consulta->execute([
                ":pid" => $profissional_id,
                ":data" => $data_selecionada
            ]);
            $agendamentos_existentes = $consulta->fetchAll(PDO::FETCH_ASSOC);

            // Busca os bloqueios de horário do dia selecionado.
            $consulta = $conexao->prepare("
                SELECT horario_inicio, horario_fim
                FROM bloqueios_agenda
                WHERE profissional_id = :pid
                  AND data_bloqueio = :data
            ");
            $consulta->execute([
                ":pid" => $profissional_id,
                ":data" => $data_selecionada
            ]);
            $bloqueios = $consulta->fetchAll(PDO::FETCH_ASSOC);

            // Avança de 30 em 30 minutos para permitir encaixes entre serviços.
            $cursor = clone $inicio_jornada;
            while (true) {
                $fim_opcao = (clone $cursor)->modify("+{$duracao_servico} minutes");

                // O atendimento precisa terminar até o limite da jornada.
                if ($fim_opcao > $fim_jornada) {
                    break;
                }

                $horario_texto = $cursor->format("H:i");
                $ocupado = false;

                // Não permite horários que já passaram no dia atual.
                if ($data_selecionada === $data_hoje && $cursor <= $agora) {
                    $ocupado = true;
                }

                // Impede que o serviço atravesse o intervalo de almoço.
                if ($inicio_almoco && $fim_almoco && $cursor < $fim_almoco && $fim_opcao > $inicio_almoco) {
                    $ocupado = true;
                }

                // Impede sobreposição com qualquer agendamento existente.
                foreach ($agendamentos_existentes as $agendamento) {
                    $inicio_existente = new DateTime($data_selecionada . " " . $agendamento["horario"]);
                    $duracao_existente = converterDuracaoMinutos($agendamento["duracao"] ?? "");
                    if ($duracao_existente <= 0) {
                        $duracao_existente = 30;
                    }
                    $fim_existente = (clone $inicio_existente)->modify("+{$duracao_existente} minutes");

                    if ($cursor < $fim_existente && $fim_opcao > $inicio_existente) {
                        $ocupado = true;
                        break;
                    }
                }

                // Impede sobreposição com bloqueios e folgas da profissional.
                foreach ($bloqueios as $bloqueio) {
                    $inicio_bloqueio = new DateTime($data_selecionada . " " . $bloqueio["horario_inicio"]);
                    $fim_bloqueio = new DateTime($data_selecionada . " " . $bloqueio["horario_fim"]);

                    if ($cursor < $fim_bloqueio && $fim_opcao > $inicio_bloqueio) {
                        $ocupado = true;
                        break;
                    }
                }

                // Só mostra opções realmente disponíveis para seleção.
                if (!$ocupado) {
                    $horarios_dia[] = [
                        "horario" => $horario_texto,
                        "link" => "confirmar_agendamento.php?" . http_build_query([
                            "servico_id" => $servico_id,
                            "profissional_id" => $profissional_id,
                            "data" => $data_selecionada,
                            "horario" => $horario_texto
                        ])
                    ];
                }

                $cursor->modify("+{$passo_minutos} minutes");
            }

            if (empty($horarios_dia)) {
                $status_dia = "Não há horários disponíveis para esta data.";
            }
        }
    } catch (Throwable $e) {
        error_log("Erro ao carregar disponibilidade da agenda: " . $e->getMessage());
        $status_dia = "Não foi possível consultar os horários desta data.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escolher Horário | TCC Estética</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-cliente pagina-agenda">
    <!-- Cabeçalho compartilhado com as páginas da cliente. -->
    <header class="cabecalho-cliente">
        <div class="logo-cliente"><h1>Estética</h1></div>
        <nav class="menu-cliente">
            <a href="painel_cliente.php">Início</a>
            <a href="servicos.php" class="ativo">Serviços</a>
            <a href="meus_agendamentos.php">Meus Agendamentos</a>
            <a href="logout.php" class="menu-sair">Sair</a>
        </nav>
    </header>

    <main class="agenda-container">
        <?php if ($erro): ?>
            <!-- Apresenta erros de parâmetro ou de carregamento sem quebrar a página. -->
            <section class="agenda-cabecalho">
                <h2>Escolha seu horário</h2>
                <div class="agenda-mensagem agenda-mensagem-erro">
                    <?= escapar($erro) ?>
                </div>
                <a href="servicos.php" class="botao-voltar">Voltar aos serviços</a>
            </section>
        <?php else: ?>
            <!-- Mantém os dados principais do serviço e da profissional em destaque. -->
            <section class="agenda-cabecalho">
                <div class="agenda-titulo">
                    <span class="agenda-label">AGENDAMENTO</span>
                    <h2>Escolha seu horário</h2>
                    <p>Selecione uma data e confira os horários disponíveis.</p>
                </div>

                <div class="agenda-resumo">
                    <div class="agenda-resumo-profissional">
                        <span class="agenda-avatar" aria-hidden="true"><?= escapar(mb_substr($profissional, 0, 1, "UTF-8")) ?></span>
                        <div>
                            <span class="agenda-resumo-legenda">PROFISSIONAL</span>
                            <strong><?= escapar($profissional) ?></strong>
                        </div>
                    </div>
                    <div class="agenda-resumo-servico">
                        <span class="agenda-resumo-legenda">SERVIÇO</span>
                        <strong><?= escapar($servico["nome_servico"]) ?></strong>
                    </div>
                    <div class="agenda-resumo-detalhes">
                        <div class="agenda-detalhe">
                            <span>Duração</span>
                            <strong><?= escapar($duracao_exibicao) ?></strong>
                        </div>
                        <div class="agenda-detalhe">
                            <span>Valor do serviço</span>
                            <strong>R$ <?= number_format($preco_final, 2, ",", ".") ?></strong>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Calendário mensal com navegação por setas. -->
            <section class="agenda-calendario">
                <div class="agenda-calendario-topo">
                    <div>
                        <span class="agenda-label">DISPONIBILIDADE</span>
                        <h3>Selecione uma data</h3>
                    </div>
                    <div class="agenda-navegacao-mes">
                        <?php if ($mes_obj->format("Y-m") > date("Y-m")): ?>
                            <a class="agenda-seta" aria-label="Mês anterior"
                               href="<?= escapar(urlAgenda($servico_id, $profissional_id, $mes_anterior_obj->format("Y-m-01"))) ?>">
                                <span aria-hidden="true">&#8249;</span>
                            </a>
                        <?php else: ?>
                            <span class="agenda-seta desativada" aria-hidden="true">&#8249;</span>
                        <?php endif; ?>
                        <strong><?= escapar($nome_mes[$mes]) ?> de <?= $ano ?></strong>
                        <a class="agenda-seta" aria-label="Próximo mês"
                           href="<?= escapar(urlAgenda($servico_id, $profissional_id, $mes_seguinte_obj->format("Y-m-01"))) ?>">
                            <span aria-hidden="true">&#8250;</span>
                        </a>
                    </div>
                </div>

                <div class="agenda-calendario-grade">
                    <?php foreach (["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"] as $nome_dia): ?>
                        <div class="agenda-dia-semana"><?= escapar($nome_dia) ?></div>
                    <?php endforeach; ?>

                    <?php foreach ($dias_calendario as $dia_numero): ?>
                        <?php if ($dia_numero === null): ?>
                            <div class="agenda-dia-vazio" aria-hidden="true"></div>
                        <?php else: ?>
                            <?php
                            // Identifica a data e o estado visual de cada dia.
                            $data_dia = sprintf("%04d-%02d-%02d", $ano, $mes, $dia_numero);
                            $dia_passado = $data_dia < $data_hoje;
                            $dia_selecionado = $data_dia === $data_selecionada;
                            $classes_dia = ["agenda-dia-calendario"];

                            if ($dia_passado) {
                                $classes_dia[] = "dia-passado";
                            }
                            if ($dia_selecionado) {
                                $classes_dia[] = "dia-selecionado";
                            }
                            ?>
                            <a class="<?= escapar(implode(" ", $classes_dia)) ?>"
                               href="<?= escapar(urlAgenda($servico_id, $profissional_id, $data_dia)) ?>"
                               aria-label="<?= escapar($dia_numero . " de " . $nome_mes[$mes]) ?>"
                               <?= $dia_selecionado ? 'aria-current="date"' : '' ?>>
                                <span><?= (int)$dia_numero ?></span>
                                <?php if ($dia_selecionado): ?>
                                    <small>Selecionado</small>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="agenda-legenda">
                    <span><i class="legenda-ponto ponto-selecionado"></i> Data selecionada</span>
                    <span><i class="legenda-ponto ponto-passado"></i> Data passada</span>
                </div>
            </section>

            <!-- Mostra apenas os horários referentes à data escolhida. -->
            <section class="agenda-horarios">
                <div class="agenda-horarios-topo">
                    <div>
                        <span class="agenda-label">HORÁRIOS</span>
                        <h3><?= escapar($data_obj_selecionada->format("d/m/Y")) ?></h3>
                    </div>
                    <span class="agenda-horarios-ajuda">Selecione um horário para continuar</span>
                </div>

                <?php if ($status_dia !== ""): ?>
                    <div class="agenda-mensagem agenda-mensagem-info"><?= escapar($status_dia) ?></div>
                <?php endif; ?>

                <?php if (!empty($horarios_dia)): ?>
                    <div class="agenda-horarios-grade">
                        <?php foreach ($horarios_dia as $horario): ?>
                            <a class="agenda-horario-opcao" href="<?= escapar($horario["link"]) ?>">
                                <?= escapar($horario["horario"]) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Mantém o caminho de retorno para o catálogo de serviços. -->
            <div class="agenda-rodape">
                <a href="servicos.php" class="botao-voltar">Voltar aos serviços</a>
                <p>O horário será confirmado após a próxima etapa do agendamento.</p>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
