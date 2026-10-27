<?php

// Define o endereço do servidor onde o banco de dados está sendo executado.
$host = "localhost";

// Define o nome do banco de dados utilizado pelo sistema.
$banco = "tcc.estetica";

// Define o usuário utilizado para acessar o banco de dados no Laragon.
$usuario = "root";

// Define a senha do usuário do banco de dados.
// No Laragon, normalmente o usuário root não possui senha por padrão.
$senha = "";

// Inicia um bloco para tentar realizar a conexão com o banco de dados.
try {

    // Cria a conexão com o banco de dados utilizando PDO.
    $conexao = new PDO(
        "mysql:host=$host;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha
    );

    // Define que os erros do banco de dados serão tratados como exceções.
    $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Define que os resultados das consultas serão organizados em arrays associativos.
    $conexao->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $erro) {

    // Interrompe a execução do sistema e mostra uma mensagem caso ocorra um erro na conexão.
    die("Erro ao conectar ao banco de dados: " . $erro->getMessage());

}

?>
