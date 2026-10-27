<?php

// Inicia a sessão para validar a profissional.
session_start();

// Inclui o arquivo de conexão com o banco de dados usando PDO, tornando o objeto $conexao disponível.
require_once("conexao.php");

// 1. Verifica se o usuário está logado. Caso não esteja, redireciona para a página de login.
if (!isset($_SESSION["usuario_id"])) {
    // Envia o usuário para a página de login
    header("Location: login.php");
    // Interrompe a execução do script imediatamente
    exit();
}

// Verifica o tipo de usuário. Se não for profissional, redireciona para o painel do cliente.
if ($_SESSION["usuario_tipo"] !== "profissional") {
    // Envia o usuário para a página do painel do cliente
    header("Location: painel_cliente.php");
    // Interrompe a execução do script
    exit();
}

// 2. Processa os dados SOMENTE quando o formulário é enviado através do método POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Armazena o ID do usuário logado (profissional) para garantir que só ele possa alterar seus próprios registros.
    $id_profissional = $_SESSION["usuario_id"];

    // Captura o ID do serviço enviado pelo formulário e valida se é um número inteiro.
    $id_servico = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
    // Recebe o nome do serviço, removendo espaços em branco no início e no fim do texto.
    $nome = trim($_POST["nome"] ?? "");
    // Recebe a descrição do serviço, removendo espaços em branco no início e no fim do texto.
    $descricao = trim($_POST["descricao"] ?? "");
    // Recebe o preço e valida se é um número decimal.
    $preco = filter_input(INPUT_POST, "preco", FILTER_VALIDATE_FLOAT);
    // Recebe o tempo estimado de atendimento e valida se é um número inteiro.
    $tempo_estimado = filter_input(INPUT_POST, "tempo_estimado", FILTER_VALIDATE_INT);
    // Recebe o status do serviço; se não for enviado, define "ativo" como valor padrão.
    $status = trim($_POST["status"] ?? "ativo");

    // Validação básica: verifica se todos os campos essenciais foram preenchidos corretamente.
    // Se algum campo estiver vazio ou com valor inválido, retorna ao formulário com mensagem de erro.
    if (!$id_servico || empty($nome) || $preco === false || $preco <= 0 || $tempo_estimado === false || $tempo_estimado <= 0) {
        // Redireciona de volta para a página de edição informando que há campos inválidos
        header("Location: editar_servico.php?id=" . $id_servico . "&erro=campos_invalidos");
        // Interrompe a execução
        exit();
    }

    // Inicia bloco de proteção para capturar possíveis erros durante a execução no banco de dados.
    try {
        // Comando SQL para atualizar os dados do serviço.
        // A cláusula WHERE garante que apenas o serviço pertencente à profissional logada será alterado.
        $sql = "UPDATE servicos 
                SET nome = :nome, 
                    descricao = :descricao, 
                    preco = :preco, 
                    tempo_estimado = :tempo_estimado, 
                    status = :status 
                WHERE id = :id_servico AND usuario_id = :usuario_id";

        // Prepara o comando SQL de forma segura contra injeção de código malicioso.
        $stmt = $conexao->prepare($sql);

        // Associa cada valor recebido do formulário ao seu respectivo campo no comando SQL, definindo o tipo de dado.
        $stmt->bindValue(":nome", $nome, PDO::PARAM_STR);
        $stmt->bindValue(":descricao", $descricao, PDO::PARAM_STR);
        $stmt->bindValue(":preco", $preco);
        $stmt->bindValue(":tempo_estimado", $tempo_estimado, PDO::PARAM_INT);
        $stmt->bindValue(":status", $status, PDO::PARAM_STR);
        $stmt->bindValue(":id_servico", $id_servico, PDO::PARAM_INT);
        $stmt->bindValue(":usuario_id", $id_profissional, PDO::PARAM_INT);

        // Executa o comando de atualização no banco de dados.
        $stmt->execute();

        // Redireciona para a lista de serviços com mensagem de sucesso na alteração.
        header("Location: gerenciar_servicos.php?sucesso=atualizado");
        // Interrompe a execução
        exit();

    // Captura qualquer erro relacionado ao banco de dados durante o processo de atualização.
    } catch (PDOException $e) {
        // Redireciona informando que ocorreu um erro no banco de dados
        header("Location: gerenciar_servicos.php?erro=banco");
        // Interrompe a execução
        exit();
    }

// Caso a página seja acessada sem envio de formulário, retorna para a lista de serviços.
} else {
    header("Location: gerenciar_servicos.php");
    exit();
}