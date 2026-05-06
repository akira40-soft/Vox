-- =========================================================
-- Vox Migration v3: Wizard campos adicionais
-- Adicionar nome_organizacao e tipo_votacao_sala
-- Executar na base de dados PostgreSQL do Render
-- =========================================================

-- Coluna para o Grupo/Organização opcional
ALTER TABLE salas_eleitorais 
    ADD COLUMN IF NOT EXISTS nome_organizacao VARCHAR(255) DEFAULT NULL;

-- Coluna para o Regime/Tipo de Votação
ALTER TABLE salas_eleitorais 
    ADD COLUMN IF NOT EXISTS tipo_votacao_sala VARCHAR(50) DEFAULT 'maioria_simples';

-- Comentários
COMMENT ON COLUMN salas_eleitorais.nome_organizacao IS 'Nome do grupo/organização que promove a eleição (substitui o nome do organizador no cabeçalho)';
COMMENT ON COLUMN salas_eleitorais.tipo_votacao_sala IS 'Regime eleitoral: maioria_simples, maioria_absoluta, proporcional, referendo';
