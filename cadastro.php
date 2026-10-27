<!-- Declaração do tipo de documento, informando ao navegador que se trata de um arquivo HTML5 -->
<!DOCTYPE html>

<!-- Define o idioma da página como Português do Brasil -->
<html lang="pt-BR">

<!-- Cabeçalho com configurações e recursos externos da página -->
<head>
    <!-- Define a codificação de caracteres como UTF-8, permitindo acentuação e símbolos especiais corretos -->
    <meta charset="UTF-8">
    <!-- Garante que a página se adapte ao tamanho da tela do dispositivo do usuário -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Define o título que aparece na aba do navegador -->
    <title>Criar conta - Seu Espaço</title>
    <!-- Importa o arquivo de estilos CSS que define a aparência visual da página -->
    <link rel="stylesheet" href="style.css">
</head>

<!-- Início do corpo da página, onde todo conteúdo visível é apresentado -->
<body class="cadastro-page">

    <!-- =========================================================
         BOTÃO DE RETORNO
         Botão localizado no canto superior que leva o usuário de volta à página inicial
         ========================================================= -->
    <!-- Link que direciona para a página inicial quando clicado -->
    <a href="index.php" class="btn-sair-cadastro">
        Sair
    </a>

    <!-- =========================================================
         ESTRUTURA PRINCIPAL DA PÁGINA
         Container que divide a página em áreas visuais distintas
         ========================================================= -->
    <main class="cadastro-container">

        <!-- =====================================================
             ÁREA ESQUERDA — APRESENTAÇÃO
             Lado decorativo com nome e frase do sistema
             ===================================================== -->
        <section class="cadastro-lateral">
            <!-- Elemento decorativo em formato de coração no canto superior -->
            <span class="decoracao-coracao cadastro-coracao-topo">♡</span>
            <!-- Nome principal do estabelecimento ou marca -->
            <h2>Seu Espaço</h2>
            <!-- Frase de apresentação com os valores do projeto -->
            <p class="cadastro-slogan">
                BELEZA&nbsp;&nbsp;·&nbsp;&nbsp; CUIDADO&nbsp;&nbsp;·&nbsp;&nbsp; CONFIANÇA
            </p>
            <!-- Elemento decorativo em formato de coração na parte inferior -->
            <span class="decoracao-coracao cadastro-coracao-baixo">♡</span>
        </section>

        <!-- =====================================================
             ÁREA DIREITA — FORMULÁRIO
             Área branca onde o usuário preenche seus dados
             ===================================================== -->
        <section class="cadastro-area">
            <!-- Cartão branco que agrupa todo o conteúdo do formulário -->
            <div class="cadastro-card">

                <!-- =================================================
                     TÍTULO E APRESENTAÇÃO DO FORMULÁRIO
                     ================================================= -->
                <!-- Título principal da seção de cadastro -->
                <h1>Crie sua conta</h1>
                <!-- Linha decorativa com símbolo central -->
                <div class="cadastro-divisor">
                    <span></span>
                    <span class="cadastro-simbolo">♡</span>
                    <span></span>
                </div>
                <!-- Texto orientando o usuário sobre o que fazer -->
                <p class="cadastro-subtitulo">
                    Preencha seus dados para realizar seu cadastro.
                </p>

                <!-- =================================================
                     FORMULÁRIO DE CADASTRO
                     Envia os dados preenchidos para o arquivo de processamento
                     ================================================= -->
                <form action="salvar_cadastro.php" method="POST" class="form-cadastro">
                    <!-- Campo oculto que define automaticamente o tipo de usuário como "cliente" -->
                    <input
                        type="hidden"
                        name="tipo_usuario"
                        value="cliente"
                    >

                    <!-- =================================================
                         CAMPO DE NOME COMPLETO
                         ================================================= -->
                    <div class="campo-cadastro">
                        <!-- Texto que identifica o campo para o usuário -->
                        <label for="nome">
                            Nome completo
                        </label>
                        <!-- Campo de texto onde o usuário digita seu nome -->
                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            placeholder="Digite seu nome completo"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <!-- =================================================
                         CAMPO DE E-MAIL
                         ================================================= -->
                    <div class="campo-cadastro">
                        <!-- Texto que identifica o campo para o usuário -->
                        <label for="email">
                            E-mail
                        </label>
                        <!-- Campo que aceita e valida formato de e-mail -->
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Digite seu e-mail"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <!-- =================================================
                         CAMPO DE TELEFONE
                         ================================================= -->
                    <div class="campo-cadastro">
                        <!-- Texto que identifica o campo para o usuário -->
                        <label for="telefone">
                            Telefone
                        </label>
                        <!-- Campo para digitação do número de contato -->
                        <input
                            type="tel"
                            id="telefone"
                            name="telefone"
                            placeholder="Digite seu telefone"
                            autocomplete="tel"
                            required
                        >
                    </div>

                    <!-- =================================================
                         CAMPO DE SENHA
                         ================================================= -->
                    <div class="campo-cadastro">
                        <!-- Texto que identifica o campo para o usuário -->
                        <label for="senha">
                            Senha
                        </label>
                        <!-- Campo para criação de senha mínima de 6 caracteres -->
                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            placeholder="Crie uma senha"
                            minlength="6"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <!-- =================================================
                         CONFIRMAÇÃO DE SENHA
                         ================================================= -->
                    <div class="campo-cadastro">
                        <!-- Texto que identifica o campo para o usuário -->
                        <label for="confirmar_senha">
                            Confirmar senha
                        </label>
                        <!-- Campo para repetir a senha e conferir se não houve erro de digitação -->
                        <input
                            type="password"
                            id="confirmar_senha"
                            name="confirmar_senha"
                            placeholder="Digite sua senha novamente"
                            minlength="6"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <!-- =================================================
                         BOTÃO DE ENVIO
                         ================================================= -->
                    <!-- Botão que envia todos os dados preenchidos para processamento -->
                    <button
                        type="submit"
                        class="btn-criar-conta"
                    >
                        Criar conta
                    </button>
                </form>

                <!-- =================================================
                     LINK PARA QUEM JÁ TEM CONTA
                     ================================================= -->
                <!-- Área com opção de acesso para usuários já cadastrados -->
                <div class="cadastro-login">
                    <span>Já possui uma conta?</span>
                    <!-- Link que leva para a página de login -->
                    <a href="login.php">
                        Entrar
                    </a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>