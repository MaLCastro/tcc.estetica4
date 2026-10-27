<?php

// Inclui o arquivo responsável pela conexão com o banco de dados.
require_once("conexao.php");

// Verifica se o formulário foi enviado utilizando o método POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    // Redireciona a pessoa para a página de cadastro caso tente acessar este arquivo diretamente.
    header("Location: cadastro.php");

    // Interrompe a execução do código após o redirecionamento.
    exit();

}

// Recebe o nome enviado pelo formulário e remove espaços desnecessários no início e no final.
$nome = trim($_POST["nome"] ?? "");

// Recebe o e-mail enviado pelo formulário e remove espaços desnecessários.
$email = trim($_POST["email"] ?? "");

// Recebe o telefone enviado pelo formulário e remove espaços desnecessários.
$telefone = trim($_POST["telefone"] ?? "");

// Recebe a senha informada pela cliente.
$senha = $_POST["senha"] ?? "";

// Recebe a confirmação da senha informada pela cliente.
$confirmar_senha = $_POST["confirmar_senha"] ?? "";

// Recebe o tipo de usuário enviado ou define 'cliente' como padrão.
$tipo_usuario = $_POST["tipo_usuario"] ?? "cliente";

// Verifica se algum dos campos obrigatórios está vazio.
if (empty($nome) || empty($email) || empty($telefone) || empty($senha) || empty($confirmar_senha)) {

    // Interrompe o cadastro e informa que todos os campos devem ser preenchidos.
    die("Todos os campos devem ser preenchidos.");

}

// Verifica se o e-mail informado possui um formato válido.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    // Interrompe o cadastro caso o e-mail informado seja inválido.
    die("Digite um endereço de e-mail válido.");

}

// Verifica se a senha possui pelo menos 6 caracteres.
if (strlen($senha) < 6) {

    // Interrompe o cadastro caso a senha não tenha o tamanho mínimo definido.
    die("A senha deve possuir pelo menos 6 caracteres.");

}

// Verifica se a senha e a confirmação da senha são iguais.
if ($senha !== $confirmar_senha) {

    // Interrompe o cadastro caso as duas senhas sejam diferentes.
    die("As senhas não são iguais.");

}

// Consulta ajustada para usar a coluna 'id' exigida pelo professor.
$sql_verificar = "SELECT id FROM usuarios WHERE email = :email LIMIT 1";

// Prepara a consulta para evitar ataques de SQL Injection.
$stmt_verificar = $conexao->prepare($sql_verificar);

// Associa o e-mail informado ao parâmetro utilizado na consulta.
$stmt_verificar->bindValue(":email", $email);

// Executa a consulta de verificação no banco de dados.
$stmt_verificar->execute();

// Verifica se foi encontrado algum usuário utilizando o mesmo e-mail.
if ($stmt_verificar->rowCount() > 0) {

    // Interrompe o cadastro caso o e-mail já esteja sendo utilizado.
    die("Este e-mail já possui uma conta cadastrada.");

}

// Cria uma versão criptografada e protegida da senha antes de salvá-la.
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// Define que a nova conta será criada com o status ativo por padrão.
$status = "ativo";

// Captura a data e a hora atuais no formato do banco de dados (Ano-Mês-Dia Horas:Minutos:Segundos).
$data_cadastro = date("Y-m-d H:i:s");

// Cria a instrução SQL incluindo o campo 'data_cadastro'.
$sql_cadastro = "INSERT INTO usuarios 
(nome, email, senha, telefone, tipo_usuario, status, data_cadastro) 
VALUES 
(:nome, :email, :senha, :telefone, :tipo_usuario, :status, :data_cadastro)";

// Prepara a consulta de cadastro antes de enviar os dados ao banco.
$stmt_cadastro = $conexao->prepare($sql_cadastro);

// Associa o nome informado ao parâmetro da consulta.
$stmt_cadastro->bindValue(":nome", $nome);

// Associa o e-mail informado ao parâmetro da consulta.
$stmt_cadastro->bindValue(":email", $email);

// Associa o hash seguro da senha ao parâmetro da consulta.
$stmt_cadastro->bindValue(":senha", $senha_hash);

// Associa o telefone informado ao parâmetro da consulta.
$stmt_cadastro->bindValue(":telefone", $telefone);

// Associa o tipo de usuário ao parâmetro da consulta.
$stmt_cadastro->bindValue(":tipo_usuario", $tipo_usuario);

// Associa o status inicial ao parâmetro da consulta.
$stmt_cadastro->bindValue(":status", $status);

// Associa a data e hora do cadastro ao parâmetro da consulta.
$stmt_cadastro->bindValue(":data_cadastro", $data_cadastro);

// Executa a instrução responsável por registrar o novo usuário no banco de dados.
$stmt_cadastro->execute();
// Redireciona para a página de login após o cadastro ser concluído com sucesso.
header("Location: login.php");

// Interrompe a execução do código após o redirecionamento.
exit();

?>