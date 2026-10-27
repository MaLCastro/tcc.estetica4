
<?php
// ============================================================
// 1. INICIAR SESSÃO E VALIDAR ACESSO
// ============================================================

session_start(); // Inicia a sessão da profissional.
require_once("conexao.php"); // Carrega a conexão com o banco.

// Permite acesso somente às profissionais autenticadas.
if (
    !isset($_SESSION["usuario_id"]) ||
    ($_SESSION["usuario_tipo"] ?? "") !== "profissional"
) {
    header("Location: login.php");
    exit();
}

// Guarda o ID da profissional autenticada.
$profissional_id = (int) $_SESSION["usuario_id"];

// Inicializa as mensagens e o estado da página.
$mensagem_sucesso = "";
$mensagem_erro = "";
$erroConsulta = false;
$modoEdicao = ($_GET["editar"] ?? "") === "1";

// Inicializa os dados do formulário.
$chave_pix_formulario = "";


// ============================================================
// 2. FUNÇÃO PARA EXIBIR TEXTO COM SEGURANÇA
// ============================================================

// Evita que os dados cadastrados sejam interpretados como HTML.
function escapar($valor)
{
    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


// ============================================================
// 3. CARREGAR DADOS DO PERFIL
// ============================================================

try {
    // Busca os dados pessoais e a chave Pix da profissional.
    $consulta = $conexao->prepare("
        SELECT id, nome, email, telefone, especialidade, chave_pix
        FROM usuarios
        WHERE id = :id
        LIMIT 1
    ");

    $consulta->execute([":id" => $profissional_id]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    // Interrompe o carregamento se a conta não existir.
    if (!$usuario) {
        throw new Exception("Perfil não encontrado.");
    }

    // Mantém a chave Pix atual no formulário de edição.
    $chave_pix_formulario = $usuario["chave_pix"] ?? "";

} catch (Exception $e) {
    // Registra o erro técnico sem exibi-lo à profissional.
    error_log("Erro ao carregar perfil: " . $e->getMessage());

    $mensagem_erro = "Não foi possível carregar os dados do perfil.";
    $usuario = [];
    $erroConsulta = true;
}


// ============================================================
// 4. SALVAR ALTERAÇÕES PESSOAIS
// ============================================================

// Processa somente o formulário de informações pessoais.
if (
    !$erroConsulta &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["acao"] ?? "") === "salvar"
) {
    // Mantém o formulário aberto se houver algum erro.
    $modoEdicao = true;

    // Recupera os dados enviados pela profissional.
    $nome = trim($_POST["nome"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $especialidade = trim($_POST["especialidade"] ?? "");
    $chave_pix_formulario = trim($_POST["chave_pix"] ?? "");

    // Define os valores permitidos para especialidade.
    $especialidadesPermitidas = ["lash", "nail", "eyebrow"];

    try {
        // Valida os campos obrigatórios.
        if ($nome === "" || $especialidade === "") {
            throw new Exception(
                "Preencha o nome e selecione uma especialidade."
            );
        }

        // Aceita somente as especialidades previstas no sistema.
        if (!in_array($especialidade, $especialidadesPermitidas, true)) {
            throw new Exception("Selecione uma especialidade válida.");
        }

        // Verifica os limites dos campos.
        if (strlen($nome) > 150 || strlen($telefone) > 20) {
            throw new Exception("Verifique o tamanho dos dados informados.");
        }

        if (strlen($chave_pix_formulario) > 255) {
            throw new Exception("A chave Pix deve ter até 255 caracteres.");
        }

        // Atualiza somente os dados permitidos nesta página.
        $atualizacao = $conexao->prepare("
            UPDATE usuarios
            SET
                nome = :nome,
                telefone = :telefone,
                especialidade = :especialidade,
                chave_pix = :chave_pix
            WHERE id = :id
              AND tipo_usuario = 'profissional'
        ");

        $atualizacao->execute([
            ":nome" => $nome,
            ":telefone" => $telefone,
            ":especialidade" => $especialidade,
            ":chave_pix" => $chave_pix_formulario !== ""
                ? $chave_pix_formulario
                : null,
            ":id" => $profissional_id
        ]);

        // Atualiza os dados utilizados durante a sessão.
        $_SESSION["usuario_nome"] = $nome;
        $_SESSION["especialidade"] = $especialidade;

        // Recarrega os dados para exibir o que foi salvo no banco.
        $consulta->execute([":id" => $profissional_id]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        // Atualiza a chave exibida com o valor confirmado no banco.
        $chave_pix_formulario = $usuario["chave_pix"] ?? "";

        // Retorna ao modo de visualização após salvar.
        $modoEdicao = false;
        $mensagem_sucesso = "Informações atualizadas com sucesso!";

    } catch (Exception $e) {
        // Registra detalhes técnicos para facilitar a depuração.
        error_log("Erro ao atualizar perfil: " . $e->getMessage());

        // Exibe mensagens de validação conhecidas.
        $mensagensPermitidas = [
            "Preencha o nome e selecione uma especialidade.",
            "Selecione uma especialidade válida.",
            "Verifique o tamanho dos dados informados.",
            "A chave Pix deve ter até 255 caracteres."
        ];

        if (in_array($e->getMessage(), $mensagensPermitidas, true)) {
            $mensagem_erro = $e->getMessage();
        } else {
            $mensagem_erro = "Não foi possível salvar as alterações.";
        }

        // Mantém os dados digitados para que possam ser corrigidos.
        $usuario["nome"] = $nome;
        $usuario["telefone"] = $telefone;
        $usuario["especialidade"] = $especialidade;
    }
}


// ============================================================
// 5. PREPARAR OS NOMES EXIBIDOS NA TELA
// ============================================================

// Converte os códigos internos em nomes amigáveis.
$nomesEspecialidades = [
    "lash" => "Cílios",
    "nail" => "Unhas",
    "eyebrow" => "Sobrancelhas"
];

$especialidadeExibida = $nomesEspecialidades[
    $usuario["especialidade"] ?? ""
] ?? "Não informada";
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <!-- Adapta o layout para computadores, tablets e celulares. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meu Perfil | Estética</title>

    <!-- Mantém o CSS principal do projeto. -->
    <link rel="stylesheet" href="style.css">

</head>

<body>

<!-- ============================================================
     6. MENU LATERAL
     ============================================================ -->

<aside class="sidebar">

    <div class="logo">
        <h1>Estética</h1>

        <!-- Exibe a especialidade da profissional autenticada. -->
        <p>
            <?= escapar(
                $_SESSION["especialidade"] ?? "Profissional"
            ) ?>
        </p>
    </div>

    <nav class="menu">
        <a href="painel_profissional.php">Início</a>
        <a href="atendimentos.php">Atendimentos</a>
        <a href="minha_agenda.php">Minha Agenda</a>
        <a href="meus_servicos.php">Meus Serviços</a>

        <div class="separador"></div>

        <!-- Identifica a página atual no menu. -->
        <a href="meu_perfil.php" class="ativo">Meu Perfil</a>
    </nav>

    <a href="logout.php" class="btn-sair">Sair</a>

</aside>


<!-- ============================================================
     7. CONTEÚDO PRINCIPAL
     ============================================================ -->

<main class="main">

    <div class="perfil-pagina">

        <!-- Apresenta a página e sua principal ação. -->
        <section class="perfil-cabecalho">
            <div>
                <h2>Meu Perfil</h2>
                <p>Consulte seus dados pessoais e suas informações de pagamento.</p>
            </div>

            <?php if (!$erroConsulta && !$modoEdicao): ?>
                <!-- Abre o formulário somente quando solicitado. -->
                <a href="meu_perfil.php?editar=1" class="perfil-botao">
                    Alterar informações pessoais
                </a>
            <?php endif; ?>
        </section>


        <!-- Mensagem apresentada após salvar ou quando ocorre um erro. -->
        <?php if ($mensagem_sucesso !== ""): ?>
            <div class="alerta alerta-sucesso perfil-alerta">
                <?= escapar($mensagem_sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro !== ""): ?>
            <div class="alerta alerta-erro perfil-alerta">
                <?= escapar($mensagem_erro) ?>
            </div>
        <?php endif; ?>


        <?php if (!$erroConsulta): ?>

            <?php if (!$modoEdicao): ?>

                <!-- ============================================
                     MODO DE VISUALIZAÇÃO
                     ============================================ -->

                <div class="perfil-layout">

                    <!-- Exibe os dados pessoais sem permitir edição direta. -->
                    <section class="perfil-cartao">
                        <h3>Informações pessoais</h3>

                        <div class="perfil-dados">

                            <div class="perfil-dado">
                                <small>Nome completo</small>
                                <p><?= escapar($usuario["nome"]) ?></p>
                            </div>

                            <div class="perfil-dado">
                                <small>E-mail cadastrado</small>
                                <p><?= escapar($usuario["email"]) ?></p>
                            </div>

                            <div class="perfil-dado">
                                <small>Telefone cadastrado</small>
                                <p>
                                    <?= trim($usuario["telefone"] ?? "") !== ""
                                        ? escapar($usuario["telefone"])
                                        : "Não informado" ?>
                                </p>
                            </div>

                            <div class="perfil-dado">
                                <small>Especialidade</small>
                                <p><?= escapar($especialidadeExibida) ?></p>
                            </div>

                        </div>

                        <!-- Direciona a profissional para a edição dos dados. -->
                        <div class="perfil-acoes">
                            <a href="meu_perfil.php?editar=1" class="perfil-botao">
                                Alterar informações pessoais
                            </a>
                        </div>
                    </section>


                    <!-- Apresenta a chave Pix salva no banco. -->
                    <section class="perfil-cartao">
                        <div class="perfil-pix">
                            <h3>Dados para pagamento</h3>

                            <?php if (!empty($usuario["chave_pix"])): ?>

                                <p><strong>Chave Pix cadastrada</strong></p>

                                <p class="pix-valor">
                                    <?= escapar($usuario["chave_pix"]) ?>
                                </p>

                                <small>
                                    Esta é a chave salva atualmente no seu perfil.
                                </small>

                            <?php else: ?>

                                <p><strong>Nenhuma chave Pix cadastrada.</strong></p>

                                <small>
                                    Para cadastrar uma chave, clique em
                                    Alterar informações pessoais.
                                </small>

                            <?php endif; ?>
                        </div>
                    </section>

                </div>

            <?php else: ?>

                <!-- ============================================
                     MODO DE EDIÇÃO
                     ============================================ -->

                <section class="perfil-cartao perfil-formulario">
                    <h3>Alterar informações pessoais</h3>

                    <p style="margin-top:-10px; margin-bottom:25px;">
                        Edite os campos desejados e salve suas alterações.
                        Seu e-mail permanece inalterado.
                    </p>

                    <form method="POST" action="meu_perfil.php?editar=1">

                        <!-- Identifica a ação processada pelo PHP. -->
                        <input type="hidden" name="acao" value="salvar">

                        <div class="form-grid">

                            <div class="form-grupo">
                                <label for="nome">Nome completo *</label>

                                <input
                                    type="text"
                                    id="nome"
                                    name="nome"
                                    maxlength="150"
                                    required
                                    value="<?= escapar($usuario["nome"] ?? "") ?>"
                                >
                            </div>

                            <div class="form-grupo">
                                <label for="email">E-mail cadastrado</label>

                                <input
                                    type="email"
                                    id="email"
                                    value="<?= escapar($usuario["email"] ?? "") ?>"
                                    readonly
                                    style="background:var(--cor-clara, #F8F0F1); opacity:0.85;"
                                >

                                <small>O e-mail não pode ser alterado nesta versão.</small>
                            </div>

                            <div class="form-grupo">
                                <label for="telefone">Telefone</label>

                                <input
                                    type="tel"
                                    id="telefone"
                                    name="telefone"
                                    maxlength="20"
                                    value="<?= escapar($usuario["telefone"] ?? "") ?>"
                                    placeholder="(00) 00000-0000"
                                >
                            </div>

                            <div class="form-grupo">
                                <label for="especialidade">Especialidade *</label>

                                <select
                                    id="especialidade"
                                    name="especialidade"
                                    required
                                >
                                    <option value="">Selecione...</option>

                                    <option
                                        value="lash"
                                        <?= ($usuario["especialidade"] ?? "") === "lash" ? "selected" : "" ?>
                                    >
                                        Cílios
                                    </option>

                                    <option
                                        value="nail"
                                        <?= ($usuario["especialidade"] ?? "") === "nail" ? "selected" : "" ?>
                                    >
                                        Unhas
                                    </option>

                                    <option
                                        value="eyebrow"
                                        <?= ($usuario["especialidade"] ?? "") === "eyebrow" ? "selected" : "" ?>
                                    >
                                        Sobrancelhas
                                    </option>
                                </select>
                            </div>

                            <div class="form-grupo linha-completa">
                                <hr class="perfil-separador">

                                <h3 style="margin:18px 0 8px;">
                                    Dados para pagamento
                                </h3>

                                <label for="chave_pix">Chave Pix</label>

                                <!-- Campo vazio remove a chave ao salvar. -->
                                <input
                                    type="text"
                                    id="chave_pix"
                                    name="chave_pix"
                                    maxlength="255"
                                    autocomplete="off"
                                    value="<?= escapar($chave_pix_formulario) ?>"
                                    placeholder="CPF, e-mail, telefone ou chave aleatória"
                                >

                                <small>
                                    Para remover a chave cadastrada, deixe o campo
                                    vazio e salve as alterações.
                                </small>
                            </div>

                        </div>

                        <!-- Oferece as ações de salvar ou cancelar. -->
                        <div class="perfil-acoes">

                            <button
                                type="submit"
                                class="perfil-botao"
                            >
                                Salvar alterações
                            </button>

                            <a
                                href="meu_perfil.php"
                                class="perfil-botao perfil-botao-secundario"
                            >
                                Cancelar
                            </a>

                        </div>

                    </form>
                </section>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</main>

</body>
</html>
