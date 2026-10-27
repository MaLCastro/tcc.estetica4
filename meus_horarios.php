<?php
session_start(); // Inicia a sessão para reconhecer qual profissional está acessando a página.
require_once("conexao.php"); // Inclui o arquivo que faz a conexão com o banco de dados.

/* =========================================================
   SEGURANÇA — VERIFICAÇÃO DE ACESSO
   ========================================================= */
// Verifica se o usuário está logado e se é do tipo profissional.
if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "profissional") {
    header("Location: login.php"); // Redireciona para a página de login se não tiver permissão.
    exit(); // Interrompe a execução do restante do código.
}
$profissional_id = (int)$_SESSION["usuario_id"]; // Armazena o ID da profissional logada em formato numérico.

/* =========================================================
   DEFINIR MÊS E ANO EXIBIDOS NO CALENDÁRIO
   ========================================================= */
date_default_timezone_set("America/Sao_Paulo"); // Define o fuso horário oficial do Brasil.
$mes_atual = date("m"); // Pega o mês atual do sistema com dois dígitos (ex: 09).
$ano_atual = date("Y"); // Pega o ano atual com quatro dígitos (ex: 2026).
$mes = $_GET["mes"] ?? $mes_atual; // Pega o mês enviado pela URL ou usa o mês atual como padrão.
$ano = $_GET["ano"] ?? $ano_atual; // Pega o ano enviado pela URL ou usa o ano atual como padrão.
$mes = str_pad(min(max((int)$mes, 1), 12), 2, "0", STR_PAD_LEFT); // Garante que o mês seja entre 01 e 12 com dois dígitos.
$ano = min(max((int)$ano, 2020), 2100); // Garante que o ano seja válido entre 2020 e 2100.

/* =========================================================
   SALVAR ALTERAÇÕES ANTES DE EXIBIR
   ========================================================= */
$mensagem_sucesso = ""; // Variável que guarda mensagem de sucesso.
$mensagem_erro = ""; // Variável que guarda mensagem de erro.

// Verifica se o formulário foi enviado.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"])) {
    try { // Inicia bloco protegido contra erros.
        // Ação: Salvar horários de atendimento por dia da semana.
        if ($_POST["acao"] === "salvar_horarios") {
            // Dias da semana: 0=Domingo, 1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado.
            for ($dia_semana = 0; $dia_semana <= 6; $dia_semana++) { // Repete para cada dia da semana (de 0 a 6).
                $trabalha = isset($_POST["trabalha"][$dia_semana]) ? 1 : 0; // Verifica se o dia está marcado como dia de trabalho.
                $horario_inicio = trim($_POST["horario_inicio"][$dia_semana] ?? ""); // Pega o horário de início de atendimento.
                $ultimo_horario = trim($_POST["ultimo_horario"][$dia_semana] ?? ""); // Pega o horário do último atendimento.
                $inicio_almoco = trim($_POST["inicio_almoco"][$dia_semana] ?? ""); // Pega o horário de início do almoço.
                $fim_almoco = trim($_POST["fim_almoco"][$dia_semana] ?? ""); // Pega o horário de retorno do almoço.

                // Verifica se já existe horário cadastrado para este dia.
                $verifica = $conexao->prepare("
                    SELECT id FROM horarios 
                    WHERE profissional_id = :pid AND dia_semana = :dia LIMIT 1
                ");
                $verifica->execute([":pid" => $profissional_id, ":dia" => $dia_semana]);
                $existe = $verifica->fetch();

                if ($existe) { // Se já existe, atualiza os dados.
                    $comando = $conexao->prepare("
                        UPDATE horarios 
                        SET trabalha = :trabalha, horario_inicio = :hi, 
                            ultimo_horario = :uh, inicio_almoco = :ia, fim_almoco = :fa
                        WHERE profissional_id = :pid AND dia_semana = :dia
                    ");
                } else { // Se não existe, cria um novo registro.
                    $comando = $conexao->prepare("
                        INSERT INTO horarios 
                        (profissional_id, dia_semana, trabalha, horario_inicio, ultimo_horario, inicio_almoco, fim_almoco)
                        VALUES (:pid, :dia, :trabalha, :hi, :uh, :ia, :fa)
                    ");
                }

                // Executa o comando salvando valores ou nulos se estiverem vazios.
                $comando->execute([
                    ":pid" => $profissional_id,
                    ":dia" => $dia_semana,
                    ":trabalha" => $trabalha,
                    ":hi" => $horario_inicio ?: null,
                    ":uh" => $ultimo_horario ?: null,
                    ":ia" => $inicio_almoco ?: null,
                    ":fa" => $fim_almoco ?: null
                ]);
            }
            $mensagem_sucesso = "✅ Horários salvos! Calendário e formulário atualizados."; // Mensagem de confirmação.
        }
        // Ação: Bloquear um dia específico.
        elseif ($_POST["acao"] === "bloquear_dia") {
            $data_bloqueio = trim($_POST["data_bloqueio"] ?? ""); // Pega a data que será bloqueada.
            $motivo = trim($_POST["motivo"] ?? "Folga"); // Pega o motivo ou usa "Folga" como padrão.
            $horario_inicio = trim($_POST["horario_inicio"] ?? "00:00"); // Horário de início do bloqueio (padrão: meia-noite).
            $horario_fim = trim($_POST["horario_fim"] ?? "23:59"); // Horário de término do bloqueio (padrão: fim do dia).

            if (!$data_bloqueio) throw new Exception("Informe a data."); // Verifica se a data foi preenchida.

            // Insere o bloqueio no banco de dados.
            $comando = $conexao->prepare("
                INSERT INTO bloqueios_agenda 
                (profissional_id, data_bloqueio, horario_inicio, horario_fim, motivo)
                VALUES (:pid, :data, :hi, :hf, :motivo)
            ");
            $comando->execute([
                ":pid" => $profissional_id,
                ":data" => $data_bloqueio,
                ":hi" => $horario_inicio,
                ":hf" => $horario_fim,
                ":motivo" => $motivo
            ]);
            $mensagem_sucesso = "✅ Dia bloqueado com sucesso!"; // Mensagem de confirmação.
        }
    } catch (Exception $erro) { // Se qualquer erro ocorrer durante o salvamento...
        $mensagem_erro = "❌ Erro: " . $erro->getMessage(); // Guarda a mensagem de erro.
    }
}

/* =========================================================
   CARREGAR HORÁRIOS DO BANCO APÓS SALVAR
   ========================================================= */
// Inicia com valores padrão caso não haja cadastro ainda.
$horarios_dia = [];
for ($i = 0; $i <= 6; $i++) {
    $horarios_dia[$i] = [
        "trabalha" => 0,
        "horario_inicio" => "08:00",
        "ultimo_horario" => "19:00",
        "inicio_almoco" => "12:00",
        "fim_almoco" => "13:00"
    ];
}

// Busca os horários reais salvos no banco de dados.
try {
    $consulta = $conexao->prepare("
        SELECT dia_semana, trabalha, horario_inicio, ultimo_horario, inicio_almoco, fim_almoco
        FROM horarios
        WHERE profissional_id = :pid
    ");
    $consulta->execute([":pid" => $profissional_id]);
    $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC); // Guarda todos os resultados encontrados.

    foreach ($resultados as $h) { // Percorre cada resultado e substitui os valores padrão.
        $horarios_dia[(int)$h["dia_semana"]] = [
            "trabalha" => (int)$h["trabalha"], // Converte para número inteiro.
            "horario_inicio" => $h["horario_inicio"] ?? "08:00",
            "ultimo_horario" => $h["ultimo_horario"] ?? "19:00",
            "inicio_almoco" => $h["inicio_almoco"] ?? "12:00",
            "fim_almoco" => $h["fim_almoco"] ?? "13:00"
        ];
    }
} catch (PDOException $e) { // Se houver erro na consulta, registra no log do sistema.
    error_log("Erro ao carregar horários: " . $e->getMessage());
}

/* =========================================================
   CARREGAR DIAS BLOQUEADOS DO MÊS
   ========================================================= */
$bloqueios = []; // Lista que vai guardar as datas bloqueadas.
try {
    $consulta = $conexao->prepare("
        SELECT data_bloqueio, motivo
        FROM bloqueios_agenda
        WHERE profissional_id = :pid 
          AND YEAR(data_bloqueio) = :ano 
          AND MONTH(data_bloqueio) = :mes
    ");
    $consulta->execute([":pid" => $profissional_id, ":ano" => $ano, ":mes" => $mes]);
    while ($b = $consulta->fetch(PDO::FETCH_ASSOC)) { // Armazena cada data bloqueada com seu motivo.
        $bloqueios[$b["data_bloqueio"]] = $b["motivo"];
    }
} catch (PDOException $e) { // Se houver erro, registra no log.
    error_log("Erro ao carregar bloqueios: " . $e->getMessage());
}

/* =========================================================
   CÁLCULOS PARA EXIBIÇÃO DO CALENDÁRIO
   ========================================================= */
$nomes_meses = [ // Lista com os nomes dos meses em português.
    "01" => "Janeiro", "02" => "Fevereiro", "03" => "Março", "04" => "Abril",
    "05" => "Maio", "06" => "Junho", "07" => "Julho", "08" => "Agosto",
    "09" => "Setembro", "10" => "Outubro", "11" => "Novembro", "12" => "Dezembro"
];
$nome_mes = $nomes_meses[$mes] ?? ""; // Converte o número do mês para o nome por extenso.
$primeiro_dia_semana = date("w", strtotime("$ano-$mes-01")); // Descobre em qual dia da semana cai o dia 1º do mês.
$quantos_dias = date("t", strtotime("$ano-$mes-01")); // Descobre quantos dias tem o mês selecionado.
$mes_anterior = $mes == 1 ? 12 : $mes - 1; // Define o mês anterior (se for janeiro, volta para dezembro).
$ano_anterior = $mes == 1 ? $ano - 1 : $ano; // Define o ano correto do mês anterior.
$proximo_mes = $mes == 12 ? 1 : $mes + 1; // Define o próximo mês (se for dezembro, avança para janeiro).
$ano_proximo = $mes == 12 ? $ano + 1 : $ano; // Define o ano correto do próximo mês.
$nomes_dias_semana = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"]; // Nomes abreviados dos dias da semana.
?>
<!DOCTYPE html> <!-- Define o tipo de documento como HTML5 -->
<html lang="pt-BR"> <!-- Define o idioma da página como português do Brasil -->
<head>
    <meta charset="UTF-8"> <!-- Define a codificação para aceitar acentuação e símbolos -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Faz a página se adaptar a celulares e telas menores -->
    <title>Meus Horários e Calendário</title> <!-- Título que aparece na aba do navegador -->
    <link rel="stylesheet" href="style.css"> <!-- Carrega o arquivo de estilos com as cores e formatação -->
    <style> <!-- Estilos visuais específicos desta página -->
        .calendario { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 30px; }
        .calendario-cabecalho { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .calendario-cabecalho h3 { color: #6B4842; margin: 0; }
        .calendario-navegacao a { color: #6B4842; text-decoration: none; padding: 6px 12px; border-radius: 6px; background: #f4e9e7; margin: 0 4px; }
        .calendario-navegacao a:hover { background: #e6d3d0; }
        .calendario-grade { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .calendario-dia-nome { text-align: center; font-weight: 600; color: #6B4842; padding: 8px; font-size: 13px; }
        .calendario-dia { min-height: 70px; padding: 6px; border-radius: 8px; background: #f9f5f4; border: 1px solid #eee; cursor: default; }
        .calendario-dia.dia-hoje { border: 2px solid #6B4842; font-weight: bold; }
        .calendario-dia.dia-trabalho { background: #e8f5e9; }
        .calendario-dia.dia-bloqueado { background: #fce4ec; color: #b71c1c; }
        .calendario-dia.dia-sem-trabalho { background: #f5f5f5; color: #999; }
        .calendario-dia-numero { font-size: 15px; font-weight: 600; }
        .calendario-dia-info { font-size: 11px; margin-top: 4px; line-height: 1.3; }
        .calendario-dia-vazio { background: transparent; border: none; }
        .etiqueta-bloqueado { display: block; font-size: 10px; background: #ef5350; color: white; padding: 1px 4px; border-radius: 3px; margin-top: 2px; }
        .form-horarios, .form-bloquear { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .linha-dia { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; flex-wrap: wrap; }
        .linha-dia strong { width: 120px; display: inline-block; }
        .linha-dia input[type="time"] { padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; }
        .alerta { padding: 12px; border-radius: 8px; margin: 10px 0; }
        .alerta-sucesso { background: #d4edda; color: #155724; }
        .alerta-erro { background: #f8d7da; color: #721c24; }
        input[type="checkbox"] { cursor: pointer; }
    </style>
</head>
<body>
<!-- MENU LATERAL PADRÃO DO SISTEMA -->
<aside class="sidebar"> <!-- Área lateral com o menu de navegação -->
    <div class="logo"> <!-- Área com nome do sistema e especialidade da profissional -->
        <h1>Estética</h1> <!-- Nome do sistema exibido no topo do menu -->
        <?php $especialidade = htmlspecialchars($_SESSION["especialidade"] ?? "Profissional"); ?>
        <p><?= $especialidade ?></p> <!-- Exibe a especialidade da profissional logada -->
    </div>
    <nav class="menu"> <!-- Conjunto de links de navegação entre as páginas -->
        <a href="painel_profissional.php" class="<?= basename($_SERVER['PHP_SELF']) === 'painel_profissional.php' ? 'ativo' : '' ?>">Início</a> <!-- Link para a página inicial -->
        <a href="minha_agenda.php" class="<?= basename($_SERVER['PHP_SELF']) === 'minha_agenda.php' ? 'ativo' : '' ?>">Minha Agenda</a> <!-- Link para o calendário de agendamentos -->
        <a href="meus_servicos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'meus_servicos.php' ? 'ativo' : '' ?>">Meus Serviços</a> <!-- Link para gerenciar serviços -->
        <a href="atendimentos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'atendimentos.php' ? 'ativo' : '' ?>">Histórico</a> <!-- Link para histórico de atendimentos -->
        <div class="separador"></div> <!-- Linha separadora visual -->
        <a href="meu_perfil.php" class="<?= basename($_SERVER['PHP_SELF']) === 'meu_perfil.php' ? 'ativo' : '' ?>">Meu Perfil</a> <!-- Link para dados pessoais -->
    </nav>
    <a href="logout.php" class="btn-sair">Sair</a> <!-- Botão para encerrar a sessão -->
</aside>

<main class="main"> <!-- Área principal de conteúdo da página -->
    <section class="topo"> <!-- Cabeçalho da página -->
        <div>
            <h2>Meus Horários e Calendário</h2> <!-- Título da página -->
            <p>Cada profissional define seus próprios horários de atendimento</p> <!-- Explicação do propósito -->
        </div>
        <div class="data"><?= date("l, d/m/Y") ?></div> <!-- Exibe a data atual formatada -->
    </section>

    <!-- Exibe mensagem de sucesso se o salvamento funcionou -->
    <?php if ($mensagem_sucesso): ?><div class="alerta alerta-sucesso"><?= htmlspecialchars($mensagem_sucesso) ?></div><?php endif; ?>
    <!-- Exibe mensagem de erro se algo deu errado -->
    <?php if ($mensagem_erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($mensagem_erro) ?></div><?php endif; ?>

    <!-- CALENDÁRIO VISUAL -->
    <div class="calendario">
        <div class="calendario-cabecalho"> <!-- Cabeçalho com mês e botões de navegação -->
            <h3><?= $nome_mes ?> de <?= $ano ?></h3> <!-- Exibe o nome do mês e ano selecionado -->
            <div class="calendario-navegacao">
                <a href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>">◀</a> <!-- Link para o mês anterior -->
                <a href="?mes=<?= date("m") ?>&ano=<?= date("Y") ?>">Hoje</a> <!-- Link para voltar ao mês atual -->
                <a href="?mes=<?= $proximo_mes ?>&ano=<?= $ano_proximo ?>">▶</a> <!-- Link para o próximo mês -->
            </div>
        </div>

        <div class="calendario-grade"> <!-- Grade com os dias do calendário -->
            <?php foreach ($nomes_dias_semana as $nome_dia): ?> <!-- Exibe os nomes dos dias da semana no topo -->
                <div class="calendario-dia-nome"><?= $nome_dia ?></div>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $primeiro_dia_semana; $i++): ?> <!-- Cria espaços vazios antes do primeiro dia do mês -->
                <div class="calendario-dia calendario-dia-vazio"></div>
            <?php endfor; ?>

            <?php
            for ($dia = 1; $dia <= $quantos_dias; $dia++): // Repete para cada dia do mês
                $dia_da_semana = ($primeiro_dia_semana + $dia - 1) % 7; // Descobre qual dia da semana é esta data
                $data_completa = sprintf("%04d-%02d-%02d", $ano, $mes, $dia); // Monta a data no formato ano-mês-dia
                $eh_hoje = $data_completa === date("Y-m-d"); // Verifica se é o dia de hoje
                $trabalha_neste_dia = (int)$horarios_dia[$dia_da_semana]["trabalha"]; // Verifica se é dia de trabalho conforme configuração
                $esta_bloqueado = isset($bloqueios[$data_completa]); // Verifica se esta data foi bloqueada
                $motivo_bloqueio = $bloqueios[$data_completa] ?? ""; // Pega o motivo do bloqueio se houver

                // Define a cor de fundo do dia conforme a situação
                $classe_dia = "";
                if ($esta_bloqueado) {
                    $classe_dia = "dia-bloqueado"; // Fica vermelho se estiver bloqueado
                } elseif ($trabalha_neste_dia === 1) {
                    $classe_dia = "dia-trabalho"; // Fica verde se for dia de trabalho
                } else {
                    $classe_dia = "dia-sem-trabalho"; // Fica cinza se for folga
                }
                if ($eh_hoje) $classe_dia .= " dia-hoje"; // Destaca o dia de hoje com borda
                $hr = $horarios_dia[$dia_da_semana]; // Pega os horários configurados para este dia da semana
            ?>
                <div class="calendario-dia <?= $classe_dia ?>"> <!-- Caixa do dia com a cor definida -->
                    <div class="calendario-dia-numero"><?= $dia ?></div> <!-- Número do dia -->
                    <div class="calendario-dia-info"> <!-- Informações exibidas dentro do dia -->
                        <?php if ($esta_bloqueado): ?> <!-- Se estiver bloqueado -->
                            <span class="etiqueta-bloqueado">Bloqueado</span>
                            <small><?= htmlspecialchars($motivo_bloqueio) ?></small>
                        <?php elseif ($trabalha_neste_dia === 1): ?> <!-- Se for dia de trabalho -->
                            <?= substr($hr["horario_inicio"], 0, 5) ?> às <?= substr($hr["ultimo_horario"], 0, 5) ?>
                            <br>
                            Almoço: <?= substr($hr["inicio_almoco"], 0, 5) ?>-<?= substr($hr["fim_almoco"], 0, 5) ?>
                        <?php else: ?> <!-- Se for folga -->
                            Folga
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- FORMULÁRIO PARA CONFIGURAR HORÁRIOS -->
    <div class="form-horarios">
        <h3> Meus Horários de Atendimento</h3>
        <p style="color:#666; margin-bottom:15px;">Marque os dias que trabalha e informe seus horários</p>
        <form method="POST" action=""> <!-- Envia os dados para a própria página -->
            <input type="hidden" name="acao" value="salvar_horarios"> <!-- Identifica a ação de salvar horários -->
            <?php
            $nomes_dias = ["Domingo", "Segunda-feira", "Terça-feira", "Quarta-feira", "Quinta-feira", "Sexta-feira", "Sábado"];
            foreach ($nomes_dias as $num => $nome_dia): // Para cada dia da semana
                $h = $horarios_dia[$num]; // Pega os horários salvos no banco
                $checado = $h["trabalha"] === 1 ? "checked" : ""; // Marca a caixa se for dia de trabalho
            ?>
                <div class="linha-dia"> <!-- Linha com os dados de um dia da semana -->
                    <strong><?= $nome_dia ?>:</strong>
                    <label><input type="checkbox" name="trabalha[<?= $num ?>]" value="1" <?= $checado ?>> Trabalha</label>
                    <span>Entrada:</span><input type="time" name="horario_inicio[<?= $num ?>]" value="<?= htmlspecialchars($h["horario_inicio"]) ?>">
                    <span>Último atend.:</span><input type="time" name="ultimo_horario[<?= $num ?>]" value="<?= htmlspecialchars($h["ultimo_horario"]) ?>">
                    <span>Almoço:</span><input type="time" name="inicio_almoco[<?= $num ?>]" value="<?= htmlspecialchars($h["inicio_almoco"]) ?>">
                    <span>às</span><input type="time" name="fim_almoco[<?= $num ?>]" value="<?= htmlspecialchars($h["fim_almoco"]) ?>">
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-sucesso" style="margin-top:15px;"> Salvar Meus Horários</button> <!-- Botão de envio -->
        </form>
    </div>

    <!-- FORMULÁRIO PARA BLOQUEAR UM DIA ESPECÍFICO -->
    <div class="form-bloquear">
        <h3> Bloquear Dia ou Período</h3>
        <p style="color:#666; margin-bottom:15px;">Bloqueie datas específicas — somente para você</p>
        <form method="POST" action=""> <!-- Envia os dados para a própria página -->
            <input type="hidden" name="acao" value="bloquear_dia"> <!-- Identifica a ação de bloquear dia -->
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <label>Data:</label><input type="date" name="data_bloqueio" required> <!-- Campo para escolher a data -->
                <label>Motivo:</label><input type="text" name="motivo" placeholder="Ex: Feriado, Folga..." value="Folga" style="width:180px; padding:6px;"> <!-- Motivo do bloqueio -->
                <button type="submit" class="btn" style="background:#ef5350; color:white;">Bloquear</button> <!-- Botão de envio -->
            </div>
        </form>
    </div>
</main>
</body>
</html>