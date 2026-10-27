<?php
session_start(); // Inicia uma sessão para permitir o acesso às mensagens e informações da usuária.
$erro = $_SESSION["erro"] ?? ""; // Verifica se existe uma mensagem de erro salva na sessão.
unset($_SESSION["erro"]); // Remove a mensagem de erro da sessão depois de recuperá-la.
?>
<!DOCTYPE html> <!-- Declara o tipo de documento como HTML5 -->
<html lang="pt-BR"> <!-- Define o idioma da página como Português do Brasil -->
<head>
    <meta charset="UTF-8"> <!-- Define a codificação de caracteres utilizada pela página. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Faz a página se adaptar corretamente a diferentes tamanhos de tela. -->
    <title>Login - TCC Estética</title> <!-- Define o título exibido na aba do navegador. -->
    <link rel="stylesheet" href="style.css"> <!-- Carrega o arquivo de estilos principal do sistema. -->
</head>
<body class="pagina-login"> <!-- Define classe de estilo específico para a página de login -->
<!-- =========================================================
     BOTÃO SAIR
     ========================================================= -->
<a href="index.php" class="login-sair">Sair</a> <!-- Link que retorna para a página inicial do sistema. -->
<!-- =========================================================
     CONTAINER PRINCIPAL DO LOGIN
     ========================================================= -->
<main class="login-container"> <!-- Área principal que envolve todo o conteúdo da página de login -->
    <!-- =====================================================
         LADO ESQUERDO
         ===================================================== -->
    <section class="login-lateral"> <!-- Seção lateral esquerda com mensagem e elementos decorativos -->
        <div class="login-coracao login-coracao-superior">♡</div> <!-- Elemento decorativo em formato de coração no topo. -->
        <h1>Seu Espaço</h1> <!-- Título que exibe o nome ou chamada do espaço estético. -->
        <p>BELEZA&nbsp;&nbsp;·&nbsp;&nbsp;CUIDADO&nbsp;&nbsp;·&nbsp;&nbsp;CONFIANÇA</p> <!-- Frase com os valores e princípios do estabelecimento. -->
        <div class="login-coracao login-coracao-inferior">♡</div> <!-- Elemento decorativo em formato de coração na parte inferior. -->
    </section>
    <!-- =====================================================
         LADO DIREITO
         ===================================================== -->
    <section class="login-direita"> <!-- Seção direita onde fica o formulário de acesso -->
        <!-- =================================================
             CARTÃO DO LOGIN
             ================================================= -->
        <div class="login-card"> <!-- Caixa branca central que agrupa todo o conteúdo de login -->
            <!-- =================================================
                 CABEÇALHO
                 ================================================= -->
            <div class="login-cabecalho"> <!-- Área superior do cartão com título e apresentação -->
                <h2>Bem-vinda!</h2> <!-- Título principal que saúda a usuária. -->
                <div class="login-divisor"> <!-- Linha decorativa que separa o título do conteúdo -->
                    <span></span> <!-- Linha esquerda do divisor decorativo. -->
                    <strong>♡</strong> <!-- Símbolo central em formato de coração. -->
                    <span></span> <!-- Linha direita do divisor decorativo. -->
                </div>
                <p>Faça login para acessar o seu painel<br>e gerenciar seus atendimentos.</p> <!-- Texto explicando o propósito da página. -->
            </div>
            <!-- =================================================
                 MENSAGEM DE ERRO
                 ================================================= -->
            <?php if (!empty($erro)): ?> <!-- Verifica se existe mensagem de erro para exibir -->
                <div class="login-erro"> <!-- Caixa com estilo próprio para mensagens de erro -->
                    <?php echo htmlspecialchars($erro); ?> <!-- Exibe a mensagem de erro de forma segura. -->
                </div>
            <?php endif; ?>
            <!-- =================================================
                 FORMULÁRIO DE LOGIN
                 ================================================= -->
            <form action="verifica_login.php" method="POST"> <!-- Formulário que envia os dados para o arquivo de verificação -->
                <div class="login-campo"> <!-- Agrupa o rótulo e o campo de e-mail -->
                    <label for="email" class="sr-only">E-mail</label> <!-- Rótulo oculto acessível para leitores de tela -->
                    <input type="email" id="email" name="email" placeholder="Digite seu e-mail" autocomplete="email" required> <!-- Caixa de texto para digitação do e-mail -->
                </div>
                <div class="login-campo"> <!-- Agrupa o rótulo e o campo de senha -->
                    <label for="senha" class="sr-only">Senha</label> <!-- Rótulo oculto acessível para leitores de tela -->
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" autocomplete="current-password" required> <!-- Caixa de texto para digitação da senha com caracteres ocultos -->
                </div>
                <button type="submit" class="login-botao">Entrar</button> <!-- Botão que envia os dados do formulário para verificação -->
            </form>
            <!-- =================================================
                 OPÇÕES EXTRAS
                 ================================================= -->
            <div class="login-links"> <!-- Área com links complementares abaixo do formulário -->
                <a href="recuperar_senha.php" class="recuperar-acesso">Esqueceu sua senha? Recuperar acesso</a> <!-- Link para a página de recuperação de senha -->
                <a href="cadastro.php" class="criar-conta">Ainda não possui uma conta? Criar conta</a> <!-- Link para a página de cadastro de novos usuários -->
            </div>
        </div> <!-- Fecha a div do cartão branco do login -->
    </section> <!-- Fecha a seção do lado direito -->
</main> <!-- Fecha o container principal da página -->
</body>
</html>