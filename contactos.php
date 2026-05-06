<?php
$pageTitle = 'Contactos - Vox';
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

    /* Contact Section */
    .section {
        padding: 5rem 5% 8rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 5rem;
    }

    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 2.5rem;
    }

    .info-card {
        display: flex;
        gap: 1.5rem;
        align-items: flex-start;
    }

    .info-icon {
        width: 50px;
        height: 50px;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .info-data h4 {
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .info-data p {
        color: var(--gray-500);
        font-size: 0.95rem;
    }

    /* Form */
    .contact-form {
        background: var(--white);
        padding: 3.5rem;
        border-radius: var(--radius-lg);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow-lg);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid var(--gray-200);
        background: var(--gray-100);
        font-family: inherit;
        font-size: 1rem;
        transition: all 0.3s;
    }

    .form-group input:focus, .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    @media (max-width: 768px) {
        .contact-grid { grid-template-columns: 1fr; }
        .contact-form { padding: 2rem; }
    }
</style>

<div class="page-hero">
    <h1>Vamos <span style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Conversar?</span></h1>
    <p style="max-width: 600px; margin: 0 auto; color: var(--gray-500); font-size: 1.1rem;">Nossa equipa está pronta para apoiar seu próximo processo eleitoral.</p>
</div>

<section class="section">
    <div class="contact-grid">
        <div class="contact-info">
            <div class="info-card">
                <div class="info-icon">📧</div>
                <div class="info-data">
                    <h4>E-mail Suporte</h4>
                    <p>suporte@vox.ao</p>
                    <p style="font-size: 0.8rem; margin-top: 0.25rem;">Resposta em até 2 horas úteis.</p>
                </div>
            </div>
            <div class="info-card">
                <div class="info-icon">💬</div>
                <div class="info-data">
                    <h4>WhatsApp / Telemóvel</h4>
                    <p>+244 952 786 417</p>
                    <p style="font-size: 0.8rem; margin-top: 0.25rem;">Segunda a Sexta, 08h-18h.</p>
                </div>
            </div>
            <div class="info-card">
                <div class="info-icon">📍</div>
                <div class="info-data">
                    <h4>Escritório em Luanda</h4>
                    <p>Luanda, Angola</p>
                    <p style="font-size: 0.8rem; margin-top: 0.25rem;">Atendimento apenas por marcação.</p>
                </div>
            </div>
        </div>

        <form class="contact-form">
            <h3 style="margin-bottom: 2rem; font-size: 1.5rem; font-weight: 800;">Envie uma Mensagem</h3>
            
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" placeholder="Seu nome">
            </div>
            <div class="form-group">
                <label>E-mail Corporativo</label>
                <input type="email" placeholder="exemplo@organizacao.ao">
            </div>
            <div class="form-group">
                <label>Assunto</label>
                <select>
                    <option>Suporte Técnico</option>
                    <option>Vendas e Planos</option>
                    <option>Parcerias</option>
                    <option>Outro</option>
                </select>
            </div>
            <div class="form-group">
                <label>Mensagem</label>
                <textarea rows="5" placeholder="Como podemos ajudar?"></textarea>
            </div>
            
            <button type="button" class="btn btn-nav btn-nav-primary" style="width: 100%; padding: 1rem;">Enviar Solicitação</button>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
