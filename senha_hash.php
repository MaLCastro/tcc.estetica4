
<?php

// Define a senha que será utilizada para gerar o hash inicial.
// A senha possui 6 caracteres, atendendo ao requisito mínimo definido.
$senha = "123456";

// Verifica se a senha possui pelo menos 6 caracteres.
if (strlen($senha) < 6) {

    // Exibe uma mensagem caso a senha não atenda ao tamanho mínimo.
    echo "A senha precisa ter no mínimo 6 caracteres.";

} else {

    // Cria uma versão criptografada da senha utilizando o algoritmo padrão do PHP.
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    // Exibe a senha original apenas para fins de teste.
    echo "Senha original: " . $senha . "<br>";

    // Exibe a senha criptografada que poderá ser copiada para testes no banco.
    echo "Senha hash: " . $senha_hash;

}

?>