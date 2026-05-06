<?php
require_once __DIR__ . '/config/helpers.php';
$userId = requireAuth();

$salaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = getDB();

// ---------- Verify user is the organizer or admin ----------
$stmt = $pdo->prepare("
    SELECT s.*, u.nome_completo AS organizador_nome
    FROM salas_eleitorais s
    JOIN users u ON s.organizador_id = u.id
    WHERE s.id = :id
");
$stmt->execute(['id' => $salaId]);
$sala = $stmt->fetch();

if (!$sala) {
    setFlash('error', 'Sala não encontrada.');
    redirect('minhas_salas.php');
}

if ((int)$sala['organizador_id'] !== $userId && $_SESSION['user_role'] !== 'admin') {
    setFlash('error', 'Sem permissão para gerir esta sala.');
    redirect('minhas_salas.php');
}

// ---------- Fetch themes ----------
$temasStmt = $pdo->prepare("
    SELECT t.*, COUNT(c.id) AS num_candidatos
    FROM temas t
    LEFT JOIN candidatos c ON c.tema_id = t.id
    WHERE t.sala_id = :sala_id
    GROUP BY t.id
    ORDER BY t.ordem ASC, t.titulo ASC
");
$temasStmt->execute(['sala_id' => $salaId]);
$temas = $temasStmt->fetchAll();

// ---------- General voting stats ----------
$totalVotosStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_votos,
           COUNT(DISTINCT user_id) AS total_votantes,
           COUNT(DISTINCT tema_id) AS temas_com_votos
    FROM votos WHERE sala_id = :sala_id
");
$totalVotosStmt->execute(['sala_id' => $salaId]);
$stats = $totalVotosStmt->fetch();

// ---------- Process POST actions ----------
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errorMsg = 'Token de seguranca invalido. Por favor, tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';

        // -- Change election status --
        if ($acao === 'alterar_status') {
            $novoStatus = $_POST['novo_status'] ?? '';
            $statusValidos = ['rascunho', 'campanha', 'ativa', 'pausada', 'finalizada'];
            if (in_array($novoStatus, $statusValidos, true)) {
                $upd = $pdo->prepare("UPDATE salas_eleitorais SET estado = :status, atualizado_em = NOW() WHERE id = :id");
                $upd->execute(['status' => $novoStatus, 'id' => $salaId]);
                $successMsg = "Status da sala alterado para '{$novoStatus}' com sucesso!";
            } else {
                $errorMsg = 'Status invalido. Valores aceitos: ' . implode(', ', $statusValidos);
            }
        }

        // -- Add new theme --
        elseif ($acao === 'adicionar_tema') {
            $nomeTema = trim($_POST['nome_tema'] ?? '');
            $descricaoTema = trim($_POST['descricao_tema'] ?? '');
            if ($nomeTema === '') {
                $errorMsg = 'O nome do tema e obrigatoria.';
            } else {
                // Get next ordem
                $ordemStmt = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 AS prox FROM temas WHERE sala_id = :sid");
                $ordemStmt->execute(['sid' => $salaId]);
                $proxOrdem = (int)$ordemStmt->fetchColumn();

                $ins = $pdo->prepare("INSERT INTO temas (sala_id, titulo, descricao, ordem) VALUES (:sid, :titulo, :desc, :ordem)");
                $ins->execute(['sid' => $salaId, 'titulo' => $nomeTema, 'desc' => $descricaoTema, 'ordem' => $proxOrdem]);
                $successMsg = "Tema '{$nomeTema}' adicionado com sucesso!";
            }
        }

        // -- Add candidate to theme --
        elseif ($acao === 'adicionar_candidato') {
            $temaId     = (int)($_POST['tema_id'] ?? 0);
            $nomeCand   = trim($_POST['nome_candidato'] ?? '');
            $propostaCand = trim($_POST['proposta_candidato'] ?? '');
            $fotoCand   = trim($_POST['foto_candidato'] ?? '');

            if ($temaId <= 0) {
                $errorMsg = 'Selecione um tema valido.';
            } elseif ($nomeCand === '') {
                $errorMsg = 'O nome do candidato e obrigatoria.';
            } else {
                // Verify theme belongs to this sala
                $checkTema = $pdo->prepare("SELECT id FROM temas WHERE id = :id AND sala_id = :sid");
                $checkTema->execute(['id' => $temaId, 'sid' => $salaId]);
                if (!$checkTema->fetch()) {
                    $errorMsg = 'Tema nao encontrado nesta sala.';
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO candidatos (tema_id, nome, proposta, foto)
                        VALUES (:tema_id, :nome, :proposta, :foto)
                    ");
                    $ins->execute([
                        'tema_id' => $temaId,
                        'nome'    => $nomeCand,
                        'proposta' => $propostaCand,
                        'foto'    => $fotoCand
                    ]);
                    $successMsg = "Candidato '{$nomeCand}' adicionado com sucesso!";
                }
            }
        }
    }
}

// Refresh sala data after potential status update
$stmt->execute(['id' => $salaId]);
$sala = $stmt->fetch();

// ---------- Page Title ----------
$pageTitle = 'Gerir Sala - ' . htmlspecialchars($sala['nome']);

// Generate CSRF token for the forms below
generateCSRFToken();

// ---------- Includes ----------
require_once __DIR__ . '/includes/header.php';
?>

    <div class="ve-card" style="background: linear-gradient(135deg, var(--blue-deeper) 0%, var(--blue-dark) 100%); color: white; margin-bottom: 2rem; border: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <nav class="breadcrumb" style="margin-bottom: 0.5rem; opacity: 0.8;">
                    <a href="minhas_salas.php" style="color: white; text-decoration: none;">&lsaquo; Minhas Salas</a>
                </nav>
                <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: white;"><?= htmlspecialchars($sala['nome']) ?></h2>
                <p style="opacity: 0.8; font-size: 1.1rem;">Painel de Gestão e Monitorização Eleitoral em Tempo Real.</p>
            </div>
            <div class="header-actions">
                <span class="badge" style="padding: 0.75rem 1.5rem; font-size: 1.1rem; border-radius: 999px; font-weight: 800; border: 2px solid rgba(255,255,255,0.3); background: rgba(0,0,0,0.2); color: white;">
                    <?= ucfirst(htmlspecialchars($sala['estado'])) ?>
                </span>
            </div>
        </div>
    </div>

<div class="content-wrapper">

    <!-- Flash Messages -->
    <?php if ($successMsg): ?>
        <div class="alert alert-success auto-dismiss" role="alert">
            <?= htmlspecialchars($successMsg) ?>
            <button class="alert-close" aria-label="Fechar">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-error auto-dismiss" role="alert">
            <?= htmlspecialchars($errorMsg) ?>
            <button class="alert-close" aria-label="Fechar">&times;</button>
        </div>
    <?php endif; ?>

    <div class="grid-2col">

        <!-- ============================== -->
        <!-- CARD 1: Status da Eleicao      -->
        <!-- ============================== -->
        <section class="ve-card" style="padding: 2rem;">
            <div style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-header);">⚙️ Controlo de Estado</h2>
            </div>
            <div class="card-body">
                <p class="card-description" style="color: var(--text-muted); margin-bottom: 1.5rem;">
                    Altere a visibilidade e o estado operacional desta sala.
                </p>

                <form method="POST" action="gerir_sala.php?id=<?= $salaId ?>" class="form-group">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="acao" value="alterar_status">

                    <div style="display: flex; gap: 0.5rem;">
                        <select name="novo_status" id="novo_status" required style="flex-grow: 1; padding: 0.75rem 1rem; border-radius: 8px; background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color);">
                            <option value="">-- Selecionar Status --</option>
                            <option value="rascunho"   <?= $sala['estado'] === 'rascunho' ? 'selected' : '' ?>>📝 Rascunho</option>
                            <option value="campanha"   <?= $sala['estado'] === 'campanha' ? 'selected' : '' ?>>📢 Campanha Eleitoral</option>
                            <option value="ativa"      <?= $sala['estado'] === 'ativa' ? 'selected' : '' ?>>🟢 Ativa (Votação)</option>
                            <option value="pausada"    <?= $sala['estado'] === 'pausada' ? 'selected' : '' ?>>⏸️ Pausada</option>
                            <option value="finalizada" <?= $sala['estado'] === 'finalizada' ? 'selected' : '' ?>>🏁 Finalizada</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">Atualizar</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- ============================== -->
        <!-- CARD 2: Estatisticas de Votacao-->
        <!-- ============================== -->
        <section class="stat-box" style="padding: 2rem; display: block;">
            <div style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-header);">📊 Estatísticas de Votação</h2>
            </div>
            <div class="stats-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="ve-card" style="padding: 1rem; text-align: center;">
                    <span style="font-size: 1.5rem; font-weight: 800; color: var(--blue);" id="totalVotos"><?= (int)$stats['total_votos'] ?></span>
                    <br><span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Votos</span>
                </div>
                <div class="ve-card" style="padding: 1rem; text-align: center;">
                    <span style="font-size: 1.5rem; font-weight: 800; color: var(--green);" id="totalVotantes"><?= (int)$stats['total_votantes'] ?></span>
                    <br><span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Votantes</span>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="api/results.php?action=export&sala_id=<?= $salaId ?>" class="btn btn-ghost" target="_blank" style="font-size: 0.85rem; font-weight: 700;">
                    <i class="fa fa-download"></i> CSV
                </a>
                <button class="btn btn-ghost" onclick="updateVoteStats(<?= $salaId ?>)" style="font-size: 0.85rem; font-weight: 700;">
                    <i class="fa fa-refresh"></i> Atualizar
                </button>
            </div>
        </section>

        <div class="ve-grid">
            <!-- Adicionar Tema -->
            <div class="ve-card">
                <div class="card-header">
                    <h3><i class="fa fa-folder-plus"></i> Adicionar Novo Tema</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="gerir_sala.php?id=<?= $salaId ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="acao" value="adicionar_tema">
                        <div class="form-group">
                            <label>Nome do Tema *</label>
                            <input type="text" name="nome_tema" placeholder="Ex: Eleição Presidencial 2026" required>
                        </div>
                        <div class="form-group">
                            <label>Descrição (opcional)</label>
                            <textarea name="descricao_tema" rows="2" placeholder="Breve descrição do tema..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">
                            <i class="fa fa-plus-circle"></i> Adicionar Tema
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================== -->
    <!-- Lista de Temas e Candidatos    -->
    <!-- ============================== -->
    <section class="card card-full mt-2">
        <div class="card-header">
            <h2>&#128193; Temas e Candidatos da Sala</h2>
        </div>
        <div class="card-body">
            <?php if (count($temas) > 0): ?>
                <div class="temas-list">
                    <?php foreach ($temas as $tema): ?>
                        <div class="tema-item">
                            <div class="tema-header">
                                <h3><?= htmlspecialchars($tema['titulo']) ?></h3>
                                <span class="badge badge-secondary"><?= (int)$tema['num_candidatos'] ?> candidatos</span>
                            </div>
                            <?php if (!empty($tema['descricao'])): ?>
                                <p class="tema-desc"><?= htmlspecialchars($tema['descricao']) ?></p>
                            <?php endif; ?>

                            <?php
                            // Fetch candidates for this theme
                            $candsStmt = $pdo->prepare("
                                SELECT c.*, c.votos_totais
                                FROM candidatos c
                                WHERE c.tema_id = :tema_id
                                ORDER BY c.votos_totais DESC, c.nome ASC
                            ");
                            $candsStmt->execute(['tema_id' => (int)$tema['id']]);
                            $candidatos = $candsStmt->fetchAll();

                            // Total votes for this theme
                            $themeTotal = array_sum(array_column($candidatos, 'votos_totais'));
                            ?>

                            <?php if (count($candidatos) === 0): ?>
                                <p class="empty-state">Nenhum candidato neste tema.</p>
                            <?php else: ?>
                                <div class="candidatos-bar-chart">
                                    <?php foreach ($candidatos as $cand):
                                        $pct = $themeTotal > 0 ? round(((int)$cand['total_votes'] / $themeTotal) * 100, 1) : 0;
                                    ?>
                                        <div class="cand-bar-row">
                                            <span class="cand-name"><?= htmlspecialchars($cand['nome']) ?></span>
                                            <div class="cand-bar-track">
                                                <div class="cand-bar-fill"
                                                     style="width: <?= $pct ?>%;"
                                                     data-votes="<?= (int)$cand['votos_totais'] ?>">
                                                </div>
                                            </div>
                                            <span class="cand-votes">
                                                <?= (int)$cand['votos_totais'] ?> votos (<?= $pct ?>%)
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="total-votes-label">Total de votos neste tema: <strong><?= $themeTotal ?></strong></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-box">
                    <p>Nenhum tema criado. Use o formulario acima para adicionar o primeiro tema.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================== -->
    <!-- Sala Info                      -->
    <!-- ============================== -->
    <section class="ve-card card-full mt-2">
        <div class="card-header">
            <h3>&#8505; Informações da Sala</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Organizador:</span>
                    <span class="info-value"><?= htmlspecialchars($sala['organizador_nome']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="badge badge-<?= htmlspecialchars($sala['estado']) ?>">
                            <?= ucfirst(htmlspecialchars($sala['estado'])) ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Criada em:</span>
                    <span class="info-value"><?= formatDate($sala['criado_em']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Link de acesso:</span>
                    <div class="copy-input-group">
                        <code>localhost:8080/votar.php?sala=<?= $salaId ?></code>
                        <button onclick="copiarTexto('localhost:8080/votar.php?sala=<?= $salaId ?>')" class="btn-icon"><i class="fa fa-copy"></i></button>
                    </div>
                </div>
                <div class="info-item">
                    <span class="info-label">Código único:</span>
                    <div class="copy-input-group">
                        <code><?= htmlspecialchars($sala['codigo_acesso'] ?? '-') ?></code>
                        <button onclick="copiarTexto('<?= htmlspecialchars($sala['codigo_acesso'] ?? '') ?>')" class="btn-icon"><i class="fa fa-copy"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>

<script>
/**
 * Inline JS specific to gerir_sala.php
 */

// ---- Copy link ----
function copiarLink() {
    const link = document.getElementById('salaLink').textContent.trim();
    navigator.clipboard.writeText(window.location.origin + '/' + link).then(() => {
        showToast('Link copiado para a area de transferencia!', 'success');
    }).catch(() => {
        showToast('Erro ao copiar o link.', 'error');
    });
}

// ---- Copy arbitrary text ----
function copiarTexto(texto) {
    navigator.clipboard.writeText(texto).then(() => {
        showToast('Codigo copiado!', 'success');
    }).catch(() => {
        showToast('Erro ao copiar.', 'error');
    });
}

// ---- Live vote stats polling ----
function updateVoteStats(salaId) {
    fetch('api/results.php?action=stats&sala_id=' + salaId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalVotos').textContent = data.total_votos;
                document.getElementById('totalVotantes').textContent = data.total_votantes;
                document.getElementById('totalTemas').textContent = data.temas_com_votos;
                showToast('Estatisticas atualizadas!', 'info');
            } else {
                showToast(data.message || 'Erro ao atualizar estatisticas.', 'error');
            }
        })
        .catch(err => {
            console.error('Erro ao buscar estatisticas:', err);
            showToast('Erro de conexao ao servidor.', 'error');
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
