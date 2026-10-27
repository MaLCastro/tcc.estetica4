<?php

// Inicia a sessão para validar a profissional logada.
session_start();

// Inclui o arquivo de conexão PDO com o MySQL.
require_once("conexao.php");

// 1. Verifica se a usuária está autenticada.
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Garante que apenas contas do tipo 'profissional' acessem o script.
if ($_SESSION["usuario_tipo"] !== "profissional") {
    header("Location: painel_cliente.php");
    exit();
}

// Captura a ID do serviço vinda na URL (ex: excluir_servico.php?id=5) e a ID da profissional logada.
$id_servico = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$id_profissional = $_SESSION["usuario_id"];

// Valida se a ID enviada é um número inteiro válido.
if (!$id_servico) {
    header("Location: gerenciar_servicos.php?erro=id_invalida");
    exit();
}

try {
    // Altera o status para 'inativo' garantindo que o serviço pertença à profissional da sessão.
    $sql = "UPDATE servicos 
            SET status = 'inativo' 
            WHERE id = :id_servico AND usuario_id = :usuario_id";

    $stmt = $conexao->prepare($sql);

    // Binda os parâmetros para impedir SQL Injection e acessos indevidos.
    $stmt->bindValue(":id_servico", $id_servico, PDO::PARAM_INT);
    $stmt->bindValue(":usuario_id", $id_profissional, PDO::PARAM_INT);

    $stmt->execute();

    // Redireciona de volta com mensagem de sucesso.
    header("Location: gerenciar_servicos.php?sucesso=desativado");
    exit();

} catch (PDOException $e) {
    // Redireciona em caso de erro no banco de dados.
    header("Location: gerenciar_servicos.php?erro=banco");
    exit();
}