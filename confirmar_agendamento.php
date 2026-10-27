<?php
// ============================================================
// INICIAR SESSÃO, CONEXÃO E VALIDAR ACESSO
// ============================================================
session_start();
require_once("conexao.php");

// Permite acesso somente à cliente autenticada.
if (!isset($_SESSION["usuario_id"]) || ($_SESSION["usuario_tipo"] ?? "") !== "cliente") {
    header("Location: login.php");
    exit();
}

date_default_timezone_set("America/Sao_Paulo");

$cliente_id = (int)$_SESSION["usuario_id"];
$servico_id = filter_input(INPUT_GET, "servico_id", FILTER_VALIDATE_INT);
$profissional_id = filter_input(INPUT_GET, "profissional_id", FILTER_VALIDATE_INT);
$data = $_POST["data"] ?? ($_GET["data"] ?? "");
$horario = $_POST["horario"] ?? ($_GET["horario"] ?? "");
$erro = "";
$sucesso = false;
$servico = null;
$cliente = null;
$chave_pix = "";
$tipo_pix = "";
$nome_pessoa = "";
$para_outra_pessoa = false;
$aceitou_confirmar = false;

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

// Escapa os valores exibidos no HTML.
function escaparConfirmacao($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

// Converte durações cadastradas em minutos.
function duracaoEmMinutosConfirmacao($duracao)
{
    $texto = trim((string)$duracao);
    if (ctype_digit($texto)) return (int)$texto;
    if (preg_match('/^(\d+)\s*h(?:\s*(\d+)\s*(?:min)?)?$/ui', $texto, $m)) {
        return ((int)$m[1] * 60) + (isset($m[2]) ? (int)$m[2] : 0);
    }
    if (preg_match('/^(\d+)\s*min$/ui', $texto, $m)) return (int)$m[1];
    if (preg_match('/^(\d+):(\d+)$/', $texto, $m)) return ((int)$m[1] * 60) + (int)$m[2];
    return 0;
}

// ============================================================
// FORMATAR DURAÇÃO PARA EXIBIÇÃO À CLIENTE
// ============================================================
function formatarDuracaoConfirmacao($minutos)
{
    // Impede a exibição de uma duração inválida.
    $minutos = (int)$minutos;

    if ($minutos <= 0) {
        return "Duração não informada";
    }

    // Separa as horas completas dos minutos restantes.
    $horas = intdiv($minutos, 60);
    $restantes = $minutos % 60;

    // Formata durações que possuem horas e minutos.
    if ($horas > 0 && $restantes > 0) {
        return $horas . " " . ($horas === 1 ? "hora" : "horas")
            . " e " . $restantes . " " 
            . ($restantes === 1 ? "minuto" : "minutos");
    }

    // Formata durações que possuem somente horas.
    if ($horas > 0) {
        return $horas . " " . ($horas === 1 ? "hora" : "horas");
    }

    // Formata durações inferiores a uma hora.
    return $minutos . " " . ($minutos === 1 ? "minuto" : "minutos");
}

// Valida datas no padrão AAAA-MM-DD.
function dataValidaConfirmacao($valor)
{
    if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return false;
    $obj = DateTime::createFromFormat("!Y-m-d", $valor);
    return $obj && $obj->format("Y-m-d") === $valor;
}

// Busca dados do serviço, profissional e chave Pix cadastrada.
function carregarDadosConfirmacao(PDO $conexao, int $servicoId, int $profissionalId)
{
    $sql = "
        SELECT s.id, s.nome_servico, s.duracao, s.preco,
               v.duracao_customizada, v.preco_customizado,
               u.id AS profissional_id, u.nome AS profissional_nome,
               u.chave_pix, u.tipo_chave_pix
        FROM servicos s
        INNER JOIN vinculacao v
            ON v.servico_id = s.id AND v.profissional_id = :pid
        INNER JOIN usuarios u ON u.id = v.profissional_id
        WHERE s.id = :sid AND s.status = 'ativo' AND u.status = 'ativo'
        LIMIT 1
    ";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([":pid" => $profissionalId, ":sid" => $servicoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Revalida a liberação, jornada, bloqueios e conflitos antes de gravar.
function horarioAindaDisponivel(PDO $conexao, int $profissionalId, string $data, string $horario, int $duracaoMinutos): bool
{
    $hoje = date("Y-m-d");
    if (!dataValidaConfirmacao($data) || $data < $hoje ||
        !preg_match('/^\d{2}:\d{2}$/', $horario) || $duracaoMinutos <= 0) {
        return false;
    }

    // Confirma que a profissional liberou a data.
    $stmt = $conexao->prepare("
        SELECT COUNT(*) FROM liberacao_agenda
        WHERE profissional_id = :pid AND :data_inicio >= data_inicio AND :data_fim <= data_fim
    ");
    $stmt->execute([":pid" => $profissionalId, ":data_inicio" => $data, ":data_fim" => $data]);
    if ((int)$stmt->fetchColumn() < 1) return false;

    // Confere se a profissional trabalha nesse dia.
    $diaSemana = (int)(new DateTime($data))->format("w");
    $stmt = $conexao->prepare("
        SELECT trabalha, horario_inicio, ultimo_horario, inicio_almoco, fim_almoco
        FROM horarios WHERE profissional_id = :pid AND dia_semana = :dia LIMIT 1
    ");
    $stmt->execute([":pid" => $profissionalId, ":dia" => $diaSemana]);
    $jornada = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$jornada || (int)$jornada["trabalha"] !== 1 || empty($jornada["horario_inicio"]) || empty($jornada["ultimo_horario"])) {
        return false;
    }

    $inicio = new DateTime($data . " " . $horario);
    $fim = (clone $inicio)->modify("+{$duracaoMinutos} minutes");
    $inicioJornada = new DateTime($data . " " . $jornada["horario_inicio"]);
    $fimJornada = new DateTime($data . " " . $jornada["ultimo_horario"]);
    if ($inicio < $inicioJornada || $fim > $fimJornada) return false;
    if ($data === $hoje && $inicio <= new DateTime()) return false;

    // Impede sobreposição com o intervalo de almoço.
    if (!empty($jornada["inicio_almoco"]) && !empty($jornada["fim_almoco"])) {
        $inicioAlmoco = new DateTime($data . " " . $jornada["inicio_almoco"]);
        $fimAlmoco = new DateTime($data . " " . $jornada["fim_almoco"]);
        if ($inicio < $fimAlmoco && $fim > $inicioAlmoco) return false;
    }

    // Confere todos os agendamentos não cancelados, considerando a duração de cada serviço.
    $stmt = $conexao->prepare("
        SELECT a.horario, s.duracao, v.duracao_customizada
        FROM agendamentos a
        LEFT JOIN servicos s ON s.id = a.servico_id
        LEFT JOIN vinculacao v ON v.servico_id = a.servico_id AND v.profissional_id = a.profissional_id
        WHERE a.profissional_id = :pid AND a.data_agendamento = :data AND a.status <> 'cancelado'
    ");
    $stmt->execute([":pid" => $profissionalId, ":data" => $data]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $existente) {
        $duracaoExistente = duracaoEmMinutosConfirmacao(
            !empty($existente["duracao_customizada"]) ? $existente["duracao_customizada"] : ($existente["duracao"] ?? "")
        );
        if ($duracaoExistente <= 0) $duracaoExistente = 30;
        $inicioExistente = new DateTime($data . " " . $existente["horario"]);
        $fimExistente = (clone $inicioExistente)->modify("+{$duracaoExistente} minutes");
        if ($inicio < $fimExistente && $fim > $inicioExistente) return false;
    }

    // Confere bloqueios que se cruzam com o período solicitado.
    $stmt = $conexao->prepare("
        SELECT horario_inicio, horario_fim
        FROM bloqueios_agenda
        WHERE profissional_id = :pid AND data_bloqueio = :data
    ");
    $stmt->execute([":pid" => $profissionalId, ":data" => $data]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $bloqueio) {
        $inicioBloqueio = new DateTime($data . " " . $bloqueio["horario_inicio"]);
        $fimBloqueio = new DateTime($data . " " . $bloqueio["horario_fim"]);
        if ($inicio < $fimBloqueio && $fim > $inicioBloqueio) return false;
    }

    return true;
}

// ============================================================
// VALIDAR PARÂMETROS E CARREGAR INFORMAÇÕES
// ============================================================

if (!$servico_id || !$profissional_id || !dataValidaConfirmacao($data) ||
    !preg_match('/^\d{2}:\d{2}$/', $horario)) {
    $erro = "Os dados do horário estão incompletos. Volte à agenda e selecione uma data e um horário.";
} else {
    try {
        $servico = carregarDadosConfirmacao($conexao, (int)$servico_id, (int)$profissional_id);
        if (!$servico) {
            $erro = "Este serviço não está disponível para a profissional selecionada.";
        } else {
        
            // Prioriza os valores personalizados da profissional.
            $duracaoTexto = !empty($servico["duracao_customizada"])
                ? $servico["duracao_customizada"]
                : $servico["duracao"];

            // Converte a duração cadastrada para minutos.
            $duracaoMinutos = duracaoEmMinutosConfirmacao($duracaoTexto);

            // Formata a duração para exibição amigável à cliente.
            $duracaoExibicao = formatarDuracaoConfirmacao($duracaoMinutos);

            $precoFinal = ($servico["preco_customizado"] !== null && $servico["preco_customizado"] !== "")
                ? (float)$servico["preco_customizado"] : (float)$servico["preco"];
            $valorSinal = round($precoFinal * 0.30, 2);
            $valorRestante = round($precoFinal - $valorSinal, 2);
            $chave_pix = trim((string)($servico["chave_pix"] ?? ""));
            $tipo_pix = trim((string)($servico["tipo_chave_pix"] ?? ""));

            // Carrega nome e contato da cliente logada.
            $stmt = $conexao->prepare("SELECT nome, email, telefone FROM usuarios WHERE id = :id AND tipo_usuario = 'cliente' LIMIT 1");
            $stmt->execute([":id" => $cliente_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                $erro = "Não foi possível localizar os dados da sua conta.";
            } elseif ($duracaoMinutos <= 0) {
                $erro = "A duração do serviço precisa ser conferida pela profissional.";
            } elseif (!horarioAindaDisponivel($conexao, (int)$profissional_id, $data, $horario, $duracaoMinutos)) {
                $erro = "Este horário não está mais disponível. Volte à agenda e escolha outro horário.";
            }
        }
    } catch (Throwable $e) {
        error_log("Erro ao preparar confirmação do agendamento: " . $e->getMessage());
        $erro = "Não foi possível carregar a confirmação. Confira os dados do banco e tente novamente.";
    }
}

// ============================================================
// SALVAR AGENDAMENTO APÓS CONFIRMAÇÃO DA CLIENTE
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && $erro === "") {
    $para_outra_pessoa = isset($_POST["para_outra_pessoa"]) && $_POST["para_outra_pessoa"] === "1";
    $nome_pessoa = trim((string)($_POST["nome_pessoa"] ?? ""));
    $aceitou_confirmar = isset($_POST["aceito_confirmar"]);

    if (!$aceitou_confirmar) {
        $erro = "Marque a confirmação para concluir o agendamento.";
    } elseif ($para_outra_pessoa && $nome_pessoa === "") {
        $erro = "Informe o nome da pessoa que receberá o atendimento.";
    } elseif (strlen($nome_pessoa) > 150) {
        $erro = "O nome informado é muito longo.";
    } else {
        try {
            // Transação reduz o risco de salvar uma reserva após uma mudança de disponibilidade.
            $conexao->beginTransaction();

            if (!horarioAindaDisponivel($conexao, (int)$profissional_id, $data, $horario, $duracaoMinutos)) {
                throw new RuntimeException("Este horário acabou de ficar indisponível. Escolha outro horário.");
            }

            // Guarda a cliente logada como responsável pela reserva.
            // O nome de outra pessoa é registrado em coluna própria quando essa opção é marcada.
           

           
            // Prepara o cadastro incluindo a coluna obrigatória de Nail Art.
            $stmt = $conexao->prepare("
                INSERT INTO agendamentos (
                    cliente_id,
                    profissional_id,
                    servico_id,
                    data_agendamento,
                    horario,
                    status,
                    preco_final,
                    sinal_pago,
                    agendamento_para_outro,
                    nome_pessoa,
                    valor_sinal,
                    nail_art_inclusa
                ) VALUES (
                    :cliente_id,
                    :profissional_id,
                    :servico_id,
                    :data_agendamento,
                    :horario,
                    'pendente',
                    :preco_final,
                    0,
                    :para_outro,
                    :nome_pessoa,
                    :valor_sinal,
                    0
                )
            ");

            // Envia os dados correspondentes aos parâmetros do SQL.
            $stmt->execute([
                ":cliente_id" => $cliente_id,
                ":profissional_id" => $profissional_id,
                ":servico_id" => $servico_id,
                ":data_agendamento" => $data,
                ":horario" => $horario,
                ":preco_final" => $precoFinal,
                ":para_outro" => $para_outra_pessoa ? 1 : 0,
                ":nome_pessoa" => $para_outra_pessoa ? $nome_pessoa : null,
                ":valor_sinal" => $valorSinal
            ]);


            $conexao->commit();
            $sucesso = true;
        } catch (Throwable $e) {
            if ($conexao->inTransaction()) $conexao->rollBack();
            error_log("Erro ao salvar agendamento: " . $e->getMessage());
            $erro = ($e instanceof RuntimeException)
                ? $e->getMessage()
                : "Não foi possível salvar o agendamento. Confira se as colunas necessárias foram criadas no banco.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar agendamento | TCC Estética</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-cliente pagina-confirmar-agendamento">
    <!-- Mantém o cabeçalho usado nas páginas da cliente. -->
    <header class="cabecalho-cliente">
        <div class="logo-cliente"><h1>Estética</h1></div>
        <nav class="menu-cliente">
            <a href="painel_cliente.php">Início</a>
            <a href="servicos.php" class="ativo">Serviços</a>
            <a href="meus_agendamentos.php">Meus Agendamentos</a>
            <a href="logout.php" class="menu-sair">Sair</a>
        </nav>
    </header>

    <main class="confirmacao-container">
        <?php if ($sucesso): ?>
            <!-- Mostra a conclusão somente depois de salvar no banco. -->
            <section class="confirmacao-card confirmacao-sucesso">
                <div class="confirmacao-simbolo" aria-hidden="true">✓</div>
                <span class="confirmacao-sobretitulo">AGENDAMENTO REGISTRADO</span>
                <h2>Agendamento concluído!</h2>
                <p>Seu horário foi registrado. Confira os detalhes em “Meus Agendamentos”.</p>
                <div class="confirmacao-resumo">
                    <p><strong>Profissional:</strong> <?= escaparConfirmacao($servico["profissional_nome"]) ?></p>
                    <p><strong>Serviço:</strong> <?= escaparConfirmacao($servico["nome_servico"]) ?></p>
                    <p><strong>Data:</strong> <?= escaparConfirmacao(date("d/m/Y", strtotime($data))) ?></p>
                    <p><strong>Horário:</strong> <?= escaparConfirmacao($horario) ?></p>
                    <p><strong>Sinal de 30%:</strong> R$ <?= number_format($valorSinal, 2, ",", ".") ?></p>
                </div>
                <div class="confirmacao-acoes">
                    <a class="confirmacao-botao principal" href="meus_agendamentos.php">Meus Agendamentos</a>
                    <a class="confirmacao-botao secundario" href="servicos.php">Voltar aos serviços</a>
                </div>
            </section>
        <?php else: ?>
            <div class="confirmacao-titulo">
                <span class="confirmacao-sobretitulo">ÚLTIMA ETAPA</span>
                <h2>Confirme seu agendamento</h2>
                <p>Confira os dados antes de registrar seu horário.</p>
            </div>

            <?php if ($erro !== ""): ?>
                <div class="confirmacao-mensagem erro"><?= escaparConfirmacao($erro) ?></div>
            <?php endif; ?>

            <?php if ($servico && $cliente && $erro === ""): ?>
                <section class="confirmacao-card">
                    <h3>Resumo do atendimento</h3>
                    <div class="confirmacao-resumo">
                        <p><strong>Profissional:</strong> <?= escaparConfirmacao($servico["profissional_nome"]) ?></p>
                        <p><strong>Serviço:</strong> <?= escaparConfirmacao($servico["nome_servico"]) ?></p>
                        <p><strong>Data:</strong> <?= escaparConfirmacao(date("d/m/Y", strtotime($data))) ?></p>
                        <p><strong>Horário:</strong> <?= escaparConfirmacao($horario) ?></p>
                        <!-- Exibe a duração em um formato fácil de entender. -->
                        <p><strong>Duração:</strong> <?= escaparConfirmacao($duracaoExibicao) ?></p>

                        <p><strong>Valor do serviço:</strong> R$ <?= number_format($precoFinal, 2, ",", ".") ?></p>
                    </div>
                </section>

                <form method="post" class="confirmacao-formulario">
                    <!-- Mantém os identificadores necessários na submissão do formulário. -->
                    <input type="hidden" name="servico_id" value="<?= (int)$servico_id ?>">
                    <input type="hidden" name="profissional_id" value="<?= (int)$profissional_id ?>">
                    <input type="hidden" name="data" value="<?= escaparConfirmacao($data) ?>">
                    <input type="hidden" name="horario" value="<?= escaparConfirmacao($horario) ?>">

                    <section class="confirmacao-card">
                        <h3>Para quem é o agendamento?</h3>
                        <label class="confirmacao-checkbox">
                            <input type="checkbox" name="para_outra_pessoa" value="1" id="para_outra_pessoa"
                                <?= $para_outra_pessoa ? "checked" : "" ?>>
                            <span>Este agendamento é para outra pessoa</span>
                        </label>
                        <div class="confirmacao-campo" id="campo_nome_pessoa" <?= $para_outra_pessoa ? "" : "hidden" ?>>
                            <label for="nome_pessoa">Nome da pessoa que receberá o atendimento</label>
                            <input type="text" name="nome_pessoa" id="nome_pessoa" maxlength="150"
                                value="<?= escaparConfirmacao($nome_pessoa) ?>"
                                placeholder="Digite o nome completo" <?= $para_outra_pessoa ? "required" : "" ?>>
                        </div>
                    </section>

                    <section class="confirmacao-card">
                        <h3>Informações de pagamento</h3>
                        <p class="confirmacao-texto">Para reservar o horário, o sinal corresponde a 30% do valor do serviço.</p>
                        <div class="confirmacao-valores">
                            <div><span>Valor total</span><strong>R$ <?= number_format($precoFinal, 2, ",", ".") ?></strong></div>
                            <div><span>Sinal de 30%</span><strong>R$ <?= number_format($valorSinal, 2, ",", ".") ?></strong></div>
                            <div><span>Valor restante</span><strong>R$ <?= number_format($valorRestante, 2, ",", ".") ?></strong></div>
                        </div>
                        <?php if ($chave_pix !== ""): ?>
                            <div class="confirmacao-pix">
                                <span class="confirmacao-pix-titulo">Chave Pix da profissional</span>
                                <div class="confirmacao-pix-chave">
                                    <div>
                                        <small><?= escaparConfirmacao($tipo_pix ?: "Chave Pix") ?></small>
                                        <strong id="chave-pix"><?= escaparConfirmacao($chave_pix) ?></strong>
                                    </div>
                                    <button type="button" class="confirmacao-copiar" id="copiar-pix">Copiar chave</button>
                                </div>
                                <p>Confira o nome do destinatário no aplicativo do banco antes de concluir o pagamento.</p>
                            </div>
                        <?php else: ?>
                            <div class="confirmacao-mensagem aviso">
                                A profissional ainda não cadastrou uma chave Pix. Entre em contato antes de realizar qualquer pagamento.
                            </div>
                        <?php endif; ?>
                    </section>

                    <label class="confirmacao-checkbox confirmacao-aceite">
                        <input type="checkbox" name="aceito_confirmar" value="1" required>
                        <span>Conferi os dados e desejo confirmar este agendamento.</span>
                    </label>

                    <div class="confirmacao-acoes">
                        <button type="submit" class="confirmacao-botao principal">Confirmar agendamento</button>
                        <a class="confirmacao-botao secundario"
                           href="agenda.php?<?= escaparConfirmacao(http_build_query([
                               "servico_id" => $servico_id,
                               "profissional_id" => $profissional_id,
                               "data" => $data
                           ])) ?>">Voltar à agenda</a>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
    // Mostra o campo do nome somente quando o agendamento for para outra pessoa.
    const opcaoOutraPessoa = document.getElementById("para_outra_pessoa");
    const campoNomePessoa = document.getElementById("campo_nome_pessoa");
    const entradaNomePessoa = document.getElementById("nome_pessoa");

    if (opcaoOutraPessoa && campoNomePessoa && entradaNomePessoa) {
        opcaoOutraPessoa.addEventListener("change", function () {
            campoNomePessoa.hidden = !this.checked;
            entradaNomePessoa.required = this.checked;
            if (!this.checked) entradaNomePessoa.value = "";
        });
    }

    // Permite copiar a chave Pix sem instalar bibliotecas externas.
    const botaoCopiarPix = document.getElementById("copiar-pix");
    const textoChavePix = document.getElementById("chave-pix");

    if (botaoCopiarPix && textoChavePix) {
        botaoCopiarPix.addEventListener("click", async function () {
            try {
                await navigator.clipboard.writeText(textoChavePix.textContent.trim());
                this.textContent = "Chave copiada";
            } catch (e) {
                this.textContent = "Copie manualmente";
            }
        });
    }
    </script>
</body>
</html>
