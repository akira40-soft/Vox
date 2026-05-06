-- ============================================================
-- Vox Electoral V2.0 - Migration Script
-- Run once against vox_db
-- ============================================================
USE vox_db;

-- 1. Add username to users
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS username VARCHAR(50) UNIQUE NULL AFTER nome_completo,
    ADD INDEX IF NOT EXISTS idx_username (username);

-- 2. Add phase timing fields to salas_eleitorais
ALTER TABLE salas_eleitorais
    ADD COLUMN IF NOT EXISTS data_campanha_inicio DATETIME NULL AFTER data_fim,
    ADD COLUMN IF NOT EXISTS data_campanha_fim    DATETIME NULL AFTER data_campanha_inicio,
    ADD COLUMN IF NOT EXISTS data_votacao_inicio  DATETIME NULL AFTER data_campanha_fim,
    ADD COLUMN IF NOT EXISTS data_votacao_fim     DATETIME NULL AFTER data_votacao_inicio,
    ADD COLUMN IF NOT EXISTS fase_atual ENUM('aguardando','campanha','votacao','estatisticas','encerrada','arquivada') NOT NULL DEFAULT 'aguardando' AFTER data_votacao_fim,
    ADD COLUMN IF NOT EXISTS arquivada_em DATETIME NULL AFTER fase_atual;

-- 3. Add papel (role) to convites_sala so we know if invite is for candidato or eleitor
ALTER TABLE convites_sala
    ADD COLUMN IF NOT EXISTS papel ENUM('candidato','eleitor') NOT NULL DEFAULT 'candidato' AFTER estado,
    ADD COLUMN IF NOT EXISTS username_convidado VARCHAR(50) NULL AFTER email_convidado;

-- 4. Expand post_reacoes tipo to include 'hate'
ALTER TABLE post_reacoes
    MODIFY COLUMN tipo ENUM('like','heart','clap','fire','hate') NOT NULL DEFAULT 'like';

-- 5. Table: sala_membros (track who entered each room for abstention stats)
CREATE TABLE IF NOT EXISTS sala_membros (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sala_id    INT NOT NULL,
    user_id    INT NOT NULL,
    papel      ENUM('eleitor','candidato','organizador') DEFAULT 'eleitor',
    entrou_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_membro (sala_id, user_id),
    FOREIGN KEY (sala_id) REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)           ON DELETE CASCADE,
    INDEX idx_sala_membros (sala_id)
) ENGINE=InnoDB;

-- 6. Table: reports_eleicao (denúncias durante votação)
CREATE TABLE IF NOT EXISTS reports_eleicao (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sala_id     INT NOT NULL,
    user_id     INT NOT NULL,
    candidato_id INT NULL,
    tipo        ENUM('fraude','irregularidade','intimidacao','outro') NOT NULL,
    descricao   TEXT NOT NULL,
    estado      ENUM('pendente','analisado','resolvido') NOT NULL DEFAULT 'pendente',
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id)      REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)       REFERENCES users(id)            ON DELETE CASCADE,
    FOREIGN KEY (candidato_id)  REFERENCES candidatos(id)       ON DELETE SET NULL,
    INDEX idx_sala_report (sala_id),
    INDEX idx_estado_report (estado)
) ENGINE=InnoDB;

ALTER TABLE campanhas
    ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER sala_id,
    MODIFY COLUMN candidato_id INT NULL;

-- Only add FK if it doesn't already exist (check via information_schema)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'vox_db' AND TABLE_NAME = 'campanhas' AND CONSTRAINT_NAME = 'fk_campanhas_user'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE campanhas ADD CONSTRAINT fk_campanhas_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration V2.0 concluída com sucesso!' AS status;
