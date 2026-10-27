<?php
// ============================================================
// INICIAR SESSÃO, CONEXÃO E PROTEÇÃO DA PÁGINA
// ============================================================
session_start();
require_once("conexao.php");

if (!isset($_SESSION["usuario_id"]) || ($_SESSION["usuario_tipo"] ?? "") !== "cliente") {
    header("Location: login.php");
    exit();
}

$nome_cliente = $_SESSION["usuario_nome"] ?? "Cliente";

$categoria = $_GET["categoria"] ?? "nail";
$categorias = [
    "nail" => "Unhas",
    "lash" => "Cílios",
    "eyebrow" => "Sobrancelhas"
];

if (!array_key_exists($categoria, $categorias)) {
    $categoria = "nail";
}

$servicos_categoria = [];
$media_preco = 0;
$quantidade_servicos = 0;
$erro_servicos = false;

try {
    $sql = "SELECT
        s.id,
        s.nome_servico,
        s.tipo_servico,
        s.descricao,
        s.preco,
        s.duracao,
        u.id AS profissional_id,
        u.nome AS profissional_nome,
        u.especialidade AS profissional_especialidade
    FROM servicos s
    INNER JOIN vinculacao v ON v.servico_id = s.id
    INNER JOIN usuarios u ON u.id = v.profissional_id
    WHERE s.status = 'ativo'
      AND u.tipo_usuario = 'profissional'
      AND u.status = 'ativo'
      AND u.especialidade = :categoria
    ORDER BY s.tipo_servico ASC, s.nome_servico ASC";

    $stmt = $conexao->prepare($sql);
    $stmt->bindValue(":categoria", $categoria, PDO::PARAM_STR);
    $stmt->execute();
    $servicos_categoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($servicos_categoria) > 0) {
        $precos = array_map(
            static fn($servico) => (float)$servico["preco"],
            $servicos_categoria
        );
        $media_preco = array_sum($precos) / count($precos);
        $quantidade_servicos = count($servicos_categoria);
    }
} catch (PDOException $erro) {
    error_log("Erro ao carregar catálogo de serviços: " . $erro->getMessage());
    $erro_servicos = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serviços | TCC Estética</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-cliente pagina-servicos">
    <header class="cabecalho-cliente">
        <div class="logo-cliente"><h1>Estética</h1></div>
        <nav class="menu-cliente">
            <a href="painel_cliente.php">Início</a>
            <a href="servicos.php" class="ativo">Serviços</a>
            <a href="meus_agendamentos.php">Meus Agendamentos</a>
            <a href="perfil_cliente.php">Meu Perfil</a>
            <a href="logout.php" class="menu-sair">Sair</a>
        </nav>
    </header>

    <main class="layout-servicos">
        <aside class="menu-categorias">
            <h2>Tipos de serviço</h2>
            <nav aria-label="Categorias de serviços">
                <a href="servicos.php?categoria=nail" class="<?= $categoria === 'nail' ? 'ativo' : '' ?>">Unhas</a>
                <a href="servicos.php?categoria=lash" class="<?= $categoria === 'lash' ? 'ativo' : '' ?>">Cílios</a>
                <a href="servicos.php?categoria=eyebrow" class="<?= $categoria === 'eyebrow' ? 'ativo' : '' ?>">Sobrancelhas</a>
            </nav>
        </aside>

        <section class="conteudo-servicos">
            <div class="intro-servicos">
                <span class="etiqueta">CATÁLOGO DE PROCEDIMENTOS</span>
                <h2>Serviços de <?= htmlspecialchars($categorias[$categoria], ENT_QUOTES, 'UTF-8') ?></h2>
                <p>Conheça os procedimentos, confira a duração e consulte a faixa de valores antes de escolher um horário.</p>
            </div>

            <?php if (!$erro_servicos && $quantidade_servicos > 0): ?>
                <div class="resumo-categoria">
                    <div><span>Procedimentos disponíveis</span><strong><?= $quantidade_servicos ?></strong></div>
                    <div><span>Preço médio da categoria</span><strong>R$ <?= number_format($media_preco, 2, ',', '.') ?></strong></div>
                </div>
            <?php endif; ?>

            <?php if ($erro_servicos): ?>
                <div class="estado-vazio"><h3>Não foi possível carregar os serviços</h3><p>Tente acessar esta página novamente em alguns instantes.</p></div>
            <?php elseif (count($servicos_categoria) > 0): ?>
                <div class="grade-servicos-nova">
                    <?php foreach ($servicos_categoria as $servico): ?>
                        <article class="cartao-servico-novo">
                            <span class="tipo"><?= htmlspecialchars(ucfirst($servico["tipo_servico"] ?? "Procedimento"), ENT_QUOTES, 'UTF-8') ?></span>
                            <h3><?= htmlspecialchars($servico["nome_servico"], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars($servico["descricao"] ?: "Procedimento realizado com cuidado e atenção.", ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="detalhes-servico">
                                <div><span>Duração</span><strong><?= htmlspecialchars($servico["duracao"] ?: "A confirmar", ENT_QUOTES, 'UTF-8') ?></strong></div>
                                <div><span>Preço do serviço</span><strong>R$ <?= number_format((float)$servico["preco"], 2, ',', '.') ?></strong></div>
                            </div>
                            <div class="profissional-servico"><strong>Profissional:</strong> <?= htmlspecialchars($servico["profissional_nome"], ENT_QUOTES, 'UTF-8') ?></div>
                            
                            <!-- LINK CORRIGIDO AQUI -->
                            <a class="botao-agenda" href="agenda.php?servico_id=<?= (int)$servico["id"] ?>&profissional_id=<?= (int)$servico["profissional_id"] ?>">Ver agenda</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="estado-vazio"><h3>Nenhum procedimento disponível</h3><p>Ainda não há serviços ativos cadastrados para esta categoria.</p></div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="rodape-servicos"><p>TCC Estética — Área da Cliente</p></footer>
</body>
</html>