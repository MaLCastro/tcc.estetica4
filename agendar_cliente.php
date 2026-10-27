<?php
// Inicia ou retoma a sessão do usuário, permitindo acesso às variáveis armazenadas (ex: id, tipo, nome)
session_start();

// Inclui o arquivo de conexão com o banco de dados, tornando o objeto $conexao disponível para uso
require_once("conexao.php");

// =========================================================
// SEGURANÇA — VERIFICAÇÃO DE ACESSO
// Bloqueia o acesso à página caso o usuário não esteja logado
// ou não tenha permissão de conta do tipo "profissional"
// =========================================================
if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "profissional") {
    // Redireciona o usuário para a página de login
    header("Location: login.php");
    // Encerra a execução do script imediatamente após o redirecionamento
    exit();
}

// Converte o ID do usuário logado para o tipo inteiro, garantindo segurança no uso
$profissional_id = (int)$_SESSION["usuario_id"];

// Inicializa variáveis vazias para armazenar mensagens de retorno ao usuário
$mensagem_sucesso = "";
$mensagem_erro = "";

// =========================================================
// PROCESSAMENTO DO FORMULÁRIO
// Verifica se o formulário foi enviado e se existe uma ação definida
// =========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"])) {
    // Inicia bloco de tratamento de erros para capturar falhas durante o cadastro
    try {
        // Verifica se a ação recebida é de "reservar" um horário
        if ($_POST["acao"] === "reservar") {
            // Remove espaços em branco do início e fim do valor recebido da data
            $data = trim($_POST["data_agendamento"] ?? "");
            // Remove espaços em branco do início e fim do valor recebido do horário
            $horario = trim($_POST["horario"] ?? "");
            // Remove espaços em branco do início e fim do nome do cliente
            $cliente_nome = trim($_POST["cliente_nome"] ?? "");
            // Remove espaços em branco do início e fim do telefone do cliente
            $cliente_telefone = trim($_POST["cliente_telefone"] ?? "");
            // Converte o ID do serviço para inteiro, evitando valores indevidos
            $servico_id = (int)($_POST["servico_id"] ?? 0);
            // Remove espaços em branco das observações adicionais
            $observacoes = trim($_POST["observacoes"] ?? "");

            // =========================================================
            // VALIDAÇÃO DE CAMPOS OBRIGATÓRIOS
            // Verifica se os campos essenciais foram preenchidos
            // =========================================================
            if (!$data || !$horario || !$cliente_nome || !$servico_id) {
                // Interrompe o processo e exibe mensagem de orientação ao usuário
                throw new Exception("Preencha data, horário, cliente e serviço.");
            }

            // =========================================================
            // PREPARAÇÃO E EXECUÇÃO DO CADASTRO NO BANCO
            // Cria o comando SQL de inserção usando PDO com segurança contra injeção
            // =========================================================
            $comando = $conexao->prepare("
                INSERT INTO agendamentos 
                (profissional_id, cliente_id, data_agendamento, horario, servico_id, status, observacoes)
                VALUES (:pid, 
                    (SELECT id FROM usuarios WHERE nome = :nome LIMIT 1),
                    :data, :horario, :sid, 'pendente', :obs)
            ");
            // Associa os valores recebidos aos campos do comando e executa
            $comando->execute([
                ":pid" => $profissional_id,       // ID da profissional logada
                ":nome" => $cliente_nome,          // Nome usado para buscar o ID do cliente
                ":data" => $data,                  // Data do agendamento
                ":horario" => $horario,            // Horário do agendamento
                ":sid" => $servico_id,             // Serviço escolhido
                ":obs" => $observacoes             // Observações adicionais
            ]);

            // Define mensagem positiva informando que o horário foi salvo
            $mensagem_sucesso = "Horário RESERVADO com sucesso!";
        }
    } catch (Exception $e) {
        // Captura qualquer erro ocorrido e armazena mensagem para exibição
        $mensagem_erro = "erro " . $e->getMessage();
    }
}

// =========================================================
// CARREGAMENTO DOS SERVIÇOS CADASTRADOS
// Busca apenas os serviços da profissional logada para exibir no formulário
// =========================================================
$servicos = $conexao->prepare("SELECT id, nome_servico FROM servicos WHERE profissional_id = :pid ORDER BY nome_servico");
// Passa o ID da profissional como parâmetro seguro
$servicos->execute([":pid" => $profissional_id]);
// Transforma o resultado em um array associativo para uso no formulário
$lista_servicos = $servicos->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Declaração do tipo de documento HTML5 -->
<!DOCTYPE html>
<html lang="pt-BR">
<!-- Cabeçalho com configurações da página -->
<head>
    <!-- Define o conjunto de caracteres como UTF-8, permitindo acentuação correta -->
    <meta charset="UTF-8">
    <!-- Garante que a página se adapte ao tamanho da tela do dispositivo -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Define o título que aparece na aba do navegador -->
    <title>Reservar Horário</title>
    <!-- Importa o arquivo de estilos padronizado do sistema -->
    <link rel="stylesheet" href="style.css">
</head>
<!-- Início do corpo da página -->
<body>
<!-- Barra lateral de navegação comum às páginas do painel -->
<aside class="sidebar">
    <!-- Área superior com nome do sistema e especialidade da profissional logada -->
    <div class="logo"><h1>Estética</h1><p><?= htmlspecialchars($_SESSION["especialidade"] ?? "") ?></p></div>
    <!-- Menu de navegação entre as páginas do painel -->
    <nav class="menu">
        <a href="painel_profissional.php">Início</a>
        <a href="minha_agenda.php" class="ativo">Minha Agenda</a>
        <a href="meus_servicos.php">Meus Serviços</a>
        <a href="atendimentos.php">Atendimentos</a>
        <!-- Linha separadora visual -->
        <div class="separador"></div>
        <a href="meu_perfil.php">Meu Perfil</a>
    </nav>
    <!-- Botão de encerramento da sessão -->
    <a href="logout.php" class="btn-sair">Sair</a>
</aside>

<!-- Conteúdo principal da página -->
<main class="main">
    <!-- Cabeçalho interno da página com título e descrição breve -->
    <section class="topo">
        <div><h2>Reservar Horário</h2><p>Reserve um horário para um cliente — pode ser alterado ou cancelado depois</p></div>
    </section>

    <!-- Exibe mensagem de sucesso se o agendamento foi realizado -->
    <?php if ($mensagem_sucesso): ?><div class="alerta alerta-sucesso"><?= htmlspecialchars($mensagem_sucesso) ?></div><?php endif; ?>
    <!-- Exibe mensagem de erro caso tenha ocorrido alguma falha -->
    <?php if ($mensagem_erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($mensagem_erro) ?></div><?php endif; ?>

    <!-- Formulário de cadastro do agendamento -->
    <div class="form-horarios">
        <form method="POST">
            <!-- Campo oculto que identifica a operação como "reservar" -->
            <input type="hidden" name="acao" value="reservar">
            
            <!-- Grade organizada com os campos do formulário -->
            <div class="form-grid">
                <!-- Campo para informar a data do atendimento -->
                <div class="form-grupo">
                    <label>Data *</label>
                    <input type="date" name="data_agendamento" required>
                </div>
                <!-- Campo para informar o horário do atendimento -->
                <div class="form-grupo">
                    <label>Horário *</label>
                    <input type="time" name="horario" required>
                </div>
                <!-- Campo para informar o nome do cliente -->
                <div class="form-grupo">
                    <label>Nome do Cliente *</label>
                    <input type="text" name="cliente_nome" placeholder="Nome completo" required>
                </div>
                <!-- Campo opcional para contato -->
                <div class="form-grupo">
                    <label>Telefone</label>
                    <input type="tel" name="cliente_telefone" placeholder="(00) 00000-0000">
                </div>
                <!-- Lista suspensa com os serviços cadastrados pela profissional -->
                <div class="form-grupo linha-completa">
                    <label>Serviço *</label>
                    <select name="servico_id" required>
                        <option value="">Selecione...</option>
                        <!-- Estrutura de repetição que percorre cada serviço encontrado -->
                        <?php foreach ($lista_servicos as $s): ?>
                            <!-- Exibe o nome do serviço e usa o ID como valor enviado -->
                            <option value="<?= $s["id"] ?>"><?= htmlspecialchars($s["nome_servico"]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Campo de texto livre para observações adicionais -->
                <div class="form-grupo linha-completa">
                    <label>Observações</label>
                    <textarea name="observacoes" placeholder="Informações adicionais..."></textarea>
                </div>
            </div>

            <!-- Botões de navegação e envio do formulário -->
            <div style="margin-top:25px;">
                <!-- Link para retornar à agenda sem salvar -->
                <a href="minha_agenda.php" class="btn" style="background:#eee; color:#444;">← Voltar</a>
                <!-- Botão que envia os dados para cadastrar o agendamento -->
                <button type="submit" class="btn btn-primario">Confirmar Reserva</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>