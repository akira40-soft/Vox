<?php
require_once 'config/helpers.php';

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$pageTitle = 'Vox — Plataforma Eleitoral Moderna e Segura em Angola';
require_once 'includes/header.php';
?>

<style>
/* ── Landing Page Specific Styles ───────────────────── */

/* Hero */
.lp-hero {
    padding: 7rem 5% 5rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
    max-width: 1280px;
    margin: 0 auto;
}
@media (max-width: 860px) {
    .lp-hero { grid-template-columns: 1fr; text-align: center; padding-top: 5rem; }
    .lp-hero-right { display: none; }
}

.lp-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(59, 130, 246, 0.1);
    color: var(--primary);
    border: 1px solid rgba(59, 130, 246, 0.25);
    padding: 0.4rem 1rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 1.75rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.lp-hero h1 {
    font-size: clamp(2.4rem, 5vw, 4rem);
    font-weight: 900;
    line-height: 1.1;
    letter-spacing: -0.03em;
    margin-bottom: 1.5rem;
    color: var(--text-header);
}

.lp-hero h1 .highlight {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}

.lp-hero > .lp-hero-left > p {
    font-size: 1.15rem;
    color: var(--text-muted);
    line-height: 1.8;
    margin-bottom: 2.5rem;
    max-width: 520px;
}

.lp-hero-btns {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.lp-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.9rem 2rem;
    background: var(--primary);
    color: #fff;
    font-weight: 700;
    font-size: 1rem;
    border-radius: var(--radius);
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(59,130,246,0.35);
    transition: var(--transition);
}
.lp-btn-primary:hover { background: var(--blue-dark); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59,130,246,0.45); }

.lp-btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.9rem 2rem;
    background: var(--bg-card);
    color: var(--text-main);
    font-weight: 700;
    font-size: 1rem;
    border-radius: var(--radius);
    text-decoration: none;
    border: 1.5px solid var(--border-color);
    transition: var(--transition);
}
.lp-btn-ghost:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }

/* Hero Quick Stats */
.lp-quick-stats {
    display: flex;
    gap: 2.5rem;
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
    flex-wrap: wrap;
}
.lp-qstat strong { display: block; font-size: 1.5rem; font-weight: 900; color: var(--primary); }
.lp-qstat span   { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }

/* Hero Right Visual */
.lp-hero-right { position: relative; }
.lp-hero-img-main {
    width: 100%;
    height: 400px;
    background: linear-gradient(135deg, var(--blue-deeper), var(--blue));
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    position: relative;
}
.lp-hero-img-main::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.07'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.lp-hero-img-main span { font-size: 7rem; position: relative; z-index: 1; }

.lp-float-card {
    position: absolute;
    bottom: -1.5rem;
    right: -1.5rem;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    box-shadow: var(--shadow-md);
    animation: floatCard 3s ease-in-out infinite;
}
.lp-float-card i { font-size: 1.5rem; color: var(--green); }
.lp-float-card strong { display: block; font-size: 1.1rem; font-weight: 800; color: var(--text-header); }
.lp-float-card span   { font-size: 0.75rem; color: var(--text-muted); }
@keyframes floatCard { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

/* Trust Bar */
.lp-trust-bar {
    background: var(--bg-accent);
    color: var(--text-accent);
    padding: 0.9rem 0;
    overflow: hidden;
    white-space: nowrap;
    position: relative;
}
[data-theme='dark'] .lp-trust-bar { background: var(--bg-card); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }

.lp-trust-inner {
    display: inline-flex;
    gap: 3rem;
    animation: marquee 22s linear infinite;
}
.lp-trust-inner span { font-size: 0.82rem; font-weight: 700; letter-spacing: 0.04em; opacity: 0.85; }
.lp-trust-inner span i { margin-right: 0.4rem; color: var(--green); }
@keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

/* Features Section */
.lp-section { padding: 6rem 5%; max-width: 1280px; margin: 0 auto; }
.lp-section-header { text-align: center; margin-bottom: 4rem; }
.lp-section-tag {
    display: inline-block;
    background: rgba(59,130,246,0.1);
    color: var(--primary);
    border: 1px solid rgba(59,130,246,0.2);
    padding: 0.3rem 0.9rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 1rem;
}
.lp-section-header h2 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 900; color: var(--text-header); letter-spacing: -0.02em; margin-bottom: 0.75rem; }
.lp-section-header p  { color: var(--text-muted); font-size: 1.05rem; max-width: 560px; margin: 0 auto; line-height: 1.8; }

.lp-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.75rem;
}

.lp-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
.lp-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    opacity: 0;
    transition: var(--transition);
}
.lp-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); border-color: rgba(59,130,246,0.3); }
.lp-card:hover::before { opacity: 1; }
.lp-card-icon { font-size: 2.5rem; margin-bottom: 1.25rem; display: block; }
.lp-card h3 { font-size: 1.2rem; font-weight: 800; color: var(--text-header); margin-bottom: 0.75rem; }
.lp-card p  { color: var(--text-muted); line-height: 1.7; font-size: 0.95rem; }

/* Stats Accent Section */
.lp-stats {
    background: var(--bg-accent);
    color: var(--text-accent);
    padding: 5rem 5%;
    text-align: center;
    margin: 2rem 0;
}
.lp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 3rem;
    max-width: 1200px;
    margin: 0 auto;
}
.lp-stat-num {
    font-size: 3rem;
    font-weight: 900;
    color: #60a5fa;
    display: block;
    margin-bottom: 0.25rem;
}
.lp-stat-lbl { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.7; font-weight: 700; }

/* CTA Banner */
.lp-cta {
    background: linear-gradient(135deg, var(--blue-deeper), var(--blue));
    padding: 5rem 5%;
    text-align: center;
    color: white;
}
.lp-cta h2 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 900; margin-bottom: 1rem; }
.lp-cta p  { opacity: 0.8; margin-bottom: 2rem; font-size: 1.1rem; }

/* Section Alternate */
.lp-alt { background: var(--bg-body); }

</style>

<!-- ===== HERO ===== -->
<div class="lp-hero">
    <div class="lp-hero-left">
        <div class="lp-hero-badge">
            <i class="fa fa-check-circle"></i> Angola's #1 Electoral Platform
        </div>
        <h1>Votações Democráticas <br><span class="highlight">do Futuro</span></h1>
        <p>A Vox transforma processos eleitorais com tecnologia segura, transparente e acessível — de grémios estudantis a grandes associações nacionais.</p>
        <div class="lp-hero-btns">
            <a href="registo.php" class="lp-btn-primary">
                <i class="fa fa-rocket"></i> Criar Sala Eleitoral
            </a>
            <a href="contactos.php" class="lp-btn-ghost">
                Solicitar Demo <i class="fa fa-arrow-right"></i>
            </a>
        </div>
        <div class="lp-quick-stats">
            <div class="lp-qstat"><strong>10M+</strong><span>Votos</span></div>
            <div class="lp-qstat"><strong>99.9%</strong><span>Uptime</span></div>
            <div class="lp-qstat"><strong>18</strong><span>Províncias</span></div>
            <div class="lp-qstat"><strong>24/7</strong><span>Suporte</span></div>
        </div>
    </div>
    <div class="lp-hero-right">
        <div class="lp-hero-img-main">
            <span>🗳️</span>
        </div>
        <div class="lp-float-card">
            <i class="fa fa-line-chart"></i>
            <div>
                <strong>+340%</strong>
                <span>Participação em 2025</span>
            </div>
        </div>
    </div>
</div>

<!-- ===== TRUST BAR ===== -->
<div class="lp-trust-bar">
    <div class="lp-trust-inner">
        <span><i class="fa fa-shield"></i> Segurança Bancária</span>
        <span><i class="fa fa-check-circle"></i> Hash SHA-256</span>
        <span><i class="fa fa-users"></i> 50.000+ Eleitores</span>
        <span><i class="fa fa-lock"></i> Criptografia E2EE</span>
        <span><i class="fa fa-trophy"></i> Plataforma #1 em Angola</span>
        <span><i class="fa fa-globe"></i> 18 Províncias</span>
        <span><i class="fa fa-check"></i> ISO 27001 Ready</span>
        <!-- Duplicate for seamless loop -->
        <span><i class="fa fa-shield"></i> Segurança Bancária</span>
        <span><i class="fa fa-check-circle"></i> Hash SHA-256</span>
        <span><i class="fa fa-users"></i> 50.000+ Eleitores</span>
        <span><i class="fa fa-lock"></i> Criptografia E2EE</span>
        <span><i class="fa fa-trophy"></i> Plataforma #1 em Angola</span>
        <span><i class="fa fa-globe"></i> 18 Províncias</span>
        <span><i class="fa fa-check"></i> ISO 27001 Ready</span>
    </div>
</div>

<!-- ===== FEATURES ===== -->
<section style="padding: 6rem 5%; max-width: 1280px; margin: 0 auto;">
    <div class="lp-section-header">
        <span class="lp-section-tag">O Que Oferecemos</span>
        <h2>Soluções Eleitorais <span style="color: var(--primary);">Completas</span></h2>
        <p>Desenvolvidas para universidades, empresas, associações e organismos governamentais.</p>
    </div>
    <div class="lp-cards-grid">
        <div class="lp-card">
            <span class="lp-card-icon">⛓️</span>
            <h3>Integridade Verificável</h3>
            <p>Cada voto é registado com hash SHA-256. Qualquer alteração é imediatamente detetada e o resultado final é auditável por qualquer parte.</p>
        </div>
        <div class="lp-card">
            <span class="lp-card-icon">🔒</span>
            <h3>Criptografia E2EE</h3>
            <p>Segurança de nível militar em cada transação. As escolhas dos eleitores pertencem apenas a eles — até à contagem final.</p>
        </div>
        <div class="lp-card">
            <span class="lp-card-icon">📊</span>
            <h3>Resultados em Tempo Real</h3>
            <p>Acompanhe a participação com gráficos animados e relatórios de auditoria completos logo após o encerramento.</p>
        </div>
        <div class="lp-card">
            <span class="lp-card-icon">🆔</span>
            <h3>Identidade Verificada</h3>
            <p>Sistema avançado de verificação para garantir que cada pessoa vote uma única vez, prevenindo fraudes sem comprometer a privacidade.</p>
        </div>
        <div class="lp-card">
            <span class="lp-card-icon">⚡</span>
            <h3>Velocidade & Escalabilidade</h3>
            <p>Infraestrutura otimizada para suportar desde 50 votos num grémio a 50.000 numa eleição nacional simultânea.</p>
        </div>
        <div class="lp-card">
            <span class="lp-card-icon">🌙</span>
            <h3>Dark Mode Premium</h3>
            <p>Interface adaptável ao conforto visual do utilizador, com mudança instantânea sincronizada em todos os dispositivos.</p>
        </div>
    </div>
</section>

<!-- ===== STATS ===== -->
<section class="lp-stats">
    <div class="lp-stats-grid">
        <div>
            <span class="lp-stat-num">10M+</span>
            <div class="lp-stat-lbl">Votos Processados</div>
        </div>
        <div>
            <span class="lp-stat-num">99.9%</span>
            <div class="lp-stat-lbl">Uptime Garantido</div>
        </div>
        <div>
            <span class="lp-stat-num">18</span>
            <div class="lp-stat-lbl">Províncias Ativas</div>
        </div>
        <div>
            <span class="lp-stat-num">24/7</span>
            <div class="lp-stat-lbl">Suporte Local</div>
        </div>
    </div>
</section>

<!-- ===== CTA FINAL ===== -->
<section class="lp-cta">
    <h2>Pronto para Democratizar o seu Processo?</h2>
    <p>Crie a sua primeira sala eleitoral em menos de 5 minutos — sem cartão de crédito.</p>
    <div class="lp-hero-btns" style="justify-content: center;">
        <a href="registo.php" class="lp-btn-primary">
            <i class="fa fa-rocket"></i> Começar Gratuitamente
        </a>
        <a href="login.php" style="display:inline-flex; align-items:center; gap:.5rem; color:rgba(255,255,255,0.85); font-weight:700; text-decoration:none; padding: 0.9rem 1.5rem; border: 1.5px solid rgba(255,255,255,0.3); border-radius: var(--radius); transition: var(--transition);">
            Já tenho conta <i class="fa fa-arrow-right"></i>
        </a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
