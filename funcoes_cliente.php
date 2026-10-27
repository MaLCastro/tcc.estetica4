<?php
// Buscar todos os serviços com dados da profissional
function listarTodosServicos($pdo) {
    $sql = "SELECT s.*, u.nome as profissional_nome, u.especialidade, u.foto 
            FROM servicos s
            INNER JOIN usuarios u ON s.profissional_id = u.id
            WHERE s.status = 1 AND u.status = 'ativo'
            ORDER BY u.especialidade, s.nome_servico";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Filtrar serviços por categoria (lash / nail / sobrancelha)
function listarPorCategoria($pdo, $categoria) {
    $sql = "SELECT s.*, u.nome as profissional_nome, u.foto, u.id as profissional_id
            FROM servicos s
            INNER JOIN usuarios u ON s.profissional_id = u.id
            WHERE s.status = 1 AND u.status = 'ativo' AND u.especialidade = ?
            ORDER BY s.nome_servico";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoria]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar horários disponíveis de uma profissional
function horariosDisponiveis($pdo, $profissional_id, $data) {
    $sql = "SELECT h.dia_semana, h.horario_inicio, h.ultimo_horario,
                   d.horario, d.ativo
            FROM horarios h
            LEFT JOIN disponibilidade d 
              ON h.dia_semana = DAYOFWEEK(?) - 1 AND h.profissional_id = ?
            WHERE h.profissional_id = ? AND h.trabalha = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data, $profissional_id, $profissional_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Verificar horários JÁ agendados (para não mostrar ocupado)
function horariosOcupados($pdo, $profissional_id, $data) {
    $sql = "SELECT horario FROM agendamentos
            WHERE profissional_id = ? AND data_agendamento = ? AND status != 'cancelado'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$profissional_id, $data]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'horario');
}