<!DOCTYPE html> <!-- Define o tipo de documento como HTML5 -->
<html lang="pt-BR"> <!-- Define o idioma da página como português do Brasil -->
<head>
    <meta charset="UTF-8"> <!-- Define a codificação de caracteres para aceitar acentuação e símbolos especiais -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Faz a página se adaptar a diferentes tamanhos de tela (celulares, tablets, computadores) -->
    <title>Espaço Estética</title> <!-- Nome que aparece na aba do navegador -->
    <link rel="stylesheet" href="style.css"> <!-- Importa o arquivo de estilos com as cores, fontes e formatação do site -->
</head>
<body>
<!-- CABEÇALHO -->
<header class="cabecalho"> <!-- Área superior fixa com nome do site e botões de acesso -->
    <h1 class="logo">Espaço Estética</h1> <!-- Nome do estabelecimento exibido em destaque -->
    <nav> <!-- Área de navegação com links de acesso -->
        <a href="login.php" class="link-entrar">Entrar</a> <!-- Link para a página de login para usuários já cadastrados -->
        <a href="cadastro.php" class="btn-rosa">Criar conta</a> <!-- Botão destacado para novos usuários se cadastrarem -->
    </nav>
</header>
<!-- CONTEÚDO PRINCIPAL -->
<main class="conteudo"> <!-- Área central com o conteúdo principal da página inicial -->
    <section class="destaque"> <!-- Seção principal com chamada e apresentação do sistema -->
        <h2>Realce sua beleza e cuide do seu bem-estar</h2> <!-- Título principal que apresenta o propósito do espaço -->
        <p>Agende seus horários de forma simples e rápida, visualize seu histórico de procedimentos e escolha suas profissionais favoritas.</p> <!-- Descrição explicando as vantagens e funcionalidades do sistema -->
        <div class="botoes"> <!-- Área com os botões de ação principais -->
            <a href="#" class="btn-rosa">Agendar meu horário</a> <!-- Botão principal para iniciar um agendamento (link a ser definido) -->
            <a href="login.php" class="btn-marrom">Já sou cliente</a> <!-- Botão alternativo para clientes que já possuem conta -->
        </div>
    </section>
    <div class="separador"></div> <!-- Linha visual que separa as seções com estilo definido no CSS -->
    <section class="servicos"> <!-- Seção que apresenta os tipos de atendimentos disponíveis -->
        <h2>Conheça nossos procedimentos</h2> <!-- Título da seção de serviços -->
        <div class="grade-servicos"> <!-- Container que organiza os cartões de serviços em formato de grade -->
            <div class="cartao"> <!-- Cartão com informações do serviço de sobrancelhas -->
                <h3>Design de Sobrancelhas</h3> <!-- Nome do procedimento -->
                <p>Modelagem completa para valorizar o seu olhar com precisão e cuidado.</p> <!-- Breve descrição do serviço oferecido -->
            </div>
            <div class="cartao"> <!-- Cartão com informações do serviço de cílios -->
                <h3>Lash Designer</h3> <!-- Nome do procedimento -->
                <p>Aplicação cuidadosa para realçar sua beleza com leveza e precisão.</p> <!-- Breve descrição do serviço oferecido -->
            </div>
            <div class="cartao"> <!-- Cartão com informações do serviço de unhas -->
                <h3>Nail Designer</h3> <!-- Nome do procedimento -->
                <p>Criação e acabamento que valorizam cada detalhe com carinho e perfeição.</p> <!-- Breve descrição do serviço oferecido -->
            </div>
        </div>
    </section>
</main>
<!-- RODAPÉ -->
<footer class="rodape"> <!-- Área inferior da página com informações de copyright -->
    <p>© 2026 Espaço Estética — Sistema de Agendamentos Online</p> <!-- Texto de rodapé com ano e nome do sistema -->
</footer>
</body>
</html>