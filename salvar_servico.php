<?php

// Inicia a sessão para identificar a profissional logada.
session_start();

// Inclui a conexão com o banco de dados via PDO.
require_once("conexao.php");

// 1. Verifica se a usuária está autenticada.
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Garante que apenas contas do tipo 'profissional' executem a gravação.
if ($_SESSION["usuario_tipo"] !== "profissional") {
    header("Location: painel_cliente.php");
    exit();
}

// 3. Processa a requisição apenas se for enviada via método POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitiza e captura a ID da profissional logada na sessão.
    $usuario_id = $_SESSION["usuario_id"];

    // Captura e limpa os campos enviados pelo formulário.
    $nome = trim($_POST["nome"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $preco = filter_input(INPUT_POST, "preco", FILTER_VALIDATE_FLOAT);
    $tempo_estimado = filter_input(INPUT_POST, "tempo_estimado", FILTER_VALIDATE_INT);

    // Validação básica dos campos obrigatórios.
    if (empty($nome) || $preco === false || $preco <= 0 || $tempo_estimado === false || $tempo_estimado <= 0) {
        // Redireciona com mensagem de erro caso algum campo esteja inválido.
        header("Location: gerenciar_servicos.php?erro=campos_invalidos");
        exit();
    }

    try {
        // Instrução SQL para inserir o novo serviço vinculado à profissional logada.
        $sql = "INSERT INTO servicos (usuario_id, nome, descricao, preco, tempo_estimado, status) 
                VALUES (:usuario_id, :nome, :descricao, :preco, :tempo_estimado, 'ativo')";

        $stmt = $conexao->prepare($sql);

        // Associa os parâmetros sanitizados à query SQL.
        $stmt->bindValue(":usuario_id", $usuario_id, PDO::PARAM_INT);
        $stmt->bindValue(":nome", $nome, PDO::PARAM_STR);
        $stmt->bindValue(":descricao", $descricao, PDO::PARAM_STR);
        $stmt->bindValue(":preco", $preco);
        $stmt->bindValue(":tempo_estimado", $tempo_estimado, PDO::PARAM_INT);

        // Executa a gravação no banco de dados.
        $stmt->execute();

        // Redireciona de volta para a gestão de serviços com status de sucesso.
        header("Location: gerenciar_servicos.php?sucesso=cadastrado");
        exit();

    } catch (PDOException $e) {
        // Em ambiente de produção, grave o erro em log e exiba apenas uma mensagem amigável.
        header("Location: gerenciar_servicos.php?erro=banco");
        exit();
    }

} else {
    // Redireciona caso o arquivo seja acessado diretamente sem formulário (via GET).
    header("Location: gerenciar_servicos.php");
    exit();
}