<?php
require_once 'config/helpers.php';
$pageTitle = 'Sobre Nós - Vox | Nossa História e Missão';
require 'includes/header.php';
?>

<style>
    .page-hero {
        padding: 5rem 5% 4rem;
        background: var(--bg-card);
        border-bottom: 1px solid var(--border-color);
        text-align: center;
    }

    .page-hero h1 {
        font-size: clamp(2.5rem, 5vw, 3.5rem);
        font-weight: 900;
        margin-bottom: 1rem;
        letter-spacing: -1px;
        color: var(--text-header);
    }

    .section {
        padding: 6rem 5%;
        max-width: 1200px;
        margin: 0 auto;
    }

    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5rem;
        align-items: center;
    }

    .history-text h2 {
        font-size: 2.25rem;
        font-weight: 800;
        margin-bottom: 2rem;
        color: var(--primary);
    }

    .history-text p {
        font-size: 1.05rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
        line-height: 1.8;
    }

    .founder-card {
        background: var(--bg-card);
        border-radius: 1.5rem;
        padding: 3rem;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-lg);
        text-align: center;
    }

    .founder-img {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 50%;
        margin: 0 auto 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
    }

    .founder-card h3 {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-header);
        margin-bottom: 0.5rem;
    }

    .mission-vision {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
        margin-top: 5rem;
    }

    .card-mv {
        padding: 3rem;
        border-radius: 1.5rem;
        background: var(--bg-card); /* Changed from hard gray-900 to adapt to theme */
        border: 1px solid var(--border-color);
    }

    .card-mv p {
        color: var(--text-muted); /* Adapt naturally to theme */
    }

    .card-mv h3 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
        color: var(--text-header);
    }

    @media (max-width: 768px) {
        .grid-2, .mission-vision { grid-template-columns: 1fr; }
    }
</style>

<div class="page-hero">
    <h1>Conheça a <span style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">Nossa História</span></h1>
    <p style="max-width: 600px; margin: 0 auto; color: var(--text-muted); font-size: 1.1rem;">Transformando votações democráticas através da tecnologia segura, transparente e acessível.</p>
</div>

<section class="section">
    <div class="grid-2">
        <div class="history-text">
            <h2>Transformando a Democracia</h2>
            <p>A Vox existe para transformar a forma como o mundo vota. Acreditamos que toda organização, associação, universidade e governo deveria ter acesso a uma plataforma de votação moderna, segura e fácil de usar.</p>
            <p>Queremos democratizar o processo eleitoral e colocar o poder da transparência nas mãos de cada pessoa. Desde o nosso início em 2024, temos trabalhado incansavelmente para elevar os padrões de segurança em Angola.</p>
        </div>
        <div class="founder-card">
            <div class="founder-img">👤</div>
            <h3>Isaac Quarenta</h3>
            <p style="color: var(--primary); font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; margin-bottom: 1.5rem;">Fundador &amp; CEO</p>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Visionário apaixonado por tecnologia e democracia, Isaac lidera a Vox com o compromisso de trazer inovação para as instituições angolanas.</p>
        </div>
    </div>

    <div class="mission-vision">
        <div class="card-mv">
            <div style="font-size: 2rem; margin-bottom: 1.5rem;">🎯</div>
            <h3 style="color: var(--primary);">Nossa Missão</h3>
            <p style="line-height: 1.7;">Democratizar o acesso a votações seguras e transparentes em Angola, fornecendo tecnologia de ponta que proteja cada voto e fortaleça cada instituição.</p>
        </div>
        <div class="card-mv">
            <div style="font-size: 2rem; margin-bottom: 1.5rem;">👁️</div>
            <h3 style="color: var(--primary);">Nossa Visão</h3>
            <p style="line-height: 1.7;">Tornar-se a infraestrutura eleitoral digital padrão em Angola, reconhecida pela integridade absoluta e facilidade de uso inigualável.</p>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
