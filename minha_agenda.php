<?php
session_start();
require_once("conexao.php");

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "profissional") {
    header("Location: login.php");
    exit();
}

$profissional_id = (int)$_SESSION["usuario_id"];
date_default_timezone_set("America/Sao_Paulo");

$mes_atual = date("m");
$ano_atual = date("Y");
$mes = $_GET["mes"] ?? $mes_atual;
$ano = $_GET["ano"] ?? $ano_atual;
$aba = $_GET["aba"] ?? "calendario";

$mes = str_pad(min(max((int)$mes, 1), 12), 2, "0", STR_PAD_LEFT);
$ano = min(max((int)$ano, 2020), 2100);

$mensagem_sucesso = "";
$mensagem_erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"])) {
    try {
        $acao = $_POST["acao"];

        if ($acao === "salvar_horarios") {
            for ($dia_semana = 0; $dia_semana <= 6; $dia_semana++) {
                $trabalha = isset($_POST["trabalha"][$dia_semana]) ? 1 : 0;
                $horario_inicio = trim($_POST["horario_inicio"][$dia_semana] ?? "");
                $ultimo_horario = trim($_POST["ultimo_horario"][$dia_semana] ?? "");
                $inicio_almoco = trim($_POST["inicio_almoco"][$dia_semana] ?? "");
                $fim_almoco = trim($_POST["fim_almoco"][$dia_semana] ?? "");
                $dia_semana_int = (int)$dia_semana;

                $verifica = $conexao->prepare("SELECT id FROM horarios WHERE profissional_id = :pid AND dia_semana = :dia LIMIT 1");
                $verifica->execute([":pid" => $profissional_id, ":dia" => $dia_semana_int]);
                $existe = $verifica->fetch();

                if ($existe) {
                    $comando = $conexao->prepare("UPDATE horarios SET trabalha = :trabalha, horario_inicio = :hi, ultimo_horario = :uh, inicio_almoco = :ia, fim_almoco = :fa WHERE profissional_id = :pid AND dia_semana = :dia");
                } else {
                    $comando = $conexao->prepare("INSERT INTO horarios (profissional_id, dia_semana, trabalha, horario_inicio, ultimo_horario, inicio_almoco, fim_almoco) VALUES (:pid, :dia, :trabalha, :hi, :uh, :ia, :fa)");
                }
                $comando->execute([
                    ":pid" => $profissional_id, ":dia" => $dia_semana_int, ":trabalha" => $trabalha,
                    ":hi" => $horario_inicio ?: null, ":uh" => $ultimo_horario ?: null,
                    ":ia" => $inicio_almoco ?: null, ":fa" => $fim_almoco ?: null
                ]);
            }
            $mensagem_sucesso = "Horários salvos!";
            $aba = "horarios";
        }

        elseif ($acao === "bloquear_dia") {
            $data_bloqueio = trim($_POST["data_bloqueio"] ?? "");
            $motivo = trim($_POST["motivo"] ?? "Folga");
            $tipo_bloqueio = $_POST["tipo_bloqueio"] ?? "dia_inteiro";
            if (!$data_bloqueio) throw new Exception("Informe a data.");
            
            if ($tipo_bloqueio === "horario") {
                $horario_inicio = trim($_POST["horario_inicio_bloqueio"] ?? "");
                $horario_fim = trim($_POST["horario_fim_bloqueio"] ?? "");
                if (!$horario_inicio || !$horario_fim) throw new Exception("Informe o horário.");
            } else {
                $horario_inicio = "00:00";
                $horario_fim = "23:59";
            }
            $comando = $conexao->prepare("INSERT INTO bloqueios_agenda (profissional_id, data_bloqueio, horario_inicio, horario_fim, motivo) VALUES (:pid, :data, :hi, :hf, :motivo)");
            $comando->execute([":pid" => $profissional_id, ":data" => $data_bloqueio, ":hi" => $horario_inicio, ":hf" => $horario_fim, ":motivo" => $motivo]);
            $mensagem_sucesso = "Bloqueio registrado!";
            $aba = "bloqueios";
        }

        elseif ($acao === "excluir_bloqueio") {
            $bloqueio_id = (int)($_POST["bloqueio_id"] ?? 0);
            $conexao->prepare("DELETE FROM bloqueios_agenda WHERE id = :id AND profissional_id = :pid")
                ->execute([":id" => $bloqueio_id, ":pid" => $profissional_id]);
            $mensagem_sucesso = "Bloqueio removido!";
            $aba = "bloqueios";
        }

        elseif ($acao === "liberar_dias") {
            $dias_selecionados = $_POST["dias"] ?? [];
            if (empty($dias_selecionados)) throw new Exception("Selecione pelo menos um dia.");
            
            foreach ($dias_selecionados as $data) {
                $verifica = $conexao->prepare("SELECT id FROM liberacao_agenda WHERE profissional_id = :pid AND data_inicio = :dt AND data_fim = :dt");
                $verifica->execute([":pid" => $profissional_id, ":dt" => $data]);
                if (!$verifica->fetch()) {
                    $ins = $conexao->prepare("INSERT INTO liberacao_agenda (profissional_id, tipo, valor, data_inicio, data_fim, criado_em) VALUES (:pid, 'dia', :dt, :dt, :dt, NOW())");
                    $ins->execute([":pid" => $profissional_id, ":dt" => $data]);
                }
            }
            $mensagem_sucesso = "Dias liberados com sucesso!";
            $aba = "liberar";
        }

        elseif ($acao === "remover_liberacao") {
            $dias_selecionados = $_POST["dias"] ?? [];
            if (empty($dias_selecionados)) throw new Exception("Selecione pelo menos um dia.");
            
            foreach ($dias_selecionados as $data) {
                $conexao->prepare("DELETE FROM liberacao_agenda WHERE profissional_id = :pid AND data_inicio = :dt AND data_fim = :dt")
                    ->execute([":pid" => $profissional_id, ":dt" => $data]);
            }
            $mensagem_sucesso = "Liberações removidas!";
            $aba = "liberar";
        }

        elseif ($acao === "cancelar_agendamento") {
            $agendamento_id = (int)($_POST["agendamento_id"] ?? 0);
            $conexao->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id = :id AND profissional_id = :pid")
                ->execute([":id" => $agendamento_id, ":pid" => $profissional_id]);
            $mensagem_sucesso = "Agendamento cancelado!";
        }
    } catch (Exception $erro) {
        $mensagem_erro = "Erro: " . $erro->getMessage();
    }
}

$horarios_dia = [];
for ($i = 0; $i <= 6; $i++) {
    $horarios_dia[$i] = ["trabalha" => 0, "horario_inicio" => "08:00", "ultimo_horario" => "19:00", "inicio_almoco" => "12:00", "fim_almoco" => "13:00"];
}
try {
    $consulta = $conexao->prepare("SELECT dia_semana, trabalha, horario_inicio, ultimo_horario, inicio_almoco, fim_almoco FROM horarios WHERE profissional_id = :pid");
    $consulta->execute([":pid" => $profissional_id]);
    foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $dia = (int)$h["dia_semana"];
        if ($dia >= 0 && $dia <= 6) {
            $horarios_dia[$dia] = [
                "trabalha" => (int)$h["trabalha"],
                "horario_inicio" => $h["horario_inicio"] ?? "08:00",
                "ultimo_horario" => $h["ultimo_horario"] ?? "19:00",
                "inicio_almoco" => $h["inicio_almoco"] ?? "12:00",
                "fim_almoco" => $h["fim_almoco"] ?? "13:00"
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar horários: " . $e->getMessage());
}

$agendamentos_por_dia = [];
try {
    $consulta = $conexao->prepare("SELECT a.id, a.data_agendamento, a.horario, a.status, c.nome AS nome_cliente, s.nome_servico FROM agendamentos a LEFT JOIN usuarios c ON a.cliente_id = c.id LEFT JOIN servicos s ON a.servico_id = s.id WHERE a.profissional_id = :pid AND YEAR(a.data_agendamento) = :ano AND MONTH(a.data_agendamento) = :mes ORDER BY a.data_agendamento, a.horario");
    $consulta->execute([":pid" => $profissional_id, ":ano" => $ano, ":mes" => $mes]);
    while ($ag = $consulta->fetch(PDO::FETCH_ASSOC)) {
        $agendamentos_por_dia[$ag["data_agendamento"]][] = $ag;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar agendamentos: " . $e->getMessage());
}

$bloqueios = [];
try {
    $consulta = $conexao->prepare("SELECT id, data_bloqueio, horario_inicio, horario_fim, motivo FROM bloqueios_agenda WHERE profissional_id = :pid AND YEAR(data_bloqueio) = :ano AND MONTH(data_bloqueio) = :mes");
    $consulta->execute([":pid" => $profissional_id, ":ano" => $ano, ":mes" => $mes]);
    while ($b = $consulta->fetch(PDO::FETCH_ASSOC)) {
        $bloqueios[$b["data_bloqueio"]][] = $b;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar bloqueios: " . $e->getMessage());
}

$liberacoes = [];
$dias_liberados = [];
try {
    $consulta = $conexao->prepare("SELECT * FROM liberacao_agenda WHERE profissional_id = :pid ORDER BY data_inicio DESC");
    $consulta->execute([":pid" => $profissional_id]);
    $liberacoes = $consulta->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($liberacoes as $lib) {
        $inicio = new DateTime($lib["data_inicio"]);
        $fim = new DateTime($lib["data_fim"]);
        while ($inicio <= $fim) {
            $dias_liberados[$inicio->format("Y-m-d")] = true;
            $inicio->modify("+1 day");
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar liberações: " . $e->getMessage());
}

$nomes_meses = [
    "01" => "Janeiro", "02" => "Fevereiro", "03" => "Março", "04" => "Abril",
    "05" => "Maio", "06" => "Junho", "07" => "Julho", "08" => "Agosto",
    "09" => "Setembro", "10" => "Outubro", "11" => "Novembro", "12" => "Dezembro"
];
$nome_mes = $nomes_meses[$mes] ?? "";
$primeiro_dia_semana = date("w", strtotime("$ano-$mes-01"));
$quantos_dias = date("t", strtotime("$ano-$mes-01"));
$mes_anterior = $mes == 1 ? 12 : $mes - 1;
$ano_anterior = $mes == 1 ? $ano - 1 : $ano;
$proximo_mes = $mes == 12 ? 1 : $mes + 1;
$ano_proximo = $mes == 12 ? $ano + 1 : $ano;
$nomes_dias_semana = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];
$hoje_data = date("Y-m-d");

// Valida o dia selecionado para abrir os detalhes somente dentro do mês exibido.
$dia_selecionado = trim($_GET["dia"] ?? "");
$data_selecionada_obj = DateTime::createFromFormat("!Y-m-d", $dia_selecionado);
if (
    !$data_selecionada_obj ||
    $data_selecionada_obj->format("Y-m-d") !== $dia_selecionado ||
    $data_selecionada_obj->format("Y") !== (string)$ano ||
    $data_selecionada_obj->format("m") !== $mes
) {
    $dia_selecionado = "";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Agenda</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<aside class="sidebar">
    <div class="logo">
        <h1>Estética</h1>
        <p><?= htmlspecialchars($_SESSION["especialidade"] ?? "Profissional") ?></p>
    </div>
    <nav class="menu">
        <a href="painel_profissional.php">Início</a>
        <a href="atendimentos.php">Atendimentos</a>
        <a href="minha_agenda.php" class="ativo">Minha Agenda</a>
        <a href="meus_servicos.php">Meus Serviços</a>
        <div class="separador"></div>
        <a href="meu_perfil.php">Meu Perfil</a>
    </nav>
    <a href="logout.php" class="btn-sair">Sair</a>
</aside>

<main class="main">
    <section class="topo">
        <div>
            <h2>Minha Agenda</h2>
            <p>Gerencie seus horários, bloqueios e períodos liberados</p>
        </div>
        <div class="data"><?= date("l, d/m/Y") ?></div>
    </section>

    <?php if ($mensagem_sucesso): ?>
        <div class="alerta alerta-sucesso"><?= htmlspecialchars($mensagem_sucesso) ?></div>
    <?php endif; ?>
    <?php if ($mensagem_erro): ?>
        <div class="alerta alerta-erro"><?= htmlspecialchars($mensagem_erro) ?></div>
    <?php endif; ?>

    <div class="botoes-acao">
        <a href="agendar_cliente.php" class="btn btn-sucesso">Reservar Horário</a>
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&aba=liberar" class="btn btn-primario">Liberar Agendamentos</a>
    </div>

    <div class="abas-linha">
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&aba=calendario" class="aba-botao <?= $aba==='calendario'?'ativa':'' ?>">Calendário</a>
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&aba=horarios" class="aba-botao <?= $aba==='horarios'?'ativa':'' ?>">Meus Horários</a>
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&aba=bloqueios" class="aba-botao <?= $aba==='bloqueios'?'ativa':'' ?>">Folgas/Bloqueios</a>
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&aba=liberar" class="aba-botao <?= $aba==='liberar'?'ativa':'' ?>">Liberação</a>
    </div>

    <!-- ABA 1 — CALENDÁRIO -->
    <div class="aba-conteudo <?= $aba==='calendario'?'visivel':'' ?>">
        <div class="calendario">
            <div class="calendario-cabecalho">
                <a href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>&aba=calendario">◀</a>
                <h3><?= $nome_mes ?> de <?= $ano ?></h3>
                <a href="?mes=<?= $proximo_mes ?>&ano=<?= $ano_proximo ?>&aba=calendario">▶</a>
            </div>
            <div class="calendario-grade">
                <?php foreach ($nomes_dias_semana as $n): ?>
                    <div class="calendario-dia-nome"><?= $n ?></div>
                <?php endforeach; ?>
                <?php for ($i=0; $i<$primeiro_dia_semana; $i++): ?>
                    <div class="calendario-dia calendario-dia-vazio"></div>
                <?php endfor; ?>
                <?php
                for ($dia=1; $dia<=$quantos_dias; $dia++):
                    $dia_sem = date("w", strtotime("$ano-$mes-".str_pad($dia,2,"0",STR_PAD_LEFT)));
                    $data = sprintf("%04d-%02d-%02d", $ano, $mes, $dia);
                    $hoje = $data === $hoje_data;
                    $config_dia = $horarios_dia[$dia_sem] ?? ["trabalha" => 0];
                    $trabalha = (int)($config_dia["trabalha"] ?? 0);
                    $tem_bloqueio = isset($bloqueios[$data]);
                    $liberado = isset($dias_liberados[$data]);
                    
                    $classe = "";
                    if ($tem_bloqueio) $classe = "dia-bloqueado";
                    elseif ($liberado) $classe = "dia-liberado";
                    elseif ($trabalha === 1) $classe = "dia-trabalho";
                    if ($hoje) $classe .= " dia-hoje";
                ?>
                    <!-- Cada dia abre os detalhes da data selecionada sem mudar as funções internas dos botões. -->
                    <div class="calendario-dia calendario-dia-clicavel <?= trim($classe) ?> <?= $dia_selecionado === $data ? 'dia-selecionado' : '' ?>"
                         role="link"
                         tabindex="0"
                         aria-label="Ver agendamentos e bloqueios de <?= date('d/m/Y', strtotime($data)) ?>"
                         data-dia-url="?mes=<?= $mes ?>&amp;ano=<?= $ano ?>&amp;aba=calendario&amp;dia=<?= $data ?>#detalhes-dia">
                        <div class="calendario-dia-numero"><?= $dia ?></div>
                        <?php if ($liberado): ?>
                            <span class="etiqueta-liberado">Liberado</span>
                        <?php endif; ?>
                        <?php if ($tem_bloqueio): ?>
                            <span class="etiqueta-bloqueado">Bloqueado</span>
                            <!-- Exibe um resumo do período e do motivo dentro do dia bloqueado. -->
                            <?php foreach ($bloqueios[$data] as $bloqueio_calendario):
                                $inicio_cal = substr($bloqueio_calendario["horario_inicio"] ?? "00:00", 0, 5);
                                $fim_cal = substr($bloqueio_calendario["horario_fim"] ?? "23:59", 0, 5);
                                $dia_inteiro_cal = ($inicio_cal === "00:00" && ($fim_cal === "23:59" || $fim_cal === "00:00"));
                            ?>
                                <small class="bloqueio-resumo-calendario">
                                    <?= $dia_inteiro_cal ? "Dia inteiro" : htmlspecialchars($inicio_cal . " às " . $fim_cal) ?>
                                    <?php if (!empty($bloqueio_calendario["motivo"])): ?>
                                        <span><?= htmlspecialchars($bloqueio_calendario["motivo"]) ?></span>
                                    <?php endif; ?>
                                </small>
                            <?php endforeach; ?>
                        <?php elseif ($trabalha === 1): ?>
                            <small class="horario-dia">
                                <?= substr($config_dia["horario_inicio"] ?? "08:00",0,5) ?> -
                                <?= substr($config_dia["ultimo_horario"] ?? "19:00",0,5) ?>
                            </small>
                        <?php endif; ?>
                        <?php if (isset($agendamentos_por_dia[$data])): ?>
                            <?php foreach ($agendamentos_por_dia[$data] as $ag):
                                $st = "etiqueta-pendente";
                                if (($ag["status"] ?? "") === "concluido") $st = "etiqueta-concluido";
                                elseif (($ag["status"] ?? "") === "cancelado") $st = "etiqueta-cancelado";
                                elseif (($ag["status"] ?? "") === "alterado") $st = "etiqueta-alterado";
                            ?>
                                <div class="agendamento-item">
                                    <strong><?= substr($ag["horario"],0,5) ?></strong>
                                    <?= htmlspecialchars(mb_substr($ag["nome_cliente"] ?? "Cliente",0,12)) ?>
                                    <?php if(($ag["status"] ?? "") !== "cancelado"): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Cancelar este agendamento?')">
                                            <input type="hidden" name="acao" value="cancelar_agendamento">
                                            <input type="hidden" name="agendamento_id" value="<?= $ag["id"] ?>">
                                            <button type="submit" class="btn-cancelar">✖</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Detalhes do dia selecionado: agendamentos completos e bloqueios registrados. -->
        <?php if ($dia_selecionado !== ""): ?>
            <section class="detalhes-dia-agenda" id="detalhes-dia">
                <h3>Agenda de <?= date("d/m/Y", strtotime($dia_selecionado)) ?></h3>
                <p class="detalhes-dia-subtitulo">
                    <?= ["Domingo", "Segunda-feira", "Terça-feira", "Quarta-feira", "Quinta-feira", "Sexta-feira", "Sábado"][(int)date("w", strtotime($dia_selecionado))] ?>
                </p>

                <h4>Agendamentos do dia</h4>
                <?php if (!empty($agendamentos_por_dia[$dia_selecionado])): ?>
                    <div class="lista-detalhes-agendamentos">
                        <?php foreach ($agendamentos_por_dia[$dia_selecionado] as $ag_detalhe):
                            $status_detalhe = $ag_detalhe["status"] ?? "pendente";
                            $status_classe = "etiqueta-pendente";
                            if ($status_detalhe === "concluido") $status_classe = "etiqueta-concluido";
                            elseif ($status_detalhe === "cancelado") $status_classe = "etiqueta-cancelado";
                            elseif ($status_detalhe === "alterado") $status_classe = "etiqueta-alterado";
                            $status_texto = ["pendente" => "Pendente", "concluido" => "Concluído", "cancelado" => "Cancelado", "alterado" => "Alterado"][$status_detalhe] ?? ucfirst($status_detalhe);
                        ?>
                            <article class="detalhe-agendamento-item">
                                <div class="detalhe-agendamento-horario"><?= htmlspecialchars(substr($ag_detalhe["horario"] ?? "--:--", 0, 5)) ?></div>
                                <div class="detalhe-agendamento-informacoes">
                                    <strong><?= htmlspecialchars($ag_detalhe["nome_cliente"] ?? "Cliente não identificada") ?></strong>
                                    <span>Serviço: <?= htmlspecialchars($ag_detalhe["nome_servico"] ?? "Serviço não informado") ?></span>
                                    <span class="<?= $status_classe ?> detalhe-status"><?= htmlspecialchars($status_texto) ?></span>
                                </div>
                                <?php if ($status_detalhe !== "cancelado" && $status_detalhe !== "concluido"): ?>
                                    <form method="POST" class="form-cancelar-detalhe" onsubmit="return confirm('Cancelar este agendamento?')">
                                        <input type="hidden" name="acao" value="cancelar_agendamento">
                                        <input type="hidden" name="agendamento_id" value="<?= (int)$ag_detalhe["id"] ?>">
                                        <button type="submit" class="btn-cancelar">Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="agenda-dia-vazia">Não há agendamentos registrados para esta data.</p>
                <?php endif; ?>

                <h4>Bloqueios do dia</h4>
                <?php if (!empty($bloqueios[$dia_selecionado])): ?>
                    <div class="lista-detalhes-bloqueios">
                        <?php foreach ($bloqueios[$dia_selecionado] as $bloqueio_detalhe):
                            $inicio_detalhe = substr($bloqueio_detalhe["horario_inicio"] ?? "00:00", 0, 5);
                            $fim_detalhe = substr($bloqueio_detalhe["horario_fim"] ?? "23:59", 0, 5);
                            $dia_inteiro_detalhe = ($inicio_detalhe === "00:00" && ($fim_detalhe === "23:59" || $fim_detalhe === "00:00"));
                        ?>
                            <article class="detalhe-bloqueio-item">
                                <strong><?= $dia_inteiro_detalhe ? "Dia inteiro" : htmlspecialchars($inicio_detalhe . " às " . $fim_detalhe) ?></strong>
                                <span>Motivo: <?= htmlspecialchars($bloqueio_detalhe["motivo"] ?? "Não informado") ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="agenda-dia-vazia">Não há bloqueios registrados para esta data.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <!-- ABA 2 — HORÁRIOS -->
    <div class="aba-conteudo <?= $aba==='horarios'?'visivel':'' ?>">
        <div class="form-horarios">
            <h3>Meus Horários de Atendimento</h3>
            <p>Configure os dias da semana que você atende (padrão). Para atender um dia excepcional ou folgar apenas um dia, use as abas ao lado.</p>
            <form method="POST" action="?mes=<?= $mes ?>&ano=<?= $ano ?>&aba=horarios">
                <input type="hidden" name="acao" value="salvar_horarios">
                <?php
                $nomes_dias = ["Domingo", "Segunda-feira", "Terça-feira", "Quarta-feira", "Quinta-feira", "Sexta-feira", "Sábado"];
                foreach ($nomes_dias as $num => $nome):
                    $h = $horarios_dia[$num];
                    $chk = ((int)($h["trabalha"] ?? 0) === 1) ? "checked" : "";
                ?>
                    <div class="linha-dia">
                        <strong><?= $nome ?>:</strong>
                        <label><input type="checkbox" name="trabalha[<?= $num ?>]" value="1" <?= $chk ?>> Trabalha</label>
                        <span>Entrada:</span><input type="time" name="horario_inicio[<?= $num ?>]" value="<?= htmlspecialchars($h["horario_inicio"]) ?>">
                        <span>Último atend.:</span><input type="time" name="ultimo_horario[<?= $num ?>]" value="<?= htmlspecialchars($h["ultimo_horario"]) ?>">
                        <span>Almoço:</span><input type="time" name="inicio_almoco[<?= $num ?>]" value="<?= htmlspecialchars($h["inicio_almoco"]) ?>">
                        <span>às</span><input type="time" name="fim_almoco[<?= $num ?>]" value="<?= htmlspecialchars($h["fim_almoco"]) ?>">
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primario">Salvar Horários Padrão</button>
            </form>
        </div>
    </div>

    <!-- ABA 3 — BLOQUEIOS -->
    <div class="aba-conteudo <?= $aba==='bloqueios'?'visivel':'' ?>">
        <div class="form-bloquear">
            <h3>Bloquear Dia ou Período Específico</h3>
            <p>Bloqueia apenas ESTA data — não afeta outros dias da semana. Ideal para folgas, feriados ou compromissos únicos.</p>
            <form method="POST" action="?mes=<?= $mes ?>&ano=<?= $ano ?>&aba=bloqueios">
                <input type="hidden" name="acao" value="bloquear_dia">
                <div class="form-grid">
                    <div class="form-grupo">
                        <label>Data para bloquear *</label>
                        <input type="date" name="data_bloqueio" required>
                    </div>
                    <div class="form-grupo">
                        <label>Tipo de Bloqueio</label>
                        <select name="tipo_bloqueio" id="tipo_bloqueio" onchange="alternarTipoBloqueio(this.value)">
                            <option value="dia_inteiro">Dia Inteiro</option>
                            <option value="horario">Apenas um Horário</option>
                        </select>
                    </div>
                    <div class="form-grupo" id="campos_horario" style="display:none;">
                        <label>Horário de:</label>
                        <input type="time" name="horario_inicio_bloqueio">
                        <label>até:</label>
                        <input type="time" name="horario_fim_bloqueio">
                    </div>
                    <div class="form-grupo">
                        <label>Motivo</label>
                        <input type="text" name="motivo" value="Folga">
                    </div>
                </div>
                <button type="submit" class="btn btn-perigo" style="margin-top:15px;">Bloquear Este Dia/Período</button>
            </form>
        </div>
        <?php if (!empty($bloqueios)): ?>
        <div class="form-horarios" style="margin-top:20px;">
            <h3>Bloqueios deste Mês</h3>
            <table>
                <thead><tr><th>Data</th><th>Período</th><th>Motivo</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach ($bloqueios as $data_bloq => $lista): foreach ($lista as $b):
                        // Considera somente horas e minutos, mesmo quando o MySQL retorna segundos.
                        $in = substr($b["horario_inicio"] ?? "00:00", 0, 5);
                        $fi = substr($b["horario_fim"] ?? "23:59", 0, 5);
                        $periodo = ($in === "00:00" && ($fi === "23:59" || $fi === "00:00")) ? "Dia inteiro" : "$in às $fi";
                    ?>
                    <tr>
                        <td><?= date("d/m/Y", strtotime($data_bloq)) ?></td>
                        <td><?= $periodo ?></td>
                        <td><?= htmlspecialchars($b["motivo"]) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="acao" value="excluir_bloqueio">
                                <input type="hidden" name="bloqueio_id" value="<?= $b["id"] ?>">
                                <button type="submit" class="btn-cancelar" onclick="return confirm('Excluir bloqueio?')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    
        <!-- ABA 4 — LIBERAÇÃO COM CALENDÁRIO CLICÁVEL + SELECIONAR MÊS INTEIRO -->
        <div class="aba-conteudo <?= $aba==='liberar'?'visivel':'' ?>">
        <div class="form-horarios">
            <h3>Liberar Agendamentos</h3>
            <p>Selecione os dias diretamente no calendário ou use a opção para liberar o mês inteiro de uma vez.</p>
            
            <!-- Navegação entre meses + Botão Mês Inteiro -->
            <div class="navegacao-meses-liberar">
                <a href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>&aba=liberar" class="btn-navega">◀</a>
                <strong><?= $nome_mes ?> / <?= $ano ?></strong>
                <a href="?mes=<?= $proximo_mes ?>&ano=<?= $ano_proximo ?>&aba=liberar" class="btn-navega">▶</a>
            </div>

            <!-- Botão Selecionar/Desmarcar Mês Inteiro -->
            <div class="acao-mes-inteiro">
                <button type="button" id="btnSelecionarMes" class="btn btn-primario"> Selecionar Mês Inteiro</button>
                <button type="button" id="btnDesmarcarMes" class="btn btn-secundario"> Desmarcar Todos os Dias</button>
            </div>

            <!-- Calendário de seleção -->
            <form method="POST" action="?mes=<?= $mes ?>&ano=<?= $ano ?>&aba=liberar" id="formLiberaDias">
                <input type="hidden" name="acao" value="liberar_dias">
                
                <div class="calendario-liberar-cabecalho">
                    <?php foreach ($nomes_dias_semana as $ds): ?>
                        <div class="dia-semana-cab"><?= $ds ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="calendario-liberar-grade" id="gradeCalendario">
                    <?php
                    $dt = new DateTime("$ano-$mes-01");
                    $dt->modify('-' . $dt->format('w') . ' days');
                    
                    for ($i = 0; $i < 42; $i++):
                        $data_celula = $dt->format("Y-m-d");
                        $numero_dia = $dt->format("j");
                        $mes_celula = (int)$dt->format("n");
                        $passado = ($data_celula < $hoje_data);
                        $liberado = isset($dias_liberados[$data_celula]);
                        $fora_mes = ($mes_celula !== (int)$mes);
                    ?>
                        <div class="celula-lib 
                            <?= $fora_mes ? 'fora-mes' : '' ?> 
                            <?= $passado ? 'passado' : '' ?> 
                            <?= $liberado ? 'selecionado' : '' ?>"
                            data-mes-atual="<?= $mes_celula === (int)$mes ? 'sim' : 'nao' ?>"
                            data-passado="<?= $passado ? 'sim' : 'nao' ?>">
                            <?php if (!$fora_mes): ?>
                                <label class="celula-lib-label">
                                    <input type="checkbox" name="dias[]" value="<?= $data_celula ?>"
                                        class="check-dia-lib"
                                        <?= $liberado ? 'checked' : '' ?>
                                        <?= $passado ? 'disabled' : '' ?>>
                                    <span class="numero-lib"><?= $numero_dia ?></span>
                                </label>
                            <?php endif; ?>
                        </div>
                    <?php $dt->modify('+1 day'); endfor; ?>
                </div>

                <div class="botoes-lib-acao">
                    <button type="submit" name="acao" value="liberar_dias" class="btn btn-primario">✓ Liberar Selecionados</button>
                    <button type="submit" name="acao" value="remover_liberacao" class="btn btn-perigo">✗ Remover Liberação</button>
                </div>
            </form>
        </div>

        <!-- Lista de liberações ativas -->
        <?php if (!empty($liberacoes)): ?>
        <div class="form-horarios" style="margin-top:30px;">
            <h3>Liberações Ativas</h3>
            <table>
                <thead><tr><th>Tipo</th><th>Período</th><th>De</th><th>Até</th></tr></thead>
                <tbody>
                    <?php foreach ($liberacoes as $l):
                        $texto_tipo = "Dia";
                        if ($l["tipo"] === "mes") $texto_tipo = "Mês";
                        elseif ($l["tipo"] === "semana") $texto_tipo = "Dias";
                    ?>
                    <tr>
                        <td><?= $texto_tipo ?></td>
                        <td><?= htmlspecialchars($l["valor"]) ?></td>
                        <td><?= date("d/m/Y", strtotime($l["data_inicio"])) ?></td>
                        <td><?= date("d/m/Y", strtotime($l["data_fim"])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Mostra ou esconde os campos de horário conforme o tipo de bloqueio escolhido.
function alternarTipoBloqueio(tipo) {
    document.getElementById('campos_horario').style.display = tipo === 'horario' ? 'block' : 'none';
}

// ===== NOVO: Selecionar mês inteiro =====
document.addEventListener('DOMContentLoaded', function() {
    const btnSelecionarMes = document.getElementById('btnSelecionarMes');
    const btnDesmarcarMes = document.getElementById('btnDesmarcarMes');
    
    if (btnSelecionarMes) {
        btnSelecionarMes.addEventListener('click', function() {
            document.querySelectorAll('.check-dia-lib').forEach(function(checkbox) {
                if (!checkbox.disabled) {
                    checkbox.checked = true;
                    const celula = checkbox.closest('.celula-lib');
                    if (celula) celula.classList.add('selecionado');
                }
            });
        });
    }
    
    if (btnDesmarcarMes) {
        btnDesmarcarMes.addEventListener('click', function() {
            document.querySelectorAll('.check-dia-lib').forEach(function(checkbox) {
                if (!checkbox.disabled) {
                    checkbox.checked = false;
                    const celula = checkbox.closest('.celula-lib');
                    if (celula) celula.classList.remove('selecionado');
                }
            });
        });
    }
    
    // Atualiza visual ao selecionar dias na aba Liberação.
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('check-dia-lib')) {
            const celula = e.target.closest('.celula-lib');
            if (celula) {
                celula.classList.toggle('selecionado', e.target.checked);
            }
        }
    });

    // Abre os detalhes ao clicar em qualquer parte do dia no calendário principal.
    document.querySelectorAll('.calendario-dia-clicavel').forEach(function(dia) {
        dia.addEventListener('click', function(e) {
            // Não interfere nos botões ou formulários que existem dentro do dia.
            if (e.target.closest('button, a, form, input, select, textarea, label')) return;
            window.location.href = dia.dataset.diaUrl;
        });

        // Permite abrir o dia também pelo teclado usando Enter ou Espaço.
        dia.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                window.location.href = dia.dataset.diaUrl;
            }
        });
    });
});
</script>
</body>
</html>