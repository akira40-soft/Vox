<?php
$pageTitle = 'Preços - Vox';
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
        letter-spacing: -2px;
    }

    /* Pricing Grid */
    .section {
        padding: 5rem 5% 8rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .pricing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2.5rem;
        align-items: flex-start;
    }

    .price-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 3rem;
        border: 1px solid var(--gray-200);
        transition: all 0.4s;
        text-align: center;
        position: relative;
    }

    .price-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-lg);
    }

    .price-card.popular {
        border-color: var(--primary);
        box-shadow: var(--shadow-lg);
        transform: scale(1.05);
        z-index: 2;
    }

    .badge-popular {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--primary);
        color: white;
        padding: 0.4rem 1rem;
        border-radius: 2rem;
        font-size: 0.75rem;
        font-weight: 800;
    }

    .price-card h3 {
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        color: var(--gray-900);
    }

    .price-value {
        font-size: 3rem;
        font-weight: 900;
        margin-bottom: 0.5rem;
        color: var(--primary);
    }

    .price-value small {
        font-size: 1rem;
        color: var(--gray-500);
    }

    .price-card ul {
        list-style: none;
        text-align: left;
        margin: 2.5rem 0;
    }

    .price-card li {
        padding: 0.75rem 0;
        font-size: 0.95rem;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-100);
    }

    .price-card li::before {
        content: "✓";
        color: var(--success);
        font-weight: bold;
        margin-right: 0.75rem;
    }

    .btn-pricing {
        display: block;
        padding: 1rem;
        border-radius: 0.75rem;
        font-weight: 800;
        text-decoration: none;
        transition: 0.3s;
    }

    .btn-pricing-primary {
        background: var(--primary);
        color: white;
    }

    .btn-pricing-ghost {
        background: var(--gray-100);
        color: var(--gray-900);
    }

    @media (max-width: 768px) {
        .price-card.popular { transform: scale(1); }
        .pricing-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-hero">
    <h1>Planos <span style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Inspiradores</span></h1>
    <p style="max-width: 600px; margin: 0 auto; color: var(--gray-500); font-size: 1.1rem;">Escalável para cada tipo de organização, com transparência total de custos.</p>
</div>

<section class="section">
    <div class="pricing-grid">
        <!-- Free Plan -->
        <div class="price-card">
            <h3>Starter</h3>
            <div class="price-value">0 <small>Kz/mês</small></div>
            <p style="color:var(--gray-500); font-size: 0.9rem;">Para pequenas eleições</p>
            <ul>
                <li>Até 50 Eleitores</li>
                <li>1 Sala Eleitoral Ativa</li>
                <li>Resultados em Tempo Real</li>
                <li>Suporte Comunitário</li>
            </ul>
            <a href="registo.php" class="btn-pricing btn-pricing-ghost">Criar Conta Grátis</a>
        </div>

        <!-- Popular Plan -->
        <div class="price-card popular">
            <div class="badge-popular">MAIS POPULAR</div>
            <h3>Professional</h3>
            <div class="price-value">25.000 <small>Kz/mês</small></div>
            <p style="color:var(--gray-500); font-size: 0.9rem;">Ideal para instituições crescentes</p>
            <ul>
                <li>Até 5.000 Eleitores</li>
                <li>Votações Ilimitadas</li>
                <li>Audit Logs Completos</li>
                <li>Suporte Prioritário 24/7</li>
                <li>Exportação de Dados PDF/Excel</li>
            </ul>
            <a href="registo.php" class="btn-pricing btn-pricing-primary">Começar Agora</a>
        </div>

        <!-- Enterprise Plan -->
        <div class="price-card">
            <h3>Enterprise</h3>
            <div class="price-value" style="font-size: 2.25rem;">Sob Consulta</div>
            <p style="color:var(--gray-500); font-size: 0.9rem;">Segurança e escala máxima</p>
            <ul>
                <li>Eleitores Ilimitados</li>
                <li>Integração com Active Directory</li>
                <li>Gerente de Conta Dedicado</li>
                <li>SLA de 99.99% Garantido</li>
                <li>Consultoria de Implementação</li>
            </ul>
            <a href="contactos.php" class="btn-pricing btn-pricing-ghost">Falar com Vendas</a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
