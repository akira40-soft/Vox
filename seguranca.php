<?php
require_once 'config/helpers.php';
$pageTitle = 'Segurança - Vox | Proteção de Nível Militar';
require 'includes/header.php';
?>

<style>
    .page-hero {
        padding: 10rem 5% 5rem;
        background: var(--gray-900);
        color: white;
        text-align: center;
    }

    .page-hero h1 {
        font-size: clamp(2.5rem, 5vw, 3.5rem);
        font-weight: 900;
        margin-bottom: 1rem;
    }

    .section {
        padding: 8rem 5%;
        max-width: 1200px;
        margin: 0 auto;
    }

    .security-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 3rem;
        margin-bottom: 5rem;
    }

    .security-card {
        padding: 2.5rem;
        border-radius: var(--radius-lg, 1rem);
        border: 1px solid var(--border-color, #e5e7eb);
        background: var(--bg-card, #ffffff);
        transition: all 0.3s;
    }

    .security-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-lg, 0 10px 15px -3px rgba(0, 0, 0, 0.1));
    }

    .icon-box {
        width: 60px;
        height: 60px;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary);
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1.5rem;
    }

    .code-block {
        background: #0f172a;
        padding: 2.5rem;
        border-radius: var(--radius-lg, 1rem);
        color: #38bdf8;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.9rem;
        line-height: 1.8;
        border: 1px solid rgba(56, 189, 248, 0.2);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
    }
</style>

<div class="page-hero">
    <h1>Segurança <span style="color: var(--primary);">Inabalável</span></h1>
    <p style="max-width: 600px; margin: 0 auto; opacity: 0.8; font-size: 1.1rem;">Protegemos a soberania de cada voto com os mais altos padrões de criptografia global.</p>
</div>

<section class="section">
    <div class="security-grid">
        <div class="security-card">
            <div class="icon-box">🔒</div>
            <h3>Criptografia E2EE</h3>
            <p style="color:var(--text-muted, #6b7280);">Utilizamos AES-256 para encriptar cada voto no dispositivo do eleitor. Ninguém, nem mesmo a Vox, pode ver a escolha antes da contagem oficial.</p>
        </div>
        <div class="security-card">
            <div class="icon-box">⛓️</div>
            <h3>Auditoria Blockchain</h3>
            <p style="color:var(--text-muted, #6b7280);">Cada voto gera um hash único em uma ledger imutável. Resultados que não podem ser alterados, apagados ou manipulados.</p>
        </div>
        <div class="security-card">
            <div class="icon-box">🆔</div>
            <h3>Identidade Verificada</h3>
            <p style="color:var(--text-muted, #6b7280);">Integração com sistemas de biometria e MFA (Autenticação Multi-fator) para garantir que cada cidadão é quem diz ser.</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 4rem; align-items: center; margin-top: 5rem;">
        <div>
            <h2 style="font-size: 2rem; margin-bottom: 1.5rem; font-weight: 800;">Protocolos de Transparência</h2>
            <p style="color:var(--text-muted, #6b7280); margin-bottom: 1.5rem;">Cremos que a confiança nasce da transparência. Por isso, todos os nossos logs de auditoria são disponibilizados para observadores autorizados em tempo real.</p>
            <p style="color:var(--text-muted, #6b7280);">Nossa infraestrutura é protegida contra ataques DDoS de larga escala, garantindo que sua eleição nunca sofra interrupções, independentemente do tráfego.</p>
        </div>
        <div class="code-block">
            <span>[VOX-SECURITY-MONITOR]</span><br>
            <span>> Initializing Node Verification... OK</span><br>
            <span>> Encrypting Vote Payload (RSA-4096)... DONE</span><br>
            <span>> Generating Blockchain Hash... COMPLETED</span><br>
            <span>> Ledger Synchronization... 100% SUCCESS</span><br>
            <span style="color: var(--success, #10b981);">> INTEGRITY STATUS: UNCOMPROMISED</span>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
