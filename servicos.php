<?php
$pageTitle = 'Serviços - Vox';
require_once 'includes/header.php';
?>

<style>
    /* Hero */
    .page-hero {
        padding: 6rem 5% 4rem;
        background: var(--gray-100);
        text-align: center;
        margin-top: -20px;
    }

    .page-hero h1 {
        font-size: clamp(2.25rem, 5vw, 3.5rem);
        font-weight: 900;
        margin-bottom: 1rem;
        letter-spacing: -1px;
    }

    /* Services Grid */
    .section {
        padding: 5rem 5% 8rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2.5rem;
    }

    .service-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 3rem;
        border: 1px solid var(--gray-200);
        transition: all 0.4s;
        position: relative;
        overflow: hidden;
    }

    .service-card:hover {
        transform: translateY(-8px);
        border-color: var(--primary);
        box-shadow: var(--shadow-lg);
    }

    .service-icon {
        font-size: 3rem;
        margin-bottom: 1.5rem;
        display: block;
    }

    .service-card h3 {
        font-size: 1.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
        color: var(--gray-900);
    }

    .service-card p {
        color: var(--gray-500);
        font-size: 1rem;
        margin-bottom: 2rem;
    }

    .tag {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: var(--gray-100);
        border-radius: 2rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--primary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 1rem;
    }

    /* Call to Action Bar */
    .cta-bar {
        background: var(--gray-900);
        color: white;
        padding: 5rem 5%;
        border-radius: var(--radius-lg);
        text-align: center;
        margin-top: 5rem;
    }

    .cta-bar h2 {
        font-size: 2.25rem;
        margin-bottom: 1rem;
        font-weight: 800;
    }

    @media (max-width: 768px) {
        .services-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-hero">
    <h1>Nossos <span style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Serviços</span></h1>
    <p style="max-width: 600px; margin: 0 auto; color: var(--gray-500); font-size: 1.1rem;">Soluções integradas de votação digital para cada necessidade institucional.</p>
</div>

<section class="section">
    <div class="services-grid">
        <div class="service-card">
            <span class="tag">Academia</span>
            <span class="service-icon">🎓</span>
            <h3>Eleições Académicas</h3>
            <p>Ideal para Universidades, Institutos e Escolas. Gestão completa de listas candidatas e votação simplificada para estudantes.</p>
        </div>
        <div class="service-card">
            <span class="tag">Corporativo</span>
            <span class="service-icon">🏢</span>
            <h3>Votações Corporativas</h3>
            <p>Decisões de conselho, assembleias gerais ou eleições de sindicatos com total segurança jurídica e técnica.</p>
        </div>
        <div class="service-card">
            <span class="tag">Social</span>
            <span class="service-icon">🤝</span>
            <h3>Associações & ONGs</h3>
            <p>Mobilize seus membros e tome decisões democráticas de forma remota e transparente, onde quer que eles estejam.</p>
        </div>
        <div class="service-card">
            <span class="tag">Pesquisa</span>
            <span class="service-icon">📊</span>
            <h3>Sondagens de Opinião</h3>
            <p>Ferramenta robusta para pesquisas de mercado e sondagens pré-eleitorais com análise estatística em tempo real.</p>
        </div>
        <div class="service-card">
            <span class="tag">Tecnologia</span>
            <span class="service-icon">🛠️</span>
            <h3>API de Integração</h3>
            <p>Integre nosso motor de votação auditável diretamente no seu portal ou aplicativo móvel via nossa API segura.</p>
        </div>
        <div class="service-card">
            <span class="tag">Consultoria</span>
            <span class="service-icon">💻</span>
            <h3>Apoio Digital</h3>
            <p>Ajudamos sua organização na transição do voto físico para o digital com suporte técnico especializado em Angola.</p>
        </div>
    </div>

    <div class="cta-bar">
        <h2>Pronto para Começar?</h2>
        <p style="margin-bottom: 2.5rem; opacity:0.8;">Junte-se a instituições que já modernizaram sua democracia com a Vox.</p>
        <a href="registo.php" class="btn-nav btn-nav-primary" style="padding: 1rem 3rem; font-size: 1rem;">Criar Conta Grátis</a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
