<?php
/**
 * sala_detalhes.php - Vox Electoral Platform
 * Refactored details page with Accordion UI and Grouped Logic.
 */
require_once 'config/helpers.php';
$userId = requireAuth();
generateCSRFToken();

$salaId = (int)($_GET['id'] ?? 0);
$codeParam = sanitize($_GET['code'] ?? '');

$sala = null;

if ($salaId > 0) {
    // Get sala details by ID
    $stmt = $pdo->prepare("
        SELECT s.*, p.nome as provincia_nome, u.nome_completo as organizador_nome
        FROM salas_eleitorais s
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        LEFT JOIN users u ON s.organizador_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$salaId]);
    $sala = $stmt->fetch();
} elseif (!empty($codeParam)) {
    // Get sala details by Access Code
    $stmt = $pdo->prepare("
        SELECT s.*, p.nome as provincia_nome, u.nome_completo as organizador_nome
        FROM salas_eleitorais s
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        LEFT JOIN users u ON s.organizador_id = u.id
        WHERE s.codigo_acesso = ?
    ");
    $stmt->execute([$codeParam]);
    $sala = $stmt->fetch();
    if ($sala) {
        $salaId = (int)$sala['id'];
    }
}

if (!$sala) {
    setFlash('error', 'Sala não encontrada.');
    redirect('home.php');
}

$userRole = $_SESSION['user_role'] ?? 'eleitor';
$isOrganizer = ($sala['organizador_id'] == $userId || $userRole === 'admin');

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votos WHERE sala_id = ?");
$stmt->execute([$salaId]);
$totalVotosGeneral = $stmt->fetchColumn();

// Get themes
$stmt = $pdo->prepare("SELECT * FROM temas WHERE sala_id = ? ORDER BY ordem ASC");
$stmt->execute([$salaId]);
$temas = $stmt->fetchAll();

// Get IDs of users followed by current user
$stmt = $pdo->prepare("SELECT seguido_id FROM seguidores WHERE seguidor_id = ?");
$stmt->execute([$userId]);
$followedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare detailed data per theme
$temasData = [];
foreach ($temas as $tema) {
    $temaId = $tema['id'];
    
    // Total votes for this specifically theme
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votos WHERE tema_id = ?");
    $stmt->execute([$temaId]);
    $temaTotalVotos = $stmt->fetchColumn();

    // Candidates for this theme
    $stmt = $pdo->prepare("
        SELECT c.*, u.username as user_handle,
               (SELECT COUNT(*) FROM votos WHERE candidato_id = c.id) as votos_cand
        FROM candidatos c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.tema_id = ?
        ORDER BY votos_cand DESC
    ");
    $stmt->execute([$temaId]);
    $candidates = $stmt->fetchAll();

    // Yes/No results if applicable
    $yesNo = null;
    if ($tema['tipo_votacao'] === 'sim_nao') {
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN opcao_sim_nao = 'sim' THEN 1 ELSE 0 END) as sim,
                SUM(CASE WHEN opcao_sim_nao = 'nao' THEN 1 ELSE 0 END) as nao
            FROM votos WHERE tema_id = ?
        ");
        $stmt->execute([$temaId]);
        $yesNoResult = $stmt->fetch();
        
        $sim = (int)($yesNoResult['sim'] ?? 0);
        $nao = (int)($yesNoResult['nao'] ?? 0);
        $total = $sim + $nao;
        
        $yesNo = [
            'sim' => $sim,
            'nao' => $nao,
            'total' => $total,
            'sim_pct' => $total > 0 ? round(($sim / $total) * 100, 1) : 0,
            'nao_pct' => $total > 0 ? round(($nao / $total) * 100, 1) : 0
        ];
    }

    $temasData[] = [
        'tema' => $tema,
        'candidates' => $candidates,
        'results' => $yesNo,
        'total_votos' => $temaTotalVotos
    ];
}

// Check participation
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votos WHERE sala_id = ? AND user_id = ?");
$stmt->execute([$salaId, $userId]);
$jaVotou = $stmt->fetchColumn() > 0;

// Fetch Campaign Posts (Twitter Style Feed)
$stmt = $pdo->prepare("
    SELECT cp.*, c.nome as cand_nome, c.partido, c.foto as cand_foto,
           u.nome_completo as author_nome,
           (SELECT COUNT(*) FROM post_reacoes WHERE post_id = cp.id AND tipo = 'adorado') as total_adorados,
           (SELECT COUNT(*) FROM post_reacoes WHERE post_id = cp.id AND tipo = 'hater') as total_haters,
           (SELECT tipo FROM post_reacoes WHERE post_id = cp.id AND user_id = :userId1) as user_reacao,
           (SELECT COUNT(*) FROM comentarios WHERE campanha_id = cp.id) as total_comentarios,
           (SELECT COUNT(*) FROM retweets WHERE post_id = cp.id) as total_retweets,
           (SELECT 1 FROM retweets WHERE post_id = cp.id AND user_id = :userId2) as user_retweeted
    FROM campanhas cp
    LEFT JOIN candidatos c ON cp.candidato_id = c.id
    LEFT JOIN users u ON cp.user_id = u.id
    WHERE cp.sala_id = :salaId
    ORDER BY cp.criado_em DESC
");
$stmt->execute(['userId1' => $userId, 'userId2' => $userId, 'salaId' => $salaId]);
$posts = $stmt->fetchAll();

// Check if current user is a candidate in THIS room OR the organizer
$stmt = $pdo->prepare("SELECT * FROM candidatos WHERE user_id = ? AND sala_id = ?");
$stmt->execute([$userId, $salaId]);
$candidateProfile = $stmt->fetch();

$isOrganizer = ($sala['organizador_id'] == $userId || $userRole === 'admin');
$isCandidateInRoom = (bool)$candidateProfile || $isOrganizer;

$pageTitle = 'Hub Eleitoral - ' . $sala['nome'];
require 'includes/header.php';

// --- AUTO-REGISTER MEMBER & NOTIFY ---
if ($salaId > 0 && $_SESSION['user_role'] !== 'admin') {
    // Check if user is already in sala_membros
    $stmtM = $pdo->prepare("SELECT papel FROM sala_membros WHERE sala_id = ? AND user_id = ?");
    $stmtM->execute([$salaId, $userId]);
    $membro = $stmtM->fetch();

    if (!$membro) {
        // Not a member yet, add as eleitor (Using ON CONFLICT for PG compatibility)
        $stmtIns = $pdo->prepare("INSERT INTO sala_membros (sala_id, user_id, papel) VALUES (?, ?, 'eleitor') ON CONFLICT (sala_id, user_id) DO NOTHING");
        if ($stmtIns->execute([$salaId, $userId])) {
            // Send welcome notification
            notifyUser($userId, 'info', 
                "🚀 Bem-vindo(a) à sala \"{$sala['nome']}\"! Agora podes acompanhar e participar nesta eleição.", 
                "sala_detalhes.php?id=" . $salaId
            );
        }
    }
}
?>

<style>
    .detail-hero {
        background: var(--bg-card); border-bottom: 1px solid var(--border-color); padding: 3rem 0; margin-bottom: 3rem;
    }
    .status-badge {
        padding: 0.5rem 1rem; border-radius: 2rem; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;
    }
    .status-ativa { background: rgba(16, 185, 129, 0.1); color: var(--green); }
    .status-finalizada { background: var(--gray-100); color: var(--gray-500); }

    .accordion-item {
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 1.5rem; margin-bottom: 1.25rem; overflow: hidden; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .accordion-header {
        padding: 1.75rem 2.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; background: var(--bg-card);
    }
    .accordion-header:hover { background: var(--gray-50); }
    .accordion-content {
        max-height: 0; padding: 0 2.5rem; overflow: hidden; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); background: var(--bg-body); opacity: 0;
    }
    .accordion-item.active .accordion-content { max-height: 2500px; padding: 2.5rem; border-top: 1px solid var(--border-color); opacity: 1; }
    .accordion-item.active { border-color: var(--primary); box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.1); transform: scale(1.01); }

    .cand-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
    .cand-stat-card {
        background: var(--bg-card); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 1.25rem; display: flex; align-items: center; gap: 1.25rem; transition: 0.3s;
    }
    .cand-stat-card:hover { border-color: var(--primary); transform: translateY(-3px); }
    .cand-avatar {
        width: 50px; height: 50px; background: linear-gradient(135deg, var(--gray-100), var(--gray-200)); border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--text-muted);
    }
    .cand-val { margin-left: auto; text-align: right; }

    .arrow-icon { transition: transform 0.3s; font-size: 0.8rem; opacity: 0.4; }
    .active .arrow-icon { transform: rotate(180deg); opacity: 1; color: var(--primary); }

    /* TWITTER X STYLE CSS */
    .social-hub-layout { display: grid; grid-template-columns: 275px minmax(500px, 600px) 350px; gap: 2rem; max-width: 1250px; margin: 0 auto; align-items: start;}
    @media (max-width: 1024px) { .social-hub-layout { grid-template-columns: 80px 1fr; } .hub-sidebar-column { display: none; } }
    .hub-nav-column { position: sticky; top: 2rem; display: flex; flex-direction: column; }
    .x-nav-item { display: flex; align-items: center; gap: 1.25rem; padding: 0.75rem 1rem; border-radius: 9999px; font-size: 1.25rem; color: var(--gray-900); text-decoration: none; transition: 0.2s; cursor: pointer; background: transparent; border: none; text-align: left; width: max-content; }
    .x-nav-item:hover { background-color: var(--gray-200); }
    .x-nav-item.active { font-weight: bold; }
    .x-nav-item i { font-size: 1.5rem; width: 30px; text-align: center;}
    .x-btn-post { background-color: var(--primary); color: white; border: none; padding: 1rem; border-radius: 9999px; font-weight: bold; font-size: 1.1rem; cursor: pointer; width: 90%; margin-top: 1rem; transition: 0.2s; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.25);}
    .x-btn-post:hover { background-color: var(--primary-dark); }
    .hub-main-column { border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); min-height: 100vh; padding-bottom: 50px;}
    .x-header { position: sticky; top: 0; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); z-index: 10; font-weight: bold; font-size: 1.25rem; cursor: pointer;}
    .x-post-composer { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; gap: 1rem; }
    .x-composer-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0; font-size: 1.2rem;}
    .x-composer-input { flex-grow: 1; }
    .x-composer-input textarea { width: 100%; border: none; font-size: 1.25rem; resize: none; outline: none; font-family: inherit; background: transparent; padding-top: 0.5rem;}
    .x-composer-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; border-top: 1px solid var(--gray-100); padding-top: 0.75rem; }
    .x-composer-tools { display: flex; gap: 0.25rem; }
    .x-tool-btn { color: var(--primary); background: transparent; border: none; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; transition: 0.2s; }
    .x-tool-btn:hover { background-color: rgba(59, 130, 246, 0.1); }
    .x-btn-submit { background-color: var(--primary); color: white; border: none; padding: 0.5rem 1.25rem; border-radius: 9999px; font-weight: bold; cursor: pointer; font-size: 1rem;}
    .x-feed-item { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; gap: 1rem; transition: 0.2s; cursor: pointer; }
    .x-feed-item:hover { background-color: rgba(0, 0, 0, 0.03); }
    .x-feed-content { flex-grow: 1; min-width: 0;}
    .x-feed-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
    .x-feed-name { font-weight: bold; color: var(--gray-900); text-decoration: none;}
    .x-feed-username, .x-feed-time { color: var(--gray-500); font-size: 0.95rem; }
    .x-feed-text { font-size: 1rem; line-height: 1.5; color: var(--gray-900); margin-bottom: 1rem; word-wrap: break-word;}
    .x-feed-media { border-radius: 1rem; overflow: hidden; margin-bottom: 1rem; border: 1px solid var(--border-color); }
    .x-feed-media img { width: 100%; height: auto; display: block; }
    .x-feed-actions { display: flex; justify-content: space-between; max-width: 425px; color: var(--gray-500); margin-top: 0.5rem;}
    .x-action-btn { display: flex; align-items: center; gap: 0.5rem; background: transparent; border: none; color: inherit; font-size: 0.95rem; cursor: pointer; transition: 0.2s; }
    .x-action-btn:hover { color: var(--primary); }
    .x-action-btn:hover .icon-wrap { background-color: rgba(59, 130, 246, 0.1); }
    .x-action-btn .icon-wrap { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    
    /* New Reactions Styles */
    .x-action-btn.adorado { color: #f91880; font-weight: bold; }
    .x-action-btn.adorado .icon-wrap { background-color: rgba(249, 24, 128, 0.1); }
    .x-action-btn.adorado i { animation: pop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    
    .x-action-btn.hater { color: #000; font-weight: bold; }
    .x-action-btn.hater .icon-wrap { background-color: rgba(0, 0, 0, 0.1); }
    .x-action-btn.hater i { animation: shake 0.4s ease-in-out; }

    @keyframes pop {
        0% { transform: scale(1); }
        50% { transform: scale(1.4); }
        100% { transform: scale(1); }
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-4px) rotate(-5deg); }
        75% { transform: translateX(4px) rotate(5deg); }
    }

    .x-btn-follow { transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); }
    .x-btn-follow:active { transform: scale(0.9); }
    .x-btn-follow.following:hover { color: #ef4444; border-color: #ef4444; background: rgba(239, 68, 68, 0.05); }
    .x-btn-follow.following:hover::after { content: 'Deixar de seguir'; position: absolute; } /* Handled by JS */
    
    .x-sidebar-widget { background-color: var(--gray-50); border-radius: 1rem; padding: 1rem 1.25rem; margin-bottom: 1rem; }
    .x-widget-title { font-size: 1.25rem; font-weight: 900; margin-bottom: 1rem; }
    .x-follow-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; cursor: pointer; transition: 0.2s; border-radius: 0.5rem; gap: 0.5rem;}
    .x-follow-item:hover { background-color: var(--gray-200); }
    .x-btn-follow { background-color: var(--gray-900); color: white; border: none; padding: 0.4rem 1rem; border-radius: 9999px; font-weight: bold; cursor: pointer; font-size: 0.9rem;}
    .x-btn-follow.following { background-color: transparent; border: 1px solid var(--border-color); color: var(--gray-900); }
    
    .social-tab-panel { 
        display: none; 
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    .social-tab-panel.active { 
        display: block; 
        opacity: 1;
        transform: translateY(0);
    }

    /* Animation classes */
    .slide-up { animation: slideUp 0.4s ease forwards; }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    
    .scale-in { animation: scaleIn 0.3s ease forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

    .hover-glow { transition: box-shadow 0.3s ease; }
    .hover-glow:hover { box-shadow: 0 0 15px var(--primary-glow); }

    /* DM Styles */
    .dm-contact-item { padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; gap: 0.75rem; cursor: pointer; transition: 0.2s; }
    .dm-contact-item:hover { background: var(--gray-50); }
    .dm-contact-item.active { background: var(--gray-100); border-right: 2px solid var(--primary); }
    .chat-bubble { max-width: 80%; padding: 0.75rem 1rem; border-radius: 1.25rem; margin-bottom: 0.5rem; font-size: 0.95rem; line-height: 1.4; position: relative; }
    .bubble-sent { background: var(--primary); color: white; align-self: flex-end; border-bottom-right-radius: 0.25rem; }
    .bubble-received { background: var(--gray-200); color: var(--gray-900); align-self: flex-start; border-bottom-left-radius: 0.25rem; }
    .chat-messages { flex-grow: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 0.25rem; background: #fff; }
    .chat-input-area { padding: 1rem; border-top: 1px solid var(--border-color); background: #fff; display: flex; gap: 0.75rem; align-items: center; }

    /* Notification Toasts */
    #notification-toast-container { position: fixed; bottom: 2rem; right: 2rem; z-index: 9999; display: flex; flex-direction: column; gap: 1rem; }
    .notification-toast { background: var(--bg-card); border-left: 4px solid var(--primary); padding: 1rem 1.5rem; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); display: flex; align-items: center; gap: 1rem; transform: translateX(120%); transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); min-width: 300px; }
    .notification-toast.show { transform: translateX(0); }

    /* ── PHASE LOCKING ─────────────────────────────── */
    .tab-locked { position: relative; }
    .tab-locked .x-nav-item.locked-tab { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
    .tab-locked .x-nav-item.locked-tab::after { content: '🔒'; font-size: 0.7rem; position: absolute; margin-left: 4px; }
    #phase-banner { display: none; align-items: center; gap: 1rem; padding: 0.85rem 1.5rem; font-weight: 700; font-size: 0.9rem; border-bottom: 1px solid var(--border-color); }
    #phase-banner.campanha { display: flex; background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(16,185,129,0.08)); color: var(--primary); }
    #phase-banner.votacao  { display: flex; background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(239,68,68,0.08)); color: #b45309; }
    #phase-banner.estatisticas { display: flex; background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.05)); color: #065f46; }
    #phase-banner.aguardando { display: flex; background: var(--gray-100); color: var(--gray-600); }

    /* ── COUNTDOWN TIMER ──────────────────────────── */
    .vote-countdown { 
        background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); 
        color: white; 
        border-radius: 1.5rem; 
        padding: 1.25rem 2rem; 
        margin: 1.5rem; 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        gap: 1rem; 
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.1);
        animation: slideDown 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .vote-countdown::after {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
        opacity: 0.05;
        pointer-events: none;
    }
    .countdown-units { display: flex; gap: 0.75rem; align-items: center; position: relative; z-index: 2; }
    .countdown-unit { 
        text-align: center; 
        background: rgba(255,255,255,0.05); 
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 1rem; 
        padding: 0.75rem 1.25rem; 
        min-width: 80px; 
        backdrop-filter: blur(10px);
    }
    .countdown-unit .num { 
        font-size: 2.75rem; 
        font-weight: 900; 
        line-height: 1; 
        display: block; 
        background: linear-gradient(to bottom, #fff, #94a3b8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0 10px 20px rgba(0,0,0,0.3);
    }
    .countdown-unit .lbl { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.2em; opacity: 0.6; margin-top: 0.25rem; display: block; font-weight: 800; }
    .countdown-sep { font-size: 2rem; font-weight: 900; opacity: 0.3; color: white; animation: blink 1s infinite; }
    @keyframes blink { 0%, 100% { opacity: 0.1; } 50% { opacity: 0.5; } }

    .vote-countdown.urgency-pulse {
        animation: pulseUrgent 1s infinite alternate, slideDown 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        border-color: #ef4444;
        box-shadow: 0 0 30px rgba(239, 68, 68, 0.4);
    }
    @keyframes pulseUrgent {
        from { background: linear-gradient(135deg, #7f1d1d 0%, #0f172a 100%); }
        to { background: linear-gradient(135deg, #b91c1c 0%, #0f172a 100%); }
    }

    /* ── E-COMMERCE CANDIDATE CARDS ──────────────── */
    .ecomm-cand-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; }
    .ecomm-cand-card { background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 1.5rem; overflow: hidden; transition: all 0.3s; position: relative; cursor: pointer; }
    .ecomm-cand-card:hover { border-color: var(--primary); transform: translateY(-5px); box-shadow: 0 20px 40px rgba(59,130,246,0.15); }
    .ecomm-cand-card.voted-for { border-color: #10b981; background: rgba(16,185,129,0.04); }
    .ecomm-cand-photo { height: 140px; background: linear-gradient(135deg, #1e40af, #3b82f6); display: flex; align-items: center; justify-content: center; font-size: 4rem; font-weight: 900; color: white; position: relative; }
    .ecomm-cand-photo img { width: 100%; height: 100%; object-fit: cover; }
    .ecomm-cand-badge { position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(0,0,0,0.5); color: white; border-radius: 9999px; padding: 0.2rem 0.6rem; font-size: 0.7rem; font-weight: 700; backdrop-filter: blur(4px); }
    .ecomm-cand-body { padding: 1.25rem; }
    .ecomm-cand-name { font-size: 1.05rem; font-weight: 900; margin-bottom: 0.25rem; }
    .ecomm-cand-party { font-size: 0.8rem; color: var(--primary); font-weight: 700; margin-bottom: 0.5rem; }
    .ecomm-cand-slogan { font-size: 0.85rem; color: var(--gray-500); line-height: 1.4; margin-bottom: 1rem; }
    .ecomm-vote-btn { width: 100%; padding: 0.85rem; background: var(--primary); color: white; border: none; border-radius: 0.75rem; font-weight: 800; font-size: 1rem; cursor: pointer; transition: all 0.2s; }
    .ecomm-vote-btn:hover { background: var(--primary-dark); transform: scale(1.02); }
    .ecomm-vote-btn.disabled { background: var(--gray-200); color: var(--gray-500); cursor: not-allowed; transform: none; }
    .ecomm-vote-btn.voted { background: #10b981; cursor: default; }
    .ecomm-report-btn { background: none; border: none; color: var(--gray-400); font-size: 0.75rem; cursor: pointer; padding: 0.25rem 0; display: flex; align-items: center; gap: 0.25rem; transition: 0.2s; }
    .ecomm-report-btn:hover { color: var(--danger); }
    .vote-confirm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9998; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); opacity: 0; pointer-events: none; transition: 0.3s; }
    .vote-confirm-overlay.show { opacity: 1; pointer-events: all; }
    .vote-confirm-modal { background: white; border-radius: 2rem; padding: 3rem; max-width: 480px; width: 90%; text-align: center; transform: scale(0.9); transition: 0.3s; }
    .vote-confirm-overlay.show .vote-confirm-modal { transform: scale(1); }
    .vote-success-anim { width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem; animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
    @keyframes popIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    /* ── STATISTICS ───────────────────────────────── */
    .winner-banner { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: 1.5rem; padding: 2rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 1.5rem; }
    .winner-trophy { font-size: 4rem; }
    .stat-participation-ring { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 2rem; }
    .stat-kpi { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 1.25rem; padding: 1.5rem; flex: 1; min-width: 140px; text-align: center; }
    .stat-kpi-val { font-size: 2.5rem; font-weight: 900; color: var(--primary); line-height: 1; }
    .stat-kpi-label { font-size: 0.8rem; color: var(--gray-500); margin-top: 0.35rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    .cand-stats-row { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 1.25rem; padding: 1.25rem 1.5rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 1.25rem; }
    .cand-stats-bar-wrap { flex-grow: 1; }
    .cand-stats-bar { height: 8px; background: var(--gray-100); border-radius: 9999px; overflow: hidden; margin-top: 0.5rem; }
    .cand-stats-bar-fill { height: 100%; border-radius: 9999px; background: var(--primary); transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); }
    .reaction-pills { display: flex; gap: 0.5rem; margin-top: 0.35rem; }
    .reaction-pill { font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 700; }
    .pill-like { background: rgba(249,24,128,0.1); color: #f91880; }
    .pill-hate { background: rgba(239,68,68,0.1); color: #ef4444; }

    /* Dark Mode Text Fixes */
    [data-theme='dark'] .x-feed-text,
    [data-theme='dark'] .x-feed-name,
    [data-theme='dark'] .x-header,
    [data-theme='dark'] .x-widget-title,
    [data-theme='dark'] .x-nav-item span {
        color: #f8fafc !important;
    }
    [data-theme='dark'] .x-feed-username,
    [data-theme='dark'] .x-feed-time,
    [data-theme='dark'] .x-sidebar-widget p {
        color: #94a3b8 !important;
    }

    .btn-follow-mini { 
        transition: all 0.2s; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
    }
    .btn-follow-mini:hover { 
        background: var(--primary) !important; 
        transform: scale(1.05); 
    }
    .btn-follow-mini:active { transform: scale(0.95); }

    .vote-confirm-overlay { 
        backdrop-filter: blur(8px); 
        background: rgba(0,0,0,0.4); 
    }
    .vote-confirm-modal {
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        border: 1px solid var(--border-color);
    }
</style>

<div id="notification-toast-container"></div>

<div class="dashboard-content">

    <!-- Hero Title Section -->
    <div style="margin-bottom: 2.5rem;">
        <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap;">
            <span class="status-badge status-<?= strtolower($sala['estado']) ?>"><?= $sala['estado'] ?></span>
            <span class="badge badge-info" style="border-radius:99px;"><?= strtoupper($sala['visibilidade']) ?></span>
            <?php if (!empty($sala['tipo_votacao_sala'])): ?>
            <span class="badge" style="background:var(--primary); color:white; border-radius:99px; padding:0.4rem 0.8rem; font-size:0.75rem; font-weight:800; text-transform:uppercase; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                <i class="fa fa-balance-scale" style="margin-right:0.3rem;"></i> SISTEMA: <?= str_replace('_', ' ', strtoupper($sala['tipo_votacao_sala'])) ?>
            </span>
            <?php endif; ?>
            <span class="badge" style="background:var(--gray-200); color:var(--gray-700); border-radius:99px; padding:0.4rem 0.8rem; font-size:0.75rem; font-weight:800; text-transform:uppercase;">
                <i class="fa fa-tag" style="margin-right:0.3rem;"></i> TEMPLATE <?= strtoupper($sala['tipo'] ?? 'institucional') ?>
            </span>
        </div>
        <h1 class="ve-title" style="font-size: 3rem;"><?= htmlspecialchars($sala['nome']) ?></h1>
        <?php if (!empty($sala['nome_organizacao'])): ?>
            <p style="color: var(--gray-500); font-weight: 500;">Hub oficial da eleição organizada por <strong><?= htmlspecialchars($sala['nome_organizacao']) ?></strong></p>
        <?php else: ?>
            <p style="color: var(--gray-500); font-weight: 500;">Hub oficial da eleição organizada por <a href="perfil.php?id=<?= $sala['organizador_id'] ?>"><strong><?= htmlspecialchars($sala['organizador_nome']) ?></strong></a></p>
        <?php endif; ?>
    </div>

    <!-- LIVE VOTE TICKER -->
    <div class="live-ticker-wrap">
        <div class="ticker-label">
            <div class="ticker-pulse"></div> LIVE
        </div>
        <div class="ticker-content" id="live-stats-content">
            <div class="ticker-item"><span>Total Votos:</span> <b class="ticker-val" id="ticker-total-votos"><?= (int)$totalVotosGeneral ?></b></div>
            <div class="ticker-item"><span>Engajamento:</span> <b class="ticker-val" id="ticker-total-posts"><?= count($posts) ?></b></div>
        </div>
    </div>

    <!-- TWITTER X STYLE SOCIAL HUB LAYOUT -->
    <div class="social-hub-layout">
        
        <!-- Left Navigation (Twitter Sidebar) -->
        <nav class="hub-nav-column">
            <?php 
            $fase = $sala['fase_atual'] ?? 'aguardando'; 
            $defaultTab = 'feed';
            // Only redirect away from feed if campaign is disabled OR phase is exclusively voting/stats
            if (!$sala['permitir_campanha']) {
                $defaultTab = ($fase === 'votacao') ? 'eleicao' : (($fase === 'estatisticas' || $fase === 'encerrada') ? 'estatisticas' : 'eleicao');
            } elseif (in_array($fase, ['votacao', 'estatisticas', 'encerrada'])) {
                // During voting/stats, organizers still see feed; others see the appropriate tab
                if (!$isOrganizer) {
                    $defaultTab = ($fase === 'votacao') ? 'eleicao' : 'estatisticas';
                }
            }
            // rascunho + aguardando with campaign enabled → always default to feed
            ?>

            <?php if ($sala['permitir_campanha']): ?>
            <button class="x-nav-item <?= ($defaultTab === 'feed') ? 'active' : '' ?>" id="nav-feed" data-tab="feed" onclick="switchSocialTab('feed', this)">
                <i class="fa fa-home"></i> <span>Página Inicial</span>
            </button>
            <?php endif; ?>
            <button class="x-nav-item" id="nav-mensagens" data-tab="mensagens" onclick="switchSocialTab('mensagens', this)">
                <i class="fa fa-envelope-o"></i> <span>Mensagens</span>
            </button>
            <button class="x-nav-item <?= ($defaultTab === 'eleicao') ? 'active' : '' ?>" id="nav-eleicao" data-tab="eleicao" onclick="switchSocialTab('eleicao', this)">
                <i class="fa fa-check-square-o"></i> <span>Votação</span>
            </button>
            <button class="x-nav-item <?= ($defaultTab === 'estatisticas') ? 'active' : '' ?>" id="nav-estatisticas" data-tab="estatisticas" onclick="switchSocialTab('estatisticas', this)">
                <i class="fa fa-bar-chart"></i> <span>Insights</span>
            </button>
            <button class="x-nav-item" onclick="window.location.href='perfil.php'">
                <i class="fa fa-user-o"></i> <span>Perfil</span>
            </button>
            <?php if ($isOrganizer): ?>
            <button class="x-nav-item" id="nav-audit" data-tab="audit" onclick="switchSocialTab('audit', this)">
                <i class="fa fa-shield"></i> <span>Auditoria</span>
            </button>
            <button class="x-nav-item" id="nav-reports" data-tab="reports" onclick="switchSocialTab('reports', this)">
                <i class="fa fa-flag"></i> <span>Denúncias</span>
            </button>
            <?php endif; ?>
            <?php if ($sala['permitir_campanha']): ?>
            <button class="x-btn-post" id="btn-post-nav" onclick="switchSocialTab('feed', document.getElementById('nav-feed')); document.querySelector('.x-composer-input textarea')?.focus()">Postar</button>
            <?php endif; ?>
        </nav>

        <!-- Main Hub Content (Middle Column) -->
        <main class="hub-main-column">

            <!-- PHASE BANNER -->
            <div id="phase-banner">
                <span id="phase-banner-icon">⏳</span>
                <span id="phase-banner-text">A verificar fase...</span>
                <span id="phase-banner-countdown" style="margin-left:auto; font-size:0.85rem; opacity:0.8;"></span>
            </div>

            <!-- GLOBAL COUNTDOWN TIMER (Visible during Countdown Phases) -->
            <div class="vote-countdown" id="voteCountdown" style="display:none">
                <div style="position: relative; z-index: 2;">
                    <div id="cd-title-1" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.15em; opacity:0.6; margin-bottom:0.35rem; font-weight:800;">Estado das Urnas</div>
                    <div id="cd-title-2" style="font-size:1.35rem; font-weight:900; letter-spacing:-0.5px; display:flex; align-items:center; gap:0.5rem;">
                        <span id="cd-indicator" style="color:#10b981; font-size:1.5rem;">●</span> <span id="cd-text">A Votação termina em</span>
                    </div>
                </div>
                <div class="countdown-units">
                    <div class="countdown-unit"><span class="num" id="cd-h">00</span><span class="lbl">Horas</span></div>
                    <span class="countdown-sep">:</span>
                    <div class="countdown-unit"><span class="num" id="cd-m">00</span><span class="lbl">Minutos</span></div>
                    <span class="countdown-sep">:</span>
                    <div class="countdown-unit"><span class="num" id="cd-s">00</span><span class="lbl">Segundos</span></div>
                </div>
            </div>
            
            <!-- SUB-TAB: FEED -->
            <div id="social-tab-feed" class="social-tab-panel <?= ($defaultTab === 'feed') ? 'active' : '' ?>">
                <div class="x-header" onclick="window.scrollTo({top:0, behavior:'smooth'})">
                    Página Inicial
                </div>

                <!-- Post Composer -->
                <div class="x-post-composer hover-glow scale-in">
                    <div class="x-composer-avatar"><?= strtoupper(substr($_SESSION['user_nome'] ?? 'V',0,1)) ?></div>
                    <form id="formPostCampanha" method="POST" action="api/interactions.php" enctype="multipart/form-data" style="flex-grow: 1;" onsubmit="return handlePostSubmit(event, this);">
                        <input type="hidden" name="action" value="create_post">
                        <input type="hidden" name="sala_id" value="<?= $salaId ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="candidato_id" value="<?= $candidateProfile['id'] ?? '' ?>">
                        
                        <div class="x-composer-input">
                            <textarea name="conteudo" placeholder="O que está a acontecer?" rows="1" required oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'" style="font-size: 1.25rem; font-weight: 500;"></textarea>
                        </div>
                        <div class="x-composer-actions">
                            <div class="x-composer-tools">
                                <label class="x-tool-btn" title="Imagem" style="color: var(--primary);">
                                    <i class="fa fa-image"></i>
                                    <input type="file" name="imagem" accept="image/*" style="display:none;" onchange="updateMediaCount(this)">
                                </label>
                                <label class="x-tool-btn" title="Vídeo" style="color: var(--success);">
                                    <i class="fa fa-file-video-o"></i>
                                    <input type="file" name="video" accept="video/*" style="display:none;" onchange="updateMediaCount(this)">
                                </label>
                                <label class="x-tool-btn" title="Áudio" style="color: var(--warning);">
                                    <i class="fa fa-file-audio-o"></i>
                                    <input type="file" name="audio" accept="audio/*" style="display:none;" onchange="updateMediaCount(this)">
                                </label>
                                <button type="button" class="x-tool-btn" title="Emoji" style="color: #fcd34d;"><i class="fa fa-smile-o"></i></button>
                                <span id="media-count" style="font-size: 0.75rem; color: var(--primary); margin-left: 0.5rem; display: none; font-weight: 700;"></span>
                            </div>
                            <button type="submit" class="x-btn-submit" id="btnPostar" style="padding: 0.6rem 1.5rem; border-radius: 9999px; font-weight: 900; transition: var(--transition);">Postar</button>
                        </div>
                    </form>
                </div>

                <!-- Feed Items -->
                <div id="postsFeed">
                <?php if (empty($posts)): ?>
                    <div style="text-align:center; padding:3rem; color:var(--gray-500);">
                        <h2>Bem-vindo à sua timeline</h2>
                        <p style="margin-top:0.5rem;">As melhores conversas da eleição acontecem aqui. Seja o primeiro a partilhar algo!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $idx => $post): ?>
                        <div class="x-feed-item slide-up stagger-<?= ($idx % 4) + 1 ?>" data-id="<?= $post['id'] ?>">
                            <a href="perfil.php?id=<?= $post['user_id'] ?>" class="x-composer-avatar" style="text-decoration:none;">
                                <?= strtoupper(substr($post['cand_nome'] ?? $post['organizador_nome'] ?? 'V', 0, 1)) ?>
                            </a>
                            <div class="x-feed-content">
                                <div class="x-feed-header">
                                    <a href="perfil.php?id=<?= $post['user_id'] ?>" class="x-feed-name">
                                        <?= htmlspecialchars($post['cand_nome'] ?? $post['organizador_nome'] ?? 'Organizador Vox') ?>
                                    </a>
                                    <?php if ($post['candidato_id']): ?>
                                        <i class="fa fa-check-circle" style="color:var(--primary); font-size: 1.1rem;" title="Candidato Verificado"></i>
                                    <?php else: ?>
                                        <i class="fa fa-check-circle" style="color:var(--gray-500); font-size: 1.1rem;" title="Organizador Oficial"></i>
                                    <?php endif; ?>
                                    <span class="x-feed-username">@<?= strtolower(str_replace(' ', '', $post['cand_nome'] ?? $post['organizador_nome'] ?? 'user')) ?></span>
                                    <span style="color:var(--gray-500);">·</span>
                                    <span class="x-feed-time"><?= date('M j', strtotime($post['criado_em'])) ?></span>
                                    
                                    <?php if ($post['user_id'] != $userId && !in_array($post['user_id'], $followedIds)): ?>
                                        <button class="btn-follow-mini" onclick="toggleFollow(this, <?= $post['user_id'] ?>)" style="margin-left:auto; background:var(--gray-900); color:white; border:none; padding:0.2rem 0.8rem; border-radius:999px; font-size:0.75rem; font-weight:700; cursor:pointer;">Seguir</button>
                                    <?php endif; ?>
                                </div>
                                <div class="x-feed-text">
                                    <?= nl2br(htmlspecialchars($post['conteudo'])) ?>
                                </div>
                                <?php if (!empty($post['imagem'])): ?>
                                    <div class="x-feed-media">
                                        <img src="uploads/campaign/<?= htmlspecialchars($post['imagem']) ?>" alt="Post Media" loading="lazy">
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($post['video_url'])): ?>
                                    <div class="x-feed-media">
                                        <video controls style="width: 100%; border-radius: 1rem; background: #000;">
                                            <source src="uploads/campaign/<?= htmlspecialchars($post['video_url']) ?>" type="video/mp4">
                                            O seu navegador não suporta vídeos.
                                        </video>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($post['audio_url'])): ?>
                                    <div class="x-feed-media" style="padding: 1rem; background: var(--bg-body); border-radius: 1rem;">
                                        <audio controls style="width: 100%;">
                                            <source src="uploads/campaign/<?= htmlspecialchars($post['audio_url']) ?>" type="audio/mpeg">
                                            O seu navegador não suporta áudio.
                                        </audio>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="x-feed-actions">
                                    <button class="x-action-btn" onclick="toggleComments(<?= $post['id'] ?>)">
                                        <div class="icon-wrap"><i class="fa fa-comment-o"></i></div>
                                        <span><?= $post['total_comentarios'] > 0 ? $post['total_comentarios'] : '' ?></span>
                                    </button>
                                    <button class="x-action-btn <?= $post['user_reacao'] === 'adorado' ? 'adorado' : '' ?>" 
                                            onclick="reactPost(<?= $post['id'] ?>, 'adorado', this, event)" title="Adorar">
                                        <div class="icon-wrap"><i class="fa <?= $post['user_reacao'] === 'adorado' ? 'fa-heart' : 'fa-heart-o' ?>"></i></div>
                                        <span class="count"><?= $post['total_adorados'] > 0 ? $post['total_adorados'] : '' ?></span>
                                    </button>
                                    <button class="x-action-btn <?= $post['user_reacao'] === 'hater' ? 'hater' : '' ?>" 
                                            onclick="reactPost(<?= $post['id'] ?>, 'hater', this, event)" title="Hater">
                                        <div class="icon-wrap"><i class="fa <?= $post['user_reacao'] === 'hater' ? 'fa-thumbs-down' : 'fa-thumbs-o-down' ?>"></i></div>
                                        <span class="count"><?= $post['total_haters'] > 0 ? $post['total_haters'] : '' ?></span>
                                    </button>
                                    <button class="x-action-btn retweet-btn" 
                                            style="color: <?= $post['user_retweeted'] ? 'var(--success)' : '' ?>;"
                                            onclick="retweetPost(<?= $post['id'] ?>, this, event)" title="Republicar">
                                        <div class="icon-wrap"><i class="fa fa-retweet"></i></div>
                                        <span class="count"><?= $post['total_retweets'] > 0 ? $post['total_retweets'] : '' ?></span>
                                    </button>
                                    <button class="x-action-btn" onclick="openReportModal(<?= $post['id'] ?>, 'esta publicação', 'post')" title="Denunciar">
                                        <div class="icon-wrap"><i class="fa fa-flag-o"></i></div>
                                    </button>
                                    <?php if ($post['user_id'] != $userId): ?>
                                    <button class="x-action-btn" onclick="openChat(<?= $post['user_id'] ?>, '<?= htmlspecialchars(addslashes($post['author_nome'])) ?>'); switchSocialTab('mensagens', document.getElementById('nav-mensagens'))" title="Mensagem Direta">
                                        <div class="icon-wrap"><i class="fa fa-envelope-o"></i></div>
                                    </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Comments Section (Hidden) -->
                                <div class="comments-container" id="comments-<?= $post['id'] ?>" style="display:none; margin-top:1rem;">
                                    <div class="comments-list"></div>
                                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem; align-items: center;">
                                        <div class="x-composer-avatar" style="width:30px; height:30px; font-size:1rem;"><?= strtoupper(substr($_SESSION['user_nome'] ?? 'V',0,1)) ?></div>
                                        <input type="text" class="form-control" style="flex-grow:1; border-radius:9999px; background:var(--gray-100); border:none; padding:0.75rem 1rem;" placeholder="Postar a sua resposta" id="input-comment-<?= $post['id'] ?>">
                                        <button class="x-btn-submit" style="padding:0.5rem 1rem; font-size:0.9rem;" onclick="sendComment(<?= $post['id'] ?>)">Responder</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>

            <!-- SUB-TAB: MENSAGENS -->
            <div id="social-tab-mensagens" class="social-tab-panel">
                <div class="x-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Mensagens</span>
                    <i class="fa fa-cog" style="font-size:1.1rem; cursor:pointer; color:var(--gray-500);"></i>
                </div>
                <div class="dm-container" style="display:flex; height: calc(100vh - 150px); background: #fff;">
                    <div class="dm-sidebar" style="width:350px; border-right:1px solid var(--border-color); overflow-y:auto; display:flex; flex-direction:column;">
                        <div style="padding:1rem;">
                            <input type="text" style="width:100%; border-radius:9999px; border:1px solid var(--border-color); padding:0.75rem 1rem; background:var(--gray-50); outline:none;" placeholder="Procurar mensagens diretas">
                        </div>
                        <div id="dm-contacts-list" style="flex-grow:1; overflow-y:auto;">
                            <!-- Contacts will be loaded here -->
                            <div style="text-align:center; padding:2rem; color:var(--gray-500);">A carregar...</div>
                        </div>
                    </div>
                    <div id="dm-chat-window" style="flex-grow:1; display:flex; flex-direction:column;">
                        <div style="flex-grow:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:2rem; text-align:center;">
                            <h2 style="font-weight:900; margin-bottom:0.5rem;">Selecione uma mensagem</h2>
                            <p style="color:var(--gray-500); max-width:300px; line-height:1.5;">Escolha uma das suas conversas existentes, inicie uma nova ou continue a debater.</p>
                            <button class="x-btn-submit" style="margin-top:1.5rem; font-size:1.1rem; padding:0.75rem 2rem;">Nova mensagem</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUB-TAB: VOTAÇÃO (E-Commerce Style) -->
            <div id="social-tab-eleicao" class="social-tab-panel <?= ($defaultTab === 'eleicao') ? 'active' : '' ?>">
                <div class="x-header">Votação Oficial</div>
                <div style="padding: 1.5rem;">

                    <?php if ($jaVotou): ?>
                    <div style="display:flex;align-items:center;gap:1rem;padding:1.25rem 1.5rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:1.25rem;margin-bottom:1.5rem;">
                        <div style="width:48px;height:48px;background:#10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:white;flex-shrink:0;">✓</div>
                        <div>
                            <div style="font-weight:800;color:#065f46;">Voto Registado com Sucesso!</div>
                            <div style="font-size:0.85rem;color:#047857;">O seu sufrágio foi contabilizado de forma segura e anónima.</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="voting-phase-gate" style="display:none;text-align:center;padding:3rem;color:var(--gray-500);">
                        <div style="font-size:3rem;margin-bottom:1rem;">🔒</div>
                        <h3 style="margin-bottom:0.5rem;">As urnas ainda não estão abertas</h3>
                        <p>A votação começa em <strong id="gate-votacao-time"></strong></p>
                    </div>

                    <div id="voting-content">
                    <?php foreach ($temasData as $tIdx => $data): ?>
                        <?php $t = $data['tema']; $cands = $data['candidates']; ?>
                        <div style="margin-bottom:2.5rem;">
                            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
                                <div style="width:36px;height:36px;background:var(--primary);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;"><?= $tIdx+1 ?></div>
                                <div>
                                    <h3 style="font-weight:900;font-size:1.15rem;"><?= htmlspecialchars($t['titulo']) ?></h3>
                                    <?php if ($t['descricao']): ?>
                                    <p style="color:var(--gray-500);font-size:0.85rem;"><?= htmlspecialchars($t['descricao']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($t['tipo_votacao'] === 'sim_nao'): ?>
                            <?php $res = $data['results']; ?>
                            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                                <button class="ecomm-vote-btn <?= $jaVotou?'voted disabled':'' ?>" style="flex:1;padding:1.5rem;background:#10b981;" onclick="castSimNaoVote(<?= $t['id'] ?>,'sim',this)" <?= $jaVotou?'disabled':'' ?>>Sim</button>
                                <button class="ecomm-vote-btn <?= $jaVotou?'voted disabled':'' ?>" style="flex:1;padding:1.5rem;background:#ef4444;" onclick="castSimNaoVote(<?= $t['id'] ?>,'nao',this)" <?= $jaVotou?'disabled':'' ?>>Não</button>
                                <button class="ecomm-vote-btn disabled" style="flex:1;padding:1.5rem;" disabled>Abstenção</button>
                            </div>
                            <?php if ($res && $res['total']>0): ?>
                            <div style="margin-top:1rem;background:var(--gray-50);border-radius:1rem;padding:1rem;">
                                <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:var(--gray-500);margin-bottom:0.5rem;"><span>Sim: <?= $res['sim_pct'] ?>%</span><span>Não: <?= $res['nao_pct'] ?>%</span></div>
                                <div class="cand-stats-bar"><div class="cand-stats-bar-fill" style="width:<?= $res['sim_pct'] ?>%;background:#10b981;"></div></div>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="ecomm-cand-grid">
                                <?php foreach ($cands as $idx => $c):
                                    $pct = ($data['total_votos']>0)?round(($c['votos_cand']/$data['total_votos'])*100,1):0;
                                    $colors=['#3b82f6','#8b5cf6','#10b981','#ef4444','#f59e0b','#06b6d4'];
                                    $color=$colors[abs(crc32($c['nome']))%count($colors)];
                                ?>
                                <div class="ecomm-cand-card scale-in hover-glow stagger-<?= ($idx % 4) + 1 ?>" id="cand-card-<?= $c['id'] ?>" style="border: 1px solid var(--border-color); background: var(--bg-card); overflow: hidden; border-radius: 20px; transition: var(--transition);">
                                    <div class="ecomm-cand-photo" style="height: 220px; background: linear-gradient(135deg,<?= $color ?>,<?= $color ?>aa); display: flex; align-items: center; justify-content: center; font-size: 4rem; color: white; position: relative;">
                                        <?php if(!empty($c['foto'])): ?>
                                            <img src="uploads/candidatos/<?= htmlspecialchars($c['foto']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($c['nome'],0,1)) ?>
                                        <?php endif; ?>
                                        <?php if($c['votos_cand']>0): ?>
                                            <div class="ecomm-cand-badge" style="position: absolute; top: 1rem; right: 1rem; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); color: white; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800;">
                                                <i class="fa fa-chart-bar"></i> <?= $c['votos_cand'] ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 50%; background: linear-gradient(to top, rgba(0,0,0,0.4), transparent); pointer-events: none;"></div>
                                    </div>
                                    <div class="ecomm-cand-body" style="padding: 1.5rem;">
                                        <div class="ecomm-cand-name" style="font-weight: 900; font-size: 1.25rem; margin-bottom: 0.25rem; color: var(--text-header);"><?= htmlspecialchars($c['nome']) ?></div>
                                        <?php if(!empty($c['partido'])): ?>
                                            <div class="ecomm-cand-party" style="color: var(--primary); font-weight: 800; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.75rem;"><?= htmlspecialchars($c['partido']) ?></div>
                                        <?php endif; ?>
                                        <?php if(!empty($c['slogan'])): ?>
                                            <div class="ecomm-cand-slogan" style="font-style: italic; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 2.6rem;">"<?= htmlspecialchars($c['slogan']) ?>"</div>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-primary w-100 <?= ($jaVotou || (!$isOrganizer && in_array($sala['estado'], ['finalizada', 'cancelada', 'pausada']))) ? 'voted disabled' : '' ?>" id="vote-btn-<?= $c['id'] ?>"
                                                style="padding: 0.85rem; border-radius: 12px; font-weight: 900; letter-spacing: 0.02em;"
                                                onclick="castVote(<?= $t['id'] ?>,<?= $c['id'] ?>,'<?= htmlspecialchars($c['nome'],ENT_QUOTES) ?>',this)"
                                                <?= ($jaVotou || (!$isOrganizer && in_array($sala['estado'], ['finalizada', 'cancelada', 'pausada']))) ? 'disabled' : '' ?>>
                                            <?= $jaVotou ? '✓ Voto registado' : '🗳️ Confirmar Escolha' ?>
                                        </button>
                                        
                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem; border-top: 1px solid var(--gray-100); padding-top: 1rem;">
                                            <button class="ecomm-report-btn" style="background: none; border: none; color: var(--gray-400); font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;" onclick="openReportModal(<?= $c['id'] ?>,'<?= htmlspecialchars($c['nome'],ENT_QUOTES) ?>')">
                                                <i class="fa fa-flag"></i> Reportar
                                            </button>
                                            <?php if($jaVotou||in_array($sala['estado'],['finalizada','cancelada'])): ?>
                                                <span style="font-size:0.85rem;color:var(--primary);font-weight:900;"><?= $pct ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- SUB-TAB: ESTATÍSTICAS -->
            <div id="social-tab-estatisticas" class="social-tab-panel <?= ($defaultTab === 'estatisticas') ? 'active' : '' ?>">
                <div class="x-header">Insights &amp; Estatísticas</div>
                <div style="padding:1.5rem;">
                    <div id="stats-phase-gate" style="display:none;text-align:center;padding:3rem;color:var(--gray-500);">
                        <div style="font-size:3rem;margin-bottom:1rem;">📊</div>
                        <h3 style="margin-bottom:0.5rem;">Resultados disponíveis após a votação</h3>
                        <p>Os insights serão revelados quando as urnas encerrarem.</p>
                    </div>
                    <div id="stats-content">
                    <?php
                    $mStmt=$pdo->prepare("SELECT COUNT(*) FROM sala_membros WHERE sala_id=?");
                    $mStmt->execute([$salaId]);
                    $nMembros=(int)$mStmt->fetchColumn();
                    $participacaoPct=$nMembros>0?round(($totalVotosGeneral/$nMembros)*100):0;
                    ?>
                    <div class="stat-participation-ring">
                        <div class="stat-kpi"><div class="stat-kpi-val"><?= (int)$totalVotosGeneral ?></div><div class="stat-kpi-label">Votos Totais</div></div>
                        <div class="stat-kpi"><div class="stat-kpi-val"><?= $nMembros ?></div><div class="stat-kpi-label">Participantes</div></div>
                        <div class="stat-kpi"><div class="stat-kpi-val" style="color:<?= $participacaoPct>=50?'#10b981':'#ef4444' ?>"><?= $participacaoPct ?>%</div><div class="stat-kpi-label">Taxa de Participação</div></div>
                        <div class="stat-kpi"><div class="stat-kpi-val"><?= count($posts) ?></div><div class="stat-kpi-label">Posts de Campanha</div></div>
                    </div>
                    <?php foreach($temasData as $tIdx=>$data): ?>
                    <?php
                        $t=$data['tema']; $cands=$data['candidates']; $totalV=(int)$data['total_votos'];
                        $winner=!empty($cands)?$cands[0]:null;
                        $faseAtualSala=$sala['fase_atual']??'aguardando';
                    ?>
                    <div style="margin-bottom:2rem;">
                        <h3 style="font-weight:900;margin-bottom:1rem;font-size:1.1rem;"><?= htmlspecialchars($t['titulo']) ?></h3>
                        <?php if($winner&&$winner['votos_cand']>0&&(in_array($faseAtualSala,['estatisticas','encerrada','arquivada'])||in_array($sala['estado'],['finalizada','cancelada']))): ?>
                        <div class="winner-banner" style="margin-bottom:1.5rem;">
                            <div class="winner-trophy">🏆</div>
                            <div>
                                <div style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.1em;opacity:0.8;">Vencedor</div>
                                <div style="font-size:1.5rem;font-weight:900;"><?= htmlspecialchars($winner['nome']) ?></div>
                                <?php if($winner['partido']): ?><div style="opacity:0.9;font-size:0.9rem;"><?= htmlspecialchars($winner['partido']) ?></div><?php endif; ?>
                            </div>
                            <div style="margin-left:auto;text-align:right;">
                                <div style="font-size:2rem;font-weight:900;"><?= $winner['votos_cand'] ?></div>
                                <div style="font-size:0.8rem;opacity:0.8;">votos (<?= $totalV>0?round($winner['votos_cand']/$totalV*100,1):0 ?>%)</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php foreach($cands as $rank=>$c):
                            $pct=$totalV>0?round($c['votos_cand']/$totalV*100,1):0;
                            $rxStmt=$pdo->prepare("SELECT pr.tipo,COUNT(*) n FROM post_reacoes pr JOIN campanhas cp ON pr.post_id=cp.id WHERE cp.sala_id=? AND cp.candidato_id=? GROUP BY pr.tipo");
                            $rxStmt->execute([$salaId,$c['id']]);
                            $rx=$rxStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        ?>
                        <div class="cand-stats-row" id="stat-row-c-<?= $c['id'] ?>">
                            <div style="width:40px;height:40px;background:var(--primary);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:0.9rem;flex-shrink:0;"><?= $rank+1 ?></div>
                            <div class="cand-stats-bar-wrap">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <strong><?= htmlspecialchars($c['nome']) ?></strong>
                                    <span class="stat-count" style="font-weight:900;color:var(--primary);"><?= $c['votos_cand'] ?> votos</span>
                                </div>
                                <div class="cand-stats-bar"><div class="cand-stats-bar-fill" style="width:<?= $pct ?>%;"></div></div>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.35rem;">
                                    <div class="reaction-pills">
                                        <?php if(!empty($rx['like'])||!empty($rx['heart'])): ?><span class="reaction-pill pill-like">❤ <?= ($rx['like']??0)+($rx['heart']??0) ?></span><?php endif; ?>
                                        <?php if(!empty($rx['hate'])): ?><span class="reaction-pill pill-hate">👎 <?= $rx['hate'] ?></span><?php endif; ?>
                                    </div>
                                    <span class="stat-pct" style="font-size:0.8rem;color:var(--gray-500);"><?= $pct ?>%</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- SUB-TAB: AUDITORIA -->
            <?php if($isOrganizer): ?>
            <div id="social-tab-audit" class="social-tab-panel">
                <div class="x-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Rastro de Auditoria (S.T.E.)</span>
                    <a href="api/results.php?action=export&sala_id=<?= $salaId ?>" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem; border-radius:9999px;">
                        <i class="fa fa-download"></i> Exportar CSV
                    </a>
                </div>
                <div style="padding:1.5rem;">
                    <p style="font-size:0.85rem; color:var(--gray-500); margin-bottom:1.5rem;">
                        Fluxo de integridade em tempo real. Cada entrada representa um voto encriptado e validado pelo sistema.
                    </p>
                    <div id="audit-trail-list" style="display:flex; flex-direction:column; gap:0.75rem;">
                        <div style="text-align:center; color:var(--gray-500); padding:2rem;">A carregar rastro de segurança...</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- SUB-TAB: REPORTS -->
            <?php if($isOrganizer): ?>
            <div id="social-tab-reports" class="social-tab-panel">
                <div class="x-header">Denúncias <span style="background:var(--danger);color:white;border-radius:9999px;padding:0.1rem 0.6rem;font-size:0.75rem;margin-left:0.5rem;" id="reports-count-badge"></span></div>
                <div style="padding:1.5rem;" id="reports-list">
                    <div style="text-align:center;color:var(--gray-500);padding:2rem;">A carregar denúncias...</div>
                </div>
            </div>
            <?php endif; ?>

        </main>


        <!-- Right Column (Twitter Sidebar Suggestions/Search) -->
        <aside class="hub-sidebar-column">
            <div style="position: sticky; top: 2rem;">
                <div class="x-sidebar-widget" style="padding:0; overflow:hidden;">
                    <div style="padding: 0.75rem 1.25rem;">
                        <input type="text" id="feedSearchInput" style="width:100%; border-radius:9999px; border:none; padding:0.75rem 1rem; background:var(--gray-200); outline:none;" placeholder="Buscar na Vox">
                    </div>
                </div>
                
                <div class="x-sidebar-widget">
                    <h3 class="x-widget-title">Sobre a Sala</h3>
                    <p style="font-size: 0.95rem; color:var(--gray-500); margin-bottom: 1rem;"><?= htmlspecialchars($sala['descricao'] ?: 'Esta sala ainda não possui uma descrição oficial.') ?></p>
                    <div style="display:flex; gap:1.5rem; margin-bottom:1rem;">
                        <div><strong style="color:var(--gray-900);"><?= count($temasData) ?></strong> <span style="color:var(--gray-500); font-size:0.9rem;">Temas</span></div>
                        <div><strong style="color:var(--gray-900);"><?= (int)$totalVotosGeneral ?></strong> <span style="color:var(--gray-500); font-size:0.9rem;">Votos</span></div>
                    </div>
                    <div style="font-size: 0.85rem; color:var(--gray-500);">
                        <i class="fa fa-calendar-o"></i> Criada em <?= formatDate($sala['criado_em']) ?>
                    </div>
                    
                    <?php if ($candidateProfile): ?>
                    <button class="btn btn-outline-primary w-100 mt-3" style="border-radius: 9999px; font-weight: 800; font-size: 0.9rem;" onclick="document.getElementById('modalEditCandidate').style.display='flex'">
                        <i class="fa fa-id-card-o"></i> Editar Perfil Candidato
                    </button>
                    <?php endif; ?>

                    <?php if ($_SESSION['user_role'] === 'admin' || (int)$sala['organizador_id'] === (int)$userId): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <button class="btn btn-outline-danger w-100" style="border-radius: 9999px; font-weight: 800; font-size: 0.85rem; border-color: rgba(239, 68, 68, 0.3); color: #ef4444;" onclick="deleteRoom(event, <?= $salaId ?>)">
                            <i class="fa fa-trash-o"></i> Eliminar Sala
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="x-sidebar-widget" id="widgetQuemSeguir">
                    <h3 class="x-widget-title">Quem seguir</h3>
                    
                    <?php 
                    // Collect all followable unique users in this room
                    $followables = [];
                    // Add organizer if exists and not self
                    if ($sala['organizador_id'] && (int)$sala['organizador_id'] !== (int)$userId) {
                        $followables[(int)$sala['organizador_id']] = [
                            'id' => $sala['organizador_id'],
                            'nome' => $sala['organizador_nome'],
                            'handle' => 'organizador',
                            'is_verified' => true
                        ];
                    }
                    
                    // Add candidates with user_id
                    foreach($temasData as $tData) {
                        foreach($tData['candidates'] as $c) {
                            if (!empty($c['user_id']) && (int)$c['user_id'] !== (int)$userId && !isset($followables[(int)$c['user_id']])) {
                                $followables[(int)$c['user_id']] = [
                                    'id' => $c['user_id'],
                                    'nome' => $c['nome'],
                                    'handle' => $c['user_handle'] ?? strtolower(str_replace(' ', '', $c['nome'])),
                                    'is_verified' => false
                                ];
                            }
                        }
                    }
                    
                    $i = 0;
                    foreach($followables as $fId => $fData): 
                        $isFollowing = in_array($fId, $followedIds);
                        $isHidden = ($i >= 3);
                        $i++;
                    ?>
                    <div class="x-follow-item <?= $isHidden ? 'hidden-follow-item' : '' ?>" style="<?= $isHidden ? 'display:none;' : '' ?>">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="x-composer-avatar" style="width:40px;height:40px; background:var(--gray-300); color:var(--gray-900);"><?= strtoupper(substr($fData['nome'],0,1)) ?></div>
                            <div style="line-height:1.2; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <div style="display:flex; align-items:center; gap:0.25rem;">
                                    <strong style="color:var(--gray-900); font-size:0.95rem;"><?= htmlspecialchars($fData['nome']) ?></strong>
                                    <?php if($fData['is_verified']): ?><i class="fa fa-check-circle" style="color:var(--primary);"></i><?php endif; ?>
                                </div>
                                <div style="color:var(--gray-500); font-size:0.85rem;">@<?= htmlspecialchars($fData['handle']) ?></div>
                            </div>
                        </div>
                        <button class="x-btn-follow <?= $isFollowing ? 'following' : '' ?>" 
                                onclick="toggleFollow(this, <?= $fId ?>)"
                                style="<?= $isFollowing ? 'background:transparent; color:var(--gray-900); border:1px solid var(--border-color);' : '' ?>">
                            <?= $isFollowing ? 'A seguir' : 'Seguir' ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if(count($followables) > 3): ?>
                    <div id="btnMostrarMaisSiga" onclick="mostrarMaisSeguir()" style="padding-top:1rem; cursor:pointer; color:var(--primary); font-size:0.95rem;">Mostrar mais</div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>

    <!-- TAB 2: ELEIÇÃO (Ballot Accordion) -->
    <section id="tab-eleicao" class="hub-tab-content" style="display:none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="font-weight: 800;">Boletim de Voto Oficial</h2>
            <?php if (!$jaVotou && $sala['estado'] === 'ativa'): ?>
                <a href="votar.php?sala=<?= $salaId ?>" class="btn btn-primary btn-pulse">Votar Agora</a>
            <?php elseif ($jaVotou): ?>
                <span class="badge badge-ativa" style="padding: 1rem;">O seu voto foi registado com sucesso ✓</span>
            <?php endif; ?>
        </div>
        
        <div class="accordion-container">
            <?php foreach ($temasData as $idx => $data): ?>
                <?php $t = $data['tema']; $cands = $data['candidates']; $res = $data['results']; ?>
                <div class="accordion-item <?= $idx === 0 ? 'active' : '' ?>">
                    <div class="accordion-header" onclick="this.parentElement.classList.toggle('active')">
                        <div style="display: flex; align-items: center; gap: 1.5rem;">
                            <span class="avatar-circle" style="width:32px; height:32px; font-size: 0.8rem;"><?= $idx + 1 ?></span>
                            <div>
                                <h3 style="font-size: 1.15rem; font-weight: 800;"><?= htmlspecialchars($t['titulo']) ?></h3>
                                <span class="badge badge-info"><?= $t['tipo_votacao'] === 'sim_nao' ? 'Consulta' : 'Eleição' ?></span>
                            </div>
                        </div>
                        <span class="arrow-icon">▼</span>
                    </div>
                    <div class="accordion-content">
                        <?php if ($t['tipo_votacao'] === 'sim_nao'): ?>
                            <p style="margin-bottom: 1.5rem;"><?= htmlspecialchars($t['descricao']) ?></p>
                            <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 1rem;">
                                <span>Acompanhamento em direto:</span>
                                <div style="display: flex; justify-content: space-between; margin-top: 1rem;">
                                    <strong>Sim: <?= (int)$res['sim'] ?></strong>
                                    <strong>Não: <?= (int)$res['nao'] ?></strong>
                                </div>
                                <div style="height: 10px; background: var(--gray-200); border-radius: 5px; overflow: hidden; margin-top: 0.5rem; display: flex;">
                                    <div style="width: <?= $res['sim_pct'] ?>%; background: var(--success);"></div>
                                    <div style="width: <?= $res['nao_pct'] ?>%; background: var(--danger);"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="cand-grid">
                                <?php foreach ($cands as $c): ?>
                                    <div class="cand-stat-card">
                                        <div class="cand-avatar"><?= strtoupper(substr($c['nome'], 0, 1)) ?></div>
                                        <div>
                                            <div style="font-weight: 800; font-size: 0.95rem;"><?= htmlspecialchars($c['nome']) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($c['partido'] ?: 'Independente') ?></div>
                                        </div>
                                        <div class="cand-val">
                                            <div style="font-weight: 900; font-size: 1.1rem; color: var(--primary);"><?= $c['votos_cand'] ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- TAB 3: ESTATÍSTICAS (Analytics) -->
    <section id="tab-estatisticas" class="hub-tab-content" style="display:none;">
        <div class="live-stats-header">
            <div>
                <h2 style="font-weight: 800;">Análise em Tempo Real</h2>
                <p style="color:var(--text-muted);">Métricas consolidadas de participação e engajamento.</p>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--gray-100); padding: 0.5rem 1rem; border-radius: 99px;">
                <div class="pulse-indicator"></div>
                <span style="font-size: 0.8rem; font-weight: 800;">LIVE</span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa fa-users"></i></div>
                <div class="stat-info">
                    <h3><?= $totalVotosGeneral ?></h3>
                    <p>Total de Votos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa fa-bullhorn"></i></div>
                <div class="stat-info">
                    <h3><?= count($posts) ?></h3>
                    <p>Posts na Campanha</p>
                </div>
            </div>
            <?php 
                $avgInteractions = count($posts) > 0 ? round(array_sum(array_column($posts, 'reacoes')) / count($posts), 1) : 0;
            ?>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fa fa-fire"></i></div>
                <div class="stat-info">
                    <h3><?= $avgInteractions ?></h3>
                    <p>Média de Engajamento</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-panel" style="padding: 2.5rem; margin-top: 2rem;">
            <h3>Engajamento por Tema</h3>
            <canvas id="engagementChart" style="max-height: 300px; width: 100%; margin-top: 2rem;"></canvas>
        </div>
    </section>

</div>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.hub-tab-content').forEach(c => c.style.display = 'none');
    document.querySelectorAll('.hub-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabId).style.display = 'block';
    btn.classList.add('active');
}

async function reactPost(postId, type, btn, ev) {
    try {
        const data = await window.apiCall('api/interactions.php', {
            body: new URLSearchParams({ action: 'add_reaction', post_id: postId, tipo: type })
        });
        
        if (data.success) {
            const countSpan = btn.querySelector('.count');
            const currentVal = parseInt(countSpan.textContent) || 0;
            countSpan.textContent = data.action === 'added' ? currentVal + 1 : Math.max(0, currentVal - 1);
            btn.classList.toggle('liked', data.action === 'added');
            
            if (data.action === 'added') {
                const floating = document.createElement('div');
                floating.className = 'reaction-float';
                floating.innerHTML = type === 'adorado' ? '❤️' : '👎';
                floating.style.left = (ev?.clientX || btn.getBoundingClientRect().left) + 'px';
                floating.style.top = (ev?.clientY || btn.getBoundingClientRect().top) + 'px';
                document.body.appendChild(floating);
                setTimeout(() => floating.remove(), 1000);
            }
        }
    } catch (e) {
        window.showErrorToast('Erro ao reagir: ' + e.message);
    }
}

function toggleComments(postId) {
    const container = document.getElementById('comments-' + postId);
    const list = container.querySelector('.comments-list');
    
    if (container.style.display === 'block') {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    list.innerHTML = '<p style="text-align:center; padding:1rem; color:var(--text-muted);">A carregar conversas...</p>';
    
    fetch(`api/interactions.php?action=fetch_comments&post_id=${postId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.comments.length === 0) {
                    list.innerHTML = '<p style="text-align:center; padding:1rem; color:var(--text-muted); font-size:0.9rem;">Nenhuma conversa iniciada. Seja o primeiro!</p>';
                } else {
                    list.innerHTML = '';
                    // Simple Nesting: Parents first, then children
                    const parents = data.comments.filter(c => !c.parent_id);
                    const children = data.comments.filter(c => c.parent_id);
                    
                    parents.forEach(c => {
                        list.innerHTML += renderComment(c, false);
                        const replies = children.filter(child => child.parent_id == c.id);
                        replies.forEach(r => {
                            list.innerHTML += renderComment(r, true);
                        });
                    });
                }
            }
        });
}

function renderComment(c, isReply) {
    const avatar = (c.nome_completo || 'U').charAt(0).toUpperCase();
    return `
        <div class="comment-item ${isReply ? 'comment-reply' : ''}" style="${isReply ? 'margin-left: 2.5rem; border-left: 2px solid var(--border-color); padding-left: 1rem;' : ''}">
            <div style="display: flex; gap: 0.75rem;">
                <a href="perfil.php?id=${c.user_id}" class="avatar-link">
                    <div class="avatar-circle" style="width:30px; height:30px; font-size:0.8rem;">${avatar}</div>
                </a>
                <div style="flex-grow: 1;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <a href="perfil.php?id=${c.user_id}" style="text-decoration:none; color:inherit;">
                            <strong style="font-size: 0.85rem;">${c.nome_completo}</strong>
                        </a>
                        <span style="font-size: 0.7rem; color: var(--text-muted);">${formatJSDate(c.criado_em)}</span>
                    </div>
                    <p style="font-size: 0.9rem; margin: 0.25rem 0;">${c.conteudo}</p>
                    ${!isReply ? `<button class="btn-text" onclick="prepareReply(${c.campanha_id}, ${c.id}, '${c.nome_completo}')" style="font-size: 0.75rem; color: var(--primary); font-weight: 700;">Responder</button>` : ''}
                </div>
            </div>
        </div>
    `;
}

function formatJSDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-PT') + ' ' + d.toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'});
}

let activeReplyTo = null;

function prepareReply(postId, commentId, userName) {
    activeReplyTo = commentId;
    const input = document.getElementById('input-comment-' + postId);
    if (input) {
        input.value = `@${userName} `;
        input.focus();
    }
}

async function sendComment(postId) {
    const input = document.getElementById('input-comment-' + postId);
    const content = input.value?.trim();
    if (!content) return;
    
    const formData = new FormData();
    formData.append('action', 'add_comment');
    formData.append('post_id', postId);
    formData.append('conteudo', content);
    if (activeReplyTo) formData.append('parent_id', activeReplyTo);
    
    try {
        await window.apiCall('api/interactions.php', { body: formData });
        input.value = '';
        activeReplyTo = null;
        toggleComments(postId); // Refresh
    } catch(e) {}
}

// Duplicate formPost listener removed to prevent SyntaxError

async function toggleFollow(btn, targetId) {
    const isFollowing = btn.classList.contains('following');
    const originalText = btn.textContent;
    
    try {
        const data = await window.apiCall('api/interactions.php', {
            body: new URLSearchParams({ action: 'toggle_follow', target_id: targetId }),
            loadingBtn: btn
        });
        
        btn.classList.toggle('following');
        
        if (isFollowing) {
            btn.textContent = 'Seguir';
            btn.style.background = 'var(--gray-900)';
            btn.style.color = 'white';
            btn.style.border = 'none';
            window.showSuccessToast('Deixou de seguir.');
        } else {
            btn.textContent = 'A seguir';
            btn.style.background = 'transparent';
            btn.style.color = 'var(--gray-900)';
            btn.style.border = '1px solid var(--border-color)';
            window.showSuccessToast('Agora está a seguir!');
        }
    } catch (e) {
        btn.textContent = originalText;
        window.showErrorToast(e.message);
    }
}

async function deleteRoom(e, sid) {
    if (!confirm('🔴 ATENÇÃO: Esta ação ELIMINARÁ permanentemente a sala e todos os dados. Confirma?')) return;
    const btn = e.currentTarget;
    try {
        await window.apiCall('api/interactions.php', {
            body: new URLSearchParams({ action: 'delete_room', sala_id: sid }),
            loadingBtn: btn
        });
        window.showSuccessToast('Sala eliminada!');
        setTimeout(() => window.location.href = 'home.php', 1200);
    } catch (e) {}
}

function switchSocialTab(subTabId, btn) {
    const isOrg = <?= json_encode($isOrganizer) ?>;
    const isAdmin = <?= json_encode($_SESSION['user_role'] === 'admin') ?>;
    const canBypass = isOrg || isAdmin;

    // Phase-based locking for regular users
    if (!canBypass) {
        if (subTabId === 'eleicao' && currentPhase !== 'votacao') {
            window.showErrorToast('A fase de votação ainda não iniciou.');
            return;
        }
        if (subTabId === 'estatisticas' && !['estatisticas', 'encerrada'].includes(currentPhase)) {
            window.showErrorToast('Os resultados serão revelados apenas no final da eleição.');
            return;
        }
        // Audit and Reports are already protected by PHP in the tabs, but let's be safe
        if (['audit', 'reports'].includes(subTabId)) {
            window.showErrorToast('Acesso restrito a organizadores.');
            return;
        }
    }

    const panels = document.querySelectorAll('.social-tab-panel');
    const btns = document.querySelectorAll('.x-nav-item');
    
    panels.forEach(p => {
        if(p.classList.contains('active')) {
            p.style.opacity = '0';
            p.style.transform = 'translateY(10px)';
        }
    });

    setTimeout(() => {
        panels.forEach(p => {
            p.classList.remove('active');
            p.style.display = 'none';
        });
        btns.forEach(b => b.classList.remove('active'));

        const panel = document.getElementById('social-tab-' + subTabId);
        if(panel) {
            panel.style.display = 'block';
            panel.offsetHeight; // force reflow
            panel.classList.add('active');
        }
        if(btn) btn.classList.add('active');

        if (subTabId === 'mensagens') loadDMContacts();
        if (subTabId === 'estatisticas') loadInsights();
        if (subTabId === 'reports' && typeof loadReports === 'function') loadReports();
        if (subTabId === 'audit' && typeof loadAuditTrail === 'function') loadAuditTrail();
        
        document.querySelector('.social-hub-layout').scrollIntoView({ behavior: 'smooth' });
    }, 200);
}

function mostrarMaisSeguir() {
    document.querySelectorAll('.hidden-follow-item').forEach(el => el.style.display = 'flex');
    const btn = document.getElementById('btnMostrarMaisSiga');
    if (btn) btn.style.display = 'none';
}

async function loadAuditTrail() {
    const list = document.getElementById('audit-trail-list');
    if (!list) return;
    
    try {
        const res = await fetch(`api/results.php?action=audit&sala_id=<?= $salaId ?>`);
        const data = await res.json();
        
        if (data.success && data.votes) {
            let html = `
                <div style="font-family:'Courier New', Courier, monospace; background:#0a0a0a; color:#4ade80; padding:1.25rem; border-radius:1rem; font-size:0.85rem; line-height:1.7; max-height:450px; overflow-y:auto; border: 1px solid #333; box-shadow: inset 0 0 10px rgba(0,0,0,0.5);">
                    <div style="color:#666; margin-bottom:0.5rem; border-bottom:1px solid #222; padding-bottom:0.5rem;">
                        [VOX_SECURE_AUDIT_LOG v2.0] - ROOM_ID: <?= $salaId ?><br>
                        TIMESTAMP: ${new Date().toLocaleString()}
                    </div>
            `;
            
            if (data.votes.length === 0) {
                html += `<div style="color:#f87171;">[SYSTEM_INFO] NENHUM VOTO REGISTADO ATÉ AO MOMENTO.</div>`;
            } else {
                data.votes.forEach(v => {
                    html += `
                        <div style="margin-bottom:0.4rem; display:flex; gap:0.75rem;">
                            <span style="color:#3b82f6; flex-shrink:0;">[${v.criado_em.split(' ')[1]}]</span>
                            <span style="color:#9ca3af; flex-shrink:0;">VOTE_VALID:</span>
                            <span style="word-break:break-all; color:#4ade80;">${v.voto_hash}</span>
                        </div>
                    `;
                });
            }
            
            html += `
                    <div style="color:#666; margin-top:1rem; border-top:1px solid #222; padding-top:0.5rem;">
                        [EOF] - TOTAL_RECORDS: ${data.votes.length}
                    </div>
                </div>
            `;
            list.innerHTML = html;
        }
    } catch(e) { 
        console.error('Audit Load Error:', e);
        list.innerHTML = '<div class="alert alert-error">Erro ao carregar rastro de auditoria.</div>';
    }
}

async function loadReports(publicView = false) {
    const list = document.getElementById('reports-list');
    if (!list) return;
    
    list.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--gray-500);">A carregar denúncias...</div>';
    
    try {
        const url = \`api/interactions.php?action=list_reports&sala_id=<?= $salaId ?>&public=\${publicView}\`;
        const res = await fetch(url);
        const data = await res.json();
        
        if (data.success) {
            if (data.reports.length === 0) {
                list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--gray-500);">Nenhuma denúncia registada.</div>';
                return;
            }
            
            list.innerHTML = data.reports.map(r => \`
                <div class="report-item" style="border-left:4px solid \${r.estado === 'pendente' ? 'var(--danger)' : 'var(--success)'}; padding:1.5rem; margin-bottom:1rem; background:var(--bg-card); border-radius:0.75rem;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                        <div>
                            <strong style="color:\${r.estado === 'pendente' ? 'var(--danger)' : 'var(--success)'};">\${r.estado.toUpperCase()}</strong>
                            <span style="margin-left:1rem; font-weight:700;">\${r.target_name}</span>
                        </div>
                        <span style="font-size:0.8rem; opacity:0.7;">\${new Date(r.criado_em).toLocaleString()}</span>
                    </div>
                    <div style="margin-bottom:0.75rem;">**\${r.motivo}**</div>
                    <p style="color:var(--gray-600); font-size:0.9rem; background:var(--gray-50); padding:1rem; border-radius:0.5rem;">\${r.detalhes || 'Sem detalhes adicionais.'}</p>
                    \${ <?= json_encode($isOrganizer) ?> && r.estado === 'pendente' ? 
                    '<div style="margin-top:1rem; display:flex; gap:0.5rem; justify-content:flex-end;">
                        <button onclick="resolveReport(\${r.id}, \'investigando\')" class="btn btn-sm btn-outline-primary">Investigar</button>
                        <button onclick="resolveReport(\${r.id}, \'resolvido\')" class="btn btn-sm btn-success">Resolver</button>
                    </div>' : ''
                    }
                </div>
            \`).join('');
        }
    } catch(e) {
        console.error('Reports error:', e);
        list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger);">Erro ao carregar denúncias.</div>';
    }
}

// Auto-load reports on tab switch (organizers) + public view support
if (<?= json_encode($isOrganizer) ?>) {
    loadReports();
}


async function retweetPost(postId, btn, ev) {
    const icon = btn.querySelector('i');
    try {
        const data = await window.apiCall('api/interactions.php', {
            body: new URLSearchParams({ action: 'retweet', post_id: postId })
        });
        
        if (data.success) {
            const isRetweeted = data.action === 'retweeted';
            btn.style.color = isRetweeted ? 'var(--success)' : '';
            if (isRetweeted) {
                icon.style.transform = 'rotate(180deg)';
                setTimeout(() => { icon.style.transform = 'rotate(0deg)'; }, 400);
            }
            
            const countSpan = btn.querySelector('span');
            if (countSpan) {
                let count = parseInt(countSpan.textContent) || 0;
                count = isRetweeted ? count + 1 : Math.max(0, count - 1);
                countSpan.textContent = count > 0 ? count : '';
            }
        }
    } catch (e) { 
        window.showErrorToast('Erro ao republicar: ' + e.message);
    }
}

// Removed duplicate reactPost function (now handled by the modern version below)

// MESSAGING ENGINE
async function loadDMContacts() {
    const list = document.getElementById('dm-contacts-list');
    try {
        const res = await fetch(`api/messages.php?action=list_contacts&sala_id=<?= $salaId ?>`);
        const data = await res.json();
        if (data.success) {
            if (data.contacts.length === 0) {
                list.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--gray-500);">Nenhum contacto disponível nesta sala.</div>';
                return;
            }
            list.innerHTML = data.contacts.map(c => `
                <div class="dm-contact-item" onclick="openChat(${c.id}, '${c.nome_completo.replace("'","\\'")}')">
                    <div class="x-composer-avatar" style="width:45px; height:45px; font-size:1.1rem;">
                        ${(c.nome_completo || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div style="flex-grow:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong style="font-size:0.95rem;">${c.nome_completo}</strong>
                            ${c.role === 'candidato' ? '<i class="fa fa-check-circle" style="color:var(--primary); font-size:0.8rem;"></i>' : ''}
                        </div>
                        <div style="color:var(--gray-500); font-size:0.85rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            ${c.ultima_msg || (c.role === 'candidato' ? 'Candidato oficial' : (c.role === 'organizador' ? 'Organizador' : 'Eleitor'))}
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            list.innerHTML = `<div style="text-align:center; padding:2rem; color:var(--danger);">Erro: ${data.message}</div>`;
        }
    } catch (e) { 
        console.error(e); 
        list.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--danger);">Erro ao carregar contactos. Verifique a sua ligação.</div>';
    }
}

let activeChatUser = null;
async function openChat(userId, userName) {
    activeChatUser = userId;
    const window = document.getElementById('dm-chat-window');
    
    // Highlight contact
    document.querySelectorAll('.dm-contact-item').forEach(item => item.classList.remove('active'));
    // We need to find the element that was clicked. Since we use onclick in the string template, we can't easily use 'this'.
    // Better to just refresh the list or use a different approach, but for now let's just update the window.
    
    window.innerHTML = `
        <div class="x-header" style="display:flex; align-items:center; gap:1rem;">
            <div class="x-composer-avatar" style="width:30px; height:30px; font-size:0.8rem;">${userName.charAt(0).toUpperCase()}</div>
            <div>
                <div style="font-weight:bold; font-size:1rem;">${userName}</div>
                <div style="font-size:0.75rem; color:var(--gray-500); font-weight:normal;">Campanha Eleitoral</div>
            </div>
        </div>
        <div class="chat-messages" id="chat-messages-box">
            <div style="text-align:center; padding:2rem; color:var(--gray-500);">A carregar conversa...</div>
        </div>
        <div class="chat-input-area">
            <button class="x-tool-btn"><i class="fa fa-image"></i></button>
            <input type="text" id="dm-input-text" placeholder="Escreva uma mensagem" style="flex-grow:1; border:none; background:var(--gray-100); padding:0.75rem 1.25rem; border-radius:9999px; outline:none;" onkeypress="if(event.key === 'Enter') sendDM()">
            <button class="x-tool-btn" style="color:var(--primary);" onclick="sendDM()"><i class="fa fa-paper-plane"></i></button>
        </div>
    `;

    fetchMessages(userId);
}

async function fetchMessages(otherId) {
    if (activeChatUser !== otherId) return;
    try {
        const res = await fetch(`api/messages.php?action=fetch_convo&with_id=${otherId}`);
        const data = await res.json();
        if (data.success) {
            const box = document.getElementById('chat-messages-box');
            if (data.messages.length === 0) {
                box.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--gray-500);">Ainda não há mensagens. Envie uma proposta de campanha!</div>';
            } else {
                box.innerHTML = data.messages.map(m => `
                    <div class="chat-bubble ${m.remetente_id == <?= $userId ?> ? 'bubble-sent' : 'bubble-received'}">
                        ${m.conteudo}
                    </div>
                `).join('');
                box.scrollTop = box.scrollHeight;
            }
        }
    } catch (e) { console.error(e); }
}

async function sendDM() {
    const input = document.getElementById('dm-input-text');
    const content = input.value.trim();
    if (!content || !activeChatUser) return;

    try {
        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('destinatario_id', activeChatUser);
        formData.append('sala_id', <?= $salaId ?>);
        formData.append('conteudo', content);

        await window.apiCall('api/messages.php', { body: formData });
        input.value = '';
        fetchMessages(activeChatUser);
    } catch (e) {}
}

// LIVE TICKER POLLING
function updateLiveStats() {
    fetch(`api/stats.php?action=room_summary&sala_id=<?= $salaId ?>`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('ticker-total-votos').textContent = data.total_votos;
                document.getElementById('ticker-total-posts').textContent = data.total_posts;
            }
        });
}
setInterval(updateLiveStats, 10000);

// NOTIFICATION POLLING
let lastNotifCount = -1;
async function checkNotifications() {
    try {
        const res = await fetch('api/notifications.php?action=count');
        const data = await res.json();
        if (data.success) {
            if (lastNotifCount !== -1 && data.unread > lastNotifCount) {
                // New notification! Fetch the latest one
                const listRes = await fetch('api/notifications.php?action=list&limit=1');
                const listData = await listRes.json();
                if (listData.success && listData.notifications.length > 0) {
                    showToast(listData.notifications[0]);
                }
            }
            lastNotifCount = data.unread;
        }
    } catch (e) { console.error(e); }
}

function showToast(notif) {
    const container = document.getElementById('notification-toast-container');
    const toast = document.createElement('div');
    toast.className = 'notification-toast';
    toast.style.cursor = 'pointer';
    toast.onclick = () => { if(notif.link) window.location.href = notif.link; };
    toast.innerHTML = `
        <div class="x-composer-avatar" style="width:35px; height:35px; font-size:0.9rem;">
            <i class="fa ${notif.tipo === 'mensagem' ? 'fa-envelope' : 'fa-bell'}"></i>
        </div>
        <div style="flex-grow:1;">
            <div style="font-weight:bold; font-size:0.9rem;">${notif.tipo.charAt(0).toUpperCase() + notif.tipo.slice(1)}</div>
            <div style="font-size:0.85rem; color:var(--gray-600);">${notif.mensagem}</div>
        </div>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

setInterval(checkNotifications, 5000); // Check every 5s
checkNotifications(); // Initial check

// Chart.js Initialization
const chartEl = document.getElementById('engagementChart');
if (chartEl && typeof Chart !== 'undefined') {
    try {
        const ctx = chartEl.getContext('2d');
        new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map('htmlspecialchars', array_column($posts, 'cand_nome'))) ?>,
            datasets: [{
                label: 'Engajamento (Likes/Comentários)',
                data: <?= json_encode(array_map(fn($p) => ($p['total_adorados'] ?? 0) + ($p['total_haters'] ?? 0) + ($p['total_comentarios'] ?? 0), $posts)) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'var(--blue)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
    } catch (e) { console.error("Chart.js failed to initialize:", e); }
}
// ── PHASE MANAGER POLLING & LOCKING ─────────────────────────────────────────
const salaId = <?= $salaId ?>;
const phaseBanner = document.getElementById('phase-banner');
const pIcon = document.getElementById('phase-banner-icon');
const pText = document.getElementById('phase-banner-text');
const pCountdown = document.getElementById('phase-banner-countdown');
const isOrganizer = <?= json_encode($isOrganizer) ?>;
let currentPhase = '<?= $sala['fase_atual'] ?? 'aguardando' ?>';

async function pollPhase() {
    try {
        const res = await fetch(`api/phase_manager.php?sala_id=${salaId}&action=check`);
        const data = await res.json();
        if (data.success && data.fase) {
            const newPhase = data.fase.nome;
            // Detect Phase Change
            if (currentPhase && currentPhase !== newPhase) {
                // If it transitioned to a more advanced phase
                showPhaseTransitionModal(currentPhase, newPhase);
            }
            currentPhase = newPhase;
            updatePhaseUI(data);
        }
    } catch(e) { console.error('Error polling phase', e); }
}

function showPhaseTransitionModal(oldPhase, newPhase) {
    const names = {
        'aguardando': 'Aguardando Início',
        'campanha': 'Fase de Campanha',
        'votacao': 'Votação Aberta',
        'estatisticas': 'Apuração de Resultados',
        'encerrada': 'Eleição Encerrada'
    };
    
    const overlay = document.createElement('div');
    overlay.className = 'vote-confirm-overlay show';
    overlay.style.zIndex = '11000';
    overlay.style.backdropFilter = 'blur(15px)';
    overlay.innerHTML = `
        <div class="vote-confirm-modal" style="text-align:center; padding: 4rem;">
            <div style="font-size:5rem; margin-bottom:2rem; filter: drop-shadow(0 0 10px var(--primary-glow));">⚡</div>
            <h1 style="font-weight:900; margin-bottom:1rem; font-size: 2.5rem; letter-spacing: -1px;">Transição de Fase</h1>
            <p style="color:var(--gray-500); margin-bottom:3rem; font-size: 1.1rem; line-height: 1.6;">
                O sistema evoluiu. A fase <strong>${names[oldPhase] || oldPhase}</strong> foi concluída com sucesso. 
                <br>Bem-vindo à fase: <strong style="color:var(--primary); font-size: 1.4rem;">${names[newPhase] || newPhase}</strong>.
            </p>
            <button class="ecomm-vote-btn" style="background:var(--primary); color:white; padding: 1.25rem 3rem; font-size: 1.1rem;" onclick="location.reload()">Aceder Agora</button>
        </div>
    `;
    document.body.appendChild(overlay);
}

function updatePhaseUI(data) {
    const f = data.fase;
    const isOrg = data.is_organizer;
    
    // Update Banner
    phaseBanner.className = '';
    phaseBanner.classList.add(f.nome);
    phaseBanner.style.display = 'flex';
    
    const formatTime = (s) => {
        if (!s) return '';
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        return `${h}h ${m}m ${sec}s`;
    };

    if (f.nome === 'aguardando') {
        pIcon.textContent = '⏳';
        pText.textContent = 'Eleição em Espera';
        pCountdown.textContent = f.proxima ? `Abertura em: ${formatTime(f.segundos_restantes)}` : 'Aguardando configuração';
        
        if (f.segundos_restantes && f.segundos_restantes > 0) {
            document.getElementById('voteCountdown').style.display = 'flex';
            document.getElementById('cd-title-1').textContent = 'Eleição em Espera';
            document.getElementById('cd-text').textContent = 'Abertura em';
            document.getElementById('cd-indicator').style.color = '#fcd34d'; // yellow
            startVotingCountdown(f.segundos_restantes);
        } else {
            document.getElementById('voteCountdown').style.display = 'none';
            stopVotingCountdown();
        }
    } else if (f.nome === 'campanha') {
        pIcon.textContent = '📢';
        pText.textContent = 'Fase de Campanha Eleitoral';
        pCountdown.textContent = f.proxima ? `Urnas abrem em: ${formatTime(f.segundos_restantes)}` : 'Campanha em curso';
        
        if (f.segundos_restantes && f.segundos_restantes > 0) {
            document.getElementById('voteCountdown').style.display = 'flex';
            document.getElementById('cd-title-1').textContent = 'Fase de Campanha';
            document.getElementById('cd-text').textContent = 'Urnas abrem em';
            document.getElementById('cd-indicator').style.color = '#3b82f6'; // blue
            startVotingCountdown(f.segundos_restantes);
        } else {
            document.getElementById('voteCountdown').style.display = 'none';
            stopVotingCountdown();
        }
    } else if (f.nome === 'votacao') {
        pIcon.textContent = '🗳️';
        pText.textContent = 'Votação Aberta';
        pCountdown.textContent = f.proxima ? `Encerramento em: ${formatTime(f.segundos_restantes)}` : 'Votação em curso';
        
        if (f.segundos_restantes && f.segundos_restantes > 0) {
            document.getElementById('voteCountdown').style.display = 'flex';
            document.getElementById('cd-title-1').textContent = 'Estado das Urnas';
            document.getElementById('cd-text').textContent = 'A Votação termina em';
            document.getElementById('cd-indicator').style.color = '#10b981'; // green
            startVotingCountdown(f.segundos_restantes);
        } else {
            document.getElementById('voteCountdown').style.display = 'none';
            stopVotingCountdown();
        }
    } else if (f.nome === 'estatisticas' || f.nome === 'encerrada') {
        pIcon.textContent = '📊';
        pText.textContent = 'Resultados Finais';
        pCountdown.textContent = 'Votação Encerrada';
        document.getElementById('voteCountdown').style.display = 'none';
        stopVotingCountdown();
    }

    // Automatic reload handled inside the countdown timer to be more exact
    // Tab visibility/locks logic handled in switchSocialTab
}

// Countdown Timer logic for Voting
let cdTimer = null;
let currentSecondsLeft = 0;

function stopVotingCountdown() {
    if (cdTimer) {
        clearInterval(cdTimer);
        cdTimer = null;
    }
}

function startVotingCountdown(secondsLeft) {
    currentSecondsLeft = secondsLeft;
    
    if (cdTimer) {
        // Just sync the time if interval is already running
        return;
    }
    
    updateCountdownUI();
    
    cdTimer = setInterval(() => {
        currentSecondsLeft--;
        
        if (currentSecondsLeft <= 0) {
            stopVotingCountdown();
            document.getElementById('cd-h').textContent = '00';
            document.getElementById('cd-m').textContent = '00';
            document.getElementById('cd-s').textContent = '00';
            document.getElementById('voteCountdown').classList.remove('urgency-pulse');
            
            // Reload to trigger next phase!
            setTimeout(() => location.reload(), 2000);
            return;
        }
        
        updateCountdownUI();
    }, 1000);
}

function updateCountdownUI() {
    // Add urgency class if < 5 minutes
    if (currentSecondsLeft < 5 * 60) {
        document.getElementById('voteCountdown').classList.add('urgency-pulse');
    } else {
        document.getElementById('voteCountdown').classList.remove('urgency-pulse');
    }
    
    const h = Math.floor(currentSecondsLeft / 3600);
    const m = Math.floor((currentSecondsLeft % 3600) / 60);
    const s = Math.floor(currentSecondsLeft % 60);
    
    document.getElementById('cd-h').textContent = h.toString().padStart(2, '0');
    document.getElementById('cd-m').textContent = m.toString().padStart(2, '0');
    document.getElementById('cd-s').textContent = s.toString().padStart(2, '0');
}

// Initial poll and set interval
pollPhase();
setInterval(pollPhase, 30000); // Check phase every 30s

// ── VOTING LOGIC ──────────────────────────────────────────────────────────
function castVote(temaId, candidatoId, candNome, btnEl) {
    if (btnEl.classList.contains('disabled')) return;
    
    // Create Confirm Overlay
    const overlay = document.createElement('div');
    overlay.className = 'vote-confirm-overlay show';
    overlay.innerHTML = `
        <div class="vote-confirm-modal">
            <h2 style="font-weight:900;margin-bottom:1rem;font-size:1.5rem;">Confirmar Voto</h2>
            <p style="color:var(--gray-500);margin-bottom:2rem;">Tem a certeza que deseja votar em <strong style="color:var(--gray-900);">${candNome}</strong>? Esta ação é irreversível e anónima.</p>
            <div style="display:flex;gap:1rem;">
                <button class="ecomm-vote-btn" style="background:var(--gray-200);color:var(--gray-900);" onclick="this.closest('.vote-confirm-overlay').remove()">Cancelar</button>
                <button class="ecomm-vote-btn" style="background:#10b981;" onclick="submitVote(${temaId}, ${candidatoId}, this)">Confirmar Voto</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
}

function castSimNaoVote(temaId, opcao, btnEl) {
    if (btnEl.classList.contains('disabled')) return;
    const overlay = document.createElement('div');
    const label = opcao === 'sim' ? 'SIM' : 'NÃO';
    overlay.className = 'vote-confirm-overlay show';
    overlay.innerHTML = `
        <div class="vote-confirm-modal">
            <h2 style="font-weight:900;margin-bottom:1rem;font-size:1.5rem;">Confirmar Voto</h2>
            <p style="color:var(--gray-500);margin-bottom:2rem;">Tem a certeza que deseja votar <strong>${label}</strong>? Esta ação é irreversível.</p>
            <div style="display:flex;gap:1rem;">
                <button class="ecomm-vote-btn" style="background:var(--gray-200);color:var(--gray-900);" onclick="this.closest('.vote-confirm-overlay').remove()">Cancelar</button>
                <button class="ecomm-vote-btn" style="background:#10b981;" onclick="submitVote(${temaId}, null, this, '${opcao}')">Confirmar Voto</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
}

async function submitVote(temaId, candidatoId, btnEl, opcaoSimNao = '') {
    btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> A processar...';
    btnEl.disabled = true;
    
    try {
        const fd = new FormData();
        fd.append('sala_id', salaId);
        fd.append('tema_id', temaId);
        fd.append('csrf_token', getCSRFToken());
        if (candidatoId) fd.append('candidato_id', candidatoId);
        if (opcaoSimNao) fd.append('opcao_sim_nao', opcaoSimNao);
        
        const res = await fetch('api/votar.php', {
            method: 'POST',
            body: fd
        });
        
        const data = await res.json();
        
        if (data.success) {
            // Success Animation
            const modal = btnEl.closest('.vote-confirm-modal');
            modal.innerHTML = `
                <div class="vote-success-anim">✓</div>
                <h2 style="font-weight:900;margin-bottom:1rem;">Voto Confirmado!</h2>
                <p style="color:var(--gray-500);margin-bottom:1rem;">O seu voto foi registado com sucesso de forma anónima.</p>
                <div style="background:var(--gray-50); padding:1rem; border-radius:1rem; font-family:monospace; font-size:0.75rem; word-break:break-all; margin-bottom:2rem; border:1px solid var(--border-color);">
                    <div style="font-weight:700; color:var(--gray-400); margin-bottom:0.35rem; text-transform:uppercase;">Comprovativo (Hash)</div>
                    ${data.hash}
                </div>
                <button class="ecomm-vote-btn" style="background:var(--primary); color:white;" onclick="location.reload()">Continuar</button>
            `;
        } else {
            alert('Erro ao votar: ' + data.message);
            btnEl.disabled = false;
            btnEl.innerHTML = 'Confirmar Voto';
        }
    } catch (e) {
        console.error(e);
        alert('Erro de rede ao tentar votar.');
        btnEl.disabled = false;
        btnEl.innerHTML = 'Confirmar Voto';
    }
}

// Unified interaction handlers

// Global listeners and utilities (outside of isOrganizer block)
// Campaign Post Submission - ROBUST INLINE HANDLER
window.handlePostSubmit = async function(e, formElement) {
    e.preventDefault(); // CRITICAL: Stop standard form submission
    
    const btn = formElement.querySelector('button[type="submit"]');
    const conteudo = formElement.querySelector('textarea[name="conteudo"]');
    
    // Validate content
    if (!conteudo || !conteudo.value.trim()) {
        if(typeof window.showErrorToast === 'function') window.showErrorToast('Por favor, escreva algo antes de publicar.');
        else alert('Por favor, escreva algo antes de publicar.');
        return false;
    }
    
    try {
        const formData = new FormData(formElement);
        
        // Execute API call
        if(typeof window.apiCall === 'function') {
            await window.apiCall('api/interactions.php', {
                body: formData,
                loadingBtn: btn
            });
            window.showSuccessToast('Publicação enviada!');
        } else {
            // Ultimate fallback if main.js failed
            btn.disabled = true;
            btn.innerText = 'A processar...';
            const res = await fetch('api/interactions.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(!data.success) throw new Error(data.message);
            alert('Publicação enviada!');
        }
        
        formElement.reset();
        const mediaCount = document.getElementById('media-count');
        if (mediaCount) mediaCount.style.display = 'none';
        if(conteudo) conteudo.style.height = 'auto';
        
        // Refresh the feed without full page reload
        if(typeof loadCampaignFeed === 'function') loadCampaignFeed(<?= $salaId ?>);
        else location.reload();
        
    } catch (err) {
        console.error('Post Error:', err);
        if(typeof window.showErrorToast === 'function') window.showErrorToast(err.message || 'Erro ao publicar.');
        else alert(err.message || 'Erro ao publicar.');
        if(btn) { btn.disabled = false; btn.innerText = 'Postar'; }
    }
    
    return false; // Prevent form default action in old browsers
};

// Load campaign feed via AJAX (instead of full page reload)
async function loadCampaignFeed(salaId) {
    try {
        const res = await fetch(`api/interactions.php?action=fetch_posts&sala_id=${salaId}`);
        const data = await res.json();
        if (data.success && data.posts) {
            renderPostsFeed(data.posts);
        }
    } catch (e) {
        console.error('Error loading feed:', e);
        // Fallback to reload
        location.reload();
    }
}

function renderPostsFeed(posts) {
    const container = document.getElementById('postsFeed');
    if (!container) return;
    
    if (!posts || posts.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:3rem; color:var(--gray-500);">
                <h2>Bem-vindo à sua timeline</h2>
                <p style="margin-top:0.5rem;">As melhores conversas da eleição acontecem aqui. Seja o primeiro a partilhar algo!</p>
            </div>
        `;
        return;
    }
    
    // Re-render posts (simple version without full PHP rendering)
    // For now, just reload the page to show new posts
    location.reload();
}

function updateMediaCount(input) {
    const mediaCount = document.getElementById('media-count');
    if (!mediaCount) return;
    
    const files = input.files;
    if (files.length > 0) {
        mediaCount.textContent = 'Ficheiro: ' + files[0].name;
        mediaCount.style.display = 'inline';
    } else {
        mediaCount.style.display = 'none';
    }
}
</script>

<!-- MODAL: EDIT CANDIDATE PROFILE -->
<?php if ($candidateProfile): ?>
<div id="modalEditCandidate" class="vote-confirm-overlay" style="z-index: 10000;">
    <div class="vote-confirm-modal" style="text-align: left; max-width: 500px;">
        <h2 style="font-weight: 900; margin-bottom: 0.5rem;">Editar Perfil de Candidato</h2>
        <p style="color: var(--gray-500); margin-bottom: 2rem; font-size: 0.9rem;">Atualize as informações que os eleitores verão no boletim de voto.</p>
        
        <form id="formEditCandidate" enctype="multipart/form-data">
            <input type="hidden" name="candidate_id" value="<?= $candidateProfile['id'] ?>">
            
            <div class="form-group mb-3">
                <label style="font-weight: 700; font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.4rem; display: block;">Nome do Candidato / Opção</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($candidateProfile['nome']) ?>" required class="form-control" style="border-radius: 0.75rem;">
            </div>
            
            <div class="form-group mb-3">
                <label style="font-weight: 700; font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.4rem; display: block;">Partido / Sigla</label>
                <input type="text" name="partido" value="<?= htmlspecialchars($candidateProfile['partido'] ?? '') ?>" class="form-control" style="border-radius: 0.75rem;">
            </div>
            
            <div class="form-group mb-3">
                <label style="font-weight: 700; font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.4rem; display: block;">Slogan / Descrição Curta</label>
                <textarea name="slogan" class="form-control" style="border-radius: 0.75rem; height: 80px;"><?= htmlspecialchars($candidateProfile['slogan'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group mb-4">
                <label style="font-weight: 700; font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.4rem; display: block;">Foto / Logótipo</label>
                <input type="file" name="foto" accept="image/*" class="form-control" style="border-radius: 0.75rem;">
                <small style="color: var(--gray-400);">Deixe vazio para manter a imagem atual.</small>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="document.getElementById('modalEditCandidate').style.display='none'" class="btn btn-outline-secondary" style="flex: 1; border-radius: 0.75rem;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="flex: 2; border-radius: 0.75rem; font-weight: 800;">Guardar Alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: REPORT / DENUNCIAR -->
<div id="modalReport" class="vote-confirm-overlay" style="z-index: 10001;">
    <div class="vote-confirm-modal" style="text-align: left; max-width: 450px; border-top: 5px solid #ef4444;">
        <h2 style="font-weight: 900; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa fa-flag" style="color:#ef4444;"></i> Denunciar <span id="reportTargetName" style="color:var(--primary);"></span>
        </h2>
        <p style="color: var(--gray-500); margin-bottom: 1.5rem; font-size: 0.9rem;">
            A sua denúncia será analisada pelos <strong>Organizadores do Sistema (Suporte Técnico)</strong> para garantir a integridade da eleição.
        </p>
        
        <form onsubmit="submitReport(event)">
            <input type="hidden" name="target_id" id="reportTargetId">
            <input type="hidden" name="target_type" id="reportTargetType">
            
            <div class="form-group mb-3">
                <label style="font-weight: 700; font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.4rem; display: block;">Motivo da Denúncia</label>
                <select name="motivo" class="form-control" style="border-radius: 0.75rem;" required>
                    <option value="">-- Selecione um motivo --</option>
                    <option value="Fraude Eleitoral">Fraude Eleitoral</option>
                    <option value="Discurso de Ódio">Discurso de Ódio / Ofensa</option>
                    <option value="Informação Falsa">Informação Falsa (Fake News)</option>
                    <option value="Identidade Falsa">Identidade Falsa</option>
                    <option value="Spam / Abuso">Spam / Abuso</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            
            <div class="form-group mb-4">
                <label style="font-weight: 700; font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.4rem; display: block;">Detalhes Adicionais</label>
                <textarea name="detalhes" class="form-control" style="border-radius: 0.75rem; height: 100px;" placeholder="Explique brevemente o que aconteceu..." required></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="document.getElementById('modalReport').style.display='none'" class="btn btn-outline-secondary" style="flex: 1; border-radius: 0.75rem;">Cancelar</button>
                <button type="submit" class="btn btn-danger" style="flex: 2; border-radius: 0.75rem; font-weight: 800; background:#ef4444; border-color:#ef4444;">Enviar Denúncia</button>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
