<?php 
// Inicia a sessão para verificar as credenciais da profissional logada e manter dados de acesso.
session_start();

// Inclui o arquivo de conexão com o banco de dados através do PDO, tornando o objeto $conexao disponível.
require_once("conexao.php");

// =========================================================
// VERIFICAÇÃO DE AUTENTICAÇÃO
// Verifica se o usuário está logado. Se não estiver, vai para a página de login.
// =========================================================
if (!isset($_SESSION["usuario_id"])) {
    // Redireciona para o login caso não esteja autenticada.
    header("Location: login.php");
    // Interrompe a execução do script.
    exit();
}

// =========================================================
// VERIFICAÇÃO DE PERMISSÃO
// Garante que apenas contas do tipo 'profissional' acessem esta página.
// Se for cliente, é enviado para o painel correspondente.
// =========================================================
if ($_SESSION["usuario_tipo"] !== "profissional") {
    // Redireciona clientes para o seu respectivo painel.
    header("Location: painel_cliente.php");
    // Interrompe a execução do script.
    exit();
}

// Armazena o ID da profissional logada para garantir que só ela veja seus próprios serviços.
$id_profissional = $_SESSION["usuario_id"];

// =========================================================
// CONSULTA NO BANCO DE DADOS
// Busca apenas os serviços cadastrados pela profissional que está logada,
// ordenados primeiro por status e depois por nome em ordem alfabética.
// =========================================================
$sql_servicos = "SELECT id, nome, descricao, preco, tempo_estimado, status 
                 FROM servicos 
                 WHERE usuario_id = :usuario_id 
                 ORDER BY status ASC, nome ASC";

// Prepara a instrução no PDO para segurança contra injeção de código malicioso.
$stmt_servicos = $conexao->prepare($sql_servicos);

// Associa o parâmetro da consulta ao ID da profissional da sessão.
$stmt_servicos->bindValue(":usuario_id", $id_profissional);

// Executa a busca no banco de dados.
$stmt_servicos->execute();

// Retorna todos os serviços encontrados em formato de lista para exibição.
$lista_servicos = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Declaração do tipo de documento HTML5 -->
<!DOCTYPE html>
<!-- Define o idioma principal do documento como Português do Brasil. -->
<html lang="pt-BR">
<head>
    <!-- Define a codificação de caracteres permitindo acentuação e símbolos corretos. -->
    <meta charset="UTF-8">
    <!-- Configura a responsividade para se adaptar a diferentes tamanhos de tela. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Título exibido na aba do navegador. -->
    <title>Gerenciar Serviços - Painel Profissional</title>

    <!-- =========================================================
         ESTILOS INTERNOS DA TABELA
         Define aparência, bordas e espaçamentos da tabela de serviços.
         ========================================================= -->
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
    </style>

    <!-- Repetição de configurações de codificação e título -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Serviços - Painel Profissional</title>
</head>
<body>
    <!-- =========================================================
         CABEÇALHO DA PÁGINA
         Título identificando a página e nome da profissional logada.
         ========================================================= -->
    <header>
        <!-- Título da página com nome da profissional logada exibido de forma segura. -->
        <h1>Gestão de Procedimentos - <?php echo htmlspecialchars($_SESSION["usuario_nome"] ?? "Profissional"); ?></h1>
        <!-- Link para voltar ao painel principal da profissional. -->
        <a href="painel_profissional.php">Voltar ao Painel</a>
    </header>
    <!-- Linha separadora visual. -->
    <hr>

    <!-- Conteúdo principal da página -->
    <main>
        <!-- =========================================================
             FORMULÁRIO DE CADASTRO
             Área onde a profissional preenche os dados para criar um novo serviço.
             ========================================================= -->
        <section>
            <h2>Cadastrar Novo Procedimento</h2>
            <!-- Envia os dados preenchidos para o arquivo salvar_servico.php via método POST. -->
            <form action="salvar_servico.php" method="POST">

                <!-- Campo para digitar o nome do serviço oferecido -->
                <div>
                    <label for="nome">Nome do Serviço:</label><br>
                    <input type="text" id="nome" name="nome" placeholder="Ex: Extensão de Cílios Volume Russo" required>
                </div>
                <br>

                <!-- Campo para descrever detalhes, cuidados ou observações do serviço -->
                <div>
                    <label for="descricao">Descrição / Detalhes:</label><br>
                    <textarea id="descricao" name="descricao" placeholder="Informe cuidados prévios ou detalhes do procedimento..." rows="3"></textarea>
                </div>
                <br>

                <!-- Campo para informar o valor do serviço em Reais -->
                <div>
                    <label for="preco">Preço Total (R$):</label><br>
                    <input type="number" id="preco" name="preco" step="0.01" placeholder="Ex: 150.00" required>
                </div>
                <br>

                <!-- Campo para informar quanto tempo dura o atendimento em minutos -->
                <div>
                    <label for="tempo_estimado">Duração Estimada (em minutos):</label><br>
                    <input type="number" id="tempo_estimado" name="tempo_estimado" placeholder="Ex: 120" required>
                </div>
                <br>

                <!-- Botão que envia os dados para cadastrar o serviço -->
                <button type="submit">Salvar Procedimento</button>
            </form>
        </section>
        <hr>

        <!-- =========================================================
             LISTA DE SERVIÇOS CADASTRADOS
             Tabela exibindo todos os serviços da profissional com opções de editar e desativar.
             ========================================================= -->
        <section>
            <h2>Meus Procedimentos Cadastrados</h2>

            <!-- Verifica se há serviços cadastrados para exibir -->
            <?php if (isset($lista_servicos) && count($lista_servicos) > 0): ?>
                <!-- Início da tabela com os dados -->
                <table>
                    <thead>
                        <!-- Cabeçalho com os nomes das colunas -->
                        <tr>
                            <th>ID</th>
                            <th>Procedimento</th>
                            <th>Preço Total</th>
                            <th>Sinal (20%)</th>
                            <th>Duração</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Estrutura de repetição que percorre cada serviço encontrado -->
                        <?php foreach ($lista_servicos as $servico): ?>

                            <!-- Calcula o valor correspondente a 20% do preço total -->
                            <?php 
                                $preco = (float)($servico["preco"] ?? 0);
                                $valor_sinal = $preco * 0.20; 
                            ?>

                            <!-- Linha com os dados de um serviço -->
                            <tr>
                                <!-- Número de identificação no sistema -->
                                <td><?php echo htmlspecialchars($servico["id"]); ?></td>
                                <!-- Nome e descrição do serviço -->
                                <td>
                                    <strong><?php echo htmlspecialchars($servico["nome"]); ?></strong><br>
                                    <small><?php echo htmlspecialchars($servico["descricao"] ?? ''); ?></small>
                                </td>
                                <!-- Valor total formatado em Reais -->
                                <td>R$ <?php echo number_format($preco, 2, ',', '.'); ?></td>
                                <!-- Valor calculado de 20% -->
                                <td>R$ <?php echo number_format($valor_sinal, 2, ',', '.'); ?></td>
                                <!-- Tempo de duração em minutos -->
                                <td><?php echo htmlspecialchars($servico["tempo_estimado"]); ?> min</td>
                                <!-- Situação do serviço (ativo/inativo) -->
                                <td><?php echo htmlspecialchars($servico["status"]); ?></td>
                                <!-- Links para alterar ou desativar o serviço -->
                                <td>
                                    <a href="editar_servico.php?id=<?php echo $servico['id']; ?>">Editar</a>
                                    |
                                    <a href="excluir_servico.php?id=<?php echo $servico['id']; ?>" onclick="return confirm('Deseja realmente desativar este serviço?');">Desativar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <!-- Caso não haja serviços cadastrados, mostra mensagem informativa -->
            <?php else: ?>
                <p>Você ainda não possui nenhum procedimento cadastrado.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>