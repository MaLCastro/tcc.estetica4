<?php

// Inicia a sessão atual para poder destruí-la.
session_start();

// Limpa todas as variáveis armazenadas na sessão.
$_SESSION = array();

// Destrói completamente a sessão ativa no servidor.
session_destroy();

// Redireciona o usuário de volta para a tela inicial (index.php).
header("Location: index.php");

// Interrompe a execução do script.
exit();

?>