<?php

// Inicia ou retoma a sessão do usuário, permitindo armazenar e verificar dados durante o acesso.
session_start();

// Inclui o arquivo de conexão com o banco de dados através do PDO, tornando o objeto $conexao disponível.
require_once("conexao.php");

// =========================================================
// VERIFICAÇÃO DE ENVIO DO FORMULÁRIO
// Processa os dados SOMENTE quando são enviados pelo método POST.
// =========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // =========================================================
    // RECEBIMENTO DOS DADOS
    // Captura o e-mail e a senha enviados pelo formulário de login.
    // =========================================================
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    // =========================================================
    // VALIDAÇÃO DE PREENCHIMENTO
    // Verifica se os campos foram preenchidos. Se estiverem vazios, retorna com mensagem de erro.
    // =========================================================
    if (empty($email) || empty($senha)) {
        header("Location: login.php?erro=campos_vazios");
        exit();
    }

    // =========================================================
    // BUSCA DO USUÁRIO NO BANCO
    // Prepara e executa uma consulta para encontrar o usuário pelo e-mail informado.
    // =========================================================
    $sql = "SELECT id, nome, email, senha, tipo_usuario, especialidade 
            FROM usuarios 
            WHERE email = :email 
            LIMIT 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bindValue(":email", $email);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // =========================================================
    // VERIFICAÇÃO DE EXISTÊNCIA E SENHA
    // Verifica se o usuário foi encontrado e se a senha corresponde ao hash armazenado.
    // =========================================================
    if (!$usuario || !password_verify($senha, $usuario["senha"])) {
        header("Location: login.php?erro=credenciais");
        exit();
    }

    // =========================================================
    // CRIAÇÃO DAS VARIÁVEIS DE SESSÃO
    // Ao confirmar os dados, armazena as informações do usuário na sessão.
    // Esses dados ficam disponíveis em todas as páginas enquanto o usuário permanecer logado.
    // =========================================================
    $_SESSION["usuario_id"] = $usuario["id"];
    $_SESSION["usuario_nome"] = $usuario["nome"];
    $_SESSION["usuario_email"] = $usuario["email"];
    $_SESSION["usuario_tipo"] = $usuario["tipo_usuario"];
    $_SESSION["especialidade"] = $usuario["especialidade"];

    // =========================================================
    // REDIRECIONAMENTO POR TIPO DE USUÁRIO
    // Após o login bem-sucedido, encaminha o usuário para o painel correspondente ao seu tipo.
    // =========================================================
    if ($usuario["tipo_usuario"] === "profissional") {
        // Se for profissional, vai para o painel de profissional
        header("Location: painel_profissional.php");
    } else {
        // Se for cliente, vai para o painel de cliente
        header("Location: painel_cliente.php");
    }
    exit();

// =========================================================
// ACESSO DIRETO À PÁGINA
// Se a página for acessada sem envio de formulário, retorna para a tela de login.
// =========================================================
} else {
    header("Location: login.php");
    exit();
}