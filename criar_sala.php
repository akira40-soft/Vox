<?php
/**
 * criar_sala.php - Vox Electoral Wizard
 * A multi-step process to create high-depth electoral rooms.
 */
require_once 'config/helpers.php';
$userId = requireAuth();

$step = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors = [];

// Initialize session data if not exists
if (!isset($_SESSION['wizard_data']) || isset($_GET['reset'])) {
    $_SESSION['wizard_data'] = [
        'nome'                  => '',
        'descricao'             => '',
        'tipo'                  => 'nacional',
        'visibilidade'          => 'privada',
        'provincia'             => '',
        'data_inicio'           => '',
        'data_fim'              => '',
        'voto_anonimo'          => 1,
        'permitir_campanha'     => 1,
        'data_campanha_inicio'  => '',
        'data_campanha_fim'     => '',
        'data_votacao_inicio'   => '',
        'data_votacao_fim'      => '',
        'temas'                 => []
    ];
}

$provincias = $pdo->query("SELECT id, nome FROM provincias ORDER BY nome")->fetchAll();

// --- STEP PROCESSING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save current step data to session
    if ($step === 1) {
        $_SESSION['wizard_data']['nome']                 = sanitize($_POST['nome'] ?? '');
        $_SESSION['wizard_data']['descricao']             = sanitize($_POST['descricao'] ?? '');
        $_SESSION['wizard_data']['tipo']                  = sanitize($_POST['tipo'] ?? 'nacional');
        $_SESSION['wizard_data']['visibilidade']          = sanitize($_POST['visibilidade'] ?? 'privada');
        $_SESSION['wizard_data']['provincia']             = (int)($_POST['provincia'] ?? 0);
        $_SESSION['wizard_data']['data_inicio']           = sanitize($_POST['data_inicio'] ?? '');
        $_SESSION['wizard_data']['data_fim']              = sanitize($_POST['data_fim'] ?? '');
        $_SESSION['wizard_data']['voto_anonimo']          = isset($_POST['voto_anonimo']) ? 1 : 0;
        $_SESSION['wizard_data']['permitir_campanha']     = isset($_POST['permitir_campanha']) ? 1 : 0;
        $_SESSION['wizard_data']['data_campanha_inicio']  = sanitize($_POST['data_campanha_inicio'] ?? '');
        $_SESSION['wizard_data']['data_campanha_fim']     = sanitize($_POST['data_campanha_fim'] ?? '');
        $_SESSION['wizard_data']['data_votacao_inicio']   = sanitize($_POST['data_votacao_inicio'] ?? '');
        $_SESSION['wizard_data']['data_votacao_fim']      = sanitize($_POST['data_votacao_fim'] ?? '');

        if (empty($_SESSION['wizard_data']['nome']))               $errors[] = 'O nome da sala é obrigatório.';
        if (empty($_SESSION['wizard_data']['data_votacao_inicio'])) $errors[] = 'Defina quando a votação começa.';
        if (empty($_SESSION['wizard_data']['data_votacao_fim']))    $errors[] = 'Defina quando a votação termina.';
        if ($_SESSION['wizard_data']['permitir_campanha']) {
            if (empty($_SESSION['wizard_data']['data_campanha_inicio'])) $errors[] = 'Defina quando a campanha começa.';
            if (empty($_SESSION['wizard_data']['data_campanha_fim']))    $errors[] = 'Defina quando a campanha termina.';
        }

        if (empty($errors)) $step = 2;
    } 
    elseif ($step === 2) {
        // Collect Themes & Candidates
        $temas = [];
        if (!empty($_POST['temas'])) {
            foreach ($_POST['temas'] as $i => $tema) {
                $tTitulo = sanitize($tema['titulo'] ?? '');
                if (!empty($tTitulo)) {
                    $candidatos = [];
                    if (!empty($_POST['candidatos'][$i])) {
                        foreach ($_POST['candidatos'][$i] as $c) {
                            if (!empty($c['nome'])) {
                                $candidatos[] = [
                                    'nome' => sanitize($c['nome']),
                                    'partido' => sanitize($c['partido'] ?? ''),
                                    'slogan' => sanitize($c['slogan'] ?? ''),
                                    'biografia' => sanitize($c['biografia'] ?? ''),
                                    'user_id' => !empty($c['user_id']) ? (int)$c['user_id'] : null,
                                    'username' => sanitize($c['username'] ?? '')
                                ];
                            }
                        }
                    }
                    $temas[] = [
                        'titulo' => $tTitulo,
                        'descricao' => sanitize($tema['descricao'] ?? ''),
                        'tipo_votacao' => sanitize($tema['tipo_votacao'] ?? 'unico'),
                        'candidatos' => $candidatos
                    ];
                }
            }
        }
        
        $_SESSION['wizard_data']['temas'] = $temas;
        
        if (empty($temas)) $errors[] = "Adicione pelo menos um tema com candidatos.";
        
        if (empty($errors)) {
            if (isset($_POST['back'])) $step = 1;
            else $step = 3;
        }
    } 
    elseif ($step === 3) {
        if (isset($_POST['back'])) {
            $step = 2;
        } else {
            // --- FINAL SUBMISSION ---
            try {
                $pdo->beginTransaction();
                $data = $_SESSION['wizard_data'];
                $codigo = generateCode('VOX');

                $stmt = $pdo->prepare("
                    INSERT INTO salas_eleitorais 
                        (nome, descricao, codigo_acesso, tipo, visibilidade, provincia_origem,
                         organizador_id, estado, fase_atual, data_inicio, data_fim,
                         data_campanha_inicio, data_campanha_fim, data_votacao_inicio, data_votacao_fim,
                         voto_anonimo, permitir_campanha)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'rascunho', 'aguardando', ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['nome'], $data['descricao'], $codigo, $data['tipo'], $data['visibilidade'],
                    $data['provincia'] > 0 ? $data['provincia'] : null,
                    $userId,
                    $data['data_votacao_inicio'] ?? null, // legacy data_inicio
                    $data['data_votacao_fim']    ?? null, // legacy data_fim
                    $data['data_campanha_inicio'] ?: null,
                    $data['data_campanha_fim']    ?: null,
                    $data['data_votacao_inicio']  ?: null,
                    $data['data_votacao_fim']     ?: null,
                    (bool)($data['voto_anonimo'] ?? true), (bool)($data['permitir_campanha'] ?? true)
                ]);
                $salaId = $pdo->lastInsertId();

                foreach ($data['temas'] as $idx => $tema) {
                    $stmt = $pdo->prepare("INSERT INTO temas (sala_id, titulo, descricao, ordem, tipo_votacao) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$salaId, $tema['titulo'], $tema['descricao'], $idx + 1, $tema['tipo_votacao']]);
                    $temaId = $pdo->lastInsertId();
                    foreach ($tema['candidatos'] as $c) {
                        $stmt = $pdo->prepare("
                            INSERT INTO candidatos (sala_id, tema_id, user_id, nome, biografia, partido, slogan, criado_por)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$salaId, $temaId, $c['user_id'], $c['nome'], $c['biografia'], $c['partido'], $c['slogan'], $userId]);
                        
                        if (!empty($c['user_id'])) {
                            // Adiciona diretamente o membro à sala, bypass do convite pendente (Using ON CONFLICT for PG compatibility)
                            $stmtMembro = $pdo->prepare("INSERT INTO sala_membros (sala_id, user_id, papel) VALUES (?, ?, 'candidato') ON CONFLICT (sala_id, user_id) DO NOTHING");
                            $stmtMembro->execute([$salaId, $c['user_id']]);

                            // Envia notificação de adição direta com link para a sala
                            $notif = $pdo->prepare("INSERT INTO notificacoes (user_id, tipo, mensagem, link) VALUES (?, 'info', ?, ?)");
                            $msg = "Foste adicionado(a) diretamente como Candidato(a) na sala " . $data['nome'] . "!";
                            $notif->execute([$c['user_id'], $msg, "sala_detalhes.php?id=" . $salaId]);
                        }
                    }
                }

                $pdo->commit();
                
                // Final Sync to ensure room starts in the correct chronological phase
                syncRoomPhase($pdo, $salaId);

                unset($_SESSION['wizard_data']);
                setFlash('success', "Eleição '$data[nome]' publicada com sucesso! Código: $codigo");
                redirect("sala_detalhes.php?id=$salaId");
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Erro crítico ao publicar: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Configurar Eleição';
require 'includes/header.php';
?>

<style>
    .wizard-container {
        max-width: 900px;
        margin: 4rem auto;
        padding: 0 5%;
    }

    /* Progress Bar */
    .wizard-progress {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4rem;
        position: relative;
    }

    .wizard-progress::before {
        content: '';
        position: absolute;
        top: 20px; left: 0; width: 100%; height: 2px;
        background: var(--gray-200);
        z-index: 0;
    }

    .progress-step {
        position: relative;
        z-index: 1;
        text-align: center;
        width: 40px;
    }

    .step-circle {
        width: 40px; height: 40px;
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; color: var(--gray-500);
        margin-bottom: 0.75rem;
        transition: 0.3s;
    }

    .progress-step.active .step-circle {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
    }

    .progress-step.completed .step-circle {
        background: var(--success);
        border-color: var(--success);
        color: white;
    }

    .step-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--gray-500);
        white-space: nowrap;
        position: absolute;
        left: 50%; transform: translateX(-50%);
    }

    .progress-step.active .step-label { color: var(--primary); }

    /* Form Styles */
    .wizard-card {
        background: white;
        border-radius: 2rem;
        padding: 3rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--gray-200);
    }

    .wizard-card h2 {
        font-size: 1.75rem;
        font-weight: 900;
        margin-bottom: 2rem;
    }

    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; font-weight: 700; margin-bottom: 0.5rem; font-size: 0.9rem; }
    
    .form-control {
        width: 100%; padding: 0.85rem 1.25rem;
        border: 1px solid var(--gray-200);
        border-radius: 0.75rem;
        font-family: inherit;
        font-size: 1rem;
        transition: 0.3s;
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .btn-wizard {
        padding: 1rem 2rem;
        border-radius: 0.75rem;
        font-weight: 800;
        cursor: pointer;
        border: none;
        transition: 0.3s;
        font-size: 1rem;
    }

    .btn-wizard-next { background: var(--primary); color: white; }
    .btn-wizard-next:hover { background: var(--primary-dark); transform: translateY(-2px); }

    .btn-wizard-back { background: var(--gray-100); color: var(--gray-900); }
    .btn-wizard-back:hover { background: var(--gray-200); }

    .tema-wizard-card {
        padding: 2rem;
        background: #f8fafc;
        border-radius: 1.5rem;
        border: 1px solid var(--gray-200);
        margin-bottom: 2rem;
    }

    /* User Search Dropdown */
    .user-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid var(--border-color);
        border-top: none;
        border-radius: 0 0 1rem 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        z-index: 9999;
        max-height: 280px;
        overflow-y: auto;
        display: none;
        animation: slideDown 0.2s ease-out;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .search-result-item {
        transition: all 0.2s ease;
    }
    .search-result-item:hover {
        background: #f1f5f9;
    }
</style>

<div class="wizard-container">
    
    <!-- Progress Bar -->
    <div class="wizard-progress">
        <div class="progress-step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>">
            <div class="step-circle"><?= $step > 1 ? '✓' : '1' ?></div>
            <span class="step-label">Contexto</span>
        </div>
        <div class="progress-step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">
            <div class="step-circle"><?= $step > 2 ? '✓' : '2' ?></div>
            <span class="step-label">Estrutura</span>
        </div>
        <div class="progress-step <?= $step >= 3 ? 'active' : '' ?>">
            <div class="step-circle">3</div>
            <span class="step-label">Revisão</span>
        </div>
    </div>

    <!-- Step Content -->
    <div class="wizard-card">
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div style="padding: 1rem; background: var(--danger); color: white; border-radius: 0.75rem; margin-bottom: 1.5rem; font-weight: 600;"><?= $err ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="step" value="<?= $step ?>">

            <?php if ($step === 1): ?>
                <h2>1. Contexto & Agenda 📝</h2>

                <div class="form-group">
                    <label>Nome da Sala Eleitoral *</label>
                    <input type="text" name="nome" class="form-control" placeholder="Ex: Assembleia Geral 2026"
                           value="<?= htmlspecialchars($_SESSION['wizard_data']['nome']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Breve Descrição</label>
                    <textarea name="descricao" class="form-control" rows="2"><?= htmlspecialchars($_SESSION['wizard_data']['descricao']) ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label>Âmbito</label>
                        <select name="tipo" class="form-control">
                            <option value="institucional"<?= $_SESSION['wizard_data']['tipo'] == 'institucional'? 'selected' : '' ?>>🏛️ Institucional (Padrão)</option>
                            <option value="nacional"     <?= $_SESSION['wizard_data']['tipo'] == 'nacional'     ? 'selected' : '' ?>>🌍 Nacional</option>
                            <option value="municipal"    <?= $_SESSION['wizard_data']['tipo'] == 'municipal'    ? 'selected' : '' ?>>🏙️ Municipal</option>
                            <option value="comunitario"  <?= $_SESSION['wizard_data']['tipo'] == 'comunitario'  ? 'selected' : '' ?>>👥 Comunitário</option>
                            <option value="pesquisa"     <?= $_SESSION['wizard_data']['tipo'] == 'pesquisa'     ? 'selected' : '' ?>>📊 Pesquisa / Inquérito</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Província (se aplicável)</label>
                        <select name="provincia" class="form-control">
                            <option value="0">Todas</option>
                            <?php foreach ($provincias as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $_SESSION['wizard_data']['provincia'] == $p['id'] ? 'selected' : '' ?>><?= $p['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Visibilidade -->
                <div class="form-group" style="padding: 1.5rem; background: var(--gray-50); border-radius: 1rem;">
                    <label style="font-weight: 800; margin-bottom: 1rem; display: block;">Visibilidade da Sala *</label>
                    <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                            <input type="radio" name="visibilidade" value="privada" <?= ($_SESSION['wizard_data']['visibilidade'] ?? 'privada') == 'privada' ? 'checked' : '' ?>>
                            <div>
                                <span style="font-weight: 700; display: block;">Privada</span>
                                <span style="font-size: 0.75rem; color: var(--gray-500);">Apenas quem tem o código pode votar.</span>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                            <input type="radio" name="visibilidade" value="publica" <?= ($_SESSION['wizard_data']['visibilidade'] ?? '') == 'publica' ? 'checked' : '' ?>>
                            <div>
                                <span style="font-weight: 700; display: block;">Pública</span>
                                <span style="font-size: 0.75rem; color: var(--gray-500);">Aparece no feed global de votações.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Options -->
                <div style="display: flex; gap: 2rem; margin-top: 0.5rem; margin-bottom: 2rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="voto_anonimo" value="1" <?= $_SESSION['wizard_data']['voto_anonimo'] ? 'checked' : '' ?>> Voto Anónimo
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer;" id="lbl-campanha">
                        <input type="checkbox" name="permitir_campanha" value="1" id="chk-campanha"
                               <?= $_SESSION['wizard_data']['permitir_campanha'] ? 'checked' : '' ?>> Permitir Fase de Campanha
                    </label>
                </div>

                <!-- ═══ FASES DE TEMPO ════════════════════════════════════════════ -->
                <div style="background: linear-gradient(135deg, rgba(59,130,246,0.05), rgba(16,185,129,0.05)); border: 1px solid var(--border-color); border-radius: 1.5rem; padding: 2rem; margin-bottom: 1.5rem;">
                    <h4 style="font-weight: 900; margin-bottom: 1.75rem; display: flex; align-items: center; gap: 0.75rem;">
                        ⏱️ Agenda das Fases Eleitorais
                    </h4>

                    <!-- Campanha Dates (shown/hidden by checkbox) -->
                    <div id="campanha-dates" style="<?= $_SESSION['wizard_data']['permitir_campanha'] ? '' : 'display:none;' ?>">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <div style="background: var(--primary); color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 0.8rem;">1</div>
                            <strong>Fase de Campanha <span style="color:var(--gray-500); font-weight:400;">(pré-eleitoral, opcional)</span></strong>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-left: 2.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group" style="margin:0">
                                <label style="font-size: 0.8rem;">Início da Campanha</label>
                                <input type="datetime-local" name="data_campanha_inicio" class="form-control"
                                       value="<?= $_SESSION['wizard_data']['data_campanha_inicio'] ?>">
                            </div>
                            <div class="form-group" style="margin:0">
                                <label style="font-size: 0.8rem;">Fim da Campanha</label>
                                <input type="datetime-local" name="data_campanha_fim" class="form-control"
                                       value="<?= $_SESSION['wizard_data']['data_campanha_fim'] ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Votação Dates -->
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <div style="background: #10b981; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 0.8rem;" id="vot-step-num">2</div>
                            <strong>Fase de Votação <span style="color: #f59e0b; font-weight: 700;">(obrigatório)</span></strong>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-left: 2.5rem;">
                            <div class="form-group" style="margin:0">
                                <label style="font-size: 0.8rem;">Abertura das Urnas *</label>
                                <input type="datetime-local" name="data_votacao_inicio" class="form-control" required
                                       value="<?= $_SESSION['wizard_data']['data_votacao_inicio'] ?>">
                            </div>
                            <div class="form-group" style="margin:0">
                                <label style="font-size: 0.8rem;">Encerramento das Urnas *</label>
                                <input type="datetime-local" name="data_votacao_fim" class="form-control" required
                                       value="<?= $_SESSION['wizard_data']['data_votacao_fim'] ?>">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; padding: 0.75rem 1rem; background: rgba(245,158,11,0.1); border-radius: 0.75rem; font-size: 0.85rem; color: #92400e;">
                        <i class="fa fa-info-circle"></i> As <strong>Estatísticas</strong> ficam disponíveis automaticamente após o encerramento das urnas.
                    </div>
                </div>

                <div style="margin-top: 3rem; text-align: right;">
                    <button type="submit" class="btn-wizard btn-wizard-next">Próximo Passo: Estrutura →</button>
                </div>

                <script>
                document.getElementById('chk-campanha').addEventListener('change', function() {
                    document.getElementById('campanha-dates').style.display = this.checked ? '' : 'none';
                    document.getElementById('vot-step-num').textContent = this.checked ? '2' : '1';
                });
                </script>

            <?php elseif ($step === 2): ?>
                <h2>2. Estrutura Eleitoral 🗳️</h2>

                <div style="background: rgba(59,130,246,0.05); border: 1px solid rgba(59,130,246,0.2); border-radius: 1rem; padding: 1rem 1.5rem; margin-bottom: 2rem; font-size: 0.9rem; color: var(--primary-dark);">
                    <i class="fa fa-lightbulb-o"></i>
                    <strong>Como funciona:</strong> Adicione candidatos pelo <strong>@username</strong> (se já têm conta) ou pelo <strong>nome</strong>.
                    Candidatos convidados por @username receberão uma notificação e preenchem o próprio perfil na sala.
                </div>

                <div id="temasList">
                    <?php 
                    $temas = $_SESSION['wizard_data']['temas'] ?: [['titulo' => '', 'descricao' => '', 'tipo_votacao' => 'unico', 'candidatos' => [['nome' => '', 'partido' => '']]]]; 
                    foreach ($temas as $tIdx => $tema): 
                    ?>
                        <div class="tema-wizard-card" data-index="<?= $tIdx ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                <h4 style="font-weight: 800; color: var(--primary);">Tema #<?= $tIdx + 1 ?></h4>
                                <?php if ($tIdx > 0): ?><button type="button" class="btn-remover-tema" style="color: var(--danger); background: none; border: none; cursor: pointer; font-weight: 700;">Remover Tema</button><?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>Título do Cargo / Questão *</label>
                                <input type="text" name="temas[<?= $tIdx ?>][titulo]" class="form-control"
                                       placeholder="Ex: Presidente da Câmara" value="<?= htmlspecialchars($tema['titulo']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Tipo de Votação</label>
                                <select name="temas[<?= $tIdx ?>][tipo_votacao]" class="form-control">
                                    <option value="unico"   <?= $tema['tipo_votacao'] == 'unico'   ? 'selected' : '' ?>>Escolha Única (Candidatos/Opções)</option>
                                    <option value="sim_nao" <?= $tema['tipo_votacao'] == 'sim_nao' ? 'selected' : '' ?>>Sim / Não / Abstenção</option>
                                </select>
                            </div>
                            
                            <div class="candidatos-area">
                                <label style="margin-top: 1.5rem; margin-bottom: 1rem; display: block; font-weight: 700;">
                                    Candidatos / Opções
                                </label>

                                <!-- @username invite search -->
                                <div style="position: relative; margin-bottom: 1.5rem;">
                                    <div style="display: flex; gap: 0.5rem;">
                                        <input type="text" class="form-control username-search"
                                               placeholder="🔍 Pesquisar por @username ou nome..."
                                               data-tema="<?= $tIdx ?>"
                                               style="border: 2px dashed var(--primary);">
                                        <button type="button" class="btn-wizard btn-wizard-next" style="padding: 0.75rem 1.25rem; white-space: nowrap; font-size: 0.85rem;"
                                                onclick="doUserSearch(this.previousElementSibling)">Buscar</button>
                                    </div>
                                    <div class="user-search-results"></div>
                                </div>

                                <!-- Manual list -->
                                <div class="candidatos-list">
                                    <?php foreach ($tema['candidatos'] as $cIdx => $cand): ?>
                                        <div class="candidato-row" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                            <span style="color: var(--gray-400); font-size: 0.8rem; width: 1.5rem; text-align:center;"><?= $cIdx + 1 ?></span>
                                            <input type="text" name="candidatos[<?= $tIdx ?>][<?= $cIdx ?>][nome]" class="form-control"
                                                   placeholder="Nome Completo do Candidato" value="<?= htmlspecialchars($cand['nome']) ?>" required>
                                            <input type="text" name="candidatos[<?= $tIdx ?>][<?= $cIdx ?>][partido]" class="form-control"
                                                   placeholder="Partido/Sigla" value="<?= htmlspecialchars($cand['partido'] ?? '') ?>"
                                                   style="max-width: 160px;">
                                            <button type="button" onclick="this.closest('.candidato-row').remove()"
                                                    style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:1.2rem; padding: 0 0.5rem;">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn-add-cand"
                                        style="background: none; border: 1px dashed var(--gray-400); padding: 0.5rem 1rem; border-radius: 0.5rem; color: var(--gray-600); cursor: pointer; margin-top: 0.5rem; font-weight: 600; width: 100%;">
                                    + Adicionar Candidato Manualmente
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="addTema" style="width: 100%; padding: 1.5rem; background: rgba(59, 130, 246, 0.05); border: 2px dashed var(--primary); border-radius: 1.5rem; color: var(--primary); font-weight: 800; cursor: pointer; margin-bottom: 3rem;">+ Adicionar Nova Questão / Cargo</button>

                <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
                    <button type="submit" name="back" class="btn-wizard btn-wizard-back">← Voltar</button>
                    <button type="submit" class="btn-wizard btn-wizard-next">Próximo Passo: Revisão →</button>
                </div>

            <?php elseif ($step === 3): ?>
                <h2>3. Revisão & Publicação 🏁</h2>
                <div style="background: #f8fafc; padding: 2rem; border-radius: 1.5rem; margin-bottom: 2rem; border: 1px solid var(--gray-200);">
                    <h3 style="margin-bottom: 1rem;">Resumo da Configuração</h3>
                    <p><strong>Nome:</strong> <?= htmlspecialchars($_SESSION['wizard_data']['nome']) ?></p>
                    <p><strong>Descrição:</strong> <?= htmlspecialchars($_SESSION['wizard_data']['descricao']) ?></p>
                    <p><strong>Período:</strong> <?= $_SESSION['wizard_data']['data_inicio'] ?> até <?= $_SESSION['wizard_data']['data_fim'] ?></p>
                    <hr style="margin: 1.5rem 0; opacity: 0.2;">
                    <h4 style="margin-bottom: 1rem;">Boletim de Voto (Preview)</h4>
                    <?php foreach ($_SESSION['wizard_data']['temas'] as $tema): ?>
                        <div style="background: white; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem; border: 1px solid var(--gray-200);">
                            <div style="font-weight: 800;"><?= htmlspecialchars($tema['titulo']) ?></div>
                            <div style="font-size: 0.85rem; color: var(--gray-500); margin-bottom: 0.75rem;"><?= htmlspecialchars($tema['descricao']) ?></div>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <?php foreach ($tema['candidatos'] as $c): ?>
                                    <div style="padding: 0.5rem 1rem; background: var(--gray-100); border-radius: 0.5rem; font-size: 0.9rem; font-weight: 600;">○ <?= htmlspecialchars($c['nome']) ?> <small style="color:var(--gray-500)">(<?= htmlspecialchars($c['partido']) ?>)</small></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="padding: 1.5rem; background: rgba(59, 130, 246, 0.05); border-left: 4px solid var(--primary); border-radius: 0.5rem; margin-bottom: 3rem;">
                    <p style="font-size: 0.9rem; color: var(--primary-dark); font-weight: 500;">
                        Ao clicar em "Publicar", a sala será criada imediatamente e o código de acesso será gerado. Certifique-se de que todas as informações abaixo estão corretas.
                    </p>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
                    <button type="submit" name="back" class="btn-wizard btn-wizard-back">← Alterar Estrutura</button>
                    <button type="submit" class="btn-wizard btn-pulse" style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 1.25rem 3rem; font-size: 1.1rem; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);">
                        Publicar Eleição Oficial <i class="fa fa-paper-plane" style="margin-left: 8px;"></i>
                    </button>
                </div>
            <?php endif; ?>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const addTemaBtn = document.getElementById('addTema');
    if (!addTemaBtn) return;

    const initBtns = (card) => {
        // Add candidate row manually
        card.querySelector('.btn-add-cand')?.addEventListener('click', () => {
            addCandidateRow(card);
        });
        // Remove tema
        card.querySelector('.btn-remover-tema')?.addEventListener('click', () => card.remove());
    };

    addTemaBtn.addEventListener('click', () => {
        const list = document.getElementById('temasList');
        const count = list.children.length;
        const card = list.children[0].cloneNode(true);
        card.dataset.index = count;
        card.querySelector('h4').textContent = `Tema #${count + 1}`;
        card.querySelectorAll('input, select, textarea').forEach(el => {
            if (el.name) el.name = el.name.replace(/\[\d+\]/, `[${count}]`);
            el.value = '';
        });
        // Clear search results
        const sr = card.querySelector('.user-search-results');
        if (sr) { sr.innerHTML = ''; sr.style.display = 'none'; }
        // Reset candidates to one empty row
        const candList = card.querySelector('.candidatos-list');
        while (candList && candList.children.length > 1) candList.lastChild.remove();
        // Re-init the remove btn on first row
        const first = candList?.children[0];
        if (first) first.querySelectorAll('input').forEach(i => i.value = '');

        list.appendChild(card);
        initBtns(card);
    });

    document.querySelectorAll('.tema-wizard-card').forEach(initBtns);
});

function addCandidateRow(card, name = '', partido = '', userId = '', username = '') {
    const tIdx = card.dataset.index;
    const list = card.querySelector('.candidatos-list');
    const cIdx = list.children.length;
    const row = document.createElement('div');
    row.className = 'candidato-row';
    row.style.cssText = 'display:flex; gap:0.5rem; margin-bottom:0.5rem; align-items:center;';
    
    let hiddenInputs = '';
    if (userId) {
        hiddenInputs = `
            <input type="hidden" name="candidatos[${tIdx}][${cIdx}][user_id]" value="${userId}">
            <input type="hidden" name="candidatos[${tIdx}][${cIdx}][username]" value="${username}">
        `;
    }

    row.innerHTML = `
        ${hiddenInputs}
        <span style="color:var(--gray-400);font-size:0.8rem;width:1.5rem;text-align:center;">${cIdx + 1}</span>
        <input type="text" name="candidatos[${tIdx}][${cIdx}][nome]" class="form-control"
               placeholder="Nome Completo do Candidato" value="${name}" required>
        <input type="text" name="candidatos[${tIdx}][${cIdx}][partido]" class="form-control"
               placeholder="Partido/Sigla" value="${partido}" style="max-width:160px;">
        <button type="button" onclick="this.closest('.candidato-row').remove()"
                style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:1.2rem;padding:0 0.5rem;">×</button>
    `;
    list.appendChild(row);
}

// ── @username live search ───────────────────────────────────────────────────
let searchTimer;

async function performUserSearch(input) {
    const q = input.value.trim();
    const area = input.closest('.candidatos-area');
    const results = area.querySelector('.user-search-results');
    if (!results) return;

    if (q.length < 2) { 
        results.style.display = 'none'; 
        return; 
    }

    results.innerHTML = '<div style="padding:1.5rem; text-align:center;"><i class="fa fa-spinner fa-spin fa-2x" style="color:var(--primary);"></i><br><small style="color:var(--gray-500); margin-top:0.5rem; display:block;">A procurar...</small></div>';
    results.style.display = 'block';
    
    try {
        const data = await window.apiCall(`api/users.php?action=search&q=${encodeURIComponent(q)}`, { 
            method: 'GET',
            showError: false
        });
        
        if (!data.success || !data.users || !data.users.length) {
            results.innerHTML = '<div style="padding:2rem; color:var(--gray-500); text-align:center;">' +
                                '<i class="fa fa-search" style="font-size:2rem; opacity:0.2; margin-bottom:1rem; display:block;"></i>' +
                                'Nenhum utilizador encontrado com "<strong>'+window.escapeHtml(q)+'</strong>"</div>';
            return;
        }
        
        results.innerHTML = data.users.map(u => `
            <div onclick="addUserAsCandidate(this, ${u.id}, '${(u.nome_completo||'').replace(/'/g,"\\'")}', '${(u.username||'').replace(/'/g,"\\'")}', event)"
                 style="padding:1rem 1.5rem; cursor:pointer; display:flex; align-items:center; gap:1.25rem; border-bottom:1px solid var(--gray-100);"
                 onmouseover="this.style.background='var(--gray-50)'" 
                 onmouseout="this.style.background=''"
                 class="search-result-item"
                 data-user-id="${u.id}">
                <div style="width:48px; height:48px; background:linear-gradient(135deg, var(--primary), var(--secondary)); color:white; border-radius:12px; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:1.2rem; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);">
                    ${(u.nome_completo||'?').charAt(0).toUpperCase()}
                </div>
                <div style="flex-grow:1;">
                    <div style="font-weight:900; font-size:1.1rem; color:var(--gray-900); margin-bottom:2px;">${u.nome_completo}</div>
                    <div style="font-size:0.85rem; color:var(--primary); font-family: 'JetBrains Mono', monospace; font-weight:600;">${u.username || '@utilizador'}</div>
                </div>
                <div style="color:white; font-weight:800; font-size:0.75rem; background: var(--primary); padding:0.5rem 1rem; border-radius:10px; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);">+ ADICIONAR</div>
            </div>
        `).join('');
    } catch(ex) {
        results.innerHTML = `<div style="padding:1.5rem; color:var(--danger); text-align:center;">
            <i class="fa fa-exclamation-triangle" style="display:block; margin-bottom:0.5rem;"></i>
            Erro: ${window.escapeHtml(ex.message || 'Desconhecido')}
        </div>`;
    }
}

document.addEventListener('input', e => {
    if (!e.target.classList.contains('username-search')) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => performUserSearch(e.target), 400);
});

function addUserAsCandidate(el, userId, nome, username, event) {
    event.stopPropagation();
    const card = el.closest('.tema-wizard-card');
    if (!card) return;
    
    // Check if already added in THIS card
    const existing = Array.from(card.querySelectorAll('.candidato-row input[name*="user_id"]'))
                          .find(input => input.value == userId);
    
    if (existing) {
        window.showErrorToast('Este utilizador já foi adicionado a este cargo.');
        return;
    }
    
    addCandidateRow(card, nome, '', userId, username);
    window.showSuccessToast(`${nome} adicionado com sucesso!`);
    
    // Close dropdown and clear
    const results = el.closest('.user-search-results');
    if (results) results.style.display = 'none';
    
    const input = el.closest('.candidatos-area')?.querySelector('.username-search');
    if (input) input.value = '';
}

// Close dropdown on outside click
document.addEventListener('click', e => {
    if (!e.target.closest('.candidatos-area')) {
        document.querySelectorAll('.user-search-results').forEach(r => r.style.display = 'none');
    }
});

function doUserSearch(btn) { 
    const input = btn.previousElementSibling;
    if (input) performUserSearch(input);
}
</script>

<?php require 'includes/footer.php'; ?>
