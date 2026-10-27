<?php 

session_start();
// Inicia a sessão para verificar as credenciais da profissional e manter dados do usuário logado.

// Inclui o arquivo de conexão com o banco de dados usando PDO, tornando o objeto $conexao disponível.
require_once("conexao.php");

// 1. VERIFICAÇÃO DE AUTENTICAÇÃO
// Verifica se o usuário está logado. Se não estiver, redireciona para a página de login.
if (!isset($_SESSION["usuario_id"])) {
    // Envia o usuário para a página de login
    header("Location: login.php");
    // Interrompe a execução do script
    exit();
}

// 2. VERIFICAÇÃO DE PERMISSÃO DE ACESSO
// Garante que apenas usuários do tipo "profissional" possam acessar esta página.
// Se for cliente, redireciona para o painel do cliente.
if ($_SESSION["usuario_tipo"] !== "profissional") {
    // Envia o usuário para o painel do cliente
    header("Location: painel_cliente.php");
    // Interrompe a execução do script
    exit();
}

// =========================================================
// RECEPÇÃO E VALIDAÇÃO DO ID DO SERVIÇO
// Captura o ID enviado pela URL e verifica se é um número inteiro válido.
// =========================================================
$id_servico = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
// Armazena o ID da profissional logada para garantir segurança na busca
$id_profissional = $_SESSION["usuario_id"];

// Se o ID não for válido ou não foi enviado, retorna para a lista com mensagem de erro
if (!$id_servico) {
    header("Location: gerenciar_servicos.php?erro=id_invalida");
    exit();
}

// =========================================================
// BUSCA DOS DADOS DO SERVIÇO
// Consulta o serviço no banco garantindo que ele pertença à profissional logada.
// =========================================================
$sql = "SELECT id, nome, descricao, preco, tempo_estimado, status 
        FROM servicos 
        WHERE id = :id_servico AND usuario_id = :usuario_id";
// Prepara o comando SQL de forma segura
$stmt = $conexao->prepare($sql);
// Associa o ID do serviço ao parâmetro da consulta
$stmt->bindValue(":id_servico", $id_servico, PDO::PARAM_INT);
// Associa o ID da profissional como medida de segurança
$stmt->bindValue(":usuario_id", $id_profissional, PDO::PARAM_INT);
// Executa a consulta no banco de dados
$stmt->execute();
// Transforma o resultado em um array associativo para exibição no formulário
$servico = $stmt->fetch(PDO::FETCH_ASSOC);

// =========================================================
// VALIDAÇÃO DE EXISTÊNCIA
// Se nenhum serviço for encontrado (não existe ou pertence a outra profissional),
// retorna para a lista com mensagem de erro.
// =========================================================
if (!$servico) {
    header("Location: gerenciar_servicos.php?erro=servico_nao_encontrado");
    exit();
}
?>

<!-- Declaração do tipo de documento HTML5 -->
<!DOCTYPE html>
<!-- Define o idioma da página como Português do Brasil -->
<html lang="pt-BR">
<head>
    <!-- Define a codificação de caracteres como UTF-8, permitindo acentuação correta -->
    <meta charset="UTF-8">
    <!-- Garante que a página se adapte ao tamanho da tela do dispositivo -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Define o título que aparece na aba do navegador -->
    <title>Editar Procedimento - Painel Profissional</title>
</head>
<!-- Início do corpo da página -->
<body>
    <!-- Cabeçalho com título e link de retorno -->
    <header>
        <!-- Título da página -->
        <h1>Editar Procedimento</h1>
        <!-- Link para voltar à lista de serviços sem salvar alterações -->
        <a href="gerenciar_servicos.php">Voltar para Gerenciar Serviços</a>
    </header>
    <!-- Linha separadora visual -->
    <hr>

    <!-- Conteúdo principal da página -->
    <main>
        <section>
            <!-- Formulário que envia as alterações para o arquivo de processamento via método POST -->
            <form action="atualizar_servico.php" method="POST">
                <!-- Campo oculto que envia o identificador do serviço que será atualizado -->
                <input type="hidden" name="id" value="<?php echo $servico['id']; ?>">

                <!-- Campo para alterar o nome do serviço -->
                <div>
                    <label for="nome">Nome do Serviço:</label><br>
                    <!-- Exibe o nome atual permitindo edição; htmlspecialchars protege contra caracteres especiais -->
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($servico['nome']); ?>" required>
                </div>
                <br>

                <!-- Campo para alterar a descrição ou detalhes do serviço -->
                <div>
                    <label for="descricao">Descrição / Detalhes:</label><br>
                    <!-- Área de texto com a descrição atual permitindo edição -->
                    <textarea id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($servico['descricao'] ?? ''); ?></textarea>
                </div>
                <br>

                <!-- Campo para alterar o preço do serviço -->
                <div>
                    <label for="preco">Preço Total (R$):</label><br>
                    <!-- Exibe o preço atual permitindo edição com precisão de duas casas decimais -->
                    <input type="number" id="preco" name="preco" step="0.01" value="<?php echo $servico['preco']; ?>" required>
                </div>
                <br>

                <!-- Campo para alterar a duração estimada do atendimento -->
                <div>
                    <label for="tempo_estimado">Duração Estimada (em minutos):</label><br>
                    <!-- Exibe o tempo atual em minutos permitindo edição -->
                    <input type="number" id="tempo_estimado" name="tempo_estimado" value="<?php echo $servico['tempo_estimado']; ?>" required>
                </div>
                <br>

                <!-- Campo para alterar a situação do serviço (Ativo ou Inativo) -->
                <div>
                    <label for="status">Status do Procedimento:</label><br>
                    <!-- Lista suspensa com opções, onde a opção atual já vem selecionada -->
                    <select id="status" name="status">
                        <option value="ativo" <?php echo ($servico['status'] === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo ($servico['status'] === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>
                <br>

                <!-- Botão que envia todos os dados alterados para salvar no banco -->
                <button type="submit">Salvar Alterações</button>
            </form>
        </section>
    </main>
</body>
</html>