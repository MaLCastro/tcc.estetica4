<?php
// Inicia a sessão para armazenar os dados do usuário autenticado.
session_start();
// Inclui o arquivo responsável pela conexão com o banco de dados.
require_once("conexao.php");

// Verifica se os dados foram enviados pelo formulário usando POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

// Recebe o e-mail e remove espaços desnecessários.
$email = trim($_POST["email"] ?? "");
// Recebe a senha informada no formulário.
$senha = $_POST["senha"] ?? "";

// Verifica se algum dos campos obrigatórios está vazio.
if (empty($email) || empty($senha)) {
    $_SESSION["erro"] = "Preencha todos os campos para entrar.";
    header("Location: login.php");
    exit();
}

// Cria a consulta para buscar o usuário pelo e-mail.
$sql = "SELECT id, nome, email, senha, tipo_usuario, especialidade, status
        FROM usuarios
        WHERE email = :email
        LIMIT 1";

$stmt = $conexao->prepare($sql);
$stmt->bindValue(":email", $email, PDO::PARAM_STR);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se o usuário existe e se a senha está correta.
if ($usuario && password_verify($senha, $usuario["senha"])) {

    // Verifica se a conta está ativa.
    if ($usuario["status"] !== "ativo") {
        $_SESSION["erro"] = "Sua conta está inativa. Entre em contato com a estética.";
        header("Location: login.php");
        exit();
    }

    // Regenera o ID da sessão após login bem-sucedido.
    session_regenerate_id(true);

    // Armazena os dados na sessão — NOMES PADRONIZADOS!
    $_SESSION["usuario_id"] = $usuario["id"];
    $_SESSION["usuario_nome"] = $usuario["nome"];
    $_SESSION["usuario_tipo"] = $usuario["tipo_usuario"];
    $_SESSION["especialidade"] = $usuario["especialidade"] ?? ""; // ✅ Nome correto para o painel

    // Redireciona conforme o tipo de usuário.
    if ($usuario["tipo_usuario"] === "profissional") {
        header("Location: painel_profissional.php");
        exit();
    }
    if ($usuario["tipo_usuario"] === "cliente") {
        header("Location: painel_cliente.php");
        exit();
    }

    // Tipo de usuário desconhecido.
    $_SESSION["erro"] = "Perfil de acesso não configurado.";
    header("Location: login.php");
    exit();

} // ✅ CHAVE QUE ESTAVA FALTANDO — fecha o bloco de login bem-sucedido

// Se chegou aqui → e-mail ou senha incorretos.
$_SESSION["erro"] = "E-mail ou senha incorretos.";
header("Location: login.php");
exit();