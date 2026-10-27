<?php
// Inicia a sessão para reconhecer a profissional que está acessando a página.
session_start();
// Inclui o arquivo que estabelece a conexão com o banco de dados usando PDO.
require_once("conexao.php");

/* =========================================================
   SEGURANÇA — VERIFICAÇÃO DE ACESSO
   ========================================================= */
// Verifica se a usuária está logada e se possui permissão de profissional.
if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "profissional") {
    // Redireciona para a página de login caso não esteja autorizada.
    header("Location: login.php");
    // Interrompe a execução do restante do código.
    exit();
}
// Armazena o identificador da profissional logada, convertido para número inteiro por segurança.
$profissional_id = (int)$_SESSION["usuario_id"];

/* =========================================================
   FUNÇÕES AUXILIARES — FORMATAÇÃO DE VALORES
   ========================================================= */
// Função responsável por formatar valores numéricos no padrão monetário brasileiro.
function formatarDinheiro($valor) {
    // Se o valor for nulo ou vazio, retorna símbolo indicando valor não informado.
    if ($valor === null || $valor === "") return "---";
    // Formata o número com duas casas decimais, vírgula como separador decimal e ponto para milhares.
    return "R$ " . number_format((float)$valor, 2, ",", ".");
}

/* =========================================================
   PROCESSAMENTO — CADASTRO, EDIÇÃO, EXCLUSÃO E CARREGAMENTO
   ========================================================= */
// Variável que armazena mensagens de confirmação das operações realizadas.
$mensagem_sucesso = "";
// Variável que armazena mensagens de erro quando algo não funciona corretamente.
$mensagem_erro = "";
// Variável que guarda os dados de um serviço quando estiver sendo editado.
$servico_edicao = null;

// Verifica se o formulário foi enviado através do método POST e se existe uma ação definida.
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["acao"])) {
    // Inicia bloco de proteção para capturar e tratar possíveis erros.
    try {
        // Identifica qual operação o usuário deseja executar.
        $acao = $_POST["acao"];

        // AÇÃO: Cadastrar um novo serviço
        if ($acao === "cadastrar") {
            // Captura o nome do serviço e remove espaços em branco desnecessários.
            $nome = trim($_POST["nome_servico"] ?? "");
            // Captura o tipo do serviço (completo, manutenção ou remoção).
            $tipo = trim($_POST["tipo_servico"] ?? "");
            // Captura a descrição detalhada do serviço.
            $descricao = trim($_POST["descricao"] ?? "");
            // Converte o preço de vírgula para ponto, formato aceito pelo banco de dados.
            $preco = !empty($_POST["preco"]) ? str_replace(',', '.', $_POST["preco"]) : null;
            // Converte o valor mínimo da faixa de preço para formato aceito pelo banco.
            $preco_min = !empty($_POST["preco_minimo"]) ? str_replace(',', '.', $_POST["preco_minimo"]) : null;
            // Converte o valor máximo da faixa de preço para formato aceito pelo banco.
            $preco_max = !empty($_POST["preco_maximo"]) ? str_replace(',', '.', $_POST["preco_maximo"]) : null;
            // Captura a duração estimada para realização do serviço.
            $duracao = trim($_POST["duracao"] ?? "");
            // Captura o status do serviço, definindo como ativo caso não seja informado.
            $status = trim($_POST["status"] ?? "ativo");

            // Validação: nome e tipo são campos obrigatórios.
            if (!$nome || !$tipo) {
                throw new Exception("Preencha o nome e o tipo do serviço.");
            }
            // Validação: deve ser informado preço fixo OU faixa de preço completa.
            if ($preco === null && ($preco_min === null || $preco_max === null)) {
                throw new Exception("Informe um preço fixo OU preencha Preço Mínimo e Máximo.");
            }
            // Validação: não é permitido informar preço fixo e faixa de preço simultaneamente.
            if ($preco !== null && ($preco_min !== null || $preco_max !== null)) {
                throw new Exception("Escolha apenas Preço Fixo OU Faixa de Preço — não ambos.");
            }

            // Prepara o comando SQL para inserir o novo serviço no banco de dados.
            $comando = $conexao->prepare("
                INSERT INTO servicos 
                (profissional_id, nome_servico, tipo_servico, descricao, preco, preco_minimo, preco_maximo, duracao, status)
                VALUES (:pid, :nome, :tipo, :descricao, :preco, :pmin, :pmax, :duracao, :status)
            ");
            // Executa o comando enviando os valores correspondentes para cada campo.
            $comando->execute([
                ":pid" => $profissional_id,
                ":nome" => $nome,
                ":tipo" => $tipo,
                ":descricao" => $descricao,
                ":preco" => $preco,
                ":pmin" => $preco_min,
                ":pmax" => $preco_max,
                ":duracao" => $duracao,
                ":status" => $status
            ]);
            // Define mensagem de confirmação do cadastro realizado com sucesso.
            $mensagem_sucesso = "Serviço cadastrado com sucesso!";
        }

        // AÇÃO: Editar um serviço já cadastrado
        elseif ($acao === "editar") {
            // Recebe o identificador do serviço que será atualizado.
            $servico_id = (int)($_POST["servico_id"] ?? 0);
            // Captura o nome do serviço enviado pelo formulário.
            $nome = trim($_POST["nome_servico"] ?? "");
            // Captura o tipo do serviço enviado pelo formulário.
            $tipo = trim($_POST["tipo_servico"] ?? "");
            // Captura a descrição enviada pelo formulário.
            $descricao = trim($_POST["descricao"] ?? "");
            // Converte o preço fixo para formato compatível com o banco de dados.
            $preco = !empty($_POST["preco"]) ? str_replace(',', '.', $_POST["preco"]) : null;
            // Converte o preço mínimo para formato compatível com o banco de dados.
            $preco_min = !empty($_POST["preco_minimo"]) ? str_replace(',', '.', $_POST["preco_minimo"]) : null;
            // Converte o preço máximo para formato compatível com o banco de dados.
            $preco_max = !empty($_POST["preco_maximo"]) ? str_replace(',', '.', $_POST["preco_maximo"]) : null;
            // Captura a duração atualizada do serviço.
            $duracao = trim($_POST["duracao"] ?? "");
            // Captura o status atualizado do serviço.
            $status = trim($_POST["status"] ?? "ativo");

            // Validação: nome e tipo são obrigatórios também na edição.
            if (!$nome || !$tipo) throw new Exception("Preencha nome e tipo.");
            // Validação: deve ser informado preço fixo ou faixa completa.
            if ($preco === null && ($preco_min === null || $preco_max === null)) {
                throw new Exception("Informe preço fixo OU faixa de preço.");
            }

            // Prepara o comando SQL para atualizar os dados do serviço.
            $comando = $conexao->prepare("
                UPDATE servicos SET
                    nome_servico = :nome, tipo_servico = :tipo, descricao = :descricao,
                    preco = :preco, preco_minimo = :pmin, preco_maximo = :pmax,
                    duracao = :duracao, status = :status
                WHERE id = :sid AND profissional_id = :pid
            ");
            // Executa a atualização garantindo que apenas a própria profissional possa alterar seus serviços.
            $comando->execute([
                ":nome" => $nome, ":tipo" => $tipo, ":descricao" => $descricao,
                ":preco" => $preco, ":pmin" => $preco_min, ":pmax" => $preco_max,
                ":duracao" => $duracao, ":status" => $status,
                ":sid" => $servico_id, ":pid" => $profissional_id
            ]);
            // Define mensagem de confirmação da alteração realizada.
            $mensagem_sucesso = "Serviço atualizado!";
        }

        // AÇÃO: Excluir um serviço cadastrado
        elseif ($acao === "excluir") {
            // Recebe o identificador do serviço que será removido.
            $servico_id = (int)($_POST["servico_id"] ?? 0);
            // Prepara e executa o comando de exclusão, garantindo que apenas a profissional dona do serviço possa excluí-lo.
            $conexao->prepare("DELETE FROM servicos WHERE id = :sid AND profissional_id = :pid")
                ->execute([":sid" => $servico_id, ":pid" => $profissional_id]);
            // Define mensagem de confirmação da exclusão.
            $mensagem_sucesso = "Serviço excluído!";
        }

        // AÇÃO: Carregar dados para o formulário de edição
        elseif ($acao === "carregar_edicao") {
            // Recebe o identificador do serviço que será carregado.
            $servico_id = (int)($_POST["servico_id"] ?? 0);
            // Prepara consulta para buscar os dados do serviço no banco de dados.
            $consulta = $conexao->prepare("SELECT * FROM servicos WHERE id = :sid AND profissional_id = :pid LIMIT 1");
            // Executa a consulta com os valores recebidos.
            $consulta->execute([":sid" => $servico_id, ":pid" => $profissional_id]);
            // Armazena os dados encontrados para preencher o formulário.
            $servico_edicao = $consulta->fetch(PDO::FETCH_ASSOC);
        }
    // Bloco de tratamento de erros
    } catch (Exception $e) {
        // Armazena a mensagem de erro para exibição na página.
        $mensagem_erro = " " . $e->getMessage();
    }
}

/* =========================================================
   CARREGAR LISTA DE SERVIÇOS CADASTRADOS
   ========================================================= */
// Inicializa variável que vai receber todos os serviços cadastrados.
$lista_servicos = [];
try {
    // Prepara consulta para buscar todos os serviços da profissional logada ordenados por nome.
    $consulta = $conexao->prepare("SELECT * FROM servicos WHERE profissional_id = :pid ORDER BY nome_servico");
    // Executa a consulta filtrando apenas os serviços da profissional logada.
    $consulta->execute([":pid" => $profissional_id]);
    // Armazena todos os registros retornados em forma de lista.
    $lista_servicos = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Caso ocorra erro na consulta, armazena mensagem de erro.
    $mensagem_erro = "Erro ao carregar serviços.";
}
?>
<!-- Define o tipo de documento como HTML5 -->
<!DOCTYPE html>
<!-- Define o idioma da página como Português do Brasil -->
<html lang="pt-BR">
<head>
    <!-- Define a codificação de caracteres para aceitar acentuação e símbolos especiais -->
    <meta charset="UTF-8">
    <!-- Faz a página se adaptar ao tamanho da tela do dispositivo do usuário -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Define o título que aparece na aba do navegador -->
    <title>Meus Serviços</title>
    <!-- Vincula o arquivo de estilos que define o visual da página -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
<!-- Área do menu lateral visível em todas as páginas do painel -->
<aside class="sidebar">
    <!-- Área que exibe o nome e especialidade da profissional logada -->
    <div class="logo">
        <h1>Estética</h1>
        <!-- Exibe a especialidade da profissional ou texto padrão caso não esteja definida -->
        <p><?= htmlspecialchars($_SESSION["especialidade"] ?? "Profissional") ?></p>
    </div>
    <!-- Navegação entre as páginas do painel da profissional -->
    <nav class="menu">
        <a href="painel_profissional.php">Início</a>
        <a href="atendimentos.php">Atendimentos</a>
        <a href="minha_agenda.php">Minha Agenda</a>
        <!-- Marca esta página como ativa no menu -->
        <a href="meus_servicos.php" class="ativo">Meus Serviços</a>
        <div class="separador"></div>
        <a href="meu_perfil.php">Meu Perfil</a>
    </nav>
    <!-- Botão para encerrar a sessão e sair do sistema -->
    <a href="logout.php" class="btn-sair">Sair</a>
</aside>
<!-- Conteúdo principal da página -->
<main class="main">
    <!-- Cabeçalho da página com título e descrição -->
    <section class="topo">
        <div>
            <h2>Meus Serviços</h2>
            <p>Cadastre e gerencie os serviços que você oferece</p>
        </div>
    </section>
    <!-- Exibe mensagem de sucesso quando alguma operação é concluída -->
    <?php if ($mensagem_sucesso): ?><div class="alerta alerta-sucesso"><?= htmlspecialchars($mensagem_sucesso) ?></div><?php endif; ?>
    <!-- Exibe mensagem de erro quando algo não funciona corretamente -->
    <?php if ($mensagem_erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($mensagem_erro) ?></div><?php endif; ?>
    <!-- Botões para alternar entre formulário e lista de serviços -->
    <div class="abas-linha">
        <button class="aba-botao ativo" onclick="abrirAba('cadastrar')"> <?= $servico_edicao ? "Editar Serviço" : "Cadastrar Serviço" ?></button>
        <button class="aba-botao" onclick="abrirAba('lista')"> Serviços Cadastrados (<?= count($lista_servicos) ?>)</button>
    </div>
    <!-- Conteúdo da aba de cadastro e edição de serviços -->
    <div class="aba-conteudo visivel" id="aba-cadastrar">
        <div class="form-horarios">
            <!-- Altera o título do formulário conforme a operação -->
            <h3><?= $servico_edicao ? "Editar Serviço" : "Cadastrar Novo Serviço" ?></h3>
            <!-- Formulário que envia dados por método POST -->
            <form method="POST">
                <!-- Campo oculto que define qual operação será realizada -->
                <input type="hidden" name="acao" value="<?= $servico_edicao ? "editar" : "cadastrar" ?>">
                <!-- Envia o identificador do serviço quando estiver em modo de edição -->
                <?php if ($servico_edicao): ?>
                    <input type="hidden" name="servico_id" value="<?= $servico_edicao["id"] ?>">
                <?php endif; ?>
                <!-- Agrupamento dos campos do formulário -->
                <div class="form-grid">
                    <!-- Campo para informar o nome do serviço -->
                    <div class="form-grupo">
                        <label>Nome do Serviço *</label>
                        <input type="text" name="nome_servico" required 
                            value="<?= htmlspecialchars($servico_edicao["nome_servico"] ?? "") ?>"
                            placeholder="Ex: Alongamento Cílios">
                    </div>
                    <!-- Campo de seleção para o tipo de serviço -->
                    <div class="form-grupo">
                        <label>Tipo de Serviço *</label>
                        <select name="tipo_servico" required>
                            <option value="">Selecione...</option>
                            <option value="completo" <?= ($servico_edicao["tipo_servico"] ?? "") === "completo" ? "selected" : "" ?>>Completo</option>
                            <option value="manutenção" <?= ($servico_edicao["tipo_servico"] ?? "") === "manutenção" ? "selected" : "" ?>>Manutenção</option>
                            <option value="remoção" <?= ($servico_edicao["tipo_servico"] ?? "") === "remoção" ? "selected" : "" ?>>Remoção</option>
                        </select>
                    </div>
                    <!-- Campo para informar o preço fixo do serviço -->
                    <div class="form-grupo">
                        <label>Preço Fixo (R$)</label>
                        <input type="text" name="preco" placeholder="0,00"
                            value="<?= !empty($servico_edicao["preco"]) ? number_format((float)$servico_edicao["preco"], 2, ",", ".") : "" ?>">
                    </div>
                    <!-- Campo para informar a duração estimada do atendimento -->
                    <div class="form-grupo">
                        <label>Duração Estimada</label>
                        <input type="text" name="duracao" placeholder="Ex: 1h30"
                            value="<?= htmlspecialchars($servico_edicao["duracao"] ?? "") ?>">
                    </div>
                    <!-- Campo para informar o valor mínimo quando usar faixa de preço -->
                    <div class="form-grupo">
                        <label>Preço Mínimo (Faixa)</label>
                        <input type="text" name="preco_minimo" placeholder="0,00"
                            value="<?= !empty($servico_edicao["preco_minimo"]) ? number_format((float)$servico_edicao["preco_minimo"], 2, ",", ".") : "" ?>">
                    </div>
                    <!-- Campo para informar o valor máximo quando usar faixa de preço -->
                    <div class="form-grupo">
                        <label>Preço Máximo (Faixa)</label>
                        <input type="text" name="preco_maximo" placeholder="0,00"
                            value="<?= !empty($servico_edicao["preco_maximo"]) ? number_format((float)$servico_edicao["preco_maximo"], 2, ",", ".") : "" ?>">
                    </div>
                    <!-- Campo para definir se o serviço está ativo ou inativo -->
                    <div class="form-grupo">
                        <label>Status</label>
                        <select name="status">
                            <option value="ativo" <?= ($servico_edicao["status"] ?? "") === "ativo" ? "selected" : "" ?>>Ativo</option>
                            <option value="inativo" <?= ($servico_edicao["status"] ?? "") === "inativo" ? "selected" : "" ?>>Inativo</option>
                        </select>
                    </div>
                    <!-- Área de texto para descrição detalhada do serviço -->
                    <div class="form-grupo linha-completa">
                        <label>Descrição</label>
                        <textarea name="descricao" placeholder="Detalhes do serviço..."><?= htmlspecialchars($servico_edicao["descricao"] ?? "") ?></textarea>
                    </div>
                </div>
                <!-- Botões de ação do formulário -->
                <div style="margin-top:25px;">
                    <!-- Botão de cancelar visível apenas durante a edição -->
                    <?php if ($servico_edicao): ?>
                        <a href="meus_servicos.php" class="btn" style="background:#eee; color:#444;">Cancelar</a>
                    <?php endif; ?>
                    <!-- Botão de envio do formulário -->
                    <button type="submit" class="btn btn-primario"><?= $servico_edicao ? "Salvar Alterações" : "Cadastrar Serviço" ?></button>
                </div>
            </form>
        </div>
    </div>
    <!-- Conteúdo da aba que exibe a lista de serviços cadastrados -->
    <div class="aba-conteudo" id="aba-lista">
        <div class="form-horarios">
            <h3>Serviços Cadastrados</h3>
            <!-- Mensagem exibida quando nenhum serviço foi cadastrado ainda -->
            <?php if (empty($lista_servicos)): ?>
                <div style="text-align:center; padding:40px; color:#888;">
                    <h4>Nenhum serviço cadastrado ainda</h4>
                    <p>Use o formulário ao lado para cadastrar seus primeiros serviços.</p>
                </div>
            <!-- Tabela com a relação de serviços cadastrados -->
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Duração</th>
                            <th>Status</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Laço de repetição que percorre cada serviço retornado do banco -->
                        <?php foreach ($lista_servicos as $s): 
                            // Define o valor a ser exibido conforme preço fixo ou faixa
                            $valor_exibido = "";
                            if ($s["preco"] !== null) {
                                $valor_exibido = formatarDinheiro($s["preco"]);
                            } elseif ($s["preco_minimo"] !== null && $s["preco_maximo"] !== null) {
                                $valor_exibido = formatarDinheiro($s["preco_minimo"]) . " a " . formatarDinheiro($s["preco_maximo"]);
                            } else {
                                $valor_exibido = "---";
                            }
                        ?>
                        <tr>
                            <!-- Exibe o nome do serviço -->
                            <td><strong><?= htmlspecialchars($s["nome_servico"]) ?></strong></td>
                            <!-- Exibe o tipo do serviço com a primeira letra em maiúscula -->
                            <td><?= ucfirst($s["tipo_servico"]) ?></td>
                            <!-- Exibe o valor formatado -->
                            <td><?= $valor_exibido ?></td>
                            <!-- Exibe a duração ou símbolo quando não houver informação -->
                            <td><?= htmlspecialchars($s["duracao"] ?? "---") ?></td>
                            <!-- Exibe o status com formatação visual diferenciada -->
                            <td>
                                <span class="etiqueta-status <?= $s["status"] === "ativo" ? "etiqueta-concluido" : "etiqueta-pendente" ?>">
                                    <?= $s["status"] === "ativo" ? "Ativo" : "Inativo" ?>
                                </span>
                            </td>
                            <!-- Botões de editar e excluir -->
                            <td style="text-align:center;">
                                <!-- Formulário para carregar os dados de um serviço para edição -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="acao" value="carregar_edicao">
                                    <input type="hidden" name="servico_id" value="<?= $s["id"] ?>">
                                    <button type="submit" class="btn" style="padding:4px 10px; font-size:12px; background:var(--cor-clara); color:var(--cor-primaria);">Editar</button>
                                </form>
                                <!-- Formulário para excluir um serviço com confirmação antes de executar -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este serviço?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="servico_id" value="<?= $s["id"] ?>">
                                    <button type="submit" class="btn" style="padding:4px 10px; font-size:12px; background:#F3E8E6; color:var(--cor-cancelado);"></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>
<!-- Script JavaScript responsável por alternar entre as abas -->
<script>
function abrirAba(nome) {
    document.querySelectorAll('.aba-botao').forEach(b => b.classList.remove('ativa'));
    document.querySelectorAll('.aba-conteudo').forEach(c => c.classList.remove('visivel'));
    event.currentTarget.classList.add('ativa');
    document.getElementById('aba-' + nome).classList.add('visivel');
}
</script>
</body>
</html>